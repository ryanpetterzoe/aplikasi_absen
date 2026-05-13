-- Jalankan jika DB sudah terlanjur dibuat dan belum ada kolom photo_path
ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) NULL AFTER address;
