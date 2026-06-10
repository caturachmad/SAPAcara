<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../../includes/layout/header.php';
$uid  = $_SESSION['user_id'];

// Stats — gunakan prepared statements untuk mencegah SQL injection
$stmtPIC = $pdo->prepare("SELECT COUNT(*) FROM event_panitia WHERE user_id=? AND peran_acara='pic'");
$stmtPIC->execute([$uid]); $totalPIC = (int)$stmtPIC->fetchColumn();

$stmtPanitia = $pdo->prepare("SELECT COUNT(*) FROM event_panitia WHERE user_id=? AND peran_acara!='pic'");
$stmtPanitia->execute([$uid]); $totalPanitia = (int)$stmtPanitia->fetchColumn();

$stmtPending = $pdo->prepare("SELECT COUNT(*) FROM event_panitia WHERE user_id=? AND status_konfirmasi='pending'");
$stmtPending->execute([$uid]); $totalPending = (int)$stmtPending->fetchColumn();

$stmtUndangan = $pdo->prepare("SELECT ep.id, ep.peran_acara, ep.bagian, ep.token_expires_at, ep.created_at, e.judul, e.tanggal_mulai, e.tanggal_selesai, e.level, u.nama AS nama_pic FROM event_panitia ep JOIN events e ON e.id=ep.event_id LEFT JOIN users u ON u.id=e.pic_id WHERE ep.user_id=? AND ep.status_konfirmasi='pending' ORDER BY ep.created_at DESC");
$stmtUndangan->execute([$uid]);
$undanganPending = $stmtUndangan->fetchAll();
$stmtApproval = $pdo->prepare("SELECT COUNT(*) FROM approvals WHERE approver_id=? AND status='pending'");
$stmtApproval->execute([$uid]); $totalApproval = (int)$stmtApproval->fetchColumn();

$totalSDM    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='aktif'")->fetchColumn();
$totalEvents = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status NOT IN ('selesai','ditolak')")->fetchColumn();

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
  <?php if (hasPermission('buat_acara')): ?>
  <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Buat Acara
  </a>
  <?php endif; ?>
</div>

<!-- Undangan Pending -->
<?php if (!empty($undanganPending)): ?>
<div class="card border-warning mb-4">
  <div class="card-header d-flex align-items-center gap-2" style="background:#fffbeb;border-bottom:1px solid #fde68a">
    <i class="bi bi-envelope-exclamation-fill text-warning"></i>
    <span class="fw-700">Undangan Menunggu Konfirmasi</span>
    <span class="badge bg-warning text-dark ms-auto"><?= count($undanganPending) ?></span>
  </div>
  <div class="card-body p-0">
    <?php foreach ($undanganPending as $inv):
      $sisaHariInv = $inv["token_expires_at"] ? (int)ceil((strtotime($inv["token_expires_at"]) - time()) / 86400) : null;
      $expiredInv = $sisaHariInv !== null && $sisaHariInv < 0;
      $peranLabel = ["pic"=>"PIC","panitia_inti"=>"Panitia Inti","panitia_support"=>"Panitia Biasa"];
    ?>
    <div class="border-bottom p-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <div class="fw-600"><?= htmlspecialchars($inv["judul"]) ?></div>
          <div class="fs-12 text-muted mt-1">
            <span class="badge bg-secondary me-1"><?= $peranLabel[$inv["peran_acara"]] ?? $inv["peran_acara"] ?></span>
            <?php if ($inv["bagian"]): ?><span class="text-muted"><?= htmlspecialchars($inv["bagian"]) ?></span><?php endif; ?>
            · <?= date("d M Y", strtotime($inv["tanggal_mulai"])) ?>
            <?php if ($inv["nama_pic"]): ?> · PIC: <?= htmlspecialchars($inv["nama_pic"]) ?><?php endif; ?>
          </div>
          <?php if ($sisaHariInv !== null): ?>
          <div class="fs-12 mt-1 <?= $expiredInv ? "text-danger" : ($sisaHariInv <= 3 ? "text-warning fw-600" : "text-muted") ?>">
            <i class="bi bi-clock me-1"></i>
            <?php if ($expiredInv): ?>Link email kadaluarsa, konfirmasi langsung di sini
            <?php else: ?>Token email berakhir dalam <?= $sisaHariInv ?> hari
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
          <form method="POST" action="<?= BASE_URL ?>/modules/panitia/konfirmasi_web.php" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="panitia_id" value="<?= $inv["id"] ?>">
            <input type="hidden" name="jawab" value="bersedia">
            <button type="submit" class="btn btn-success btn-sm">
              <i class="bi bi-check-circle me-1"></i>Bersedia
            </button>
          </form>
          <button type="button" class="btn btn-outline-danger btn-sm"
            onclick="showTolakModal(<?= $inv["id"] ?>, <?= htmlspecialchars(json_encode($inv["judul"])) ?>)">
            <i class="bi bi-x-circle me-1"></i>Tolak
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

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
        <?php if (hasPermission('buat_acara')): ?>
        <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-sm btn-primary px-2 py-0">+</a>
        <?php endif; ?>
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
<!-- Modal Tolak Undangan -->
<div class="modal fade" id="tolakModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>Tolak Undangan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>/modules/panitia/konfirmasi_web.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="panitia_id" id="tolakPanitiaId" value="">
        <input type="hidden" name="jawab" value="tidak_bisa">
        <div class="modal-body">
          <p>Kamu akan menolak undangan untuk acara <strong id="tolakJudul"></strong>.</p>
          <div class="mb-3">
            <label class="form-label fw-600">Alasan tidak bisa hadir <span class="text-danger">*</span></label>
            <textarea name="alasan" class="form-control" rows="3" placeholder="cth: Ada acara keluarga, sedang tugas luar kota, dll." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Konfirmasi Tolak</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function showTolakModal(panitiaId, judul) {
  document.getElementById('tolakPanitiaId').value = panitiaId;
  document.getElementById('tolakJudul').textContent = judul;
  new bootstrap.Modal(document.getElementById('tolakModal')).show();
}
</script>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>