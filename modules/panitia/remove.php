<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status()===PHP_SESSION_NONE) session_start();
requireLogin();

$id      = (int)($_GET['id'] ?? 0);
$eventId = (int)($_GET['event_id'] ?? 0);

if ($id && $eventId && (isPIC($eventId,$pdo) || isEventAdmin($eventId,$pdo) || isSuperAdmin())) {
    // Jangan hapus PIC
    $cek = $pdo->prepare("SELECT peran_acara FROM event_panitia WHERE id=? AND event_id=?");
    $cek->execute([$id,$eventId]); $p = $cek->fetch();
    if ($p && $p['peran_acara']!=='pic') {
        $pdo->prepare("DELETE FROM event_panitia WHERE id=? AND event_id=?")->execute([$id,$eventId]);
        setFlash('Panitia berhasil dikeluarkan.','success');
    } else {
        setFlash('PIC tidak bisa dikeluarkan.','danger');
    }
}
header('Location: ' . BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '#tim');
exit;
