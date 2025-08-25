-- Add new fields to kullanicilar table
ALTER TABLE kullanicilar
ADD COLUMN hakkinda TEXT DEFAULT NULL COMMENT 'Kullanıcı biyografisi',
ADD COLUMN uzmanlik_alanlari TEXT DEFAULT NULL COMMENT 'Fizyoterapist uzmanlık alanları',
ADD COLUMN egitim TEXT DEFAULT NULL COMMENT 'Eğitim bilgileri',
ADD COLUMN telefon VARCHAR(20) DEFAULT NULL COMMENT 'İletişim telefonu',
ADD COLUMN calisma_saatleri TEXT DEFAULT NULL COMMENT 'Çalışma saatleri',
ADD COLUMN konum VARCHAR(255) DEFAULT NULL COMMENT 'Çalışma lokasyonu';

-- Add indexes for performance
ALTER TABLE kullanicilar
ADD INDEX idx_rol (rol),
ADD INDEX idx_olusturulma_tarihi (olusturulma_tarihi); 