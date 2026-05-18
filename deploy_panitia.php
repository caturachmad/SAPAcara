<?php
/**
 * SAPAcara – Deploy Tahap 4: Assign Panitia & Conflict Detection
 * Jalankan: php /var/www/html/siakad/deploy_panitia.php
 */

define('ROOT', '/var/www/html/siakad');

$files = [];

/* ============================================================
   modules/panitia/assign.php
   ============================================================ */
$files['modules/panitia/assign.php'] = <<<'PHP'
<?php
$pageTitle = 'Assign Panitia';
require_once __DIR__ . '/../../includes/layout/header.php';

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) { header('Location: ' . BASE_URL . '/modules/events/'); exit; }

// Ambil data event
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$ev = $stmt->fetch();
if (!$ev) { header('Location: ' . BASE_URL . '/modules/events/'); exit; }

// Cek akses: hanya PIC, Event Admin, atau Super Admin
if (!isSuperAdmin() && !isPIC($eventId, $pdo) && !isEventAdmin($eventId, $pdo)) {
    setFlash('Anda tidak punya akses ke halaman ini.', 'danger');
    header('Location: ' . BASE_URL . '/modules/events/detail.php?id=' . $eventId);
    exit;
}

// ── Handle POST: tambah panitia ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId    = (int)($_POST['user_id']    ?? 0);
    $peran     = $_POST['peran_acara']      ?? '';
    $bagian    = trim($_POST['bagian']      ?? '');
    $catatan   = trim($_POST['catatan']     ?? '');
    $forceDouble = isset($_POST['force_double']);

    $errors = [];
    if (!$userId)  $errors[] = 'Pilih SDM terlebih dahulu.';
    if (!$peran)   $errors[] = 'Pilih peran/bagian.';
    if (!in_array($peran, ['panitia_inti','panitia_support'])) $errors[] = 'Peran tidak valid.';

    if (empty($errors)) {
        // Cek apakah sudah terdaftar di acara ini
        $cekSama = $pdo->prepare("SELECT id FROM event_panitia WHERE event_id=? AND user_id=?");
        $cekSama->execute([$eventId, $userId]);
        if ($cekSama->fetch()) {
            $errors[] = 'SDM ini sudah terdaftar di acara ini.';
        }
    }

    // Deteksi konflik tanggal
    $isConflict = false;
    $conflictInfo = '';
    if (empty($errors)) {
        $cekBentrok = $pdo->prepare("
            SELECT e.judul, e.tanggal_mulai, e.tanggal_selesai
            FROM event_panitia ep
            JOIN events e ON e.id = ep.event_id
            WHERE ep.user_id = ?
              AND e.id != ?
              AND e.status NOT IN ('selesai','ditolak')
              AND e.tanggal_mulai <= ?
              AND e.tanggal_selesai >= ?
            LIMIT 1
        ");
        $cekBentrok->execute([$userId, $eventId, $ev['tanggal_selesai'], $ev['tanggal_mulai']]);
        $bentrok = $cekBentrok->fetch();
        if ($bentrok) {
            $isConflict = true;
            $conflictInfo = $bentrok['judul'] . ' (' . date('d M Y', strtotime($bentrok['tanggal_mulai'])) . ')';
            if (!$forceDouble) {
                $errors[] = "BENTROK|{$conflictInfo}";
            }
        }
    }

    if (empty($errors)) {
        $token = bin2hex(random_bytes(32));
        $ins = $pdo->prepare("
            INSERT INTO event_panitia
              (event_id, user_id, peran_acara, bagian, is_double_job, status_konfirmasi, token_konfirmasi, token_expires_at, catatan)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, DATE_ADD(NOW(), INTERVAL 7 DAY), ?)
        ");
        $ins->execute([
            $eventId, $userId, $peran, $bagian,
            $isConflict ? 1 : 0,
            $token, $catatan
        ]);
        setFlash('Panitia berhasil ditambahkan.', 'success');
        header('Location: ' . BASE_URL . '/modules/panitia/assign.php?event_id=' . $eventId);
        exit;
    }

    // Pisahkan error konflik dari error biasa
    $conflictError = '';
    $normalErrors  = [];
    foreach ($errors as $e) {
        if (str_starts_with($e, 'BENTROK|')) {
            $conflictError = substr($e, 8);
        } else {
            $normalErrors[] = $e;
        }
    }
}

// ── Data untuk form ─────────────────────────────────────────
// Ambil semua SDM aktif beserta status ketersediaan di tanggal acara ini
$semuaSDM = $pdo->prepare("
    SELECT
        u.id, u.nama, u.divisi, u.jabatan,
        (
            SELECT e2.judul FROM event_panitia ep2
            JOIN events e2 ON e2.id = ep2.event_id
            WHERE ep2.user_id = u.id
              AND e2.id != :eid
              AND e2.status NOT IN ('selesai','ditolak')
              AND e2.tanggal_mulai <= :tgl_sel
              AND e2.tanggal_selesai >= :tgl_mul
            LIMIT 1
        ) AS acara_bentrok,
        (
            SELECT 1 FROM event_panitia ep3
            WHERE ep3.user_id = u.id AND ep3.event_id = :eid2
            LIMIT 1
        ) AS sudah_di_acara_ini
    FROM users u
    WHERE u.status = 'aktif'
    ORDER BY u.divisi, u.nama
");
$semuaSDM->execute([
    ':eid'     => $eventId,
    ':tgl_sel' => $ev['tanggal_selesai'],
    ':tgl_mul' => $ev['tanggal_mulai'],
    ':eid2'    => $eventId,
]);
$daftarSDM = $semuaSDM->fetchAll();

// Panitia yang sudah terdaftar
$sudahDaftar = $pdo->prepare("
    SELECT ep.*, u.nama, u.divisi, u.jabatan
    FROM event_panitia ep
    JOIN users u ON u.id = ep.user_id
    WHERE ep.event_id = ?
    ORDER BY FIELD(ep.peran_acara,'pic','panitia_inti','panitia_support'), ep.bagian
");
$sudahDaftar->execute([$eventId]);
$panitia = $sudahDaftar->fetchAll();

$peranLabel = ['pic'=>'PIC','panitia_inti'=>'Panitia Inti','panitia_support'=>'Panitia Support'];
?>

<!-- Breadcrumb -->
<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $eventId ?>" class="text-muted text-decoration-none">
    <i class="bi bi-arrow-left"></i>
  </a>
  <div>
    <h5 class="mb-0 fw-700">Assign Panitia</h5>
    <div class="text-muted small"><?= htmlspecialchars($ev['judul']) ?> &bull;
      <?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?>
      <?= $ev['tanggal_selesai'] !== $ev['tanggal_mulai'] ? ' s/d ' . date('d M Y', strtotime($ev['tanggal_selesai'])) : '' ?>
    </div>
  </div>
</div>

<?php if (!empty($normalErrors)): ?>
<div class="alert alert-danger mb-3">
  <ul class="mb-0 ps-3">
    <?php foreach ($normalErrors as $e): ?>
    <li><?= htmlspecialchars($e) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<!-- Modal konfirmasi double job -->
<?php if (!empty($conflictError ?? '')): ?>
<div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h6 class="modal-title fw-700"><i class="bi bi-exclamation-triangle me-2"></i>Peringatan Bentrok!</h6>
      </div>
      <div class="modal-body">
        <p class="mb-1">SDM ini <strong>sudah ditugaskan</strong> di acara lain pada tanggal yang sama:</p>
        <div class="alert alert-warning py-2 small fw-600 mb-0"><?= htmlspecialchars($conflictError) ?></div>
        <p class="mt-3 mb-0 small text-muted">Apakah tetap ingin menugaskan sebagai <strong>Double Job</strong>?</p>
      </div>
      <div class="modal-footer gap-2">
        <a href="?" class="btn btn-secondary btn-sm">Batal</a>
        <form method="POST">
          <?php foreach ($_POST as $k => $v): ?>
          <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
          <?php endforeach; ?>
          <input type="hidden" name="force_double" value="1">
          <button type="submit" class="btn btn-warning btn-sm">
            <i class="bi bi-person-fill-exclamation me-1"></i> Tetap Tugaskan (Double Job)
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- Form Assign -->
  <div class="col-lg-5">
    <div class="form-section">
      <h6 class="fw-700 mb-3"><i class="bi bi-person-plus me-2 text-primary"></i>Tambah Panitia</h6>

      <form method="POST">
        <!-- Pilih SDM -->
        <div class="mb-3">
          <label class="form-label">Cari & Pilih SDM <span class="text-danger">*</span></label>
          <input type="text" id="sdmSearch" class="form-control form-control-sm mb-2"
                 placeholder="Ketik nama atau divisi...">
          <div style="max-height:240px;overflow-y:auto;border:1px solid #dee2e6;border-radius:8px;">
            <div id="sdmList">
              <?php foreach ($daftarSDM as $sdm): ?>
              <?php
                $disabled = $sdm['sudah_di_acara_ini'] ? true : false;
                $bentrok  = $sdm['acara_bentrok'] ?? null;
              ?>
              <label class="d-flex align-items-center gap-2 px-3 py-2 border-bottom sdm-item <?= $disabled ? 'opacity-50' : '' ?>"
                     style="cursor:<?= $disabled ? 'not-allowed' : 'pointer' ?>; background:#fff;"
                     data-nama="<?= strtolower($sdm['nama']) ?>"
                     data-divisi="<?= strtolower($sdm['divisi'] ?? '') ?>">
                <input type="radio" name="user_id" value="<?= $sdm['id'] ?>"
                       <?= $disabled ? 'disabled' : '' ?>
                       <?= isset($_POST['user_id']) && $_POST['user_id'] == $sdm['id'] ? 'checked' : '' ?>>
                <div class="flex-grow-1">
                  <div class="fw-600 small"><?= htmlspecialchars($sdm['nama']) ?></div>
                  <div class="text-muted" style="font-size:.72rem">
                    <?= htmlspecialchars($sdm['divisi'] ?? '') ?>
                    <?= $sdm['jabatan'] ? ' · ' . htmlspecialchars($sdm['jabatan']) : '' ?>
                  </div>
                </div>
                <?php if ($disabled): ?>
                  <span class="badge bg-secondary" style="font-size:.65rem">Sudah di acara ini</span>
                <?php elseif ($bentrok): ?>
                  <span class="conflict-badge" title="<?= htmlspecialchars($bentrok) ?>">Bentrok</span>
                <?php else: ?>
                  <span class="available-badge">Tersedia</span>
                <?php endif; ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Peran -->
        <div class="mb-3">
          <label class="form-label">Peran <span class="text-danger">*</span></label>
          <select name="peran_acara" class="form-select form-select-sm" required>
            <option value="">-- Pilih Peran --</option>
            <option value="panitia_inti"    <?= ($_POST['peran_acara'] ?? '') === 'panitia_inti'    ? 'selected' : '' ?>>Panitia Inti</option>
            <option value="panitia_support" <?= ($_POST['peran_acara'] ?? '') === 'panitia_support' ? 'selected' : '' ?>>Panitia Support</option>
          </select>
        </div>

        <!-- Bagian/Divisi -->
        <div class="mb-3">
          <label class="form-label">Bagian / Divisi Tugas</label>
          <input type="text" name="bagian" class="form-control form-control-sm"
                 placeholder="contoh: Logistik, Konsumsi, Dokumentasi"
                 value="<?= htmlspecialchars($_POST['bagian'] ?? '') ?>">
        </div>

        <!-- Catatan -->
        <div class="mb-4">
          <label class="form-label">Catatan (opsional)</label>
          <textarea name="catatan" class="form-control form-control-sm" rows="2"
                    placeholder="Instruksi khusus untuk panitia ini"><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn-primary-custom w-100">
          <i class="bi bi-person-check me-1"></i> Tambahkan ke Panitia
        </button>
      </form>
    </div>
  </div>

  <!-- Daftar Panitia Terdaftar -->
  <div class="col-lg-7">
    <div class="card-section">
      <div class="card-header-bar">
        <span><i class="bi bi-people me-2"></i>Susunan Panitia
          <span class="badge bg-secondary ms-1"><?= count($panitia) ?> orang</span>
        </span>
      </div>

      <?php if ($panitia): ?>

      <!-- Ringkasan konflik -->
      <?php $doublers = array_filter($panitia, fn($p) => $p['is_double_job']); ?>
      <?php if ($doublers): ?>
      <div class="alert alert-warning m-3 py-2 small mb-0">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong><?= count($doublers) ?> SDM</strong> berstatus double job di acara ini.
      </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Nama</th>
              <th>Peran & Bagian</th>
              <th>Status</th>
              <th>Admin</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($panitia as $p): ?>
            <tr>
              <td>
                <div class="fw-600 small">
                  <?= htmlspecialchars($p['nama']) ?>
                  <?php if ($p['is_double_job']): ?>
                  <span class="conflict-badge ms-1" title="Double Job">DJ</span>
                  <?php endif; ?>
                </div>
                <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($p['divisi'] ?? '') ?></div>
              </td>
              <td>
                <span class="badge bg-secondary" style="font-size:.68rem">
                  <?= $peranLabel[$p['peran_acara']] ?? $p['peran_acara'] ?>
                </span>
                <?php if ($p['bagian']): ?>
                <div class="text-muted small"><?= htmlspecialchars($p['bagian']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($p['status_konfirmasi']==='bersedia'): ?>
                  <span class="badge bg-success">Bersedia</span>
                <?php elseif ($p['status_konfirmasi']==='tidak_bisa'): ?>
                  <span class="badge bg-danger">Tidak Bisa</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($p['peran_acara'] !== 'pic'): ?>
                <a href="<?= BASE_URL ?>/modules/panitia/toggle_admin.php?id=<?= $p['id'] ?>&event_id=<?= $eventId ?>"
                   class="btn btn-xs <?= $p['is_event_admin'] ? 'btn-primary' : 'btn-outline-secondary' ?>"
                   style="font-size:.7rem;padding:2px 8px;"
                   title="<?= $p['is_event_admin'] ? 'Cabut Event Admin' : 'Jadikan Event Admin' ?>">
                  <i class="bi bi-<?= $p['is_event_admin'] ? 'shield-fill-check' : 'shield' ?>"></i>
                </a>
                <?php else: ?>
                <span class="text-muted small">PIC</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($p['peran_acara'] !== 'pic'): ?>
                <a href="<?= BASE_URL ?>/modules/panitia/remove.php?id=<?= $p['id'] ?>&event_id=<?= $eventId ?>"
                   class="btn btn-xs btn-outline-danger"
                   style="font-size:.7rem;padding:2px 8px;"
                   data-confirm="Hapus <?= htmlspecialchars($p['nama']) ?> dari panitia?">
                  <i class="bi bi-trash"></i>
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Legenda -->
      <div class="px-3 py-2 border-top d-flex gap-3" style="font-size:.72rem">
        <span><span class="conflict-badge">DJ</span> = Double Job</span>
        <span><i class="bi bi-shield-fill-check text-primary"></i> = Event Admin</span>
        <span class="text-muted">Event Admin bisa tambah/kelola panitia</span>
      </div>

      <?php else: ?>
      <div class="text-center text-muted py-5 small">
        <i class="bi bi-people d-block mb-2 fs-3"></i>Belum ada panitia. Tambahkan dari form di kiri.
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
document.getElementById('sdmSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.sdm-item').forEach(item => {
    const nama   = item.dataset.nama   || '';
    const divisi = item.dataset.divisi || '';
    item.style.display = (nama.includes(q) || divisi.includes(q)) ? '' : 'none';
  });
});
</script>
JS;
require_once __DIR__ . '/../../includes/layout/footer.php';
?>
PHP;

/* ============================================================
   modules/panitia/remove.php
   ============================================================ */
$files['modules/panitia/remove.php'] = <<<'PHP'
<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$id      = (int)($_GET['id']       ?? 0);
$eventId = (int)($_GET['event_id'] ?? 0);

if (!$id || !$eventId) {
    header('Location: ' . BASE_URL . '/modules/events/');
    exit;
}

// Validasi akses
if (!isSuperAdmin() && !isPIC($eventId, $pdo) && !isEventAdmin($eventId, $pdo)) {
    setFlash('Akses ditolak.', 'danger');
    header('Location: ' . BASE_URL . '/modules/events/detail.php?id=' . $eventId);
    exit;
}

// Tidak boleh hapus PIC
$cek = $pdo->prepare("SELECT peran_acara FROM event_panitia WHERE id=? AND event_id=?");
$cek->execute([$id, $eventId]);
$row = $cek->fetch();

if (!$row || $row['peran_acara'] === 'pic') {
    setFlash('PIC tidak bisa dihapus dari acara.', 'danger');
    header('Location: ' . BASE_URL . '/modules/panitia/assign.php?event_id=' . $eventId);
    exit;
}

$pdo->prepare("DELETE FROM event_panitia WHERE id=? AND event_id=?")->execute([$id, $eventId]);
setFlash('Panitia berhasil dihapus dari acara.', 'success');
header('Location: ' . BASE_URL . '/modules/panitia/assign.php?event_id=' . $eventId);
exit;
PHP;

/* ============================================================
   modules/panitia/toggle_admin.php
   ============================================================ */
$files['modules/panitia/toggle_admin.php'] = <<<'PHP'
<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$id      = (int)($_GET['id']       ?? 0);
$eventId = (int)($_GET['event_id'] ?? 0);

if (!$id || !$eventId) {
    header('Location: ' . BASE_URL . '/modules/events/');
    exit;
}

// Hanya PIC atau Super Admin yang bisa upgrade
if (!isSuperAdmin() && !isPIC($eventId, $pdo)) {
    setFlash('Hanya PIC yang bisa mengubah Event Admin.', 'danger');
    header('Location: ' . BASE_URL . '/modules/panitia/assign.php?event_id=' . $eventId);
    exit;
}

// Toggle
$cek = $pdo->prepare("SELECT is_event_admin, peran_acara FROM event_panitia WHERE id=? AND event_id=?");
$cek->execute([$id, $eventId]);
$row = $cek->fetch();

if (!$row || $row['peran_acara'] === 'pic') {
    setFlash('Aksi tidak valid.', 'danger');
    header('Location: ' . BASE_URL . '/modules/panitia/assign.php?event_id=' . $eventId);
    exit;
}

$newVal = $row['is_event_admin'] ? 0 : 1;
$pdo->prepare("UPDATE event_panitia SET is_event_admin=? WHERE id=?")->execute([$newVal, $id]);

$msg = $newVal ? 'Panitia dijadikan Event Admin.' : 'Status Event Admin dicabut.';
setFlash($msg, 'success');
header('Location: ' . BASE_URL . '/modules/panitia/assign.php?event_id=' . $eventId);
exit;
PHP;

/* ============================================================
   Tulis semua file
   ============================================================ */
$ok = 0; $fail = 0;
foreach ($files as $path => $content) {
    $full = ROOT . '/' . $path;
    $dir  = dirname($full);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (file_put_contents($full, $content) !== false) {
        echo "✓ $path\n"; $ok++;
    } else {
        echo "✗ GAGAL: $path\n"; $fail++;
    }
}
echo "\n============================\n";
echo "Selesai: $ok file" . ($fail ? ", $fail GAGAL" : '') . "\n";
echo "============================\n";
