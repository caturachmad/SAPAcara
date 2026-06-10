<?php
$pageTitle = 'Manajemen Panitia';
require_once __DIR__ . '/../../includes/layout/header.php';

$uid  = $_SESSION['user_id'];
$role = $user['role_sistem'];

// Filter
$filterEvent  = (int)($_GET['event_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$search       = $_GET['q'] ?? '';

// Ambil acara yang bisa dilihat user
if ($role === 'superadmin') {
    $events = $pdo->query("SELECT id, judul, level, tanggal_mulai FROM events ORDER BY tanggal_mulai DESC")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.id, e.judul, e.level, e.tanggal_mulai FROM events e
        JOIN event_panitia ep ON ep.event_id = e.id
        WHERE ep.user_id = ? AND (ep.peran_acara = 'pic' OR ep.is_event_admin = 1)
        ORDER BY e.tanggal_mulai DESC
    ");
    $stmt->execute([$uid]);
    $events = $stmt->fetchAll();
}

// Query panitia
$where = ['1=1']; $params = [];
if ($filterEvent)  { $where[] = 'ep.event_id = ?'; $params[] = $filterEvent; }
if ($filterStatus) { $where[] = 'ep.status_konfirmasi = ?'; $params[] = $filterStatus; }
if ($search)       { $where[] = '(u.nama LIKE ? OR u.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

// Batasi akses non-superadmin
if ($role !== 'superadmin') {
    $where[] = 'ep.event_id IN (
        SELECT event_id FROM event_panitia WHERE user_id = ? AND (peran_acara="pic" OR is_event_admin=1)
    )';
    $params[] = $uid;
}

$stmt = $pdo->prepare("
    SELECT ep.*, u.nama, u.email, u.divisi, u.jabatan,
           e.judul AS nama_event, e.level, e.tanggal_mulai, e.tanggal_selesai, e.status AS event_status
    FROM event_panitia ep
    JOIN users u ON u.id = ep.user_id
    JOIN events e ON e.id = ep.event_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.tanggal_mulai DESC, ep.peran_acara, u.nama
");
$stmt->execute($params);
$list = $stmt->fetchAll();

// Stats
$totalBersedia  = count(array_filter($list, fn($r) => $r['status_konfirmasi'] === 'bersedia'));
$totalPending   = count(array_filter($list, fn($r) => $r['status_konfirmasi'] === 'pending'));
$totalTidakBisa = count(array_filter($list, fn($r) => $r['status_konfirmasi'] === 'tidak_bisa'));

$peranLabel = ['pic'=>'PIC','panitia_inti'=>'Panitia','panitia_support'=>'Panitia'];
$peranColor = ['pic'=>'bg-primary','panitia_inti'=>'bg-secondary','panitia_support'=>'bg-secondary'];
?>

<div class="page-header">
  <div>
    <h5>Manajemen Panitia</h5>
    <div class="sub"><?= count($list) ?> penugasan ditemukan</div>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#1a3a5c,#245a8a)">
      <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
      <div><div class="stat-num"><?= count($list) ?></div><div class="stat-label">Total Penugasan</div></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#059669,#10b981)">
      <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
      <div><div class="stat-num"><?= $totalBersedia ?></div><div class="stat-label">Bersedia</div></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#d97706,#f59e0b)">
      <div class="stat-icon"><i class="bi bi-clock-fill"></i></div>
      <div><div class="stat-num"><?= $totalPending ?></div><div class="stat-label">Menunggu Konfirmasi</div></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#dc2626,#ef4444)">
      <div class="stat-icon"><i class="bi bi-x-circle-fill"></i></div>
      <div><div class="stat-num"><?= $totalTidakBisa ?></div><div class="stat-label">Tidak Bisa</div></div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="filter-bar mb-4">
  <form method="GET" class="row g-2 align-items-center">
    <div class="col-md-3">
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Cari nama SDM..." value="<?= htmlspecialchars($search) ?>">
      </div>
    </div>
    <div class="col-md-3">
      <select name="event_id" class="form-select form-select-sm">
        <option value="">Semua Acara</option>
        <?php foreach ($events as $ev): ?>
          <option value="<?= $ev['id'] ?>" <?= $filterEvent==$ev['id']?'selected':'' ?>>
            <?= htmlspecialchars($ev['judul']) ?> (<?= $ev['level'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select form-select-sm">
        <option value="">Semua Status</option>
        <option value="pending"    <?= $filterStatus==='pending'?'selected':'' ?>>Menunggu</option>
        <option value="bersedia"   <?= $filterStatus==='bersedia'?'selected':'' ?>>Bersedia</option>
        <option value="tidak_bisa" <?= $filterStatus==='tidak_bisa'?'selected':'' ?>>Tidak Bisa</option>
      </select>
    </div>
    <div class="col-md-auto d-flex gap-2">
      <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
    </div>
  </form>
</div>

<!-- Table -->
<div class="card">
  <div class="card-body p-0">
    <?php if (empty($list)): ?>
      <div class="empty-state"><i class="bi bi-people"></i><p>Tidak ada data panitia ditemukan</p></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Nama SDM</th>
            <th>Acara</th>
            <th>Peran</th>
            <th>Bagian</th>
            <th>Konfirmasi</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $p):
          $kc = ['pending'=>['warning','Menunggu'],'bersedia'=>['success','Bersedia'],'tidak_bisa'=>['danger','Tidak Bisa']];
          [$kcColor, $kcLabel] = $kc[$p['status_konfirmasi']];
        ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm"><?= strtoupper(substr($p['nama'],0,2)) ?></div>
                <div>
                  <div class="fw-600"><?= htmlspecialchars($p['nama']) ?></div>
                  <div class="fs-12" style="color:var(--text-muted)"><?= htmlspecialchars($p['divisi'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td>
              <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $p['event_id'] ?>" class="fw-500 text-decoration-none">
                <?= htmlspecialchars($p['nama_event']) ?>
              </a>
              <div class="fs-12" style="color:var(--text-muted)"><?= date('d M Y', strtotime($p['tanggal_mulai'])) ?></div>
            </td>
            <td>
              <span class="badge <?= $p['peran_acara'] === 'pic' ? 'bg-primary' : 'bg-secondary' ?>">
                <?= $p['peran_acara'] === 'pic'
                    ? 'PIC' . ($p['bagian'] ? ' + ' . htmlspecialchars($p['bagian']) : '')
                    : ($p['bagian'] ? htmlspecialchars($p['bagian']) : 'Panitia') ?>
              </span>
              <?php if ($p['is_event_admin']): ?>
                <span class="badge bg-warning text-dark ms-1" title="Event Admin"><i class="bi bi-star-fill"></i></span>
              <?php endif; ?>
              <?php if ($p['is_double_job']): ?>
                <span class="badge bg-light text-dark ms-1 border">Double</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['bagian'] ?? '—') ?></td>
            <td>
              <span class="status-pill <?= $kcColor==='warning'?'s-berlangsung':($kcColor==='success'?'s-disetujui':'s-ditolak') ?>">
                <?= $kcLabel ?>
              </span>
            </td>
            <td>
              <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $p['event_id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="Lihat Acara">
                <i class="bi bi-eye"></i>
              </a>
              <?php if ($role === 'superadmin' || isPIC($p['event_id'], $pdo) || isEventAdmin($p['event_id'], $pdo)): ?>
                <a href="<?= BASE_URL ?>/modules/panitia/assign.php?event_id=<?= $p['event_id'] ?>"
                   class="btn btn-sm btn-outline-success" title="Tambah Panitia">
                  <i class="bi bi-person-plus"></i>
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
