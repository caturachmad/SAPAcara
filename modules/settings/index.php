<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

// Guard SEBELUM header.php agar redirect bisa berjalan (headers belum terkirim)
if (!hasPermission('pengaturan_sistem')) {
    setFlash('Anda tidak memiliki izin mengakses halaman ini.', 'danger');
    header('Location: ' . BASE_URL . '/modules/dashboard/select.php');
    exit;
}

$pageTitle = 'Pengaturan Sistem';
require_once __DIR__ . '/../../includes/layout/header.php';

// ── Definisi fitur untuk permission matrix ────────────────────────────────────
$featureList = [
    'buat_acara'        => ['label' => 'Buat Acara',          'icon' => 'plus-circle',        'desc' => 'Membuat acara baru di sistem'],
    'kelola_approval'   => ['label' => 'Kelola Approval',     'icon' => 'check2-circle',       'desc' => 'Menyetujui atau menolak pengajuan acara'],
    'admin_panel'       => ['label' => 'Admin Panel',         'icon' => 'grid-1x2-fill',       'desc' => 'Akses ke dashboard admin dan statistik'],
    'manajemen_sdm'     => ['label' => 'Manajemen SDM',       'icon' => 'person-gear',         'desc' => 'Tambah, edit, hapus data pengguna'],
    'import_excel'      => ['label' => 'Import Excel',        'icon' => 'file-earmark-excel',  'desc' => 'Import data SDM massal dari file Excel'],
    'pengaturan_sistem' => ['label' => 'Pengaturan Sistem',   'icon' => 'gear-fill',           'desc' => 'Akses ke halaman pengaturan ini'],
];

// Roles yang bisa dikonfigurasi (superadmin selalu full, dikunci di UI)
$editableRoles = ['admin', 'staff'];

// ── Handle POST: simpan settings ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $allowed = ['app_name','app_subtitle','school_name','maintenance_mode','maintenance_msg'];
    foreach ($allowed as $key) {
        $val = match($key) {
            'maintenance_mode' => isset($_POST[$key]) ? '1' : '0',
            default            => trim($_POST[$key] ?? ''),
        };
        $pdo->prepare(
            "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?"
        )->execute([$val, $key]);
    }
    setFlash('Pengaturan sistem berhasil disimpan.', 'success');
    header('Location: ?tab=general');
    exit;
}

// ── Handle POST: simpan permission matrix ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    foreach ($editableRoles as $role) {
        foreach (array_keys($featureList) as $feature) {
            $allowed = isset($_POST['perm'][$role][$feature]) ? 1 : 0;
            $pdo->prepare(
                "INSERT INTO role_permissions (role, feature, is_allowed)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)"
            )->execute([$role, $feature, $allowed]);
        }
    }
    // Refresh session permission jika user yang login adalah role yang baru diubah
    $currentRole = currentUser()['role_sistem'] ?? 'staff';
    if (in_array($currentRole, $editableRoles)) {
        refreshPermissions($pdo);
    }
    setFlash('Permission matrix berhasil disimpan.', 'success');
    header('Location: ?tab=permissions');
    exit;
}

// ── Load data ─────────────────────────────────────────────────────────────────
// Settings
$settingsRaw = $pdo->query(
    "SELECT setting_key, setting_value FROM system_settings"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// Permission matrix: [role][feature] = is_allowed
$permMatrix = [];
$permRows   = $pdo->query(
    "SELECT role, feature, is_allowed FROM role_permissions ORDER BY role, feature"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($permRows as $row) {
    $permMatrix[$row['role']][$row['feature']] = (bool)$row['is_allowed'];
}

$activeTab = $_GET['tab'] ?? 'general';
$flash     = getFlash();
?>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> fade show" role="alert">
    <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>-fill me-2"></i>
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
<?php endif; ?>

<div class="d-flex align-items-center gap-3 mb-4">
  <div style="width:44px;height:44px;background:linear-gradient(135deg,#1a3a5c,#245a8a);border-radius:12px;display:flex;align-items:center;justify-content:center;">
    <i class="bi bi-gear-fill text-white fs-5"></i>
  </div>
  <div>
    <h4 class="mb-0 fw-700">Pengaturan Sistem</h4>
    <div class="text-muted" style="font-size:.8rem">Konfigurasi aplikasi dan manajemen hak akses role</div>
  </div>
</div>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-4" id="settingsTabs">
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>"
       href="?tab=general">
      <i class="bi bi-sliders me-2"></i>Pengaturan Umum
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'permissions' ? 'active' : '' ?>"
       href="?tab=permissions">
      <i class="bi bi-shield-lock me-2"></i>Role &amp; Permission
    </a>
  </li>
</ul>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB 1: PENGATURAN UMUM
     ═══════════════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'general'): ?>
<div class="card shadow-sm border-0">
  <div class="card-body p-4">
    <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="save_settings" value="1">

      <h6 class="fw-700 mb-3 text-uppercase" style="font-size:.75rem;letter-spacing:.08em;color:#64748b;">
        Identitas Aplikasi
      </h6>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label fw-600">Nama Aplikasi</label>
          <input type="text" name="app_name" class="form-control"
                 value="<?= htmlspecialchars($settingsRaw['app_name'] ?? 'SAPAcara') ?>"
                 placeholder="SAPAcara" required>
          <div class="form-text">Tampil di header sidebar dan halaman login</div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-600">Nama Sekolah / Institusi</label>
          <input type="text" name="school_name" class="form-control"
                 value="<?= htmlspecialchars($settingsRaw['school_name'] ?? '') ?>"
                 placeholder="Sekolah Alam Bandung">
        </div>
        <div class="col-12">
          <label class="form-label fw-600">Subtitle Aplikasi</label>
          <input type="text" name="app_subtitle" class="form-control"
                 value="<?= htmlspecialchars($settingsRaw['app_subtitle'] ?? '') ?>"
                 placeholder="Sistem Manajemen Acara Sekolah">
          <div class="form-text">Tampil di bawah nama aplikasi pada halaman login</div>
        </div>
      </div>

      <hr class="my-4">

      <h6 class="fw-700 mb-3 text-uppercase" style="font-size:.75rem;letter-spacing:.08em;color:#64748b;">
        Mode Maintenance
      </h6>

      <div class="mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="maintenanceMode"
                 name="maintenance_mode" value="1"
                 <?= ($settingsRaw['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
          <label class="form-check-label fw-600" for="maintenanceMode">
            Aktifkan Mode Maintenance
          </label>
        </div>
        <div class="form-text mt-1">
          Saat aktif, hanya <strong>superadmin</strong> yang bisa login. User lain akan melihat pesan maintenance.
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-600">Pesan Maintenance</label>
        <textarea name="maintenance_msg" class="form-control" rows="3"
                  placeholder="Sistem sedang dalam pemeliharaan..."><?= htmlspecialchars($settingsRaw['maintenance_msg'] ?? '') ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-floppy me-2"></i>Simpan Pengaturan
        </button>
        <a href="?tab=general" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB 2: ROLE & PERMISSION MATRIX
     ═══════════════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'permissions'): ?>

<div class="alert alert-info border-0 d-flex gap-2 align-items-start mb-4" style="background:#eff6ff;">
  <i class="bi bi-info-circle-fill text-primary mt-1 flex-shrink-0"></i>
  <div style="font-size:.85rem">
    <strong>Super Admin</strong> selalu memiliki akses ke semua fitur dan tidak dapat dibatasi.
    Kolom Super Admin dikunci di sini hanya untuk referensi. Ubah permission <strong>Admin</strong>
    dan <strong>Staff</strong> sesuai kebutuhan, lalu klik <em>Simpan Permission</em>.
  </div>
</div>

<form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <input type="hidden" name="save_permissions" value="1">
  <div class="card shadow-sm border-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead style="background:#f8fafc;">
          <tr>
            <th style="min-width:240px;padding:14px 20px;">Fitur / Modul</th>
            <th class="text-center" style="width:140px;">
              <span class="badge bg-danger px-3 py-2" style="font-size:.8rem;">
                <i class="bi bi-shield-fill me-1"></i>Super Admin
              </span>
            </th>
            <th class="text-center" style="width:140px;">
              <span class="badge bg-primary px-3 py-2" style="font-size:.8rem;">
                <i class="bi bi-shield-half me-1"></i>Admin
              </span>
            </th>
            <th class="text-center" style="width:140px;">
              <span class="badge bg-secondary px-3 py-2" style="font-size:.8rem;">
                <i class="bi bi-person me-1"></i>Staff
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($featureList as $featureKey => $featureMeta): ?>
          <tr>
            <td style="padding:14px 20px;">
              <div class="d-flex align-items-center gap-3">
                <div style="width:36px;height:36px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                  <i class="bi bi-<?= $featureMeta['icon'] ?> text-primary" style="font-size:1rem;"></i>
                </div>
                <div>
                  <div class="fw-600" style="font-size:.875rem;"><?= htmlspecialchars($featureMeta['label']) ?></div>
                  <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($featureMeta['desc']) ?></div>
                </div>
              </div>
            </td>

            <!-- superadmin: selalu checked, disabled -->
            <td class="text-center">
              <div class="form-check d-flex justify-content-center">
                <input class="form-check-input" type="checkbox" checked disabled
                       style="width:1.2rem;height:1.2rem;cursor:not-allowed;opacity:.6;">
              </div>
            </td>

            <!-- admin: configurable -->
            <td class="text-center">
              <div class="form-check d-flex justify-content-center">
                <input class="form-check-input perm-check" type="checkbox"
                       name="perm[admin][<?= $featureKey ?>]"
                       style="width:1.2rem;height:1.2rem;cursor:pointer;"
                       <?= ($permMatrix['admin'][$featureKey] ?? false) ? 'checked' : '' ?>
                       data-role="admin" data-feature="<?= $featureKey ?>">
              </div>
            </td>

            <!-- staff: configurable -->
            <td class="text-center">
              <div class="form-check d-flex justify-content-center">
                <input class="form-check-input perm-check" type="checkbox"
                       name="perm[staff][<?= $featureKey ?>]"
                       style="width:1.2rem;height:1.2rem;cursor:pointer;"
                       <?= ($permMatrix['staff'][$featureKey] ?? false) ? 'checked' : '' ?>
                       data-role="staff" data-feature="<?= $featureKey ?>">
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex gap-2 align-items-center justify-content-between px-4 py-3"
         style="background:#f8fafc;border-top:1px solid #e2e8f0;">
      <div class="text-muted" style="font-size:.8rem;">
        <i class="bi bi-exclamation-triangle me-1 text-warning"></i>
        Perubahan permission akan langsung berlaku pada sesi login berikutnya untuk semua user.
        User yang sedang login perlu logout &amp; login kembali untuk merasakan perubahan.
      </div>
      <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-floppy me-2"></i>Simpan Permission
      </button>
    </div>
  </div>
</form>

<script>
// Visual feedback saat checkbox berubah
document.querySelectorAll('.perm-check').forEach(cb => {
  cb.addEventListener('change', function () {
    const row = this.closest('tr');
    row.style.background = '#fffbeb';
    setTimeout(() => row.style.background = '', 600);
  });
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
