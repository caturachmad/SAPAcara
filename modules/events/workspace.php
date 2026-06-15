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

// PR-05: Acara selesai → redirect ke archive.php (jika punya akses) atau dashboard (jika tidak)
if ($ev['status'] === 'selesai') {
    $canViewArchive = $isSA || isAdmin() || (($_SESSION['template_access'][$id] ?? false) === true);
    if ($canViewArchive) {
        header('Location: '.BASE_URL.'/modules/events/archive.php?id='.$id);
    } else {
        setFlash('Acara "'.$ev['judul'].'" sudah selesai dan diarsipkan oleh PIC.', 'info');
        header('Location: '.BASE_URL.'/modules/dashboard/select.php');
    }
    exit;
}

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

// ── POST: finalize event (PR-05, tombol di tab Evaluasi) ─────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['finalize_event']) && $isPic) {
    // Validasi server-side: harus sama dengan kondisi tombol di UI
    if (!$swotSentAt) {
        setFlash('Kirim form evaluasi ke panitia terlebih dahulu sebelum menyelesaikan acara.', 'danger');
        header("Location: ?id=$id#evaluasi"); exit;
    }
    if (!isset($belumCount) || $belumCount > 0) {
        $sisa = $belumCount ?? count($swotTracking);
        setFlash("Masih ada $sisa panitia yang belum mengisi evaluasi. Tunggu semua selesai sebelum menyelesaikan acara.", 'danger');
        header("Location: ?id=$id#evaluasi"); exit;
    }
    $pdo->prepare("UPDATE events SET status='selesai' WHERE id=?")->execute([$id]);
    setFlash('Acara berhasil diselesaikan. Kamu diarahkan ke halaman arsip.', 'success');
    header('Location: '.BASE_URL.'/modules/events/archive.php?id='.$id); exit;
}

// ── POST: update status ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status']) && $isPic) {
    $ns = $_POST['new_status'];
    if (in_array($ns,['selesai','ditolak'], true)) {
        $pdo->prepare("UPDATE events SET status=? WHERE id=?")->execute([$ns,$id]);
        // SWOT notification dikirim manual oleh PIC via tombol "Kirim SWOT", bukan otomatis
        setFlash('Status acara diperbarui.','success');
    } else {
        setFlash('Status tidak valid.', 'danger');
    }
    header("Location: ?id=$id"); exit;
}

// ── POST: toggle checklist ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_check'])) {
    if (!$isAnggota) { http_response_code(403); exit; }
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

// ── POST: hapus evaluasi (PIC) ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_evaluation']) && $isPic) {
    $evalId = (int)($_POST['evaluation_id'] ?? 0);
    $check = $pdo->prepare("SELECT event_id FROM event_evaluasi WHERE id=?");
    $check->execute([$evalId]);
    $row = $check->fetch();
    if ($row && $row['event_id'] === $id) {
        $pdo->prepare("DELETE FROM evaluasi_jawaban WHERE evaluasi_id=?")->execute([$evalId]);
        $pdo->prepare("DELETE FROM event_evaluasi WHERE id=?")->execute([$evalId]);
        setFlash('Evaluasi berhasil dihapus.', 'success');
    }
    header("Location: ?id=$id#evaluasi"); exit;
}

// ── POST: tambah custom SWOT question (PIC, sebelum dikirim) ─────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_swot_q']) && $isPic && !$swotSentAt) {
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    if ($pertanyaan) {
        $nextUrutan = count($swotQuestions) + 1;
        $pdo->prepare("INSERT INTO event_swot_questions (event_id, pertanyaan, urutan) VALUES (?,?,?)")
            ->execute([$id, $pertanyaan, $nextUrutan]);
    }
    header("Location: ?id=$id#evaluasi"); exit;
}

// ── POST: hapus custom SWOT question (PIC, sebelum dikirim) ──────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_swot_q']) && $isPic && !$swotSentAt) {
    $qid = (int)$_POST['question_id'];
    $pdo->prepare("DELETE FROM event_swot_questions WHERE id=? AND event_id=?")->execute([$qid, $id]);
    header("Location: ?id=$id#evaluasi"); exit;
}

// ── POST: kirim SWOT ke panitia (PIC) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_swot']) && $isPic && !$swotSentAt) {
    $pdo->prepare("UPDATE events SET swot_sent_at=NOW() WHERE id=?")->execute([$id]);
    foreach ($panitia as $p) {
        addNotif($pdo, $p['user_id'],
            '📋 Evaluasi Acara — ' . $ev['judul'],
            'PIC telah membuka form evaluasi acara ini. Mohon isi secepatnya.',
            BASE_URL . '/modules/events/workspace.php?id=' . $id,
            'info'
        );
    }
    setFlash('Form evaluasi berhasil dikirim ke ' . count($panitia) . ' panitia.', 'success');
    header("Location: ?id=$id#evaluasi"); exit;
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
    setFlash('Evaluasi berhasil dikirim. Terima kasih!', 'success');
    header("Location: ?id=$id#evaluasi"); exit;
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
$hasStarted = time() >= strtotime($ev['tanggal_mulai']);
$displayStatus = $ev['status'];
if ($ev['status'] === 'disetujui' && $hasStarted) {
    $displayStatus = 'berlangsung';
}
$workflowHint = $workflowStep[$displayStatus] ?? '';

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
        <span class="badge fs-12 py-2 px-3 <?= match($displayStatus) {
          'berlangsung'=>'bg-warning text-dark','selesai'=>'bg-success','ditolak'=>'bg-danger',default=>'bg-light text-dark'
        } ?>"><?= $statusLabel[$displayStatus] ?></span>
        <?php if ($sisaHari >= 0 && $ev['status']!=='selesai'): ?>
          <span class="badge fs-12 py-2 px-3 <?= $sisaHari<=3?'bg-danger':($sisaHari<=7?'bg-warning text-dark':'bg-primary') ?>">
            <?= $sisaHari===0?'Hari ini!':"$sisaHari hari lagi" ?>
          </span>
        <?php endif; ?>
        <?php /* PR-05: Tombol Selesaikan Acara dipindah ke tab Evaluasi */ ?>
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
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#evaluasi"><i class="bi bi-clipboard-check me-1"></i>Evaluasi</a></li>
</ul>

<div class="tab-content">

<!-- ── TAB: OVERVIEW ── -->
<div class="tab-pane fade show active" id="overview">

<?php
// ── Progress Wizard (hanya untuk PIC, selama event belum selesai) ────────────
if ($isPic && !in_array($ev['status'], ['selesai','ditolak'])):
  // Cek kondisi setiap step
  $levelApproverLabel = ['TK'=>'Manager TK','SD'=>'Manager SD','SMP'=>'Manager SMP','Umum'=>'Kepala Sekolah'];
  $hasProposalDoc = count(array_filter($files, fn($f) => in_array($f['file_type'],['proposal','rab']))) > 0;
  $hasApproval    = count($approvals) > 0;
  $approvedByMgr  = in_array($ev['status'], ['disetujui_manager','proposal_dibuat','rab_diajukan','perijinan','disetujui','berlangsung']);
  $hasPanitia     = count($panitia) > 1; // lebih dari PIC sendiri
  $hasWA          = !empty($ev['wa_group_link']);
  $hasDoc         = !empty($ev['link_dokumentasi']);
  $isFullyApproved = $ev['status'] === 'disetujui' || $displayStatus === 'berlangsung';

  $steps = [
    ['icon'=>'file-earmark-text','label'=>'Upload Proposal/RAB','desc'=>'Upload minimal satu dokumen proposal atau RAB','done'=>$hasProposalDoc,'tab'=>'dokumen','action'=>'Upload Dokumen','action_url'=>BASE_URL.'/modules/files/upload.php?event_id='.$id],
    ['icon'=>'send','label'=>'Ajukan ke '.($levelApproverLabel[$ev['level']]??'Manager'),'desc'=>'Ajukan proposal ke manager untuk mendapat persetujuan','done'=>$ev['status']!=='draft','tab'=>'approval','action'=>'Lihat Approval','action_url'=>null],
    ['icon'=>'person-check','label'=>'Tunggu Approval Manager','desc'=>'Manager akan meninjau dan menyetujui proposal','done'=>$approvedByMgr,'tab'=>null,'action'=>null,'action_url'=>null],
    ['icon'=>'people','label'=>'Undang Panitia','desc'=>'Tambahkan anggota panitia inti dan pendukung','done'=>$hasPanitia,'tab'=>'tim','action'=>'Kelola Tim','action_url'=>null],
    ['icon'=>'whatsapp','label'=>'Setup Grup WA & Dokumentasi','desc'=>'Atur link grup WhatsApp dan folder dokumentasi','done'=>$hasWA && $hasDoc,'tab'=>null,'action'=>null,'action_url'=>null],
    ['icon'=>'trophy','label'=>'Acara Siap Berjalan','desc'=>'Semua persiapan selesai, acara bisa dilaksanakan','done'=>$isFullyApproved,'tab'=>null,'action'=>null,'action_url'=>null],
  ];

  // Hitung step aktif (yang belum done, pertama)
  $activeStep = 0;
  foreach ($steps as $i => $s) { if (!$s['done']) { $activeStep = $i; break; } if ($i === array_key_last($steps)) $activeStep = $i; }
  $completedSteps = count(array_filter($steps, fn($s) => $s['done']));
  $totalSteps = count($steps);
?>
<div class="card mb-4" style="border:2px solid #e2e8f0;border-radius:14px">
  <div class="card-header d-flex align-items-center justify-content-between" style="background:linear-gradient(90deg,#f0f7ff,#fff);border-radius:12px 12px 0 0;border-bottom:1px solid #e2e8f0">
    <div>
      <div class="fw-700 fs-14"><i class="bi bi-map me-2 text-primary"></i>Persiapan Acara</div>
      <div class="fs-12 text-muted"><?= $completedSteps ?>/<?= $totalSteps ?> langkah selesai</div>
    </div>
    <div style="width:120px">
      <div class="progress" style="height:8px;border-radius:4px">
        <div class="progress-bar bg-primary" style="width:<?= round($completedSteps/$totalSteps*100) ?>%"></div>
      </div>
    </div>
  </div>
  <div class="card-body p-0">
    <?php foreach ($steps as $i => $step): ?>
    <div class="d-flex align-items-start gap-3 px-4 py-3 <?= $i < $totalSteps-1 ? 'border-bottom' : '' ?>"
         style="<?= $i === $activeStep && !$step['done'] ? 'background:#f8faff' : '' ?>">
      <!-- Icon lingkaran -->
      <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle"
           style="width:36px;height:36px;background:<?= $step['done'] ? '#d1fae5' : ($i===$activeStep ? '#dbeafe' : '#f1f5f9') ?>;margin-top:2px">
        <?php if ($step['done']): ?>
          <i class="bi bi-check-lg" style="color:#059669;font-size:.95rem"></i>
        <?php elseif ($i===$activeStep): ?>
          <i class="bi bi-<?= $step['icon'] ?>" style="color:#2563eb;font-size:.85rem"></i>
        <?php else: ?>
          <span style="color:#94a3b8;font-size:.8rem;font-weight:700"><?= $i+1 ?></span>
        <?php endif; ?>
      </div>
      <!-- Label & desc -->
      <div class="flex-grow-1">
        <div class="fw-600 fs-13 <?= $step['done'] ? 'text-muted text-decoration-line-through' : ($i===$activeStep ? 'text-primary' : '') ?>"><?= $step['label'] ?></div>
        <div class="fs-12 text-muted"><?= $step['desc'] ?></div>
      </div>
      <!-- Action -->
      <?php if (!$step['done'] && $i===$activeStep): ?>
        <?php if ($step['action_url']): ?>
          <a href="<?= $step['action_url'] ?>" class="btn btn-primary btn-sm flex-shrink-0"><?= $step['action'] ?> <i class="bi bi-arrow-right ms-1"></i></a>
        <?php elseif ($step['tab']): ?>
          <button type="button" class="btn btn-primary btn-sm flex-shrink-0"
                  onclick="switchToTab('<?= $step['tab'] ?>')"><?= $step['action'] ?> <i class="bi bi-arrow-right ms-1"></i></button>
        <?php endif; ?>
      <?php elseif ($step['done']): ?>
        <span class="badge bg-success flex-shrink-0" style="align-self:center">Selesai</span>
      <?php else: ?>
        <span class="badge bg-light text-muted border flex-shrink-0" style="align-self:center">Menunggu</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($completedSteps < 3): // Hint status approval ?>
  <div class="card-footer fs-12 text-muted" style="background:#f8fafc;border-radius:0 0 12px 12px">
    <i class="bi bi-info-circle me-1 text-primary"></i>
    <?= htmlspecialchars($workflowHint) ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

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

      <?php
      // PR-05: Referensi Template card — tampil jika acara ini punya template_dari_event_id
      if (!empty($ev['template_dari_event_id'])):
          $tmplStmt = $pdo->prepare("SELECT id, judul, level, tanggal_mulai FROM events WHERE id=? AND status='selesai'");
          $tmplStmt->execute([$ev['template_dari_event_id']]);
          $tmplEv = $tmplStmt->fetch();
          if ($tmplEv):
              // Grant session access so user can open archive
              $_SESSION['template_access'][$tmplEv['id']] = true;
      ?>
      <div class="card mb-3" style="border:1px dashed #fbbf24;background:#fffbeb">
        <div class="card-body py-2 px-3">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-bookmark-star-fill text-warning fs-5 flex-shrink-0"></i>
            <div class="flex-grow-1 overflow-hidden">
              <div class="fw-700 fs-13">📎 Referensi Template</div>
              <div class="text-truncate fs-12 text-muted"><?= htmlspecialchars($tmplEv['judul']) ?></div>
              <div class="fs-12 text-muted"><?= $tmplEv['level'] ?> &bull; <?= date('d M Y', strtotime($tmplEv['tanggal_mulai'])) ?></div>
            </div>
            <a href="<?= BASE_URL ?>/modules/events/archive.php?id=<?= $tmplEv['id'] ?>"
               target="_blank" class="btn btn-sm btn-warning flex-shrink-0">
              <i class="bi bi-archive me-1"></i>Lihat Arsip
            </a>
          </div>
        </div>
      </div>
      <?php endif; endif; ?>

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
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
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
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
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
                    <form method="POST" action="<?= BASE_URL ?>/modules/files/delete.php" style="display:inline">
                      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                      <input type="hidden" name="id" value="<?= $f['id'] ?>">
                      <input type="hidden" name="event_id" value="<?= $id ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger ms-1"
                              data-confirm="Hapus file ini?"><i class="bi bi-trash"></i></button>
                    </form>
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
  <?php
  // Group panitia by bagian, mark PJ per bagian
  $picList   = array_values(array_filter($panitia, fn($p) => $p['peran_acara'] === 'pic'));
  $nonPicArr = array_values(array_filter($panitia, fn($p) => $p['peran_acara'] !== 'pic'));
  $bagianMap = [];
  foreach ($nonPicArr as $p) {
      $bag = trim($p['bagian'] ?? '') ?: 'Umum';
      $bagianMap[$bag][] = $p;
  }
  ksort($bagianMap);
  foreach ($bagianMap as $bag => &$members) {
      $pjSet = false;
      foreach ($members as &$m) {
          $m['is_pj'] = false;
          if (!$pjSet && !empty($m['is_event_admin'])) { $m['is_pj'] = true; $pjSet = true; }
      }
      unset($m);
      if (!$pjSet && !empty($members)) { $members[0]['is_pj'] = true; }
      usort($members, fn($a, $b) => ($b['is_pj'] ? 1 : 0) - ($a['is_pj'] ? 1 : 0));
  }
  unset($members);
  ?>

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

  <?php if (empty($panitia)): ?>
    <div class="empty-state">
      <i class="bi bi-person-plus"></i>
      <p>Belum ada panitia. Undang anggota tim untuk mulai.</p>
    </div>
  <?php else: ?>

  <!-- PIC Row -->
  <?php if (!empty($picList)): ?>
  <div class="card mb-3" style="border-left:4px solid #ef4444">
    <div class="card-body py-2 px-3">
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge bg-danger px-2">PIC</span>
        <span class="fs-11 fw-600 text-muted text-uppercase" style="letter-spacing:.05em">Penanggung Jawab Acara</span>
      </div>
      <div class="d-flex flex-wrap gap-3">
        <?php foreach ($picList as $p): ?>
        <div class="d-flex align-items-center gap-2">
          <div class="avatar avatar-sm"><?= strtoupper(substr($p['nama'],0,2)) ?></div>
          <div>
            <span class="fw-700 fs-14"><?= htmlspecialchars($p['nama']) ?></span>
            <?php if ($p['jabatan'] ?? ''): ?><span class="text-muted fs-12 ms-1">· <?= htmlspecialchars($p['jabatan']) ?></span><?php endif; ?>
            <i class="bi bi-<?= $p['status_konfirmasi']==='bersedia'?'check-circle-fill text-success':($p['status_konfirmasi']==='tidak_bisa'?'x-circle-fill text-danger':'clock-history text-warning') ?> ms-2 fs-13" title="<?= $konfirmLabel[$p['status_konfirmasi']] ?>"></i>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Bagian / Jobdesk Groups -->
  <?php if (!empty($bagianMap)): ?>
  <div class="fs-12 text-muted fw-600 text-uppercase mb-2 ps-1" style="letter-spacing:.05em">
    <i class="bi bi-diagram-3 me-1"></i>Susunan Per Bagian
    <span class="badge bg-secondary ms-1"><?= count($bagianMap) ?> jobdesk</span>
  </div>
  <?php foreach ($bagianMap as $bagian => $members):
    $pjMember = null;
    foreach ($members as $m) { if (!empty($m['is_pj'])) { $pjMember = $m; break; } }
  ?>
  <div class="card mb-2">
    <div class="card-body py-2 px-3">
      <div class="d-flex align-items-start gap-3 flex-wrap">
        <!-- Bagian label -->
        <div class="flex-shrink-0 pt-1">
          <span class="badge bg-primary fw-600 px-3 py-2 fs-12" style="min-width:88px;text-align:center"><?= htmlspecialchars($bagian) ?></span>
          <div class="fs-11 text-muted text-center mt-1"><?= count($members) ?> orang</div>
        </div>
        <!-- Members list -->
        <div class="d-flex flex-wrap gap-2 align-items-center flex-grow-1 py-1">
          <?php foreach ($members as $m): ?>
          <div class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded"
               style="background:<?= !empty($m['is_pj']) ? '#fffbeb' : '#f8fafc' ?>;border:1px solid <?= !empty($m['is_pj']) ? '#fbbf24' : '#e2e8f0' ?>">
            <div class="avatar" style="width:24px;height:24px;font-size:.65rem;flex-shrink:0"><?= strtoupper(substr($m['nama'],0,1)) ?></div>
            <span class="fw-600 fs-13"><?= htmlspecialchars($m['nama']) ?></span>
            <?php if (!empty($m['is_pj'])): ?>
              <span class="badge bg-warning text-dark fs-11 px-1">PJ</span>
            <?php endif; ?>
            <i class="bi bi-<?= $m['status_konfirmasi']==='bersedia'?'check-circle-fill text-success':($m['status_konfirmasi']==='tidak_bisa'?'x-circle-fill text-danger':'clock-history text-warning') ?>"
               style="font-size:.78rem" title="<?= $konfirmLabel[$m['status_konfirmasi']] ?>"></i>
            <?php if ($isEventAdmin && $m['peran_acara']!=='pic'): ?>
            <div class="d-inline-flex gap-1 ms-1 border-start ps-1">
              <form method="POST" action="<?= BASE_URL ?>/modules/panitia/toggle_admin.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <input type="hidden" name="event_id" value="<?= $id ?>">
                <button type="submit" class="btn p-0 border-0 bg-transparent"
                        data-confirm="<?= !empty($m['is_event_admin'])?'Cabut jabatan Event Admin dari':'Jadikan' ?> <?= htmlspecialchars($m['nama']) ?> sebagai Event Admin?"
                        style="width:18px;height:18px;line-height:1" title="<?= !empty($m['is_event_admin'])?'Cabut Admin':'Jadikan Admin' ?>">
                  <i class="bi bi-star<?= !empty($m['is_event_admin'])?'-fill text-warning':' text-muted' ?>" style="font-size:.72rem"></i>
                </button>
              </form>
              <form method="POST" action="<?= BASE_URL ?>/modules/panitia/remove.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <input type="hidden" name="event_id" value="<?= $id ?>">
                <button type="submit" class="btn p-0 border-0 bg-transparent"
                        data-confirm="Keluarkan <?= htmlspecialchars($m['nama']) ?> dari panitia?"
                        style="width:18px;height:18px;line-height:1" title="Hapus dari panitia">
                  <i class="bi bi-person-x text-danger" style="font-size:.72rem"></i>
                </button>
              </form>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <!-- PJ label ringkas -->
        <?php if ($pjMember): ?>
        <div class="flex-shrink-0 fs-12 text-muted align-self-center">
          PJ: <span class="fw-600 text-dark"><?= htmlspecialchars($pjMember['nama']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Legenda -->
  <div class="d-flex gap-3 mt-3 fs-12 text-muted align-items-center flex-wrap">
    <span><i class="bi bi-check-circle-fill text-success me-1"></i>Bersedia</span>
    <span><i class="bi bi-x-circle-fill text-danger me-1"></i>Tidak Bisa</span>
    <span><i class="bi bi-clock-history text-warning me-1"></i>Menunggu</span>
    <span><span class="badge bg-warning text-dark me-1">PJ</span>Penanggung Jawab Bagian</span>
    <?php if ($isEventAdmin): ?>
    <span><i class="bi bi-star-fill text-warning me-1"></i>Event Admin</span>
    <?php endif; ?>
  </div>

  <?php endif; // end if panitia ?>
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
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
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
  <?php
  // Cek apakah sudah ada pengajuan ke manager untuk level ini
  $levelToTipe = ['TK'=>'manager_tk','SD'=>'manager_sd','SMP'=>'manager_smp','Umum'=>'kepala_sekolah'];
  $tipeManagerEvent = $levelToTipe[$ev['level']] ?? null;
  $levelApproverLabelLocal = ['TK'=>'Manager TK','SD'=>'Manager SD','SMP'=>'Manager SMP','Umum'=>'Kepala Sekolah'];
  $namaApproverTarget = $levelApproverLabelLocal[$ev['level']] ?? 'Manager';

  $sudahDiajukanKeManager = false;
  foreach ($approvals as $ap) {
    if ($ap['tipe_approver'] === $tipeManagerEvent) { $sudahDiajukanKeManager = true; break; }
  }

  // Cek dokumen proposal/RAB sudah ada
  $pFilesQ = $pdo->prepare("SELECT COUNT(*) FROM event_files WHERE event_id=? AND file_type IN ('proposal','rab')");
  $pFilesQ->execute([$id]); $jumlahDokProposal = (int)$pFilesQ->fetchColumn();
  ?>

  <!-- Info alur approval -->
  <div class="card mb-3" style="border-left:4px solid #2563eb">
    <div class="card-body py-3">
      <div class="d-flex align-items-start gap-3">
        <i class="bi bi-diagram-3 text-primary fs-4 flex-shrink-0 mt-1"></i>
        <div>
          <div class="fw-700 mb-1">Alur Approval Acara Level <?= htmlspecialchars($ev['level']) ?></div>
          <div class="fs-13 text-muted">Acara level <strong><?= htmlspecialchars($ev['level']) ?></strong> membutuhkan persetujuan dari <strong><?= htmlspecialchars($namaApproverTarget) ?></strong>. Upload proposal/RAB terlebih dahulu, lalu ajukan.</div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($ev['status']==='draft' && !$sudahDiajukanKeManager): ?>
  <!-- Belum diajukan ke manager -->
  <div class="card mb-3 border-warning">
    <div class="card-body">
      <div class="fw-700 mb-2"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Belum Diajukan ke <?= htmlspecialchars($namaApproverTarget) ?></div>
      <?php if ($jumlahDokProposal === 0): ?>
        <div class="alert alert-warning fs-13 mb-3">
          <i class="bi bi-folder2 me-2"></i>Upload dokumen <strong>Proposal</strong> atau <strong>RAB</strong> terlebih dahulu sebelum mengajukan.
          <a href="<?= BASE_URL ?>/modules/files/upload.php?event_id=<?= $id ?>" class="btn btn-sm btn-warning ms-3">
            <i class="bi bi-upload me-1"></i>Upload Sekarang
          </a>
        </div>
      <?php else: ?>
        <div class="fs-13 text-muted mb-3">
          Dokumen tersedia (<?= $jumlahDokProposal ?> file). Klik tombol di bawah untuk mengajukan proposal ke <?= htmlspecialchars($namaApproverTarget) ?>.
        </div>
        <form method="POST" action="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $id ?>">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="submit_to_manager" value="1">
          <button type="submit" class="btn btn-primary" data-confirm="Ajukan ke <?= htmlspecialchars($namaApproverTarget) ?>? Pastikan dokumen sudah lengkap.">
            <i class="bi bi-send-plus me-2"></i>Ajukan ke <?= htmlspecialchars($namaApproverTarget) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-7">
      <div class="card">
        <div class="card-header"><i class="bi bi-diagram-3"></i> Riwayat Approval</div>
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
      <?php if (isSuperAdmin()): ?>
      <div class="card">
        <div class="card-header"><i class="bi bi-shield-lock"></i> Tambah Approval Manual <small class="text-muted">(Superadmin)</small></div>
        <div class="card-body">
          <form method="POST" action="<?= BASE_URL ?>/modules/approvals/">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
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
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

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
              <button type="button" class="btn btn-outline-danger btn-sm delete-eval-button" data-evaluation-id="<?= $ev2['id'] ?>" data-evaluation-title="<?= htmlspecialchars($ev2['judul'], ENT_QUOTES) ?>">
                <i class="bi bi-trash me-1"></i>Hapus
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
// PR-05: Tombol Selesaikan Acara — muncul di tab Evaluasi
// Kondisi: isPic, swotSentAt ada, semua panitia sudah submit SWOT (belumCount === 0)
if ($isPic && $swotSentAt !== null && isset($belumCount) && $belumCount === 0 && in_array($ev['status'], ['disetujui','berlangsung'])):
?>
<hr class="my-4">
<div class="card border-success">
  <div class="card-body d-flex align-items-center gap-3 flex-wrap">
    <div class="flex-grow-1">
      <div class="fw-700 text-success fs-15"><i class="bi bi-flag-fill me-2"></i>Semua evaluasi sudah masuk!</div>
      <div class="fs-13 text-muted">
        Semua <?= count($swotTracking) ?> panitia sudah mengisi SWOT.
        Klik tombol di bawah untuk menyelesaikan acara dan menyimpannya ke arsip.
      </div>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <button name="finalize_event" type="submit" class="btn btn-success px-4 fw-700"
              data-confirm="Selesaikan acara ini? Workspace akan ditutup dan acara disimpan ke arsip. Tindakan tidak bisa dibatalkan.">
        <i class="bi bi-flag me-2"></i>Selesaikan &amp; Arsipkan
      </button>
    </form>
  </div>
</div>
<?php elseif ($isPic && in_array($ev['status'], ['disetujui','berlangsung'])): ?>
<hr class="my-4">
<div class="alert alert-light border fs-13 text-muted">
  <i class="bi bi-info-circle me-2"></i>
  Tombol <strong>Selesaikan Acara</strong> akan muncul di sini setelah form SWOT dikirim ke panitia dan semua panitia sudah mengisi.
</div>
<?php endif; ?>
</div>

<!-- Modal Quick Invite Panitia -->
<?php if ($isPic || $isEventAdmin): ?>
<div class="modal fade" id="deleteEvalModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Hapus Evaluasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Kamu akan menghapus evaluasi ini. Proses ini tidak bisa dikembalikan.</p>
        <p class="fw-600" id="deleteEvalTitle">-</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteEvalBtn">
          <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
          <span class="btn-text">Hapus</span>
        </button>
      </div>
    </div>
  </div>
</div>
<form method="POST" id="deleteEvalForm" class="d-none">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <input type="hidden" name="delete_evaluation" value="1">
  <input type="hidden" name="evaluation_id" id="evaluationIdInput" value="0">
</form>
<div class="modal fade" id="modalInviteQuick" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-700"><i class="bi bi-person-plus me-2"></i>Undang Panitia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>/modules/panitia/bulk_invite.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
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

// Fix 4: Switch tab dari wizard progress (bukan pakai data-bs-toggle yang kadang tidak trigger)
function switchToTab(tabId) {
  const navLink = document.querySelector('.nav-link[href="#' + tabId + '"]');
  if (navLink) {
    const bsTab = bootstrap.Tab.getOrCreateInstance(navLink);
    bsTab.show();
    // Scroll ke area tabs
    const tabBar = document.getElementById('workspaceTabs');
    if (tabBar) tabBar.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// Status update form
function submitStatus(s) {
  document.getElementById('statusInput').value = s;
  document.getElementById('statusSubmitBtn').click();
}
function submitStatusConfirm(s, msg) {
  showConfirmModal(msg, function() {
    document.getElementById('statusInput').value = s;
    document.getElementById('statusSubmitBtn').click();
  });
}

// Evaluasi delete flow
let deleteEvalModal;
document.addEventListener('DOMContentLoaded', function() {
  deleteEvalModal = new bootstrap.Modal(document.getElementById('deleteEvalModal'));
  document.querySelectorAll('.delete-eval-button').forEach(btn => {
    btn.addEventListener('click', () => {
      const evalId = btn.dataset.evaluationId;
      const title = btn.dataset.evaluationTitle;
      document.getElementById('deleteEvalTitle').textContent = title;
      document.getElementById('evaluationIdInput').value = evalId;
      deleteEvalModal.show();
    });
  });
  document.getElementById('confirmDeleteEvalBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.querySelector('.spinner-border').classList.remove('d-none');
    btn.querySelector('.btn-text').textContent = 'Menghapus...';
    document.getElementById('deleteEvalForm').submit();
  });
});

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
    showToast('Link grup WA belum diatur. Atur terlebih dahulu di tab Overview → Grup WhatsApp.', 'warning');
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

// data-confirm: ditangani oleh modal di footer.php

// PR-01: Redirect staff yang coba akses #approval via URL langsung
(function() {
  const isPic = <?= $isPic ? 'true' : 'false' ?>;
  if (!isPic && window.location.hash === '#approval') {
    window.location.hash = '#overview';
    // Activate overview tab
    const overviewLink = document.querySelector('.nav-link[href="#overview"]');
    if (overviewLink) {
      const bsTab = bootstrap.Tab.getOrCreateInstance(overviewLink);
      bsTab.show();
    }
  }
})();

function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `position-fixed bottom-0 end-0 m-3 alert alert-${type} shadow`;
  toast.style.cssText = 'z-index:9999;min-width:280px;';
  toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : type === 'warning' ? 'exclamation-triangle-fill' : 'info-circle-fill'} me-2"></i>${message}`;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 4500);
}
</script>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>