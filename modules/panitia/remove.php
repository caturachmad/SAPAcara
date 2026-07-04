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

if ($id && $eventId && (isPIC($eventId,$pdo) || isEventAdmin($eventId,$pdo) || isSuperAdmin())) {
    $cek = $pdo->prepare("SELECT peran_acara FROM event_panitia WHERE id=? AND event_id=?");
    $cek->execute([$id,$eventId]); $p = $cek->fetch();
    if ($p && $p['peran_acara']!=='pic') {
        $pdo->prepare("DELETE FROM event_panitia WHERE id=? AND event_id=?")->execute([$id,$eventId]);
        setFlash('Panitia berhasil dikeluarkan.','success');
    } else {
        setFlash('PIC tidak bisa dikeluarkan.','danger');
    }
}
redirectBack(BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '#tim');
