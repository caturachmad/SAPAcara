<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status()===PHP_SESSION_NONE) session_start();
requireLogin();

$id      = (int)($_GET['id'] ?? 0);
$eventId = (int)($_GET['event_id'] ?? 0);
$stmt    = $pdo->prepare("SELECT * FROM event_files WHERE id=? AND event_id=?");
$stmt->execute([$id, $eventId]); $file = $stmt->fetch();

if ($file && (isSuperAdmin() || isPIC($eventId,$pdo) || isEventAdmin($eventId,$pdo) || $file['uploaded_by']==$_SESSION['user_id'])) {
    $path = __DIR__ . '/../../uploads/' . $file['file_path'];
    if (file_exists($path)) unlink($path);
    $pdo->prepare("DELETE FROM event_files WHERE id=?")->execute([$id]);
    setFlash('File berhasil dihapus.', 'success');
}
header('Location: ' . BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '#dokumen');
exit;
