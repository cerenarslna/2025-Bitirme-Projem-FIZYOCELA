-- Test veritabanı için örnek veriler
-- Oluşturma Tarihi: 2024

-- Mevcut verileri temizle (foreign key kısıtlamalarını dikkate alarak ters sırada)
SET FOREIGN_KEY_CHECKS = 0; -- Geçici olarak foreign key kontrollerini devre dışı bırak

TRUNCATE TABLE ip_engel;
TRUNCATE TABLE giris_takip;
TRUNCATE TABLE slider_ayarlari;
TRUNCATE TABLE slider_items;
TRUNCATE TABLE yorum_begeniler;
TRUNCATE TABLE yorumlar;
TRUNCATE TABLE gonderi_begeniler;
TRUNCATE TABLE gonderi_favoriler;
TRUNCATE TABLE gonderi_puanlar;
TRUNCATE TABLE gonderiler;
TRUNCATE TABLE site_ayarlari;
TRUNCATE TABLE sirket_bilgileri;
TRUNCATE TABLE sifre_gecmisi;
TRUNCATE TABLE kullanicilar;

SET FOREIGN_KEY_CHECKS = 1; -- Foreign key kontrollerini tekrar etkinleştir

-- Kullanıcılar için test verileri (Önce parent tabloya veri eklemeliyiz)
INSERT INTO kullanicilar (kullanici_adi, isim, soyisim, email, sifre, rol, olusturulma_tarihi) VALUES
('superadmin', 'Süper', 'Yönetici', 'superadmin@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN.jf/5Kz.N.PKHKUTOvK', 'superuser', NOW()),
('admin1', 'Admin', 'User', 'admin@test.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN.jf/5Kz.N.PKHKUTOvK', 'admin', NOW()),
('fizyoterapist1', 'Ahmet', 'Yılmaz', 'ahmet@test.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN.jf/5Kz.N.PKHKUTOvK', 'fizyoterapist', NOW()),
('fizyoterapist2', 'Ayşe', 'Demir', 'ayse@test.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN.jf/5Kz.N.PKHKUTOvK', 'fizyoterapist', NOW()),
('user1', 'Mehmet', 'Kaya', 'mehmet@test.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN.jf/5Kz.N.PKHKUTOvK', 'user', NOW()),
('user2', 'Fatma', 'Şahin', 'fatma@test.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN.jf/5Kz.N.PKHKUTOvK', 'user', NOW());

-- Şirket bilgileri için test verisi
INSERT INTO sirket_bilgileri (sirket_adi, adres, telefon, email, harita, calisma_saatleri) VALUES
('FizyoCela', 'Örnek Mahallesi, Sağlık Sokak No:123\nÇankaya/Ankara', '0312 123 4567', 'info@fizyocela.com', '<iframe src="https://www.google.com/maps/embed?..." width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 'Pazartesi - Cuma: 09:00 - 18:00\nCumartesi: 09:00 - 14:00\nPazar: Kapalı');

-- Gönderiler için test verileri (kullanıcılar tablosuna bağımlı)
INSERT INTO gonderiler (baslik, icerik, yazar_id, blog, egzersiz, olusturulma_tarihi) 
SELECT 
    'Fizyoterapi Nedir?', 
    '<p>Fizyoterapi, fiziksel rahatsızlıkların tedavisinde kullanılan bir sağlık hizmetidir...</p>', 
    id, 
    1, 
    0, 
    NOW() 
FROM kullanicilar WHERE kullanici_adi = 'fizyoterapist1';

INSERT INTO gonderiler (baslik, icerik, yazar_id, blog, egzersiz, olusturulma_tarihi) 
SELECT 
    'Evde Yapılabilecek Egzersizler', 
    '<p>Evde kolayca yapabileceğiniz temel egzersizler...</p>', 
    id, 
    0, 
    1, 
    NOW() 
FROM kullanicilar WHERE kullanici_adi = 'fizyoterapist1';

INSERT INTO gonderiler (baslik, icerik, yazar_id, blog, egzersiz, olusturulma_tarihi) 
SELECT 
    'Bel Ağrısı İçin Öneriler', 
    '<p>Bel ağrısını hafifletmek için öneriler ve egzersizler...</p>', 
    id, 
    1, 
    1, 
    NOW() 
FROM kullanicilar WHERE kullanici_adi = 'fizyoterapist2';

INSERT INTO gonderiler (baslik, icerik, yazar_id, blog, egzersiz, olusturulma_tarihi) 
SELECT 
    'Boyun Fıtığı Egzersizleri', 
    '<p>Boyun fıtığı için önerilen egzersiz hareketleri...</p>', 
    id, 
    0, 
    1, 
    NOW() 
FROM kullanicilar WHERE kullanici_adi = 'fizyoterapist2';

INSERT INTO gonderiler (baslik, icerik, yazar_id, blog, egzersiz, olusturulma_tarihi) 
SELECT 
    'Sağlıklı Yaşam İpuçları', 
    '<p>Günlük hayatta uygulayabileceğiniz sağlıklı yaşam önerileri...</p>', 
    id, 
    1, 
    0, 
    NOW() 
FROM kullanicilar WHERE kullanici_adi = 'fizyoterapist1';

-- Yorumlar için test verileri (gönderiler ve kullanıcılar tablolarına bağımlı)
INSERT INTO yorumlar (gonderi_id, kullanici_id, icerik, olusturulma_tarihi, parent_id, sentiment)
SELECT 
    g.id,
    u.id,
    'Çok faydalı bir yazı olmuş, teşekkürler.',
    NOW(),
    NULL,
    'Pozitif'
FROM gonderiler g
JOIN kullanicilar u ON u.kullanici_adi = 'user1'
WHERE g.baslik = 'Fizyoterapi Nedir?'
LIMIT 1;

INSERT INTO yorumlar (gonderi_id, kullanici_id, icerik, olusturulma_tarihi, parent_id, sentiment)
SELECT 
    g.id,
    u.id,
    'Ben de bu tedaviden fayda gördüm.',
    NOW(),
    (SELECT id FROM yorumlar ORDER BY id ASC LIMIT 1),
    'Pozitif'
FROM gonderiler g
JOIN kullanicilar u ON u.kullanici_adi = 'user2'
WHERE g.baslik = 'Fizyoterapi Nedir?'
LIMIT 1;

-- Yorum beğenileri için test verileri
INSERT INTO yorum_begeniler (yorum_id, kullanici_id, olusturulma_tarihi)
SELECT 
    y.id,
    u.id,
    NOW()
FROM yorumlar y
JOIN kullanicilar u ON u.kullanici_adi IN ('fizyoterapist1', 'fizyoterapist2')
LIMIT 2;

-- Site ayarları için test verisi
INSERT INTO site_ayarlari (setting_key, setting_value) VALUES
('site_about', 'FizyoCela, profesyonel fizyoterapi hizmetleri sunan bir sağlık kuruluşudur...');

-- Slider öğeleri için test verileri
INSERT INTO slider_items (baslik, aciklama, image, sira, aktif, gonderi_id, type) 
SELECT 
    'Fizyoterapi Hizmetlerimiz',
    'Uzman kadromuzla yanınızdayız',
    'slider1.jpg',
    1,
    1,
    id,
    'gonderi'
FROM gonderiler 
WHERE baslik = 'Fizyoterapi Nedir?'
LIMIT 1;

-- Slider ayarları için test verisi
INSERT INTO slider_ayarlari (gecis_suresi) VALUES (5000);

-- Güvenlik kayıtları için test verileri
INSERT INTO giris_takip (ip_adresi, kullanici_id, durum, giris_tarihi)
SELECT 
    '192.168.1.1',
    id,
    'basarili',
    NOW()
FROM kullanicilar 
WHERE kullanici_adi = 'superadmin'
LIMIT 1;

-- IP engelleme için test verisi
INSERT INTO ip_engel (ip_adresi, engel_tarihi, engel_bitis_tarihi, engelleme_nedeni) VALUES
('192.168.1.100', NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'Çok fazla başarısız giriş denemesi'); 