<?php
$pageTitle = 'Isi Evaluasi';
require_once __DIR__ . '/../../includes/layout/header.php';

$evalId = (int)($_GET['id'] ?? 0);
if (!$evalId) { header('Location:'.BASE_URL.'/modules/dashboard/select.php'); exit; }

$eval = $pdo->prepare("SELECT ev.*, e.judul AS judul_acara, e.id AS event_id FROM event_evaluasi ev JOIN events e ON e.id=ev.event_id WHERE ev.id=?");
$eval->execute([$evalId]); $eval = $eval->fetch();
if (!$eval) { setFlash('Form tidak ditemukan.','danger'); header('Location:'.BASE_URL.'/modules/dashboard/select.php'); exit; }

// Cek akses (harus panitia acara ini)
$akses = $pdo->prepare("SELECT id FROM event_panitia WHERE event_id=? AND user_id=?");
$akses->execute([$eval['event_id'],$_SESSION['user_id']]);
if (!$akses->fetchColumn() && !isSuperAdmin()) { header('Location:'.BASE_URL.'/modules/dashboard/select.php'); exit; }

// Cek sudah isi
$sudah = $pdo->prepare("SELECT COUNT(*) FROM evaluasi_jawaban WHERE evaluasi_id=? AND user_id=?");
$sudah->execute([$evalId,$_SESSION['user_id']]); $sudahIsi = (bool)$sudah->fetchColumn();

// Pertanyaan
$pertQ = $pdo->prepare("SELECT * FROM evaluasi_pertanyaan WHERE evaluasi_id=? ORDER BY urutan");
$pertQ->execute([$evalId]); $pertanyaan = $pertQ->fetchAll();

// Handle submit
if ($_SERVER['REQUEST_METHOD']==='POST' && !$sudahIsi) {
    $errors = [];
    foreach ($pertanyaan as $p) {
        $jawaban = trim($_POST['jawaban_'.$p['id']] ?? '');
        if ($p['is_required'] && !$jawaban) $errors[] = "Pertanyaan #{$p['urutan']} wajib diisi.";
    }
    if (empty($errors)) {
        foreach ($pertanyaan as $p) {
            $jawaban = trim($_POST['jawaban_'.$p['id']] ?? '');
            $pdo->prepare("INSERT INTO evaluasi_jawaban (evaluasi_id,pertanyaan_id,user_id,jawaban) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE jawaban=VALUES(jawaban)")
                ->execute([$evalId,$p['id'],$_SESSION['user_id'],$jawaban]);
        }
        setFlash('Evaluasi berhasil disubmit. Terima kasih!','success');
        header('Location:'.BASE_URL.'/modules/events/workspace.php?id='.$eval['event_id'].'#evaluasi'); exit;
    }
}
?>
<div class="page-header">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eval['event_id'] ?>#evaluasi" class="back-btn"><i class="bi bi-arrow-left"></i></a>
    <div><h5><?= htmlspecialchars($eval['judul']) ?></h5><div class="sub"><?= htmlspecialchars($eval['judul_acara']) ?></div></div>
  </div>
</div>

<?php if ($sudahIsi): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>Kamu sudah mengisi form evaluasi ini. Terima kasih atas masukannya!</div>
<?php else: ?>
  <?php if ($eval['deskripsi']): ?><div class="alert alert-info mb-4"><?= nl2br(htmlspecialchars($eval['deskripsi'])) ?></div><?php endif; ?>
  <?php if (!empty($errors)): ?><div class="alert alert-danger mb-3"><?php foreach($errors as $e) echo "<div>• $e</div>"; ?></div><?php endif; ?>
  <form method="POST" class="card" style="max-width:680px">
    <div class="card-body">
      <?php foreach ($pertanyaan as $i => $p):
        $opsi = $p['opsi'] ? json_decode($p['opsi'],true) : [];
      ?>
      <div class="mb-4 pb-3 <?= $i<count($pertanyaan)-1?'border-bottom':'' ?>">
        <label class="form-label fw-700">
          <?= $i+1 ?>. <?= htmlspecialchars($p['pertanyaan']) ?>
          <?php if ($p['is_required']): ?><span class="text-danger ms-1">*</span><?php endif; ?>
        </label>

        <?php if ($p['tipe']==='rating'): ?>
          <div class="d-flex gap-2 flex-wrap mt-1">
            <?php for ($r=1;$r<=10;$r++): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="jawaban_<?= $p['id'] ?>" id="r<?= $p['id'].'_'.$r ?>" value="<?= $r ?>" <?= $p['is_required']?'required':'' ?>>
                <label class="form-check-label fw-600" for="r<?= $p['id'].'_'.$r ?>"><?= $r ?></label>
              </div>
            <?php endfor; ?>
          </div>
          <div class="fs-12 text-muted mt-1">1 = Sangat Buruk, 10 = Sangat Baik</div>

        <?php elseif ($p['tipe']==='ya_tidak'): ?>
          <div class="d-flex gap-3 mt-1">
            <div class="form-check"><input class="form-check-input" type="radio" name="jawaban_<?= $p['id'] ?>" id="ya<?= $p['id'] ?>" value="Ya" <?= $p['is_required']?'required':'' ?>><label class="form-check-label" for="ya<?= $p['id'] ?>">✅ Ya</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="jawaban_<?= $p['id'] ?>" id="tidak<?= $p['id'] ?>" value="Tidak"><label class="form-check-label" for="tidak<?= $p['id'] ?>">❌ Tidak</label></div>
          </div>

        <?php elseif ($p['tipe']==='pilihan_ganda' && !empty($opsi)): ?>
          <?php foreach ($opsi as $oi => $o): ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="jawaban_<?= $p['id'] ?>" id="o<?= $p['id'].'_'.$oi ?>" value="<?= htmlspecialchars($o) ?>" <?= $p['is_required']?'required':'' ?>>
            <label class="form-check-label" for="o<?= $p['id'].'_'.$oi ?>"><?= htmlspecialchars($o) ?></label>
          </div>
          <?php endforeach; ?>

        <?php elseif ($p['tipe']==='text'): ?>
          <input type="text" name="jawaban_<?= $p['id'] ?>" class="form-control" <?= $p['is_required']?'required':'' ?>>

        <?php else: ?>
          <textarea name="jawaban_<?= $p['id'] ?>" class="form-control" rows="3" placeholder="Tulis jawaban kamu di sini..." <?= $p['is_required']?'required':'' ?>></textarea>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit Evaluasi</button>
    </div>
  </form>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
