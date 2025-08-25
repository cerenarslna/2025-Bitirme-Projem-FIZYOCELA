-- Create the comment likes table
CREATE TABLE IF NOT EXISTS yorum_begeniler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    yorum_id INT NOT NULL,
    kullanici_id INT NOT NULL,
    olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (yorum_id) REFERENCES yorumlar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (yorum_id, kullanici_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 