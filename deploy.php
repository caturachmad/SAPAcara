<?php
/**
 * SAPAcara – Deploy Script
 * Jalankan: php /var/www/html/siakad/deploy.php
 * Semua file ditulis otomatis ke folder yang benar.
 */

define('ROOT', '/var/www/html/siakad');

$files = [];

/* ============================================================
   assets/css/main.css
   ============================================================ */
$files['assets/css/main.css'] = <<<'CSS'
:root {
  --primary:       #1E3A5F;
  --primary-light: #2E86C1;
  --accent:        #2ECC71;
  --danger:        #E74C3C;
  --warning:       #F39C12;
  --sidebar-w:     260px;
  --topbar-h:      60px;
  --bg:            #F0F4F8;
  --card-radius:   12px;
  --shadow:        0 4px 20px rgba(0,0,0,.08);
}

body {
  font-family: 'Inter', sans-serif;
  background: var(--bg);
  color: #2d3748;
  margin: 0;
}

/* ── Sidebar ── */
#sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  width: var(--sidebar-w);
  background: var(--primary);
  display: flex;
  flex-direction: column;
  z-index: 1000;
  overflow-y: auto;
  transition: transform .25s ease;
}

#sidebar .brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 20px 20px 16px;
  color: #fff;
  font-size: 1.15rem;
  font-weight: 700;
  text-decoration: none;
  border-bottom: 1px solid rgba(255,255,255,.1);
}

#sidebar .nav-section {
  font-size: .7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: rgba(255,255,255,.4);
  padding: 14px 20px 4px;
}

#sidebar .nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 20px;
  color: rgba(255,255,255,.75);
  text-decoration: none;
  font-size: .9rem;
  border-radius: 0;
  transition: background .15s, color .15s;
}
#sidebar .nav-link:hover,
#sidebar .nav-link.active {
  background: rgba(255,255,255,.1);
  color: #fff;
}
#sidebar .nav-link i { font-size: 1rem; width: 20px; text-align: center; }

#sidebar .sidebar-user {
  margin-top: auto;
  padding: 14px 20px;
  border-top: 1px solid rgba(255,255,255,.1);
  display: flex;
  align-items: center;
  gap: 10px;
}
.avatar-circle {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: var(--primary-light);
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .85rem; flex-shrink: 0;
}

/* ── Topbar ── */
#topbar {
  position: fixed;
  top: 0;
  left: var(--sidebar-w);
  right: 0;
  height: var(--topbar-h);
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  padding: 0 24px;
  z-index: 999;
  gap: 12px;
}
#topbar .page-title { font-weight: 600; font-size: 1rem; flex: 1; }
#sidebarToggle { display: none; border: none; background: none; font-size: 1.4rem; cursor: pointer; }

/* ── Main content ── */
#main {
  margin-left: var(--sidebar-w);
  padding-top: var(--topbar-h);
  min-height: 100vh;
}
#main > .content-wrap { padding: 28px 24px; }

/* ── Cards ── */
.stat-card {
  background: #fff;
  border-radius: var(--card-radius);
  box-shadow: var(--shadow);
  padding: 22px 24px;
  display: flex;
  align-items: center;
  gap: 18px;
}
.stat-icon {
  width: 52px; height: 52px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; flex-shrink: 0;
}
.stat-value { font-size: 1.75rem; font-weight: 700; line-height: 1; }
.stat-label { font-size: .82rem; color: #718096; margin-top: 2px; }

.card-section {
  background: #fff;
  border-radius: var(--card-radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.card-section .card-header-bar {
  padding: 16px 20px;
  border-bottom: 1px solid #f0f4f8;
  font-weight: 600;
  display: flex; align-items: center; justify-content: space-between;
}

/* ── Badge status ── */
.badge-status {
  font-size: .72rem; font-weight: 600;
  padding: 4px 10px; border-radius: 20px;
  text-transform: capitalize;
}
.status-draft        { background: #e2e8f0; color: #4a5568; }
.status-pengajuan    { background: #bee3f8; color: #2b6cb0; }
.status-disetujui    { background: #c6f6d5; color: #276749; }
.status-berlangsung  { background: #fefcbf; color: #744210; }
.status-selesai      { background: #d1fae5; color: #065f46; }
.status-ditolak      { background: #fed7d7; color: #9b2c2c; }

/* ── Event list table ── */
.event-row:hover { background: #f7fafc; }
.event-row td { vertical-align: middle; }

/* ── Form ── */
.form-section {
  background: #fff;
  border-radius: var(--card-radius);
  box-shadow: var(--shadow);
  padding: 28px;
}
.form-label { font-weight: 500; font-size: .9rem; }
.form-control:focus, .form-select:focus {
  border-color: var(--primary-light);
  box-shadow: 0 0 0 3px rgba(46,134,193,.15);
}
.btn-primary-custom {
  background: var(--primary);
  border: none; color: #fff;
  padding: 10px 28px; border-radius: 8px;
  font-weight: 600; transition: background .2s;
}
.btn-primary-custom:hover { background: var(--primary-light); color: #fff; }

/* ── Conflict badge ── */
.conflict-badge {
  font-size: .72rem; padding: 2px 8px;
  border-radius: 20px; background: #fed7d7; color: #9b2c2c; font-weight: 600;
}
.available-badge {
  font-size: .72rem; padding: 2px 8px;
  border-radius: 20px; background: #c6f6d5; color: #276749; font-weight: 600;
}

/* ── Mobile ── */
@media (max-width: 768px) {
  #sidebar { transform: translateX(-100%); }
  #sidebar.open { transform: translateX(0); }
  #topbar, #main { left: 0; margin-left: 0; }
  #sidebarToggle { display: block; }
}
CSS;

/* ============================================================
   assets/js/main.js
   ============================================================ */
$files['assets/js/main.js'] = <<<'JS'
document.addEventListener('DOMContentLoaded', () => {
  /* Sidebar toggle mobile */
  const toggler = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggler && sidebar) {
    toggler.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', e => {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggler) {
        sidebar.classList.remove('open');
      }
    });
  }

  /* Auto-close flash alert */
  document.querySelectorAll('.alert-auto').forEach(el => {
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 4000);
  });

  /* Confirm delete */
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', e => {
      if (!confirm(btn.dataset.confirm)) e.preventDefault();
    });
  });

  /* Active nav link */
  const path = window.location.pathname;
  document.querySelectorAll('#sidebar .nav-link').forEach(a => {
    const href = a.getAttribute('href');
    if (href && path.startsWith(href) && href !== '/siakad/') {
      a.classList.add('active');
    }
  });

  /* Tanggal selesai min = tanggal mulai */
  const tglMulai   = document.getElementById('tanggal_mulai');
  const tglSelesai = document.getElementById('tanggal_selesai');
  if (tglMulai && tglSelesai) {
    tglMulai.addEventListener('change', () => {
      tglSelesai.min = tglMulai.value;
      if (tglSelesai.value && tglSelesai.value < tglMulai.value) {
        tglSelesai.value = tglMulai.value;
      }
    });
  }
});
JS;

/* ============================================================
   includes/layout/header.php
   ============================================================ */
$files['includes/layout/header.php'] = <<<'PHP'
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle = $pageTitle ?? 'SAPAcara';
$user = currentUser();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> — SAPAcara</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/main.css" rel="stylesheet">
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div id="topbar">
  <button id="sidebarToggle"><i class="bi bi-list"></i></button>
  <span class="page-title"><?= htmlspecialchars($pageTitle) ?></span>
  <span class="text-muted small"><?= htmlspecialchars($user['nama'] ?? '') ?></span>
</div>

<div id="main">
  <div class="content-wrap">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible alert-auto mb-3" role="alert">
      <?= htmlspecialchars($flash['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
PHP;

/* ============================================================
   includes/layout/sidebar.php
   ============================================================ */
$files['includes/layout/sidebar.php'] = <<<'PHP'
<?php
$user     = currentUser();
$initials = strtoupper(substr($user['nama'] ?? 'U', 0, 2));
$role     = $user['role_sistem'] ?? 'staff';
?>
<nav id="sidebar">
  <a href="<?= BASE_URL ?>" class="brand">
    <i class="bi bi-calendar-event-fill"></i> SAPAcara
  </a>

  <div class="overflow-auto flex-grow-1 py-2">
    <div class="nav-section">Menu Utama</div>
    <a href="<?= BASE_URL ?>/modules/dashboard/" class="nav-link">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/modules/events/" class="nav-link">
      <i class="bi bi-calendar3"></i> Semua Acara
    </a>
    <a href="<?= BASE_URL ?>/modules/events/create.php" class="nav-link">
      <i class="bi bi-plus-circle"></i> Buat Acara
    </a>

    <?php if ($role === 'superadmin'): ?>
    <div class="nav-section mt-2">Admin</div>
    <a href="<?= BASE_URL ?>/modules/users/" class="nav-link">
      <i class="bi bi-people"></i> Kelola SDM
    </a>
    <?php endif; ?>
  </div>

  <div class="sidebar-user">
    <div class="avatar-circle"><?= $initials ?></div>
    <div class="flex-grow-1 overflow-hidden">
      <div class="text-white small fw-600 text-truncate"><?= htmlspecialchars($user['nama'] ?? '') ?></div>
      <div class="text-white-50" style="font-size:.72rem"><?= ucfirst($role) ?></div>
    </div>
    <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="text-white opacity-75 ms-1" title="Keluar">
      <i class="bi bi-box-arrow-right"></i>
    </a>
  </div>
</nav>
PHP;

/* ============================================================
   includes/layout/footer.php
   ============================================================ */
$files['includes/layout/footer.php'] = <<<'PHP'
  </div><!-- .content-wrap -->
</div><!-- #main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
PHP;

/* ============================================================
   includes/auth.php
   ============================================================ */
$files['includes/auth.php'] = <<<'PHP'
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/modules/auth/login.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function isSuperAdmin(): bool {
    return (currentUser()['role_sistem'] ?? '') === 'superadmin';
}

function isPIC(int $eventId, PDO $pdo): bool {
    $uid = $_SESSION['user_id'] ?? 0;
    $q = $pdo->prepare("SELECT 1 FROM event_panitia WHERE event_id=? AND user_id=? AND peran_acara='pic' LIMIT 1");
    $q->execute([$eventId, $uid]);
    return (bool)$q->fetchColumn();
}

function isEventAdmin(int $eventId, PDO $pdo): bool {
    $uid = $_SESSION['user_id'] ?? 0;
    $q = $pdo->prepare("SELECT 1 FROM event_panitia WHERE event_id=? AND user_id=? AND is_event_admin=1 LIMIT 1");
    $q->execute([$eventId, $uid]);
    return (bool)$q->fetchColumn();
}

function setFlash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
PHP;

/* ============================================================
   modules/auth/login.php
   ============================================================ */
$files['modules/auth/login.php'] = <<<'PHP'
<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'aktif'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;
        header('Location: ' . BASE_URL . '/modules/dashboard/');
        exit;
    }
    $error = 'Email atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — SAPAcara</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#F0F4F8; min-height:100vh; display:flex; align-items:center; justify-content:center; }
  .login-card { background:#fff; border-radius:16px; box-shadow:0 8px 32px rgba(0,0,0,.1); padding:40px 36px; width:100%; max-width:400px; }
  .brand-logo { color:#1E3A5F; font-size:1.6rem; font-weight:700; text-align:center; margin-bottom:8px; }
  .brand-sub  { text-align:center; color:#718096; font-size:.85rem; margin-bottom:28px; }
  .btn-login  { background:#1E3A5F; color:#fff; border:none; padding:11px; font-weight:600; width:100%; border-radius:8px; }
  .btn-login:hover { background:#2E86C1; }
  .form-control:focus { border-color:#2E86C1; box-shadow:0 0 0 3px rgba(46,134,193,.15); }
</style>
</head>
<body>
<div class="login-card">
  <div class="brand-logo"><i class="bi bi-calendar-event-fill me-2"></i>SAPAcara</div>
  <div class="brand-sub">Sistem Manajemen Acara Sekolah</div>

  <?php if ($error): ?>
  <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="mb-3">
      <label class="form-label fw-500">Email</label>
      <input type="email" name="email" class="form-control" placeholder="nama@sekolah.sch.id" required autofocus>
    </div>
    <div class="mb-4">
      <label class="form-label fw-500">Password</label>
      <input type="password" name="password" class="form-control" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-login">
      <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
    </button>
  </form>
</div>
</body>
</html>
PHP;

/* ============================================================
   modules/auth/logout.php
   ============================================================ */
$files['modules/auth/logout.php'] = <<<'PHP'
<?php
require_once __DIR__ . '/../../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
header('Location: ' . BASE_URL . '/modules/auth/login.php');
exit;
PHP;

/* ============================================================
   modules/dashboard/index.php
   ============================================================ */
$files['modules/dashboard/index.php'] = <<<'PHP'
<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../../includes/layout/header.php';

$uid = $_SESSION['user_id'];

$q = $pdo->prepare("SELECT COUNT(*) FROM event_panitia WHERE user_id=? AND peran_acara='pic'");
$q->execute([$uid]); $totalPIC = $q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM event_panitia WHERE user_id=? AND peran_acara != 'pic'");
$q->execute([$uid]); $totalPanitia = $q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM event_panitia WHERE user_id=? AND status_konfirmasi='pending'");
$q->execute([$uid]); $totalPending = $q->fetchColumn();

$totalEvents = $pdo->query("SELECT COUNT(*) FROM events WHERE status NOT IN ('selesai','ditolak')")->fetchColumn();

// Acara saya sebagai PIC (terbaru 5)
$myEvents = $pdo->prepare("
    SELECT e.* FROM events e
    JOIN event_panitia ep ON ep.event_id = e.id
    WHERE ep.user_id = ? AND ep.peran_acara = 'pic'
    ORDER BY e.tanggal_mulai ASC LIMIT 5
");
$myEvents->execute([$uid]);
$acaraPIC = $myEvents->fetchAll();

// Acara saya sebagai panitia (terbaru 5)
$myJoin = $pdo->prepare("
    SELECT e.*, ep.peran_acara, ep.bagian, ep.status_konfirmasi FROM events e
    JOIN event_panitia ep ON ep.event_id = e.id
    WHERE ep.user_id = ? AND ep.peran_acara != 'pic'
    ORDER BY e.tanggal_mulai ASC LIMIT 5
");
$myJoin->execute([$uid]);
$acaraPanitia = $myJoin->fetchAll();

$statusLabel = [
    'draft'               => 'Draft',
    'pengajuan'           => 'Pengajuan',
    'disetujui_manager'   => 'Disetujui Manager',
    'proposal_dibuat'     => 'Proposal Dibuat',
    'rab_diajukan'        => 'RAB Diajukan',
    'perijinan'           => 'Perijinan',
    'disetujui'           => 'Disetujui',
    'berlangsung'         => 'Berlangsung',
    'selesai'             => 'Selesai',
    'ditolak'             => 'Ditolak',
];
?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-badge"></i></div>
      <div>
        <div class="stat-value"><?= $totalPIC ?></div>
        <div class="stat-label">Acara sebagai PIC</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-people"></i></div>
      <div>
        <div class="stat-value"><?= $totalPanitia ?></div>
        <div class="stat-label">Acara sebagai Panitia</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock-history"></i></div>
      <div>
        <div class="stat-value"><?= $totalPending ?></div>
        <div class="stat-label">Menunggu Konfirmasi</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-calendar3"></i></div>
      <div>
        <div class="stat-value"><?= $totalEvents ?></div>
        <div class="stat-label">Total Acara Aktif</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Acara sebagai PIC -->
  <div class="col-md-6">
    <div class="card-section">
      <div class="card-header-bar">
        <span><i class="bi bi-person-badge me-2 text-primary"></i>Acara yang Saya Kelola</span>
        <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-sm btn-primary">
          <i class="bi bi-plus-lg"></i> Buat Acara
        </a>
      </div>
      <?php if ($acaraPIC): ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($acaraPIC as $ev): ?>
        <li class="list-group-item d-flex align-items-center gap-2 py-3">
          <div class="flex-grow-1">
            <div class="fw-600 small"><?= htmlspecialchars($ev['judul']) ?></div>
            <div class="text-muted" style="font-size:.78rem">
              <?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?> &bull; <?= $ev['level'] ?>
            </div>
          </div>
          <span class="badge-status status-<?= $ev['status'] ?>">
            <?= $statusLabel[$ev['status']] ?? $ev['status'] ?>
          </span>
          <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $ev['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">
            <i class="bi bi-eye"></i>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="text-center text-muted py-5 small">
        <i class="bi bi-calendar-x d-block mb-2 fs-4"></i>Belum ada acara yang Anda kelola
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Acara sebagai Panitia -->
  <div class="col-md-6">
    <div class="card-section">
      <div class="card-header-bar">
        <span><i class="bi bi-people me-2 text-success"></i>Acara yang Saya Ikuti</span>
      </div>
      <?php if ($acaraPanitia): ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($acaraPanitia as $ev): ?>
        <li class="list-group-item d-flex align-items-center gap-2 py-3">
          <div class="flex-grow-1">
            <div class="fw-600 small"><?= htmlspecialchars($ev['judul']) ?></div>
            <div class="text-muted" style="font-size:.78rem">
              <?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?> &bull;
              <?= htmlspecialchars($ev['bagian'] ?? ucfirst($ev['peran_acara'])) ?>
            </div>
          </div>
          <?php if ($ev['status_konfirmasi'] === 'pending'): ?>
          <span class="badge bg-warning text-dark" style="font-size:.7rem">Pending</span>
          <?php elseif ($ev['status_konfirmasi'] === 'bersedia'): ?>
          <span class="badge bg-success" style="font-size:.7rem">Bersedia</span>
          <?php else: ?>
          <span class="badge bg-danger" style="font-size:.7rem">Tidak Bisa</span>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $ev['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">
            <i class="bi bi-eye"></i>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="text-center text-muted py-5 small">
        <i class="bi bi-person-x d-block mb-2 fs-4"></i>Belum terdaftar di acara manapun
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
PHP;

/* ============================================================
   modules/events/index.php
   ============================================================ */
$files['modules/events/index.php'] = <<<'PHP'
<?php
$pageTitle = 'Semua Acara';
require_once __DIR__ . '/../../includes/layout/header.php';

$uid  = $_SESSION['user_id'];
$role = $user['role_sistem'] ?? 'staff';

$filterStatus = $_GET['status'] ?? '';
$filterLevel  = $_GET['level']  ?? '';
$search       = $_GET['q']      ?? '';

$where  = ['1=1'];
$params = [];

if ($role !== 'superadmin') {
    $where[]  = 'ep.user_id = ?';
    $params[] = $uid;
    $join     = 'JOIN event_panitia ep ON ep.event_id = e.id';
} else {
    $join = 'LEFT JOIN event_panitia ep ON ep.event_id = e.id AND ep.peran_acara = "pic"';
}

if ($filterStatus) { $where[] = 'e.status = ?';  $params[] = $filterStatus; }
if ($filterLevel)  { $where[] = 'e.level = ?';   $params[] = $filterLevel;  }
if ($search)       { $where[] = 'e.judul LIKE ?'; $params[] = "%$search%";   }

$sql = "SELECT e.*, ep.peran_acara, u.nama AS nama_pic
        FROM events e
        $join
        LEFT JOIN users u ON u.id = e.pic_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY e.id
        ORDER BY e.tanggal_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$statusLabel = [
    'draft'             => 'Draft',        'pengajuan'         => 'Pengajuan',
    'disetujui_manager' => 'Disetujui Mgr','proposal_dibuat'   => 'Proposal',
    'rab_diajukan'      => 'RAB',          'perijinan'         => 'Perijinan',
    'disetujui'         => 'Disetujui',    'berlangsung'       => 'Berlangsung',
    'selesai'           => 'Selesai',      'ditolak'           => 'Ditolak',
];
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <h5 class="mb-0 fw-700">Daftar Acara</h5>
  <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i> Buat Acara
  </a>
</div>

<!-- Filter -->
<div class="card-section mb-3 p-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control form-control-sm"
             placeholder="Cari nama acara…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-3">
      <select name="status" class="form-select form-select-sm">
        <option value="">Semua Status</option>
        <?php foreach ($statusLabel as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= $filterStatus===$val?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="level" class="form-select form-select-sm">
        <option value="">Semua Level</option>
        <?php foreach (['TK','SD','SMP','Umum'] as $lv): ?>
        <option value="<?= $lv ?>" <?= $filterLevel===$lv?'selected':'' ?>><?= $lv ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm flex-fill">Filter</button>
      <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </form>
</div>

<!-- Tabel -->
<div class="card-section">
  <?php if ($events): ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th>Nama Acara</th>
          <th>Level</th>
          <th>Tanggal</th>
          <th>PIC</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $ev): ?>
        <tr class="event-row">
          <td>
            <div class="fw-600"><?= htmlspecialchars($ev['judul']) ?></div>
            <?php if ($ev['lokasi']): ?>
            <div class="text-muted small"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($ev['lokasi']) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-secondary"><?= $ev['level'] ?></span></td>
          <td class="small">
            <?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?>
            <?php if ($ev['tanggal_selesai'] !== $ev['tanggal_mulai']): ?>
            <br><span class="text-muted">s/d <?= date('d M Y', strtotime($ev['tanggal_selesai'])) ?></span>
            <?php endif; ?>
          </td>
          <td class="small"><?= htmlspecialchars($ev['nama_pic'] ?? '-') ?></td>
          <td><span class="badge-status status-<?= $ev['status'] ?>"><?= $statusLabel[$ev['status']] ?? $ev['status'] ?></span></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $ev['id'] ?>"
               class="btn btn-sm btn-outline-primary">
              <i class="bi bi-eye"></i> Detail
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-calendar-x d-block mb-2 fs-3"></i>
    Tidak ada acara ditemukan.
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
PHP;

/* ============================================================
   modules/events/create.php   ← INI YANG BELUM BISA JALAN
   ============================================================ */
$files['modules/events/create.php'] = <<<'PHP'
<?php
$pageTitle = 'Buat Acara Baru';
require_once __DIR__ . '/../../includes/layout/header.php';

// Ambil daftar template (acara yang sudah pernah ada)
$templates = $pdo->query(
    "SELECT id, judul, level, tanggal_mulai FROM events ORDER BY tanggal_mulai DESC LIMIT 50"
)->fetchAll();

$errors = [];
$old    = [];   // untuk repopulate form jika ada error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $judul       = trim($_POST['judul']         ?? '');
    $level       = $_POST['level']              ?? '';
    $tgl_mulai   = $_POST['tanggal_mulai']      ?? '';
    $tgl_selesai = $_POST['tanggal_selesai']    ?? '';
    $lokasi      = trim($_POST['lokasi']        ?? '');
    $deskripsi   = trim($_POST['deskripsi']     ?? '');
    $templateId  = ($_POST['template_id'] ?? '') ?: null;

    // Validasi
    if (!$judul)                       $errors[] = 'Nama acara wajib diisi.';
    if (!$level)                       $errors[] = 'Level wajib dipilih.';
    if (!$tgl_mulai)                   $errors[] = 'Tanggal mulai wajib diisi.';
    if (!$tgl_selesai)                 $errors[] = 'Tanggal selesai wajib diisi.';
    if ($tgl_selesai < $tgl_mulai)    $errors[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Insert event
            $ins = $pdo->prepare("
                INSERT INTO events (judul, level, tanggal_mulai, tanggal_selesai, lokasi, deskripsi, status, pic_id, template_id)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?)
            ");
            $ins->execute([
                $judul, $level, $tgl_mulai, $tgl_selesai,
                $lokasi, $deskripsi,
                $_SESSION['user_id'],
                $templateId
            ]);
            $eventId = (int)$pdo->lastInsertId();

            // 2. Daftarkan pembuat sebagai PIC di event_panitia
            $pic = $pdo->prepare("
                INSERT INTO event_panitia (event_id, user_id, peran_acara, is_event_admin, status_konfirmasi)
                VALUES (?, ?, 'pic', 1, 'bersedia')
            ");
            $pic->execute([$eventId, $_SESSION['user_id']]);

            // 3. Kalau pakai template, clone checklist dari acara sumber
            if ($templateId) {
                $chk = $pdo->prepare("SELECT * FROM event_checklist WHERE event_id = ?");
                $chk->execute([$templateId]);
                $checklists = $chk->fetchAll();
                if ($checklists) {
                    $insChk = $pdo->prepare("
                        INSERT INTO event_checklist (event_id, urutan, item, keterangan)
                        VALUES (?, ?, ?, ?)
                    ");
                    foreach ($checklists as $c) {
                        $insChk->execute([$eventId, $c['urutan'], $c['item'], $c['keterangan']]);
                    }
                }
            }

            $pdo->commit();

            setFlash("Acara \"{$judul}\" berhasil dibuat! Silakan lengkapi detail acara.", 'success');
            header('Location: ' . BASE_URL . '/modules/events/detail.php?id=' . $eventId);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= BASE_URL ?>/modules/events/" class="text-muted text-decoration-none">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-700">Buat Acara Baru</h5>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-3">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <ul class="mb-0 ps-3">
    <?php foreach ($errors as $e): ?>
    <li><?= htmlspecialchars($e) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <form method="POST" class="form-section">

      <!-- Nama Acara -->
      <div class="mb-3">
        <label class="form-label">Nama Acara <span class="text-danger">*</span></label>
        <input type="text" name="judul" class="form-control"
               placeholder="contoh: Peringatan Hari Pahlawan 2026"
               value="<?= htmlspecialchars($old['judul'] ?? '') ?>" required>
      </div>

      <!-- Level -->
      <div class="mb-3">
        <label class="form-label">Level / Jenjang <span class="text-danger">*</span></label>
        <select name="level" class="form-select" required>
          <option value="">-- Pilih Level --</option>
          <?php foreach (['TK','SD','SMP','Umum'] as $lv): ?>
          <option value="<?= $lv ?>" <?= ($old['level'] ?? '') === $lv ? 'selected' : '' ?>><?= $lv ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Tanggal -->
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
          <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-control"
                 value="<?= htmlspecialchars($old['tanggal_mulai'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
          <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-control"
                 value="<?= htmlspecialchars($old['tanggal_selesai'] ?? '') ?>" required>
        </div>
      </div>

      <!-- Lokasi -->
      <div class="mb-3">
        <label class="form-label">Lokasi</label>
        <input type="text" name="lokasi" class="form-control"
               placeholder="contoh: Aula Utama Lt. 2"
               value="<?= htmlspecialchars($old['lokasi'] ?? '') ?>">
      </div>

      <!-- Deskripsi -->
      <div class="mb-4">
        <label class="form-label">Deskripsi / Catatan</label>
        <textarea name="deskripsi" class="form-control" rows="4"
                  placeholder="Gambaran umum acara, tujuan, dll."><?= htmlspecialchars($old['deskripsi'] ?? '') ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn-primary-custom">
          <i class="bi bi-check-lg me-1"></i> Simpan & Buat Acara
        </button>
        <a href="<?= BASE_URL ?>/modules/events/" class="btn btn-outline-secondary">Batal</a>
      </div>

    </form>
  </div>

  <!-- Sidebar: Pilih Template -->
  <div class="col-lg-4">
    <div class="form-section">
      <h6 class="fw-700 mb-1"><i class="bi bi-files me-2 text-primary"></i>Gunakan Template</h6>
      <p class="text-muted small mb-3">
        Pilih acara tahun lalu untuk meng-clone checklist panitia secara otomatis.
      </p>
      <?php if ($templates): ?>
      <form id="templatePicker">
        <input type="text" id="templateSearch" class="form-control form-control-sm mb-2"
               placeholder="Cari acara...">
        <div style="max-height:300px;overflow-y:auto;">
          <div class="list-group list-group-flush" id="templateList">
            <?php foreach ($templates as $t): ?>
            <label class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-2 cursor-pointer">
              <input type="radio" name="template_id" form="mainForm"
                     value="<?= $t['id'] ?>"
                     <?= ($old['template_id'] ?? '') == $t['id'] ? 'checked' : '' ?>>
              <div>
                <div class="small fw-600"><?= htmlspecialchars($t['judul']) ?></div>
                <div class="text-muted" style="font-size:.72rem">
                  <?= $t['level'] ?> &bull; <?= date('Y', strtotime($t['tanggal_mulai'])) ?>
                </div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="button" class="btn btn-link btn-sm text-muted p-0 mt-1"
                onclick="document.querySelectorAll('input[name=template_id]').forEach(r=>r.checked=false)">
          Hapus pilihan template
        </button>
      </form>
      <?php else: ?>
      <div class="text-center text-muted small py-3">
        <i class="bi bi-archive d-block mb-1 fs-4"></i>Belum ada acara tersimpan.
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
// Sinkronisasi radio template ke form utama
document.querySelectorAll('input[name=template_id]').forEach(r => {
  r.form = document.querySelector('form[method=POST]');
});

// Filter template
document.getElementById('templateSearch')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#templateList label').forEach(lbl => {
    lbl.style.display = lbl.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
JS;
require_once __DIR__ . '/../../includes/layout/footer.php';
?>
PHP;

/* ============================================================
   modules/events/detail.php
   ============================================================ */
$files['modules/events/detail.php'] = <<<'PHP'
<?php
$pageTitle = 'Detail Acara';
require_once __DIR__ . '/../../includes/layout/header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/modules/events/'); exit; }

$stmt = $pdo->prepare("
    SELECT e.*, u.nama AS nama_pic
    FROM events e
    LEFT JOIN users u ON u.id = e.pic_id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$ev = $stmt->fetch();
if (!$ev) { header('Location: ' . BASE_URL . '/modules/events/'); exit; }

$canManage = isSuperAdmin() || isPIC($id, $pdo) || isEventAdmin($id, $pdo);

$panitia = $pdo->prepare("
    SELECT ep.*, u.nama, u.divisi, u.jabatan
    FROM event_panitia ep
    JOIN users u ON u.id = ep.user_id
    WHERE ep.event_id = ?
    ORDER BY FIELD(ep.peran_acara,'pic','panitia_inti','panitia_support'), ep.bagian
");
$panitia->execute([$id]);
$daftarPanitia = $panitia->fetchAll();

$checklist = $pdo->prepare("SELECT * FROM event_checklist WHERE event_id=? ORDER BY urutan");
$checklist->execute([$id]);
$items = $checklist->fetchAll();

$statusLabel = [
    'draft'             => 'Draft',          'pengajuan'         => 'Pengajuan',
    'disetujui_manager' => 'Disetujui Mgr',  'proposal_dibuat'   => 'Proposal Dibuat',
    'rab_diajukan'      => 'RAB Diajukan',   'perijinan'         => 'Perijinan',
    'disetujui'         => 'Disetujui',      'berlangsung'       => 'Berlangsung',
    'selesai'           => 'Selesai',        'ditolak'           => 'Ditolak',
];
$peranLabel = ['pic'=>'PIC','panitia_inti'=>'Panitia Inti','panitia_support'=>'Panitia Support'];
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= BASE_URL ?>/modules/events/" class="text-muted text-decoration-none">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-700 flex-grow-1"><?= htmlspecialchars($ev['judul']) ?></h5>
  <span class="badge-status status-<?= $ev['status'] ?>"><?= $statusLabel[$ev['status']] ?? $ev['status'] ?></span>
</div>

<div class="row g-3">
  <!-- Info Acara -->
  <div class="col-lg-8">
    <div class="card-section mb-3 p-4">
      <div class="row g-3 mb-3">
        <div class="col-sm-4">
          <div class="text-muted small">Level</div>
          <div class="fw-600"><?= htmlspecialchars($ev['level']) ?></div>
        </div>
        <div class="col-sm-4">
          <div class="text-muted small">Tanggal Mulai</div>
          <div class="fw-600"><?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?></div>
        </div>
        <div class="col-sm-4">
          <div class="text-muted small">Tanggal Selesai</div>
          <div class="fw-600"><?= date('d M Y', strtotime($ev['tanggal_selesai'])) ?></div>
        </div>
        <div class="col-sm-4">
          <div class="text-muted small">Lokasi</div>
          <div class="fw-600"><?= htmlspecialchars($ev['lokasi'] ?: '-') ?></div>
        </div>
        <div class="col-sm-4">
          <div class="text-muted small">PIC</div>
          <div class="fw-600"><?= htmlspecialchars($ev['nama_pic'] ?? '-') ?></div>
        </div>
      </div>
      <?php if ($ev['deskripsi']): ?>
      <div class="border-top pt-3">
        <div class="text-muted small mb-1">Deskripsi</div>
        <div class="small"><?= nl2br(htmlspecialchars($ev['deskripsi'])) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Susunan Panitia -->
    <div class="card-section">
      <div class="card-header-bar">
        <span><i class="bi bi-people me-2"></i>Susunan Panitia</span>
        <?php if ($canManage): ?>
        <a href="<?= BASE_URL ?>/modules/panitia/assign.php?event_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-person-plus"></i> Tambah Panitia
        </a>
        <?php endif; ?>
      </div>
      <?php if ($daftarPanitia): ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>Nama</th><th>Peran</th><th>Bagian</th><th>Konfirmasi</th></tr></thead>
          <tbody>
            <?php foreach ($daftarPanitia as $p): ?>
            <tr>
              <td>
                <div class="fw-600 small"><?= htmlspecialchars($p['nama']) ?></div>
                <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($p['divisi'] ?? '') ?></div>
              </td>
              <td><span class="badge bg-secondary"><?= $peranLabel[$p['peran_acara']] ?? $p['peran_acara'] ?></span></td>
              <td class="small"><?= htmlspecialchars($p['bagian'] ?? '-') ?></td>
              <td>
                <?php if ($p['status_konfirmasi']==='bersedia'): ?>
                <span class="badge bg-success">Bersedia</span>
                <?php elseif ($p['status_konfirmasi']==='tidak_bisa'): ?>
                <span class="badge bg-danger">Tidak Bisa</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark">Pending</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center text-muted py-4 small">
        <i class="bi bi-person-plus d-block mb-1 fs-3"></i>Belum ada panitia ditugaskan.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Sidebar: Checklist & Aksi -->
  <div class="col-lg-4">
    <?php if ($items): ?>
    <div class="card-section mb-3">
      <div class="card-header-bar"><span><i class="bi bi-list-check me-2"></i>Checklist Panitia</span></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($items as $c): ?>
        <li class="list-group-item d-flex gap-2 align-items-start py-2 small">
          <i class="bi bi-check2-circle text-success mt-1"></i>
          <div>
            <div class="fw-600"><?= htmlspecialchars($c['item']) ?></div>
            <?php if ($c['keterangan']): ?>
            <div class="text-muted"><?= htmlspecialchars($c['keterangan']) ?></div>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <div class="card-section p-3">
      <h6 class="fw-700 mb-2">Aksi Cepat</h6>
      <div class="d-grid gap-2">
        <?php if ($ev['status'] === 'draft'): ?>
        <a href="?id=<?=$id?>&action=submit" class="btn btn-primary btn-sm"
           data-confirm="Ajukan acara ini untuk persetujuan?">
          <i class="bi bi-send me-1"></i> Ajukan ke Manager
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/events/edit.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-pencil me-1"></i> Edit Acara
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
// Handle quick action: submit
if (isset($_GET['action']) && $_GET['action'] === 'submit' && $canManage && $ev['status'] === 'draft') {
    $pdo->prepare("UPDATE events SET status='pengajuan' WHERE id=?")->execute([$id]);
    setFlash('Acara berhasil diajukan untuk persetujuan.', 'success');
    header('Location: ' . BASE_URL . '/modules/events/detail.php?id=' . $id);
    exit;
}
require_once __DIR__ . '/../../includes/layout/footer.php';
?>
PHP;

/* ============================================================
   index.php (root redirect)
   ============================================================ */
$files['index.php'] = <<<'PHP'
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/');
} else {
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
}
exit;
PHP;

/* ============================================================
   Tulis semua file
   ============================================================ */
$ok = 0; $fail = 0;
foreach ($files as $path => $content) {
    $full = ROOT . '/' . $path;
    $dir  = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (file_put_contents($full, $content) !== false) {
        echo "✓ $path\n";
        $ok++;
    } else {
        echo "✗ GAGAL: $path\n";
        $fail++;
    }
}

echo "\n";
echo "============================\n";
echo "Selesai: $ok file ditulis" . ($fail ? ", $fail GAGAL" : '') . "\n";
echo "============================\n";
