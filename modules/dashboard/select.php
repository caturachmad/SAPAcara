<?php
$pageTitle = 'Beranda';
require_once __DIR__ . '/../../includes/layout/header.php';

$uid  = $_SESSION['user_id'];
$role = $user['role_sistem'];

/* ── Stats untuk superadmin ── */
if ($role === 'superadmin') {
    $totalSDM    = $pdo->query("SELECT COUNT(*) FROM users WHERE status='aktif'")->fetchColumn();
    $totalEvents = $pdo->query("SELECT COUNT(*) FROM events WHERE status NOT IN ('selesai','ditolak')")->fetchColumn();
    $totalApprv  = $pdo->query("SELECT COUNT(*) FROM approvals WHERE status='pending'")->fetchColumn();
    $totalSelesai= $pdo->query("SELECT COUNT(*) FROM events WHERE status='selesai'")->fetchColumn();
}

/* ── Acara sebagai PIC ── */
$stmtPIC = $pdo->prepare("
    SELECT e.*, ep.peran_acara,
           DATEDIFF(e.tanggal_mulai, CURDATE()) AS hari_lagi,
           (SELECT COUNT(*) FROM event_panitia x WHERE x.event_id=e.id) AS jml_panitia,
           (SELECT COUNT(*) FROM event_panitia x WHERE x.event_id=e.id AND x.status_konfirmasi='pending') AS jml_pending
    FROM events e JOIN event_panitia ep ON ep.event_id=e.id AND ep.user_id=? AND ep.peran_acara='pic'
    WHERE e.status NOT IN ('selesai','ditolak')
    ORDER BY e.tanggal_mulai ASC
");
$stmtPIC->execute([$uid]); $asPIC = $stmtPIC->fetchAll();

/* ── Acara sebagai panitia ── */
$stmtPan = $pdo->prepare("
    SELECT e.*, ep.peran_acara, ep.bagian, ep.is_event_admin, ep.status_konfirmasi,
           DATEDIFF(e.tanggal_mulai, CURDATE()) AS hari_lagi
    FROM events e JOIN event_panitia ep ON ep.event_id=e.id AND ep.user_id=? AND ep.peran_acara!='pic'
    WHERE e.status NOT IN ('ditolak')
    ORDER BY e.status='selesai' ASC, e.tanggal_mulai ASC
");
$stmtPan->execute([$uid]); $asPanitia = $stmtPan->fetchAll();

/* ── Approval pending user ini (superadmin) ── */
$myApproval = 0;
if ($role === 'superadmin' || $role === 'admin') {
    $ap = $pdo->prepare("SELECT COUNT(*) FROM approvals WHERE approver_id=? AND status='pending'");
    $ap->execute([$uid]); $myApproval = (int)$ap->fetchColumn();
}

$statusLabel = ['draft'=>'Draft','pengajuan'=>'Diajukan','disetujui_manager'=>'Disetujui Manager',
  'proposal_dibuat'=>'Proposal','rab_diajukan'=>'RAB','perijinan'=>'Perijinan',
  'disetujui'=>'Disetujui','berlangsung'=>'Berlangsung','selesai'=>'Selesai','ditolak'=>'Ditolak'];

function hariChip(int $h): string {
    if ($h<0)  return '<span class="badge bg-secondary">Selesai</span>';
    if ($h===0) return '<span class="badge bg-danger">Hari ini!</span>';
    if ($h<=3)  return "<span class='badge bg-danger'>$h hari lagi</span>";
    if ($h<=7)  return "<span class='badge bg-warning text-dark'>$h hari lagi</span>";
    return "<span class='badge bg-primary'>$h hari lagi</span>";
}
?>

<!-- ══════════════════════════════════════════
     SUPERADMIN VIEW
     ══════════════════════════════════════════ -->
<?php if ($role === 'superadmin'): ?>

<!-- Role Banner -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge bg-danger py-1 px-2">Super Admin</span>
      <span class="fs-12 text-muted">Akses penuh ke seluruh sistem</span>
    </div>
    <h5 class="fw-800 mb-0">Halo, <?= htmlspecialchars(explode(' ',$user['nama'])[0]) ?>! 👋</h5>
    <div class="fs-12 text-muted"><?= date('l, d F Y') ?></div>
  </div>
  <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Buat Acara Baru
  </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <a href="<?= BASE_URL ?>/modules/users/" class="text-decoration-none">
      <div class="stat-card h-100" style="background:linear-gradient(135deg,#1a3a5c,#245a8a)">
        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
        <div><div class="stat-num"><?= $totalSDM ?></div><div class="stat-label">Total SDM Aktif</div></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="<?= BASE_URL ?>/modules/events/" class="text-decoration-none">
      <div class="stat-card h-100" style="background:linear-gradient(135deg,#0891b2,#06b6d4)">
        <div class="stat-icon"><i class="bi bi-calendar-event-fill"></i></div>
        <div><div class="stat-num"><?= $totalEvents ?></div><div class="stat-label">Acara Berjalan</div></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="<?= BASE_URL ?>/modules/approvals/" class="text-decoration-none">
      <div class="stat-card h-100" style="background:linear-gradient(135deg,#d97706,#f59e0b)">
        <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
        <div><div class="stat-num"><?= $totalApprv ?></div><div class="stat-label">Approval Pending</div></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="<?= BASE_URL ?>/modules/events/?status=selesai" class="text-decoration-none">
      <div class="stat-card h-100" style="background:linear-gradient(135deg,#059669,#10b981)">
        <div class="stat-icon"><i class="bi bi-flag-fill"></i></div>
        <div><div class="stat-num"><?= $totalSelesai ?></div><div class="stat-label">Acara Selesai</div></div>
      </div>
    </a>
  </div>
</div>

<!-- Quick Access Admin -->
<div class="row g-3 mb-4">
  <div class="col-12"><h6 class="fw-700 text-muted mb-0">⚡ Akses Cepat Admin</h6></div>
  <?php
  $quickLinks = [
    [BASE_URL.'/modules/approvals/',      'check2-circle',     '#245a8a','Approval Acara',  "Review $totalApprv pengajuan"],
    [BASE_URL.'/modules/users/',           'person-gear',       '#0891b2','Manajemen SDM',   'Kelola data seluruh SDM'],
    [BASE_URL.'/modules/users/import.php', 'file-earmark-excel','#059669','Import Excel',    'Upload data SDM massal'],
    [BASE_URL.'/modules/events/',          'calendar3',         '#7c3aed','Semua Acara',      'Lihat & kelola semua acara'],
  ];
  foreach ($quickLinks as [$url,$icon,$color,$label,$sub]):
  ?>
  <div class="col-6 col-md-3">
    <a href="<?= $url ?>" class="card h-100 text-decoration-none text-dark" style="transition:.2s"
       onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='<?= $color ?>'"
       onmouseout="this.style.transform='';this.style.borderColor=''">
      <div class="card-body text-center py-3">
        <i class="bi bi-<?= $icon ?> fs-2 d-block mb-2" style="color:<?= $color ?>"></i>
        <div class="fw-700 fs-13"><?= $label ?></div>
        <div class="fs-12 text-muted"><?= $sub ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<hr class="my-3">
<h6 class="fw-700 mb-3">📋 Acara Aktif Saat Ini</h6>

<?php
// Semua acara aktif (superadmin bisa lihat semua)
$allEvents = $pdo->query("
    SELECT e.*, u.nama AS nama_pic,
           (SELECT COUNT(*) FROM event_panitia ep WHERE ep.event_id=e.id) AS jml_panitia,
           DATEDIFF(e.tanggal_mulai, CURDATE()) AS hari_lagi
    FROM events e LEFT JOIN users u ON u.id=e.pic_id
    WHERE e.status NOT IN ('selesai','ditolak')
    ORDER BY e.tanggal_mulai ASC LIMIT 12
")->fetchAll();
?>

<?php if (empty($allEvents)): ?>
  <div class="empty-state"><i class="bi bi-calendar-x"></i><p>Belum ada acara aktif</p></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($allEvents as $ev): ?>
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $ev['id'] ?>"
       class="card h-100 text-decoration-none text-dark"
       onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.1)'"
       onmouseout="this.style.transform='';this.style.boxShadow=''">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="badge bg-secondary"><?= $ev['level'] ?></span>
          <?= hariChip((int)$ev['hari_lagi']) ?>
        </div>
        <div class="fw-700 fs-13 mb-1"><?= htmlspecialchars($ev['judul']) ?></div>
        <div class="fs-12 text-muted mb-2">PIC: <?= htmlspecialchars($ev['nama_pic']??'—') ?></div>
        <div class="d-flex justify-content-between align-items-center">
          <div class="fs-12 text-muted"><i class="bi bi-people me-1"></i><?= $ev['jml_panitia'] ?> panitia</div>
          <span class="status-pill <?= match($ev['status']) {
            'berlangsung'=>'s-berlangsung','disetujui'=>'s-disetujui',
            'pengajuan','disetujui_manager','proposal_dibuat','rab_diajukan','perijinan'=>'s-pengajuan',
            default=>'s-draft'
          } ?>"><?= $statusLabel[$ev['status']] ?></span>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════
     ADMIN VIEW
     ══════════════════════════════════════════ -->
<?php elseif ($role === 'admin'): ?>

<!-- Role Banner -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge bg-primary py-1 px-2">Admin</span>
      <span class="fs-12 text-muted">Akses administrasi</span>
    </div>
    <h5 class="fw-800 mb-0">Halo, <?= htmlspecialchars(explode(' ',$user['nama'])[0]) ?>! 👋</h5>
    <div class="fs-12 text-muted"><?= date('l, d F Y') ?></div>
  </div>
  <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Buat Acara Baru
  </a>
</div>

<?php if ($myApproval > 0): ?>
<!-- Approval pending badge -->
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
  <i class="bi bi-bell-fill fs-4 flex-shrink-0"></i>
  <div class="flex-grow-1">
    <div class="fw-700">Kamu memiliki <strong><?= $myApproval ?> approval</strong> yang menunggu keputusanmu.</div>
    <div class="fs-12 text-muted">Buka halaman Approval untuk meninjau dan memberikan keputusan.</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/approvals/" class="btn btn-warning btn-sm flex-shrink-0">
    <i class="bi bi-check2-circle me-1"></i> Lihat Approval
  </a>
</div>
<?php endif; ?>

<!-- Acara sebagai PIC dan panitia -->
<div class="row g-4">
  <!-- Sebagai PIC -->
  <div class="col-lg-6">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h6 class="fw-800 mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Acara yang Saya Pimpin</h6>
        <div class="fs-12 text-muted">Kamu adalah PIC acara ini</div>
      </div>
      <?php if (!empty($asPIC)): ?><span class="badge bg-primary"><?= count($asPIC) ?></span><?php endif; ?>
    </div>
    <?php if (empty($asPIC)): ?>
      <div class="card border-dashed">
        <div class="card-body text-center py-4">
          <i class="bi bi-plus-circle-dotted fs-2 text-muted d-block mb-2 opacity-50"></i>
          <p class="text-muted fs-13 mb-3">Kamu belum memimpin acara apapun</p>
          <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus me-1"></i> Buat Acara Baru
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($asPIC as $ev): ?>
        <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $ev['id'] ?>"
           class="card text-decoration-none text-dark border-start border-primary border-3"
           onmouseover="this.style.transform='translateX(4px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div class="flex-grow-1">
                <div class="fw-700"><?= htmlspecialchars($ev['judul']) ?></div>
                <div class="fs-12 text-muted mt-1">
                  <i class="bi bi-calendar3 me-1"></i><?= date('d M Y',strtotime($ev['tanggal_mulai'])) ?>
                  · <span class="badge bg-secondary"><?= $ev['level'] ?></span>
                </div>
              </div>
              <div class="text-end flex-shrink-0">
                <?= hariChip((int)$ev['hari_lagi']) ?>
                <div class="mt-1">
                  <span class="status-pill <?= match($ev['status']) {
                    'berlangsung'=>'s-berlangsung','disetujui'=>'s-disetujui',
                    'pengajuan','disetujui_manager','proposal_dibuat','rab_diajukan','perijinan'=>'s-pengajuan',
                    default=>'s-draft'
                  } ?>"><?= $statusLabel[$ev['status']] ?></span>
                </div>
              </div>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Sebagai Panitia -->
  <div class="col-lg-6">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h6 class="fw-800 mb-0"><i class="bi bi-people-fill me-2 text-success"></i>Acara yang Saya Ikuti</h6>
        <div class="fs-12 text-muted">Terdaftar sebagai panitia</div>
      </div>
      <?php if (!empty($asPanitia)): ?><span class="badge bg-success"><?= count($asPanitia) ?></span><?php endif; ?>
    </div>
    <?php if (empty($asPanitia)): ?>
      <div class="card border-dashed">
        <div class="card-body text-center py-4">
          <i class="bi bi-people fs-2 text-muted d-block mb-2 opacity-50"></i>
          <p class="text-muted fs-13">Belum terdaftar sebagai panitia acara manapun.</p>
        </div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($asPanitia as $ev):
          $kc = ['pending'=>['warning','⏳ Konfirmasi'],'bersedia'=>['success','✅ Bersedia'],'tidak_bisa'=>['danger','❌ Tidak Bisa']];
          [$badgeC, $badgeL] = $kc[$ev['status_konfirmasi']] ?? ['secondary','?'];
        ?>
        <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $ev['id'] ?>"
           class="card text-decoration-none text-dark border-start border-<?= $badgeC ?> border-3"
           onmouseover="this.style.transform='translateX(4px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
          <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="flex-grow-1">
                <div class="fw-700"><?= htmlspecialchars($ev['judul']) ?></div>
                <div class="fs-12 text-muted mt-1">
                  <?php if ($ev['bagian']): ?><span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($ev['bagian']) ?></span><?php endif; ?>
                  <i class="bi bi-calendar3 me-1"></i><?= date('d M Y',strtotime($ev['tanggal_mulai'])) ?>
                </div>
              </div>
              <div class="text-end flex-shrink-0">
                <?= hariChip((int)$ev['hari_lagi']) ?>
                <div class="mt-1"><span class="badge bg-<?= $badgeC ?>"><?= $badgeL ?></span></div>
              </div>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════
     PIC / STAFF VIEW
     ══════════════════════════════════════════ -->
<?php else: ?>

<!-- Role Banner berdasarkan keterlibatan -->
<div class="card mb-4 border-0" style="background:linear-gradient(135deg,#1a3a5c,#245a8a)">
  <div class="card-body py-4 px-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <?php if (!empty($asPIC)): ?>
            <span class="badge py-1 px-2" style="background:rgba(255,255,255,.25);color:#fff">PIC Acara</span>
          <?php endif; ?>
          <?php if (!empty($asPanitia)): ?>
            <span class="badge py-1 px-2" style="background:rgba(255,255,255,.15);color:#fff">Panitia</span>
          <?php endif; ?>
          <span class="fs-12" style="color:rgba(255,255,255,.6)"><?= htmlspecialchars($user['divisi']??'Staff') ?></span>
        </div>
        <h5 class="text-white fw-800 mb-0">Halo, <?= htmlspecialchars(explode(' ',$user['nama'])[0]) ?>! 👋</h5>
        <div class="mt-1 fs-12" style="color:rgba(255,255,255,.65)"><?= date('l, d F Y') ?></div>
      </div>
      <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-light fw-600">
        <i class="bi bi-plus-circle me-1"></i> Buat Acara Baru
      </a>
    </div>
  </div>
</div>

<!-- ── PILIH AKTIVITAS ── -->
<?php if (empty($asPIC) && empty($asPanitia)): ?>
  <!-- Belum ada acara sama sekali -->
  <div class="empty-state py-5">
    <i class="bi bi-calendar-plus fs-1 d-block mb-3 text-primary opacity-50"></i>
    <h5 class="fw-700">Belum ada acara</h5>
    <p class="text-muted">Kamu belum terlibat dalam acara apapun. Mulai dengan membuat acara baru!</p>
    <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary mt-2">
      <i class="bi bi-plus-circle me-1"></i> Buat Acara Pertama
    </a>
  </div>

<?php else: ?>

<!-- Dua kolom utama: sebagai PIC & sebagai panitia -->
<div class="row g-4">

  <!-- ── Sebagai PIC ── -->
  <div class="col-lg-<?= !empty($asPanitia)?'6':'12' ?>">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h6 class="fw-800 mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Acara yang Saya Pimpin</h6>
        <div class="fs-12 text-muted">Kamu adalah PIC — bisa kelola semua aspek acara</div>
      </div>
      <?php if (!empty($asPIC)): ?>
        <span class="badge bg-primary"><?= count($asPIC) ?></span>
      <?php endif; ?>
    </div>

    <?php if (empty($asPIC)): ?>
      <div class="card border-dashed">
        <div class="card-body text-center py-4">
          <i class="bi bi-plus-circle-dotted fs-2 text-muted d-block mb-2 opacity-50"></i>
          <p class="text-muted fs-13 mb-3">Kamu belum memimpin acara apapun</p>
          <a href="<?= BASE_URL ?>/modules/events/create.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus me-1"></i> Buat Acara Baru
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($asPIC as $ev): ?>
        <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $ev['id'] ?>"
           class="card text-decoration-none text-dark border-start border-primary border-3"
           onmouseover="this.style.transform='translateX(4px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div class="flex-grow-1">
                <div class="fw-700"><?= htmlspecialchars($ev['judul']) ?></div>
                <div class="fs-12 text-muted mt-1">
                  <i class="bi bi-calendar3 me-1"></i><?= date('d M Y',strtotime($ev['tanggal_mulai'])) ?>
                  <?= $ev['tanggal_mulai']!==$ev['tanggal_selesai']?' – '.date('d M Y',strtotime($ev['tanggal_selesai'])):'' ?>
                  · <span class="badge bg-secondary"><?= $ev['level'] ?></span>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                  <span class="fs-12 text-muted"><i class="bi bi-people me-1"></i><?= $ev['jml_panitia'] ?> panitia</span>
                  <?php if ($ev['jml_pending']>0): ?>
                    <span class="badge bg-warning text-dark"><?= $ev['jml_pending'] ?> konfirmasi pending</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="text-end flex-shrink-0">
                <?= hariChip((int)$ev['hari_lagi']) ?>
                <div class="mt-1">
                  <span class="status-pill <?= match($ev['status']) {
                    'berlangsung'=>'s-berlangsung','disetujui'=>'s-disetujui',
                    'pengajuan','disetujui_manager','proposal_dibuat','rab_diajukan','perijinan'=>'s-pengajuan',
                    default=>'s-draft'
                  } ?>"><?= $statusLabel[$ev['status']] ?></span>
                </div>
              </div>
            </div>
          </div>
          <div class="card-footer py-2 text-center fs-12 fw-600 text-primary bg-transparent">
            <i class="bi bi-arrow-right-circle me-1"></i>Buka Workspace & Kelola Acara
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Sebagai Panitia ── -->
  <?php if (!empty($asPanitia)): ?>
  <div class="col-lg-6">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h6 class="fw-800 mb-0"><i class="bi bi-people-fill me-2 text-success"></i>Acara yang Saya Ikuti</h6>
        <div class="fs-12 text-muted">Kamu terdaftar sebagai panitia</div>
      </div>
      <span class="badge bg-success"><?= count($asPanitia) ?></span>
    </div>
    <div class="d-flex flex-column gap-3">
      <?php foreach ($asPanitia as $ev):
        $kc = ['pending'=>['warning','⏳ Konfirmasi Diperlukan'],'bersedia'=>['success','✅ Bersedia'],'tidak_bisa'=>['danger','❌ Tidak Bisa']];
        [$badgeC, $badgeL] = $kc[$ev['status_konfirmasi']];
      ?>
      <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $ev['id'] ?>"
         class="card text-decoration-none text-dark border-start border-<?= $badgeC === 'warning' ? 'warning' : ($badgeC === 'success' ? 'success' : 'danger') ?> border-3"
         onmouseover="this.style.transform='translateX(4px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'"
         onmouseout="this.style.transform='';this.style.boxShadow=''">
        <div class="card-body py-3">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="flex-grow-1">
              <div class="fw-700"><?= htmlspecialchars($ev['judul']) ?></div>
              <div class="fs-12 text-muted mt-1">
                <?php if ($ev['bagian']): ?>
                  <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($ev['bagian']) ?></span>
                <?php endif; ?>
                <?php if ($ev['is_event_admin']): ?>
                  <span class="badge bg-warning text-dark me-1"><i class="bi bi-star-fill"></i> Event Admin</span>
                <?php endif; ?>
                <i class="bi bi-calendar3 ms-1 me-1"></i><?= date('d M Y',strtotime($ev['tanggal_mulai'])) ?>
              </div>
            </div>
            <div class="text-end flex-shrink-0">
              <?= hariChip((int)$ev['hari_lagi']) ?>
              <div class="mt-1"><span class="badge bg-<?= $badgeC ?>"><?= $badgeL ?></span></div>
            </div>
          </div>
        </div>
        <div class="card-footer py-2 text-center fs-12 fw-600 bg-transparent" style="color:var(--text-muted)">
          <i class="bi bi-arrow-right-circle me-1"></i>Lihat Detail & Dokumen Acara
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- end row -->
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
