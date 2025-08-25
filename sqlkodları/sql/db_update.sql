-- Add new security columns to kullanicilar table
ALTER TABLE kullanicilar
ADD COLUMN reset_attempts INT DEFAULT 0,
ADD COLUMN last_reset_request DATETIME,
ADD COLUMN last_successful_reset DATETIME,
ADD INDEX idx_email (email),
ADD INDEX idx_reset_token (reset_token);

-- Update existing reset_token column to support longer hashed tokens
ALTER TABLE kullanicilar
MODIFY COLUMN reset_token VARCHAR(255); 