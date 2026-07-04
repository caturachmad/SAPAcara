<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId || !(isPIC($eventId,$pdo)||isSuperAdmin())) {
    header('Location:'.BASE_URL.'/modules/dashboard/select.php'); exit;
}
$ev = $pdo->prepare("SELECT * FROM events WHERE id=?");
$ev->execute([$eventId]); $ev = $ev->fetch();
if (!$ev) { header('Location:'.BASE_URL.'/modules/dashboard/select.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $judul  = trim($_POST['judul']??'');
    $desc   = trim($_POST['deskripsi']??'');
    $ddl    = $_POST['deadline']??null;
    $pertan = $_POST['pertanyaan']??[];
    $tipes  = $_POST['tipe']??[];
    $opsis  = $_POST['opsi']??[];
    $req    = $_POST['required']??[];

    if (!$judul) $errors[] = 'Judul form wajib diisi.';
    if (empty($pertan) || empty(array_filter($pertan,'trim'))) $errors[] = 'Minimal 1 pertanyaan.';

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO event_evaluasi (event_id,judul,deskripsi,created_by,deadline) VALUES (?,?,?,?,?)")
                ->execute([$eventId,$judul,$desc,$_SESSION['user_id'],$ddl?:null]);
            $evalId = $pdo->lastInsertId();

            foreach ($pertan as $i => $p) {
                if (!trim($p)) continue;
                $tipe  = $tipes[$i] ?? 'textarea';
                $opsi  = null;
                if ($tipe==='pilihan_ganda' && !empty($opsis[$i])) {
                    $opsiArr = array_filter(array_map('trim', explode("\n", $opsis[$i])));
                    $opsi = json_encode(array_values($opsiArr));
                }
                $isReq = isset($req[$i]) ? 1 : 0;
                $pdo->prepare("INSERT INTO evaluasi_pertanyaan (evaluasi_id,pertanyaan,tipe,opsi,is_required,urutan) VALUES (?,?,?,?,?,?)")
                    ->execute([$evalId,trim($p),$tipe,$opsi,$isReq,$i]);
            }

            // Notif ke semua panitia
            $panitiaAll = $pdo->prepare("SELECT user_id FROM event_panitia WHERE event_id=?");
            $panitiaAll->execute([$eventId]);
            foreach ($panitiaAll->fetchAll() as $p) {
                if ($p['user_id']==$_SESSION['user_id']) continue;
                addNotif($pdo,$p['user_id'],'Form Evaluasi Tersedia',
                    "Form evaluasi acara {$ev['judul']} sudah dibuka. Mohon isi sebelum ".($ddl?date('d M Y',strtotime($ddl)):'batas waktu').'.',
                    BASE_URL.'/modules/evaluasi/fill.php?id='.$evalId,'info');
            }

            $pdo->commit();
            setFlash('Form evaluasi berhasil dibuat!','success');
            header('Location:'.BASE_URL.'/modules/events/workspace.php?id='.$eventId.'#evaluasi'); exit;
        } catch (\Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error: '.$e->getMessage();
        }
    }
}

$pageTitle = 'Buat Form Evaluasi';
require_once __DIR__ . '/../../includes/layout/header.php';
?>
<div class="page-header">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/modules/events/workspace.php?id=<?= $eventId ?>#evaluasi" class="back-btn"><i class="bi bi-arrow-left"></i></a>
    <div><h5>Buat Form Evaluasi</h5><div class="sub"><?= htmlspecialchars($ev['judul']) ?></div></div>
  </div>
</div>

<?php if(!empty($errors)): ?><div class="alert alert-danger mb-3"><?php foreach($errors as $e) echo "<div><i class='bi bi-x-circle me-1'></i>$e</div>"; ?></div><?php endif; ?>

<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
<div class="row g-3">
  <div class="col-md-4">
    <div class="card" style="position:sticky;top:80px">
      <div class="card-header"><i class="bi bi-info-circle"></i> Info Form</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Judul Form <span class="text-danger">*</span></label>
          <input type="text" name="judul" class="form-control" placeholder="cth: Evaluasi Pensi SD 2026" value="<?= htmlspecialchars($_POST['judul']??'') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Deskripsi</label>
          <textarea name="deskripsi" class="form-control" rows="3" placeholder="Penjelasan singkat tujuan evaluasi..."><?= htmlspecialchars($_POST['deskripsi']??'') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Deadline Pengisian</label>
          <input type="date" name="deadline" class="form-control" value="<?= htmlspecialchars($_POST['deadline']??'') ?>" min="<?= date('Y-m-d') ?>">
          <div class="form-text">Kosongkan jika tidak ada batas</div>
        </div>
        <button type="submit" name="simpan" class="btn btn-primary w-100">
          <i class="bi bi-send me-1"></i> Buat & Kirim Notifikasi
        </button>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-task"></i> Daftar Pertanyaan</span>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="tambahPertanyaan()">
          <i class="bi bi-plus"></i> Tambah Pertanyaan
        </button>
      </div>
      <div class="card-body" id="pertanyaanContainer">
        <div class="alert alert-info mb-3">
          Form evaluasi ini sudah otomatis mencakup SWOT (Strength, Weakness, Opportunity, Threat) dan Saran.
        </div>
        <?php
        $tipeOptions = ['textarea'=>'Jawaban Panjang','text'=>'Jawaban Singkat','rating'=>'Rating (1-10)','pilihan_ganda'=>'Pilihan Ganda','ya_tidak'=>'Ya / Tidak'];
        $defaultQ = [
          ['Strength: Apa yang berjalan baik dalam acara ini?','textarea'],
          ['Weakness: Apa yang perlu diperbaiki untuk acara berikutnya?','textarea'],
          ['Opportunity: Peluang apa yang bisa dikembangkan dari acara ini?','textarea'],
          ['Threat: Risiko atau kendala apa yang perlu diwaspadai?','textarea'],
          ['Saran tambahan untuk penyelenggara?','textarea'],
        ];
        foreach ($defaultQ as $qi => $q): ?>
        <div class="pertanyaan-item border rounded p-3 mb-3" id="item<?= $qi ?>">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-primary">Pertanyaan <?= $qi+1 ?></span>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusPertanyaan(<?= $qi ?>)" <?= $qi===0?'disabled':'' ?>>
              <i class="bi bi-trash"></i>
            </button>
          </div>
          <div class="mb-2">
            <textarea name="pertanyaan[<?= $qi ?>]" class="form-control form-control-sm" rows="2" placeholder="Tulis pertanyaan..." required><?= htmlspecialchars($q[0]) ?></textarea>
          </div>
          <div class="row g-2 align-items-center">
            <div class="col-md-5">
              <select name="tipe[<?= $qi ?>]" class="form-select form-select-sm" onchange="toggleOpsi(this,<?= $qi ?>)">
                <?php foreach ($tipeOptions as $tv=>$tl): ?>
                  <option value="<?= $tv ?>" <?= $q[1]===$tv?'selected':'' ?>><?= $tl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="required[<?= $qi ?>]" id="req<?= $qi ?>" checked>
                <label class="form-check-label fs-12" for="req<?= $qi ?>">Wajib diisi</label>
              </div>
            </div>
          </div>
          <div class="opsi-container mt-2 d-none" id="opsi<?= $qi ?>">
            <textarea name="opsi[<?= $qi ?>]" class="form-control form-control-sm" rows="3" placeholder="Tulis setiap pilihan di baris baru:&#10;Sangat Puas&#10;Puas&#10;Kurang Puas&#10;Tidak Puas"></textarea>
            <div class="form-text">Satu pilihan per baris</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
</form>

<script>
let counter = <?= count($defaultQ) ?>;
const tipeOptions = <?= json_encode($tipeOptions) ?>;

function tambahPertanyaan() {
  const idx = counter++;
  const html = `
  <div class="pertanyaan-item border rounded p-3 mb-3" id="item${idx}">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <span class="badge bg-primary">Pertanyaan ${idx+1}</span>
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusPertanyaan(${idx})">
        <i class="bi bi-trash"></i>
      </button>
    </div>
    <div class="mb-2">
      <textarea name="pertanyaan[${idx}]" class="form-control form-control-sm" rows="2" placeholder="Tulis pertanyaan..." required></textarea>
    </div>
    <div class="row g-2 align-items-center">
      <div class="col-md-5">
        <select name="tipe[${idx}]" class="form-select form-select-sm" onchange="toggleOpsi(this,${idx})">
          ${Object.entries(tipeOptions).map(([v,l])=>`<option value="${v}">${l}</option>`).join('')}
        </select>
      </div>
      <div class="col-md-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="required[${idx}]" id="req${idx}" checked>
          <label class="form-check-label fs-12" for="req${idx}">Wajib diisi</label>
        </div>
      </div>
    </div>
    <div class="opsi-container mt-2 d-none" id="opsi${idx}">
      <textarea name="opsi[${idx}]" class="form-control form-control-sm" rows="3" placeholder="Tulis setiap pilihan di baris baru"></textarea>
    </div>
  </div>`;
  document.getElementById('pertanyaanContainer').insertAdjacentHTML('beforeend', html);
}

function hapusPertanyaan(idx) {
  const el = document.getElementById('item'+idx);
  if (el) el.remove();
}

function toggleOpsi(sel, idx) {
  const opsiDiv = document.getElementById('opsi'+idx);
  if (sel.value === 'pilihan_ganda') opsiDiv?.classList.remove('d-none');
  else opsiDiv?.classList.add('d-none');
}
</script>
<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>