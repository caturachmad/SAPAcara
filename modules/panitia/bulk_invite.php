<?php
$pageTitle = 'Undang Panitia';
require_once __DIR__ . '/../../includes/layout/header.php';
require_once __DIR__ . '/../../config/mail.php';

$eventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
if (!$eventId) {
    header('Location: ' . BASE_URL . '/modules/dashboard/select.php');
    exit;
}

$ev = $pdo->prepare("SELECT * FROM events WHERE id=?");
$ev->execute([$eventId]);
$ev = $ev->fetch();

if (!$ev || !(isPIC($eventId, $pdo) || isEventAdmin($eventId, $pdo) || isSuperAdmin())) {
    header('Location: ' . BASE_URL . '/modules/events/workspace.php?id=' . $eventId);
    exit;
}

// SDM yang sudah ada
$sudahIds = [];
$s2 = $pdo->prepare("SELECT user_id FROM event_panitia WHERE event_id=?");
$s2->execute([$eventId]);
$sudahIds = array_column($s2->fetchAll(), 'user_id');

// Semua SDM kecuali yang sudah ada
$sdmQ = $pdo->prepare("SELECT * FROM users WHERE status='aktif' AND id NOT IN (SELECT user_id FROM event_panitia WHERE event_id=?) ORDER BY divisi,nama");
$sdmQ->execute([$eventId]);
$sdmList = $sdmQ->fetchAll();

// Cek konflik per SDM
$konflikMap = [];
foreach ($sdmList as $sdm) {
    $cek = $pdo->prepare("SELECT e.judul FROM event_panitia ep JOIN events e ON e.id=ep.event_id WHERE ep.user_id=? AND e.id!=? AND e.status NOT IN ('selesai','ditolak','draft') AND NOT (e.tanggal_selesai < ? OR e.tanggal_mulai > ?)");
    $cek->execute([$sdm['id'], $eventId, $ev['tanggal_mulai'], $ev['tanggal_selesai']]);
    $b = $cek->fetchAll();
    if ($b) {
        $konflikMap[$sdm['id']] = $b[0]['judul'];
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undang'])) {
    $userIds = $_POST['user_ids'] ?? [];
    $peran   = $_POST['peran_acara'] ?? 'panitia_support';
    $bagianSelect = trim($_POST['bagian_select'] ?? '');
    $bagianCustom = trim($_POST['bagian'] ?? '');
    $bagianValue = $bagianCustom !== '' ? $bagianCustom : $bagianSelect;

    if ($bagianValue === '__custom__') {
        $bagianValue = '';
    }

    $bagianMap = [];
    if ($bagianValue !== '') {
        foreach ($userIds as $uid2) {
            $bagianMap[(int)$uid2] = $bagianValue;
        }
    }

    if (empty($userIds)) {
        $errors[] = 'Pilih minimal 1 SDM.';
    }

    if (empty($errors)) {
        $ok = 0;
        $failedEmails = [];
        foreach ($userIds as $uid2) {
            $uid2 = (int)$uid2;
            $bagian = trim($bagianMap[$uid2] ?? '');
            $token = bin2hex(random_bytes(32));
            $exp = date('Y-m-d H:i:s', strtotime('+14 days'));

            try {
                $isAdminFlag = ($peran === 'pic') ? 1 : 0;
                $pdo->prepare("INSERT INTO event_panitia (event_id,user_id,peran_acara,bagian,is_event_admin,token_konfirmasi,token_expires_at) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE peran_acara=VALUES(peran_acara),bagian=VALUES(bagian),token_konfirmasi=VALUES(token_konfirmasi),token_expires_at=VALUES(token_expires_at), is_event_admin = IF(VALUES(peran_acara)='pic',1,is_event_admin)")
                    ->execute([$eventId, $uid2, $peran, $bagian, $isAdminFlag, $token, $exp]);

                // Kirim email
                $sdmData = $pdo->prepare("SELECT * FROM users WHERE id=?");
                $sdmData->execute([$uid2]);
                $sdmUser = $sdmData->fetch();
                if ($sdmUser) {
                    $roleName = $bagian ?: 'Panitia Biasa';
                    $html = mailTemplatePanitia($sdmUser, $ev, $roleName, $token);
                    $sent = sendMail($sdmUser['email'], $sdmUser['nama'], 'Undangan Panitia: ' . $ev['judul'], $html);
                    if ($sent) {
                        addNotif($pdo, $uid2, 'Undangan Panitia', "Kamu diundang menjadi panitia acara {$ev['judul']}", BASE_URL . '/modules/events/workspace.php?id=' . $eventId, 'info');
                    } else {
                        $failedEmails[] = $sdmUser['email'];
                    }
                }
                $ok++;
            } catch (\Exception $e) {
                error_log('[SAPAcara Bulk Invite Error] ' . $e->getMessage());
            }
        }

        if (!empty($failedEmails)) {
            $failList = implode(', ', array_unique($failedEmails));
            setFlash("$ok SDM berhasil diundang, tetapi email gagal dikirim ke: $failList", 'warning');
        } else {
            setFlash("$ok SDM berhasil diundang! Email notifikasi terkirim.", 'success');
        }
        header('Location: ' . BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '#tim');
        exit;
    }
}

$bagianOptions = ['Bendahara','Sekretaris','Logistik','Dokumentasi','Konsumsi','Tim Medis','Tim Acara','Korlap','SC Kegiatan','Tilawah','MC','Operator','Dekor','Kehumasan','Perlengkapan','Keamanan','Registrasi','Transportasi'];
$divisiGroup = [];
foreach ($sdmList as $s) {
    $divisiGroup[$s['divisi']][] = $s;
}
?>

<div class="page-header">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eventId ?>#tim" class="back-btn"><i class="bi bi-arrow-left"></i></a>
    <div>
      <h5>Undang Panitia</h5>
      <div class="sub"><?= htmlspecialchars($ev['judul']) ?></div>
    </div>
  </div>
</div>

<?php if ($ev['wa_group_link']): ?>
<div class="alert alert-success mb-4">
  <i class="bi bi-whatsapp me-2"></i>
  Grup WA sudah tersedia. Panitia yang menerima undangan akan mendapat link grup.
  <a href="<?= htmlspecialchars($ev['wa_group_link']) ?>" target="_blank" class="alert-link ms-2">Buka Grup</a>
</div>
<?php else: ?>
<div class="alert alert-warning mb-4">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <strong>Grup WA belum diatur!</strong> Sebaiknya siapkan grup WA panitia terlebih dahulu sebelum mengundang.
  <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eventId ?>" class="alert-link ms-2">Atur sekarang</a>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger mb-3"><?php foreach ($errors as $e) echo "<div><i class='bi bi-x-circle me-1'></i>$e</div>"; ?></div>
<?php endif; ?>

<form method="POST" id="bulkForm">
          <?php if(function_exists('csrfToken')): ?><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><?php endif; ?>
<div class="row g-3">

  <!-- Panel Pengaturan -->
  <div class="col-md-4">
    <div class="card mb-3" style="position:sticky;top:80px">
      <div class="card-header"><i class="bi bi-sliders"></i> Pengaturan Undangan</div>
      <div class="card-body">
        <input type="hidden" name="peran_acara" value="panitia_support">
        <div class="mb-3">
          <label class="form-label">Bagian / Divisi Panitia</label>
          <p class="form-text text-muted mb-2">Pilih atau ketik nama bagian yang sesuai untuk panitia biasa: Bendahara, Sekretaris, Logistik, Dokumentasi, Konsumsi, Tim Medis, Tim Acara, Korlap, SC Kegiatan, Tilawah, MC, Operator, Dekor, dll.</p>
          <div class="form-text">Panitia inti bisa dipilih saat buat acara atau dinaikkan jabatan oleh PIC. Undangan ini default untuk panitia biasa.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Filter SDM</label>
          <input type="text" id="filterSDM" class="form-control form-control-sm" placeholder="Cari nama atau divisi...">
        </div>

        <div class="mb-3">
          <label class="form-label">Bagian / Divisi Panitia</label>
          <select id="massBagianSelect" class="form-select form-select-sm">
            <option value="">-- Pilih bagian --</option>
            <?php foreach ($bagianOptions as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
            <option value="__custom__">Bagian lain...</option>
          </select>
          <input type="text" id="massBagianCustom" class="form-control form-control-sm mt-2 d-none" placeholder="Ketik bagian lain jika tidak ada dalam daftar">
          <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="applyBagianToSelected()">Terapkan ke yang dipilih</button>
          <div class="form-text">Pilih bagian terlebih dahulu, lalu centang beberapa SDM yang akan diundang.</div>
        </div>

        <div class="d-flex gap-2 mb-3">
          <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" onclick="selectAll()"><i class="bi bi-check-all me-1"></i>Pilih Semua</button>
          <button type="button" class="btn btn-outline-secondary btn-sm flex-grow-1" onclick="clearAll()"><i class="bi bi-x me-1"></i>Batal Semua</button>
        </div>

        <div id="selectedCount" class="alert alert-primary py-2 text-center fw-700 mb-3">0 SDM dipilih</div>

        <button type="submit" name="undang" class="btn btn-primary w-100" id="btnUndang" disabled>
          <i class="bi bi-envelope-fill me-1"></i> Kirim Undangan & Email
        </button>
        <div class="form-text text-center mt-1">Email notifikasi dikirim otomatis ke setiap SDM yang dipilih</div>
      </div>
    </div>
  </div>

  <!-- Daftar SDM -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people"></i> Daftar SDM Tersedia (<?= count($sdmList) ?>)</span>
        <div>
          <span class="badge bg-success me-1">✅ <?= count($sdmList) - count($konflikMap) ?> Senggang</span>
          <span class="badge bg-warning text-dark">⚠️ <?= count($konflikMap) ?> Bentrok</span>
        </div>
      </div>
      <div class="card-body p-0" style="max-height:600px;overflow-y:auto">
        <?php foreach ($divisiGroup as $divisi => $members): ?>
        <div class="px-3 pt-2 pb-1 bg-light border-bottom">
          <small class="fw-700 text-muted text-uppercase" style="font-size:.68rem;letter-spacing:.08em"><?= htmlspecialchars($divisi) ?></small>
        </div>
        <?php foreach ($members as $s):
          $konflik = $konflikMap[$s['id']] ?? null;
        ?>
        <div class="sdm-row d-flex align-items-center px-3 py-2 border-bottom <?= $konflik ? 'conflict' : 'available' ?>"
             data-nama="<?= strtolower($s['nama']) ?>" data-divisi="<?= strtolower($divisi) ?>">
          <div class="me-3">
            <input type="checkbox" name="user_ids[]" value="<?= $s['id'] ?>" id="sdm<?= $s['id'] ?>"
                   class="form-check-input sdm-check" onchange="updateCount()" style="width:18px;height:18px">
          </div>
          <div class="avatar avatar-sm me-2 flex-shrink-0" style="background:<?= $konflik ? '#ef4444' : '#1a3a5c' ?>">
            <?= strtoupper(substr($s['nama'],0,2)) ?>
          </div>
          <div class="flex-grow-1">
            <div class="fw-600 fs-13"><?= htmlspecialchars($s['nama']) ?></div>
            <div class="fs-12 text-muted"><?= htmlspecialchars($s['jabatan'] ?? $divisi) ?></div>
            <?php if ($konflik): ?>
              <div class="fs-12 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Bentrok: <?= htmlspecialchars($konflik) ?></div>
            <?php endif; ?>
          </div>
          <input type="hidden" name="bagian[<?= $s['id'] ?>]" class="bagian-hidden" value="">
          <div class="ms-2 text-end" style="width:140px">
            <span class="badge bg-light text-dark bagian-label">—</span>
          </div>
          <?php if (!$konflik): ?>
            <span class="badge bg-success ms-2">Senggang</span>
          <?php else: ?>
            <span class="badge bg-warning text-dark ms-2">Bentrok</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
        <datalist id="bagianOptions">
          <?php foreach ($bagianOptions as $b): ?>
            <option value="<?= htmlspecialchars($b) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <?php if (empty($sdmList)): ?>
          <div class="empty-state py-4"><i class="bi bi-people"></i><p>Semua SDM sudah ada dalam panitia acara ini</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
</form>

<script>
function updateCount() {
  const checked = document.querySelectorAll('.sdm-check:checked').length;
  document.getElementById('selectedCount').textContent = checked + ' SDM dipilih';
  document.getElementById('btnUndang').disabled = checked === 0;
}
function selectAll() {
  document.querySelectorAll('.sdm-row:not([style*="display:none"]) .sdm-check').forEach(c => c.checked = true);
  updateCount();
}
function clearAll() {
  document.querySelectorAll('.sdm-check').forEach(c => c.checked = false);
  updateCount();
}
document.getElementById('filterSDM').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.sdm-row').forEach(row => {
    const nama = row.dataset.nama || '';
    const div  = row.dataset.divisi || '';
    row.style.display = (!q || nama.includes(q) || div.includes(q)) ? '' : 'none';
  });
});

function applyBagianToSelected() {
  const select = document.getElementById('massBagianSelect');
  const custom = document.getElementById('massBagianCustom');
  let val = select.value;
  if (!val) return alert('Pilih bagian terlebih dahulu.');
  if (val === '__custom__') {
    val = custom.value.trim();
    if (!val) return alert('Masukkan bagian custom terlebih dahulu.');
  }
  const checks = document.querySelectorAll('.sdm-check:checked');
  if (checks.length === 0) return alert('Pilih minimal satu SDM terlebih dahulu.');
  checks.forEach(ch => {
    const row = ch.closest('.sdm-row');
    if (!row) return;
    const hidden = row.querySelector('.bagian-hidden');
    const label = row.querySelector('.bagian-label');
    if (hidden) hidden.value = val;
    if (label) label.textContent = val;
  });
}

const bagianSelect = document.getElementById('massBagianSelect');
const bagianCustom = document.getElementById('massBagianCustom');
if (bagianSelect) {
  bagianSelect.addEventListener('change', function() {
    if (this.value === '__custom__') {
      bagianCustom.classList.remove('d-none');
      bagianCustom.focus();
    } else {
      bagianCustom.classList.add('d-none');
      bagianCustom.value = '';
    }
  });
}
</script>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
