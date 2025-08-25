CREATE TABLE IF NOT EXISTS `sirket_bilgileri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `adres` text NOT NULL,
  `telefon` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `calisma_saatleri` text NOT NULL,
  `guncelleme_tarihi` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default values
INSERT INTO `sirket_bilgileri` (`adres`, `telefon`, `email`, `calisma_saatleri`, `guncelleme_tarihi`) 
VALUES ('Örnek Mahallesi, Örnek Sokak No:123\nÖrnek/İSTANBUL', '+90 (212) 123 45 67', 'info@fizyocela.com', 'Pazartesi - Cumartesi: 09:00 - 18:00\nPazar: Kapalı', NOW())
ON DUPLICATE KEY UPDATE `guncelleme_tarihi` = NOW(); 