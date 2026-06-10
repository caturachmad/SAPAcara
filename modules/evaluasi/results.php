<?php
$pageTitle = 'Hasil Evaluasi';
require_once __DIR__ . '/../../includes/layout/header.php';

$evalId = (int)($_GET['id'] ?? 0);
if (!$evalId) { header('Location:'.BASE_URL.'/modules/dashboard/select.php'); exit; }

$eval = $pdo->prepare("SELECT ev.*, e.judul AS judul_acara, e.id AS event_id FROM event_evaluasi ev JOIN events e ON e.id=ev.event_id WHERE ev.id=?");
$eval->execute([$evalId]); $eval = $eval->fetch();
if (!$eval || !(isPIC($eval['event_id'],$pdo)||isSuperAdmin())) {
    header('Location:'.BASE_URL.'/modules/dashboard/select.php'); exit;
}

$pertQ = $pdo->prepare("SELECT * FROM evaluasi_pertanyaan WHERE evaluasi_id=? ORDER BY urutan");
$pertQ->execute([$evalId]); $pertanyaan = $pertQ->fetchAll();

$totalRes = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM evaluasi_jawaban WHERE evaluasi_id=?");
$totalRes->execute([$evalId]); $totalResponden = $totalRes->fetchColumn();

$totalPan = $pdo->prepare("SELECT COUNT(*) FROM event_panitia WHERE event_id=?");
$totalPan->execute([$eval['event_id']]); $totalPanitia = $totalPan->fetchColumn();
?>
<div class="page-header">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eval['event_id'] ?>#evaluasi" class="back-btn"><i class="bi bi-arrow-left"></i></a>
    <div><h5>Hasil: <?= htmlspecialchars($eval['judul']) ?></h5><div class="sub"><?= htmlspecialchars($eval['judul_acara']) ?></div></div>
  </div>
  <span class="badge bg-primary fs-13 py-2 px-3"><?= $totalResponden ?>/<?= $totalPanitia ?> responden</span>
</div>

<?php if ($totalResponden == 0): ?>
  <div class="empty-state"><i class="bi bi-bar-chart-line"></i><p>Belum ada yang mengisi form evaluasi ini</p></div>
<?php else: ?>
  <?php foreach ($pertanyaan as $p):
    $jawabanQ = $pdo->prepare("SELECT ej.jawaban, u.nama FROM evaluasi_jawaban ej JOIN users u ON u.id=ej.user_id WHERE ej.pertanyaan_id=? ORDER BY ej.created_at");
    $jawabanQ->execute([$p['id']]); $jawabans = $jawabanQ->fetchAll();
    $validJawabans = array_filter($jawabans, fn($j) => !empty($j['jawaban']));
  ?>
  <div class="card mb-4">
    <div class="card-header">
      <span class="badge bg-secondary me-2"><?= $p['tipe'] ?></span>
      <?= htmlspecialchars($p['pertanyaan']) ?>
      <span class="ms-auto badge bg-light text-dark border"><?= count($validJawabans) ?> jawaban</span>
    </div>
    <div class="card-body">
      <?php if ($p['tipe']==='rating' && !empty($validJawabans)):
        $vals = array_map(fn($j)=>(int)$j['jawaban'], $validJawabans);
        $avg  = round(array_sum($vals)/count($vals),1);
        $dist = array_count_values($vals); ksort($dist);
      ?>
        <div class="d-flex align-items-center gap-4 mb-3">
          <div class="text-center"><div class="fw-800" style="font-size:2.5rem;color:var(--primary)"><?= $avg ?></div><div class="fs-12 text-muted">Rata-rata (dari 10)</div></div>
          <div class="flex-grow-1">
            <?php for ($r=10;$r>=1;$r--): $c=$dist[$r]??0; $pct=$c?round($c/count($validJawabans)*100):0; ?>
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fs-12 fw-600" style="width:20px;text-align:right"><?= $r ?></span>
                <div class="flex-grow-1 bg-light rounded" style="height:14px"><div class="bg-primary rounded" style="width:<?= $pct ?>%;height:100%"></div></div>
                <span class="fs-12 text-muted" style="width:30px"><?= $c ?></span>
              </div>
            <?php endfor; ?>
          </div>
        </div>

      <?php elseif (in_array($p['tipe'],['ya_tidak','pilihan_ganda']) && !empty($validJawabans)):
        $dist = array_count_values(array_column($validJawabans,'jawaban'));
        arsort($dist);
      ?>
        <?php foreach ($dist as $opt => $cnt): $pct = round($cnt/count($validJawabans)*100); ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between fs-13 mb-1"><span class="fw-600"><?= htmlspecialchars($opt) ?></span><span><?= $cnt ?> (<?= $pct ?>%)</span></div>
            <div class="progress" style="height:10px;border-radius:5px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
          </div>
        <?php endforeach; ?>

      <?php else: ?>
        <?php foreach ($validJawabans as $j): ?>
          <div class="bg-light rounded p-2 mb-2 fs-13">
            <i class="bi bi-chat-left-text me-1 text-muted"></i> <?= htmlspecialchars($j['jawaban']) ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
