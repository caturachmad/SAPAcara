<?php
$pageTitle = 'Buat Acara Baru';
require_once __DIR__ . '/../../includes/layout/header.php';

// Ambil daftar SDM aktif untuk dropdown kepanitiaan inti
$semuaSDM = $pdo->query("SELECT id, nama, divisi, jabatan, jabatan_sistem FROM users WHERE status='aktif' ORDER BY nama")->fetchAll();

// Ambil daftar template (acara yang sudah pernah ada)
$templates = $pdo->query(
    "SELECT id, judul, level, tanggal_mulai FROM events ORDER BY tanggal_mulai DESC LIMIT 50"
)->fetchAll();

$errors = [];
$old    = [];   // untuk repopulate form jika ada error

function mergeRoleLabel(string $existing, string $label): string {
    $existing = trim($existing);
    if ($existing === '') {
        return $label;
    }
    $parts = array_map('trim', explode(' / ', $existing));
    if (!in_array($label, $parts, true)) {
        $parts[] = $label;
    }
    return implode(' / ', array_values(array_filter($parts)));
}

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
            //    Panitia yang sama boleh dipilih di lebih dari satu jabatan (double job).
            $coreRoles = [
              'bendahara' => 'Bendahara Acara',
              'sekretaris' => 'Sekretaris Acara',
              'kehumasan' => 'Kehumasan/Publikasi'
            ];
            $findExisting = $pdo->prepare("SELECT * FROM event_panitia WHERE event_id = ? AND user_id = ? LIMIT 1");
            $insertCore = $pdo->prepare("INSERT INTO event_panitia (event_id,user_id,peran_acara,bagian,is_double_job,status_konfirmasi) VALUES (?,?,?,?,?,?)");
            $updateCore = $pdo->prepare("UPDATE event_panitia SET bagian = ?, is_double_job = 1 WHERE id = ?");
            foreach ($coreRoles as $field => $label) {
              $uid = isset($_POST[$field]) && is_numeric($_POST[$field]) ? (int)$_POST[$field] : 0;
              if (!$uid) {
                continue;
              }

              $findExisting->execute([$eventId, $uid]);
              $existing = $findExisting->fetch();

              if ($existing) {
                $mergedBagian = mergeRoleLabel((string)($existing['bagian'] ?? ''), $label);
                $updateCore->execute([$mergedBagian, $existing['id']]);
              } else {
                $insertCore->execute([$eventId, $uid, 'panitia_inti', $label, 0, 'bersedia']);
              }
            }

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

            // 4. Approval manager dibuat setelah proposal diajukan dari workspace
            //    agar manager dapat melihat dokumen proposal yang lengkap terlebih dahulu.

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

      <!-- Kepanitiaan Inti -->
      <div class="mb-3">
        <label class="form-label">Kepanitiaan Inti & Double Job (opsional)</label>
        <div class="form-text mb-2">Satu orang boleh dipilih di lebih dari satu jabatan. Jika seseorang sudah jadi PIC, ia tetap bisa merangkap sekretaris, bendahara, atau kehumasan.</div>
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
