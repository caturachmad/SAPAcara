<?php
require_once __DIR__ . '/../../config/db.php';
if (session_status()===PHP_SESSION_NONE) session_start();

$token = $_GET['token'] ?? '';
$jawab = $_GET['jawab'] ?? '';

if (!$token || !in_array($jawab,['bersedia','tidak_bisa'])) {
    die('<div style="font-family:Arial;text-align:center;padding:60px"><h2>❌ Link tidak valid</h2></div>');
}

$stmt = $pdo->prepare("SELECT ep.*, u.nama, u.email, e.judul, e.tanggal_mulai, e.level, e.wa_group_link FROM event_panitia ep JOIN users u ON u.id=ep.user_id JOIN events e ON e.id=ep.event_id WHERE ep.token_konfirmasi=? AND ep.token_expires_at>NOW() AND ep.status_konfirmasi='pending'");
$stmt->execute([$token]); $row = $stmt->fetch();

if (!$row) {
    die('<div style="font-family:Arial;text-align:center;padding:60px;background:#f1f5f9;min-height:100vh"><div style="max-width:400px;margin:auto;background:#fff;border-radius:12px;padding:40px;box-shadow:0 4px 20px rgba(0,0,0,.1)"><div style="font-size:3rem">⏱️</div><h2 style="color:#ef4444">Link Kadaluarsa</h2><p style="color:#64748b">Link konfirmasi sudah tidak berlaku atau sudah digunakan. Hubungi PIC acara untuk informasi lebih lanjut.</p></div></div>');
}

// Handle alasan tolak via form
$alasan = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['alasan'])) {
    $alasan = trim($_POST['alasan']);
    $jawab  = 'tidak_bisa';
}

if ($jawab === 'tidak_bisa' && $_SERVER['REQUEST_METHOD']!=='POST' && !isset($_POST['alasan'])) {
    // Tampilkan form alasan sebelum konfirmasi tolak
    ?>
    <!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Konfirmasi Penolakan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head><body style="background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh">
    <div style="max-width:440px;width:100%;margin:24px auto">
      <div class="card shadow border-0" style="border-radius:16px;overflow:hidden">
        <div style="background:#ef4444;padding:24px;text-align:center">
          <div style="font-size:2.5rem">❌</div>
          <h4 style="color:#fff;margin:8px 0 0">Tolak Penugasan?</h4>
        </div>
        <div class="p-4">
          <div style="background:#f8fafc;border-radius:8px;padding:14px;margin-bottom:20px;border-left:4px solid #ef4444">
            <strong><?= htmlspecialchars($row['judul']) ?></strong><br>
            <small class="text-muted">📅 <?= date('d M Y', strtotime($row['tanggal_mulai'])) ?> · <?= $row['level'] ?></small>
          </div>
          <form method="POST">
            <div class="mb-3">
              <label class="form-label fw-semibold">Alasan tidak bisa hadir <span class="text-danger">*</span></label>
              <textarea name="alasan" class="form-control" rows="3" placeholder="cth: Ada acara keluarga, sedang sakit, dll." required></textarea>
              <div class="form-text">Alasan akan disampaikan ke PIC acara</div>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-danger flex-grow-1">Konfirmasi Tidak Bisa</button>
              <a href="?token=<?= urlencode($token) ?>&jawab=bersedia" class="btn btn-success flex-grow-1">Bersedia ✅</a>
            </div>
          </form>
        </div>
      </div>
    </div></body></html>
    <?php exit;
}

// Simpan konfirmasi
$pdo->prepare("UPDATE event_panitia SET status_konfirmasi=?, alasan_tolak=?, confirmed_at=NOW() WHERE token_konfirmasi=?")
    ->execute([$jawab, $alasan ?: null, $token]);

// Jika yang dikonfirmasi adalah PIC dan konfirmasi diterima, pastikan dia menjadi Event Admin juga
if ($jawab === 'bersedia' && isset($row['peran_acara']) && $row['peran_acara'] === 'pic') {
    try {
        $pdo->prepare("UPDATE event_panitia SET is_event_admin=1 WHERE id=?")->execute([$row['id']]);
    } catch (Exception $e) {
        error_log('[panitia/confirm] failed to set is_event_admin for id=' . ($row['id'] ?? 'unknown') . ' - ' . $e->getMessage());
    }
}

$emoji = $jawab==='bersedia' ? '✅' : '❌';
$warna = $jawab==='bersedia' ? '#10b981' : '#ef4444';
$judul = $jawab==='bersedia' ? 'Konfirmasi Diterima!' : 'Penugasan Ditolak';
$pesan = $jawab==='bersedia' ? 'Kamu telah dikonfirmasi sebagai panitia acara ini.' : 'Penolakan kamu telah dicatat. Terima kasih sudah memberikan informasi.';
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $judul ?> — SAPAcara</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body style="background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh">
<div style="max-width:480px;width:100%;margin:24px auto">
  <div class="card shadow border-0" style="border-radius:16px;overflow:hidden">
    <div style="background:<?= $warna ?>;padding:32px;text-align:center">
      <div style="font-size:3.5rem"><?= $emoji ?></div>
      <h3 style="color:#fff;margin:10px 0 0"><?= $judul ?></h3>
    </div>
    <div class="p-4 text-center">
      <p style="color:#64748b;margin-bottom:20px"><?= $pesan ?></p>
      <div style="background:#f8fafc;border-radius:8px;padding:14px;margin-bottom:20px;text-align:left;border-left:4px solid <?= $warna ?>">
        <strong><?= htmlspecialchars($row['judul']) ?></strong><br>
        <small class="text-muted">📅 <?= date('d M Y', strtotime($row['tanggal_mulai'])) ?> · <?= $row['level'] ?></small>
      </div>

      <?php if ($jawab==='bersedia' && $row['wa_group_link']): ?>
        <div class="alert alert-success text-start mb-3">
          <strong>🎉 Langkah selanjutnya:</strong><br>
          Gabung ke grup WhatsApp panitia untuk koordinasi lebih lanjut!
        </div>
        <a href="<?= htmlspecialchars($row['wa_group_link']) ?>" target="_blank"
           class="btn btn-success w-100 mb-3" style="font-size:1rem;padding:12px">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="me-2" viewBox="0 0 16 16"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592z"/></svg>
          Gabung Grup WhatsApp Panitia
        </a>
      <?php endif; ?>

      <a href="<?= BASE_URL ?>/modules/auth/login.php" class="btn btn-outline-secondary">
        Masuk ke SAPAcara
      </a>
    </div>
  </div>
</div></body></html>
