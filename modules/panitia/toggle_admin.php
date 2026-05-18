<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status()===PHP_SESSION_NONE) session_start();
requireLogin();

$id      = (int)($_GET['id'] ?? 0);
$eventId = (int)($_GET['event_id'] ?? 0);

if ($id && $eventId && (isPIC($eventId,$pdo) || isEventAdmin($eventId,$pdo) || isSuperAdmin())) {
    $pdo->prepare("UPDATE event_panitia SET is_event_admin = IF(is_event_admin=1,0,1) WHERE id=? AND event_id=?")
        ->execute([$id, $eventId]);
    setFlash('Status Event Admin diperbarui.','success');
}
header('Location: ' . BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '#tim');
exit;
