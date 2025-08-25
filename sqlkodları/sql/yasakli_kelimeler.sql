-- Yasaklı kelimeler tablosu
CREATE TABLE IF NOT EXISTS yasakli_kelimeler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kelime VARCHAR(50) NOT NULL,
    olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (kelime)
);

-- Yorumlar tablosuna flag sütunu ekleme
ALTER TABLE yorumlar 
ADD COLUMN flag BOOLEAN DEFAULT FALSE,
ADD COLUMN flag_nedeni VARCHAR(255) DEFAULT NULL; 