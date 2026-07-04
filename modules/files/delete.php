<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin(); // includes CSRF verify for POST


function redirectBack(string $fallback): void {
    $ref  = $_SERVER['HTTP_REFERER'] ?? '';
    $safe = str_starts_with($ref, BASE_URL) ? $ref : $fallback;
    header('Location: ' . $safe);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

$id      = (int)($_POST['id'] ?? 0);
$eventId = (int)($_POST['event_id'] ?? 0);
$stmt    = $pdo->prepare("SELECT * FROM event_files WHERE id=? AND event_id=?");
$stmt->execute([$id, $eventId]); $file = $stmt->fetch();

if ($file && (isSuperAdmin() || isPIC($eventId,$pdo) || isEventAdmin($eventId,$pdo) || $file['uploaded_by']==$_SESSION['user_id'])) {
    $path = __DIR__ . '/../../uploads/' . $file['file_path'];
    if (file_exists($path)) unlink($path);
    $pdo->prepare("DELETE FROM event_files WHERE id=?")->execute([$id]);
    setFlash('File berhasil dihapus.', 'success');
}
redirectBack(BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '#dokumen');
