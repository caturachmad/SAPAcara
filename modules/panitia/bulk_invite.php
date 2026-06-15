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

// SDM yang sudah ada di panitia
$s2 = $pdo->prepare("SELECT user_id FROM event_panitia WHERE event_id=?");
$s2->execute([$eventId]);
$sudahIds = array_column($s2->fetchAll(), 'user_id');

// Semua SDM aktif yang belum terdaftar
$sdmQ = $pdo->prepare("SELECT * FROM users WHERE status='aktif' AND id NOT IN (SELECT user_id FROM event_panitia WHERE event_id=?) ORDER BY divisi,nama");
$sdmQ->execute([$eventId]);
$sdmList = $sdmQ->fetchAll();

// Cek konflik jadwal per SDM
$konflikMap = [];
foreach ($sdmList as $sdm) {
    $cek = $pdo->prepare("SELECT e.judul FROM event_panitia ep JOIN events e ON e.id=ep.event_id WHERE ep.user_id=? AND e.id!=? AND e.status NOT IN ('selesai','ditolak','draft') AND NOT (e.tanggal_selesai < ? OR e.tanggal_mulai > ?)");
    $cek->execute([$sdm['id'], $eventId, $ev['tanggal_mulai'], $ev['tanggal_selesai']]);
    $b = $cek->fetchAll();
    if ($b) $konflikMap[$sdm['id']] = $b[0]['judul'];
}

$errors = [];

// ── POST: Proses undangan ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undang'])) {
    $userIds  = $_POST['user_ids'] ?? [];
    $peran    = 'panitia_support';
    // bagian is sent as bagian[user_id] => value
    $bagianPerUser = (is_array($_POST['bagian'] ?? null)) ? $_POST['bagian'] : [];

    if (empty($userIds)) {
        $errors[] = 'Pilih minimal 1 SDM.';
    }

    if (empty($errors)) {
        $ok = 0;
        $failedEmails  = [];
        $smtpLastError = '';

        foreach ($userIds as $uid2) {
            $uid2   = (int)$uid2;
            $bagian = trim($bagianPerUser[$uid2] ?? '');
            $token  = bin2hex(random_bytes(32));
            $exp    = date('Y-m-d H:i:s', strtotime('+14 days'));

            try {
                $pdo->prepare("INSERT INTO event_panitia (event_id,user_id,peran_acara,bagian,is_event_admin,token_konfirmasi,token_expires_at) VALUES (?,?,?,?,0,?,?) ON DUPLICATE KEY UPDATE peran_acara=VALUES(peran_acara),bagian=VALUES(bagian),token_konfirmasi=VALUES(token_konfirmasi),token_expires_at=VALUES(token_expires_at)")
                    ->execute([$eventId, $uid2, $peran, $bagian, $token, $exp]);

                $sdmData = $pdo->prepare("SELECT * FROM users WHERE id=?");
                $sdmData->execute([$uid2]);
                $sdmUser = $sdmData->fetch();

                if ($sdmUser) {
                    $roleName = $bagian ?: 'Panitia';
                    $html = mailTemplatePanitia($sdmUser, $ev, $roleName, $token);
                    $sent = sendMail($sdmUser['email'], $sdmUser['nama'], 'Undangan Panitia: ' . $ev['judul'], $html);
                    // Notif in-app selalu dikirim, terlepas dari status email
                    addNotif($pdo, $uid2, 'Undangan Panitia',
                        "Kamu diundang menjadi panitia acara {$ev['judul']}",
                        BASE_URL . '/modules/events/workspace.php?id=' . $eventId, 'info');
                    if (!$sent) {
                        $failedEmails[] = $sdmUser['email'];
                        if (!$smtpLastError && !empty($GLOBALS['last_mail_error'])) {
                            $smtpLastError = $GLOBALS['last_mail_error'];
                        }
                    }
                }
                $ok++;
            } catch (\Exception $e) {
                error_log('[SAPAcara Bulk Invite Error] ' . $e->getMessage());
            }
        }

        if (!empty($failedEmails)) {
            $failList = implode(', ', array_unique($failedEmails));
            $detail   = $smtpLastError ? " — Error SMTP: " . htmlspecialchars($smtpLastError) : '';
            setFlash("$ok SDM berhasil diundang. Email gagal terkirim ke: $failList.$detail", 'warning');
        } else {
            setFlash("$ok SDM berhasil diundang! Email notifikasi sudah terkirim.", 'success');
        }
        header('Location: ' . BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '#tim');
        exit;
    }
}

$bagianOptions = ['Bendahara','Sekretaris','Logistik','Dokumentasi','Konsumsi','Tim Medis',
                  'Tim Acara','Korlap','SC Kegiatan','Tilawah','MC','Operator','Dekor',
                  'Kehumasan','Perlengkapan','Keamanan','Registrasi','Transportasi'];

$divisiGroup = [];
foreach ($sdmList as $s) {
    $divisiGroup[$s['divisi'] ?: 'Lainnya'][] = $s;
}
?>

<style>
.sdm-row {
  display: flex;
  align-items: center;
  padding: 10px 16px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  transition: background .12s;
  user-select: none;
  gap: 12px;
}
.sdm-row:hover { background: rgba(var(--primary-rgb,26,58,92),.05); }
.sdm-row.selected {
  background: rgba(var(--primary-rgb,26,58,92),.09);
  border-left: 3px solid var(--primary,#1a3a5c);
}
.sdm-row.selected .sdm-check-icon { opacity: 1; }
.sdm-check-icon {
  width: 20px; height: 20px;
  border: 2px solid #cbd5e1;
  border-radius: 4px;
  flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: #fff;
  transition: all .12s;
}
.sdm-row.selected .sdm-check-icon {
  background: var(--primary,#1a3a5c);
  border-color: var(--primary,#1a3a5c);
  color: #fff;
}
.sdm-bagian-badge {
  font-size: .72rem;
  padding: 2px 8px;
  border-radius: 20px;
  background: var(--primary,#1a3a5c);
  color: #fff;
  white-space: nowrap;
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.divisi-header {
  background: var(--sidebar-bg,#f1f5f9);
  padding: 6px 16px;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--text-muted,#64748b);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 148px;
  z-index: 10;
}
.action-bar {
  position: sticky;
  top: 60px;
  z-index: 40;
  background: var(--card-bg,#fff);
  border-bottom: 1px solid var(--border);
  padding: 12px 0 10px;
  margin-bottom: 0;
}
.avatar-sm {
  width:36px;height:36px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:.8rem;color:#fff;flex-shrink:0;
}
.conflict-label { font-size:.72rem; color:#dc2626; margin-top:2px; }
</style>

<!-- Page header -->
<div class="page-header">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eventId ?>#tim" class="back-btn">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <h5>Undang Panitia</h5>
      <div class="sub"><?= htmlspecialchars($ev['judul']) ?></div>
    </div>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <span class="badge bg-success"><i class="bi bi-person-check me-1"></i><?= count($sdmList) - count($konflikMap) ?> Senggang</span>
    <?php if ($konflikMap): ?>
      <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i><?= count($konflikMap) ?> Bentrok</span>
    <?php endif; ?>
  </div>
</div>

<?php if (!$ev['wa_group_link']): ?>
<div class="alert alert-warning mb-3 py-2">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <strong>Grup WA belum diatur.</strong> Sebaiknya siapkan grup WA sebelum mengundang.
  <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eventId ?>" class="alert-link ms-2">Atur sekarang</a>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger mb-3">
    <?php foreach ($errors as $e) echo "<div><i class='bi bi-x-circle me-1'></i>" . htmlspecialchars($e) . "</div>"; ?>
  </div>
<?php endif; ?>

<form method="POST" id="bulkForm">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <input type="hidden" name="event_id" value="<?= $eventId ?>">

  <!-- ── Sticky action bar ── -->
  <div class="action-bar">
    <div class="d-flex flex-wrap gap-2 align-items-center">

      <!-- Search -->
      <div class="flex-grow-1" style="min-width:180px;max-width:280px">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="filterSDM" class="form-control" placeholder="Cari nama atau divisi…">
        </div>
      </div>

      <!-- Bagian global -->
      <div style="min-width:160px;max-width:200px">
        <select id="globalBagianSelect" class="form-select form-select-sm">
          <option value="">— Bagian (opsional) —</option>
          <?php foreach ($bagianOptions as $b): ?>
            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
          <?php endforeach; ?>
          <option value="__custom__">Bagian lain…</option>
        </select>
      </div>
      <div id="customBagianWrap" class="d-none" style="min-width:140px;max-width:180px">
        <input type="text" id="globalBagianCustom" class="form-control form-control-sm"
               placeholder="Ketik bagian…" maxlength="60">
      </div>

      <!-- Terapkan -->
      <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyBagianToSelected()" title="Terapkan bagian ke semua yang sudah dicentang">
        <i class="bi bi-tag me-1"></i>Terapkan ke dipilih
      </button>

      <!-- Separator -->
      <div class="d-none d-md-block border-start" style="height:28px;margin:0 4px"></div>

      <!-- Pilih semua / batal -->
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAll()">
        <i class="bi bi-check-all me-1"></i>Pilih Semua
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAll()">
        <i class="bi bi-x me-1"></i>Batal Semua
      </button>

      <!-- Count + Submit -->
      <div class="ms-auto d-flex align-items-center gap-2">
        <span id="selectedCount"
              class="badge rounded-pill px-3 py-2 fs-13 fw-700"
              style="background:var(--primary,#1a3a5c);color:#fff">0 dipilih</span>
        <button type="submit" name="undang" id="btnSubmit"
                class="btn btn-success fw-700" disabled>
          <span class="spinner-border spinner-border-sm d-none me-1" id="submitSpinner"></span>
          <i class="bi bi-envelope-fill me-1" id="submitIcon"></i>
          <span id="submitLabel">Kirim Undangan</span>
        </button>
      </div>

    </div>
  </div><!-- end action bar -->

  <!-- ── Daftar SDM ── -->
  <div class="card mt-3" style="overflow:hidden">

    <?php if (empty($sdmList)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-people fs-1 d-block mb-2"></i>
        Semua SDM aktif sudah terdaftar sebagai panitia acara ini.
      </div>
    <?php else:
      foreach ($divisiGroup as $divisi => $members):
    ?>
      <div class="divisi-header"><?= htmlspecialchars($divisi) ?></div>

      <?php foreach ($members as $s):
        $konflik = $konflikMap[$s['id']] ?? null;
        $initials = strtoupper(mb_substr($s['nama'], 0, 2));
        $avatarBg = $konflik ? '#ef4444' : '#1a3a5c';
      ?>
      <div class="sdm-row <?= $konflik ? 'conflict' : 'available' ?>"
           data-id="<?= $s['id'] ?>"
           data-nama="<?= strtolower(htmlspecialchars($s['nama'])) ?>"
           data-divisi="<?= strtolower(htmlspecialchars($divisi)) ?>"
           onclick="toggleRow(this, event)">

        <!-- Checkbox visual -->
        <div class="sdm-check-icon">
          <i class="bi bi-check2" style="font-size:.85rem"></i>
        </div>

        <!-- Hidden real checkbox (tidak di-render, kita pakai data-id) -->
        <!-- Avatar -->
        <div class="avatar-sm flex-shrink-0" style="background:<?= $avatarBg ?>">
          <?= $initials ?>
        </div>

        <!-- Info -->
        <div class="flex-grow-1">
          <div class="fw-600 fs-13"><?= htmlspecialchars($s['nama']) ?></div>
          <div class="fs-12 text-muted">
            <?= htmlspecialchars($s['jabatan'] ?: $divisi) ?>
          </div>
          <?php if ($konflik): ?>
            <div class="conflict-label">
              <i class="bi bi-exclamation-triangle-fill me-1"></i>Bentrok dengan: <?= htmlspecialchars($konflik) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Bagian badge (muncul saat dipilih) -->
        <span class="sdm-bagian-badge d-none" title="Bagian yang akan ditetapkan"></span>

        <!-- Status -->
        <?php if ($konflik): ?>
          <span class="badge bg-warning text-dark flex-shrink-0">Bentrok</span>
        <?php else: ?>
          <span class="badge bg-success flex-shrink-0">Senggang</span>
        <?php endif; ?>

      </div>
      <?php endforeach; // members ?>
    <?php endforeach; // divisi ?>
    <?php endif; // empty sdmList ?>

  </div><!-- end card -->

  <!-- Hidden inputs untuk user_ids dan bagian (diisi via JS) -->
  <!-- 'undang' dikirim via hidden input agar tidak hilang saat button di-disable oleh footer.php -->
  <input type="hidden" name="undang" value="1">
  <div id="hiddenInputs"></div>

</form>

<script>
(function () {
  // ── State ────────────────────────────────────────────────────────────────
  const selected = new Map(); // id → bagian string

  // ── Helpers ─────────────────────────────────────────────────────────────
  function getGlobalBagian() {
    const sel = document.getElementById('globalBagianSelect');
    const cust = document.getElementById('globalBagianCustom');
    if (!sel) return '';
    if (sel.value === '__custom__') return cust.value.trim();
    return sel.value;
  }

  function updateRowUI(row, isSelected, bagian) {
    if (isSelected) {
      row.classList.add('selected');
      const badge = row.querySelector('.sdm-bagian-badge');
      if (badge) {
        if (bagian) {
          badge.textContent = bagian;
          badge.classList.remove('d-none');
        } else {
          badge.classList.add('d-none');
        }
      }
    } else {
      row.classList.remove('selected');
      const badge = row.querySelector('.sdm-bagian-badge');
      if (badge) badge.classList.add('d-none');
    }
  }

  function updateCount() {
    const n = selected.size;
    document.getElementById('selectedCount').textContent = n + ' dipilih';
    const btn = document.getElementById('btnSubmit');
    btn.disabled = n === 0;
    const label = document.getElementById('submitLabel');
    label.textContent = n > 0 ? 'Kirim Undangan (' + n + ')' : 'Kirim Undangan';
    rebuildHiddenInputs();
  }

  function rebuildHiddenInputs() {
    const container = document.getElementById('hiddenInputs');
    container.innerHTML = '';
    selected.forEach((bagian, id) => {
      const uid = document.createElement('input');
      uid.type = 'hidden';
      uid.name = 'user_ids[]';
      uid.value = id;
      container.appendChild(uid);

      const bag = document.createElement('input');
      bag.type = 'hidden';
      bag.name = 'bagian[' + id + ']';
      bag.value = bagian;
      container.appendChild(bag);
    });
  }

  // ── Row toggle ───────────────────────────────────────────────────────────
  window.toggleRow = function (row, e) {
    // Jangan intercept jika klik pada badge atau elemen interaktif lain
    if (e && e.target.closest('a,button,input,select,textarea')) return;

    const id = row.dataset.id;
    if (selected.has(id)) {
      selected.delete(id);
      updateRowUI(row, false, '');
    } else {
      const bagian = getGlobalBagian();
      selected.set(id, bagian);
      updateRowUI(row, true, bagian);
    }
    updateCount();
  };

  // ── Apply bagian to already-selected ────────────────────────────────────
  window.applyBagianToSelected = function () {
    if (selected.size === 0) {
      alert('Pilih minimal 1 SDM terlebih dahulu, lalu klik Terapkan.');
      return;
    }
    const bagian = getGlobalBagian();
    selected.forEach((_, id) => selected.set(id, bagian));
    document.querySelectorAll('.sdm-row.selected').forEach(row => {
      updateRowUI(row, true, bagian);
    });
    rebuildHiddenInputs();
  };

  // ── Select/clear all ─────────────────────────────────────────────────────
  window.selectAll = function () {
    const bagian = getGlobalBagian();
    document.querySelectorAll('.sdm-row:not([style*="display:none"])').forEach(row => {
      const id = row.dataset.id;
      if (!selected.has(id)) {
        selected.set(id, bagian);
        updateRowUI(row, true, bagian);
      }
    });
    updateCount();
  };

  window.clearAll = function () {
    selected.clear();
    document.querySelectorAll('.sdm-row').forEach(row => updateRowUI(row, false, ''));
    updateCount();
  };

  // ── Filter ───────────────────────────────────────────────────────────────
  document.getElementById('filterSDM').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.sdm-row').forEach(row => {
      const match = !q || row.dataset.nama.includes(q) || row.dataset.divisi.includes(q);
      row.style.display = match ? '' : 'none';
    });
    // Hide divisi headers if all their rows are hidden
    document.querySelectorAll('.divisi-header').forEach(header => {
      let next = header.nextElementSibling;
      let anyVisible = false;
      while (next && !next.classList.contains('divisi-header')) {
        if (next.style.display !== 'none') anyVisible = true;
        next = next.nextElementSibling;
      }
      header.style.display = anyVisible ? '' : 'none';
    });
  });

  // ── Bagian select custom toggle ──────────────────────────────────────────
  document.getElementById('globalBagianSelect').addEventListener('change', function () {
    const wrap = document.getElementById('customBagianWrap');
    const cust = document.getElementById('globalBagianCustom');
    if (this.value === '__custom__') {
      wrap.classList.remove('d-none');
      cust.focus();
    } else {
      wrap.classList.add('d-none');
      cust.value = '';
    }
    // Auto-apply to selected rows if any
    if (selected.size > 0) applyBagianToSelected();
  });

  document.getElementById('globalBagianCustom')?.addEventListener('input', function () {
    if (selected.size > 0) {
      const bagian = this.value.trim();
      selected.forEach((_, id) => selected.set(id, bagian));
      document.querySelectorAll('.sdm-row.selected').forEach(row => updateRowUI(row, true, bagian));
      rebuildHiddenInputs();
    }
  });

  // ── Submit loading state ─────────────────────────────────────────────────
  document.getElementById('bulkForm').addEventListener('submit', function () {
    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    document.getElementById('submitSpinner').classList.remove('d-none');
    document.getElementById('submitIcon').classList.add('d-none');
    document.getElementById('submitLabel').textContent = 'Memproses…';
  });

})();
</script>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
