<?php
declare(strict_types=1);

// Tidak pakai header.php — login adalah halaman standalone
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Kalau sudah login → langsung ke beranda
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/select.php');
    exit;
}

$error = '';

// ── Cek maintenance mode ──────────────────────────────────────────────────────
// Query langsung tanpa helper karena user belum login
$maintenanceRow = $pdo->query(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode' LIMIT 1"
)->fetchColumn();
$inMaintenance = ($maintenanceRow === '1');
$maintenanceMsgRow = '';
if ($inMaintenance) {
    $maintenanceMsgRow = (string)$pdo->query(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_msg' LIMIT 1"
    )->fetchColumn();
}
// ─────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =       $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'aktif' LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if ($u && password_verify($password, $u['password'])) {
            // Blokir login saat maintenance — kecuali superadmin
            if ($inMaintenance && ($u['role_sistem'] ?? '') !== 'superadmin') {
                $msg = $maintenanceMsgRow ?: 'Sistem sedang dalam pemeliharaan.';
                $error = '🔧 ' . $msg;
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['user']    = $u;
                // Load permission role ke session agar hasPermission() tidak perlu
                // query DB setiap request. Cache ini di-refresh saat settings diubah.
                loadPermissions($pdo);
                header('Location: ' . BASE_URL . '/modules/dashboard/select.php');
                exit;
            }
        } else {
            $error = 'Email atau password salah.';
        }
    }
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      background: linear-gradient(135deg, #0f2443 0%, #1a3a5c 60%, #245a8a 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
    }

    .wrap {
      width: 100%;
      max-width: 420px;
    }

    .card-login {
      background: #fff;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 24px 64px rgba(0,0,0,.32);
    }

    .card-top {
      background: linear-gradient(135deg, #1a3a5c, #245a8a);
      padding: 36px 32px 32px;
      text-align: center;
    }

    .logo-box {
      width: 64px;
      height: 64px;
      background: rgba(255,255,255,.15);
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 18px;
      font-size: 1.75rem;
      color: #7dd3fc;
    }

    .app-name {
      font-size: 1.5rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: -.02em;
    }

    .app-sub {
      font-size: .8rem;
      color: rgba(255,255,255,.55);
      margin-top: 4px;
    }

    .card-body-login {
      padding: 32px;
    }

    .field-label {
      font-size: .8rem;
      font-weight: 700;
      color: #334155;
      display: block;
      margin-bottom: 6px;
    }

    .field-input {
      width: 100%;
      border: 1.5px solid #e2e8f0;
      border-radius: 9px;
      padding: 10px 14px;
      font-size: .875rem;
      font-family: inherit;
      color: #1e293b;
      transition: border-color .2s, box-shadow .2s;
      background: #fff;
    }

    .field-input:focus {
      outline: none;
      border-color: #0ea5e9;
      box-shadow: 0 0 0 3px rgba(14,165,233,.15);
    }

    .field-group { margin-bottom: 18px; }

    .btn-login {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #1a3a5c, #245a8a);
      color: #fff;
      font-size: .9rem;
      font-weight: 700;
      font-family: inherit;
      border: none;
      border-radius: 9px;
      cursor: pointer;
      transition: opacity .2s, transform .15s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 8px;
    }

    .btn-login:hover  { opacity: .9; }
    .btn-login:active { transform: scale(.98); }

    .error-box {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: .82rem;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .hint {
      text-align: center;
      font-size: .75rem;
      color: #94a3b8;
      margin-top: 20px;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card-login">

      <!-- Top -->
      <div class="card-top">
        <div class="logo-box">
          <i class="bi bi-calendar-event-fill"></i>
        </div>
        <div class="app-name">SAPAcara</div>
        <div class="app-sub">Sistem Manajemen Acara Sekolah</div>
      </div>

      <!-- Body — hanya 1 form -->
      <div class="card-body-login">

        <?php if ($inMaintenance): ?>
          <div class="error-box" style="background:#fefce8;border-color:#fde68a;color:#92400e;">
            <i class="bi bi-tools" style="flex-shrink:0"></i>
            <span>Sistem dalam mode maintenance. Hanya Super Admin yang dapat masuk.</span>
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="error-box">
            <i class="bi bi-exclamation-circle-fill" style="flex-shrink:0"></i>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
          <div class="field-group">
            <label class="field-label" for="email">Alamat Email</label>
            <input
              class="field-input"
              type="email"
              id="email"
              name="email"
              placeholder="nama@sekolah.sch.id"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required
              autofocus
            >
          </div>

          <div class="field-group">
            <label class="field-label" for="password">Password</label>
            <input
              class="field-input"
              type="password"
              id="password"
              name="password"
              placeholder="••••••••"
              required
            >
          </div>

          <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right"></i>
            Masuk ke SAPAcara
          </button>
        </form>

        <p class="hint">Hubungi administrator jika lupa password</p>
      </div>

    </div>
  </div>
</body>
</html>
