-- Create post likes table
CREATE TABLE IF NOT EXISTS gonderi_begeniler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gonderi_id INT NOT NULL,
    kullanici_id INT NOT NULL,
    olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gonderi_id) REFERENCES gonderiler(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (gonderi_id, kullanici_id)
);

-- Create post favorites table
CREATE TABLE IF NOT EXISTS gonderi_favoriler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gonderi_id INT NOT NULL,
    kullanici_id INT NOT NULL,
    olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gonderi_id) REFERENCES gonderiler(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (gonderi_id, kullanici_id)
);

-- Create post ratings table
CREATE TABLE IF NOT EXISTS gonderi_puanlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gonderi_id INT NOT NULL,
    kullanici_id INT NOT NULL,
    puan TINYINT NOT NULL CHECK (puan BETWEEN 1 AND 5),
    olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gonderi_id) REFERENCES gonderiler(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rating (gonderi_id, kullanici_id)
);