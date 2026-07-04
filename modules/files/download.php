<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
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
$pendingApproval = $pdo->prepare("SELECT COUNT(*) FROM approvals WHERE event_id=? AND approver_id=? AND status='pending'");
$pendingApproval->execute([$file['event_id'], $_SESSION['user_id']]);
$isPendingApprover = (int)$pendingApproval->fetchColumn() > 0;

$canSee = match($file['visibility']) {
  'all'      => (bool)$role || isSuperAdmin(),
  'inti'     => $isPic || $isInti || $isAdmin || isSuperAdmin(),
  'pic_only' => $isPic || isSuperAdmin(),
  default    => false
};

if (!$canSee && !$isPendingApprover) { http_response_code(403); die('Akses ditolak.'); }

$path = __DIR__ . '/../../uploads/' . $file['file_path'];
if (!file_exists($path)) { http_response_code(404); die('File tidak ditemukan di server.'); }

// Validate path to prevent directory traversal attacks
$realPath = realpath($path);
$uploadDir = realpath(__DIR__ . '/../../uploads/');
if ($realPath === false || strpos($realPath, $uploadDir) !== 0) {
    http_response_code(403);
    die('Akses ditolak (path validation gagal).');
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
$filename = $file['file_original'] ?: basename($path);
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-cache');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
