<?php
$pageTitle = 'Profil Saya';
require_once __DIR__ . '/../../includes/layout/header.php';

$uid    = $_SESSION['user_id'];
$errors = []; $success = '';

// Ambil data terbaru
$me = $pdo->prepare("SELECT * FROM users WHERE id=?");
$me->execute([$uid]); $me = $me->fetch();

// Update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nama    = trim($_POST['nama'] ?? '');
    $no_wa   = trim($_POST['no_wa'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    if (!$nama) $errors[] = 'Nama tidak boleh kosong.';
    if (empty($errors)) {
        $pdo->prepare("UPDATE users SET nama=?, no_wa=?, jabatan=?, updated_at=NOW() WHERE id=?")
            ->execute([$nama, $no_wa, $jabatan, $uid]);
        $_SESSION['user']['nama']    = $nama;
        $_SESSION['user']['no_wa']   = $no_wa;
        $_SESSION['user']['jabatan'] = $jabatan;
        setFlash('Profil berhasil diperbarui!', 'success');
        header('Location: ?'); exit;
    }
}

// Ganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ganti_password'])) {
    $lama = $_POST['password_lama'] ?? '';
    $baru = $_POST['password_baru'] ?? '';
    $conf = $_POST['password_konfirm'] ?? '';
    if (!password_verify($lama, $me['password'])) $errors[] = 'Password lama salah.';
    if (strlen($baru) < 6) $errors[] = 'Password baru minimal 6 karakter.';
    if ($baru !== $conf) $errors[] = 'Konfirmasi password tidak cocok.';
    if (empty($errors)) {
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($baru, PASSWORD_DEFAULT), $uid]);
        setFlash('Password berhasil diubah!', 'success');
        header('Location: ?'); exit;
    }
}

// Riwayat acara
$riwayat = $pdo->prepare("
    SELECT e.judul, e.level, e.tanggal_mulai, ep.peran_acara, ep.bagian, ep.status_konfirmasi
    FROM event_panitia ep JOIN events e ON e.id=ep.event_id
    WHERE ep.user_id=? ORDER BY e.tanggal_mulai DESC LIMIT 10
");
$riwayat->execute([$uid]); $riwayatList = $riwayat->fetchAll();
?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
  <!-- Kartu Profil -->
  <div class="col-md-4">
    <div class="card text-center mb-3">
      <div class="card-body py-4">
        <div style="width:80px;height:80px;background:#1E3A5F;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.8rem;color:#fff;font-weight:700">
          <?= $initials ?>
        </div>
        <h5 class="fw-bold mb-0"><?= htmlspecialchars($me['nama']) ?></h5>
        <p class="text-muted small mb-1"><?= htmlspecialchars($me['jabatan'] ?? '-') ?></p>
        <p class="text-muted small mb-2"><?= htmlspecialchars($me['divisi'] ?? '-') ?></p>
        <span class="badge <?= $me['role_sistem']==='superadmin'?'bg-danger':'bg-secondary' ?>"><?= $me['role_sistem'] ?></span>
      </div>
    </div>
    <!-- Stats -->
    <div class="card">
      <div class="card-header fw-semibold small"><i class="bi bi-bar-chart me-2"></i>Statistik Saya</div>
      <div class="card-body">
        <?php
        $totalPIC = $pdo->prepare("SELECT COUNT(*) FROM event_panitia WHERE user_id=? AND peran_acara='pic'")->execute([$uid]) ? $pdo->query("SELECT COUNT(*) FROM event_panitia WHERE user_id=$uid AND peran_acara='pic'")->fetchColumn() : 0;
        $totalIkut = $pdo->prepare("SELECT COUNT(*) FROM event_panitia WHERE user_id=?")->execute([$uid]) ? $pdo->query("SELECT COUNT(*) FROM event_panitia WHERE user_id=$uid")->fetchColumn() : 0;
        ?>
        <div class="d-flex justify-content-between mb-2">
          <span class="small text-muted">Acara sebagai PIC</span>
          <span class="fw-bold text-primary"><?= $totalPIC ?></span>
        </div>
        <div class="d-flex justify-content-between">
          <span class="small text-muted">Total keterlibatan</span>
          <span class="fw-bold text-primary"><?= $totalIkut ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <!-- Edit Profil -->
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-pencil me-2"></i>Edit Profil</div>
      <div class="card-body">
        <form method="POST" class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($me['nama']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">No. WhatsApp</label>
            <input type="text" name="no_wa" class="form-control" value="<?= htmlspecialchars($me['no_wa'] ?? '') ?>" placeholder="08xxxxxxxxxx">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($me['email']) ?>" disabled>
            <small class="text-muted">Email tidak dapat diubah</small>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Jabatan</label>
            <input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($me['jabatan'] ?? '') ?>">
          </div>
          <div class="col-12">
            <button type="submit" name="update_profil" class="btn btn-primary">
              <i class="bi bi-save me-1"></i> Simpan Perubahan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Ganti Password -->
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-lock me-2"></i>Ganti Password</div>
      <div class="card-body">
        <form method="POST" class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Password Lama</label>
            <input type="password" name="password_lama" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Password Baru</label>
            <input type="password" name="password_baru" class="form-control" minlength="6" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Konfirmasi Password</label>
            <input type="password" name="password_konfirm" class="form-control" required>
          </div>
          <div class="col-12">
            <button type="submit" name="ganti_password" class="btn btn-warning">
              <i class="bi bi-shield-lock me-1"></i> Ganti Password
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Riwayat Acara -->
    <div class="card">
      <div class="card-header"><i class="bi bi-clock-history me-2"></i>Riwayat Keterlibatan Acara</div>
      <div class="card-body p-0">
        <?php if (empty($riwayatList)): ?>
          <div class="text-center text-muted py-3 small">Belum ada riwayat</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Acara</th><th>Peran</th><th>Bagian</th><th>Konfirmasi</th></tr></thead>
              <tbody>
              <?php foreach ($riwayatList as $r): ?>
                <tr>
                  <td>
                    <div class="small fw-semibold"><?= htmlspecialchars($r['judul']) ?></div>
                    <span class="badge bg-secondary" style="font-size:.65rem"><?= $r['level'] ?></span>
                    <span class="text-muted" style="font-size:.7rem"> <?= date('d M Y', strtotime($r['tanggal_mulai'])) ?></span>
                  </td>
                  <td><small><?= $r['peran_acara'] === 'pic' ? 'PIC' . ($r['bagian'] ? ' + ' . htmlspecialchars($r['bagian']) : '') : ($r['bagian'] ? htmlspecialchars($r['bagian']) : 'Panitia') ?></small></td>
                  <td><small><?= htmlspecialchars($r['bagian'] ?? '-') ?></small></td>
                  <td>
                    <?php $kc=['pending'=>'warning','bersedia'=>'success','tidak_bisa'=>'danger']; ?>
                    <span class="badge bg-<?= $kc[$r['status_konfirmasi']] ?>"><?= $r['status_konfirmasi'] ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
