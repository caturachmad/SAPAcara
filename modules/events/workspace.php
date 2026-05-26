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
$isInti       = $myRole && $myRole['peran_acara'] !== 'pic';
$isAnggota    = (bool)$myRole || $isSA;

if (!$isAnggota) { header('Location: '.BASE_URL.'/modules/dashboard/select.php'); exit; }

$pageTitle = $ev['judul'];

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

// ── SWOT ──────────────────────────────────────────────────────────────────────
$mySwotStmt = $pdo->prepare("SELECT * FROM event_swot WHERE event_id=? AND user_id=?");
$mySwotStmt->execute([$id, $_SESSION['user_id']]);
$mySwotRow  = $mySwotStmt->fetch() ?: null;
$sudahSwot  = (bool)$mySwotRow;
$swotSentAt = $ev['swot_sent_at'] ?? null;

// Pertanyaan custom yang sudah dibuat PIC
$swotQsStmt = $pdo->prepare("SELECT * FROM event_swot_questions WHERE event_id=? ORDER BY urutan");
$swotQsStmt->execute([$id]);
$swotQuestions = $swotQsStmt->fetchAll();

// Tracking (siapa sudah/belum submit) — load hanya jika sudah dikirim
$swotTracking = [];
if ($swotSentAt) {
    $trkQ = $pdo->prepare("
        SELECT ep.user_id, u.nama, ep.bagian, ep.peran_acara,
               sw.submitted_at
        FROM event_panitia ep
        JOIN users u ON u.id = ep.user_id
        LEFT JOIN event_swot sw ON sw.event_id = ep.event_id AND sw.user_id = ep.user_id
        WHERE ep.event_id = ?
        ORDER BY (sw.submitted_at IS NULL) DESC, u.nama ASC
    ");
    $trkQ->execute([$id]);
    $swotTracking = $trkQ->fetchAll();
    $sudahCount  = count(array_filter($swotTracking, fn($r) => $r['submitted_at']));
    $belumCount  = count($swotTracking) - $sudahCount;
}

// Data SWOT semua panitia (untuk analisis PIC)
$swotData = [];
if ($swotSentAt && $isPic) {
    $sdQ = $pdo->prepare("SELECT sw.*, u.nama FROM event_swot sw LEFT JOIN users u ON u.id=sw.user_id WHERE sw.event_id=?");
    $sdQ->execute([$id]);
    $swotData = $sdQ->fetchAll();
}

// Jawaban custom questions milik user yang sedang login
$mySwotAnswers = [];
if ($mySwotRow) {
    $maQ = $pdo->prepare("SELECT question_id, jawaban FROM event_swot_answers WHERE swot_id=?");
    $maQ->execute([$mySwotRow['id']]);
    $mySwotAnswers = $maQ->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Jawaban custom questions semua panitia (untuk analisis PIC)
$allQAnswers = [];
if (!empty($swotQuestions) && !empty($swotData)) {
    foreach ($swotQuestions as $sq) {
        $aqQ = $pdo->prepare("
            SELECT ea.jawaban, u.nama, sw.is_anonim
            FROM event_swot_answers ea
            JOIN event_swot sw ON sw.id = ea.swot_id
            LEFT JOIN users u ON u.id = sw.user_id
            WHERE ea.question_id = ?
        ");
        $aqQ->execute([$sq['id']]);
        $allQAnswers[$sq['id']] = $aqQ->fetchAll();
    }
}

// Helper analisis keyword
function topKeywords(array $rows, string $field, int $top = 5): array {
    $all = [];
    foreach ($rows as $r) {
        if (!$r[$field]) continue;
        $words = preg_split('/[\s,\.;]+/', strtolower($r[$field]), -1, PREG_SPLIT_NO_EMPTY);
        $stop  = ['dan','yang','di','ke','dari','untuk','dengan','ini','itu','tidak','ada','acara','saat','kami','kita'];
        foreach ($words as $w) {
            if (strlen($w) > 3 && !in_array($w, $stop)) $all[] = $w;
        }
    }
    $count = array_count_values($all);
    arsort($count);
    return array_slice($count, 0, $top, true);
}

// ── POST: update status ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status']) && $isPic) {
    $ns = $_POST['new_status'];
    if (in_array($ns,['berlangsung','selesai','ditolak'])) {
        $pdo->prepare("UPDATE events SET status=? WHERE id=?")->execute([$ns,$id]);
        // SWOT notification dikirim manual oleh PIC via tombol "Kirim SWOT", bukan otomatis
    }
    setFlash('Status acara diperbarui.','success');
    header("Location: ?id=$id"); exit;
}

// ── POST: toggle checklist ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_check'])) {
    $cid = (int)$_POST['check_id']; $done = (int)$_POST['is_done'];
    $pdo->prepare("UPDATE event_checklist SET is_done=?,done_by=?,done_at=? WHERE id=? AND event_id=?")
        ->execute([$done,$_SESSION['user_id'],$done?date('Y-m-d H:i:s'):null,$cid,$id]);
    header("Location: ?id=$id#checklist"); exit;
}

// ── POST: save WA group link ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_wa']) && $isPic) {
    $pdo->prepare("UPDATE events SET wa_group_link=? WHERE id=?")->execute([trim($_POST['wa_group_link']),$id]);
    setFlash('Link grup WA disimpan.','success');
    header("Location: ?id=$id"); exit;
}

// ── POST: save documentation link ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_doc']) && $isPic) {
    $pdo->prepare("UPDATE events SET link_dokumentasi=? WHERE id=?")->execute([trim($_POST['link_dokumentasi']),$id]);
    setFlash('Link dokumentasi disimpan.','success');
    header("Location: ?id=$id"); exit;
}

// ── POST: tambah custom SWOT question (PIC, sebelum dikirim) ─────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_swot_q']) && $isPic && !$swotSentAt) {
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    if ($pertanyaan) {
        $nextUrutan = count($swotQuestions) + 1;
        $pdo->prepare("INSERT INTO event_swot_questions (event_id, pertanyaan, urutan) VALUES (?,?,?)")
            ->execute([$id, $pertanyaan, $nextUrutan]);
    }
    header("Location: ?id=$id#swot"); exit;
}

// ── POST: hapus custom SWOT question (PIC, sebelum dikirim) ──────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_swot_q']) && $isPic && !$swotSentAt) {
    $qid = (int)$_POST['question_id'];
    $pdo->prepare("DELETE FROM event_swot_questions WHERE id=? AND event_id=?")->execute([$qid, $id]);
    header("Location: ?id=$id#swot"); exit;
}

// ── POST: kirim SWOT ke panitia (PIC) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_swot']) && $isPic && !$swotSentAt) {
    $pdo->prepare("UPDATE events SET swot_sent_at=NOW() WHERE id=?")->execute([$id]);
    foreach ($panitia as $p) {
        addNotif($pdo, $p['user_id'],
            '📋 Evaluasi SWOT — ' . $ev['judul'],
            'PIC telah membuka form evaluasi SWOT. Mohon isi secepatnya.',
            BASE_URL . '/modules/events/workspace.php?id=' . $id,
            'info'
        );
    }
    setFlash('Form SWOT berhasil dikirim ke ' . count($panitia) . ' panitia.', 'success');
    header("Location: ?id=$id#swot"); exit;
}

// ── POST: submit SWOT (panitia isi form) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_swot']) && $swotSentAt && $isAnggota && !$sudahSwot) {
    $pdo->prepare("
        INSERT INTO event_swot (event_id, user_id, strength, weakness, opportunity, threat, saran, is_anonim)
        VALUES (?,?,?,?,?,?,?,?)
    ")->execute([
        $id,
        $_SESSION['user_id'],
        trim($_POST['strength']    ?? ''),
        trim($_POST['weakness']    ?? ''),
        trim($_POST['opportunity'] ?? ''),
        trim($_POST['threat']      ?? ''),
        trim($_POST['saran']       ?? ''),
        isset($_POST['is_anonim']) ? 1 : 0,
    ]);
    $newSwotId = (int)$pdo->lastInsertId();
    // Simpan jawaban custom questions
    foreach ($swotQuestions as $sq) {
        $jawaban = trim($_POST['cq_' . $sq['id']] ?? '');
        $pdo->prepare("INSERT INTO event_swot_answers (swot_id, question_id, jawaban) VALUES (?,?,?)")
            ->execute([$newSwotId, $sq['id'], $jawaban]);
    }
    setFlash('Evaluasi SWOT berhasil dikirim. Terima kasih!', 'success');
    header("Location: ?id=$id#swot"); exit;
}

// ── Label helpers ─────────────────────────────────────────────────────────────
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
$workflowHint = $workflowStep[$ev['status']] ?? '';
$fileTypeLabel = ['rab'=>'RAB','rundown'=>'Rundown','proposal'=>'Proposal','perijinan'=>'Perijinan',
  'jobdesk'=>'Jobdesk','undangan'=>'Undangan','lainnya'=>'Lainnya'];
$fileTypeIcon  = ['rab'=>'cash-stack','rundown'=>'list-task','proposal'=>'file-text','perijinan'=>'shield-check',
  'jobdesk'=>'person-workspace','undangan'=>'envelope','lainnya'=>'file-earmark'];
$visLabel = ['all'=>'Semua Panitia','inti'=>'Panitia & PIC','pic_only'=>'PIC Saja'];
$visColor = ['all'=>'success','inti'=>'warning','pic_only'=>'danger'];
$panitiaBagianOptions = ['Bendahara','Sekretaris','Logistik','Dokumentasi','Konsumsi','Tim Medis','Tim Acara','Korlap','SC Kegiatan','Tilawah','MC','Operator','Dekor','Kehumasan','Perlengkapan','Keamanan','Registrasi','Transportasi'];
$peranLabel = ['pic'=>'PIC','panitia_inti'=>'Panitia Inti','panitia_support'=>'Panitia Biasa'];
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
              <?= $myRole ? ($myRole['peran_acara']==='pic' ? 'PIC' : ($myRole['is_event_admin'] ? 'Panitia Inti' : 'Panitia Biasa')) : 'Viewer' ?>
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
            <?php if ($ev['lokasi'] ?? ''): ?><span class="ms-3"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($ev['lokasi']) ?></span><?php endif; ?>
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
              <?php if ($ev['status']==='disetujui'): ?><li><button type="button" class="dropdown-item" onclick="submitStatus('berlangsung')">✏️ Tandai Berlangsung</button></li><?php endif; ?>
              <?php if ($ev['status']==='berlangsung'): ?><li><button type="button" class="dropdown-item text-success" onclick="submitStatus('selesai')" data-confirm="Tandai acara ini selesai?">🏁 Tandai Selesai</button></li><?php endif; ?>
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

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" id="workspaceTabs">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#overview"><i class="bi bi-house me-1"></i>Overview</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#dokumen"><i class="bi bi-folder2-open me-1"></i>Dokumen & File</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tim"><i class="bi bi-people me-1"></i>Tim & Panitia</a></li>
  <?php if (count($checks) > 0 || $isEventAdmin): ?>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#checklist"><i class="bi bi-list-check me-1"></i>Checklist <span class="badge bg-primary ms-1"><?= $doneChecks ?>/<?= count($checks) ?></span></a></li>
  <?php endif; ?>
  <?php if ($isPic): ?>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#approval"><i class="bi bi-check2-circle me-1"></i>Approval</a></li>
  <?php endif; ?>
  <?php if ($ev['status']==='selesai'): ?>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#swot">
        <i class="bi bi-bar-chart-line me-1"></i>Evaluasi SWOT
        <?php if (!$swotSentAt && $isPic): ?>
          <span class="badge bg-warning text-dark ms-1">Belum dikirim</span>
        <?php elseif ($swotSentAt && !$sudahSwot && !$isPic): ?>
          <span class="badge bg-danger ms-1">Belum isi</span>
        <?php elseif ($swotSentAt && $sudahSwot && !$isPic): ?>
          <span class="badge bg-success ms-1"><i class="bi bi-check"></i></span>
        <?php endif; ?>
      </a>
    </li>
  <?php endif; ?>
</ul>

<div class="tab-content">

<!-- ── TAB: OVERVIEW ── -->
<div class="tab-pane fade show active" id="overview">
  <div class="row g-3">
    <div class="col-md-5">
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
          <?php if ($ev['deskripsi'] ?? ''): ?>
            <hr class="my-2"><p class="fs-12 text-muted mb-0"><?= nl2br(htmlspecialchars($ev['deskripsi'])) ?></p>
          <?php endif; ?>
        </div>
      </div>

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

      <div class="card">
        <div class="card-header"><i class="bi bi-images"></i> Dokumentasi</div>
        <div class="card-body">
          <?php if ($ev['link_dokumentasi'] ?? ''): ?>
            <a href="<?= htmlspecialchars($ev['link_dokumentasi']) ?>" target="_blank" class="btn btn-outline-primary w-100 mb-2">
              <i class="bi bi-google me-1"></i> Buka Google Drive
            </a>
          <?php else: ?>
            <p class="fs-12 text-muted">Link dokumentasi belum diatur.</p>
          <?php endif; ?>
          <?php if ($isPic): ?>
            <form method="POST" class="mt-3">
              <div class="input-group input-group-sm">
                <input type="url" name="link_dokumentasi" class="form-control" placeholder="https://drive.google.com/..." value="<?= htmlspecialchars($ev['link_dokumentasi'] ?? '') ?>">
                <button name="save_doc" class="btn btn-outline-secondary">Simpan</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-7">
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-people"></i> Komposisi Tim</div>
        <div class="card-body">
          <div class="row g-2 text-center mb-3">
            <?php $byPeran = array_count_values(array_column($panitia,'peran_acara')); ?>
            <div class="col-4"><div class="p-2 rounded" style="background:#eff6ff"><div class="fw-800 fs-4 text-primary"><?= $byPeran['pic']??0 ?></div><div class="fs-12 text-muted">PIC</div></div></div>
            <div class="col-4"><div class="p-2 rounded" style="background:#f0fdf4"><div class="fw-800 fs-4" style="color:#059669"><?= ($byPeran['panitia_inti']??0)+($byPeran['panitia_support']??0) ?></div><div class="fs-12 text-muted">Panitia</div></div></div>
            <div class="col-4"><div class="p-2 rounded" style="background:#fafafa"><div class="fw-800 fs-4 text-muted"><?= count($panitia) ?></div><div class="fs-12 text-muted">Total Tim</div></div></div>
          </div>
        </div>
      </div>

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
              <a href="<?= BASE_URL ?>/modules/files/preview.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
              <a href="<?= BASE_URL ?>/modules/files/download.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary ms-1"><i class="bi bi-download"></i></a>
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
        <?= $isPic ? 'Sebagai PIC, kamu dapat upload semua jenis file acara.' : ($isEventAdmin ? 'Gunakan tombol Upload File untuk menambahkan file acara.' : 'Kamu dapat melihat file yang dibagikan ke kamu.') ?>
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
                <td><div class="fw-600 fs-13"><?= htmlspecialchars($f['nama_file']) ?></div></td>
                <td class="fs-12"><?= htmlspecialchars($f['deskripsi']??'—') ?></td>
                <td><span class="badge bg-<?= $visColor[$f['visibility']] ?>"><?= $visLabel[$f['visibility']] ?></span></td>
                <td class="fs-12"><?= htmlspecialchars($f['uploader']??'—') ?></td>
                <td class="fs-12"><?= date('d M Y H:i', strtotime($f['created_at'])) ?></td>
                <td>
                  <a href="<?= BASE_URL ?>/modules/files/preview.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                  <a href="<?= BASE_URL ?>/modules/files/download.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary ms-1"><i class="bi bi-download"></i></a>
                  <?php if ($isPic || $isEventAdmin || (int)($f['uploaded_by']??0)===(int)$_SESSION['user_id']): ?>
                    <a href="<?= BASE_URL ?>/modules/files/edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-warning ms-1"><i class="bi bi-pencil"></i></a>
                  <?php endif; ?>
                  <?php if ($isEventAdmin || (int)($f['uploaded_by']??0)===(int)$_SESSION['user_id']): ?>
                    <a href="<?= BASE_URL ?>/modules/files/delete.php?id=<?= $f['id'] ?>&event_id=<?= $id ?>" class="btn btn-sm btn-outline-danger ms-1" data-confirm="Hapus file ini?"><i class="bi bi-trash"></i></a>
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
      <small class="text-muted"><?= $isPic ? 'Sebagai PIC, kamu dapat mengundang dan mengelola panitia acara.' : 'Kamu dapat melihat susunan tim.' ?></small>
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
            <span class="badge bg-secondary"><?= $p['peran_acara']==='pic'?'PIC':(($p['is_event_admin']??0)?'Panitia Inti':'Panitia Biasa') ?></span>
            <?php if ($p['is_event_admin']): ?><span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i></span><?php endif; ?>
          </td>
          <td><?= htmlspecialchars($p['bagian']??'—') ?></td>
          <td><span class="badge bg-<?= $konfirmColor[$p['status_konfirmasi']] ?>"><?= $konfirmLabel[$p['status_konfirmasi']] ?></span></td>
          <?php if ($isEventAdmin): ?>
          <td>
            <?php if ($p['peran_acara']!=='pic'): ?>
              <a href="<?= BASE_URL ?>/modules/panitia/toggle_admin.php?id=<?= $p['id'] ?>&event_id=<?= $id ?>" class="btn btn-sm btn-outline-warning" data-confirm="<?= $p['is_event_admin']?'Cabut':'Jadikan' ?> Event Admin?"><i class="bi bi-star<?= $p['is_event_admin']?'-fill':'' ?>"></i></a>
              <a href="<?= BASE_URL ?>/modules/panitia/remove.php?id=<?= $p['id'] ?>&event_id=<?= $id ?>" class="btn btn-sm btn-outline-danger ms-1" data-confirm="Keluarkan dari panitia?"><i class="bi bi-person-dash"></i></a>
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
    <?php if ($doneChecks > 0): ?><span class="badge bg-success fs-12"><?= round($doneChecks/max(count($checks),1)*100) ?>% Selesai</span><?php endif; ?>
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
    <div class="empty-state"><i class="bi bi-list-check"></i><p>Belum ada checklist.</p></div>
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
                $rowActive = $ap['status']==='pending' && $ap['urutan']===$currentApprovalUrutan;
              ?>
                <tr id="approval-row-<?= (int)$ap['urutan'] ?>" class="approval-table-row<?= $rowActive?' selected':'' ?>"
                    data-step="<?= (int)$ap['urutan'] ?>"
                    data-type="<?= htmlspecialchars($tipeApprLabel[$ap['tipe_approver']]??$ap['tipe_approver'],ENT_QUOTES) ?>"
                    data-approver="<?= htmlspecialchars($ap['nama_approver']??'—',ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($approvalLabel[$ap['status']]??$ap['status'],ENT_QUOTES) ?>"
                    data-note="<?= htmlspecialchars($ap['catatan']??'Tidak ada catatan',ENT_QUOTES) ?>"
                    data-time="<?= $ap['approved_at']?date('d M Y H:i',strtotime($ap['approved_at'])):'Belum diproses' ?>"
                    data-color="<?= $approvalColor[$ap['status']]??'secondary' ?>">
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
              foreach ($approvals as $row) { if ($row['urutan']===$currentApprovalUrutan) { $defaultDetail=$row; break; } }
          }
          ?>
          <?php if ($defaultDetail): ?>
            <div class="mb-2"><div class="fs-13 text-muted">Tipe</div><div id="detailApprovalTitle" class="fw-700"><?= htmlspecialchars($tipeApprLabel[$defaultDetail['tipe_approver']]??$defaultDetail['tipe_approver']) ?></div></div>
            <div class="mb-2"><div class="fs-13 text-muted">Approver</div><div id="detailApprover" class="fw-700"><?= htmlspecialchars($defaultDetail['nama_approver']??'Belum ditetapkan') ?></div></div>
            <div class="mb-2"><div class="fs-13 text-muted">Status</div><div id="detailStatus"><span class="badge bg-<?= $approvalColor[$defaultDetail['status']]??'secondary' ?>"><?= $approvalLabel[$defaultDetail['status']]??ucfirst($defaultDetail['status']) ?></span></div></div>
            <div class="mb-2"><div class="fs-13 text-muted">Catatan</div><div id="detailNote"><?= htmlspecialchars($defaultDetail['catatan']??'Tidak ada catatan') ?></div></div>
            <div class="mb-0"><div class="fs-13 text-muted">Waktu</div><div id="detailTime"><?= $defaultDetail['approved_at']?date('d M Y H:i',strtotime($defaultDetail['approved_at'])):'Belum diproses' ?></div></div>
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
            <button type="submit" name="buat_approval" class="btn btn-primary btn-sm w-100"><i class="bi bi-send me-1"></i> Buat & Kirim</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── TAB: EVALUASI SWOT ── -->
<?php if ($ev['status']==='selesai'): ?>
<div class="tab-pane fade" id="swot">

<?php if ($isPic): ?>

  <?php if (!$swotSentAt): ?>
  <!-- ════════════════════════════════════════════════
       STATE 1 — PIC: setup pertanyaan & kirim
       ════════════════════════════════════════════════ -->
  <div class="row g-3">
    <div class="col-md-7">
      <div class="card mb-3">
        <div class="card-header fw-700"><i class="bi bi-clipboard-plus me-2"></i>Pertanyaan Custom Evaluasi</div>
        <div class="card-body">
          <p class="fs-13 text-muted mb-3">
            Form evaluasi sudah otomatis berisi 4 kuadran SWOT (Strength, Weakness, Opportunity, Threat) dan kolom Saran.
            Tambahkan pertanyaan spesifik untuk acara ini jika diperlukan.
            <strong>Pertanyaan tidak bisa diubah setelah form dikirim.</strong>
          </p>

          <!-- List pertanyaan yang sudah ditambah -->
          <?php if (!empty($swotQuestions)): ?>
            <div class="mb-3">
              <div class="fw-600 fs-13 mb-2">Pertanyaan tambahan (<?= count($swotQuestions) ?>):</div>
              <?php foreach ($swotQuestions as $i => $sq): ?>
                <div class="d-flex align-items-start gap-2 py-2 border-bottom">
                  <span class="badge bg-primary flex-shrink-0 mt-1"><?= $i+1 ?></span>
                  <div class="flex-grow-1 fs-13"><?= htmlspecialchars($sq['pertanyaan']) ?></div>
                  <form method="POST" class="flex-shrink-0">
                    <input type="hidden" name="del_swot_q" value="1">
                    <input type="hidden" name="question_id" value="<?= $sq['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger p-1" data-confirm="Hapus pertanyaan ini?" title="Hapus">
                      <i class="bi bi-trash fs-12"></i>
                    </button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="alert alert-light border fs-13 mb-3">
              <i class="bi bi-info-circle me-2 text-muted"></i>
              Belum ada pertanyaan tambahan. Form hanya berisi SWOT standar.
            </div>
          <?php endif; ?>

          <!-- Form tambah pertanyaan baru -->
          <form method="POST">
            <input type="hidden" name="add_swot_q" value="1">
            <div class="input-group">
              <input type="text" name="pertanyaan" class="form-control form-control-sm"
                     placeholder="Contoh: Apa kendala koordinasi antar divisi?" required
                     maxlength="500">
              <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Tambah
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-5">
      <!-- Preview form yang akan dilihat panitia -->
      <div class="card mb-3" style="border:1px dashed #cbd5e1">
        <div class="card-header bg-transparent fs-13 fw-600">
          <i class="bi bi-eye me-2 text-muted"></i>Preview form untuk panitia
        </div>
        <div class="card-body p-3">
          <div class="fs-12 text-muted mb-2">Field yang akan muncul di form panitia:</div>
          <div class="d-flex align-items-center gap-2 py-1 border-bottom fs-13">
            <i class="bi bi-lock-fill text-muted fs-12"></i>
            <span>Nama <span class="badge bg-light text-dark border ms-1 fs-11">Auto-filled</span></span>
          </div>
          <div class="d-flex align-items-center gap-2 py-1 border-bottom fs-13">
            <i class="bi bi-lock-fill text-muted fs-12"></i>
            <span>Bagian <span class="badge bg-light text-dark border ms-1 fs-11">Auto-filled</span></span>
          </div>
          <div class="d-flex align-items-center gap-2 py-1 border-bottom fs-13"><span class="text-success fw-600">S</span> Strength</div>
          <div class="d-flex align-items-center gap-2 py-1 border-bottom fs-13"><span class="text-danger fw-600">W</span> Weakness</div>
          <div class="d-flex align-items-center gap-2 py-1 border-bottom fs-13"><span class="text-primary fw-600">O</span> Opportunity</div>
          <div class="d-flex align-items-center gap-2 py-1 border-bottom fs-13"><span class="text-warning fw-600">T</span> Threat</div>
          <div class="d-flex align-items-center gap-2 py-1 border-bottom fs-13"><i class="bi bi-lightbulb text-muted fs-12"></i> Saran</div>
          <?php foreach ($swotQuestions as $i => $sq): ?>
            <div class="d-flex align-items-center gap-2 py-1 border-bottom fs-13">
              <span class="badge bg-primary fs-11"><?= $i+1 ?></span>
              <span class="text-truncate"><?= htmlspecialchars(substr($sq['pertanyaan'],0,50)).(strlen($sq['pertanyaan'])>50?'...':'') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Tombol kirim -->
      <div class="card border-0" style="background:linear-gradient(135deg,#1a3a5c,#245a8a);">
        <div class="card-body text-white p-4 text-center">
          <i class="bi bi-send-fill fs-2 mb-3 d-block opacity-75"></i>
          <div class="fw-700 mb-1">Siap mengirim form evaluasi?</div>
          <div class="fs-12 opacity-75 mb-3">
            Setelah dikirim, pertanyaan tidak bisa diubah.<br>
            <?= count($panitia) ?> panitia akan menerima notifikasi.
          </div>
          <form method="POST" onsubmit="return confirm('Kirim form SWOT ke <?= count($panitia) ?> panitia? Pertanyaan tidak bisa diubah setelah ini.');">
            <input type="hidden" name="send_swot" value="1">
            <button type="submit" class="btn btn-light fw-700 w-100">
              <i class="bi bi-send me-2"></i>Kirim SWOT ke Panitia
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ════════════════════════════════════════════════
       STATE 2 — PIC: tracking & analisis
       ════════════════════════════════════════════════ -->
  <div class="row g-3">

    <!-- Kiri: Tracking -->
    <div class="col-md-5">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="fw-700"><i class="bi bi-person-check me-2"></i>Tracking Pengisian</span>
          <div class="d-flex gap-1">
            <span class="badge bg-success"><?= $sudahCount ?? 0 ?> isi</span>
            <span class="badge bg-danger"><?= $belumCount ?? 0 ?> belum</span>
          </div>
        </div>

        <!-- Progress bar -->
        <?php $pct = count($swotTracking) > 0 ? round(($sudahCount??0)/count($swotTracking)*100) : 0; ?>
        <div class="px-3 pt-3">
          <div class="d-flex justify-content-between fs-12 mb-1">
            <span class="text-muted">Progress pengisian</span>
            <span class="fw-600"><?= $pct ?>%</span>
          </div>
          <div class="progress mb-3" style="height:8px;border-radius:4px">
            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
          </div>
        </div>

        <!-- List panitia -->
        <div class="list-group list-group-flush">
          <?php foreach ($swotTracking as $tr): ?>
            <div class="list-group-item d-flex align-items-center gap-3 py-2">
              <div class="flex-shrink-0">
                <?php if ($tr['submitted_at']): ?>
                  <i class="bi bi-check-circle-fill text-success fs-5"></i>
                <?php else: ?>
                  <i class="bi bi-clock text-danger fs-5"></i>
                <?php endif; ?>
              </div>
              <div class="flex-grow-1 overflow-hidden">
                <div class="fw-600 fs-13 text-truncate"><?= htmlspecialchars($tr['nama']) ?></div>
                <div class="fs-12 text-muted"><?= htmlspecialchars($tr['bagian'] ?? ($tr['peran_acara']==='pic'?'PIC':'—')) ?></div>
              </div>
              <div class="flex-shrink-0 text-end fs-12 text-muted">
                <?= $tr['submitted_at'] ? date('d M H:i', strtotime($tr['submitted_at'])) : '<span class="text-danger">Belum</span>' ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Tombol reminder WA -->
        <div class="card-footer bg-transparent pt-3">
          <?php
          $reminderMsg = "📋 *Reminder Evaluasi SWOT*\n"
                        . "Acara: *{$ev['judul']}*\n\n"
                        . "Mohon segera isi evaluasi SWOT acara ini.\n"
                        . "Buka workspace acara → Tab *Evaluasi SWOT*\n\n"
                        . BASE_URL . "/modules/events/workspace.php?id={$id}\n\n"
                        . "Sudah isi: " . ($sudahCount??0) . "/" . count($swotTracking) . " panitia. Terima kasih 🙏";
          $waLink = $ev['wa_group_link'] ?? '';
          ?>
          <button type="button" class="btn btn-success w-100"
                  onclick="kirimReminderWA()"
                  <?= !$waLink ? 'title="Atur link WA grup di tab Overview dulu"' : '' ?>>
            <i class="bi bi-whatsapp me-2"></i>Kirim Reminder WA
          </button>
          <div class="fs-12 text-muted text-center mt-1">
            Salin pesan reminder + buka grup WA
          </div>
        </div>
      </div>
    </div>

    <!-- Kanan: Analisis agregat -->
    <div class="col-md-7">
      <?php if (empty($swotData)): ?>
        <div class="card">
          <div class="card-body text-center text-muted py-5">
            <i class="bi bi-hourglass fs-2 d-block mb-2"></i>
            Belum ada panitia yang mengisi SWOT
          </div>
        </div>
      <?php else:
        $sKw = topKeywords($swotData, 'strength');
        $wKw = topKeywords($swotData, 'weakness');
        $oKw = topKeywords($swotData, 'opportunity');
        $tKw = topKeywords($swotData, 'threat');
      ?>
      <!-- SWOT Matrix -->
      <div class="card mb-3">
        <div class="card-header fw-700">
          <i class="bi bi-grid-3x3-gap me-2"></i>Analisis SWOT
          <span class="badge bg-secondary ms-1"><?= count($swotData) ?> responden</span>
        </div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-6">
              <div class="swot-box swot-s p-3">
                <div class="fw-700 small text-success mb-2">💪 Strength</div>
                <?php if (empty($sKw)): ?><span class="text-muted fs-12">Belum ada data</span>
                <?php else: foreach ($sKw as $w => $c): ?>
                  <span class="badge bg-success bg-opacity-20 text-success me-1 mb-1"><?= $w ?> (<?= $c ?>)</span>
                <?php endforeach; endif; ?>
              </div>
            </div>
            <div class="col-6">
              <div class="swot-box swot-w p-3">
                <div class="fw-700 small text-danger mb-2">⚠️ Weakness</div>
                <?php if (empty($wKw)): ?><span class="text-muted fs-12">Belum ada data</span>
                <?php else: foreach ($wKw as $w => $c): ?>
                  <span class="badge bg-danger bg-opacity-20 text-danger me-1 mb-1"><?= $w ?> (<?= $c ?>)</span>
                <?php endforeach; endif; ?>
              </div>
            </div>
            <div class="col-6">
              <div class="swot-box swot-o p-3">
                <div class="fw-700 small text-primary mb-2">🚀 Opportunity</div>
                <?php if (empty($oKw)): ?><span class="text-muted fs-12">Belum ada data</span>
                <?php else: foreach ($oKw as $w => $c): ?>
                  <span class="badge bg-primary bg-opacity-20 text-primary me-1 mb-1"><?= $w ?> (<?= $c ?>)</span>
                <?php endforeach; endif; ?>
              </div>
            </div>
            <div class="col-6">
              <div class="swot-box swot-t p-3">
                <div class="fw-700 small text-warning mb-2">🔥 Threat</div>
                <?php if (empty($tKw)): ?><span class="text-muted fs-12">Belum ada data</span>
                <?php else: foreach ($tKw as $w => $c): ?>
                  <span class="badge bg-warning bg-opacity-20 text-warning me-1 mb-1"><?= $w ?> (<?= $c ?>)</span>
                <?php endforeach; endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Saran panitia -->
      <?php $saranList = array_filter($swotData, fn($s) => !empty($s['saran']));
      if (!empty($saranList)): ?>
      <div class="card mb-3">
        <div class="card-header fw-700"><i class="bi bi-lightbulb me-2"></i>Saran dari Panitia</div>
        <div class="card-body">
          <?php foreach ($saranList as $sd): ?>
            <div class="bg-light rounded p-3 mb-2 fs-13">
              <i class="bi bi-chat-quote me-2 text-muted"></i>
              <?= htmlspecialchars($sd['saran']) ?>
              <?php if (!$sd['is_anonim'] && $sd['nama']): ?>
                <span class="text-muted fs-12"> — <?= htmlspecialchars($sd['nama']) ?></span>
              <?php else: ?>
                <span class="text-muted fs-12"> — <em>anonim</em></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Jawaban custom questions -->
      <?php if (!empty($swotQuestions) && !empty($allQAnswers)): ?>
      <div class="card">
        <div class="card-header fw-700"><i class="bi bi-patch-question me-2"></i>Pertanyaan Custom</div>
        <div class="card-body">
          <?php foreach ($swotQuestions as $i => $sq): ?>
            <div class="mb-4">
              <div class="fw-600 fs-13 mb-2">
                <span class="badge bg-primary me-2"><?= $i+1 ?></span>
                <?= htmlspecialchars($sq['pertanyaan']) ?>
              </div>
              <?php $answers = $allQAnswers[$sq['id']] ?? []; ?>
              <?php if (empty($answers)): ?>
                <div class="text-muted fs-12">Belum ada jawaban</div>
              <?php else: ?>
                <?php foreach ($answers as $ans): ?>
                  <div class="bg-light rounded p-2 mb-2 fs-13">
                    <?= htmlspecialchars($ans['jawaban']) ?>
                    <?php if (!$ans['is_anonim'] && $ans['nama']): ?>
                      <span class="text-muted fs-12"> — <?= htmlspecialchars($ans['nama']) ?></span>
                    <?php else: ?>
                      <span class="text-muted fs-12"> — <em>anonim</em></span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php endif; // end !empty swotData ?>
    </div>

  </div>
  <?php endif; // end $swotSentAt PIC view ?>

<?php else: ?>
  <!-- ════════════════════════════════════════════════
       VIEW PANITIA (bukan PIC)
       ════════════════════════════════════════════════ -->

  <?php if (!$swotSentAt): ?>
  <!-- Panitia: menunggu PIC -->
  <div class="text-center py-5">
    <i class="bi bi-hourglass-split fs-1 text-muted d-block mb-3"></i>
    <div class="fw-700 mb-1">Menunggu PIC membuka form evaluasi</div>
    <div class="text-muted fs-13">PIC sedang menyiapkan pertanyaan evaluasi SWOT.<br>Kamu akan mendapat notifikasi saat form sudah bisa diisi.</div>
  </div>

  <?php elseif (!$sudahSwot): ?>
  <!-- Panitia: belum isi, tampilkan form -->
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header fw-700">
          <i class="bi bi-pencil-square me-2"></i>Form Evaluasi SWOT — <?= htmlspecialchars($ev['judul']) ?>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="submit_swot" value="1">

            <!-- Identitas: auto-filled, read-only -->
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label fw-600">Nama</label>
                <input type="text" class="form-control bg-light"
                       value="<?= htmlspecialchars($myRole['nama'] ?? (currentUser()['nama'] ?? '')) ?>"
                       readonly disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-600">Bagian</label>
                <input type="text" class="form-control bg-light"
                       value="<?= htmlspecialchars($myRole['bagian'] ?? ($myRole['peran_acara']==='pic'?'PIC':'—')) ?>"
                       readonly disabled>
              </div>
            </div>

            <hr class="my-4">
            <div class="fs-13 text-muted mb-3">
              <i class="bi bi-info-circle me-1"></i>
              Semua field wajib diisi. Tulis <strong>–</strong> jika tidak ada yang ingin disampaikan.
            </div>

            <!-- SWOT 4 kuadran -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <div class="swot-box swot-s p-3">
                  <label class="form-label fw-700 text-success">💪 Strength</label>
                  <div class="fs-12 text-muted mb-2">Apa yang berjalan baik di acara ini?</div>
                  <textarea name="strength" class="form-control form-control-sm border-0 bg-transparent" rows="4"
                            placeholder="Tulis strength..." required></textarea>
                </div>
              </div>
              <div class="col-md-6">
                <div class="swot-box swot-w p-3">
                  <label class="form-label fw-700 text-danger">⚠️ Weakness</label>
                  <div class="fs-12 text-muted mb-2">Apa yang masih kurang atau perlu diperbaiki?</div>
                  <textarea name="weakness" class="form-control form-control-sm border-0 bg-transparent" rows="4"
                            placeholder="Tulis weakness..." required></textarea>
                </div>
              </div>
              <div class="col-md-6">
                <div class="swot-box swot-o p-3">
                  <label class="form-label fw-700 text-primary">🚀 Opportunity</label>
                  <div class="fs-12 text-muted mb-2">Potensi apa yang bisa dikembangkan ke depannya?</div>
                  <textarea name="opportunity" class="form-control form-control-sm border-0 bg-transparent" rows="4"
                            placeholder="Tulis opportunity..." required></textarea>
                </div>
              </div>
              <div class="col-md-6">
                <div class="swot-box swot-t p-3">
                  <label class="form-label fw-700 text-warning">🔥 Threat</label>
                  <div class="fs-12 text-muted mb-2">Kendala atau risiko yang perlu diwaspadai?</div>
                  <textarea name="threat" class="form-control form-control-sm border-0 bg-transparent" rows="4"
                            placeholder="Tulis threat..." required></textarea>
                </div>
              </div>
            </div>

            <!-- Saran -->
            <div class="mb-4">
              <label class="form-label fw-600">💡 Saran untuk acara berikutnya</label>
              <textarea name="saran" class="form-control" rows="3"
                        placeholder="Apa yang sebaiknya diperbaiki atau ditambahkan?" required></textarea>
            </div>

            <!-- Custom questions dari PIC -->
            <?php if (!empty($swotQuestions)): ?>
              <hr class="my-4">
              <div class="fw-700 mb-3">Pertanyaan dari PIC</div>
              <?php foreach ($swotQuestions as $i => $sq): ?>
                <div class="mb-4">
                  <label class="form-label fw-600">
                    <span class="badge bg-primary me-2"><?= $i+1 ?></span>
                    <?= htmlspecialchars($sq['pertanyaan']) ?>
                  </label>
                  <textarea name="cq_<?= $sq['id'] ?>" class="form-control" rows="3"
                            placeholder="Jawaban kamu..." required></textarea>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <!-- Anonim -->
            <div class="form-check mb-4">
              <input type="checkbox" name="is_anonim" id="isAnonim" class="form-check-input" value="1">
              <label for="isAnonim" class="form-check-label fs-13">
                Kirim secara anonim <span class="text-muted">(nama tidak akan ditampilkan ke PIC)</span>
              </label>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-700">
              <i class="bi bi-send me-2"></i>Kirim Evaluasi SWOT
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php else: ?>
  <div class="text-center py-4 mb-4">
    <div style="width:72px;height:72px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
      <i class="bi bi-check-circle-fill text-success fs-1"></i>
    </div>
    <div class="fw-700 fs-5 mb-1">Evaluasi sudah dikirim</div>
    <div class="text-muted fs-13">Terima kasih sudah mengisi evaluasi SWOT!</div>
    <?php if ($mySwotRow['submitted_at'] ?? null): ?>
      <div class="text-muted fs-12 mt-1">Dikirim pada <?= date('d M Y H:i', strtotime($mySwotRow['submitted_at'])) ?></div>
    <?php endif; ?>
  </div>

  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header fw-700"><i class="bi bi-eye me-2"></i>Jawaban Kamu</div>
        <div class="card-body">
          <div class="row g-2 mb-3">
            <div class="col-6"><div class="swot-box swot-s p-3"><div class="fw-700 small text-success mb-1">💪 Strength</div><div class="fs-13"><?= nl2br(htmlspecialchars($mySwotRow['strength']??'—')) ?></div></div></div>
            <div class="col-6"><div class="swot-box swot-w p-3"><div class="fw-700 small text-danger mb-1">⚠️ Weakness</div><div class="fs-13"><?= nl2br(htmlspecialchars($mySwotRow['weakness']??'—')) ?></div></div></div>
            <div class="col-6"><div class="swot-box swot-o p-3"><div class="fw-700 small text-primary mb-1">🚀 Opportunity</div><div class="fs-13"><?= nl2br(htmlspecialchars($mySwotRow['opportunity']??'—')) ?></div></div></div>
            <div class="col-6"><div class="swot-box swot-t p-3"><div class="fw-700 small text-warning mb-1">🔥 Threat</div><div class="fs-13"><?= nl2br(htmlspecialchars($mySwotRow['threat']??'—')) ?></div></div></div>
          </div>
          <div class="mb-3">
            <div class="fw-600 small mb-1">💡 Saran</div>
            <div class="fs-13 bg-light rounded p-3"><?= nl2br(htmlspecialchars($mySwotRow['saran']??'—')) ?></div>
          </div>
          <?php if (!empty($swotQuestions) && !empty($mySwotAnswers)): ?>
            <hr>
            <?php foreach ($swotQuestions as $i => $sq): ?>
              <div class="mb-3">
                <div class="fw-600 small mb-1"><span class="badge bg-primary me-1"><?= $i+1 ?></span><?= htmlspecialchars($sq['pertanyaan']) ?></div>
                <div class="fs-13 bg-light rounded p-3"><?= nl2br(htmlspecialchars($mySwotAnswers[$sq['id']] ?? '—')) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php endif;?>
<?php endif;?>

</div>
<?php endif;?>

<!-- ── TAB: EVALUASI (existing, kept as-is) ── -->
<div class="tab-pane fade" id="evaluasi">
<?php
$evalList = $pdo->prepare("SELECT ev2.*, (SELECT COUNT(DISTINCT user_id) FROM evaluasi_jawaban ej WHERE ej.evaluasi_id=ev2.id) AS jml_responden FROM event_evaluasi ev2 WHERE ev2.event_id=? ORDER BY ev2.created_at DESC");
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
    <p><?= $isPic?'Belum ada form evaluasi. Buat form untuk mengumpulkan feedback panitia.':'Belum ada form evaluasi dari PIC.' ?></p>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($evaluasiList as $ev2):
      $myJawab=$pdo->prepare("SELECT COUNT(*) FROM evaluasi_jawaban WHERE evaluasi_id=? AND user_id=?");
      $myJawab->execute([$ev2['id'],$_SESSION['user_id']]); $sudahIsiEval=(bool)$myJawab->fetchColumn();
      $pct=$totalPanitiaEval>0?round($ev2['jml_responden']/$totalPanitiaEval*100):0;
    ?>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <div class="fw-700 mb-1"><?= htmlspecialchars($ev2['judul']) ?></div>
          <?php if ($ev2['deskripsi']): ?><div class="fs-12 text-muted mb-2"><?= htmlspecialchars($ev2['deskripsi']) ?></div><?php endif; ?>
          <?php if ($ev2['deadline']): ?><div class="fs-12 mb-2"><i class="bi bi-clock me-1 text-warning"></i>Deadline: <?= date('d M Y',strtotime($ev2['deadline'])) ?></div><?php endif; ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between fs-12 mb-1"><span class="text-muted">Responden</span><span class="fw-600"><?= $ev2['jml_responden'] ?>/<?= $totalPanitiaEval ?></span></div>
            <div class="progress" style="height:6px;border-radius:3px"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div>
          </div>
          <div class="d-flex gap-2 mt-3">
            <?php if (!$sudahIsiEval && $ev2['is_active']): ?>
              <a href="<?= BASE_URL ?>/modules/evaluasi/fill.php?id=<?= $ev2['id'] ?>" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-pencil-square me-1"></i>Isi Evaluasi</a>
            <?php elseif ($sudahIsiEval): ?>
              <span class="btn btn-success btn-sm flex-grow-1 disabled"><i class="bi bi-check me-1"></i>Sudah Diisi</span>
            <?php endif; ?>
            <?php if ($isPic): ?>
              <a href="<?= BASE_URL ?>/modules/evaluasi/results.php?id=<?= $ev2['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart-line me-1"></i>Hasil</a>
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
        <input type="hidden" name="peran_acara" value="panitia_inti">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Bagian / Divisi</label>
            <select name="bagian_select" id="quickBagianSelect" class="form-select form-select-sm">
              <option value="">-- Pilih bagian --</option>
              <?php foreach ($panitiaBagianOptions as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
              <?php endforeach; ?>
              <option value="__custom__">Bagian lain...</option>
            </select>
            <input type="text" name="bagian" id="quickBagianCustom" class="form-control form-control-sm mt-2 d-none" placeholder="Ketik bagian lain">
          </div>
          <div class="mb-3">
            <label class="form-label">Cari & Pilih SDM</label>
            <input type="text" id="quickSearch" class="form-control form-control-sm" placeholder="Ketik nama atau divisi...">
          </div>
          <div class="mb-3" style="max-height:350px;overflow-y:auto;border:1px solid var(--border);border-radius:6px;padding:8px">
            <?php
            $sdmQuickList=$pdo->prepare("SELECT u.* FROM users u WHERE u.status='aktif' AND u.id NOT IN (SELECT user_id FROM event_panitia WHERE event_id=?) ORDER BY u.divisi,u.nama");
            $sdmQuickList->execute([$id]); $sdmList=$sdmQuickList->fetchAll();
            if (empty($sdmList)): ?>
              <div class="text-center py-4 text-muted fs-12"><i class="bi bi-inbox"></i> Semua SDM sudah terdaftar.</div>
            <?php else: foreach ($sdmList as $s): ?>
              <div class="form-check py-2 border-bottom quicksdm-item" data-name="<?= htmlspecialchars(strtolower($s['nama'])) ?>" data-divisi="<?= htmlspecialchars(strtolower($s['divisi']??'')) ?>">
                <input class="form-check-input sdm-check-quick" type="checkbox" name="user_ids[]" value="<?= $s['id'] ?>" id="sdmq<?= $s['id'] ?>">
                <label class="form-check-label w-100" for="sdmq<?= $s['id'] ?>">
                  <div class="fw-600 fs-13"><?= htmlspecialchars($s['nama']) ?></div>
                  <div class="fs-12 text-muted"><?= htmlspecialchars($s['divisi']??'—') ?> · <?= htmlspecialchars($s['jabatan']??'') ?></div>
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
<?php endif; ?>

</div>

<script>
// Approval table row selection
document.addEventListener('DOMContentLoaded', function() {
  const rows = document.querySelectorAll('.approval-table-row');
  const detailPanel = document.getElementById('approvalDetailPanel');
  function selectRow(row) {
    rows.forEach(r => r.classList.remove('selected'));
    row.classList.add('selected');
    if (!detailPanel) return;
    detailPanel.querySelector('#detailApprovalTitle').textContent = row.dataset.type||'—';
    detailPanel.querySelector('#detailApprover').textContent = row.dataset.approver||'—';
    detailPanel.querySelector('#detailStatus').innerHTML = `<span class="badge bg-${row.dataset.color}">${row.dataset.status}</span>`;
    detailPanel.querySelector('#detailNote').textContent = row.dataset.note||'—';
    detailPanel.querySelector('#detailTime').textContent = row.dataset.time||'—';
  }
  rows.forEach(row => row.addEventListener('click', () => selectRow(row)));
});

// Status update form
function submitStatus(s) {
  document.getElementById('statusInput').value = s;
  document.getElementById('statusForm').querySelector('[type=submit]').click();
}

// Invite modal
document.getElementById('quickSearch')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.quicksdm-item').forEach(item => {
    item.style.display = (item.dataset.name.includes(q)||item.dataset.divisi.includes(q)) ? '' : 'none';
  });
});
document.getElementById('quickBagianSelect')?.addEventListener('change', function() {
  const custom = document.getElementById('quickBagianCustom');
  if (this.value==='__custom__') { custom.classList.remove('d-none'); custom.focus(); }
  else { custom.classList.add('d-none'); custom.value=''; }
});
document.querySelectorAll('.sdm-check-quick').forEach(chk => {
  chk.addEventListener('change', () => {
    document.getElementById('quickCount').textContent = document.querySelectorAll('.sdm-check-quick:checked').length;
  });
});

// Reminder WA
function kirimReminderWA() {
  const waLink = <?= json_encode($ev['wa_group_link'] ?? '') ?>;
  const msg    = <?= json_encode($reminderMsg ?? '') ?>;

  if (!waLink) {
    alert('Link grup WA belum diatur.\nAtur terlebih dahulu di tab Overview → Grup WhatsApp.');
    return;
  }

  // Copy pesan ke clipboard
  navigator.clipboard.writeText(msg).then(() => {
    // Buka grup WA di tab baru
    window.open(waLink, '_blank');
    // Toast feedback
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 m-3 alert alert-success shadow';
    toast.style.cssText = 'z-index:9999;min-width:280px;';
    toast.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Pesan disalin! Paste di grup WA yang baru dibuka.';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  }).catch(() => {
    // Fallback: prompt manual copy
    prompt('Salin pesan ini lalu paste di grup WA:', msg);
    window.open(waLink, '_blank');
  });
}

// data-confirm global handler
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm)) e.preventDefault();
  });
});
</script>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>