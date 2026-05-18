<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status()===PHP_SESSION_NONE) session_start();
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: '.BASE_URL.'/modules/dashboard/select.php'); exit; }

// Data acara
$stmt = $pdo->prepare("SELECT e.*, u.nama AS nama_pic FROM events e LEFT JOIN users u ON u.id=e.pic_id WHERE e.id=?");
$stmt->execute([$id]); $ev = $stmt->fetch();
if (!$ev) { header('Location: '.BASE_URL.'/modules/dashboard/select.php'); exit; }

// Role user di acara ini
$stmtR = $pdo->prepare("SELECT * FROM event_panitia WHERE event_id=? AND user_id=?");
$stmtR->execute([$id, $_SESSION['user_id']]); $myRole = $stmtR->fetch();

$isSA         = isSuperAdmin();
$isPic        = $isSA || ($myRole && $myRole['peran_acara']==='pic');
$isEventAdmin = $isSA || ($myRole && ($myRole['is_event_admin']||$myRole['peran_acara']==='pic'));
$isInti       = $myRole && in_array($myRole['peran_acara'],['pic','panitia_inti']);
$isAnggota    = (bool)$myRole || $isSA;

// Akses check
if (!$isAnggota) { header('Location: '.BASE_URL.'/modules/dashboard/select.php'); exit; }

$pageTitle = $ev['judul'];

// Determine max visibility user can see
$visibilityAccess = 'all';
if ($isPic) $visibilityAccess = 'pic_only';
elseif ($isInti || $isEventAdmin) $visibilityAccess = 'inti';

$visMap = ['all'=>0,'inti'=>1,'pic_only'=>2];
function canSeeFile(string $fileVis, string $userAccess): bool {
    global $visMap;
    return $visMap[$userAccess] >= $visMap[$fileVis];
}

// Files
$filesQuery = $pdo->prepare("SELECT f.*, u.nama AS uploader FROM event_files f LEFT JOIN users u ON u.id=f.uploaded_by WHERE f.event_id=? ORDER BY f.file_type, f.created_at DESC");
$filesQuery->execute([$id]); $allFiles = $filesQuery->fetchAll();
$files = array_filter($allFiles, fn($f) => canSeeFile($f['visibility'], $visibilityAccess));

// Panitia
$panitiaQ = $pdo->prepare("SELECT ep.*, u.nama, u.divisi, u.jabatan FROM event_panitia ep JOIN users u ON u.id=ep.user_id WHERE ep.event_id=? ORDER BY ep.peran_acara, u.nama");
$panitiaQ->execute([$id]); $panitia = $panitiaQ->fetchAll();

// Approvals
$approvQ = $pdo->prepare("SELECT ap.*, u.nama AS nama_approver FROM approvals ap LEFT JOIN users u ON u.id=ap.approver_id WHERE ap.event_id=? ORDER BY ap.urutan");
$approvQ->execute([$id]); $approvals = $approvQ->fetchAll();

// Checklist
$checkQ = $pdo->prepare("SELECT * FROM event_checklist WHERE event_id=? ORDER BY urutan");
$checkQ->execute([$id]); $checks = $checkQ->fetchAll();
$doneChecks = count(array_filter($checks, fn($c) => $c['is_done']));

// SWOT status user
$mySwot = $pdo->prepare("SELECT id FROM event_swot WHERE event_id=? AND user_id=?");
$mySwot->execute([$id, $_SESSION['user_id']]); $sudahSwot = (bool)$mySwot->fetchColumn();

// Handle update status
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status']) && $isPic) {
    $ns = $_POST['new_status'];
    if (in_array($ns,['berlangsung','selesai','ditolak'])) {
        $pdo->prepare("UPDATE events SET status=? WHERE id=?")->execute([$ns,$id]);
        if ($ns==='selesai') {
            // Kirim notif SWOT ke semua panitia
            foreach ($panitia as $p) {
                addNotif($pdo, $p['user_id'], 'Acara Selesai – Isi Evaluasi SWOT',
                    "Acara {$ev['judul']} telah selesai. Mohon isi evaluasi SWOT.",
                    BASE_URL.'/modules/swot/', 'info');
            }
        }
        setFlash('Status acara diperbarui.','success');
    }
    header("Location: ?id=$id"); exit;
}

// Handle toggle checklist
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_check'])) {
    $cid = (int)$_POST['check_id']; $done = (int)$_POST['is_done'];
    $pdo->prepare("UPDATE event_checklist SET is_done=?,done_by=?,done_at=? WHERE id=? AND event_id=?")
        ->execute([$done,$_SESSION['user_id'],$done?date('Y-m-d H:i:s'):null,$cid,$id]);
    header("Location: ?id=$id#checklist"); exit;
}

// Handle update WA group link
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_wa']) && $isPic) {
    $pdo->prepare("UPDATE events SET wa_group_link=? WHERE id=?")->execute([trim($_POST['wa_group_link']),$id]);
    setFlash('Link grup WA disimpan.','success');
    header("Location: ?id=$id"); exit;
}

$statusLabel = ['draft'=>'Draft','pengajuan'=>'Diajukan','disetujui_manager'=>'Disetujui Manager',
  'proposal_dibuat'=>'Proposal Dibuat','rab_diajukan'=>'RAB Diajukan','perijinan'=>'Perijinan',
  'disetujui'=>'Disetujui','berlangsung'=>'Berlangsung','selesai'=>'Selesai','ditolak'=>'Ditolak'];
$workflowStep = [
  'draft' => 'Isi semua data acara lalu ajukan kepada manager.',
  'pengajuan' => 'Acara sudah diajukan. Tunggu approval manager.',
  'disetujui_manager' => 'Manager menyetujui. Upload proposal/RAB di tab Dokumen & File.',
  'proposal_dibuat' => 'Proposal sudah dibuat. Lanjutkan dengan upload RAB di tab Dokumen & File.',
  'rab_diajukan' => 'RAB sudah diajukan. Menunggu approval bendahara.',
  'perijinan' => 'Proses perijinan berjalan. Pantau status di tab Dokumen & File.',
  'disetujui' => 'RAB disetujui. Undang panitia di tab Tim & Panitia.',
  'berlangsung' => 'Acara sedang berlangsung. Pantau checklist dan dokumentasi.',
  'selesai' => 'Acara selesai. Lakukan evaluasi dan tutup acara.',
  'ditolak' => 'Acara ditolak. Revisi dokumen atau approval kemudian ajukan kembali.',
];
$workflowHint = $workflowStep[$ev['status']] ?? 'Ikuti alur acara dan gunakan tab di bawah untuk mengelola dokumen, tim, checklist, dan approval.';
$fileTypeLabel = ['rab'=>'RAB','rundown'=>'Rundown','proposal'=>'Proposal','perijinan'=>'Perijinan',
  'jobdesk'=>'Jobdesk','undangan'=>'Undangan','lainnya'=>'Lainnya'];
$fileTypeIcon  = ['rab'=>'cash-stack','rundown'=>'list-task','proposal'=>'file-text','perijinan'=>'shield-check',
  'jobdesk'=>'person-workspace','undangan'=>'envelope','lainnya'=>'file-earmark'];
$visLabel = ['all'=>'Semua Panitia','inti'=>'Panitia Inti & PIC','pic_only'=>'PIC Saja'];
$visColor = ['all'=>'success','inti'=>'warning','pic_only'=>'danger'];
$peranLabel = ['pic'=>'PIC','panitia_inti'=>'Panitia Inti','panitia_support'=>'Support'];
$konfirmColor = ['pending'=>'warning','bersedia'=>'success','tidak_bisa'=>'danger'];
$konfirmLabel = ['pending'=>'Menunggu','bersedia'=>'Bersedia','tidak_bisa'=>'Tidak Bisa'];
$approvalLabel = ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak'];
$approvalColor = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'];
$tipeApprLabel = ['manager_tk'=>'Manager TK','manager_sd'=>'Manager SD','manager_smp'=>'Manager SMP',
  'sekretaris'=>'Sekretaris','bendahara'=>'Bendahara','kehumasan'=>'Kehumasan','kepala_sekolah'=>'Kepala Sekolah'];

$pendingApprovals = array_filter($approvals, fn($ap) => $ap['status'] === 'pending');
$pendingCount = count($pendingApprovals);
$currentApprovalUrutan = null;
if ($pendingCount > 0) {
    $currentApprovalUrutan = min(array_column($pendingApprovals, 'urutan'));
}

// Hitung sisa hari
$sisaHari = (int)ceil((strtotime($ev['tanggal_mulai']) - time()) / 86400);

require_once __DIR__ . '/../../includes/layout/header.php';
?>

<!-- Event Header -->
<div class="card mb-4" style="background:linear-gradient(135deg,#1a3a5c,#245a8a);border:none">
  <div class="card-body px-4 py-3">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-3">
        <a href="<?= BASE_URL ?>/modules/dashboard/select.php" class="back-btn"
           style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.2);color:#fff">
          <i class="bi bi-arrow-left"></i>
        </a>
        <div>
          <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <span class="badge bg-light text-dark"><?= $ev['level'] ?></span>
            <span class="badge bg-warning text-dark">
              <?= $myRole ? ($myRole['peran_acara']==='pic' ? 'PIC' : ($myRole['is_event_admin'] ? 'Event Admin' : ucfirst(str_replace('_',' ',$myRole['peran_acara'])))) : 'Viewer' ?>
            </span>
            <?php if ($myRole && $myRole['bagian']): ?>
              <span class="badge" style="background:rgba(255,255,255,.2);color:#fff"><?= htmlspecialchars($myRole['bagian']) ?></span>
            <?php endif; ?>
          </div>
          <h5 class="text-white fw-800 mb-0"><?= htmlspecialchars($ev['judul']) ?></h5>
          <div class="mt-1 fs-12" style="color:rgba(255,255,255,.7)">
            <i class="bi bi-calendar3 me-1"></i>
            <?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?>
            <?= $ev['tanggal_mulai']!==$ev['tanggal_selesai']?' – '.date('d M Y',strtotime($ev['tanggal_selesai'])):'' ?>
            <?php if (($ev['lokasi'] ?? '')): ?><span class="ms-3"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars(($ev['lokasi'] ?? '')) ?></span><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge fs-12 py-2 px-3 <?= match($ev['status']) {
          'berlangsung'=>'bg-warning text-dark','selesai'=>'bg-success','ditolak'=>'bg-danger',default=>'bg-light text-dark'
        } ?>"><?= $statusLabel[$ev['status']] ?></span>
        <?php if ($sisaHari >= 0 && $ev['status']!=='selesai'): ?>
          <span class="badge fs-12 py-2 px-3 <?= $sisaHari<=3?'bg-danger':($sisaHari<=7?'bg-warning text-dark':'bg-primary') ?>">
            <?= $sisaHari===0?'Hari ini!':"$sisaHari hari lagi" ?>
          </span>
        <?php endif; ?>
        <?php if ($isPic && in_array($ev['status'],['disetujui','berlangsung'])): ?>
          <div class="dropdown">
            <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">Update Status</button>
            <ul class="dropdown-menu">
              <?php if ($ev['status']==='disetujui'): ?><li><a class="dropdown-item" href="#" onclick="submitStatus('berlangsung')">✏️ Tandai Berlangsung</a></li><?php endif; ?>
              <?php if ($ev['status']==='berlangsung'): ?><li><a class="dropdown-item text-success" href="#" onclick="submitStatus('selesai')" data-confirm="Tandai acara ini selesai?">🏁 Tandai Selesai</a></li><?php endif; ?>
            </ul>
          </div>
          <form method="POST" id="statusForm" class="d-none">
            <input type="hidden" name="new_status" id="statusInput">
            <button name="update_status" type="submit" class="d-none"></button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card mb-4 border-start border-4 border-primary">
  <div class="card-body py-3">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
      <div>
        <div class="badge bg-primary text-white mb-2">Ringkasan Alur</div>
        <div class="fw-700 fs-14 mb-1"><?= htmlspecialchars($statusLabel[$ev['status']] ?? 'Status tidak diketahui') ?></div>
        <div class="fs-13 text-muted mb-2"><?= htmlspecialchars($workflowHint) ?></div>
        <?php if (!$isEventAdmin && !$isPic): ?>
          <div class="alert alert-warning py-2 px-3 mb-0 fs-12">
            Kamu saat ini hanya dapat melihat. Untuk upload dokumen atau menambahkan panitia, hubungi PIC atau event admin di acara ini.
          </div>
        <?php endif; ?>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary btn-sm" data-bs-toggle="tab" href="#overview">Overview</a>
        <a class="btn btn-outline-primary btn-sm" data-bs-toggle="tab" href="#dokumen">Dokumen</a>
        <a class="btn btn-outline-primary btn-sm" data-bs-toggle="tab" href="#tim">Tim</a>
        <a class="btn btn-outline-primary btn-sm" data-bs-toggle="tab" href="#checklist">Checklist</a>
        <?php if ($isPic): ?><a class="btn btn-outline-primary btn-sm" data-bs-toggle="tab" href="#approval">Approval</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Approval Pipeline -->
<?php if (!empty($approvals)): ?>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between">
    <div><i class="bi bi-flow-chart"></i> Alur Approval</div>
    <div class="approval-action-summary">
      <span><i class="bi bi-clock-history"></i> <?= $pendingCount ?> menunggu</span>
      <span><i class="bi bi-check-circle"></i> <?= count(array_filter($approvals, fn($ap) => $ap['status'] === 'approved')) ?> disetujui</span>
      <span><i class="bi bi-x-circle"></i> <?= count(array_filter($approvals, fn($ap) => $ap['status'] === 'rejected')) ?> ditolak</span>
    </div>
  </div>
  <div class="card-body py-3">
    <div class="approval-timeline">
      <?php foreach ($approvals as $ap):
        $cardClass = $ap['status'] === 'approved' ? 'approved' : ($ap['status'] === 'rejected' ? 'rejected' : 'pending');
        $isActive = $ap['status'] === 'pending' && $ap['urutan'] === $currentApprovalUrutan;
        $tooltip = trim(($ap['catatan'] ?? '') ?: 'Tidak ada catatan');
      ?>
      <button type="button" class="approval-step-card <?= $cardClass ?><?= $isActive ? ' active' : '' ?>" data-step="<?= (int)$ap['urutan'] ?>"
        data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($tooltip, ENT_QUOTES) ?>">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="fw-700 fs-13"><?= htmlspecialchars($ap['nama_approver'] ?? 'Belum ditetapkan') ?></div>
          <span class="badge bg-<?= $approvalColor[$ap['status']] ?? 'secondary' ?> fs-12"><?= $approvalLabel[$ap['status']] ?? ucfirst($ap['status']) ?></span>
        </div>
        <div class="fs-12 text-muted mb-2"><?= htmlspecialchars($tipeApprLabel[$ap['tipe_approver']] ?? $ap['tipe_approver']) ?></div>
        <div class="d-flex align-items-center justify-content-between">
          <span class="approval-meta">Urutan <?= (int)$ap['urutan'] ?></span>
          <?php if ($isActive): ?><span class="badge bg-primary fs-11">Langkah sekarang</span><?php endif; ?>
        </div>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Progress Step (PIC/Inti only) -->
<?php if ($isPic || $isInti): ?>
<div class="card mb-4">
  <div class="card-body py-3">
    <?php
    $steps = [
      ['draft','bi-pencil','Draft'],
      ['pengajuan','bi-send','Diajukan'],
      ['disetujui_manager','bi-person-check','Manager'],
      ['proposal_dibuat','bi-file-text','Proposal'],
      ['rab_diajukan','bi-cash','RAB'],
      ['disetujui','bi-check-circle','Disetujui'],
      ['berlangsung','bi-play-circle','Berlangsung'],
      ['selesai','bi-flag','Selesai'],
    ];
    $curIdx = array_search($ev['status'], array_column($steps,0));
    ?>
    <div class="step-flow">
      <?php foreach ($steps as $si => [$sk,$sic,$sl]): ?>
      <div class="step-item <?= $si<$curIdx?'done':($si===$curIdx?'active':'') ?>">
        <div class="step-dot"><i class="bi <?= $si<$curIdx?'bi-check':$sic ?>"></i></div>
        <div class="step-label"><?= $sl ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" id="workspaceTabs">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#overview"><i class="bi bi-house me-1"></i>Overview</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#dokumen"><i class="bi bi-folder2-open me-1"></i>Dokumen & File</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tim"><i class="bi bi-people me-1"></i>Tim & Panitia</a></li>
  <?php if (count($checks) > 0 || $isEventAdmin): ?><li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#checklist"><i class="bi bi-list-check me-1"></i>Checklist <span class="badge bg-primary ms-1"><?= $doneChecks ?>/<?= count($checks) ?></span></a></li><?php endif; ?>
  <?php if ($isPic): ?><li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#approval"><i class="bi bi-check2-circle me-1"></i>Approval</a></li><?php endif; ?>
  <?php if ($ev['status']==='selesai'): ?><li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#swot"><i class="bi bi-bar-chart-line me-1"></i>Evaluasi SWOT <?= !$sudahSwot?'<span class="badge bg-warning text-dark ms-1">Belum isi</span>':'' ?></a></li><?php endif; ?>
</ul>

<div class="tab-content">

<!-- ── TAB: OVERVIEW ── -->
<div class="tab-pane fade show active" id="overview">
  <div class="row g-3">
    <div class="col-md-5">

      <!-- Info Acara -->
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-info-circle"></i> Info Acara</div>
        <div class="card-body">
          <table class="table table-sm table-borderless mb-0 fs-13">
            <tr><th class="text-muted pe-3" width="120">PIC</th><td class="fw-600"><?= htmlspecialchars($ev['nama_pic']??'—') ?></td></tr>
            <tr><th class="text-muted">Tanggal</th><td><?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?><?= $ev['tanggal_mulai'] !== $ev['tanggal_selesai'] ? ' s/d ' . date('d M Y', strtotime($ev['tanggal_selesai'])) : '' ?></td></tr>
            <tr><th class="text-muted">Lokasi</th><td><?= htmlspecialchars($ev['lokasi'] ?? '—') ?></td></tr>
            <tr><th class="text-muted">Level</th><td><span class="badge bg-secondary"><?= $ev['level'] ?></span></td></tr>
            <tr><th class="text-muted">Total Panitia</th><td><?= count($panitia) ?> orang</td></tr>
          </table>
          <?php if (($ev['deskripsi'] ?? '')): ?>
            <hr class="my-2"><p class="fs-12 text-muted mb-0"><?= nl2br(htmlspecialchars(($ev['deskripsi'] ?? ''))) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Grup WA -->
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-whatsapp" style="color:#25d366"></i> Grup WhatsApp</div>
        <div class="card-body">
          <?php if (!empty($ev['wa_group_link'])): ?>
            <a href="<?= htmlspecialchars($ev['wa_group_link']) ?>" target="_blank" class="btn btn-success w-100 mb-2">
              <i class="bi bi-whatsapp me-1"></i> Gabung Grup WA Panitia
            </a>
          <?php else: ?>
            <p class="fs-12 text-muted">Link grup WA belum diatur.</p>
          <?php endif; ?>
          <?php if ($isPic): ?>
          <form method="POST">
            <div class="input-group input-group-sm">
              <input type="url" name="wa_group_link" class="form-control" placeholder="https://chat.whatsapp.com/..." value="<?= htmlspecialchars($ev['wa_group_link'] ?? '') ?>">
              <button name="save_wa" class="btn btn-outline-secondary">Simpan</button>
            </div>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Dokumentasi -->
      <div class="card">
        <div class="card-header"><i class="bi bi-images"></i> Dokumentasi</div>
        <div class="card-body">
          <?php if (($ev['link_dokumentasi'] ?? '')): ?>
            <a href="<?= htmlspecialchars(($ev['link_dokumentasi'] ?? '')) ?>" target="_blank" class="btn btn-outline-primary w-100 mb-2">
              <i class="bi bi-google me-1"></i> Buka Google Drive
            </a>
          <?php else: ?>
            <p class="fs-12 text-muted">Link dokumentasi belum diatur.</p>
          <?php endif; ?>
        </div>
      </div>

    </div>
    <div class="col-md-7">

      <!-- Komposisi Tim -->
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-people"></i> Komposisi Tim</div>
        <div class="card-body">
          <div class="row g-2 text-center mb-3">
            <?php
            $byPeran = array_count_values(array_column($panitia,'peran_acara'));
            $byStatus = array_count_values(array_column($panitia,'status_konfirmasi'));
            ?>
            <div class="col-4"><div class="p-2 rounded" style="background:#eff6ff"><div class="fw-800 fs-4 text-primary"><?= $byPeran['pic']??0 ?></div><div class="fs-12 text-muted">PIC</div></div></div>
            <div class="col-4"><div class="p-2 rounded" style="background:#f0fdf4"><div class="fw-800 fs-4" style="color:#059669"><?= $byPeran['panitia_inti']??0 ?></div><div class="fs-12 text-muted">Panitia Inti</div></div></div>
            <div class="col-4"><div class="p-2 rounded" style="background:#fafafa"><div class="fw-800 fs-4 text-muted"><?= $byPeran['panitia_support']??0 ?></div><div class="fs-12 text-muted">Support</div></div></div>
          </div>
          <div class="row g-2 text-center">
            <div class="col-4"><div class="p-2 rounded" style="background:#f0fdf4"><div class="fw-700" style="color:#059669"><?= $byStatus['bersedia']??0 ?></div><div class="fs-12 text-muted">Bersedia</div></div></div>
            <div class="col-4"><div class="p-2 rounded" style="background:#fffbeb"><div class="fw-700" style="color:#d97706"><?= $byStatus['pending']??0 ?></div><div class="fs-12 text-muted">Pending</div></div></div>
            <div class="col-4"><div class="p-2 rounded" style="background:#fef2f2"><div class="fw-700" style="color:#dc2626"><?= $byStatus['tidak_bisa']??0 ?></div><div class="fs-12 text-muted">Tidak Bisa</div></div></div>
          </div>
        </div>
      </div>

      <!-- File Terbaru (preview) -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-files"></i> File Terbaru</span>
          <a href="#dokumen" class="fs-12 text-primary" data-bs-toggle="tab" data-bs-target="#dokumen">Lihat semua</a>
        </div>
        <div class="card-body p-0">
          <?php $recentFiles = array_slice(array_values($files), 0, 4);
          if (empty($recentFiles)): ?>
            <div class="empty-state py-3"><i class="bi bi-folder2"></i><p>Belum ada file yang diupload</p></div>
          <?php else: foreach ($recentFiles as $f): ?>
            <div class="d-flex align-items-center px-3 py-2 border-bottom gap-2">
              <i class="bi bi-<?= $fileTypeIcon[$f['file_type']] ?> text-primary fs-5"></i>
              <div class="flex-grow-1 overflow-hidden">
                <div class="fw-600 fs-13 text-truncate"><?= htmlspecialchars($f['nama_file']) ?></div>
                <div class="fs-12 text-muted"><?= $fileTypeLabel[$f['file_type']] ?> · <?= date('d M', strtotime($f['created_at'])) ?></div>
              </div>
              <a href="<?= BASE_URL ?>/modules/files/download.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ── TAB: DOKUMEN ── -->
<div class="tab-pane fade" id="dokumen">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h6 class="fw-700 mb-0">Dokumen & File Acara</h6>
      <small class="text-muted">
        <?= $isPic ? 'Sebagai PIC, kamu dapat upload semua jenis file acara.' : ($isEventAdmin ? 'Gunakan tombol Upload File untuk menambahkan file acara.' : ($isInti ? 'Kamu dapat melihat file internal & publik.' : 'Kamu hanya dapat melihat file yang dibagikan ke semua panitia.')) ?>
      </small>
    </div>
    <?php if ($isPic || $isEventAdmin): ?>
      <a href="<?= BASE_URL ?>/modules/files/upload.php?event_id=<?= $id ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-upload me-1"></i> Upload File
      </a>
    <?php endif; ?>
  </div>

  <?php if (empty($files)): ?>
    <div class="empty-state"><i class="bi bi-folder2-open"></i><p>Belum ada file yang bisa kamu akses</p></div>
  <?php else:
    $grouped = [];
    foreach ($files as $f) $grouped[$f['file_type']][] = $f;
    foreach ($grouped as $type => $typeFiles): ?>
    <div class="card mb-3">
      <div class="card-header">
        <i class="bi bi-<?= $fileTypeIcon[$type] ?>"></i> <?= $fileTypeLabel[$type] ?>
        <span class="badge bg-secondary ms-1"><?= count($typeFiles) ?></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Nama File</th><th>Deskripsi</th><th>Akses</th><th>Diupload oleh</th><th>Tanggal</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($typeFiles as $f): ?>
              <tr>
                <td>
                  <div class="fw-600 fs-13"><?= htmlspecialchars($f['nama_file']) ?></div>
                  <div class="fs-12 text-muted"><?= htmlspecialchars($f['file_original']) ?></div>
                </td>
                <td class="fs-12"><?= htmlspecialchars($f['deskripsi']??'—') ?></td>
                <td>
                  <span class="badge bg-<?= $visColor[$f['visibility']] ?>">
                    <?= $visLabel[$f['visibility']] ?>
                  </span>
                </td>
                <td class="fs-12"><?= htmlspecialchars($f['uploader']??'—') ?></td>
                <td class="fs-12"><?= date('d M Y H:i', strtotime($f['created_at'])) ?></td>
                <td>
                  <a href="<?= BASE_URL ?>/modules/files/download.php?id=<?= $f['id'] ?>"
                     class="btn btn-sm btn-outline-primary" title="Download">
                    <i class="bi bi-download"></i>
                  </a>
                  <?php if ($isEventAdmin || ($f['uploaded_by']==$_SESSION['user_id'])): ?>
                    <a href="<?= BASE_URL ?>/modules/files/delete.php?id=<?= $f['id'] ?>&event_id=<?= $id ?>"
                       class="btn btn-sm btn-outline-danger ms-1" title="Hapus"
                       data-confirm="Hapus file ini?">
                      <i class="bi bi-trash"></i>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- ── TAB: TIM ── -->
<div class="tab-pane fade" id="tim">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h6 class="fw-700 mb-0">Tim & Susunan Panitia</h6>
      <small class="text-muted">
        <?= $isPic ? 'Sebagai PIC, kamu dapat mengundang dan mengelola panitia acara.' : ($isEventAdmin ? 'Undang panitia teknis melalui tombol Undang Panitia.' : 'Kamu dapat melihat susunan tim. Hanya PIC/event admin yang bisa menambahkan panitia.') ?>
      </small>
    </div>
    <?php if ($isPic || $isEventAdmin): ?>
      <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalInviteQuick">
        <i class="bi bi-person-plus me-1"></i> Undang Panitia
      </button>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Nama</th><th>Divisi</th><th>Peran</th><th>Bagian</th><th>Konfirmasi</th><?php if($isEventAdmin):?><th>Aksi</th><?php endif;?></tr></thead>
      <tbody>
      <?php foreach ($panitia as $p): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="avatar avatar-sm"><?= strtoupper(substr($p['nama'],0,2)) ?></div>
              <div>
                <div class="fw-600"><?= htmlspecialchars($p['nama']) ?></div>
                <div class="fs-12 text-muted"><?= htmlspecialchars($p['jabatan']??'') ?></div>
              </div>
            </div>
          </td>
          <td class="fs-12"><?= htmlspecialchars($p['divisi']??'—') ?></td>
          <td>
            <span class="badge <?= ['pic'=>'bg-primary','panitia_inti'=>'bg-info text-dark','panitia_support'=>'bg-secondary'][$p['peran_acara']] ?>">
              <?= $peranLabel[$p['peran_acara']] ?>
            </span>
            <?php if ($p['is_event_admin']): ?><span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i> Admin</span><?php endif; ?>
          </td>
          <td><?= htmlspecialchars($p['bagian']??'—') ?></td>
          <td>
            <span class="badge bg-<?= $konfirmColor[$p['status_konfirmasi']] ?>"><?= $konfirmLabel[$p['status_konfirmasi']] ?></span>
            <?php if ($p['status_konfirmasi']==='tidak_bisa' && $p['alasan_tolak']): ?>
              <div class="fs-12 text-danger mt-1">Alasan: <?= htmlspecialchars($p['alasan_tolak']) ?></div>
            <?php endif; ?>
          </td>
          <?php if ($isEventAdmin): ?>
          <td>
            <?php if ($p['peran_acara']!=='pic'): ?>
              <a href="<?= BASE_URL ?>/modules/panitia/toggle_admin.php?id=<?= $p['id'] ?>&event_id=<?= $id ?>"
                 class="btn btn-sm btn-outline-warning" title="<?= $p['is_event_admin']?'Cabut admin':'Jadikan admin' ?>"
                 data-confirm="<?= $p['is_event_admin']?'Cabut':'Jadikan' ?> Event Admin?">
                <i class="bi bi-star<?= $p['is_event_admin']?'-fill':'' ?>"></i>
              </a>
              <a href="<?= BASE_URL ?>/modules/panitia/remove.php?id=<?= $p['id'] ?>&event_id=<?= $id ?>"
                 class="btn btn-sm btn-outline-danger ms-1" data-confirm="Keluarkan dari panitia?">
                <i class="bi bi-person-dash"></i>
              </a>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── TAB: CHECKLIST ── -->
<div class="tab-pane fade" id="checklist">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-700 mb-0">Checklist Persiapan</h6>
    <?php if ($doneChecks > 0): ?>
      <span class="badge bg-success fs-12"><?= round($doneChecks/max(count($checks),1)*100) ?>% Selesai</span>
    <?php endif; ?>
  </div>
  <?php if (count($checks) > 0): ?>
    <div class="card">
      <div class="card-body">
        <div class="progress mb-3" style="height:8px;border-radius:4px">
          <div class="progress-bar bg-success" style="width:<?= count($checks)?round($doneChecks/count($checks)*100):0 ?>%"></div>
        </div>
        <?php foreach ($checks as $c): ?>
          <form method="POST" class="d-flex align-items-center gap-2 py-2 border-bottom">
            <input type="hidden" name="toggle_check" value="1">
            <input type="hidden" name="check_id" value="<?= $c['id'] ?>">
            <input type="hidden" name="is_done" value="<?= $c['is_done']?0:1 ?>">
            <button type="submit" class="btn p-0 border-0 bg-transparent flex-shrink-0">
              <i class="bi bi-<?= $c['is_done']?'check-circle-fill text-success':'circle text-muted' ?> fs-5"></i>
            </button>
            <span class="flex-grow-1 fs-13 <?= $c['is_done']?'text-decoration-line-through text-muted':'' ?>">
              <?= htmlspecialchars($c['item']) ?>
              <?php if ($c['kategori']): ?><span class="badge bg-light text-dark border ms-1 fs-12"><?= htmlspecialchars($c['kategori']) ?></span><?php endif; ?>
            </span>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="empty-state"><i class="bi bi-list-check"></i><p>Belum ada checklist. Gunakan template saat buat acara.</p></div>
  <?php endif; ?>
</div>

<!-- ── TAB: APPROVAL (PIC only) ── -->
<?php if ($isPic): ?>
<div class="tab-pane fade" id="approval">
  <div class="row g-3">
    <div class="col-md-7">
      <div class="card">
        <div class="card-header"><i class="bi bi-diagram-3"></i> Status Approval</div>
        <div class="card-body p-0">
          <?php if (empty($approvals)): ?>
            <div class="empty-state py-4"><i class="bi bi-check2-circle"></i><p>Belum ada approval dibuat</p></div>
          <?php else: ?>
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Tipe</th><th>Approver</th><th>Status</th><th>Catatan</th><th>Waktu</th></tr></thead>
              <tbody>
              <?php foreach ($approvals as $ap):
                $rowActive = $ap['status'] === 'pending' && $ap['urutan'] === $currentApprovalUrutan;
              ?>
                <tr id="approval-row-<?= (int)$ap['urutan'] ?>" class="approval-table-row<?= $rowActive ? ' selected' : '' ?>"
                    data-step="<?= (int)$ap['urutan'] ?>"
                    data-type="<?= htmlspecialchars($tipeApprLabel[$ap['tipe_approver']] ?? $ap['tipe_approver'], ENT_QUOTES) ?>"
                    data-approver="<?= htmlspecialchars($ap['nama_approver'] ?? '—', ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($approvalLabel[$ap['status']] ?? $ap['status'], ENT_QUOTES) ?>"
                    data-note="<?= htmlspecialchars($ap['catatan'] ?? 'Tidak ada catatan', ENT_QUOTES) ?>"
                    data-time="<?= $ap['approved_at'] ? date('d M Y H:i', strtotime($ap['approved_at'])) : 'Belum diproses' ?>"
                    data-color="<?= $approvalColor[$ap['status']] ?? 'secondary' ?>">
                  <td class="fw-600 fs-12"><?= $tipeApprLabel[$ap['tipe_approver']]??$ap['tipe_approver'] ?></td>
                  <td class="fs-12"><?= htmlspecialchars($ap['nama_approver']??'—') ?></td>
                  <td><span class="badge bg-<?= $approvalColor[$ap['status']] ?>"><?= $approvalLabel[$ap['status']] ?></span></td>
                  <td class="fs-12 text-muted"><?= htmlspecialchars($ap['catatan']??'—') ?></td>
                  <td class="fs-12"><?= $ap['approved_at']?date('d M H:i',strtotime($ap['approved_at'])):'—' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-5">
      <div class="card mb-3" id="approvalDetailPanel">
        <div class="card-header"><i class="bi bi-info-circle"></i> Detail Approval</div>
        <div class="card-body">
          <?php
          $defaultDetail = $approvals[0] ?? null;
          if ($currentApprovalUrutan !== null) {
              foreach ($approvals as $row) {
                  if ($row['urutan'] === $currentApprovalUrutan) {
                      $defaultDetail = $row;
                      break;
                  }
              }
          }
          ?>
          <?php if ($defaultDetail): ?>
            <div class="mb-2">
              <div class="fs-13 text-muted">Tipe</div>
              <div id="detailApprovalTitle" class="fw-700"><?= htmlspecialchars($tipeApprLabel[$defaultDetail['tipe_approver']] ?? $defaultDetail['tipe_approver']) ?></div>
            </div>
            <div class="mb-2">
              <div class="fs-13 text-muted">Approver</div>
              <div id="detailApprover" class="fw-700"><?= htmlspecialchars($defaultDetail['nama_approver'] ?? 'Belum ditetapkan') ?></div>
            </div>
            <div class="mb-2">
              <div class="fs-13 text-muted">Status</div>
              <div id="detailStatus"><span class="badge bg-<?= $approvalColor[$defaultDetail['status']] ?? 'secondary' ?>"><?= $approvalLabel[$defaultDetail['status']] ?? ucfirst($defaultDetail['status']) ?></span></div>
            </div>
            <div class="mb-2">
              <div class="fs-13 text-muted">Catatan</div>
              <div class="text-truncate" id="detailNote"><?= htmlspecialchars($defaultDetail['catatan'] ?? 'Tidak ada catatan') ?></div>
            </div>
            <div class="mb-0">
              <div class="fs-13 text-muted">Waktu</div>
              <div id="detailTime"><?= $defaultDetail['approved_at'] ? date('d M Y H:i', strtotime($defaultDetail['approved_at'])) : 'Belum diproses' ?></div>
            </div>
          <?php else: ?>
            <div class="empty-state py-4"><i class="bi bi-clock-history"></i><p>Pilih approval untuk melihat detail.</p></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><i class="bi bi-send"></i> Tambah Approval</div>
        <div class="card-body">
          <form method="POST" action="<?= BASE_URL ?>/modules/approvals/">
            <input type="hidden" name="event_id" value="<?= $id ?>">
            <div class="mb-3">
              <label class="form-label">Tipe Approver</label>
              <select name="tipe_approver" class="form-select form-select-sm" required>
                <?php foreach ($tipeApprLabel as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Pilih Approver</label>
              <select name="approver_id" class="form-select form-select-sm" required>
                <option value="">— Pilih SDM —</option>
                <?php $sdmAll=$pdo->query("SELECT id,nama,jabatan FROM users WHERE status='aktif' ORDER BY nama")->fetchAll();
                foreach ($sdmAll as $s): ?>
                  <option value="<?=$s['id']?>"><?=htmlspecialchars($s['nama'])?> · <?=htmlspecialchars($s['jabatan']??'')?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Urutan</label>
              <input type="number" name="urutan" class="form-control form-control-sm" value="<?=count($approvals)+1?>" min="1">
            </div>
            <button type="submit" name="buat_approval" class="btn btn-primary btn-sm w-100">
              <i class="bi bi-send me-1"></i> Buat & Kirim
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const approvalCards = document.querySelectorAll('.approval-step-card');
  const rows = document.querySelectorAll('.approval-table-row');
  const detailPanel = document.getElementById('approvalDetailPanel');
  const approvalTabLink = document.querySelector('a[href="#approval"]');

  function clearSelection() {
    rows.forEach(row => row.classList.remove('selected'));
  }

  function selectRow(row) {
    if (!row) return;
    clearSelection();
    row.classList.add('selected');
    const type = row.dataset.type || '—';
    const approver = row.dataset.approver || '—';
    const status = row.dataset.status || '—';
    const note = row.dataset.note || 'Tidak ada catatan';
    const time = row.dataset.time || 'Belum diproses';
    detailPanel.querySelector('#detailApprovalTitle')?.textContent = type;
    detailPanel.querySelector('#detailApprover')?.textContent = approver;
    detailPanel.querySelector('#detailStatus')?.innerHTML = `<span class="badge bg-${row.dataset.color}">${status}</span>`;
    detailPanel.querySelector('#detailNote')?.textContent = note;
    detailPanel.querySelector('#detailTime')?.textContent = time;
  }

  approvalCards.forEach(card => {
    card.addEventListener('click', function() {
      const row = document.getElementById('approval-row-' + this.dataset.step);
      if (row) {
        if (approvalTabLink) approvalTabLink.click();
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        selectRow(row);
      }
    });
  });

  rows.forEach(row => {
    row.addEventListener('click', function() {
      if (approvalTabLink) approvalTabLink.click();
      selectRow(this);
    });
  });
});
</script>


<!-- ── TAB: EVALUASI ── -->
<div class="tab-pane fade" id="evaluasi">
<?php
$evalList = $pdo->prepare("SELECT ev.*, (SELECT COUNT(DISTINCT user_id) FROM evaluasi_jawaban ej WHERE ej.evaluasi_id=ev.id) AS jml_responden FROM event_evaluasi ev WHERE ev.event_id=? ORDER BY ev.created_at DESC");
$evalList->execute([$id]); $evaluasiList = $evalList->fetchAll();
$totalPanitiaEval = count($panitia);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h6 class="fw-700 mb-0">Form Evaluasi Acara</h6>
    <small class="text-muted">Evaluasi post-acara yang dibuat oleh PIC untuk diisi seluruh panitia</small>
  </div>
  <?php if ($isPic): ?>
    <a href="<?= BASE_URL ?>/modules/evaluasi/create.php?event_id=<?= $id ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-circle me-1"></i> Buat Form Evaluasi
    </a>
  <?php endif; ?>
</div>

<?php if (empty($evaluasiList)): ?>
  <div class="empty-state">
    <i class="bi bi-clipboard-x"></i>
    <p><?= $isPic ? 'Belum ada form evaluasi. Buat form untuk mengumpulkan feedback panitia.' : 'Belum ada form evaluasi dari PIC.' ?></p>
    <?php if ($isPic): ?><a href="<?= BASE_URL ?>/modules/evaluasi/create.php?event_id=<?= $id ?>" class="btn btn-primary btn-sm mt-2"><i class="bi bi-plus me-1"></i>Buat Form Evaluasi</a><?php endif; ?>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($evaluasiList as $ev2):
      $myJawab = $pdo->prepare("SELECT COUNT(*) FROM evaluasi_jawaban WHERE evaluasi_id=? AND user_id=?");
      $myJawab->execute([$ev2['id'],$_SESSION['user_id']]); $sudahIsiEval = (bool)$myJawab->fetchColumn();
      $pct = $totalPanitiaEval > 0 ? round($ev2['jml_responden']/$totalPanitiaEval*100) : 0;
    ?>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <div class="fw-700 mb-1"><?= htmlspecialchars($ev2['judul']) ?></div>
          <?php if ($ev2['deskripsi']): ?><div class="fs-12 text-muted mb-2"><?= htmlspecialchars($ev2['deskripsi']) ?></div><?php endif; ?>
          <?php if ($ev2['deadline']): ?><div class="fs-12 mb-2"><i class="bi bi-clock me-1 text-warning"></i>Deadline: <?= date('d M Y',strtotime($ev2['deadline'])) ?></div><?php endif; ?>

          <div class="mb-2">
            <div class="d-flex justify-content-between fs-12 mb-1">
              <span class="text-muted">Responden</span>
              <span class="fw-600"><?= $ev2['jml_responden'] ?>/<?= $totalPanitiaEval ?></span>
            </div>
            <div class="progress" style="height:6px;border-radius:3px">
              <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
            </div>
          </div>

          <div class="d-flex gap-2 mt-3">
            <?php if (!$sudahIsiEval && $ev2['is_active']): ?>
              <a href="<?= BASE_URL ?>/modules/evaluasi/fill.php?id=<?= $ev2['id'] ?>" class="btn btn-primary btn-sm flex-grow-1">
                <i class="bi bi-pencil-square me-1"></i>Isi Evaluasi
              </a>
            <?php elseif ($sudahIsiEval): ?>
              <span class="btn btn-success btn-sm flex-grow-1 disabled"><i class="bi bi-check me-1"></i>Sudah Diisi</span>
            <?php endif; ?>
            <?php if ($isPic): ?>
              <a href="<?= BASE_URL ?>/modules/evaluasi/results.php?id=<?= $ev2['id'] ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-bar-chart-line me-1"></i>Lihat Hasil
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<!-- Modal Quick Invite Panitia -->
<?php if ($isPic || $isEventAdmin): ?>
<div class="modal fade" id="modalInviteQuick" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-700"><i class="bi bi-person-plus me-2"></i>Undang Panitia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>/modules/panitia/bulk_invite.php">
        <input type="hidden" name="event_id" value="<?= $id ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Peran</label>
            <select name="peran_acara" class="form-select form-select-sm" required>
              <option value="panitia_inti">Panitia Inti</option>
              <option value="panitia_support">Panitia Support</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Cari & Pilih SDM</label>
            <input type="text" id="quickSearch" class="form-control form-control-sm" placeholder="Ketik nama atau divisi...">
          </div>
          <div class="mb-3" style="max-height:350px;overflow-y:auto;border:1px solid var(--border);border-radius:6px;padding:8px">
            <?php
            $sdmQuickList = $pdo->prepare("SELECT u.* FROM users u WHERE u.status='aktif' AND u.id NOT IN (SELECT user_id FROM event_panitia WHERE event_id=?) ORDER BY u.divisi,u.nama");
            $sdmQuickList->execute([$id]); $sdmList = $sdmQuickList->fetchAll();
            if (empty($sdmList)): ?>
              <div class="text-center py-4 text-muted fs-12"><i class="bi bi-inbox"></i> Semua SDM sudah terdaftar di acara ini.</div>
            <?php else: foreach ($sdmList as $s): ?>
              <div class="form-check py-2 border-bottom quicksdm-item" data-name="<?= htmlspecialchars(strtolower($s['nama'])) ?>" data-divisi="<?= htmlspecialchars(strtolower($s['divisi']??'')) ?>">
                <input class="form-check-input sdm-check-quick" type="checkbox" name="user_ids[]" value="<?= $s['id'] ?>" id="sdmq<?= $s['id'] ?>">
                <label class="form-check-label w-100" for="sdmq<?= $s['id'] ?>">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <div class="fw-600 fs-13"><?= htmlspecialchars($s['nama']) ?></div>
                      <div class="fs-12 text-muted"><?= htmlspecialchars($s['divisi']??'—') ?> · <?= htmlspecialchars($s['jabatan']??'') ?></div>
                    </div>
                  </div>
                </label>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="undang" class="btn btn-primary">
            <i class="bi bi-send me-1"></i>Undang (<span id="quickCount">0</span>)
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.getElementById('quickSearch')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.quicksdm-item').forEach(item => {
    const name = item.dataset.name;
    const divisi = item.dataset.divisi;
    item.style.display = (name.includes(q) || divisi.includes(q)) ? '' : 'none';
  });
});
document.querySelectorAll('.sdm-check-quick').forEach(chk => {
  chk.addEventListener('change', function() {
    const cnt = document.querySelectorAll('.sdm-check-quick:checked').length;
    document.getElementById('quickCount').textContent = cnt;
  });
});
</script>
<?php endif; ?>

</div><!-- end tab-content --><?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
