<?php
$pageTitle = 'Evaluasi SWOT';
require_once __DIR__ . '/../../includes/layout/header.php';

$uid = $_SESSION['user_id'];

// Handle submit SWOT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_swot'])) {
    $eventId    = (int)$_POST['event_id'];
    $strength   = trim($_POST['strength']   ?? '');
    $weakness   = trim($_POST['weakness']   ?? '');
    $opportunity= trim($_POST['opportunity']?? '');
    $threat     = trim($_POST['threat']     ?? '');
    $saran      = trim($_POST['saran']      ?? '');
    $isAnonim   = isset($_POST['is_anonim']) ? 1 : 0;

    $pdo->prepare("INSERT INTO event_swot (event_id, user_id, strength, weakness, opportunity, threat, saran, is_anonim)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE strength=VALUES(strength), weakness=VALUES(weakness),
        opportunity=VALUES(opportunity), threat=VALUES(threat), saran=VALUES(saran), is_anonim=VALUES(is_anonim)")
        ->execute([$eventId, $uid, $strength, $weakness, $opportunity, $threat, $saran, $isAnonim]);

    setFlash('Evaluasi SWOT berhasil disimpan. Terima kasih!', 'success');
    header('Location: ?'); exit;
}

// Acara selesai yang diikuti user (belum isi SWOT)
$belumIsi = $pdo->prepare("
    SELECT e.id, e.judul, e.level, e.tanggal_mulai, ep.bagian
    FROM events e
    JOIN event_panitia ep ON ep.event_id = e.id AND ep.user_id = ?
    WHERE e.status = 'selesai'
      AND NOT EXISTS (SELECT 1 FROM event_swot sw WHERE sw.event_id = e.id AND sw.user_id = ?)
    ORDER BY e.tanggal_mulai DESC
");
$belumIsi->execute([$uid, $uid]);
$acaraBelum = $belumIsi->fetchAll();

// Acara selesai yang sudah isi SWOT
$sudahIsi = $pdo->prepare("
    SELECT e.id, e.judul, e.level, e.tanggal_mulai, sw.submitted_at
    FROM events e
    JOIN event_swot sw ON sw.event_id = e.id AND sw.user_id = ?
    ORDER BY sw.submitted_at DESC
");
$sudahIsi->execute([$uid]);
$acaraSudah = $sudahIsi->fetchAll();

// Analisis SWOT (untuk PIC/Admin: acara yang mereka kelola)
$eventAnalisis = null; $swotData = [];
$viewId = (int)($_GET['view'] ?? 0);

if ($viewId && (isSuperAdmin() || isPIC($viewId, $pdo) || isEventAdmin($viewId, $pdo))) {
    $ea = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $ea->execute([$viewId]);
    $eventAnalisis = $ea->fetch();

    $swotData = $pdo->prepare("
        SELECT sw.*, u.nama FROM event_swot sw
        LEFT JOIN users u ON u.id = sw.user_id
        WHERE sw.event_id = ?
    ");
    $swotData->execute([$viewId]);
    $swotData = $swotData->fetchAll();
}

// Acara selesai yang PIC-nya user ini (untuk lihat analisis)
$acaraKelola = $pdo->prepare("
    SELECT e.id, e.judul, e.level, e.tanggal_mulai,
           (SELECT COUNT(*) FROM event_swot sw WHERE sw.event_id = e.id) AS total_swot,
           (SELECT COUNT(*) FROM event_panitia ep2 WHERE ep2.event_id = e.id) AS total_panitia
    FROM events e
    JOIN event_panitia ep ON ep.event_id = e.id AND ep.user_id = ? AND ep.peran_acara = 'pic'
    WHERE e.status = 'selesai'
    ORDER BY e.tanggal_mulai DESC
");
$acaraKelola->execute([$uid]);
$kelolaList = $acaraKelola->fetchAll();

// Helper: kumpulkan & hitung kata kunci
function topKeywords(array $rows, string $field, int $top = 5): array {
    $all = [];
    foreach ($rows as $r) {
        if (!$r[$field]) continue;
        $words = preg_split('/[\s,\.;]+/', strtolower($r[$field]), -1, PREG_SPLIT_NO_EMPTY);
        $stopwords = ['dan','yang','di','ke','dari','untuk','dengan','ini','itu','tidak','ada','acara','saat'];
        foreach ($words as $w) {
            if (strlen($w) > 3 && !in_array($w, $stopwords)) $all[] = $w;
        }
    }
    $count = array_count_values($all);
    arsort($count);
    return array_slice($count, 0, $top, true);
}
?>

<?php if (!empty($acaraBelum)): ?>
<div class="alert alert-warning mb-4">
  <i class="bi bi-clipboard-check me-2"></i>
  Kamu belum mengisi evaluasi SWOT untuk <strong><?= count($acaraBelum) ?> acara</strong>. Mohon segera isi!
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- Kiri: Form Isi SWOT -->
  <div class="col-md-5">

    <?php if (!empty($acaraBelum)): ?>
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-pencil-square me-2"></i>Isi Evaluasi SWOT</div>
      <div class="card-body">
        <form method="POST">
          <?php if(function_exists('csrfToken')): ?><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><?php endif; ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Pilih Acara</label>
            <select name="event_id" class="form-select form-select-sm" required>
              <option value="">— Pilih acara —</option>
              <?php foreach ($acaraBelum as $ab): ?>
                <option value="<?= $ab['id'] ?>"><?= htmlspecialchars($ab['judul']) ?> (<?= date('M Y', strtotime($ab['tanggal_mulai'])) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <div class="swot-box swot-s">
                <label class="form-label fw-bold small text-success">💪 Strength</label>
                <textarea name="strength" class="form-control form-control-sm border-0 bg-transparent" rows="3"
                          placeholder="Apa yang berjalan baik?"></textarea>
              </div>
            </div>
            <div class="col-6">
              <div class="swot-box swot-w">
                <label class="form-label fw-bold small text-danger">⚠️ Weakness</label>
                <textarea name="weakness" class="form-control form-control-sm border-0 bg-transparent" rows="3"
                          placeholder="Apa yang masih kurang?"></textarea>
              </div>
            </div>
            <div class="col-6">
              <div class="swot-box swot-o">
                <label class="form-label fw-bold small text-primary">🚀 Opportunity</label>
                <textarea name="opportunity" class="form-control form-control-sm border-0 bg-transparent" rows="3"
                          placeholder="Potensi yang bisa dikembangkan?"></textarea>
              </div>
            </div>
            <div class="col-6">
              <div class="swot-box swot-t">
                <label class="form-label fw-bold small text-warning">🔥 Threat</label>
                <textarea name="threat" class="form-control form-control-sm border-0 bg-transparent" rows="3"
                          placeholder="Kendala atau risiko ke depan?"></textarea>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">💡 Saran untuk tahun depan</label>
            <textarea name="saran" class="form-control form-control-sm" rows="2"
                      placeholder="Apa yang sebaiknya diperbaiki atau ditambah?"></textarea>
          </div>
          <div class="mb-3 form-check">
            <input type="checkbox" name="is_anonim" id="isAnonim" class="form-check-input">
            <label for="isAnonim" class="form-check-label small">Kirim secara anonim</label>
          </div>
          <button type="submit" name="submit_swot" class="btn btn-primary w-100">
            <i class="bi bi-send me-1"></i> Kirim Evaluasi
          </button>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="card mb-3">
      <div class="card-body text-center text-muted py-4">
        <i class="bi bi-check2-circle fs-1 d-block mb-2 text-success"></i>
        Semua evaluasi SWOT sudah diisi!
      </div>
    </div>
    <?php endif; ?>

    <!-- Sudah Diisi -->
    <?php if (!empty($acaraSudah)): ?>
    <div class="card">
      <div class="card-header"><i class="bi bi-check-circle me-2"></i>Sudah Diisi</div>
      <div class="list-group list-group-flush">
        <?php foreach ($acaraSudah as $s): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <div class="small fw-semibold"><?= htmlspecialchars($s['judul']) ?></div>
              <small class="text-muted"><?= date('d M Y', strtotime($s['submitted_at'])) ?></small>
            </div>
            <span class="badge bg-success"><i class="bi bi-check"></i></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Kanan: Analisis SWOT -->
  <div class="col-md-7">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-bar-chart-line me-2"></i>Analisis SWOT Acara (PIC)</div>
      <div class="card-body p-0">
        <?php if (empty($kelolaList)): ?>
          <div class="text-center text-muted py-4">
            <i class="bi bi-graph-up fs-2 d-block mb-1"></i>
            Belum ada acara selesai yang kamu kelola
          </div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($kelolaList as $kl): ?>
              <a href="?view=<?= $kl['id'] ?>" class="list-group-item list-group-item-action <?= $viewId==$kl['id']?'active':'' ?>">
                <div class="d-flex justify-content-between">
                  <div>
                    <div class="fw-semibold small"><?= htmlspecialchars($kl['judul']) ?></div>
                    <small><?= date('M Y', strtotime($kl['tanggal_mulai'])) ?> · <?= $kl['level'] ?></small>
                  </div>
                  <span class="badge bg-primary"><?= $kl['total_swot'] ?>/<?= $kl['total_panitia'] ?></span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Detail Analisis -->
    <?php if ($eventAnalisis && !empty($swotData)): ?>
    <div class="card">
      <div class="card-header">
        <i class="bi bi-clipboard-data me-2"></i>
        Insight: <?= htmlspecialchars($eventAnalisis['judul']) ?>
        <span class="badge bg-secondary ms-1"><?= count($swotData) ?> responden</span>
      </div>
      <div class="card-body">
        <?php
        $s_kw = topKeywords($swotData, 'strength');
        $w_kw = topKeywords($swotData, 'weakness');
        $o_kw = topKeywords($swotData, 'opportunity');
        $t_kw = topKeywords($swotData, 'threat');
        ?>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <div class="swot-box swot-s p-2">
              <div class="fw-bold small text-success mb-1">💪 Strength</div>
              <?php foreach ($s_kw as $w => $c): ?>
                <span class="badge bg-success bg-opacity-25 text-success me-1"><?= $w ?> (<?= $c ?>)</span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-6">
            <div class="swot-box swot-w p-2">
              <div class="fw-bold small text-danger mb-1">⚠️ Weakness</div>
              <?php foreach ($w_kw as $w => $c): ?>
                <span class="badge bg-danger bg-opacity-25 text-danger me-1"><?= $w ?> (<?= $c ?>)</span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-6">
            <div class="swot-box swot-o p-2">
              <div class="fw-bold small text-primary mb-1">🚀 Opportunity</div>
              <?php foreach ($o_kw as $w => $c): ?>
                <span class="badge bg-primary bg-opacity-25 text-primary me-1"><?= $w ?> (<?= $c ?>)</span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-6">
            <div class="swot-box swot-t p-2">
              <div class="fw-bold small text-warning mb-1">🔥 Threat</div>
              <?php foreach ($t_kw as $w => $c): ?>
                <span class="badge bg-warning bg-opacity-25 text-warning me-1"><?= $w ?> (<?= $c ?>)</span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Saran Terbaru -->
        <div class="fw-semibold small mb-2">💡 Saran dari Panitia</div>
        <?php foreach ($swotData as $sd): if (!$sd['saran']) continue; ?>
          <div class="bg-light rounded p-2 mb-2 small">
            <i class="bi bi-chat-quote me-1 text-muted"></i>
            <?= htmlspecialchars($sd['saran']) ?>
            <?php if (!$sd['is_anonim']): ?>
              <span class="text-muted"> — <?= htmlspecialchars($sd['nama']) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php elseif ($eventAnalisis): ?>
    <div class="card">
      <div class="card-body text-center text-muted py-4">
        <i class="bi bi-hourglass fs-2 d-block mb-2"></i>
        Belum ada panitia yang mengisi SWOT untuk acara ini
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
