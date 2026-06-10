<?php
$pageTitle = 'Upload File';
require_once __DIR__ . '/../../includes/layout/header.php';

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) { header('Location: ' . BASE_URL . '/modules/dashboard/select.php'); exit; }

$ev = $pdo->prepare("SELECT * FROM events WHERE id=?");
$ev->execute([$eventId]); $ev = $ev->fetch();
if (!$ev || !($isEventAdmin || isSuperAdmin() || isPIC($eventId,$pdo) || isEventAdmin($eventId,$pdo))) {
    header('Location: ' . BASE_URL . '/modules/events/workspace.php?id=' . $eventId); exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['file'])) {
    $f       = $_FILES['file'];
    $nama    = trim($_POST['nama_file'] ?? '');
    $desc    = trim($_POST['deskripsi'] ?? '');
    $type    = $_POST['file_type'] ?? 'lainnya';
    $vis     = $_POST['visibility'] ?? 'inti';
    $canEdit = $_POST['can_edit_by'] ?? 'inti';

    if (!$nama)            $errors[] = 'Nama file wajib diisi.';
    if ($f['error'] !== 0) {
      switch ($f['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
          $errors[] = 'Ukuran file melebihi batas server. Maksimum: ' . ini_get('upload_max_filesize');
          break;
        case UPLOAD_ERR_PARTIAL:
          $errors[] = 'File hanya terupload sebagian. Coba lagi.';
          break;
        case UPLOAD_ERR_NO_FILE:
          $errors[] = 'Tidak ada file yang diunggah.';
          break;
        case UPLOAD_ERR_NO_TMP_DIR:
          $errors[] = 'Folder sementara (tmp) tidak tersedia di server.';
          break;
        case UPLOAD_ERR_CANT_WRITE:
          $errors[] = 'Gagal menulis file ke disk (permission).';
          break;
        case UPLOAD_ERR_EXTENSION:
        default:
          $errors[] = 'Gagal upload file (kode: ' . $f['error'] . ').';
      }
    }
    if ($f['size'] > 20*1024*1024) $errors[] = 'Ukuran file max 20MB.';

    // sanity: ensure PHP actually created temporary uploaded file
    if (!empty($f['tmp_name']) && !is_uploaded_file($f['tmp_name'])) {
      $errors[] = 'File upload tidak valid atau tmp file tidak ditemukan.';
    }

    $allowedExt = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','zip','rar'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) $errors[] = 'Tipe file tidak didukung.';

    // Batasi dokumen yang hanya boleh dikirim setelah approval manager
    $restricted = ['rab','perijinan'];
    if (in_array($type, $restricted) && !in_array($ev['status'], ['disetujui_manager','disetujui','berlangsung'])) {
      $errors[] = 'Dokumen ini hanya dapat diupload setelah approval manager.';
    }

    if (empty($errors)) {
        $dir = __DIR__ . '/../../uploads/events/' . $eventId . '/';
        if (!is_dir($dir)) {
          if (!mkdir($dir, 0777, true)) {
            $errors[] = 'Gagal membuat direktori upload. Periksa permission pada folder uploads/.';
          } else {
            chmod($dir, 0777);
          }
        }
        if (empty($errors) && (!is_dir($dir) || !is_writable($dir))) {
          $errors[] = 'Direktori upload tidak dapat diakses. Pastikan folder uploads/ dan subfoldernya dapat ditulis oleh server.';
          error_log("[upload] upload dir cannot be written: {$dir}");
        }
        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . $filename;
        if (empty($errors) && move_uploaded_file($f['tmp_name'], $target)) {
            $pdo->prepare("INSERT INTO event_files (event_id,nama_file,deskripsi,file_path,file_original,file_type,file_size,visibility,can_edit_by,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$eventId,$nama,$desc,'events/'.$eventId.'/'.$filename,$f['name'],$type,$f['size'],$vis,$canEdit,$_SESSION['user_id']]);
          // Jika file adalah RAB, buat approval ke bendahara otomatis
          if ($type === 'rab') {
            $b = $pdo->prepare("SELECT id FROM users WHERE jabatan_sistem = 'bendahara_tertinggi' AND status='aktif' LIMIT 1");
            $b->execute(); $bend = $b->fetch();
            if ($bend) {
              $bid = (int)$bend['id'];
              // tambahkan approval baru untuk bendahara
              $pdo->prepare("INSERT INTO approvals (event_id, approver_id, tipe_approver, urutan) VALUES (?,?,?,?)")
                ->execute([$eventId, $bid, 'bendahara', 1]);
              // update status event menjadi RAB diajukan
              $pdo->prepare("UPDATE events SET status='rab_diajukan' WHERE id=?")->execute([$eventId]);
              addNotif($pdo, $bid, 'Permintaan Pencairan Dana (RAB)', "RAB untuk acara {$ev['judul']} telah diajukan. Mohon tinjau dan setujui.", BASE_URL.'/modules/approvals/','info');
              // kirim email ke bendahara
              require_once __DIR__ . '/../../config/mail.php';
              $appr = $pdo->prepare("SELECT * FROM users WHERE id=?"); $appr->execute([$bid]); $apUser = $appr->fetch();
              if ($apUser) {
                $html = mailTemplateApproval($apUser, $ev, 'bendahara');
                sendMail($apUser['email'], $apUser['nama'], 'Permintaan Pencairan Dana (RAB): ' . $ev['judul'], $html);
              }
            }
          }
            setFlash('File berhasil diupload!','success');
            header('Location: ' . BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '#dokumen'); exit;
        } else {
          $lastErr = error_get_last();
          error_log("[upload] move_uploaded_file failed for event={$eventId} user={$_SESSION['user_id']} tmp=" . ($f['tmp_name']??'') . " target={$target} - " . json_encode($lastErr));
          $errors[] = 'Gagal menyimpan file ke server. Periksa permission folder uploads/ atau hubungi admin.';
        }
    }
}
?>
<div class="page-header">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eventId ?>#dokumen" class="back-btn"><i class="bi bi-arrow-left"></i></a>
    <div>
      <h5>Upload File</h5>
      <div class="sub"><?= htmlspecialchars($ev['judul']) ?></div>
    </div>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger mb-3"><?php foreach($errors as $e) echo "<div><i class='bi bi-x-circle me-1'></i>$e</div>"; ?></div>
<?php endif; ?>

<div class="card" style="max-width:600px">
  <div class="card-header"><i class="bi bi-upload"></i> Upload File Baru</div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data" class="row g-3">
          <?php if(function_exists('csrfToken')): ?><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><?php endif; ?>
      <div class="col-12">
        <label class="form-label">File <span class="text-danger">*</span></label>
        <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip,.rar" required>
        <div class="form-text">Max 20MB · PDF, Word, Excel, PPT, Gambar, ZIP</div>
      </div>
      <div class="col-12">
        <label class="form-label">Nama File <span class="text-danger">*</span></label>
        <input type="text" name="nama_file" class="form-control" placeholder="cth: RAB Pensi SD 2026 v1" required>
      </div>
      <div class="col-12">
        <label class="form-label">Deskripsi</label>
        <input type="text" name="deskripsi" class="form-control" placeholder="Keterangan singkat isi file (opsional)">
      </div>
      <div class="col-md-6">
        <label class="form-label">Tipe Dokumen</label>
        <select name="file_type" class="form-select">
          <option value="rab">RAB (Rencana Anggaran)</option>
          <option value="rundown">Rundown Acara</option>
          <option value="proposal">Proposal</option>
          <option value="perijinan">Surat Perijinan</option>
          <option value="jobdesk">Jobdesk / SOP</option>
          <option value="undangan">Undangan</option>
          <option value="lainnya" selected>Lainnya</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Siapa yang Bisa Lihat?</label>
        <select name="visibility" class="form-select">
          <option value="all">Semua Panitia</option>
          <option value="inti" selected>Panitia Inti & PIC</option>
          <option value="pic_only">PIC Saja</option>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Siapa yang Bisa Edit/Hapus?</label>
        <select name="can_edit_by" class="form-select">
          <option value="pic_only">PIC & Event Admin saja</option>
          <option value="inti" selected>Panitia Inti & PIC</option>
        </select>
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload File</button>
        <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eventId ?>#dokumen" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
