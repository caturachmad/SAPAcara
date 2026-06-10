<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$uid = $_SESSION['user_id'];

// Handle POST konfirmasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $panitiaId = (int)($_POST['panitia_id'] ?? 0);
    $jawab     = $_POST['jawab'] ?? '';
    $alasan    = trim($_POST['alasan'] ?? '');

    if (!$panitiaId || !in_array($jawab, ['bersedia', 'tidak_bisa'])) {
        setFlash('Data tidak valid.', 'danger');
        header('Location: ' . BASE_URL . '/modules/dashboard/index.php'); exit;
    }

    // Pastikan record ini milik user yang login dan masih pending
    $cek = $pdo->prepare("SELECT ep.*, e.judul FROM event_panitia ep JOIN events e ON e.id=ep.event_id WHERE ep.id=? AND ep.user_id=? AND ep.status_konfirmasi='pending'");
    $cek->execute([$panitiaId, $uid]);
    $row = $cek->fetch();

    if (!$row) {
        setFlash('Undangan tidak ditemukan atau sudah dikonfirmasi.', 'danger');
        header('Location: ' . BASE_URL . '/modules/dashboard/index.php'); exit;
    }

    $pdo->prepare("UPDATE event_panitia SET status_konfirmasi=?, catatan=?, confirmed_at=NOW() WHERE id=? AND user_id=?")
        ->execute([$jawab, $jawab === 'tidak_bisa' ? ($alasan ?: null) : null, $panitiaId, $uid]);

    // Kalau PIC bersedia, set is_event_admin
    if ($jawab === 'bersedia' && $row['peran_acara'] === 'pic') {
        $pdo->prepare("UPDATE event_panitia SET is_event_admin=1 WHERE id=?")->execute([$panitiaId]);
    }

    $pesan = $jawab === 'bersedia'
        ? "Kamu berhasil konfirmasi bersedia untuk acara \"{$row['judul']}\"!"
        : "Penolakan untuk acara \"{$row['judul']}\" telah dicatat.";
    setFlash($pesan, $jawab === 'bersedia' ? 'success' : 'warning');
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php'); exit;
}

header('Location: ' . BASE_URL . '/modules/dashboard/index.php'); exit;
