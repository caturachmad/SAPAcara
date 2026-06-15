-- ═══════════════════════════════════════════════════════════════
-- SAPAcara — database.sql
-- Version: 2.0 (PR-03 rebuild — bersih dari data user real)
-- Generated: 2026-06-11
-- Seed: superadmin default, divisions, system_settings, role_permissions
-- ═══════════════════════════════════════════════════════════════

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ═══════════════════════════════════════════════════════════════
-- SCHEMA
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE `approvals` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `approver_id` int DEFAULT NULL,
  `tipe_approver` enum('manager_tk','manager_sd','manager_smp','sekretaris','bendahara','kehumasan','kepala_sekolah') COLLATE utf8mb4_unicode_ci NOT NULL,
  `urutan` int DEFAULT '1',
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `divisions` (
  `id` int NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `urutan` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evaluasi_jawaban` (
  `id` int NOT NULL,
  `evaluasi_id` int NOT NULL,
  `pertanyaan_id` int NOT NULL,
  `user_id` int NOT NULL,
  `jawaban` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evaluasi_pertanyaan` (
  `id` int NOT NULL,
  `evaluasi_id` int NOT NULL,
  `pertanyaan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe` enum('text','textarea','rating','pilihan_ganda','ya_tidak') COLLATE utf8mb4_unicode_ci DEFAULT 'textarea',
  `opsi` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON untuk pilihan_ganda',
  `is_required` tinyint(1) DEFAULT '1',
  `urutan` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events` (
  `id` int NOT NULL,
  `judul` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` enum('TK','SD','SMP','Umum') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `lokasi` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','pengajuan','disetujui_manager','proposal_dibuat','rab_diajukan','perijinan','disetujui','berlangsung','selesai','ditolak') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `pic_id` int DEFAULT NULL,
  `template_dari_event_id` int DEFAULT NULL,
  `wa_group_link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `swot_sent_at` timestamp NULL DEFAULT NULL,
  `link_dokumentasi` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `event_checklist` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `item` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deadline` date DEFAULT NULL,
  `pj` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('belum','on_progres','selesai') COLLATE utf8mb4_unicode_ci DEFAULT 'belum',
  `kategori` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urutan` int DEFAULT '0',
  `is_done` tinyint(1) DEFAULT '0',
  `done_by` int DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `event_evaluasi` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `judul` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `deadline` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `event_files` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `nama_file` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_original` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` enum('rab','rundown','proposal','perijinan','jobdesk','undangan','lainnya') COLLATE utf8mb4_unicode_ci DEFAULT 'lainnya',
  `file_size` int DEFAULT NULL,
  `visibility` enum('all','inti','pic_only') COLLATE utf8mb4_unicode_ci DEFAULT 'inti',
  `can_edit_by` enum('pic_only','inti','all') COLLATE utf8mb4_unicode_ci DEFAULT 'inti',
  `uploaded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `event_panitia` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `user_id` int NOT NULL,
  `peran_acara` enum('pic','panitia_inti','panitia_support') COLLATE utf8mb4_unicode_ci NOT NULL,
  `bagian` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_event_admin` tinyint(1) DEFAULT '0',
  `is_double_job` tinyint(1) DEFAULT '0',
  `status_konfirmasi` enum('pending','bersedia','tidak_bisa') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `token_konfirmasi` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `catatan` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `event_swot` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `user_id` int NOT NULL,
  `strength` text COLLATE utf8mb4_unicode_ci,
  `weakness` text COLLATE utf8mb4_unicode_ci,
  `opportunity` text COLLATE utf8mb4_unicode_ci,
  `threat` text COLLATE utf8mb4_unicode_ci,
  `saran` text COLLATE utf8mb4_unicode_ci,
  `is_anonim` tinyint(1) DEFAULT '0',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `event_swot_answers` (
  `id` int NOT NULL,
  `swot_id` int NOT NULL,
  `question_id` int NOT NULL,
  `jawaban` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `event_swot_questions` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `pertanyaan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `urutan` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `judul` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pesan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `tipe` enum('info','success','warning','danger') COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `perijinan` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `jenis_izin` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ditujukan_ke` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('perlu_izin','diproses','diterima','ditolak') COLLATE utf8mb4_unicode_ci DEFAULT 'perlu_izin',
  `handled_by` int DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposals` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dibuat_oleh` int DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rab` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_anggaran` decimal(15,2) DEFAULT NULL,
  `status` enum('draft','diajukan','disetujui','ditolak') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `submitted_by` int DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `id` int NOT NULL,
  `role` enum('superadmin','admin','staff') COLLATE utf8mb4_unicode_ci NOT NULL,
  `feature` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sdm_import_log` (
  `id` int NOT NULL,
  `filename` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_rows` int DEFAULT '0',
  `success_rows` int DEFAULT '0',
  `skip_rows` int DEFAULT '0',
  `imported_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` enum('text','textarea','boolean','select') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `setting_group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `label` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_wa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `divisi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jabatan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_sistem` enum('superadmin','admin','staff') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `jabatan_sistem` enum('staff','bendahara_tertinggi','kepala_sekolah','manager_tk','manager_sd','manager_smp') COLLATE utf8mb4_unicode_ci DEFAULT 'staff',
  `status` enum('aktif','nonaktif') COLLATE utf8mb4_unicode_ci DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Indexes for dumped tables
--

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `approver_id` (`approver_id`);

--
-- Indexes for table `divisions`
--
ALTER TABLE `divisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indexes for table `evaluasi_jawaban`
--
ALTER TABLE `evaluasi_jawaban`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_jawaban` (`pertanyaan_id`,`user_id`),
  ADD KEY `evaluasi_id` (`evaluasi_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `evaluasi_pertanyaan`
--
ALTER TABLE `evaluasi_pertanyaan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evaluasi_id` (`evaluasi_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pic_id` (`pic_id`),
  ADD KEY `template_dari_event_id` (`template_dari_event_id`);

--
-- Indexes for table `event_checklist`
--
ALTER TABLE `event_checklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `done_by` (`done_by`);

--
-- Indexes for table `event_evaluasi`
--
ALTER TABLE `event_evaluasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `event_files`
--
ALTER TABLE `event_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `event_panitia`
--
ALTER TABLE `event_panitia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_event_user` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `event_swot`
--
ALTER TABLE `event_swot`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_swot` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `event_swot_answers`
--
ALTER TABLE `event_swot_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_swot_answer` (`swot_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `event_swot_questions`
--
ALTER TABLE `event_swot_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `perijinan`
--
ALTER TABLE `perijinan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `handled_by` (`handled_by`);

--
-- Indexes for table `proposals`
--
ALTER TABLE `proposals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `dibuat_oleh` (`dibuat_oleh`);

--
-- Indexes for table `rab`
--
ALTER TABLE `rab`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_role_feature` (`role`,`feature`);

--
-- Indexes for table `sdm_import_log`
--
ALTER TABLE `sdm_import_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `imported_by` (`imported_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `divisions`
--
ALTER TABLE `divisions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `evaluasi_jawaban`
--
ALTER TABLE `evaluasi_jawaban`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `evaluasi_pertanyaan`
--
ALTER TABLE `evaluasi_pertanyaan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `event_checklist`
--
ALTER TABLE `event_checklist`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_evaluasi`
--
ALTER TABLE `event_evaluasi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event_files`
--
ALTER TABLE `event_files`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `event_panitia`
--
ALTER TABLE `event_panitia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `event_swot`
--
ALTER TABLE `event_swot`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_swot_answers`
--
ALTER TABLE `event_swot_answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_swot_questions`
--
ALTER TABLE `event_swot_questions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `perijinan`
--
ALTER TABLE `perijinan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proposals`
--
ALTER TABLE `proposals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rab`
--
ALTER TABLE `rab`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `sdm_import_log`
--
ALTER TABLE `sdm_import_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approvals`
--
ALTER TABLE `approvals`
  ADD CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approvals_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `evaluasi_jawaban`
--
ALTER TABLE `evaluasi_jawaban`
  ADD CONSTRAINT `evaluasi_jawaban_ibfk_1` FOREIGN KEY (`evaluasi_id`) REFERENCES `event_evaluasi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluasi_jawaban_ibfk_2` FOREIGN KEY (`pertanyaan_id`) REFERENCES `evaluasi_pertanyaan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluasi_jawaban_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluasi_pertanyaan`
--
ALTER TABLE `evaluasi_pertanyaan`
  ADD CONSTRAINT `evaluasi_pertanyaan_ibfk_1` FOREIGN KEY (`evaluasi_id`) REFERENCES `event_evaluasi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`pic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`template_dari_event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_checklist`
--
ALTER TABLE `event_checklist`
  ADD CONSTRAINT `event_checklist_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_checklist_ibfk_2` FOREIGN KEY (`done_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_evaluasi`
--
ALTER TABLE `event_evaluasi`
  ADD CONSTRAINT `event_evaluasi_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_evaluasi_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_files`
--
ALTER TABLE `event_files`
  ADD CONSTRAINT `event_files_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_panitia`
--
ALTER TABLE `event_panitia`
  ADD CONSTRAINT `event_panitia_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_panitia_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_swot`
--
ALTER TABLE `event_swot`
  ADD CONSTRAINT `event_swot_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_swot_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_swot_answers`
--
ALTER TABLE `event_swot_answers`
  ADD CONSTRAINT `event_swot_answers_ibfk_1` FOREIGN KEY (`swot_id`) REFERENCES `event_swot` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_swot_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `event_swot_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_swot_questions`
--
ALTER TABLE `event_swot_questions`
  ADD CONSTRAINT `event_swot_questions_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `perijinan`
--
ALTER TABLE `perijinan`
  ADD CONSTRAINT `perijinan_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `perijinan_ibfk_2` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proposals`
--
ALTER TABLE `proposals`
  ADD CONSTRAINT `proposals_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proposals_ibfk_2` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `rab`
--
ALTER TABLE `rab`
  ADD CONSTRAINT `rab_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rab_ibfk_2` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sdm_import_log`
--
ALTER TABLE `sdm_import_log`
  ADD CONSTRAINT `sdm_import_log_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- ═══════════════════════════════════════════════════════════════
-- SEED DATA
-- ═══════════════════════════════════════════════════════════════

-- divisions
INSERT INTO `divisions` (`id`, `nama`, `urutan`, `created_at`) VALUES
(1, 'TK', 0, '2026-05-19 07:11:20'),
(2, 'SD', 1, '2026-05-19 07:11:20'),
(3, 'SMP', 2, '2026-05-19 07:11:20'),
(4, 'Umum', 3, '2026-05-19 07:11:20'),
(5, 'IT', 4, '2026-05-19 07:11:20'),
(6, 'INKLUSI', 5, '2026-05-20 00:58:19'),
(7, 'Keuangan', 6, '2026-05-20 01:00:44');

-- system_settings
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `setting_group`, `label`, `description`, `updated_at`) VALUES
(1, 'app_name', 'SAPAcara', 'text', 'general', 'Nama Aplikasi', 'Tampil di header dan halaman login', '2026-05-21 08:07:33'),
(2, 'app_subtitle', 'Sistem Manajemen Acara Sekolah', 'text', 'general', 'Subtitle Aplikasi', 'Di bawah nama aplikasi pada halaman login', '2026-05-21 08:07:33'),
(3, 'school_name', 'Sekolah Alam Bandung', 'text', 'general', 'Nama Sekolah', 'Nama institusi', '2026-05-21 08:07:33'),
(4, 'maintenance_mode', '0', 'boolean', 'general', 'Mode Maintenance', 'Aktifkan untuk mencegah login selain superadmin', '2026-05-21 08:07:33'),
(5, 'maintenance_msg', 'Sistem sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.', 'textarea', 'general', 'Pesan Maintenance', 'Pesan yang tampil saat maintenance aktif', '2026-05-21 08:07:33');

-- role_permissions
INSERT INTO `role_permissions` (`id`, `role`, `feature`, `is_allowed`) VALUES
(1, 'superadmin', 'manajemen_sdm', 1),
(2, 'superadmin', 'import_excel', 1),
(3, 'superadmin', 'pengaturan_sistem', 1),
(4, 'superadmin', 'kelola_approval', 1),
(5, 'superadmin', 'buat_acara', 1),
(6, 'superadmin', 'admin_panel', 1),
(7, 'admin', 'manajemen_sdm', 0),
(8, 'admin', 'import_excel', 0),
(9, 'admin', 'pengaturan_sistem', 0),
(10, 'admin', 'kelola_approval', 1),
(11, 'admin', 'buat_acara', 1),
(12, 'admin', 'admin_panel', 1),
(13, 'staff', 'manajemen_sdm', 0),
(14, 'staff', 'import_excel', 0),
(15, 'staff', 'pengaturan_sistem', 0),
(16, 'staff', 'kelola_approval', 0),
(17, 'staff', 'buat_acara', 1),
(18, 'staff', 'admin_panel', 0);

-- users (superadmin default only)
INSERT INTO `users` (`id`, `nama`, `email`, `password`, `no_wa`, `divisi`, `jabatan`, `role_sistem`, `jabatan_sistem`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'admin@sekolah.sch.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'IT', 'Admin Sistem', 'superadmin', 'staff', 'aktif', '2026-05-12 03:07:58', '2026-05-12 03:07:58');


-- ═══════════════════════════════════════════════════════════════
-- PR-05: Archive & Template System
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `is_template` TINYINT(1) DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `template_notes` TEXT NULL;

-- ═══════════════════════════════════════════════════════════════
-- PR-10: Performance indexes
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `notifications` ADD INDEX IF NOT EXISTS `idx_user_unread` (`user_id`, `is_read`);
ALTER TABLE `events` ADD INDEX IF NOT EXISTS `idx_status` (`status`);
ALTER TABLE `approvals` ADD INDEX IF NOT EXISTS `idx_appr_status` (`status`);

COMMIT;
