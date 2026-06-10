-- Jalankan file ini SEKALI di database yang sudah berjalan
-- Menambahkan kolom deadline, pj, status ke tabel event_checklist

ALTER TABLE event_checklist
  ADD COLUMN IF NOT EXISTS deadline DATE NULL AFTER item,
  ADD COLUMN IF NOT EXISTS pj VARCHAR(150) NULL AFTER deadline,
  ADD COLUMN IF NOT EXISTS status ENUM('belum','on_progres','selesai') DEFAULT 'belum' AFTER pj;

-- Sinkronisasi data lama: kalau is_done=1, set status='selesai'
UPDATE event_checklist SET status = 'selesai' WHERE is_done = 1 AND status = 'belum';
