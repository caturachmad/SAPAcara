<?php
$in = __DIR__ . '/../siakad_db.sql';
$out = __DIR__ . '/../siakad_db.repaired.sql';
if (!file_exists($in)) { echo "Input SQL not found: $in\n"; exit(1); }
$content = file_get_contents($in);
$marker = "-- Database: `siakad_db`";
$pos = strpos($content, $marker);
if ($pos === false) { echo "Marker not found\n"; exit(1); }
$insertPos = strpos($content, "\n", $pos) + 1; // after marker line
$block = "\n/* Make import idempotent: disable foreign key checks and drop existing tables */\nSET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;\nSET FOREIGN_KEY_CHECKS=0;\n\n-- Drop tables if they exist (safe import)\nDROP TABLE IF EXISTS `approvals`;\nDROP TABLE IF EXISTS `audit_logs`;\nDROP TABLE IF EXISTS `divisions`;\nDROP TABLE IF EXISTS `evaluasi_jawaban`;\nDROP TABLE IF EXISTS `evaluasi_pertanyaan`;\nDROP TABLE IF EXISTS `events`;\nDROP TABLE IF EXISTS `event_checklist`;\nDROP TABLE IF EXISTS `event_evaluasi`;\nDROP TABLE IF EXISTS `event_files`;\nDROP TABLE IF EXISTS `event_panitia`;\nDROP TABLE IF EXISTS `event_swot`;\nDROP TABLE IF EXISTS `event_swot_answers`;\nDROP TABLE IF EXISTS `event_swot_questions`;\nDROP TABLE IF EXISTS `login_attempts`;\nDROP TABLE IF EXISTS `notifications`;\nDROP TABLE IF EXISTS `perijinan`;\nDROP TABLE IF EXISTS `proposals`;\nDROP TABLE IF EXISTS `rab`;\nDROP TABLE IF EXISTS `role_permissions`;\nDROP TABLE IF EXISTS `sdm_import_log`;\nDROP TABLE IF EXISTS `system_settings`;\nDROP TABLE IF EXISTS `users`;\n\n";
$new = substr($content, 0, $insertPos) . $block . substr($content, $insertPos);
// Ensure we re-enable FK checks before final COMMIT
if (strpos($new, "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS") === false) {
    $new = str_replace("COMMIT;", "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;\nCOMMIT;", $new);
}
file_put_contents($out, $new);
echo "Repaired SQL written to: $out\n";
