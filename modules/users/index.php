<?php
$pageTitle = 'Manajemen SDM';
require_once __DIR__ . '/../../includes/layout/header.php';
if (!isSuperAdmin()) { header('Location: ' . BASE_URL . '/modules/dashboard/select.php'); exit; }

// ── Handle ubah role ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah_role'])) {
    $tid           = (int)$_POST['target_id'];
    $roleS         = in_array($_POST['role_sistem'],['superadmin','staff']) ? $_POST['role_sistem'] : 'staff';
    $jabatanS      = in_array($_POST['jabatan_sistem'],['staff','bendahara_tertinggi','kepala_sekolah','manager_tk','manager_sd','manager_smp'])
                     ? $_POST['jabatan_sistem'] : 'staff';
    if ($tid !== 1 || $roleS === 'superadmin') { // Protect user ID 1
        $pdo->prepare("UPDATE users SET role_sistem=?, jabatan_sistem=? WHERE id=?")
            ->execute([$roleS, $jabatanS, $tid]);
        setFlash('Role SDM berhasil diperbarui.', 'success');
    }
    header('Location: ?'); exit;
}

// ── Handle tambah user ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    $nama    = trim($_POST['nama']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $divisi  = trim($_POST['divisi']  ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $no_wa   = trim($_POST['no_wa']   ?? '');
    $roleS   = $_POST['role_sistem']   ?? 'staff';
    $jabatanS= $_POST['jabatan_sistem']?? 'staff';
    $pass    = password_hash($_POST['password'] ?? 'password', PASSWORD_DEFAULT);
    try {
        $pdo->prepare("INSERT INTO users (nama,email,password,divisi,jabatan,no_wa,role_sistem,jabatan_sistem) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$nama,$email,$pass,$divisi,$jabatan,$no_wa,$roleS,$jabatanS]);
        setFlash("SDM $nama berhasil ditambahkan.", 'success');
    } catch (\Exception $e) { setFlash('Email sudah terdaftar.', 'danger'); }
    header('Location: ?'); exit;
}

// ── Handle toggle status ──
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    if ($tid !== 1) {
        $pdo->prepare("UPDATE users SET status = IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$tid]);
        setFlash('Status SDM diperbarui.', 'success');
    }
    header('Location: ?'); exit;
}

// ── Query ──
$search    = $_GET['q']      ?? '';
$divFilter = $_GET['divisi'] ?? '';
$roleFilter= $_GET['role']   ?? '';
$where = ['1=1']; $params = [];
if ($search)    { $where[] = '(nama LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($divFilter) { $where[] = 'divisi = ?';        $params[] = $divFilter; }
if ($roleFilter){ $where[] = 'role_sistem = ?';   $params[] = $roleFilter; }
$stmt = $pdo->prepare("SELECT * FROM users WHERE " . implode(' AND ', $where) . " ORDER BY role_sistem DESC, jabatan_sistem, divisi, nama");
$stmt->execute($params); $users = $stmt->fetchAll();

$divisiOpts = ['TK','SD','SMP','Umum','IT'];
$jabatanSistemLabel = [
    'staff'               => ['Staff Biasa',          'secondary', ''],
    'manager_tk'          => ['Manager TK',            'primary',   'bi-person-workspace'],
    'manager_sd'          => ['Manager SD',            'primary',   'bi-person-workspace'],
    'manager_smp'         => ['Manager SMP',           'primary',   'bi-person-workspace'],
    'bendahara_tertinggi' => ['Bendahara Tertinggi',   'warning',   'bi-cash-coin'],
    'kepala_sekolah'      => ['Kepala Sekolah',        'danger',    'bi-award'],
];
?>

<div class="page-header">
  <div>
    <h5>Manajemen SDM</h5>
    <div class="sub"><?= count($users) ?> SDM ditemukan</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/modules/users/import.php" class="btn btn-outline-success">
      <i class="bi bi-file-earmark-excel me-1"></i> Import Excel
    </a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
      <i class="bi bi-person-plus me-1"></i> Tambah SDM
    </button>
  </div>
</div>

<!-- Keterangan Jabatan Sistem -->
<div class="row g-2 mb-4">
  <?php foreach ($jabatanSistemLabel as $k => $v): if ($k === 'staff') continue; ?>
  <div class="col-auto">
    <span class="badge bg-<?= $v[1] ?> py-2 px-3">
      <?php if ($v[2]): ?><i class="bi <?= $v[2] ?> me-1"></i><?php endif; ?>
      <?= $v[0] ?>
    </span>
  </div>
  <?php endforeach; ?>
  <div class="col-auto"><span class="badge bg-danger py-2 px-3"><i class="bi bi-shield-fill me-1"></i>Super Admin</span></div>
</div>

<!-- Filter -->
<div class="filter-bar mb-4">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Cari</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Nama atau email..." value="<?= htmlspecialchars($search) ?>">
      </div>
    </div>
    <div class="col-md-2">
      <label class="form-label">Divisi</label>
      <select name="divisi" class="form-select form-select-sm">
        <option value="">Semua</option>
        <?php foreach ($divisiOpts as $d): ?><option value="<?= $d ?>" <?= $divFilter===$d?'selected':'' ?>><?= $d ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Role</label>
      <select name="role" class="form-select form-select-sm">
        <option value="">Semua Role</option>
        <option value="superadmin" <?= $roleFilter==='superadmin'?'selected':'' ?>>Super Admin</option>
        <option value="staff" <?= $roleFilter==='staff'?'selected':'' ?>>Staff</option>
      </select>
    </div>
    <div class="col-md-auto d-flex gap-2">
      <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
    </div>
  </form>
</div>

<!-- Tabel SDM -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Nama SDM</th>
            <th>Email</th>
            <th>Divisi</th>
            <th>Jabatan Sistem</th>
            <th>Role Sistem</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u):
          [$jsLabel, $jsColor, $jsIcon] = $jabatanSistemLabel[$u['jabatan_sistem'] ?? 'staff'] ?? ['Staff','secondary',''];
        ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm"
                     style="background:<?= $u['role_sistem']==='superadmin'?'#dc2626':'#1a3a5c' ?>">
                  <?= strtoupper(substr($u['nama'],0,2)) ?>
                </div>
                <div>
                  <div class="fw-600"><?= htmlspecialchars($u['nama']) ?></div>
                  <div class="fs-12 text-muted"><?= htmlspecialchars($u['jabatan']??'') ?></div>
                </div>
              </div>
            </td>
            <td class="fs-12"><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['divisi']??'—') ?></td>
            <td>
              <?php if (($u['jabatan_sistem']??'staff') !== 'staff'): ?>
                <span class="badge bg-<?= $jsColor ?>">
                  <?php if ($jsIcon): ?><i class="bi <?= $jsIcon ?> me-1"></i><?php endif; ?>
                  <?= $jsLabel ?>
                </span>
              <?php else: ?>
                <span class="text-muted fs-12">Staff Biasa</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $u['role_sistem']==='superadmin'?'bg-danger':'bg-secondary' ?>">
                <?= $u['role_sistem']==='superadmin'?'⚡ Super Admin':'Staff' ?>
              </span>
            </td>
            <td>
              <span class="badge <?= $u['status']==='aktif'?'bg-success':'bg-secondary' ?>">
                <?= $u['status'] ?>
              </span>
            </td>
            <td>
              <div class="d-flex gap-1">
                <!-- Tombol Ubah Role -->
                <button class="btn btn-sm btn-outline-primary" title="Ubah Role"
                        data-bs-toggle="modal" data-bs-target="#modalUbahRole"
                        data-id="<?= $u['id'] ?>"
                        data-nama="<?= htmlspecialchars($u['nama']) ?>"
                        data-role="<?= $u['role_sistem'] ?>"
                        data-jabatan="<?= $u['jabatan_sistem']??'staff' ?>">
                  <i class="bi bi-shield-lock"></i>
                </button>
                <!-- Toggle Aktif -->
                <?php if ($u['id'] != 1): ?>
                <a href="?toggle=<?= $u['id'] ?>"
                   class="btn btn-sm btn-outline-<?= $u['status']==='aktif'?'warning':'success' ?>"
                   data-confirm="<?= $u['status']==='aktif'?'Nonaktifkan':'Aktifkan' ?> SDM ini?"
                   title="<?= $u['status']==='aktif'?'Nonaktifkan':'Aktifkan' ?>">
                  <i class="bi bi-<?= $u['status']==='aktif'?'pause':'play' ?>-circle"></i>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Modal Ubah Role ── -->
<div class="modal fade" id="modalUbahRole" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-700"><i class="bi bi-shield-lock me-2"></i>Ubah Role & Jabatan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="target_id" id="roleTargetId">
        <div class="modal-body">
          <div class="alert alert-info fs-13 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Mengubah role untuk: <strong id="roleTargetNama"></strong>
          </div>

          <div class="mb-4">
            <label class="form-label fw-700">Role Sistem</label>
            <div class="row g-2">
              <div class="col-6">
                <input type="radio" class="btn-check" name="role_sistem" id="roleStaff" value="staff">
                <label class="btn btn-outline-secondary w-100 text-start" for="roleStaff">
                  <i class="bi bi-person me-2"></i>
                  <strong>Staff</strong>
                  <div class="fs-12 mt-1 fw-400">Akses standar — kelola acara yang diikuti</div>
                </label>
              </div>
              <div class="col-6">
                <input type="radio" class="btn-check" name="role_sistem" id="roleSuperadmin" value="superadmin">
                <label class="btn btn-outline-danger w-100 text-start" for="roleSuperadmin">
                  <i class="bi bi-shield-fill me-2"></i>
                  <strong>Super Admin</strong>
                  <div class="fs-12 mt-1 fw-400">Akses penuh — kelola seluruh sistem</div>
                </label>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-700">Jabatan dalam Hierarki Sekolah</label>
            <div class="form-text mb-2">Menentukan siapa yang bisa menjadi approver untuk tipe tertentu</div>
            <select name="jabatan_sistem" id="jabatanSistemSelect" class="form-select">
              <option value="staff">Staff Biasa (tidak ada jabatan khusus)</option>
              <optgroup label="Manager Level">
                <option value="manager_tk">Manager TK — bisa approve acara TK</option>
                <option value="manager_sd">Manager SD — bisa approve acara SD</option>
                <option value="manager_smp">Manager SMP — bisa approve acara SMP</option>
              </optgroup>
              <optgroup label="Jabatan Administratif">
                <option value="bendahara_tertinggi">💰 Bendahara Tertinggi — approve RAB & anggaran</option>
                <option value="kepala_sekolah">🏫 Kepala Sekolah — approval tertinggi</option>
              </optgroup>
            </select>
          </div>

          <!-- Info jabatan yang dipilih -->
          <div id="jabatanInfo" class="alert alert-warning fs-12 d-none">
            <i class="bi bi-info-circle me-1"></i>
            <span id="jabatanInfoText"></span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="ubah_role" class="btn btn-primary">
            <i class="bi bi-save me-1"></i> Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal Tambah SDM ── -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-700"><i class="bi bi-person-plus me-2"></i>Tambah SDM Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" name="nama" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">No. WhatsApp</label>
              <input type="text" name="no_wa" class="form-control" placeholder="08xxxxxxxxxx">
            </div>
            <div class="col-md-6">
              <label class="form-label">Jabatan</label>
              <input type="text" name="jabatan" class="form-control" placeholder="cth: Guru Kelas 3">
            </div>
            <div class="col-md-4">
              <label class="form-label">Divisi</label>
              <select name="divisi" class="form-select">
                <?php foreach ($divisiOpts as $d): ?><option value="<?=$d?>"><?=$d?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Role Sistem</label>
              <select name="role_sistem" class="form-select">
                <option value="staff">Staff</option>
                <option value="superadmin">Super Admin</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Jabatan Sistem</label>
              <select name="jabatan_sistem" class="form-select">
                <option value="staff">Staff Biasa</option>
                <option value="manager_tk">Manager TK</option>
                <option value="manager_sd">Manager SD</option>
                <option value="manager_smp">Manager SMP</option>
                <option value="bendahara_tertinggi">Bendahara Tertinggi</option>
                <option value="kepala_sekolah">Kepala Sekolah</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Password Awal</label>
              <input type="text" name="password" class="form-control" value="password">
              <div class="form-text">User bisa ganti password setelah login</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="tambah_user" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Tambahkan SDM
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Isi modal ubah role dengan data SDM yang dipilih
document.getElementById('modalUbahRole').addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  document.getElementById('roleTargetId').value   = btn.dataset.id;
  document.getElementById('roleTargetNama').textContent = btn.dataset.nama;

  // Set radio role_sistem
  const roleVal = btn.dataset.role;
  const radioId = roleVal === 'superadmin' ? 'roleSuperadmin' : 'roleStaff';
  document.getElementById(radioId).checked = true;

  // Set jabatan_sistem
  document.getElementById('jabatanSistemSelect').value = btn.dataset.jabatan || 'staff';
  updateJabatanInfo();
});

// Info jabatan
const jabatanInfo = {
  'bendahara_tertinggi': 'Orang ini akan muncul sebagai pilihan Approver Bendahara saat PIC membuat approval RAB.',
  'kepala_sekolah':      'Orang ini akan muncul sebagai Approver Kepala Sekolah untuk approval final acara.',
  'manager_tk':          'Orang ini akan muncul sebagai Approver Manager TK untuk acara level TK.',
  'manager_sd':          'Orang ini akan muncul sebagai Approver Manager SD untuk acara level SD.',
  'manager_smp':         'Orang ini akan muncul sebagai Approver Manager SMP untuk acara level SMP.',
};

function updateJabatanInfo() {
  const val     = document.getElementById('jabatanSistemSelect').value;
  const infoDiv = document.getElementById('jabatanInfo');
  const infoTxt = document.getElementById('jabatanInfoText');
  if (jabatanInfo[val]) {
    infoTxt.textContent = jabatanInfo[val];
    infoDiv.classList.remove('d-none');
  } else {
    infoDiv.classList.add('d-none');
  }
}

document.getElementById('jabatanSistemSelect').addEventListener('change', updateJabatanInfo);
</script>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
