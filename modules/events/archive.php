<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/modules/dashboard/select.php'); exit; }

// Ambil data event
$stmt = $pdo->prepare("SELECT e.*, u.nama AS nama_pic FROM events e LEFT JOIN users u ON u.id=e.pic_id WHERE e.id=? AND e.status='selesai'");
$stmt->execute([$id]);
$ev = $stmt->fetch();

if (!$ev) {
    setFlash('Acara tidak ditemukan atau belum selesai.', 'warning');
    header('Location: ' . BASE_URL . '/modules/dashboard/select.php');
    exit;
}

// Cek otorisasi akses
$isAdmin      = isAdmin() || isSuperAdmin();
$templateAccess = ($_SESSION['template_access'][$id] ?? false) === true;

if (!$isAdmin && !$templateAccess) {
    setFlash('Kamu tidak memiliki akses ke halaman ini.', 'danger');
    header('Location: ' . BASE_URL . '/modules/dashboard/select.php');
    exit;
}

// ── POST: toggle is_template (admin only) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_template']) && $isAdmin) {
    $newVal = $ev['is_template'] ? 0 : 1;
    $notes  = trim($_POST['template_notes'] ?? $ev['template_notes'] ?? '');
    $pdo->prepare("UPDATE events SET is_template=?, template_notes=? WHERE id=?")
        ->execute([$newVal, $notes, $id]);
    setFlash($newVal ? '✅ Acara ini ditandai sebagai template.' : 'Template dihapus.', $newVal ? 'success' : 'secondary');
    header("Location: ?id=$id"); exit;
}

// ── Data ──
// Files
$filesStmt = $pdo->prepare("SELECT f.*, u.nama AS uploader FROM event_files f LEFT JOIN users u ON u.id=f.uploaded_by WHERE f.event_id=? ORDER BY f.file_type, f.created_at DESC");
$filesStmt->execute([$id]);
$files = $filesStmt->fetchAll();

// Panitia
$panitiaStmt = $pdo->prepare("SELECT ep.*, u.nama FROM event_panitia ep JOIN users u ON u.id=ep.user_id WHERE ep.event_id=? ORDER BY ep.peran_acara, u.nama");
$panitiaStmt->execute([$id]);
$panitia = $panitiaStmt->fetchAll();

// SWOT agregat
$swotStmt = $pdo->prepare("SELECT sw.*, u.nama FROM event_swot sw LEFT JOIN users u ON u.id=sw.user_id WHERE sw.event_id=?");
$swotStmt->execute([$id]);
$swotData = $swotStmt->fetchAll();

// SWOT questions
$swotQsStmt = $pdo->prepare("SELECT * FROM event_swot_questions WHERE event_id=? ORDER BY urutan");
$swotQsStmt->execute([$id]);
$swotQuestions = $swotQsStmt->fetchAll();

// Custom Q answers
$allQAnswers = [];
if (!empty($swotQuestions) && !empty($swotData)) {
    foreach ($swotQuestions as $sq) {
        $aqQ = $pdo->prepare("SELECT ea.jawaban, u.nama, sw.is_anonim FROM event_swot_answers ea JOIN event_swot sw ON sw.id=ea.swot_id LEFT JOIN users u ON u.id=sw.user_id WHERE ea.question_id=?");
        $aqQ->execute([$sq['id']]);
        $allQAnswers[$sq['id']] = $aqQ->fetchAll();
    }
}

// Evaluasi count
$evalCountStmt = $pdo->prepare("SELECT COUNT(*) FROM event_evaluasi WHERE event_id=?");
$evalCountStmt->execute([$id]);
$evalCount = (int)$evalCountStmt->fetchColumn();

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

$fileTypeLabel = ['rab'=>'RAB','rundown'=>'Rundown','proposal'=>'Proposal','perijinan'=>'Perijinan',
  'jobdesk'=>'Jobdesk','undangan'=>'Undangan','lainnya'=>'Lainnya'];
$fileTypeIcon  = ['rab'=>'cash-stack','rundown'=>'list-task','proposal'=>'file-text','perijinan'=>'shield-check',
  'jobdesk'=>'person-workspace','undangan'=>'envelope','lainnya'=>'file-earmark'];

$pageTitle = 'Arsip: ' . $ev['judul'];
require_once __DIR__ . '/../../includes/layout/header.php';
?>

<!-- Header arsip -->
<div class="card mb-4" style="background:linear-gradient(135deg,#1a3a5c,#245a8a);border:none">
  <div class="card-body px-4 py-3">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-3">
        <a href="<?= BASE_URL ?>/modules/events/index.php" class="back-btn"
           style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.2);color:#fff">
          <i class="bi bi-arrow-left"></i>
        </a>
        <div>
          <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <span class="badge bg-success">Selesai</span>
            <span class="badge bg-light text-dark"><?= $ev['level'] ?></span>
            <?php if ($ev['is_template']): ?>
              <span class="badge bg-warning text-dark"><i class="bi bi-bookmark-star me-1"></i>Template Tersedia</span>
            <?php endif; ?>
          </div>
          <h5 class="text-white fw-800 mb-0"><?= htmlspecialchars($ev['judul']) ?></h5>
          <div class="mt-1 fs-12" style="color:rgba(255,255,255,.7)">
            <i class="bi bi-calendar3 me-1"></i>
            <?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?>
            <?= $ev['tanggal_mulai'] !== $ev['tanggal_selesai'] ? ' – ' . date('d M Y', strtotime($ev['tanggal_selesai'])) : '' ?>
            · PIC: <?= htmlspecialchars($ev['nama_pic'] ?? '—') ?>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php if ($isAdmin): ?>
          <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="toggle_template" value="1">
            <?php if (!$ev['is_template']): ?>
              <div class="input-group input-group-sm">
                <input type="text" name="template_notes" class="form-control"
                       placeholder="Catatan mengapa layak jadi template..." style="min-width:220px">
                <button type="submit" class="btn btn-warning">
                  <i class="bi bi-bookmark-star me-1"></i>Jadikan Template
                </button>
              </div>
            <?php else: ?>
              <button type="submit" class="btn btn-sm btn-outline-light"
                      data-confirm="Lepas status template dari acara ini?">
                <i class="bi bi-bookmark-x me-1"></i>Lepas Template
              </button>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Ringkasan -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body py-3">
        <div class="fw-800 fs-3 text-primary"><?= count($panitia) ?></div>
        <div class="fs-12 text-muted">Total Panitia</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body py-3">
        <div class="fw-800 fs-3 text-success"><?= count($swotData) ?></div>
        <div class="fs-12 text-muted">Evaluasi SWOT Masuk</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body py-3">
        <div class="fw-800 fs-3 text-warning"><?= $evalCount ?></div>
        <div class="fs-12 text-muted">Form Evaluasi</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body py-3">
        <div class="fw-800 fs-3 text-info"><?= count($files) ?></div>
        <div class="fs-12 text-muted">File Acara</div>
      </div>
    </div>
  </div>
</div>

<?php if ($ev['template_notes']): ?>
<div class="alert alert-warning d-flex gap-2 mb-4">
  <i class="bi bi-bookmark-star-fill flex-shrink-0 mt-1"></i>
  <div>
    <strong>Catatan Template:</strong> <?= htmlspecialchars($ev['template_notes']) ?>
  </div>
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#arc-files">
    <i class="bi bi-folder2-open me-1"></i>File Acara
  </a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#arc-swot">
    <i class="bi bi-grid-3x3-gap me-1"></i>Evaluasi SWOT
  </a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#arc-tim">
    <i class="bi bi-people me-1"></i>Tim Panitia
  </a></li>
</ul>

<div class="tab-content">

<!-- Tab Files -->
<div class="tab-pane fade show active" id="arc-files">
  <?php if (empty($files)): ?>
    <div class="empty-state"><i class="bi bi-folder2"></i><p>Tidak ada file untuk acara ini</p></div>
  <?php else:
    $grouped = [];
    foreach ($files as $f) $grouped[$f['file_type']][] = $f;
    foreach ($grouped as $type => $typeFiles): ?>
    <div class="card mb-3">
      <div class="card-header">
        <i class="bi bi-<?= $fileTypeIcon[$type] ?? 'file-earmark' ?>"></i>
        <?= $fileTypeLabel[$type] ?? ucfirst($type) ?>
        <span class="badge bg-secondary ms-1"><?= count($typeFiles) ?></span>
      </div>
      <div class="card-body p-0">
        <?php foreach ($typeFiles as $f): ?>
          <div class="d-flex align-items-center px-3 py-2 border-bottom gap-3">
            <i class="bi bi-<?= $fileTypeIcon[$f['file_type']] ?? 'file-earmark' ?> text-primary fs-5 flex-shrink-0"></i>
            <div class="flex-grow-1 overflow-hidden">
              <div class="fw-600 fs-13 text-truncate"><?= htmlspecialchars($f['nama_file']) ?></div>
              <div class="fs-12 text-muted"><?= htmlspecialchars($f['deskripsi'] ?? '—') ?> · <?= date('d M Y', strtotime($f['created_at'])) ?> · <?= htmlspecialchars($f['uploader'] ?? '—') ?></div>
            </div>
            <div class="flex-shrink-0 d-flex gap-1">
              <a href="<?= BASE_URL ?>/modules/files/preview.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                <i class="bi bi-eye me-1"></i>Preview
              </a>
              <a href="<?= BASE_URL ?>/modules/files/download.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download me-1"></i>Unduh
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- Tab SWOT -->
<div class="tab-pane fade" id="arc-swot">
  <?php if (empty($swotData)): ?>
    <div class="empty-state"><i class="bi bi-clipboard-x"></i><p>Belum ada data evaluasi SWOT</p></div>
  <?php else:
    $sKw = topKeywords($swotData, 'strength');
    $wKw = topKeywords($swotData, 'weakness');
    $oKw = topKeywords($swotData, 'opportunity');
    $tKw = topKeywords($swotData, 'threat');
  ?>

  <!-- SWOT Matrix -->
  <div class="card mb-4">
    <div class="card-header fw-700">
      <i class="bi bi-grid-3x3-gap me-2"></i>Analisis SWOT
      <span class="badge bg-secondary ms-1"><?= count($swotData) ?> responden</span>
    </div>
    <div class="card-body">
      <div class="row g-2">
        <div class="col-6">
          <div class="p-3 rounded" style="background:#f0fdf4">
            <div class="fw-700 small text-success mb-2">💪 Strength</div>
            <?php foreach ($sKw as $w => $c): ?><span class="badge bg-success bg-opacity-20 text-success me-1 mb-1"><?= $w ?> (<?= $c ?>)</span><?php endforeach; ?>
          </div>
        </div>
        <div class="col-6">
          <div class="p-3 rounded" style="background:#fff5f5">
            <div class="fw-700 small text-danger mb-2">⚠️ Weakness</div>
            <?php foreach ($wKw as $w => $c): ?><span class="badge bg-danger bg-opacity-20 text-danger me-1 mb-1"><?= $w ?> (<?= $c ?>)</span><?php endforeach; ?>
          </div>
        </div>
        <div class="col-6">
          <div class="p-3 rounded" style="background:#eff6ff">
            <div class="fw-700 small text-primary mb-2">🚀 Opportunity</div>
            <?php foreach ($oKw as $w => $c): ?><span class="badge bg-primary bg-opacity-20 text-primary me-1 mb-1"><?= $w ?> (<?= $c ?>)</span><?php endforeach; ?>
          </div>
        </div>
        <div class="col-6">
          <div class="p-3 rounded" style="background:#fffbeb">
            <div class="fw-700 small text-warning mb-2">🔥 Threat</div>
            <?php foreach ($tKw as $w => $c): ?><span class="badge bg-warning bg-opacity-20 text-warning me-1 mb-1"><?= $w ?> (<?= $c ?>)</span><?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Saran -->
  <?php $saranList = array_filter($swotData, fn($s) => !empty($s['saran']));
  if (!empty($saranList)): ?>
  <div class="card mb-4">
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
            <span class="badge bg-primary me-2"><?= $i + 1 ?></span>
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

  <!-- List per panitia (anonim tetap anonim) -->
  <div class="card mt-4">
    <div class="card-header fw-700">
      <i class="bi bi-list-ul me-2"></i>Jawaban Lengkap Per Panitia
    </div>
    <div class="card-body p-0">
      <?php foreach ($swotData as $sd): ?>
        <div class="p-3 border-bottom">
          <div class="fw-600 fs-13 mb-2">
            <?php if ($sd['is_anonim']): ?>
              <i class="bi bi-person-fill-slash text-muted me-1"></i><em class="text-muted">Anonim</em>
            <?php else: ?>
              <i class="bi bi-person-fill me-1 text-primary"></i><?= htmlspecialchars($sd['nama'] ?? '—') ?>
            <?php endif; ?>
            <span class="text-muted fs-12 ms-2"><?= date('d M Y', strtotime($sd['created_at'] ?? 'now')) ?></span>
          </div>
          <div class="row g-2 fs-12">
            <div class="col-6"><strong class="text-success">S:</strong> <?= htmlspecialchars(substr($sd['strength'] ?? '—', 0, 150)) ?></div>
            <div class="col-6"><strong class="text-danger">W:</strong> <?= htmlspecialchars(substr($sd['weakness'] ?? '—', 0, 150)) ?></div>
            <div class="col-6"><strong class="text-primary">O:</strong> <?= htmlspecialchars(substr($sd['opportunity'] ?? '—', 0, 150)) ?></div>
            <div class="col-6"><strong class="text-warning">T:</strong> <?= htmlspecialchars(substr($sd['threat'] ?? '—', 0, 150)) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php endif; // end !empty swotData ?>
</div>

<!-- Tab Tim -->
<div class="tab-pane fade" id="arc-tim">
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Nama</th><th>Peran</th><th>Bagian</th><th>Konfirmasi</th></tr></thead>
        <tbody>
          <?php foreach ($panitia as $p): ?>
          <tr>
            <td class="fw-600 fs-13"><?= htmlspecialchars($p['nama']) ?></td>
            <td><?= $p['peran_acara'] === 'pic' ? '<span class="badge bg-danger">PIC</span>' : '<span class="badge bg-secondary">Panitia</span>' ?></td>
            <td class="fs-12"><?= htmlspecialchars($p['bagian'] ?? '—') ?></td>
            <td><?php
              $klabel = ['pending'=>'Menunggu','bersedia'=>'Bersedia','tidak_bisa'=>'Tidak Bisa'];
              $kcolor = ['pending'=>'warning','bersedia'=>'success','tidak_bisa'=>'danger'];
              $kst = $p['status_konfirmasi'] ?? 'pending';
            ?><span class="badge bg-<?= $kcolor[$kst] ?? 'secondary' ?>"><?= $klabel[$kst] ?? $kst ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</div><!-- end tab-content -->

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
