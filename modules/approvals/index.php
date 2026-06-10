<?php
$pageTitle = 'Approval Acara';
require_once __DIR__ . '/../../includes/layout/header.php';
require_once __DIR__ . '/../../config/mail.php';

$uid  = $_SESSION['user_id'];
$role = $user['role_sistem'];

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $approvalId = (int)$_POST['approval_id'];
    $action     = $_POST['action'];
    $catatan    = trim($_POST['catatan'] ?? '');

    $ap = $pdo->prepare("SELECT * FROM approvals WHERE id = ? AND approver_id = ?");
    $ap->execute([$approvalId, $uid]);
    $apRow = $ap->fetch();

    // pastikan approval ini adalah urutan terendah (actionable)
    $isActionable = false;
    if ($apRow) {
      $minQ = $pdo->prepare("SELECT MIN(urutan) FROM approvals WHERE event_id = ? AND status = 'pending'");
      $minQ->execute([$apRow['event_id']]);
      $minUrutan = $minQ->fetchColumn();
      $isActionable = ((int)$apRow['urutan'] === (int)$minUrutan);
    }

    if ($apRow && $isActionable && in_array($action, ['approved','rejected'])) {
        $pdo->prepare("UPDATE approvals SET status=?, catatan=?, approved_at=NOW() WHERE id=?")
            ->execute([$action, $catatan, $approvalId]);

        // Update status event
        if ($action === 'approved') {
            $nextStatus = [
                'manager_tk'      => 'disetujui_manager',
                'manager_sd'      => 'disetujui_manager',
                'manager_smp'     => 'disetujui_manager',
                'sekretaris'      => 'proposal_dibuat',
                'bendahara'       => 'rab_diajukan',
                'kehumasan'       => 'perijinan',
                'kepala_sekolah'  => 'disetujui',
            ][$apRow['tipe_approver']] ?? null;
            if ($nextStatus) {
              $pdo->prepare("UPDATE events SET status=? WHERE id=?")
                ->execute([$nextStatus, $apRow['event_id']]);
            }
            // jika tidak ada lagi approval pending untuk event ini, mark final status if applicable
            $left = $pdo->prepare("SELECT COUNT(*) FROM approvals WHERE event_id=? AND status='pending'");
            $left->execute([$apRow['event_id']]);
            if ((int)$left->fetchColumn() === 0) {
              // jika semua approval selesai, set event status ke 'disetujui' jika belum diatur
              $pdo->prepare("UPDATE events SET status = 'disetujui' WHERE id = ? AND status NOT IN ('selesai','ditolak')")->execute([$apRow['event_id']]);
            }
        } else {
            $pdo->prepare("UPDATE events SET status='ditolak' WHERE id=?")->execute([$apRow['event_id']]);
        }

        setFlash('Keputusan approval berhasil disimpan.', 'success');
    }
    header('Location: ?'); exit;
}

// Handle buat approval baru (oleh PIC)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_approval'])) {
    $eventId       = (int)$_POST['event_id'];
    $tipeApprover  = $_POST['tipe_approver'];
    $approverId    = (int)$_POST['approver_id'];
    $urutan        = (int)$_POST['urutan'];

    // Cek PIC
    if (isPIC($eventId, $pdo) || isSuperAdmin()) {
        $eventCheck = $pdo->prepare("SELECT COUNT(*) FROM event_files WHERE event_id=? AND file_type IN ('proposal','rab')");
        $eventCheck->execute([$eventId]);
        $proposalOrRabCount = (int)$eventCheck->fetchColumn();
        $managerTypes = ['manager_tk','manager_sd','manager_smp','kepala_sekolah'];

        $existingPending = 0;
        if (in_array($tipeApprover, $managerTypes, true)) {
            $pendingCheck = $pdo->prepare("SELECT COUNT(*) FROM approvals WHERE event_id=? AND tipe_approver=? AND status='pending'");
            $pendingCheck->execute([$eventId, $tipeApprover]);
            $existingPending = (int)$pendingCheck->fetchColumn();
        }

        if (in_array($tipeApprover, $managerTypes, true) && $proposalOrRabCount === 0) {
            setFlash('Unggah minimal satu dokumen proposal atau RAB terlebih dahulu sebelum membuat approval manager.', 'warning');
        } else {
            $approverValidQ = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id=? AND status='aktif' AND (role_sistem IN ('admin','superadmin') OR jabatan_sistem IN ('manager_tk','manager_sd','manager_smp','kepala_sekolah'))");
            $approverValidQ->execute([$approverId]);
            $approverIsValid = (int)$approverValidQ->fetchColumn() > 0;

            if (!$approverIsValid) {
                setFlash('Approver harus berupa Admin, Superadmin, atau Manager yang aktif.', 'warning');
            } elseif ($existingPending > 0) {
                setFlash('Sudah ada permintaan approval untuk tipe ini yang sedang menunggu keputusan.', 'warning');
            } else {
                $pdo->prepare("INSERT INTO approvals (event_id, approver_id, tipe_approver, urutan) VALUES (?,?,?,?)")
                    ->execute([$eventId, $approverId, $tipeApprover, $urutan]);

                // Kirim email pemberitahuan ke approver
                $appr = $pdo->prepare("SELECT * FROM users WHERE id=?");
                $appr->execute([$approverId]); $apUser = $appr->fetch();
                if ($apUser) {
                    $evData = $pdo->prepare("SELECT * FROM events WHERE id=?"); $evData->execute([$eventId]); $evRow = $evData->fetch();
                    $html = mailTemplateApproval($apUser, $evRow, $tipeApprover);
                    sendMail($apUser['email'], $apUser['nama'], 'Permintaan Approval: ' . ($evRow['judul'] ?? ''), $html);
                    addNotif($pdo, $approverId, 'Permintaan Approval', "Terdapat permintaan approval untuk acara {$evRow['judul']}", BASE_URL.'/modules/approvals/', 'info');
                }
                // Update status event jadi pengajuan
                $pdo->prepare("UPDATE events SET status='pengajuan' WHERE id=? AND status='draft'")->execute([$eventId]);
                setFlash('Approval berhasil dibuat dan acara diajukan!', 'success');
            }
        }
    }
    header('Location: ?'); exit;
}

// Ambil approval yang perlu di-review oleh user ini (hanya approval yang berada di urutan terendah untuk eventnya)
$myApprovals = $pdo->prepare(
  "SELECT ap.*, e.judul, e.level, e.tanggal_mulai, e.tanggal_selesai, e.status AS event_status,
       u.nama AS nama_pic
  FROM approvals ap
  JOIN events e ON e.id = ap.event_id
  LEFT JOIN users u ON u.id = e.pic_id
  WHERE ap.approver_id = ? AND ap.status = 'pending'
    AND ap.urutan = (
      SELECT MIN(a2.urutan) FROM approvals a2 WHERE a2.event_id = ap.event_id AND a2.status = 'pending'
    )
  ORDER BY ap.urutan ASC, ap.created_at ASC
  "
);
$myApprovals->execute([$uid]);
$pendingApprovals = $myApprovals->fetchAll();

// Riwayat approval oleh user ini
$riwayat = $pdo->prepare("
    SELECT ap.*, e.judul, e.level, e.tanggal_mulai
    FROM approvals ap
    JOIN events e ON e.id = ap.event_id
    WHERE ap.approver_id = ? AND ap.status != 'pending'
    ORDER BY ap.approved_at DESC LIMIT 20
");
$riwayat->execute([$uid]);
$riwayatList = $riwayat->fetchAll();

// Untuk PIC: lihat status approval acara miliknya
$acaraSaya = $pdo->prepare("
    SELECT e.*, GROUP_CONCAT(CONCAT(ap.tipe_approver,'|',ap.status) ORDER BY ap.urutan SEPARATOR ';;') as approval_info
    FROM events e
    JOIN event_panitia ep ON ep.event_id = e.id AND ep.user_id = ? AND ep.peran_acara = 'pic'
    LEFT JOIN approvals ap ON ap.event_id = e.id
    WHERE e.status != 'selesai'
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$acaraSaya->execute([$uid]);
$acaraPIC = $acaraSaya->fetchAll();

// Data untuk form buat approval
$semuaSDM  = $pdo->query("SELECT id, nama, jabatan FROM users WHERE status='aktif' AND (role_sistem IN ('admin','superadmin') OR jabatan_sistem IN ('manager_tk','manager_sd','manager_smp','kepala_sekolah')) ORDER BY nama")->fetchAll();
$acaraDraft = $pdo->prepare("
    SELECT e.id, e.judul FROM events e
    JOIN event_panitia ep ON ep.event_id=e.id AND ep.user_id=? AND ep.peran_acara='pic'
    WHERE e.status IN ('draft','pengajuan')
");
$acaraDraft->execute([$uid]);
$draftList = $acaraDraft->fetchAll();

// Dokumen untuk pending approvals (dipakai di modal Review Dokumen)
$pendingEventIds = array_unique(array_column($pendingApprovals, 'event_id'));
$eventDocs = [];
if (!empty($pendingEventIds)) {
    $pholds = implode(',', array_fill(0, count($pendingEventIds), '?'));
    $docsQ  = $pdo->prepare("SELECT f.id, f.event_id, f.nama_file, f.file_type, f.created_at FROM event_files f WHERE f.event_id IN ($pholds) ORDER BY f.file_type, f.created_at DESC");
    $docsQ->execute($pendingEventIds);
    foreach ($docsQ->fetchAll() as $doc) {
        $eventDocs[$doc['event_id']][] = $doc;
    }
}

$tipeLabel = [
    'manager_tk'=>'Manager TK','manager_sd'=>'Manager SD','manager_smp'=>'Manager SMP',
    'sekretaris'=>'Sekretaris','bendahara'=>'Bendahara',
    'kehumasan'=>'Kehumasan','kepala_sekolah'=>'Kepala Sekolah'
];
$statusBadge = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'];
$statusLabel = ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak'];
?>

<!-- Approval Masuk -->
<?php if (!empty($pendingApprovals)): ?>
<div class="alert alert-warning mb-4">
  <i class="bi bi-bell-fill me-2"></i>
  Kamu memiliki <strong><?= count($pendingApprovals) ?> approval</strong> yang menunggu keputusanmu.
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- Kiri: Approval Masuk + Riwayat -->
  <div class="col-md-7">

    <!-- Approval Menunggu -->
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-inbox me-2"></i>Perlu Keputusanmu</span>
        <span class="badge bg-warning text-dark"><?= count($pendingApprovals) ?></span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($pendingApprovals)): ?>
          <div class="text-center text-muted py-4">
            <i class="bi bi-check2-all fs-2 d-block mb-1"></i> Tidak ada approval yang perlu ditinjau
          </div>
        <?php else: ?>
          <?php foreach ($pendingApprovals as $ap): ?>
          <div class="p-3 border-bottom">
            <div class="d-flex justify-content-between mb-2">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars($ap['judul']) ?></div>
                <small class="text-muted">
                  <span class="badge bg-secondary"><?= $ap['level'] ?></span>
                  <?= date('d M Y', strtotime($ap['tanggal_mulai'])) ?>
                  <?= $ap['tanggal_mulai'] !== $ap['tanggal_selesai'] ? ' – ' . date('d M Y', strtotime($ap['tanggal_selesai'])) : '' ?>
                  · PIC: <?= htmlspecialchars($ap['nama_pic'] ?? '-') ?>
                </small>
              </div>
              <span class="badge bg-primary"><?= $tipeLabel[$ap['tipe_approver']] ?? $ap['tipe_approver'] ?></span>
            </div>
            <form method="POST" class="row g-2" id="approvalForm_<?= $ap['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="approval_id" value="<?= $ap['id'] ?>">
              <input type="hidden" name="action" id="approvalAction_<?= $ap['id'] ?>" value="">
              <div class="col-12">
                <textarea name="catatan" class="form-control form-control-sm" rows="2"
                          placeholder="Catatan (opsional)..."></textarea>
              </div>
              <div class="col-auto">
                <button type="button" class="btn btn-success btn-sm"
                        onclick="handleApproval(<?= $ap['id'] ?>, 'approved')">
                  <i class="bi bi-check-circle me-1"></i> Setujui
                </button>
              </div>
              <div class="col-auto">
                <button type="button" class="btn btn-danger btn-sm"
                        onclick="handleApproval(<?= $ap['id'] ?>, 'rejected')">
                  <i class="bi bi-x-circle me-1"></i> Tolak
                </button>
              </div>
              <div class="col-auto ms-auto">
                <button type="button" class="btn btn-outline-info btn-sm"
                        onclick="showDocReview(<?= $ap['event_id'] ?>, <?= json_encode(htmlspecialchars($ap['judul'], ENT_QUOTES)) ?>)">
                  <i class="bi bi-folder2-open me-1"></i> Review Dokumen
                </button>
                <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $ap['event_id'] ?>"
                   class="btn btn-outline-secondary btn-sm ms-1">
                  <i class="bi bi-eye me-1"></i> Detail Acara
                </a>
              </div>
            </form>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Riwayat -->
    <div class="card">
      <div class="card-header"><i class="bi bi-clock-history me-2"></i>Riwayat Keputusanmu</div>
      <div class="card-body p-0">
        <?php if (empty($riwayatList)): ?>
          <div class="text-center text-muted py-3"><small>Belum ada riwayat</small></div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Acara</th><th>Tipe</th><th>Keputusan</th><th>Tanggal</th></tr></thead>
              <tbody>
              <?php foreach ($riwayatList as $r): ?>
                <tr>
                  <td><small class="fw-semibold"><?= htmlspecialchars($r['judul']) ?></small></td>
                  <td><small><?= $tipeLabel[$r['tipe_approver']] ?? $r['tipe_approver'] ?></small></td>
                  <td><span class="badge bg-<?= $statusBadge[$r['status']] ?>"><?= $statusLabel[$r['status']] ?></span></td>
                  <td><small><?= $r['approved_at'] ? date('d M Y', strtotime($r['approved_at'])) : '-' ?></small></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Kanan: Status Acara Saya + Buat Approval -->
  <div class="col-md-5">

    <!-- Status Approval Acara PIC -->
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-diagram-3 me-2"></i>Status Acara Saya</div>
      <div class="card-body p-0">
        <?php if (empty($acaraPIC)): ?>
          <div class="text-center text-muted py-3"><small>Tidak ada acara aktif</small></div>
        <?php else: ?>
          <?php foreach ($acaraPIC as $ac):
            $approvalItems = [];
            if ($ac['approval_info']) {
                foreach (explode(';;', $ac['approval_info']) as $item) {
                    [$tipe, $sts] = explode('|', $item);
                    $approvalItems[] = ['tipe'=>$tipe,'status'=>$sts];
                }
            }
          ?>
          <div class="p-3 border-bottom">
            <div class="fw-semibold mb-1"><?= htmlspecialchars($ac['judul']) ?></div>
            <?php if (empty($approvalItems)): ?>
              <small class="text-muted">Belum ada approval dibuat</small>
            <?php else: ?>
              <div class="d-flex flex-wrap gap-1">
              <?php foreach ($approvalItems as $ai): ?>
                <span class="badge bg-<?= $statusBadge[$ai['status']] ?>">
                  <?= $tipeLabel[$ai['tipe']] ?? $ai['tipe'] ?>
                </span>
              <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <div class="mt-2">
              <a href="<?= BASE_URL ?>/modules/events/detail.php?id=<?= $ac['id'] ?>"
                 class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i> Detail
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Form Buat Approval Baru -->
    <?php if (!empty($draftList)): ?>
    <div class="card">
      <div class="card-header"><i class="bi bi-send me-2"></i>Ajukan ke Approver</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">Acara</label>
            <select name="event_id" class="form-select form-select-sm" required>
              <option value="">— Pilih acara —</option>
              <?php foreach ($draftList as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['judul']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Tipe Approver</label>
            <select name="tipe_approver" class="form-select form-select-sm" required>
              <?php foreach ($tipeLabel as $k => $v): ?>
                <option value="<?= $k ?>"><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Pilih Approver (Manager / Admin / Superadmin)</label>
            <select name="approver_id" class="form-select form-select-sm" required>
              <option value="">— Pilih Manager / Admin / Superadmin —</option>
              <?php foreach ($semuaSDM as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama']) ?> · <?= htmlspecialchars($s['jabatan'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Urutan</label>
            <input type="number" name="urutan" class="form-control form-control-sm" value="1" min="1" max="10">
            <small class="text-muted">Urutan proses approval (1 = pertama)</small>
          </div>
          <button type="submit" name="buat_approval" class="btn btn-primary btn-sm w-100">
            <i class="bi bi-send me-1"></i> Buat & Kirim Approval
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ── Modal Review Dokumen ── -->
<div class="modal fade" id="docReviewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-700">
          <i class="bi bi-folder2-open me-2 text-primary"></i>Review Dokumen
          <span class="text-primary ms-1" id="docReviewTitle"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" id="docReviewBody">
        <div class="text-center py-5 text-muted"><div class="spinner-border text-primary mb-2" role="status"></div><p>Memuat dokumen...</p></div>
      </div>
      <div class="modal-footer bg-light">
        <small class="text-muted me-auto"><i class="bi bi-info-circle me-1"></i>Review dokumen sebelum memberikan keputusan approval.</small>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
const _eventDocs  = <?= json_encode($eventDocs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_NUMERIC_CHECK) ?>;
const _baseUrl    = '<?= BASE_URL ?>';
const _ftLabel    = {rab:'RAB',rundown:'Rundown',proposal:'Proposal',perijinan:'Perijinan',jobdesk:'Jobdesk',undangan:'Undangan',lainnya:'Lainnya'};
const _ftIcon     = {rab:'cash-stack',rundown:'list-task',proposal:'file-text',perijinan:'shield-check',jobdesk:'person-workspace',undangan:'envelope',lainnya:'file-earmark'};

// Fix 3: Setujui / Tolak dengan alur konfirmasi → memproses → sukses
function handleApproval(apId, action) {
  const msgs = {
    approved: '✅ Setujui pengajuan ini?\nPastikan dokumen sudah ditinjau sebelum menyetujui.',
    rejected: '❌ Tolak pengajuan ini?\nTindakan ini tidak bisa dibatalkan dan akan menghentikan proses acara.'
  };
  showConfirmModal(msgs[action], function() {
    document.getElementById('approvalAction_' + apId).value = action;
    const form = document.getElementById('approvalForm_' + apId);
    // Trigger loading overlay dari footer.php
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: false }));
    form.submit();
  });
}

// Fix 2: Review Dokumen — tampilkan dokumen acara dalam modal, bukan redirect
function showDocReview(eventId, title) {
  document.getElementById('docReviewTitle').textContent = '— ' + title;
  const docs = _eventDocs[eventId] || [];
  const body = document.getElementById('docReviewBody');

  if (!docs.length) {
    body.innerHTML = '<div class="text-center text-muted py-5">'
      + '<i class="bi bi-folder2 d-block mb-2" style="font-size:2.5rem"></i>'
      + '<p class="fw-500">Belum ada dokumen yang diupload untuk acara ini.</p>'
      + '<small>Minta PIC untuk mengunggah dokumen proposal atau RAB terlebih dahulu.</small></div>';
  } else {
    // Group by file type
    const grouped = {};
    docs.forEach(d => { if (!grouped[d.file_type]) grouped[d.file_type] = []; grouped[d.file_type].push(d); });
    let html = '';
    Object.keys(grouped).forEach(type => {
      const icon = _ftIcon[type] || 'file-earmark';
      const label = _ftLabel[type] || type;
      html += `<div class="px-3 py-2 border-bottom bg-light fw-600 fs-13"><i class="bi bi-${icon} me-2 text-secondary"></i>${label} <span class="badge bg-secondary ms-1">${grouped[type].length}</span></div>`;
      grouped[type].forEach(d => {
        const dt = d.created_at ? d.created_at.substring(0,10) : '—';
        html += `<div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
          <i class="bi bi-${icon} text-primary fs-5 flex-shrink-0"></i>
          <div class="flex-grow-1 overflow-hidden">
            <div class="fw-600 fs-13 text-truncate">${d.nama_file}</div>
            <div class="fs-12 text-muted">${label} · ${dt}</div>
          </div>
          <div class="flex-shrink-0 d-flex gap-1">
            <a href="${_baseUrl}/modules/files/preview.php?id=${d.id}" target="_blank" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-eye me-1"></i>Preview
            </a>
            <a href="${_baseUrl}/modules/files/download.php?id=${d.id}" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-download me-1"></i>Unduh
            </a>
          </div>
        </div>`;
      });
    });
    body.innerHTML = html;
  }

  const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('docReviewModal'));
  modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
