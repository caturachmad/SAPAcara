<?php
// Redirect ke halaman bulk invite yang baru
$eventId = (int)($_GET['event_id'] ?? 0);
require_once __DIR__ . '/../../config/db.php';
header('Location: ' . BASE_URL . '/modules/panitia/bulk_invite.php' . ($eventId ? '?event_id='.$eventId : ''));
exit;
