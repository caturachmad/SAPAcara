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

$panitia = $pdo->prepare("
    SELECT ep.*, u.nama, u.divisi, u.jabatan
    FROM event_panitia ep
    JOIN users u ON u.id = ep.user_id
    WHERE ep.event_id = ?
    ORDER BY FIELD(ep.peran_acara,'pic','panitia_inti','panitia_support'), ep.bagian
");
$panitia->execute([$id]);
$daftarPanitia = $panitia->fetchAll();

$checklist = $pdo->prepare("SELECT * FROM event_checklist WHERE event_id=? ORDER BY urutan");
$checklist->execute([$id]);
$items = $checklist->fetchAll();

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
        <?php if ($ev['status'] === 'draft'): ?>
        <a href="?id=<?=$id?>&action=submit" class="btn btn-primary btn-sm"
           data-confirm="Ajukan acara ini untuk persetujuan?">
          <i class="bi bi-send me-1"></i> Ajukan ke Manager
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/events/edit.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-pencil me-1"></i> Edit Acara
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
// Handle quick action: submit
if (isset($_GET['action']) && $_GET['action'] === 'submit' && $canManage && $ev['status'] === 'draft') {
    $pdo->prepare("UPDATE events SET status='pengajuan' WHERE id=?")->execute([$id]);
    setFlash('Acara berhasil diajukan untuk persetujuan.', 'success');
    header('Location: ' . BASE_URL . '/modules/events/detail.php?id=' . $id);
    exit;
}
require_once __DIR__ . '/../../includes/layout/footer.php';
?>