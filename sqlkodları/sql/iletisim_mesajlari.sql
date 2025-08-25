-- İletişim mesajları tablosunu oluştur
CREATE TABLE IF NOT EXISTS iletisim_mesajlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad VARCHAR(50) NOT NULL,
    soyad VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefon VARCHAR(20) NOT NULL,
    konu VARCHAR(255) NOT NULL,
    mesaj TEXT NOT NULL,
    ip_adresi VARCHAR(45) NOT NULL,
    tarih DATETIME NOT NULL,
    durum ENUM('beklemede', 'okundu', 'yanitlandi', 'arsivlendi') DEFAULT 'beklemede',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 