-- =============================================================
-- Migration v3 — Tabel-tabel yang hilang dari database.sql awal
-- Jalankan sekali. Aman dijalankan ulang (IF NOT EXISTS).
-- =============================================================

-- 1. Tabel event_files (digunakan di semua modul files/)
CREATE TABLE IF NOT EXISTS event_files (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  event_id      INT NOT NULL,
  nama_file     VARCHAR(300) NOT NULL,
  deskripsi     TEXT NULL,
  file_path     VARCHAR(500) NOT NULL,
  file_original VARCHAR(300) NULL,
  file_type     ENUM('rab','rundown','proposal','perijinan','jobdesk','undangan','lainnya') NOT NULL DEFAULT 'lainnya',
  file_size     BIGINT NULL,
  visibility    ENUM('all','inti','pic_only') NOT NULL DEFAULT 'all',
  can_edit_by   ENUM('all','inti','pic_only') NOT NULL DEFAULT 'inti',
  uploaded_by   INT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id)    REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)  ON DELETE SET NULL
);

-- 2. Tabel notifications
CREATE TABLE IF NOT EXISTS notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  judul      VARCHAR(300) NOT NULL,
  pesan      TEXT NULL,
  link       VARCHAR(500) NULL,
  tipe       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Tabel event_swot_questions (pertanyaan custom SWOT oleh PIC)
CREATE TABLE IF NOT EXISTS event_swot_questions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  event_id    INT NOT NULL,
  pertanyaan  TEXT NOT NULL,
  urutan      INT DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- 4. Tabel event_swot_answers (jawaban pertanyaan custom SWOT)
CREATE TABLE IF NOT EXISTS event_swot_answers (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  swot_id     INT NOT NULL,
  question_id INT NOT NULL,
  jawaban     TEXT NULL,
  FOREIGN KEY (swot_id)     REFERENCES event_swot(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES event_swot_questions(id) ON DELETE CASCADE
);

-- 5. Kolom swot_sent_at pada tabel events (jika belum ada)
ALTER TABLE events
  ADD COLUMN IF NOT EXISTS swot_sent_at DATETIME NULL AFTER link_dokumentasi;

-- 6. Tabel event_evaluasi (form evaluasi yang dibuat PIC)
CREATE TABLE IF NOT EXISTS event_evaluasi (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  event_id    INT NOT NULL,
  judul       VARCHAR(300) NOT NULL,
  deskripsi   TEXT NULL,
  created_by  INT NULL,
  deadline    DATE NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id)   REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE SET NULL
);

-- 7. Tabel evaluasi_pertanyaan
CREATE TABLE IF NOT EXISTS evaluasi_pertanyaan (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  evaluasi_id INT NOT NULL,
  pertanyaan  TEXT NOT NULL,
  tipe        ENUM('text','textarea','radio','checkbox','scale') NOT NULL DEFAULT 'textarea',
  opsi        TEXT NULL COMMENT 'JSON array untuk tipe radio/checkbox',
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  urutan      INT DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (evaluasi_id) REFERENCES event_evaluasi(id) ON DELETE CASCADE
);

-- 8. Tabel evaluasi_jawaban
CREATE TABLE IF NOT EXISTS evaluasi_jawaban (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  evaluasi_id    INT NOT NULL,
  pertanyaan_id  INT NOT NULL,
  user_id        INT NOT NULL,
  jawaban        TEXT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (evaluasi_id)   REFERENCES event_evaluasi(id)    ON DELETE CASCADE,
  FOREIGN KEY (pertanyaan_id) REFERENCES evaluasi_pertanyaan(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)       REFERENCES users(id)              ON DELETE CASCADE
);
