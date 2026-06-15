<?php
$pageTitle = 'Semua Acara';
require_once __DIR__ . '/../../includes/layout/header.php';

$uid = $_SESSION['user_id'];
$role = $user['role_sistem'] ?? 'staff';
$isSuperAdmin = isSuperAdmin();

$allowedStatuses = [
    'draft' => 'Draft',
    'pengajuan' => 'Diajukan',
    'disetujui_manager' => 'Disetujui Manager',
    'proposal_dibuat' => 'Proposal Dibuat',
    'rab_diajukan' => 'RAB Diajukan',
    'perijinan' => 'Perijinan',
    'disetujui' => 'Disetujui',
    'berlangsung' => 'Berlangsung',
    'selesai' => 'Selesai',
    'ditolak' => 'Ditolak',
];
$statusPill = [
    'draft' => 's-draft',
    'pengajuan' => 's-pengajuan',
    'disetujui_manager' => 's-pengajuan',
    'proposal_dibuat' => 's-pengajuan',
    'rab_diajukan' => 's-pengajuan',
    'perijinan' => 's-pengajuan',
    'disetujui' => 's-disetujui',
    'berlangsung' => 's-berlangsung',
    'selesai' => 's-selesai',
    'ditolak' => 's-ditolak',
];
$allowedLevels = ['TK', 'SD', 'SMP', 'Umum'];

$filterStatus = $_GET['status'] ?? '';
$filterLevel = $_GET['level'] ?? '';
$search = trim((string)($_GET['q'] ?? ''));

if (!isset($allowedStatuses[$filterStatus])) {
    $filterStatus = '';
}
if (!in_array($filterLevel, $allowedLevels, true)) {
    $filterLevel = '';
}

$conditions = [];
$params = [];

if (!$isSuperAdmin) {
    $conditions[] = 'EXISTS (SELECT 1 FROM event_panitia ep WHERE ep.event_id = e.id AND ep.user_id = ?)';
    $params[] = $uid;
}
if ($filterStatus !== '') {
    $conditions[] = 'e.status = ?';
    $params[] = $filterStatus;
}
if ($filterLevel !== '') {
    $conditions[] = 'e.level = ?';
    $params[] = $filterLevel;
}
if ($search !== '') {
    $conditions[] = 'e.judul LIKE ?';
    $params[] = "%{$search}%";
}

$whereClause = '';
if ($conditions !== []) {
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 20;
$totalPages = 1;
$queryParams = [
    'status' => $filterStatus,
    'level' => $filterLevel,
    'q' => $search,
];

$totalEvents = 0;
$events = [];
$queryError = '';

try {
    $countSql = "SELECT COUNT(*) FROM events e
                 LEFT JOIN users u ON u.id = e.pic_id
                 {$whereClause}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalEvents = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalEvents / $pageSize));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $pageSize;

    $eventSql = "SELECT e.id, e.judul, e.level, e.tanggal_mulai, e.tanggal_selesai,
                        e.lokasi, e.status, e.pic_id,
                        u.nama AS nama_pic,
                        (SELECT COUNT(*) FROM event_panitia ep2 WHERE ep2.event_id = e.id) AS jml_panitia,
                        (SELECT COUNT(*) FROM event_files ef WHERE ef.event_id = e.id AND ef.file_type IN ('proposal','rab')) AS docs_count
                 FROM events e
                 LEFT JOIN users u ON u.id = e.pic_id
                 {$whereClause}
                 ORDER BY e.tanggal_mulai DESC
                 LIMIT ? OFFSET ?";

    $eventStmt = $pdo->prepare($eventSql);
    $eventParams = array_merge($params, [$pageSize, $offset]);
    $eventStmt->execute($eventParams);
    $events = $eventStmt->fetchAll();
} catch (PDOException $e) {
    error_log('[events/index] ' . $e->getMessage());
    $queryError = 'Gagal memuat daftar acara. Silakan coba lagi nanti.';
}

function getDaysUntilStart(string $dateString): ?int {
    try {
        $eventDate = new DateTimeImmutable($dateString);
        $today = new DateTimeImmutable('today');
        return (int)$today->diff($eventDate)->format('%r%a');
    } catch (Exception $e) {
        return null;
    }
}

function getStatusLabel(string $status, array $map): string {
    return $map[$status] ?? 'Tidak diketahui';
}

function getNextTask(string $status, int $docsCount, int $panitiaCount): string {
    return match ($status) {
        'draft' => $docsCount > 0 ? 'Ajukan ke manager' : 'Upload proposal/RAB',
        'pengajuan' => 'Tunggu keputusan manager',
        'disetujui_manager' => 'Upload RAB atau dokumen pendukung',
        'proposal_dibuat' => 'Upload RAB',
        'rab_diajukan' => 'Tunggu approval bendahara',
        'perijinan' => 'Pantau proses perijinan',
        'disetujui' => $panitiaCount > 1 ? 'Undang panitia & siapkan acara' : 'Undang panitia',
        'berlangsung' => 'Pantau acara & dokumentasi',
        'selesai' => 'Acara selesai',
        'ditolak' => 'Revisi dokumen & ajukan kembali',
        default => 'Periksa detail acara',
    };
}

function getStatusPillClass(string $status, array $map): string {
    return $map[$status] ?? 's-draft';
}
?>

<?php $isAdminOrSA = isAdmin() || isSuperAdmin(); ?>

<div class="page-header">
  <div>
    <h5>Semua Acara</h5>
    <div class="sub">
      <?= $role === 'superadmin' ? $totalEvents.' acara total di sistem' : $totalEvents.' acara yang kamu ikuti' ?>
    </div>
  </div>
  <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Buat Acara Baru
  </a>
</div>

<!-- PR-05: Tabs: Aktif / Arsip -->
<?php if ($isAdminOrSA): ?>
<ul class="nav nav-tabs mb-4" id="eventIndexTabs">
  <li class="nav-item">
    <a class="nav-link <?= (!isset($_GET['tab']) || $_GET['tab'] !== 'arsip') ? 'active' : '' ?>"
       href="?<?= http_build_query(array_merge($_GET, ['tab' => 'aktif'])) ?>">
      <i class="bi bi-calendar-event me-1"></i>Akan Datang / Berjalan
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= (($_GET['tab'] ?? '') === 'arsip') ? 'active' : '' ?>"
       href="?<?= http_build_query(array_merge($_GET, ['tab' => 'arsip'])) ?>">
      <i class="bi bi-archive me-1"></i>Arsip Selesai
    </a>
  </li>
</ul>
<?php endif; ?>

<?php if ($queryError): ?>
  <div class="alert alert-danger mb-4" role="alert">
    <?= htmlspecialchars($queryError) ?>
  </div>
<?php endif; ?>

<?php
// PR-05: Arsip tab (selesai events) for admin
if ($isAdminOrSA && ($_GET['tab'] ?? '') === 'arsip'):
    // Query arsip (selesai events)
    $arsipConditions = [];
    $arsipParams = [];
    $arsipConditions[] = "e.status = 'selesai'";
    if ($filterLevel !== '') {
        $arsipConditions[] = 'e.level = ?';
        $arsipParams[] = $filterLevel;
    }
    if ($search !== '') {
        $arsipConditions[] = 'e.judul LIKE ?';
        $arsipParams[] = "%{$search}%";
    }
    $arsipWhere = 'WHERE ' . implode(' AND ', $arsipConditions);
    $arsipStmt = $pdo->prepare("
        SELECT e.id, e.judul, e.level, e.tanggal_mulai, e.tanggal_selesai, e.is_template, e.template_notes,
               u.nama AS nama_pic,
               (SELECT COUNT(*) FROM event_panitia ep WHERE ep.event_id=e.id) AS jml_panitia,
               (SELECT COUNT(*) FROM event_swot sw WHERE sw.event_id=e.id) AS jml_swot
        FROM events e LEFT JOIN users u ON u.id=e.pic_id
        $arsipWhere
        ORDER BY e.tanggal_selesai DESC
        LIMIT 50
    ");
    $arsipStmt->execute($arsipParams);
    $arsipEvents = $arsipStmt->fetchAll();
?>
<!-- Filter untuk arsip -->
<div class="filter-bar mb-4">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="arsip">
    <div class="col-md-5">
      <label class="form-label">Cari Arsip</label>
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
    <div class="col-md-auto d-flex gap-2">
      <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="?tab=arsip" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
    </div>
  </form>
</div>

<?php if (empty($arsipEvents)): ?>
  <div class="empty-state"><i class="bi bi-archive"></i><p>Tidak ada acara selesai.</p></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($arsipEvents as $arc): ?>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="badge bg-secondary"><?= $arc['level'] ?></span>
          <?php if ($arc['is_template']): ?>
            <span class="badge bg-warning text-dark"><i class="bi bi-bookmark-star me-1"></i>Template</span>
          <?php endif; ?>
        </div>
        <div class="fw-700 fs-13 mb-1"><?= htmlspecialchars($arc['judul']) ?></div>
        <div class="fs-12 text-muted mb-2">
          PIC: <?= htmlspecialchars($arc['nama_pic'] ?? '—') ?><br>
          <?= date('d M Y', strtotime($arc['tanggal_mulai'])) ?>
          <?= $arc['tanggal_mulai'] !== $arc['tanggal_selesai'] ? ' – ' . date('d M Y', strtotime($arc['tanggal_selesai'])) : '' ?>
        </div>
        <div class="d-flex gap-2 fs-12 text-muted mb-3">
          <span><i class="bi bi-people me-1"></i><?= $arc['jml_panitia'] ?> panitia</span>
          <span><i class="bi bi-clipboard-check me-1"></i><?= $arc['jml_swot'] ?> evaluasi</span>
        </div>
        <div class="d-flex gap-2">
          <a href="<?= BASE_URL ?>/modules/events/archive.php?id=<?= $arc['id'] ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
            <i class="bi bi-archive me-1"></i>Lihat Arsip
          </a>
          <!-- Toggle template -->
          <form method="POST" action="<?= BASE_URL ?>/modules/events/archive.php?id=<?= $arc['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="toggle_template" value="1">
            <button type="submit" class="btn btn-sm <?= $arc['is_template'] ? 'btn-warning' : 'btn-outline-warning' ?>"
                    title="<?= $arc['is_template'] ? 'Lepas template' : 'Tandai sebagai template' ?>">
              <i class="bi bi-bookmark<?= $arc['is_template'] ? '-star-fill' : '-star' ?>"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: // Tampilkan tab aktif (default) ?>

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
        <?php foreach ($allowedStatuses as $k => $v): ?>
          <option value="<?= htmlspecialchars($k) ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
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
              <th>Tindakan Selanjutnya</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($events as $ev):
            $sisa = getDaysUntilStart((string)($ev['tanggal_mulai'] ?? ''));
            $level = htmlspecialchars($ev['level'] ?? '');
            $picName = htmlspecialchars($ev['nama_pic'] ?? '—');
            $statusKey = $ev['status'] ?? '';
          ?>
            <tr>
              <td>
                <div class="fw-600"><?= htmlspecialchars($ev['judul'] ?? '') ?></div>
                <?php if (!empty($ev['lokasi'])): ?>
                  <div class="fs-12 text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($ev['lokasi']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-secondary"><?= $level ?></span></td>
              <td>
                <div class="fs-13"><?= htmlspecialchars(date('d M Y', strtotime($ev['tanggal_mulai'] ?? ''))) ?></div>
                <?php if (!empty($ev['tanggal_mulai']) && ($ev['tanggal_mulai'] !== ($ev['tanggal_selesai'] ?? ''))): ?>
                  <div class="fs-12 text-muted">s/d <?= htmlspecialchars(date('d M Y', strtotime($ev['tanggal_selesai'] ?? ''))) ?></div>
                <?php endif; ?>
                <?php if ($sisa !== null && $sisa >= 0 && $statusKey !== 'selesai'): ?>
                  <div class="fs-12 <?= $sisa <= 3 ? 'text-danger' : ($sisa <= 7 ? 'text-warning' : 'text-muted') ?>">
                    <?= $sisa === 0 ? 'Hari ini' : '(' . $sisa . ' hari lagi)' ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="fs-13"><?= $picName ?></td>
              <td>
                <span class="badge bg-light text-dark border">
                  <i class="bi bi-people me-1"></i><?= (int)($ev['jml_panitia'] ?? 0) ?>
                </span>
              </td>
              <td>
                <div class="text-muted fs-12"><?= htmlspecialchars(getNextTask($statusKey, (int)$ev['docs_count'], (int)$ev['jml_panitia'])) ?></div>
              </td>
              <td>
                <span class="status-pill <?= htmlspecialchars(getStatusPillClass($statusKey, $statusPill)) ?>">
                  <?= htmlspecialchars(getStatusLabel($statusKey, $allowedStatuses)) ?>
                </span>
              </td>
              <td>
                <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= urlencode((string)($ev['id'] ?? '')) ?>"
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
  <?php if ($totalPages > 1): ?>
    <nav aria-label="Pagination" class="px-3 py-2">
      <ul class="pagination justify-content-end mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($queryParams, ['page' => $page - 1]))) ?>" tabindex="-1">Sebelumnya</a>
        </li>
        <?php
          $start = max(1, $page - 2);
          $end = min($totalPages, $page + 2);
          if ($start > 1):
        ?>
          <li class="page-item"><a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($queryParams, ['page' => 1]))) ?>">1</a></li>
          <?php if ($start > 2): ?>
            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          <?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($queryParams, ['page' => $i]))) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
          <?php if ($end < $totalPages - 1): ?>
            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          <?php endif; ?>
          <li class="page-item"><a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($queryParams, ['page' => $totalPages]))) ?>"><?= $totalPages ?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($queryParams, ['page' => $page + 1]))) ?>">Berikutnya</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php endif; // end aktif/arsip tab ?>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
