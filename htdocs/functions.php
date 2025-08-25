<?php
/**
 * Güvenlik fonksiyonları kütüphanesi
 * Bu dosya, web uygulamasının güvenlik işlevlerini içerir
 */

// Zaman dilimini ayarla
date_default_timezone_set('Europe/Istanbul');

/**
 * IP kontrolü yapan fonksiyon
 * @param mysqli $veritabani Veritabanı bağlantısı
 * @param string $ip_adresi Kontrol edilecek IP adresi
 * @return array Engel durumu ve mesaj içeren dizi
 */
function ipKontrol($veritabani, $ip_adresi) {
    // IP engel tablosundan kontrol
    $sorgu = $veritabani->prepare("SELECT * FROM ip_engel WHERE ip_adresi = ? AND (engel_bitis_tarihi > NOW() OR engel_bitis_tarihi IS NULL)");
    $sorgu->bind_param("s", $ip_adresi);
    $sorgu->execute();
    $sonuc = $sorgu->get_result();

    if ($sonuc->num_rows > 0) {
        $engel = $sonuc->fetch_assoc();
        return [
            'engelli' => true,
            'mesaj' => 'Bu IP adresi engellenmiştir. ' . 
                      ($engel['engel_bitis_tarihi'] ? 'Engel bitiş tarihi: ' . $engel['engel_bitis_tarihi'] : 'Süresiz engellenmiştir.')
        ];
    }

    // Son 15 dakika içindeki başarısız giriş denemelerini kontrol et
    $sorgu = $veritabani->prepare("SELECT COUNT(*) as deneme_sayisi FROM giris_takip 
                                  WHERE ip_adresi = ? 
                                  AND durum = 'basarisiz' 
                                  AND giris_tarihi > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $sorgu->bind_param("s", $ip_adresi);
    $sorgu->execute();
    $sonuc = $sorgu->get_result();
    $satir = $sonuc->fetch_assoc();

    if ($satir['deneme_sayisi'] >= 5) {
        // Önce mevcut IP engelini kontrol et ve güncelle
        $sorgu = $veritabani->prepare("SELECT id FROM ip_engel WHERE ip_adresi = ?");
        $sorgu->bind_param("s", $ip_adresi);
        $sorgu->execute();
        $sonuc = $sorgu->get_result();

        if ($sonuc->num_rows > 0) {
            // IP zaten engelli listesinde, güncelle
            $sorgu = $veritabani->prepare("UPDATE ip_engel 
                                         SET engelleme_tarihi = NOW(),
                                             engel_bitis_tarihi = DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                                             engelleme_nedeni = ?
                                         WHERE ip_adresi = ?");
            $sorgu->bind_param("ss", $engelleme_nedeni, $ip_adresi);
        } else {
            // Yeni IP engeli ekle
            $engelleme_nedeni = '5 başarısız giriş denemesi';
            $sorgu = $veritabani->prepare("INSERT INTO ip_engel 
                                         (ip_adresi, engelleme_tarihi, engel_bitis_tarihi, engelleme_nedeni)
                                         VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?)");
            $sorgu->bind_param("ss", $ip_adresi, $engelleme_nedeni);
        }
        
        $sorgu->execute();

        // Güncel engel bitiş tarihini al
        $sorgu = $veritabani->prepare("SELECT engel_bitis_tarihi FROM ip_engel WHERE ip_adresi = ?");
        $sorgu->bind_param("s", $ip_adresi);
        $sorgu->execute();
        $sonuc = $sorgu->get_result();
        $engel = $sonuc->fetch_assoc();

        return [
            'engelli' => true,
            'mesaj' => 'Çok fazla başarısız giriş denemesi. IP adresiniz 30 dakika süreyle engellenmiştir. Engel bitiş tarihi: ' . $engel['engel_bitis_tarihi']
        ];
    }

    return ['engelli' => false, 'mesaj' => ''];
}

/**
 * Giriş denemelerini kaydeden fonksiyon
 * @param mysqli $veritabani Veritabanı bağlantısı
 * @param string $ip_adresi IP adresi
 * @param int|null $kullanici_id Kullanıcı ID (opsiyonel)
 * @param string $durum Giriş durumu ('basarili' veya 'basarisiz')
 */
function girisKaydet($veritabani, $ip_adresi, $kullanici_id = null, $durum = 'basarisiz') {
    $tarayici_bilgisi = $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmiyor';
    
    $sorgu = $veritabani->prepare("INSERT INTO giris_takip (ip_adresi, kullanici_id, durum, tarayici_bilgisi, giris_tarihi) 
                                  VALUES (?, ?, ?, ?, NOW())");
    $sorgu->bind_param("siss", $ip_adresi, $kullanici_id, $durum, $tarayici_bilgisi);
    $sorgu->execute();
}

/**
 * Şifre güvenlik kontrolü yapan fonksiyon
 * @param string $sifre Kontrol edilecek şifre
 * @return array Kontrol sonucu ve hata mesajları
 */
function sifreGuvenlikKontrol($sifre) {
    $hatalar = [];
    
    if (strlen($sifre) < 8) {
        $hatalar[] = 'Şifre en az 8 karakter uzunluğunda olmalıdır.';
    }
    if (!preg_match('/[A-Z]/', $sifre)) {
        $hatalar[] = 'Şifre en az bir büyük harf içermelidir.';
    }
    if (!preg_match('/[a-z]/', $sifre)) {
        $hatalar[] = 'Şifre en az bir küçük harf içermelidir.';
    }
    if (!preg_match('/[0-9]/', $sifre)) {
        $hatalar[] = 'Şifre en az bir rakam içermelidir.';
    }
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $sifre)) {
        $hatalar[] = 'Şifre en az bir özel karakter içermelidir.';
    }

    return [
        'gecerli' => empty($hatalar),
        'hatalar' => $hatalar
    ];
}

/**
 * Şifre geçmişini kontrol eden fonksiyon
 * @param mysqli $veritabani Veritabanı bağlantısı
 * @param int $kullanici_id Kullanıcı ID
 * @param string $yeni_sifre Yeni şifre
 * @return bool Şifre daha önce kullanılmış mı?
 */
function sifreGecmisKontrol($veritabani, $kullanici_id, $yeni_sifre) {
    $sorgu = $veritabani->prepare("SELECT sifre_hash FROM sifre_gecmisi WHERE kullanici_id = ? ORDER BY olusturulma_tarihi DESC LIMIT 5");
    $sorgu->bind_param("i", $kullanici_id);
    $sorgu->execute();
    $sonuc = $sorgu->get_result();

    while ($satir = $sonuc->fetch_assoc()) {
        if (password_verify($yeni_sifre, $satir['sifre_hash'])) {
            return true; // Şifre daha önce kullanılmış
        }
    }

    return false; // Şifre daha önce kullanılmamış
}

/**
 * Yeni şifreyi geçmişe kaydeden fonksiyon
 * @param mysqli $veritabani Veritabanı bağlantısı
 * @param int $kullanici_id Kullanıcı ID
 * @param string $sifre_hash Şifre hash'i
 */
function sifreGecmisiKaydet($veritabani, $kullanici_id, $sifre_hash) {
    $sorgu = $veritabani->prepare("INSERT INTO sifre_gecmisi (kullanici_id, sifre_hash) VALUES (?, ?)");
    $sorgu->bind_param("is", $kullanici_id, $sifre_hash);
    $sorgu->execute();
}

/**
 * Giriş denemelerini sıfırlayan fonksiyon
 * @param mysqli $veritabani Veritabanı bağlantısı
 * @param string|null $ip_adresi Belirli bir IP için sıfırlama (opsiyonel)
 */
function girisDenemeleriniSifirla($veritabani, $ip_adresi = null) {
    // Session değişkenlerini temizle
    if (isset($_SESSION['giris_deneme'])) unset($_SESSION['giris_deneme']);
    if (isset($_SESSION['son_deneme'])) unset($_SESSION['son_deneme']);
    
    // Veritabanı kayıtlarını temizle
    if ($ip_adresi) {
        // Belirli IP için temizle
        $sorgu = $veritabani->prepare("DELETE FROM ip_engel WHERE ip_adresi = ?");
        $sorgu->bind_param("s", $ip_adresi);
        $sorgu->execute();
        
        $sorgu = $veritabani->prepare("DELETE FROM giris_takip WHERE ip_adresi = ?");
        $sorgu->bind_param("s", $ip_adresi);
        $sorgu->execute();
    } else {
        // Tüm kayıtları temizle
        $veritabani->query("TRUNCATE TABLE ip_engel");
        $veritabani->query("TRUNCATE TABLE giris_takip");
    }
}

// Bu fonksiyon, adları doğru şekilde büyük harfle başlatmak için kullanılır
function properCapitalize($name) {
    // Adı boşluk veya kısa çizgiyle böl
    $parts = preg_split('/[\s-]+/', $name);
    
    // Her bölümü büyük harfle başlat
    $parts = array_map(function($part) {
        // İlk harfi küçük harfle başlat, sonra büyük harfle başlat
        $part = mb_strtolower($part, 'UTF-8');
        return mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8') . 
               mb_substr($part, 1, null, 'UTF-8');
    }, $parts);
    
    // Orijinal ayırıcılarla birleştir
    $name = $name;
    foreach ($parts as $part) {
        $pos = mb_stripos($name, $part, 0, 'UTF-8');
        if ($pos !== false) {
            $name = mb_substr($name, 0, $pos, 'UTF-8') . $part . 
                   mb_substr($name, $pos + mb_strlen($part, 'UTF-8'), null, 'UTF-8');
        }
    }
    
    return $name;
} 