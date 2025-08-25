<?php
// Güvenli erişim tanımlanıyor
define('SECURE_ACCESS', true);

// Admin header'ı dahil ediyor, oturum, güvenlik ve ortak işlevleri işliyor
require_once 'admin_header.php';

// Sadece POST isteklerini izin ver
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mesaj'] = 'Geçersiz istek yöntemi.';
    $_SESSION['mesaj_tur'] = 'danger';
    safeRedirect('sirket_bilgiler.php');
    exit;
}

// Gerekli alanları doğrula
$required_fields = ['sirket_adi', 'adres', 'telefon', 'email'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $_SESSION['mesaj'] = 'Lütfen tüm zorunlu alanları doldurun.';
        $_SESSION['mesaj_tur'] = 'danger';
        safeRedirect('sirket_bilgiler.php');
        exit;
    }
}

// E-posta doğrulama
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $_SESSION['mesaj'] = 'Lütfen geçerli bir e-posta adresi girin.';
    $_SESSION['mesaj_tur'] = 'danger';
    safeRedirect('sirket_bilgiler.php');
    exit;
}

try {
    // Önce mevcut tabloyu sil (eğer varsa)
    $conn->query("DROP TABLE IF EXISTS sirket_bilgileri");
    
    // Tabloyu yeniden oluştur
    $create_table = "CREATE TABLE sirket_bilgileri (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sirket_adi VARCHAR(255) NOT NULL,
        adres TEXT NOT NULL,
        telefon VARCHAR(50) NOT NULL,
        email VARCHAR(255) NOT NULL,
        harita TEXT,
        calisma_saatleri TEXT,
        guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($create_table)) {
        throw new Exception("Tablo oluşturma hatası: " . $conn->error);
    }

    // Verileri hazırla
    $sirket_adi = trim($_POST['sirket_adi']);
    $adres = trim($_POST['adres']);
    $telefon = trim($_POST['telefon']);
    $email = trim($_POST['email']);
    $harita = trim($_POST['harita'] ?? '');
    $calisma_saatleri = trim($_POST['calisma_saatleri'] ?? '');

    // Yeni kayıt ekle
    $stmt = $conn->prepare("INSERT INTO sirket_bilgileri 
                           (sirket_adi, adres, telefon, email, harita, calisma_saatleri) 
                           VALUES (?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        throw new Exception("SQL hazırlama hatası: " . $conn->error);
    }

    $stmt->bind_param("ssssss", $sirket_adi, $adres, $telefon, $email, $harita, $calisma_saatleri);
    
    if (!$stmt->execute()) {
        throw new Exception("Veritabanı hatası: " . $stmt->error);
    }

    $_SESSION['mesaj'] = 'Şirket bilgileri başarıyla kaydedildi.';
    $_SESSION['mesaj_tur'] = 'success';

} catch (Exception $e) {
    error_log("Şirket bilgileri kaydetme hatası: " . $e->getMessage());
    $_SESSION['mesaj'] = 'Şirket bilgileri kaydedilirken bir hata oluştu: ' . $e->getMessage();
    $_SESSION['mesaj_tur'] = 'danger';
}

// Geri yönlendir
safeRedirect('sirket_bilgiler.php'); 