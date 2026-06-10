<?php
$user     = currentUser();
$initials = strtoupper(substr($user['nama'] ?? 'U', 0, 2));
$role     = $user['role_sistem'] ?? 'staff';
$path     = $_SERVER['REQUEST_URI'];

function sNav(string $url, string $icon, string $label, string $path, string $badge = ''): void {
    $seg    = parse_url($url, PHP_URL_PATH);
    $active = ($seg && strlen($seg) > 1 && str_contains($path, $seg)) ? 'active' : '';
    $b      = $badge ? "<span class='badge bg-danger ms-auto fs-12'>$badge</span>" : '';
    echo "<a href='$url' class='nav-link $active'><i class='bi bi-$icon'></i><span>$label</span>$b</a>";
}
?>
<aside id="sidebar">
  <a href="<?= BASE_URL ?>/modules/dashboard/select.php" class="sidebar-brand">
    <div class="brand-icon"><i class="bi bi-calendar-event-fill"></i></div>
    <div>
      <div class="brand-name">SAPAcara</div>
      <div class="brand-sub">Sistem Manajemen Acara</div>
    </div>
  </a>

  <nav class="sidebar-nav">

    <!-- Semua role -->
    <div class="nav-section">Menu Utama</div>
    <?php sNav(BASE_URL.'/modules/dashboard/select.php', 'house-fill',   'Beranda',         $path); ?>
    <?php sNav(BASE_URL.'/modules/events/',              'calendar3',    'Semua Acara',      $path); ?>
    <?php if (hasPermission('buat_acara')): ?>
      <?php sNav(BASE_URL.'/modules/events/create.php', 'plus-circle',  'Buat Acara Baru',  $path); ?>
    <?php endif; ?>

    <!-- Administrasi: untuk superadmin dan admin (yang punya izin kelola_approval) -->
    <?php if (hasPermission('kelola_approval') || hasPermission('admin_panel')): ?>
      <div class="nav-section">Administrasi</div>
      <?php if (hasPermission('kelola_approval')): ?>
        <?php sNav(BASE_URL.'/modules/approvals/', 'check2-circle', 'Approval', $path); ?>
      <?php endif; ?>
      <?php if (hasPermission('admin_panel')): ?>
        <?php sNav(BASE_URL.'/modules/dashboard/', 'grid-1x2-fill', 'Admin Panel', $path); ?>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Hanya superadmin: Manajemen SDM -->
    <?php if (hasPermission('manajemen_sdm')): ?>
      <?php sNav(BASE_URL.'/modules/users/',              'person-gear',        'Manajemen SDM', $path); ?>
    <?php endif; ?>
    <?php if (hasPermission('import_excel')): ?>
      <?php sNav(BASE_URL.'/modules/users/import.php',   'file-earmark-excel', 'Import Excel',  $path); ?>
    <?php endif; ?>

    <!-- Pengaturan: hanya superadmin (atau admin jika diberi izin) -->
    <?php if (hasPermission('pengaturan_sistem')): ?>
      <div class="nav-section">Konfigurasi</div>
      <?php sNav(BASE_URL.'/modules/settings/', 'gear-fill', 'Pengaturan Sistem', $path); ?>
    <?php endif; ?>

    <!-- Semua role -->
    <div class="nav-section">Akun</div>
    <?php sNav(BASE_URL.'/modules/profile/',       'person-circle', 'Profil Saya', $path); ?>
    <?php sNav(BASE_URL.'/modules/notifications/', 'bell',          'Notifikasi',  $path); ?>

  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar avatar-sm flex-shrink-0"><?= $initials ?></div>
      <div class="overflow-hidden flex-grow-1">
        <div class="user-name"><?= htmlspecialchars($user['nama']) ?></div>
        <div class="user-role"><?= getRoleLabel($role) ?></div>
      </div>
      <a href="<?= BASE_URL ?>/modules/auth/logout.php"
         class="text-white opacity-50 flex-shrink-0" title="Logout" data-confirm="Yakin logout?">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</aside>
