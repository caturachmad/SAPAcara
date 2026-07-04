<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/security-headers.php';  // Security headers for HTTPS + headers protection
requireLogin();

$pageTitle = $pageTitle ?? 'SAPAcara';
$user      = currentUser();
$uid       = $_SESSION['user_id'];
$initials  = strtoupper(substr($user['nama'] ?? 'U', 0, 2));

$stmtN = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmtN->execute([$uid]);
$unreadNotif = (int)$stmtN->fetchColumn();

// Hitung approval pending yang actionable untuk user ini (badge di header)
$stmtApprCnt = $pdo->prepare("SELECT COUNT(*) FROM approvals ap WHERE ap.approver_id = ? AND ap.status = 'pending' AND ap.urutan = (SELECT MIN(a2.urutan) FROM approvals a2 WHERE a2.event_id = ap.event_id AND a2.status = 'pending')");
$stmtApprCnt->execute([$uid]);
$pendingApprovalCount = (int)$stmtApprCnt->fetchColumn();

$stmtNL = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
$stmtNL->execute([$uid]);
$notifItems = $stmtNL->fetchAll();

$notifIcon  = ['info'=>'info-circle','success'=>'check-circle-fill','warning'=>'exclamation-triangle-fill','danger'=>'x-circle-fill'];
$notifColor = ['info'=>'#0ea5e9','success'=>'#10b981','warning'=>'#f59e0b','danger'=>'#ef4444'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= csrfToken() ?>">
<title><?= htmlspecialchars($pageTitle) ?> — SAPAcara</title>
<link href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/main.css" rel="stylesheet">
<?php if (isset($extraCss)) echo $extraCss; ?>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<header id="topbar">
  <button class="btn border-0 p-1 d-md-none" id="sidebarToggle" style="color:var(--text-muted)">
    <i class="bi bi-list fs-4"></i>
  </button>

  <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>

  <div class="topbar-actions">

    <!-- ── Notification (custom panel, no Bootstrap dropdown) ── -->
    <div class="notif-wrapper">
      <button class="notif-btn" id="notifBtn" type="button" aria-label="Notifikasi">
        <i class="bi bi-bell fs-6"></i>
        <?php if ($unreadNotif > 0): ?>
          <span class="notif-badge"><?= $unreadNotif > 9 ? '9+' : $unreadNotif ?></span>
        <?php endif; ?>
      </button>

      <div class="notif-panel" id="notifPanel">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom flex-shrink-0">
          <span class="fw-700 fs-13">Notifikasi</span>
          <div class="d-flex gap-2 align-items-center">
            <?php if ($unreadNotif > 0): ?>
            <form method="POST" action="<?= BASE_URL ?>/modules/notifications/read_all.php" style="display:inline"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><button type="submit" class="btn btn-link fs-12 text-primary p-0" style="white-space:nowrap">Tandai dibaca</button></form>
            <?php endif; ?>
            <button onclick="document.getElementById('notifPanel').classList.remove('open')" class="btn-close" style="font-size:.7rem"></button>
          </div>
        </div>

        <!-- Body -->
        <div class="notif-panel-body">
          <?php if (empty($notifItems)): ?>
            <div class="text-center py-4" style="color:var(--text-muted)">
              <i class="bi bi-bell-slash d-block fs-2 mb-2 opacity-25"></i>
              <span class="fs-12">Tidak ada notifikasi</span>
            </div>
          <?php else: ?>
            <?php foreach ($notifItems as $n):
              $ic = $notifIcon[$n['tipe']] ?? 'info-circle';
              $cl = $notifColor[$n['tipe']] ?? '#0ea5e9';
            ?>
            <a href="<?= htmlspecialchars($n['link'] ?: BASE_URL.'/modules/notifications/') ?>"
               class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>"
               onclick="document.getElementById('notifPanel').classList.remove('open')">
              <i class="bi bi-<?= $ic ?> notif-icon" style="color:<?= $cl ?>"></i>
              <div class="overflow-hidden">
                <div class="fw-600 fs-12" style="line-height:1.3"><?= htmlspecialchars($n['judul']) ?></div>
                <div class="fs-12 mt-1" style="color:var(--text-muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= htmlspecialchars($n['pesan']) ?></div>
                <div class="fs-12 mt-1" style="color:#94a3b8"><?= date('d M · H:i', strtotime($n['created_at'])) ?></div>
              </div>
            </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="border-top text-center py-2 flex-shrink-0">
          <a href="<?= BASE_URL ?>/modules/notifications/" class="fs-12 text-primary"
             onclick="document.getElementById('notifPanel').classList.remove('open')">
            Lihat semua notifikasi
          </a>
        </div>
      </div>
    </div><!-- end notif-wrapper -->

    <!-- Approval quick badge -->
    <div class="ms-2">
      <a href="<?= BASE_URL ?>/modules/approvals/" class="btn border-0 p-1" style="color:var(--text-muted);position:relative">
        <i class="bi bi-clipboard-check fs-6"></i>
        <?php if ($pendingApprovalCount > 0): ?>
          <span class="notif-badge" style="position:absolute;top:-6px;right:-6px;"><?= $pendingApprovalCount > 9 ? '9+' : $pendingApprovalCount ?></span>
        <?php endif; ?>
      </a>
    </div>

    <!-- ── User Dropdown (tetap pakai Bootstrap) ── -->
    <div class="dropdown">
      <div class="user-chip" data-bs-toggle="dropdown" aria-expanded="false" style="cursor:pointer">
        <div class="avatar avatar-sm"><?= $initials ?></div>
        <span class="chip-name d-none d-md-block"><?= htmlspecialchars(explode(' ',$user['nama'])[0]) ?></span>
        <i class="bi bi-chevron-down d-none d-md-block" style="font-size:.7rem;color:var(--text-muted)"></i>
      </div>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li class="px-3 py-2 border-bottom">
          <div class="fw-700 fs-13"><?= htmlspecialchars($user['nama']) ?></div>
          <div class="fs-12 text-muted"><?= htmlspecialchars($user['email']) ?></div>
          <span class="badge mt-1 <?= $user['role_sistem']==='superadmin'?'bg-danger':'bg-primary' ?>"><?= $user['role_sistem'] ?></span>
        </li>
        <li><a class="dropdown-item mt-1" href="<?= BASE_URL ?>/modules/profile/"><i class="bi bi-person-circle me-2"></i>Profil Saya</a></li>
        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/notifications/"><i class="bi bi-bell me-2"></i>Notifikasi <?php if($unreadNotif>0):?><span class="badge bg-danger ms-auto"><?=$unreadNotif?></span><?php endif;?></a></li>
        <li><hr class="dropdown-divider my-1"></li>
        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/modules/auth/logout.php" data-confirm="Yakin ingin logout?"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>

  </div>
</header>

<main id="main">
<?php if (isset($_SESSION['flash'])): ?>
  <?php $ft=$_SESSION['flash']['type']; ?>
  <div class="alert alert-<?= $ft ?> alert-auto d-flex align-items-center gap-2 mb-4" style="max-height:200px">
    <i class="bi bi-<?= $ft==='success'?'check-circle-fill':'exclamation-triangle-fill' ?> flex-shrink-0"></i>
    <span class="flex-grow-1"><?= htmlspecialchars($_SESSION['flash']['msg']) ?></span>
    <button type="button" class="btn-close ms-auto" aria-label="Tutup"></button>
  </div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
