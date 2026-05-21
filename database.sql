CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  no_wa VARCHAR(20),
  divisi VARCHAR(100),
  jabatan VARCHAR(100),
  role_sistem ENUM('superadmin','staff') DEFAULT 'staff',
  status ENUM('aktif','nonaktif') DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(200) NOT NULL,
  level ENUM('TK','SD','SMP','Umum') NOT NULL,
  tanggal_mulai DATE NOT NULL,
  tanggal_selesai DATE NOT NULL,
  lokasi VARCHAR(200),
  deskripsi TEXT,
  status ENUM('draft','pengajuan','disetujui_manager','proposal_dibuat','rab_diajukan','perijinan','disetujui','berlangsung','selesai','ditolak') DEFAULT 'draft',
  pic_id INT,
  template_dari_event_id INT NULL,
  wa_group_link VARCHAR(500) NULL,
  link_dokumentasi VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (pic_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (template_dari_event_id) REFERENCES events(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS event_panitia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  peran_acara ENUM('pic','panitia_inti','panitia_support') NOT NULL,
  bagian VARCHAR(100) NULL,
  is_event_admin TINYINT(1) DEFAULT 0,
  is_double_job TINYINT(1) DEFAULT 0,
  status_konfirmasi ENUM('pending','bersedia','tidak_bisa') DEFAULT 'pending',
  token_konfirmasi VARCHAR(64) NULL,
  token_expires_at DATETIME NULL,
  confirmed_at DATETIME NULL,
  catatan VARCHAR(300) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_event_user (event_id, user_id)
);

CREATE TABLE IF NOT EXISTS approvals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  approver_id INT NULL,
  tipe_approver ENUM('manager_tk','manager_sd','manager_smp','sekretaris','bendahara','kehumasan','kepala_sekolah') NOT NULL,
  urutan INT DEFAULT 1,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  catatan TEXT NULL,
  approved_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS proposals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_name VARCHAR(200) NOT NULL,
  dibuat_oleh INT NULL,
  catatan TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS rab (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_name VARCHAR(200) NOT NULL,
  total_anggaran DECIMAL(15,2) NULL,
  status ENUM('draft','diajukan','disetujui','ditolak') DEFAULT 'draft',
  submitted_by INT NULL,
  submitted_at DATETIME NULL,
  catatan TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS perijinan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  jenis_izin VARCHAR(200) NOT NULL,
  ditujukan_ke VARCHAR(200) NULL,
  status ENUM('perlu_izin','diproses','diterima','ditolak') DEFAULT 'perlu_izin',
  handled_by INT NULL,
  file_path VARCHAR(500) NULL,
  keterangan TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS event_swot (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  strength TEXT NULL,
  weakness TEXT NULL,
  opportunity TEXT NULL,
  threat TEXT NULL,
  saran TEXT NULL,
  is_anonim TINYINT(1) DEFAULT 0,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_swot (event_id, user_id)
);

CREATE TABLE IF NOT EXISTS event_checklist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  item VARCHAR(300) NOT NULL,
  kategori VARCHAR(100) NULL,
  urutan INT DEFAULT 0,
  is_done TINYINT(1) DEFAULT 0,
  done_by INT NULL,
  done_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (done_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO users (nama, email, password, divisi, jabatan, role_sistem) VALUES
('Administrator', 'admin@sekolah.sch.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'IT', 'Admin Sistem', 'superadmin');
