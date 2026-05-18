<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status()===PHP_SESSION_NONE) session_start();
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM event_files WHERE id=?");
$stmt->execute([$id]); $file = $stmt->fetch();
if (!$file) { http_response_code(404); die('File tidak ditemukan.'); }

// Cek akses user ke file ini
$myRole = $pdo->prepare("SELECT * FROM event_panitia WHERE event_id=? AND user_id=?");
$myRole->execute([$file['event_id'], $_SESSION['user_id']]); $role = $myRole->fetch();

$isPic = isSuperAdmin() || ($role && $role['peran_acara']==='pic');
$isInti = $role && in_array($role['peran_acara'],['pic','panitia_inti']);
$isAdmin = $role && $role['is_event_admin'];

$canSee = match($file['visibility']) {
  'all'      => (bool)$role || isSuperAdmin(),
  'inti'     => $isPic || $isInti || $isAdmin || isSuperAdmin(),
  'pic_only' => $isPic || isSuperAdmin(),
  default    => false
};

if (!$canSee) { http_response_code(403); die('Akses ditolak.'); }

$path = __DIR__ . '/../../uploads/' . $file['file_path'];
if (!file_exists($path)) { http_response_code(404); die('File tidak ditemukan di server.'); }

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . addslashes($file['file_original']) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-cache');
readfile($path);
exit;
