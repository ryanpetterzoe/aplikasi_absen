-- Jalankan ini jika DB sudah ada (tanpa import ulang)
CREATE TABLE IF NOT EXISTS app_settings (
  `key` VARCHAR(60) PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO app_settings (`key`,`value`) VALUES
('app_name','Absensi Sekolah'),
('app_logo',''),
('footer_text','©'),
('marquee_enabled','0'),
('marquee_text','')
ON DUPLICATE KEY UPDATE value=VALUES(value);
