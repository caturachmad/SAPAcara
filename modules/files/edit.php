<?php
$pageTitle = 'Edit File';
require_once __DIR__ . '/../../includes/layout/header.php';

if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/auth.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/modules/dashboard/select.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM event_files WHERE id=?");
$stmt->execute([$id]);
$file = $stmt->fetch();
if (!$file) {
    http_response_code(404);
    die('File tidak ditemukan.');
}

$eventId = (int)$file['event_id'];

$myRole = $pdo->prepare("SELECT * FROM event_panitia WHERE event_id=? AND user_id=?");
$myRole->execute([$eventId, $_SESSION['user_id']]);
$role = $myRole->fetch();

$isPic = isSuperAdmin() || ($role && $role['peran_acara'] === 'pic');
$isInti = $role && in_array($role['peran_acara'], ['pic', 'panitia_inti'], true);
$isAdmin = $role && (int)($role['is_event_admin'] ?? 0) === 1;

$canEdit = isSuperAdmin()
    || $isPic
    || $isAdmin
    || (int)$file['uploaded_by'] === (int)$_SESSION['user_id']
    || in_array($file['can_edit_by'] ?? 'inti', ['inti', 'pic_only'], true);

if (!$canEdit) {
    http_response_code(403);
    die('Akses ditolak.');
}

$errors = [];
$event = $pdo->prepare("SELECT * FROM events WHERE id=?");
$event->execute([$eventId]);
$ev = $event->fetch();
if (!$ev) {
    http_response_code(404);
    die('Acara tidak ditemukan.');
}

$visOptions = ['all', 'inti', 'pic_only'];
$editOptions = ['inti', 'pic_only'];
$fileTypes = ['rab', 'rundown', 'proposal', 'perijinan', 'jobdesk', 'undangan', 'lainnya'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_file'])) {
    $nama = trim($_POST['nama_file'] ?? '');
    $desc = trim($_POST['deskripsi'] ?? '');
    $type = $_POST['file_type'] ?? 'lainnya';
    $vis = $_POST['visibility'] ?? 'inti';
    $canEditBy = $_POST['can_edit_by'] ?? 'inti';

    if ($nama === '') {
        $errors[] = 'Nama file wajib diisi.';
    }
    if (!in_array($type, $fileTypes, true)) {
        $errors[] = 'Tipe file tidak valid.';
    }
    if (!in_array($vis, $visOptions, true)) {
        $errors[] = 'Visibilitas tidak valid.';
    }
    if (!in_array($canEditBy, $editOptions, true)) {
        $errors[] = 'Pengaturan edit tidak valid.';
    }

    $newPath = $file['file_path'];
    $newOriginal = $file['file_original'];
    $newSize = (int)$file['file_size'];

    if (!empty($_FILES['file']['name'])) {
        $f = $_FILES['file'];
        if ($f['error'] !== 0) {
            $errors[] = 'File baru gagal diupload.';
        } else {
            $allowedExt = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','zip','rar'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'Tipe file baru tidak didukung.';
            }
            if ($f['size'] > 20 * 1024 * 1024) {
                $errors[] = 'Ukuran file baru max 20MB.';
            }
            if (empty($errors)) {
                // Validasi isi file (MIME type asli + magic bytes) sebelum replace
                require_once __DIR__ . '/../../includes/FileUploader.php';
                foreach (FileUploader::validateContent($f['tmp_name'], $ext) as $ce) {
                    $errors[] = $ce;
                }
            }
            if (empty($errors)) {
                $dir = __DIR__ . '/../../uploads/events/' . $eventId . '/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $target = $dir . $filename;
                if (move_uploaded_file($f['tmp_name'], $target)) {
                    $oldPath = __DIR__ . '/../../uploads/' . $file['file_path'];
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                    $newPath = 'events/' . $eventId . '/' . $filename;
                    $newOriginal = $f['name'];
                    $newSize = (int)$f['size'];
                } else {
                    $errors[] = 'Gagal menyimpan file baru.';
                }
            }
        }
    }

    if (empty($errors)) {
        $pdo->prepare("UPDATE event_files SET nama_file=?, deskripsi=?, file_type=?, visibility=?, can_edit_by=?, file_path=?, file_original=?, file_size=? WHERE id=?")
            ->execute([$nama, $desc, $type, $vis, $canEditBy, $newPath, $newOriginal, $newSize, $id]);

        setFlash('File berhasil diperbarui.', 'success');
        header('Location: ' . BASE_URL . '/modules/events/workspace.php?id=' . $eventId . '#dokumen');
        exit;
    }
}

$downloadUrl = BASE_URL . '/modules/files/download.php?id=' . $id;
$previewUrl = BASE_URL . '/modules/files/preview.php?id=' . $id;
?>

<div class="page-header">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eventId ?>#dokumen" class="back-btn"><i class="bi bi-arrow-left"></i></a>
    <div>
      <h5>Edit File</h5>
      <div class="sub"><?= htmlspecialchars($ev['judul']) ?></div>
    </div>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger mb-3">
    <?php foreach ($errors as $e) echo "<div><i class='bi bi-x-circle me-1'></i>" . htmlspecialchars($e) . "</div>"; ?>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-pencil-square"></i> Edit Metadata & File</span>
        <div class="d-flex gap-2">
          <a href="<?= htmlspecialchars($previewUrl) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Preview</a>
          <a href="<?= htmlspecialchars($downloadUrl) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Download</a>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="row g-3">
          <?php if(function_exists('csrfToken')): ?><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><?php endif; ?>
          <input type="hidden" name="id" value="<?= $id ?>">

          <div class="col-12">
            <label class="form-label">Nama File</label>
            <input type="text" name="nama_file" class="form-control" value="<?= htmlspecialchars($file['nama_file']) ?>" required>
          </div>

          <div class="col-12">
            <label class="form-label">Deskripsi</label>
            <input type="text" name="deskripsi" class="form-control" value="<?= htmlspecialchars($file['deskripsi'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Tipe Dokumen</label>
            <select name="file_type" class="form-select">
              <?php foreach ($fileTypes as $ft): ?>
                <option value="<?= $ft ?>" <?= $file['file_type'] === $ft ? 'selected' : '' ?>><?= strtoupper($ft) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Siapa yang Bisa Lihat?</label>
            <select name="visibility" class="form-select">
              <option value="all" <?= $file['visibility'] === 'all' ? 'selected' : '' ?>>Semua Panitia</option>
              <option value="inti" <?= $file['visibility'] === 'inti' ? 'selected' : '' ?>>Panitia & PIC</option>
              <option value="pic_only" <?= $file['visibility'] === 'pic_only' ? 'selected' : '' ?>>PIC Saja</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Siapa yang Bisa Edit/Hapus?</label>
            <select name="can_edit_by" class="form-select">
              <option value="inti" <?= ($file['can_edit_by'] ?? 'inti') === 'inti' ? 'selected' : '' ?>>Panitia & PIC</option>
              <option value="pic_only" <?= ($file['can_edit_by'] ?? 'inti') === 'pic_only' ? 'selected' : '' ?>>PIC & Event Admin saja</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Ganti File</label>
            <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip,.rar">
            <div class="form-text">Kosongkan jika tidak ingin mengganti file.</div>
          </div>

          <div class="col-12 d-flex gap-2">
            <button type="submit" name="save_file" class="btn btn-primary">
              <i class="bi bi-save me-1"></i>Simpan Perubahan
            </button>
            <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eventId ?>#dokumen" class="btn btn-outline-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-info-circle"></i> Informasi File</div>
      <div class="card-body">
        <div class="mb-2"><small class="text-muted">Nama asli</small><div class="fw-600"><?= htmlspecialchars($file['file_original']) ?></div></div>
        <div class="mb-2"><small class="text-muted">Ukuran</small><div class="fw-600"><?= number_format(((int)$file['file_size']) / 1024, 2) ?> KB</div></div>
        <div class="mb-2"><small class="text-muted">Diunggah oleh</small><div class="fw-600"><?= htmlspecialchars($_SESSION['user']['nama'] ?? '—') ?></div></div>
        <div class="mb-2"><small class="text-muted">Upload terakhir</small><div class="fw-600"><?= date('d M Y H:i', strtotime($file['created_at'])) ?></div></div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
