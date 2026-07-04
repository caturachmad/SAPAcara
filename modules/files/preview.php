<?php
$pageTitle = 'Preview File';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status()===PHP_SESSION_NONE) session_start();
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM event_files WHERE id=?");
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    die('File tidak ditemukan.');
}

$myRole = $pdo->prepare("SELECT * FROM event_panitia WHERE event_id=? AND user_id=?");
$myRole->execute([$file['event_id'], $_SESSION['user_id']]);
$role = $myRole->fetch();

$isPic = isSuperAdmin() || ($role && $role['peran_acara'] === 'pic');
$isInti = $role && in_array($role['peran_acara'], ['pic', 'panitia_inti'], true);
$isAdmin = $role && (int)($role['is_event_admin'] ?? 0) === 1;

$pendingApproval = $pdo->prepare("SELECT COUNT(*) FROM approvals WHERE event_id=? AND approver_id=? AND status='pending'");
$pendingApproval->execute([$file['event_id'], $_SESSION['user_id']]);
$isPendingApprover = (int)$pendingApproval->fetchColumn() > 0;

$canSee = match ($file['visibility']) {
    'all'      => (bool)$role || isSuperAdmin(),
    'inti'     => $isPic || $isInti || $isAdmin || isSuperAdmin(),
    'pic_only' => $isPic || isSuperAdmin(),
    default    => false,
};

if (!$canSee && !$isPendingApprover) {
    http_response_code(403);
    die('Akses ditolak.');
}

$path = __DIR__ . '/../../uploads/' . $file['file_path'];
if (!file_exists($path)) {
    http_response_code(404);
    die('File tidak ditemukan di server.');
}

// Validate path to prevent directory traversal attacks
$realPath = realpath($path);
$uploadDir = realpath(__DIR__ . '/../../uploads/');
if ($realPath === false || strpos($realPath, $uploadDir) !== 0) {
    http_response_code(403);
    die('Akses ditolak (path validation gagal).');
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
$isImage = str_starts_with($mime, 'image/');
$isPdf = $mime === 'application/pdf';
$isText = str_starts_with($mime, 'text/');
$downloadUrl = BASE_URL . '/modules/files/download.php?id=' . (int)$file['id'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Preview File — SAPAcara</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body{background:#f4f7fb}
    .preview-shell{max-width:1100px;margin:24px auto;padding:0 16px}
    .preview-frame{background:#fff;border-radius:16px;box-shadow:0 8px 30px rgba(15,23,42,.08);overflow:hidden;border:1px solid #e5e7eb}
    .preview-head{padding:18px 20px;border-bottom:1px solid #e5e7eb;background:#fff}
    .preview-body{padding:20px}
    .meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .meta-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px}
    .viewer{background:#0f172a;border-radius:14px;overflow:hidden;min-height:65vh;display:flex;align-items:center;justify-content:center}
    .viewer iframe,.viewer img{width:100%;height:75vh;border:0;background:#fff}
    .viewer img{object-fit:contain}
    @media (max-width:768px){.meta-grid{grid-template-columns:1fr}.viewer iframe,.viewer img{height:60vh}}
  </style>
</head>
<body>
  <div class="preview-shell">
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= (int)$file['event_id'] ?>#dokumen" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kembali
      </a>
      <div class="d-flex gap-2">
        <a href="<?= htmlspecialchars($downloadUrl) ?>" class="btn btn-primary btn-sm">
          <i class="bi bi-download me-1"></i>Download
        </a>
        <?php if (($role && ($isPic || $isAdmin)) || isSuperAdmin() || (int)$file['uploaded_by'] === (int)$_SESSION['user_id']): ?>
          <a href="<?= BASE_URL ?>/modules/files/edit.php?id=<?= (int)$file['id'] ?>" class="btn btn-warning btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="preview-frame">
      <div class="preview-head">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <h4 class="mb-1 fw-700"><?= htmlspecialchars($file['nama_file']) ?></h4>
            <div class="text-muted small">
              <?= htmlspecialchars($file['file_original']) ?> · <?= htmlspecialchars($file['file_type']) ?> · <?= number_format(((int)$file['file_size']) / 1024, 2) ?> KB
            </div>
          </div>
          <div class="text-end">
            <span class="badge bg-<?= $file['visibility'] === 'pic_only' ? 'danger' : ($file['visibility'] === 'inti' ? 'warning text-dark' : 'success') ?>">
              <?= htmlspecialchars($file['visibility']) ?>
            </span>
            <div class="small text-muted mt-1">
              Diupload: <?= date('d M Y H:i', strtotime($file['created_at'])) ?>
            </div>
          </div>
        </div>
      </div>

      <div class="preview-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="meta-box">
              <div class="small text-muted">Deskripsi</div>
              <div class="fw-600"><?= htmlspecialchars($file['deskripsi'] ?: '—') ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="meta-box">
              <div class="small text-muted">Tipe Akses</div>
              <div class="fw-600"><?= htmlspecialchars($file['visibility']) ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="meta-box">
              <div class="small text-muted">Bisa Edit/Hapus</div>
              <div class="fw-600"><?= htmlspecialchars($file['can_edit_by'] ?? '—') ?></div>
            </div>
          </div>
        </div>

        <div class="viewer">
          <?php if ($isPdf): ?>
            <iframe src="<?= htmlspecialchars($downloadUrl) ?>" title="Preview PDF"></iframe>
          <?php elseif ($isImage): ?>
            <img src="<?= htmlspecialchars($downloadUrl) ?>" alt="<?= htmlspecialchars($file['nama_file']) ?>">
          <?php elseif ($isText): ?>
            <iframe src="<?= htmlspecialchars($downloadUrl) ?>" title="Preview Text"></iframe>
          <?php else: ?>
            <div class="text-center text-white p-4">
              <div class="display-6 mb-2">📄</div>
              <h5 class="mb-2">Preview tidak tersedia untuk tipe file ini</h5>
              <p class="mb-3 text-white-50">Gunakan tombol download untuk membuka file di aplikasi yang sesuai.</p>
              <a href="<?= htmlspecialchars($downloadUrl) ?>" class="btn btn-light">
                <i class="bi bi-download me-1"></i>Download File
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
