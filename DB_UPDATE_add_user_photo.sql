-- Jalankan jika database sudah ada dan kolom foto user belum ada
ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) NULL AFTER address;
