<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status()===PHP_SESSION_NONE) session_start();
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

if ($id && $eventId && (isPIC($eventId,$pdo) || isEventAdmin($eventId,$pdo) || isSuperAdmin())) {
    $pdo->prepare("UPDATE event_panitia SET is_event_admin = IF(is_event_admin=1,0,1) WHERE id=? AND event_id=?")
        ->execute([$id, $eventId]);
    setFlash('Status Event Admin diperbarui.','success');
}
redirectBack(BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '#tim');
