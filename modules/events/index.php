<?php
$pageTitle = 'Semua Acara';
require_once __DIR__ . '/../../includes/layout/header.php';

$uid  = $_SESSION['user_id'];
$role = $user['role_sistem'];

$filterStatus = $_GET['status'] ?? '';
$filterLevel  = $_GET['level']  ?? '';
$search       = $_GET['q']      ?? '';

$params = [];

// Superadmin: lihat semua | Staff: hanya acara yang diikuti
if ($role === 'superadmin') {
    $join  = "LEFT JOIN users u ON u.id = e.pic_id";
    $where = "WHERE 1=1";
} else {
    $join  = "JOIN event_panitia ep ON ep.event_id = e.id AND ep.user_id = ?
              LEFT JOIN users u ON u.id = e.pic_id";
    $where = "WHERE 1=1";
    $params[] = $uid;
}

if ($filterStatus) { $where .= " AND e.status = ?";      $params[] = $filterStatus; }
if ($filterLevel)  { $where .= " AND e.level = ?";       $params[] = $filterLevel; }
if ($search)       { $where .= " AND e.judul LIKE ?";    $params[] = "%$search%"; }

$sql = "SELECT DISTINCT e.*, u.nama AS nama_pic,
               (SELECT COUNT(*) FROM event_panitia ep2 WHERE ep2.event_id = e.id) AS jml_panitia
        FROM events e
        $join
        $where
        ORDER BY e.tanggal_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$statusLabel = ['draft'=>'Draft','pengajuan'=>'Diajukan','disetujui_manager'=>'Disetujui Manager',
  'proposal_dibuat'=>'Proposal Dibuat','rab_diajukan'=>'RAB Diajukan','perijinan'=>'Perijinan',
  'disetujui'=>'Disetujui','berlangsung'=>'Berlangsung','selesai'=>'Selesai','ditolak'=>'Ditolak'];
$statusPill = ['draft'=>'s-draft','pengajuan'=>'s-pengajuan','disetujui_manager'=>'s-pengajuan',
  'proposal_dibuat'=>'s-pengajuan','rab_diajukan'=>'s-pengajuan','perijinan'=>'s-pengajuan',
  'disetujui'=>'s-disetujui','berlangsung'=>'s-berlangsung','selesai'=>'s-selesai','ditolak'=>'s-ditolak'];
?>

<div class="page-header">
  <div>
    <h5>Semua Acara</h5>
    <div class="sub">
      <?= $role === 'superadmin' ? count($events).' acara total di sistem' : count($events).' acara yang kamu ikuti' ?>
    </div>
  </div>
  <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Buat Acara Baru
  </a>
</div>

<!-- Filter -->
<div class="filter-bar mb-4">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Cari Acara</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Nama acara..." value="<?= htmlspecialchars($search) ?>">
      </div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Level</label>
      <select name="level" class="form-select form-select-sm">
        <option value="">Semua Level</option>
        <?php foreach (['TK','SD','SMP','Umum'] as $lv): ?>
          <option value="<?= $lv ?>" <?= $filterLevel===$lv?'selected':'' ?>><?= $lv ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Semua Status</option>
        <?php foreach ($statusLabel as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $filterStatus===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
    </div>
  </form>
</div>

<!-- List Acara -->
<?php if (empty($events)): ?>
  <div class="empty-state">
    <i class="bi bi-calendar-x"></i>
    <h6 class="fw-700 mt-2">Tidak ada acara</h6>
    <p><?= $role==='superadmin' ? 'Belum ada acara di sistem.' : 'Kamu belum terlibat dalam acara apapun.' ?></p>
    <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary btn-sm mt-2">
      <i class="bi bi-plus me-1"></i> Buat Acara
    </a>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Nama Acara</th>
              <th>Level</th>
              <th>Tanggal</th>
              <th>PIC</th>
              <th>Panitia</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($events as $ev):
            $sisa = ceil((strtotime($ev['tanggal_mulai']) - time()) / 86400);
          ?>
            <tr>
              <td>
                <div class="fw-600"><?= htmlspecialchars($ev['judul']) ?></div>
                <?php if ($ev['lokasi']): ?>
                  <div class="fs-12 text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($ev['lokasi']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-secondary"><?= $ev['level'] ?></span></td>
              <td>
                <div class="fs-13"><?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?></div>
                <?php if ($ev['tanggal_mulai'] !== $ev['tanggal_selesai']): ?>
                  <div class="fs-12 text-muted">s/d <?= date('d M Y', strtotime($ev['tanggal_selesai'])) ?></div>
                <?php endif; ?>
                <?php if ($sisa >= 0 && $ev['status'] !== 'selesai'): ?>
                  <div class="fs-12 <?= $sisa<=3?'text-danger':($sisa<=7?'text-warning':'text-muted') ?>">
                    <?= $sisa===0?'Hari ini':'('.$sisa.' hari lagi)' ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="fs-13"><?= htmlspecialchars($ev['nama_pic'] ?? '—') ?></td>
              <td>
                <span class="badge bg-light text-dark border">
                  <i class="bi bi-people me-1"></i><?= $ev['jml_panitia'] ?>
                </span>
              </td>
              <td>
                <span class="status-pill <?= $statusPill[$ev['status']] ?>">
                  <?= $statusLabel[$ev['status']] ?>
                </span>
              </td>
              <td>
                <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $ev['id'] ?>"
                   class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-arrow-right-circle me-1"></i>Buka
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
