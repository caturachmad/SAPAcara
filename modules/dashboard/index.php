<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../../includes/layout/header.php';
$uid  = $_SESSION['user_id'];

// Stats
$totalPIC     = $pdo->query("SELECT COUNT(*) FROM event_panitia WHERE user_id=$uid AND peran_acara='pic'")->fetchColumn();
$totalPanitia = $pdo->query("SELECT COUNT(*) FROM event_panitia WHERE user_id=$uid AND peran_acara!='pic'")->fetchColumn();
$totalPending = $pdo->query("SELECT COUNT(*) FROM event_panitia WHERE user_id=$uid AND status_konfirmasi='pending'")->fetchColumn();
$totalApproval= $pdo->query("SELECT COUNT(*) FROM approvals WHERE approver_id=$uid AND status='pending'")->fetchColumn();
$totalSDM     = $pdo->query("SELECT COUNT(*) FROM users WHERE status='aktif'")->fetchColumn();
$totalEvents  = $pdo->query("SELECT COUNT(*) FROM events WHERE status NOT IN ('selesai','ditolak')")->fetchColumn();

// Chart: acara per level
$chartLevel = $pdo->query("SELECT level, COUNT(*) as total FROM events GROUP BY level")->fetchAll();
$chartStatus = $pdo->query("SELECT status, COUNT(*) as total FROM events GROUP BY status")->fetchAll();

// Acara mendatang
$upcoming = $pdo->query("
    SELECT e.*, u.nama AS nama_pic,
           (SELECT COUNT(*) FROM event_panitia ep WHERE ep.event_id=e.id) AS total_panitia
    FROM events e LEFT JOIN users u ON u.id=e.pic_id
    WHERE e.status NOT IN ('selesai','ditolak') AND e.tanggal_mulai >= CURDATE()
    ORDER BY e.tanggal_mulai ASC LIMIT 5
")->fetchAll();

// Acara saya
$acaraPIC = $pdo->prepare("
    SELECT e.*, ep.peran_acara FROM events e
    JOIN event_panitia ep ON ep.event_id=e.id AND ep.user_id=? AND ep.peran_acara='pic'
    ORDER BY e.tanggal_mulai DESC LIMIT 5
"); $acaraPIC->execute([$uid]); $picList = $acaraPIC->fetchAll();

$acaraGabung = $pdo->prepare("
    SELECT e.*, ep.bagian, ep.status_konfirmasi FROM events e
    JOIN event_panitia ep ON ep.event_id=e.id AND ep.user_id=? AND ep.peran_acara!='pic'
    ORDER BY e.tanggal_mulai DESC LIMIT 5
"); $acaraGabung->execute([$uid]); $gabungList = $acaraGabung->fetchAll();

// Aktivitas terbaru
$aktivitas = $pdo->query("
    SELECT ep.created_at, u.nama, e.judul, ep.peran_acara, ep.bagian
    FROM event_panitia ep
    JOIN users u ON u.id=ep.user_id
    JOIN events e ON e.id=ep.event_id
    ORDER BY ep.created_at DESC LIMIT 8
")->fetchAll();

$statusLabel = ['draft'=>'Draft','pengajuan'=>'Pengajuan','disetujui_manager'=>'Disetujui Manager',
  'proposal_dibuat'=>'Proposal','rab_diajukan'=>'RAB','perijinan'=>'Perijinan',
  'disetujui'=>'Disetujui','berlangsung'=>'Berlangsung','selesai'=>'Selesai','ditolak'=>'Ditolak'];
$statusClass = ['draft'=>'status-draft','pengajuan'=>'status-pengajuan','disetujui_manager'=>'status-pengajuan',
  'proposal_dibuat'=>'status-pengajuan','rab_diajukan'=>'status-pengajuan','perijinan'=>'status-pengajuan',
  'disetujui'=>'status-disetujui','berlangsung'=>'status-berlangsung','selesai'=>'status-selesai','ditolak'=>'status-ditolak'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h5 class="fw-bold mb-0">Selamat datang, <?= htmlspecialchars($user['nama']) ?>! 👋</h5>
    <small class="text-muted"><?= date('l, d F Y') ?></small>
  </div>
  <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Buat Acara
  </a>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-2">
    <div class="stat-card" style="background:linear-gradient(135deg,#1E3A5F,#2E86C1)">
      <div><div class="stat-num"><?= $totalPIC ?></div><div class="stat-label">PIC Acara</div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="stat-card" style="background:linear-gradient(135deg,#2ECC71,#1abc9c)">
      <div><div class="stat-num"><?= $totalPanitia ?></div><div class="stat-label">Ikut Panitia</div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="stat-card" style="background:linear-gradient(135deg,#F39C12,#e67e22)">
      <div><div class="stat-num"><?= $totalPending ?></div><div class="stat-label">Konfirmasi</div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="stat-card" style="background:linear-gradient(135deg,#E74C3C,#c0392b)">
      <div><div class="stat-num"><?= $totalApproval ?></div><div class="stat-label">Approval Masuk</div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="stat-card" style="background:linear-gradient(135deg,#8e44ad,#9b59b6)">
      <div><div class="stat-num"><?= $totalEvents ?></div><div class="stat-label">Acara Aktif</div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="stat-card" style="background:linear-gradient(135deg,#16a085,#1abc9c)">
      <div><div class="stat-num"><?= $totalSDM ?></div><div class="stat-label">Total SDM</div></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <!-- Chart Level -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Acara per Level</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="chartLevel" height="200"></canvas>
      </div>
    </div>
  </div>
  <!-- Chart Status -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Acara per Status</div>
      <div class="card-body">
        <canvas id="chartStatus" height="200"></canvas>
      </div>
    </div>
  </div>
  <!-- Acara Mendatang -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calendar-week me-2"></i>Acara Mendatang</div>
      <div class="card-body p-0">
        <?php if (empty($upcoming)): ?>
          <div class="text-center text-muted py-4 small">Tidak ada acara mendatang</div>
        <?php else: ?>
          <?php foreach ($upcoming as $ev):
            $sisa = ceil((strtotime($ev['tanggal_mulai']) - time()) / 86400);
          ?>
          <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $ev['id'] ?>"
             class="d-block px-3 py-2 border-bottom text-decoration-none text-dark">
            <div class="d-flex justify-content-between align-items-start">
              <div class="flex-grow-1">
                <div class="small fw-semibold"><?= htmlspecialchars($ev['judul']) ?></div>
                <div style="font-size:.72rem" class="text-muted">
                  <?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?> ·
                  <?= $ev['total_panitia'] ?> panitia
                </div>
              </div>
              <span class="badge <?= $sisa <= 3 ? 'bg-danger' : ($sisa <= 7 ? 'bg-warning text-dark' : 'bg-primary') ?> ms-2">
                <?= $sisa === 0 ? 'Hari ini' : ($sisa < 0 ? 'Lewat' : "$sisa hr") ?>
              </span>
            </div>
          </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Acara Saya -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-person-badge me-2"></i>PIC Acara</span>
        <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-sm btn-primary px-2 py-0">+</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($picList)): ?>
          <div class="text-center text-muted py-3 small">Belum ada acara</div>
        <?php else: ?>
          <?php foreach ($picList as $ev): ?>
            <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $ev['id'] ?>"
               class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom text-decoration-none text-dark">
              <div>
                <div class="small fw-semibold"><?= htmlspecialchars($ev['judul']) ?></div>
                <span class="badge bg-secondary" style="font-size:.65rem"><?= $ev['level'] ?></span>
              </div>
              <span class="badge-status <?= $statusClass[$ev['status']] ?>" style="font-size:.7rem"><?= $statusLabel[$ev['status']] ?></span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Acara Diikuti -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-people me-2"></i>Acara Diikuti</div>
      <div class="card-body p-0">
        <?php if (empty($gabungList)): ?>
          <div class="text-center text-muted py-3 small">Belum terdaftar</div>
        <?php else: ?>
          <?php foreach ($gabungList as $ev):
            $kc=['pending'=>'warning','bersedia'=>'success','tidak_bisa'=>'danger'];
          ?>
            <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $ev['id'] ?>"
               class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom text-decoration-none text-dark">
              <div>
                <div class="small fw-semibold"><?= htmlspecialchars($ev['judul']) ?></div>
                <div style="font-size:.72rem" class="text-muted"><?= htmlspecialchars($ev['bagian'] ?? '-') ?></div>
              </div>
              <span class="badge bg-<?= $kc[$ev['status_konfirmasi']] ?>" style="font-size:.65rem"><?= $ev['status_konfirmasi'] ?></span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Aktivitas Terbaru -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-activity me-2"></i>Aktivitas Terbaru</div>
      <div class="card-body p-0">
        <?php foreach ($aktivitas as $a): ?>
          <div class="d-flex gap-2 px-3 py-2 border-bottom align-items-start">
            <div class="avatar mt-1" style="width:28px;height:28px;background:#2E86C1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;flex-shrink:0">
              <?= strtoupper(substr($a['nama'],0,2)) ?>
            </div>
            <div>
              <div style="font-size:.8rem"><strong><?= htmlspecialchars($a['nama']) ?></strong>
                ditugaskan ke <em><?= htmlspecialchars($a['judul']) ?></em>
                <?php if ($a['bagian']): ?> sebagai <?= htmlspecialchars($a['bagian']) ?><?php endif; ?>
              </div>
              <div style="font-size:.7rem" class="text-muted"><?= date('d M H:i', strtotime($a['created_at'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php
// Data untuk chart
$levelLabels = array_column($chartLevel, 'level');
$levelData   = array_column($chartLevel, 'total');
$statusLabelsChart = array_map(fn($r) => $statusLabel[$r['status']] ?? $r['status'], $chartStatus);
$statusData  = array_column($chartStatus, 'total');
$extraJs = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById("chartLevel"), {
  type: "doughnut",
  data: {
    labels: ' . json_encode($levelLabels) . ',
    datasets: [{ data: ' . json_encode($levelData) . ',
      backgroundColor: ["#1E3A5F","#2E86C1","#2ECC71","#F39C12"],
      borderWidth: 2, borderColor: "#fff" }]
  },
  options: { plugins: { legend: { position: "bottom", labels: { font: { size: 11 } } } }, cutout: "65%" }
});
new Chart(document.getElementById("chartStatus"), {
  type: "bar",
  data: {
    labels: ' . json_encode($statusLabelsChart) . ',
    datasets: [{ data: ' . json_encode($statusData) . ',
      backgroundColor: "#2E86C1", borderRadius: 6 }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { ticks: { font: { size: 10 } } } }
  }
});
</script>';
?>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
