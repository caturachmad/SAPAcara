-- =============================================================
-- Migration v2 — Admin Role, Jabatan Sistem, Settings, Permissions
-- Jalankan sekali di MySQL. Aman dijalankan ulang (IF NOT EXISTS / IGNORE).
-- =============================================================

-- 1. Tambah kolom jabatan_sistem ke tabel users (jika belum ada)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS jabatan_sistem
    ENUM('staff','bendahara_tertinggi','kepala_sekolah','manager_tk','manager_sd','manager_smp')
    NOT NULL DEFAULT 'staff'
    AFTER role_sistem;

-- 2. Tambah nilai 'admin' ke ENUM role_sistem
--    MySQL tidak bisa IF NOT EXISTS pada MODIFY COLUMN, jadi statement ini
--    idempotent: kalau 'admin' sudah ada, MODIFY tidak merusak data yang ada.
ALTER TABLE users
  MODIFY COLUMN role_sistem
    ENUM('superadmin','admin','staff')
    NOT NULL DEFAULT 'staff';

-- 3. Tabel pengaturan sistem (key-value store)
CREATE TABLE IF NOT EXISTS system_settings (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  setting_key   VARCHAR(100)  NOT NULL,
  setting_value TEXT          NULL,
  setting_type  ENUM('text','textarea','boolean','select') NOT NULL DEFAULT 'text',
  setting_group VARCHAR(50)   NOT NULL DEFAULT 'general',
  label         VARCHAR(200)  NOT NULL DEFAULT '',
  description   VARCHAR(500)  NULL,
  updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_setting_key (setting_key)
);

-- 4. Tabel role_permissions (matrix per role per fitur)
CREATE TABLE IF NOT EXISTS role_permissions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  role       ENUM('superadmin','admin','staff') NOT NULL,
  feature    VARCHAR(100) NOT NULL,
  is_allowed TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_role_feature (role, feature)
);

-- 5. Data default: system_settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, setting_group, label, description) VALUES
  ('app_name',       'SAPAcara',                             'text',    'general', 'Nama Aplikasi',       'Nama yang tampil di header dan login'),
  ('app_subtitle',   'Sistem Manajemen Acara Sekolah',       'text',    'general', 'Subtitle Aplikasi',   'Deskripsi singkat di bawah nama aplikasi'),
  ('school_name',    'Sekolah Alam Bandung',                 'text',    'general', 'Nama Sekolah',        'Nama institusi'),
  ('maintenance_mode','0',                                   'boolean', 'general', 'Mode Maintenance',    'Aktifkan untuk mencegah login selain superadmin'),
  ('maintenance_msg','Sistem sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.', 'textarea', 'general', 'Pesan Maintenance', 'Pesan yang tampil saat maintenance aktif');

-- 6. Data default: role_permissions
--    superadmin selalu allowed = 1 (dikunci di UI, tidak bisa diubah)
--    admin dan staff bisa dikonfigurasi

INSERT IGNORE INTO role_permissions (role, feature, is_allowed) VALUES
  -- superadmin: semua fitur aktif (dikunci, tidak bisa dimatikan via UI)
  ('superadmin', 'manajemen_sdm',      1),
  ('superadmin', 'import_excel',       1),
  ('superadmin', 'pengaturan_sistem',  1),
  ('superadmin', 'kelola_approval',    1),
  ('superadmin', 'buat_acara',         1),
  ('superadmin', 'admin_panel',        1),

  -- admin: default — boleh approval dan admin panel, tidak bisa SDM/import/settings
  ('admin',      'manajemen_sdm',      0),
  ('admin',      'import_excel',       0),
  ('admin',      'pengaturan_sistem',  0),
  ('admin',      'kelola_approval',    1),
  ('admin',      'buat_acara',         1),
  ('admin',      'admin_panel',        1),

  -- staff: hanya buat acara
  ('staff',      'manajemen_sdm',      0),
  ('staff',      'import_excel',       0),
  ('staff',      'pengaturan_sistem',  0),
  ('staff',      'kelola_approval',    0),
  ('staff',      'buat_acara',         1),
  ('staff',      'admin_panel',        0);
