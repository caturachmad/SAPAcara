<?php
/**
 * ajax_check_template.php
 * PR-05: Returns info about a template event for the create form
 * Called via fetch when user selects a template in create.php
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'ID tidak valid']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT e.id, e.judul, e.level, e.tanggal_mulai, e.is_template, e.template_notes,
           (SELECT COUNT(*) FROM event_swot WHERE event_id=e.id) AS jml_swot,
           (SELECT COUNT(*) FROM event_panitia WHERE event_id=e.id) AS jml_panitia,
           (SELECT COUNT(*) FROM event_checklist WHERE event_id=e.id) AS jml_checklist
    FROM events e
    WHERE e.id=? AND e.status='selesai' AND e.is_template=1
");
$stmt->execute([$id]);
$ev = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ev) {
    echo json_encode(['error' => 'Template tidak ditemukan']);
    exit;
}

// Grant session access to archive.php for this event
$_SESSION['template_access'][$id] = true;

echo json_encode([
    'id'             => $ev['id'],
    'judul'          => $ev['judul'],
    'level'          => $ev['level'],
    'tanggal_mulai'  => $ev['tanggal_mulai'],
    'is_template'    => (bool)$ev['is_template'],
    'template_notes' => $ev['template_notes'],
    'jml_swot'       => (int)$ev['jml_swot'],
    'jml_panitia'    => (int)$ev['jml_panitia'],
    'jml_checklist'  => (int)$ev['jml_checklist'],
    'archive_url'    => BASE_URL . '/modules/events/archive.php?id=' . $ev['id'],
]);
