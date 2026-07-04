<?php
/**
 * use statement WAJIB di atas file, tidak boleh di dalam if/function block
 */
ob_start();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// ── Download Template (sebelum HTML output) ──
if (isset($_GET['download_template'])) {
    requireLogin();
    ob_end_clean();

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet()->setTitle('Data SDM');

    $cols   = ['A','B','C','D','E','F','G'];
    $labels = ['Nama Lengkap*','Email*','No. WhatsApp','Divisi*','Jabatan','Role','Password Awal'];

    foreach ($labels as $i => $lbl) {
        $sheet->setCellValue($cols[$i].'1', $lbl);
        $sheet->getColumnDimension($cols[$i])->setWidth($i < 2 ? 26 : 20);
    }
    $sheet->getRowDimension(1)->setRowHeight(22);

    // Style header
    $sheet->getStyle('A1:G1')->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a3a5c']],
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // Contoh data
    $examples = [
        ['Budi Santoso',    'budi@sekolah.sch.id',   '081234567890', 'SD',   'Guru Kelas 3', 'staff',      'password'],
        ['Sari Dewi',       'sari@sekolah.sch.id',    '082345678901', 'TK',   'Guru TK A',    'staff',      'password'],
        ['Hendra Wijaya',   'hendra@sekolah.sch.id',  '',             'SMP',  'Manager SMP',  'staff',      'password'],
        ['Maya Sekretaris', 'maya2@sekolah.sch.id',   '089876543210', 'Umum', 'Sekretaris',   'staff',      'password'],
        ['Admin Kegiatan',  'admin@sekolah.sch.id',   '085678901234', 'Umum', 'Admin',         'admin',      'password'],
        ['Admin IT',        'it@sekolah.sch.id',      '',             'IT',   'Admin Sistem',  'superadmin', 'rahasia123'],
    ];

    foreach ($examples as $ri => $row) {
        $rowNum = $ri + 2;
        foreach ($row as $ci => $val) {
            $sheet->setCellValue($cols[$ci].$rowNum, $val);
        }
        $bg = $ri % 2 === 0 ? 'f8fafc' : 'ffffff';
        $sheet->getStyle("A{$rowNum}:G{$rowNum}")->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'e2e8f0']]],
        ]);
    }

    // Border outline
    $lastRow = count($examples) + 1;
    $sheet->getStyle("A1:G{$lastRow}")->applyFromArray([
        'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1a3a5c']]],
    ]);

    // Sheet Petunjuk
    $guide     = $spreadsheet->createSheet()->setTitle('Petunjuk');
    
    // ── Get divisions for template ──
    $divStmt = $pdo->prepare("SELECT nama FROM divisions ORDER BY urutan ASC");
    $divStmt->execute();
    $divList = implode(' / ', array_column($divStmt->fetchAll(), 'nama')) ?: 'TK / SD / SMP / Umum / IT';
    
    $guideRows = [
        ['PETUNJUK IMPORT DATA SDM — SAPAcara', ''],
        ['', ''],
        ['Kolom', 'Keterangan'],
        ['nama *',        'Nama lengkap SDM (wajib)'],
        ['email *',       'Email unik untuk login (wajib)'],
        ['no_wa',         'Nomor WhatsApp, format: 08xxxxxxxxxx (opsional)'],
        ['divisi *',      'Tulis divisi yang ada atau baru: ' . $divList . ' (wajib)'],
        ['jabatan',       'Jabatan SDM (opsional)'],
        ['role_sistem',   'staff (default), admin, atau superadmin'],
        ['password',      'Password awal login, default "password" jika kosong'],
        ['', ''],
        ['Catatan:', ''],
        ['', '• Baris pertama (header) jangan diubah atau dihapus'],
        ['', '• Kolom bertanda * wajib diisi'],
        ['', '• Email duplikat akan diupdate jika data berbeda, atau dilewati jika tidak ada perubahan'],
        ['', '• Password bisa diganti setelah login pertama'],
        ['', '• Maks upload 5MB, format .xlsx / .xls / .csv'],
    ];
    foreach ($guideRows as $ri => $r) {
        $guide->setCellValue('A'.($ri+1), $r[0]);
        $guide->setCellValue('B'.($ri+1), $r[1]);
    }
    $guide->getColumnDimension('A')->setWidth(20);
    $guide->getColumnDimension('B')->setWidth(58);
    $guide->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1a3a5c');
    $guide->getStyle('A3:B3')->getFont()->setBold(true);
    $guide->getStyle('A3:B3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('e8f1f9');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Template_Import_SDM_SAPAcara.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}

ob_end_flush();

// ── Halaman normal ──
$pageTitle = 'Import SDM via Excel';
require_once __DIR__ . '/../../includes/layout/header.php';
if (!isSuperAdmin()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/');
    exit;
}

$preview = []; $errors = [];

// ── Upload & Parse ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] === 0) {
    $file = $_FILES['file_excel'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx','xls','csv'])) {
        $errors[] = 'Format file tidak didukung. Gunakan .xlsx, .xls, atau .csv';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Ukuran file maksimal 5MB.';
    } else {
        try {
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            array_shift($rows); // buang baris header

            // ── Fetch valid divisions from DB ──
            $divStmt = $pdo->prepare("SELECT nama FROM divisions ORDER BY urutan ASC");
            $divStmt->execute();
            $divisiValid = array_column($divStmt->fetchAll(), 'nama');

            foreach ($rows as $i => $row) {
                // Skip baris kosong
                if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) continue;

                $nama     = trim((string)($row[0] ?? ''));
                $email    = strtolower(trim((string)($row[1] ?? '')));
                $no_wa    = trim((string)($row[2] ?? ''));
                $divisi   = trim((string)($row[3] ?? ''));
                $jabatan  = trim((string)($row[4] ?? ''));
                $role     = strtolower(trim((string)($row[5] ?? 'staff')));
                $passRaw  = trim((string)($row[6] ?? ''));
                $pass     = $passRaw === '' ? null : $passRaw;

                if (!in_array($role, ['staff','admin','superadmin'])) $role = 'staff';

                $rowErrors = [];
                if (!$nama)  $rowErrors[] = 'Nama kosong';
                if (!$email) $rowErrors[] = 'Email kosong';
                elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $rowErrors[] = 'Format email salah';
                if (!$divisi) $rowErrors[] = 'Divisi kosong';

                $existingUser = null;
                $exists = false;
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $cek = $pdo->prepare("SELECT id,nama,no_wa,divisi,jabatan,role_sistem FROM users WHERE email = ?");
                    $cek->execute([$email]);
                    $existingUser = $cek->fetch(PDO::FETCH_ASSOC);
                    $exists = (bool)$existingUser;
                }

                $needsUpdate = false;
                if ($exists && empty($rowErrors)) {
                    $compareFields = [
                        'nama' => $nama,
                        'no_wa' => $no_wa,
                        'divisi' => $divisi,
                        'jabatan' => $jabatan,
                        'role_sistem' => $role,
                    ];
                    foreach ($compareFields as $field => $value) {
                        if (($existingUser[$field] ?? '') !== $value) {
                            $needsUpdate = true;
                            break;
                        }
                    }
                    if (!$needsUpdate && $pass !== null) {
                        $needsUpdate = true;
                    }
                }

                $isNewDivision = $divisi !== '' && !in_array($divisi, $divisiValid);
                $preview[] = compact('nama','email','no_wa','divisi','jabatan','role','pass','rowErrors','exists','needsUpdate','isNewDivision');
            }

            // PR-02: Save preview to session instead of hidden input
            $_SESSION['import_preview'] = $preview;

            if (empty($preview)) {
                $errors[] = 'File tidak berisi data. Pastikan data dimulai dari baris ke-2.';
            }
        } catch (\Exception $e) {
            $errors[] = 'Gagal membaca file: ' . $e->getMessage();
        }
    }
}

// ── Konfirmasi Import ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $rows = $_SESSION['import_preview'] ?? [];
    $ok = 0; $skip = 0;
    foreach ($rows as $r) {
        if (!empty($r['rowErrors'])) { $skip++; continue; }

        try {
            if (!$r['exists']) {
                $divisi = trim($r['divisi']);
                if ($divisi !== '') {
                    $checkDiv = $pdo->prepare("SELECT id FROM divisions WHERE nama = ?");
                    $checkDiv->execute([$divisi]);
                    if (!$checkDiv->fetchColumn()) {
                        $nextOrder = $pdo->query("SELECT COALESCE(MAX(urutan), 0) + 1 FROM divisions")->fetchColumn();
                        $pdo->prepare("INSERT INTO divisions (nama, urutan) VALUES (?, ?)")
                            ->execute([$divisi, $nextOrder]);
                    }
                }

                $pdo->prepare("INSERT INTO users (nama,email,password,no_wa,divisi,jabatan,role_sistem) VALUES (?,?,?,?,?,?,?)")
                    ->execute([
                        $r['nama'],
                        $r['email'],
                        password_hash($r['pass'] ?? 'password', PASSWORD_DEFAULT),
                        $r['no_wa'],
                        $r['divisi'],
                        $r['jabatan'],
                        $r['role'],
                    ]);
                $ok++;
            } elseif (!empty($r['needsUpdate'])) {
                $divisi = trim($r['divisi']);
                if ($divisi !== '') {
                    $checkDiv = $pdo->prepare("SELECT id FROM divisions WHERE nama = ?");
                    $checkDiv->execute([$divisi]);
                    if (!$checkDiv->fetchColumn()) {
                        $nextOrder = $pdo->query("SELECT COALESCE(MAX(urutan), 0) + 1 FROM divisions")->fetchColumn();
                        $pdo->prepare("INSERT INTO divisions (nama, urutan) VALUES (?, ?)")
                            ->execute([$divisi, $nextOrder]);
                    }
                }

                $params = [$r['nama'], $r['no_wa'], $r['divisi'], $r['jabatan'], $r['role']];
                $sql = "UPDATE users SET nama = ?, no_wa = ?, divisi = ?, jabatan = ?, role_sistem = ?";
                if (!empty($r['pass'])) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($r['pass'], PASSWORD_DEFAULT);
                }
                $sql .= " WHERE email = ?";
                $params[] = $r['email'];
                $pdo->prepare($sql)->execute($params);
                $ok++;
            } else {
                $skip++;
            }
        } catch (\Exception $e) {
            $skip++;
        }
    }
    $pdo->prepare("INSERT INTO sdm_import_log (filename,total_rows,success_rows,skip_rows,imported_by) VALUES (?,?,?,?,?)")
        ->execute(['Import Excel', count($rows), $ok, $skip, $_SESSION['user_id']]);
    unset($_SESSION['import_preview']);
    setFlash("Import selesai! $ok SDM berhasil diproses, $skip baris dilewati.", $ok > 0 ? 'success' : 'warning');
    header('Location: ' . BASE_URL . '/modules/users/');
    exit;
}

$validCount = count(array_filter($preview, fn($r) => empty($r['rowErrors']) && (!$r['exists'] || !empty($r['needsUpdate']))));
$skipCount  = count($preview) - $validCount;
?>

<div class="page-header">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/modules/users/" class="back-btn"><i class="bi bi-arrow-left"></i></a>
    <div>
      <h5>Import SDM via Excel</h5>
      <div class="sub">Upload file Excel untuk mendaftarkan banyak SDM sekaligus</div>
    </div>
  </div>
  <a href="?download_template=1" class="btn btn-success">
    <i class="bi bi-file-earmark-excel me-1"></i> Download Template
  </a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger mb-4">
    <?php foreach ($errors as $e): ?>
      <div><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (empty($preview)): ?>

<!-- Langkah-langkah -->
<div class="row g-3 mb-4">
  <?php
  $steps = [
    ['①','var(--success)', 'file-earmark-arrow-down', 'Download Template', 'Klik "Download Template" di kanan atas. File Excel berisi format kolom dan contoh data.'],
    ['②','var(--accent)',  'pencil-square',            'Isi Data SDM',      'Isi data mulai baris ke-2. Jangan ubah atau hapus baris header (baris pertama).'],
    ['③','var(--primary)', 'cloud-upload',             'Upload & Import',   'Upload file di form bawah. Sistem menampilkan preview & validasi sebelum data disimpan.'],
  ];
  foreach ($steps as $s): ?>
  <div class="col-md-4">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="fw-800 mb-2" style="font-size:2.2rem;color:<?= $s[1] ?>"><?= $s[0] ?></div>
        <i class="bi bi-<?= $s[2] ?> fs-2 mb-2 d-block" style="color:<?= $s[1] ?>"></i>
        <h6 class="fw-700 mb-2"><?= $s[3] ?></h6>
        <p class="small mb-0" style="color:var(--text-muted)"><?= $s[4] ?></p>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Format Kolom -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-table"></i> Format Kolom Excel</div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead><tr><th width="50">Kolom</th><th>Header</th><th width="100">Wajib?</th><th>Nilai Valid</th></tr></thead>
      <tbody>
        <tr><td class="text-center fw-700">A</td><td><code>nama</code></td><td><span class="badge bg-danger">Wajib</span></td><td>Nama lengkap SDM</td></tr>
        <tr><td class="text-center fw-700">B</td><td><code>email</code></td><td><span class="badge bg-danger">Wajib</span></td><td>Email valid dan unik</td></tr>
        <tr><td class="text-center fw-700">C</td><td><code>no_wa</code></td><td><span class="badge bg-secondary">Opsional</span></td><td>Format: 08xxxxxxxxxx</td></tr>
        <tr><td class="text-center fw-700">D</td><td><code>divisi</code></td><td><span class="badge bg-danger">Wajib</span></td><td><?php
          $divStmt2 = $pdo->prepare("SELECT GROUP_CONCAT(nama SEPARATOR ' / ') as divs FROM divisions ORDER BY urutan");
          $divStmt2->execute();
          $divText = $divStmt2->fetchColumn() ?: 'TK / SD / SMP / Umum / IT';
          echo htmlspecialchars($divText);
        ?></td></tr>
        <tr><td class="text-center fw-700">E</td><td><code>jabatan</code></td><td><span class="badge bg-secondary">Opsional</span></td><td>Jabatan SDM</td></tr>
        <tr><td class="text-center fw-700">F</td><td><code>role_sistem</code></td><td><span class="badge bg-secondary">Opsional</span></td><td><code>staff</code> (default) / <code>admin</code> / <code>superadmin</code></td></tr>
        <tr><td class="text-center fw-700">G</td><td><code>password</code></td><td><span class="badge bg-secondary">Opsional</span></td><td>Default: <code>password</code></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Upload Form -->
<div class="card">
  <div class="card-header"><i class="bi bi-cloud-upload"></i> Upload File Excel</div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">

          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="mb-3">
        <label class="form-label">Pilih File</label>
        <input type="file" name="file_excel" class="form-control" accept=".xlsx,.xls,.csv" required>
        <div class="form-text">Format: .xlsx, .xls, .csv — Maksimal 5MB per file</div>
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-search me-1"></i> Proses & Tampilkan Preview
      </button>
    </form>
  </div>
</div>

<?php else: ?>

<!-- Stats Preview -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#1a3a5c,#245a8a)">
      <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
      <div><div class="stat-num"><?= count($preview) ?></div><div class="stat-label">Total Baris</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#059669,#10b981)">
      <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
      <div><div class="stat-num"><?= $validCount ?></div><div class="stat-label">Siap Diimport</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#d97706,#f59e0b)">
      <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div><div class="stat-num"><?= count(array_filter($preview, fn($r) => $r['exists'])) ?></div><div class="stat-label">Email Terdaftar</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#dc2626,#ef4444)">
      <div class="stat-icon"><i class="bi bi-x-circle-fill"></i></div>
      <div><div class="stat-num"><?= count(array_filter($preview, fn($r) => !empty($r['rowErrors']))) ?></div><div class="stat-label">Error Validasi</div></div>
    </div>
  </div>
</div>

<!-- Preview Table -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-table"></i> Preview Data (<?= count($preview) ?> baris)</span>
    <div class="d-flex gap-2 flex-wrap">
      <span class="badge bg-success">✅ Valid = akan diimport</span>
      <span class="badge bg-info text-dark">🔁 Update = email sudah ada, data diperbarui</span>
      <span class="badge bg-warning text-dark">⚠️ Duplikat = tidak ada perubahan</span>
      <span class="badge bg-danger">❌ Error = dilewati</span>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive" style="max-height:460px;overflow-y:auto">
      <table class="table table-sm mb-0">
        <thead style="position:sticky;top:0;z-index:2">
          <tr><th>#</th><th>Nama</th><th>Email</th><th>Divisi</th><th>Jabatan</th><th>Role</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($preview as $i => $r):
          $isErr  = !empty($r['rowErrors']);
          $rowCls = $isErr ? 'table-danger' : ($r['exists'] ? ($r['needsUpdate'] ? 'table-info' : 'table-warning') : '');
        ?>
          <tr class="<?= $rowCls ?>">
            <td class="text-muted fw-500"><?= $i+2 ?></td>
            <td class="fw-600"><?= htmlspecialchars($r['nama'] ?: '—') ?></td>
            <td class="fs-12"><?= htmlspecialchars($r['email'] ?: '—') ?></td>
            <td><?= htmlspecialchars($r['divisi'] ?: '—') ?></td>
            <td class="fs-12"><?= htmlspecialchars($r['jabatan'] ?: '—') ?></td>
            <td><span class="badge <?= $r['role']==='superadmin'?'bg-danger':($r['role']==='admin'?'bg-primary':'bg-secondary') ?>"><?= $r['role'] ?></span></td>
            <td>
              <?php if ($isErr): ?>
                <span class="badge bg-danger">❌ Error</span>
                <div class="fs-12 text-danger mt-1"><?= htmlspecialchars(implode(', ', $r['rowErrors'])) ?></div>
              <?php elseif ($r['exists'] && $r['needsUpdate']): ?>
                <span class="badge bg-info text-dark">🔁 Update</span>
                <div class="fs-12" style="color:#0f766e">Email sudah ada, data akan diperbarui</div>
              <?php elseif ($r['exists']): ?>
                <span class="badge bg-warning text-dark">⚠️ Duplikat</span>
                <div class="fs-12" style="color:#92400e">Email sudah terdaftar, tidak ada perubahan</div>
              <?php else: ?>
                <span class="badge bg-success">✅ Valid</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Aksi -->
<div class="d-flex gap-3 align-items-center flex-wrap">
  <?php if ($validCount > 0): ?>
  <form method="POST">

          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <button type="submit" name="confirm_import" class="btn btn-success btn-lg"
            data-confirm="Import <?= $validCount ?> SDM ke sistem? <?= $skipCount ?> baris akan dilewati.">
      <i class="bi bi-cloud-upload me-2"></i>Import <?= $validCount ?> SDM Sekarang
    </button>
  </form>
  <?php else: ?>
    <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Tidak ada data valid. Perbaiki error dan upload ulang.</div>
  <?php endif; ?>
  <a href="?" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-counterclockwise me-1"></i> Upload Ulang
  </a>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
