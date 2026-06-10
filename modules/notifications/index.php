<?php
$pageTitle = 'Notifikasi';
require_once __DIR__ . '/../../includes/layout/header.php';

// Tandai semua dibaca saat buka halaman
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);

$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$_SESSION['user_id']]);
$list = $notifs->fetchAll();

$icon  = ['info'=>'info-circle','success'=>'check-circle','warning'=>'exclamation-triangle','danger'=>'x-circle'];
$color = ['info'=>'primary','success'=>'success','warning'=>'warning','danger'=>'danger'];
?>
<h5 class="fw-bold mb-4">Semua Notifikasi</h5>
<div class="card">
  <div class="card-body p-0">
    <?php if (empty($list)): ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-bell-slash fs-1 d-block mb-2"></i> Belum ada notifikasi
      </div>
    <?php else: ?>
      <?php foreach ($list as $n): ?>
        <a href="<?= htmlspecialchars($n['link'] ?: '#') ?>"
           class="d-flex align-items-start gap-3 px-4 py-3 border-bottom text-decoration-none text-dark <?= !$n['is_read']?'bg-light':'' ?>">
          <div class="mt-1">
            <i class="bi bi-<?= $icon[$n['tipe']] ?> fs-5 text-<?= $color[$n['tipe']] ?>"></i>
          </div>
          <div class="flex-grow-1">
            <div class="fw-semibold"><?= htmlspecialchars($n['judul']) ?></div>
            <div class="small text-muted"><?= htmlspecialchars($n['pesan']) ?></div>
            <div class="small text-muted mt-1"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
