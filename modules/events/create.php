<?php
$pageTitle = 'Buat Acara Baru';
require_once __DIR__ . '/../../includes/layout/header.php';

// Guard: hanya user dengan permission 'buat_acara' yang boleh akses
if (!hasPermission('buat_acara')) {
    setFlash('Anda tidak memiliki izin untuk membuat acara.', 'danger');
    header('Location: ' . BASE_URL . '/modules/dashboard/select.php');
    exit;
}

// Ambil daftar SDM aktif untuk dropdown kepanitiaan inti
$semuaSDM = $pdo->query("SELECT id, nama, divisi, jabatan, jabatan_sistem FROM users WHERE status='aktif' ORDER BY nama")->fetchAll();

// Ambil daftar template (hanya yang selesai dan ditandai is_template=1)
$templates = $pdo->query(
    "SELECT e.id, e.judul, e.level, e.tanggal_mulai, e.template_notes,
            (SELECT COUNT(*) FROM event_swot sw WHERE sw.event_id=e.id) AS jml_swot,
            (SELECT COUNT(*) FROM event_panitia ep WHERE ep.event_id=e.id) AS jml_panitia
     FROM events e
     WHERE e.status='selesai' AND e.is_template=1
     ORDER BY e.tanggal_mulai DESC LIMIT 50"
)->fetchAll();

// PR-05: Set session access if template_id was chosen (from AJAX check)
if (!empty($_SESSION['template_access'])) {
    // already set via ajax_check_template.php
}

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

    // PR-05: Grant session access ke archive.php untuk template yang dipilih
    if ($templateId) {
        if (!isset($_SESSION['template_access'])) $_SESSION['template_access'] = [];
        $_SESSION['template_access'][(int)$templateId] = true;
    }

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
                INSERT INTO events (judul, level, tanggal_mulai, tanggal_selesai, lokasi, deskripsi, status, pic_id, template_dari_event_id)
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

            // 3. Daftarkan kepanitiaan inti jika disediakan (bendahara, sekretaris, kehumasan)
            // Jika user sudah terdaftar sebagai PIC, biarkan dia menjadi PIC sekaligus memiliki bagian tambahan.
            $coreRoles = [
              'bendahara' => 'Bendahara Acara',
              'sekretaris' => 'Sekretaris Acara',
              'kehumasan' => 'Kehumasan/Publikasi'
            ];
            foreach ($coreRoles as $field => $label) {
              $uid = isset($_POST[$field]) && is_numeric($_POST[$field]) ? (int)$_POST[$field] : 0;
              if ($uid) {
                $insCore = $pdo->prepare(
                    "INSERT INTO event_panitia (event_id,user_id,peran_acara,bagian,status_konfirmasi) VALUES (?,?,?,?,?) " .
                    "ON DUPLICATE KEY UPDATE " .
                    "bagian=VALUES(bagian), status_konfirmasi=VALUES(status_konfirmasi), is_double_job=1, " .
                    "peran_acara = IF(peran_acara='pic', peran_acara, VALUES(peran_acara))"
                );
                $insCore->execute([$eventId, $uid, 'panitia_inti', $label, 'bersedia']);
              }
            }

            // 3. Kalau pakai template, clone checklist dari acara sumber
            if ($templateId) {
                $chk = $pdo->prepare("SELECT * FROM event_checklist WHERE event_id = ?");
                $chk->execute([$templateId]);
                $checklists = $chk->fetchAll();
                if ($checklists) {
                    $insChk = $pdo->prepare("
                        INSERT INTO event_checklist (event_id, urutan, item)
                        VALUES (?, ?, ?)
                    ");
                    foreach ($checklists as $c) {
                        $insChk->execute([$eventId, $c['urutan'], $c['item']]);
                    }
                }
            }

            // 4. Tidak membuat approval manager saat acara dibuat.
            //    Approval manager akan dibuat saat PIC mengajukan proposal/rab dari halaman acara.

            $pdo->commit();

            setFlash("Acara \"{$judul}\" berhasil dibuat! Ikuti langkah-langkah persiapan di bawah.", 'success');
            header('Location: ' . BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '&onboarding=1');
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
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

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
        <div id="levelMismatchHint" class="form-text text-warning d-none mt-1">
          <i class="bi bi-exclamation-triangle me-1"></i>Level acara ini berbeda dengan level template yang dipilih.
        </div>
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

      <!-- Kepanitiaan Inti -->
      <div class="mb-3">
        <label class="form-label">Kepanitiaan Inti (opsional)</label>
        <div class="form-text mb-2">Bisa memilih PIC yang sama sebagai Sekretaris jika ingin double job.</div>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Bendahara Acara</label>
            <select name="bendahara" class="form-select">
              <option value="">— Pilih Bendahara —</option>
              <?php foreach ($semuaSDM as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (isset($old['bendahara']) && $old['bendahara']==$s['id'])? 'selected' : '' ?>><?= htmlspecialchars($s['nama'].' · '.($s['jabatan']??'').' · '.($s['divisi']??'')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Sekretaris Acara</label>
            <select name="sekretaris" class="form-select">
              <option value="">— Pilih Sekretaris —</option>
              <?php foreach ($semuaSDM as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (isset($old['sekretaris']) && $old['sekretaris']==$s['id'])? 'selected' : '' ?>><?= htmlspecialchars($s['nama'].' · '.($s['jabatan']??'').' · '.($s['divisi']??'')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Kehumasan / Publikasi</label>
            <select name="kehumasan" class="form-select">
              <option value="">— Pilih Kehumasan —</option>
              <?php foreach ($semuaSDM as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (isset($old['kehumasan']) && $old['kehumasan']==$s['id'])? 'selected' : '' ?>><?= htmlspecialchars($s['nama'].' · '.($s['jabatan']??'').' · '.($s['divisi']??'')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn-primary-custom">
          <i class="bi bi-check-lg me-1"></i> Simpan & Buat Acara
        </button>
        <a href="<?= BASE_URL ?>/modules/events/" class="btn btn-outline-secondary">Batal</a>
      </div>

    </form>
  </div>

  <!-- Sidebar: Pilih Template (PR-05) -->
  <div class="col-lg-4">
    <div class="form-section">
      <h6 class="fw-700 mb-1"><i class="bi bi-bookmark-star me-2 text-warning"></i>Referensi Template</h6>
      <p class="text-muted small mb-3">
        Pilih template acara selesai sebagai referensi. Checklist akan di-clone otomatis dan kamu bisa melihat arsipnya.
      </p>
      <?php if (!empty($templates)): ?>
      <input type="text" id="templateSearch" class="form-control form-control-sm mb-2"
             placeholder="Cari template..." oninput="filterTemplates(this.value)">
      <div id="templateCards" style="max-height:380px;overflow-y:auto;">
        <?php foreach ($templates as $t): ?>
        <div class="template-card border rounded p-2 mb-2 cursor-pointer"
             data-id="<?= $t['id'] ?>"
             data-level="<?= $t['level'] ?>"
             data-judul="<?= htmlspecialchars(strtolower($t['judul'])) ?>"
             onclick="selectTemplate(<?= $t['id'] ?>, '<?= htmlspecialchars($t['judul'], ENT_QUOTES) ?>', '<?= $t['level'] ?>')">
          <div class="d-flex justify-content-between align-items-start">
            <div class="fw-600 fs-13 flex-grow-1 me-2"><?= htmlspecialchars($t['judul']) ?></div>
            <span class="badge bg-secondary flex-shrink-0"><?= $t['level'] ?></span>
          </div>
          <div class="fs-12 text-muted mt-1">
            <?= date('d M Y', strtotime($t['tanggal_mulai'])) ?>
            &bull; <?= (int)$t['jml_panitia'] ?> panitia
            &bull; <?= (int)$t['jml_swot'] ?> evaluasi
          </div>
          <?php if ($t['template_notes']): ?>
          <div class="fs-11 text-warning mt-1"><i class="bi bi-info-circle me-1"></i><?= htmlspecialchars(substr($t['template_notes'], 0, 80)) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div id="selectedTemplateInfo" class="alert alert-success d-none mt-2 py-2 px-3 fs-13">
        <i class="bi bi-check-circle me-2"></i>
        <span id="selectedTemplateName"></span>
        <a href="#" id="selectedTemplateArchiveLink" target="_blank" class="ms-2 fs-12">
          <i class="bi bi-box-arrow-up-right"></i> Lihat Arsip
        </a>
        <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-2" onclick="clearTemplate()">
          ✕ Hapus
        </button>
      </div>
      <input type="hidden" name="template_id" id="templateIdInput" value="<?= htmlspecialchars($old['template_id'] ?? '') ?>">
      <?php else: ?>
      <div class="text-center text-muted small py-3">
        <i class="bi bi-bookmark-x d-block mb-1 fs-4"></i>
        Belum ada template tersedia.<br>
        <span class="fs-11">Admin dapat menandai acara selesai sebagai template di halaman Arsip.</span>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// PR-05: Template picker functionality
const BASE_URL = '<?= BASE_URL ?>';
let selectedTemplateLevel = null;

function updateLevelMismatchHint() {
  const hint = document.getElementById('levelMismatchHint');
  if (!hint) return;
  const levelSelect = document.querySelector('select[name="level"]');
  const currentLevel = levelSelect ? levelSelect.value : '';
  if (selectedTemplateLevel && currentLevel && currentLevel !== selectedTemplateLevel) {
    hint.classList.remove('d-none');
  } else {
    hint.classList.add('d-none');
  }
}

function selectTemplate(id, judul, level) {
  // Set hidden input
  document.getElementById('templateIdInput').value = id;
  selectedTemplateLevel = level;

  // Highlight selected card
  document.querySelectorAll('.template-card').forEach(c => {
    c.style.background = '';
    c.style.borderColor = '';
  });
  const card = document.querySelector(`.template-card[data-id="${id}"]`);
  if (card) {
    card.style.background = '#f0fdf4';
    card.style.borderColor = '#059669';
  }

  // Show info
  document.getElementById('selectedTemplateName').textContent = judul;
  document.getElementById('selectedTemplateInfo').classList.remove('d-none');

  // Fetch archive link via AJAX (also sets session access)
  fetch(`${BASE_URL}/modules/events/ajax_check_template.php?id=${id}`)
    .then(r => r.json())
    .then(data => {
      if (data.archive_url) {
        document.getElementById('selectedTemplateArchiveLink').href = data.archive_url;
      }
    });

  // Warn jika level acara baru berbeda dengan level template
  updateLevelMismatchHint();
}

function clearTemplate() {
  document.getElementById('templateIdInput').value = '';
  document.getElementById('selectedTemplateInfo').classList.add('d-none');
  document.querySelectorAll('.template-card').forEach(c => {
    c.style.background = '';
    c.style.borderColor = '';
  });
  selectedTemplateLevel = null;
  updateLevelMismatchHint();
}

function filterTemplates(q) {
  q = q.toLowerCase();
  // Also get currently selected level
  const levelSelect = document.querySelector('select[name="level"]');
  const selectedLevel = levelSelect ? levelSelect.value : '';

  document.querySelectorAll('.template-card').forEach(card => {
    const matchesText = !q || card.dataset.judul.includes(q);
    const matchesLevel = !selectedLevel || card.dataset.level === selectedLevel;
    card.style.display = (matchesText && matchesLevel) ? '' : 'none';
  });
}

// Auto-filter templates & re-check mismatch hint when level changes
document.querySelector('select[name="level"]')?.addEventListener('change', function() {
  filterTemplates(document.getElementById('templateSearch')?.value || '');
  updateLevelMismatchHint();
});
</script>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>