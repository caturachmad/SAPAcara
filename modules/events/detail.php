<?php
$pageTitle = 'Detail Acara';
require_once __DIR__ . '/../../includes/layout/header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/modules/events/'); exit; }

$stmt = $pdo->prepare("
    SELECT e.*, u.nama AS nama_pic
    FROM events e
    LEFT JOIN users u ON u.id = e.pic_id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$ev = $stmt->fetch();
if (!$ev) { header('Location: ' . BASE_URL . '/modules/events/'); exit; }

$canManage = isSuperAdmin() || isPIC($id, $pdo) || isEventAdmin($id, $pdo);

$hasProposalDocsQ = $pdo->prepare("SELECT COUNT(*) FROM event_files WHERE event_id=? AND file_type IN ('proposal','rundown','jobdesk','undangan','rab')");
$hasProposalDocsQ->execute([$id]);
$proposalDocsCount = (int)$hasProposalDocsQ->fetchColumn();

$pendingManagerQ = $pdo->prepare("SELECT COUNT(*) FROM approvals WHERE event_id=? AND tipe_approver IN ('manager_tk','manager_sd','manager_smp') AND status='pending'");
$pendingManagerQ->execute([$id]);
$hasPendingManagerApproval = (int)$pendingManagerQ->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_to_manager']) && isPIC($id, $pdo) && $ev['status'] === 'draft') {
    if ($proposalDocsCount === 0) {
        setFlash('Unggah minimal satu dokumen proposal/rundown terlebih dahulu sebelum mengajukan ke kepala sekolah.', 'warning');
    } elseif ($hasPendingManagerApproval) {
        setFlash('Sudah ada pengajuan kepala sekolah yang menunggu keputusan.', 'info');
    } else {
        $levelToTipe = ['TK' => 'manager_tk', 'SD' => 'manager_sd', 'SMP' => 'manager_smp'];
        $tipeManager = $levelToTipe[$ev['level']] ?? null;
        if (!$tipeManager) {
            setFlash('Level acara tidak sesuai untuk pengajuan kepala sekolah. Hubungi admin.', 'warning');
        } else {
            $approverId = null;
            $q = $pdo->prepare("SELECT id FROM users WHERE jabatan_sistem = ? AND divisi = ? AND status='aktif' LIMIT 1");
            $q->execute([$tipeManager, $ev['level']]);
            if ($r = $q->fetch()) {
                $approverId = (int)$r['id'];
            }
            if (!$approverId) {
                $q2 = $pdo->prepare("SELECT id FROM users WHERE jabatan_sistem = ? AND status='aktif' LIMIT 1");
                $q2->execute([$tipeManager]);
                if ($r2 = $q2->fetch()) {
                    $approverId = (int)$r2['id'];
                }
            }
            if ($approverId) {
                $pdo->prepare("INSERT INTO approvals (event_id, approver_id, tipe_approver, urutan) VALUES (?,?,?,?)")
                    ->execute([$id, $approverId, $tipeManager, 1]);
                $pdo->prepare("UPDATE events SET status='pengajuan' WHERE id=?")->execute([$id]);
                require_once __DIR__ . '/../../config/mail.php';
                $approver = $pdo->prepare("SELECT * FROM users WHERE id=?");
                $approver->execute([$approverId]);
                if ($approverUser = $approver->fetch()) {
                    $html = mailTemplateApproval($approverUser, $ev, $tipeManager);
                    sendMail($approverUser['email'], $approverUser['nama'], 'Permintaan Approval Proposal: ' . $ev['judul'], $html);
                    addNotif($pdo, $approverId, 'Permintaan Approval Proposal', "Proposal acara {$ev['judul']} telah diajukan. Mohon tinjau dan berikan keputusan.", BASE_URL.'/modules/approvals/', 'info');
                }
                setFlash('Proposal berhasil diajukan ke kepala sekolah.', 'success');
            } else {
                setFlash('Approver kepala sekolah belum ditemukan. Hubungi admin.', 'danger');
            }
        }
    }
    header('Location: ?id=' . $id);
    exit;
}

$panitia = $pdo->prepare("
    SELECT ep.*, u.nama, u.divisi, u.jabatan
    FROM event_panitia ep
    JOIN users u ON u.id = ep.user_id
    WHERE ep.event_id = ?
    ORDER BY FIELD(ep.peran_acara,'pic','panitia_inti','panitia_support'), ep.bagian
");
$panitia->execute([$id]);
$daftarPanitia = $panitia->fetchAll();

$filesQ = $pdo->prepare("SELECT f.*, u.nama AS uploader FROM event_files f LEFT JOIN users u ON u.id=f.uploaded_by WHERE f.event_id=? ORDER BY f.created_at DESC");
$filesQ->execute([$id]);
$files = $filesQ->fetchAll();

$checklist = $pdo->prepare("SELECT * FROM event_checklist WHERE event_id=? ORDER BY urutan");
$checklist->execute([$id]);
$items = $checklist->fetchAll();

$fileTypeLabel = ['rab'=>'RAB','rundown'=>'Rundown','proposal'=>'Proposal','perijinan'=>'Perijinan','jobdesk'=>'Jobdesk','undangan'=>'Undangan','lainnya'=>'Lainnya'];

$statusLabel = [
    'draft'             => 'Draft',          'pengajuan'         => 'Pengajuan',
    'disetujui_manager' => 'Disetujui Mgr',  'proposal_dibuat'   => 'Proposal Dibuat',
    'rab_diajukan'      => 'RAB Diajukan',   'perijinan'         => 'Perijinan',
    'disetujui'         => 'Disetujui',      'berlangsung'       => 'Berlangsung',
    'selesai'           => 'Selesai',        'ditolak'           => 'Ditolak',
];
$peranLabel = ['pic'=>'PIC','panitia_inti'=>'Panitia Inti','panitia_support'=>'Panitia Support'];
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= BASE_URL ?>/modules/events/" class="text-muted text-decoration-none">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-700 flex-grow-1"><?= htmlspecialchars($ev['judul']) ?></h5>
  <span class="badge-status status-<?= $ev['status'] ?>"><?= $statusLabel[$ev['status']] ?? $ev['status'] ?></span>
  <?php if (isPIC($id, $pdo) && $ev['status'] === 'draft' && !$hasPendingManagerApproval): ?>
    <form method="POST" class="ms-3">
      <button type="submit" name="submit_to_manager" value="1" class="btn btn-sm btn-warning">
        <i class="bi bi-send-plus me-1"></i> Ajukan ke Kepala Sekolah
      </button>
    </form>
  <?php elseif (isPIC($id, $pdo) && $ev['status'] === 'draft' && $hasPendingManagerApproval): ?>
    <span class="badge bg-info text-dark ms-3">Pengajuan kepala sekolah sedang menunggu keputusan</span>
  <?php endif; ?>
</div>

<div class="row g-3">
  <!-- Info Acara -->
  <div class="col-lg-8">
    <div class="card-section mb-3 p-4">
      <div class="row g-3 mb-3">
        <div class="col-sm-4">
          <div class="text-muted small">Level</div>
          <div class="fw-600"><?= htmlspecialchars($ev['level']) ?></div>
        </div>
        <div class="col-sm-4">
          <div class="text-muted small">Tanggal Mulai</div>
          <div class="fw-600"><?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?></div>
        </div>
        <div class="col-sm-4">
          <div class="text-muted small">Tanggal Selesai</div>
          <div class="fw-600"><?= date('d M Y', strtotime($ev['tanggal_selesai'])) ?></div>
        </div>
        <div class="col-sm-4">
          <div class="text-muted small">Lokasi</div>
          <div class="fw-600"><?= htmlspecialchars($ev['lokasi'] ?: '-') ?></div>
        </div>
        <div class="col-sm-4">
          <div class="text-muted small">PIC</div>
          <div class="fw-600"><?= htmlspecialchars($ev['nama_pic'] ?? '-') ?></div>
        </div>
      </div>
      <?php if ($ev['deskripsi']): ?>
      <div class="border-top pt-3">
        <div class="text-muted small mb-1">Deskripsi</div>
        <div class="small"><?= nl2br(htmlspecialchars($ev['deskripsi'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if (isPIC($id, $pdo) && $ev['status'] === 'draft'): ?>
      <div class="alert alert-info mt-3 mb-0 small">
        Upload proposal, rundown, daftar kepanitiaan, dan dokumen pendukung di halaman dokumen sebelum mengajukan ke kepala sekolah.
      </div>
      <?php endif; ?>
    </div>

    <!-- Dokumen -->
    <div class="card-section mb-3">
      <div class="card-header-bar">
        <span><i class="bi bi-folder2-open me-2"></i>Dokumen Acara</span>
        <?php if ($canManage): ?>
        <a href="<?= BASE_URL ?>/modules/files/upload.php?event_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-upload me-1"></i> Upload File
        </a>
        <?php endif; ?>
      </div>
      <?php if (!empty($files)): ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>Nama File</th><th>Tipe</th><th>Uploader</th><th>Tanggal</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($files as $f): ?>
            <tr>
              <td class="fw-600 small"><?= htmlspecialchars($f['nama_file']) ?></td>
              <td class="small"><?= htmlspecialchars($fileTypeLabel[$f['file_type']] ?? $f['file_type']) ?></td>
              <td class="small"><?= htmlspecialchars($f['uploader'] ?? '-') ?></td>
              <td class="small"><?= date('d M Y H:i', strtotime($f['created_at'])) ?></td>
              <td>
                <a href="<?= BASE_URL ?>/modules/files/download.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-download"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center text-muted py-4 small">
        <i class="bi bi-folder2 d-block mb-1 fs-3"></i>Tidak ada dokumen acara.
      </div>
      <?php endif; ?>
    </div>

    <!-- Susunan Panitia -->
    <div class="card-section">
      <div class="card-header-bar">
        <span><i class="bi bi-people me-2"></i>Susunan Panitia</span>
        <?php if ($canManage): ?>
        <a href="<?= BASE_URL ?>/modules/panitia/assign.php?event_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-person-plus"></i> Tambah Panitia
        </a>
        <?php endif; ?>
      </div>
      <?php if ($daftarPanitia): ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>Nama</th><th>Peran</th><th>Bagian</th><th>Konfirmasi</th></tr></thead>
          <tbody>
            <?php foreach ($daftarPanitia as $p): ?>
            <tr>
              <td>
                <div class="fw-600 small"><?= htmlspecialchars($p['nama']) ?></div>
                <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($p['divisi'] ?? '') ?></div>
              </td>
              <td><span class="badge bg-secondary"><?= $peranLabel[$p['peran_acara']] ?? $p['peran_acara'] ?></span></td>
              <td class="small"><?= htmlspecialchars($p['bagian'] ?? '-') ?></td>
              <td>
                <?php if ($p['status_konfirmasi']==='bersedia'): ?>
                <span class="badge bg-success">Bersedia</span>
                <?php elseif ($p['status_konfirmasi']==='tidak_bisa'): ?>
                <span class="badge bg-danger">Tidak Bisa</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark">Pending</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center text-muted py-4 small">
        <i class="bi bi-person-plus d-block mb-1 fs-3"></i>Belum ada panitia ditugaskan.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Sidebar: Checklist & Aksi -->
  <div class="col-lg-4">
    <?php if ($items): ?>
    <div class="card-section mb-3">
      <div class="card-header-bar"><span><i class="bi bi-list-check me-2"></i>Checklist Panitia</span></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($items as $c): ?>
        <li class="list-group-item d-flex gap-2 align-items-start py-2 small">
          <i class="bi bi-check2-circle text-success mt-1"></i>
          <div>
            <div class="fw-600"><?= htmlspecialchars($c['item']) ?></div>
            <?php if ($c['keterangan']): ?>
            <div class="text-muted"><?= htmlspecialchars($c['keterangan']) ?></div>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <div class="card-section p-3">
      <h6 class="fw-700 mb-2">Aksi Cepat</h6>
      <div class="d-grid gap-2">
        <a href="<?= BASE_URL ?>/modules/events/edit.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-pencil me-1"></i> Edit Acara
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
require_once __DIR__ . '/../../includes/layout/footer.php';
?>