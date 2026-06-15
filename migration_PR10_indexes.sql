-- ============================================================
-- SAPAcara Migration: PR-10 — Performance Indexes
-- Jalankan sekali pada install existing (fresh install sudah include ini di database.sql)
-- ============================================================

-- Notifications: composite index untuk query unread per user
ALTER TABLE `notifications`
  ADD INDEX IF NOT EXISTS `idx_user_unread` (`user_id`, `is_read`);

-- Events: index pada kolom status (sering difilter)
ALTER TABLE `events`
  ADD INDEX IF NOT EXISTS `idx_status` (`status`);

-- Approvals: index pada kolom status (sering difilter)
ALTER TABLE `approvals`
  ADD INDEX IF NOT EXISTS `idx_status` (`status`);
