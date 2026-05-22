<?php
$pageTitle = 'Manajemen SDM';
require_once __DIR__ . '/../../includes/layout/header.php';
if (!isSuperAdmin()) { header('Location: ' . BASE_URL . '/modules/dashboard/select.php'); exit; }

// ── Handle edit user ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $tid      = (int)$_POST['target_id'];
    $nama     = trim($_POST['nama'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $divisi   = trim($_POST['divisi'] ?? '');
    $jabatan  = trim($_POST['jabatan'] ?? '');
    $roleS    = in_array($_POST['role_sistem'], ['superadmin','admin','staff']) ? $_POST['role_sistem'] : 'staff';

    $jabatanS = in_array($_POST['jabatan_sistem'], ['staff','bendahara_tertinggi','kepala_sekolah','manager_tk','manager_sd','manager_smp'])
               ? $_POST['jabatan_sistem'] : 'staff';

    if ($tid !== 1 || $roleS === 'superadmin') {
        $params = [$nama, $email, $divisi, $jabatan, $roleS, $jabatanS];
        $sql = "UPDATE users SET nama=?, email=?, divisi=?, jabatan=?, role_sistem=?, jabatan_sistem=?";
        if (isset($_POST['reset_password'])) {
            $sql .= ", password=?";
            $params[] = password_hash('password', PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id=?";
        $params[] = $tid;
        $pdo->prepare($sql)->execute($params);
        setFlash('Data SDM berhasil diperbarui.', 'success');
    }
    header('Location: ?'); exit;
}

// ── Handle bulk action ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $ids = array_filter(array_map('intval', $_POST['bulk_user_ids'] ?? []));
    if (!empty($ids)) {
        $ids = array_values(array_filter($ids, fn($id) => $id !== 1));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($_POST['bulk_action_type'] === 'delete') {
                $pdo->prepare("DELETE FROM event_panitia WHERE user_id IN ($placeholders)")
                    ->execute($ids);
                $pdo->prepare("DELETE FROM notifications WHERE user_id IN ($placeholders)")
                    ->execute($ids);
                $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)")
                    ->execute($ids);
                setFlash('SDM terpilih berhasil dihapus.', 'success');
            } elseif ($_POST['bulk_action_type'] === 'divisi') {
                $divisi = trim($_POST['bulk_divisi'] ?? '');
                if ($divisi) {
                    $pdo->prepare("UPDATE users SET divisi=? WHERE id IN ($placeholders)")
                        ->execute(array_merge([$divisi], $ids));
                    setFlash('Divisi SDM terpilih diperbarui.', 'success');
                }
            }
        }
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

// ── Handle delete user ──
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tid = (int)$_GET['delete'];
    if ($tid !== 1) {
        $pdo->prepare("DELETE FROM event_panitia WHERE user_id=?")->execute([$tid]);
        $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$tid]);
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$tid]);
        setFlash('SDM berhasil dihapus.', 'success');
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

// ── Fetch divisions from DB ──
$divStmt = $pdo->prepare("SELECT nama FROM divisions ORDER BY urutan ASC");
$divStmt->execute();
$divisiOpts = array_column($divStmt->fetchAll(), 'nama');

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
  <div class="col-auto"><span class="badge bg-primary py-2 px-3"><i class="bi bi-shield-half me-1"></i>Admin</span></div>
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
        <option value="admin"      <?= $roleFilter==='admin'?'selected':'' ?>>Admin</option>
        <option value="staff"      <?= $roleFilter==='staff'?'selected':'' ?>>Staff</option>
      </select>
    </div>
    <div class="col-md-auto d-flex gap-2">
      <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
    </div>
  </form>
</div>

<div class="text-muted fs-12 mb-2">Pilih beberapa SDM untuk pindah divisi atau hapus sekaligus.</div>
<div id="bulkActionsBar" class="d-flex flex-wrap align-items-center gap-2 mb-3 d-none">
  <button id="bulkEditBtn" class="btn btn-outline-primary btn-sm" disabled>
    <i class="bi bi-pencil-square me-1"></i> Bulk Edit
  </button>
  <button id="bulkDeactivateBtn" class="btn btn-outline-danger btn-sm" disabled>
    <i class="bi bi-trash me-1"></i> Bulk Hapus
  </button>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th style="width:40px"><input type="checkbox" id="selectAllUsers"></th>
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
            <td class="text-center align-middle">
              <?php if ($u['id'] !== 1): ?>
                <input type="checkbox" class="user-select-checkbox" value="<?= $u['id'] ?>">
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php $avatarBg = $u['role_sistem']==='superadmin' ? '#dc2626' : ($u['role_sistem']==='admin' ? '#2563eb' : '#1a3a5c'); ?>
                <div class="avatar avatar-sm"
                     style="background:<?= $avatarBg ?>">
                  <?= strtoupper(substr($u['nama'],0,2)) ?>
                </div>
                <div>
                  <div class="fw-600"><?= htmlspecialchars($u['nama']) ?></div>
                  <?php if ($u['role_sistem']==='superadmin'): ?>
                    <div class="text-warning fs-12 d-flex align-items-center gap-1">
                      <i class="bi bi-lightning-charge-fill"></i>Super Admin
                    </div>
                  <?php elseif ($u['role_sistem']==='admin'): ?>
                    <div class="text-primary fs-12 d-flex align-items-center gap-1">
                      <i class="bi bi-lightning-charge-fill"></i>Admin
                    </div>
                  <?php else: ?>
                    <div class="fs-12 text-muted"><?= htmlspecialchars($u['jabatan']??'') ?></div>
                  <?php endif; ?>
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
              <?php if ($u['role_sistem'] === 'superadmin'): ?>
                <span class="badge bg-danger"><i class="bi bi-shield-fill me-1"></i>Super Admin</span>
              <?php elseif ($u['role_sistem'] === 'admin'): ?>
                <span class="badge bg-primary"><i class="bi bi-shield-half me-1"></i>Admin</span>
              <?php else: ?>
                <span class="badge bg-secondary"><i class="bi bi-person me-1"></i>Staff</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $u['status']==='aktif'?'bg-success':'bg-secondary' ?>">
                <?= $u['status'] ?>
              </span>
            </td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary edit-user-btn" title="Edit SDM"
                        data-bs-toggle="modal" data-bs-target="#modalEditUser"
                        data-id="<?= $u['id'] ?>"
                        data-nama="<?= htmlspecialchars($u['nama']) ?>"
                        data-email="<?= htmlspecialchars($u['email']) ?>"
                        data-divisi="<?= htmlspecialchars($u['divisi']??'') ?>"
                        data-jabatan="<?= htmlspecialchars($u['jabatan']??'') ?>"
                        data-role="<?= $u['role_sistem'] ?>"
                        data-jabatan-sistem="<?= $u['jabatan_sistem']??'staff' ?>">
                  <i class="bi bi-pencil-square"></i>
                </button>
                <?php if ($u['id'] != 1): ?>
                <a href="?delete=<?= $u['id'] ?>"
                   class="btn btn-sm btn-outline-danger"
                   data-confirm="Hapus SDM ini?"
                   title="Hapus SDM">
                  <i class="bi bi-trash"></i>
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

<!-- ── Modal Edit SDM ── -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-700"><i class="bi bi-pencil-square me-2"></i>Edit SDM</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="target_id" id="editTargetId">
        <div class="modal-body">
          <div class="alert alert-info fs-13 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Mengubah data untuk: <strong id="editTargetNama"></strong>
          </div>

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nama Lengkap</label>
              <input type="text" name="nama" id="editNama" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="editEmail" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Divisi</label>
              <select name="divisi" id="editDivisi" class="form-select">
                <option value="">Pilih Divisi</option>
                <?php foreach ($divisiOpts as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Jabatan</label>
              <input type="text" name="jabatan" id="editJabatan" class="form-control" placeholder="cth: Guru Kelas 3">
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-700">Role Sistem</label>
            <div class="row g-2">
              <div class="col-4">
                <input type="radio" class="btn-check" name="role_sistem" id="roleStaff" value="staff">
                <label class="btn btn-outline-secondary w-100 text-start" for="roleStaff">
                  <i class="bi bi-person me-2"></i>
                  <strong>Staff</strong>
                  <div class="fs-12 mt-1 fw-400">Akses standar</div>
                </label>
              </div>
              <div class="col-4">
                <input type="radio" class="btn-check" name="role_sistem" id="roleAdmin" value="admin">
                <label class="btn btn-outline-primary w-100 text-start" for="roleAdmin">
                  <i class="bi bi-shield-half me-2"></i>
                  <strong>Admin</strong>
                  <div class="fs-12 mt-1 fw-400">Akses administrasi</div>
                </label>
              </div>
              <div class="col-4">
                <input type="radio" class="btn-check" name="role_sistem" id="roleSuperadmin" value="superadmin">
                <label class="btn btn-outline-danger w-100 text-start" for="roleSuperadmin">
                  <i class="bi bi-shield-fill me-2"></i>
                  <strong>Super Admin</strong>
                  <div class="fs-12 mt-1 fw-400">Akses penuh seluruh sistem</div>
                </label>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-700">Jabatan dalam Hierarki Sekolah</label>
            <div class="form-text mb-2">Menentukan approver untuk tipe acara tertentu</div>
            <select name="jabatan_sistem" id="jabatanSistemSelect" class="form-select">
              <option value="staff">Staff Biasa</option>
              <optgroup label="Manager Level">
                <option value="manager_tk">Manager TK</option>
                <option value="manager_sd">Manager SD</option>
                <option value="manager_smp">Manager SMP</option>
              </optgroup>
              <optgroup label="Jabatan Administratif">
                <option value="bendahara_tertinggi">Bendahara Tertinggi</option>
                <option value="kepala_sekolah">Kepala Sekolah</option>
              </optgroup>
            </select>
          </div>

          <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" value="1" id="resetPassword" name="reset_password">
            <label class="form-check-label" for="resetPassword">Reset password ke nilai awal <strong>password</strong></label>
          </div>

          <div id="jabatanInfo" class="alert alert-warning fs-12 d-none">
            <i class="bi bi-info-circle me-1"></i>
            <span id="jabatanInfoText"></span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="edit_user" class="btn btn-primary">
            <i class="bi bi-save me-1"></i> Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal Bulk Edit SDM ── -->
<div class="modal fade" id="modalBulkEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-700"><i class="bi bi-people me-2"></i>Bulk Edit SDM</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="bulk_action" value="1">
        <input type="hidden" name="bulk_action_type" value="divisi">
        <div id="bulkUserIdsContainer"></div>
        <div class="modal-body">
          <div class="alert alert-info fs-13 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Ubah divisi untuk SDM terpilih.
          </div>
          <div class="mb-3">
            <label class="form-label">Divisi Baru</label>
            <select name="bulk_divisi" class="form-select">
              <?php foreach ($divisiOpts as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-text">Terpilih: <span id="bulkSelectedCount">0</span> SDM.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i> Terapkan ke Terpilih
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
                <option value="admin">Admin</option>
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

// Modal edit user
const editUserModal = document.getElementById('modalEditUser');
document.querySelectorAll('.edit-user-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    document.getElementById('editTargetId').value = this.dataset.id;
    document.getElementById('editTargetNama').textContent = this.dataset.nama;
    document.getElementById('editNama').value = this.dataset.nama;
    document.getElementById('editEmail').value = this.dataset.email;
    document.getElementById('editDivisi').value = this.dataset.divisi;
    document.getElementById('editJabatan').value = this.dataset.jabatan;
    const roleMap = { superadmin: 'roleSuperadmin', admin: 'roleAdmin', staff: 'roleStaff' };
    const roleRadio = roleMap[this.dataset.role] ?? 'roleStaff';
    document.getElementById(roleRadio).checked = true;
    document.getElementById('jabatanSistemSelect').value = this.dataset.jabatanSistem || 'staff';
    document.getElementById('resetPassword').checked = false;
    updateJabatanInfo();
  });
});

// Bulk selection
const selectAllCheckbox = document.getElementById('selectAllUsers');
const bulkEditBtn = document.getElementById('bulkEditBtn');
const bulkDeactivateBtn = document.getElementById('bulkDeactivateBtn');
const bulkSelectedCount = document.getElementById('bulkSelectedCount');
const bulkUserIdsContainer = document.getElementById('bulkUserIdsContainer');
const bulkActionModal = new bootstrap.Modal(document.getElementById('modalBulkEdit'));

function getSelectedUserIds() {
  return Array.from(document.querySelectorAll('.user-select-checkbox:checked')).map(chk => chk.value);
}

function updateBulkButtons() {
  const selected = getSelectedUserIds();
  const enabled = selected.length > 0;
  bulkEditBtn.disabled = !enabled;
  bulkDeactivateBtn.disabled = !enabled;
  bulkSelectedCount.textContent = selected.length;
  document.getElementById('bulkActionsBar').classList.toggle('d-none', !enabled);
}

document.querySelectorAll('.user-select-checkbox').forEach(chk => {
  chk.addEventListener('change', updateBulkButtons);
});

selectAllCheckbox?.addEventListener('change', function () {
  document.querySelectorAll('.user-select-checkbox').forEach(chk => {
    chk.checked = this.checked;
  });
  updateBulkButtons();
});

updateBulkButtons();

bulkEditBtn?.addEventListener('click', function () {
  const ids = getSelectedUserIds();
  bulkUserIdsContainer.innerHTML = ids.map(id => `<input type="hidden" name="bulk_user_ids[]" value="${id}">`).join('');
  updateBulkButtons();
  bulkActionModal.show();
});

bulkDeactivateBtn?.addEventListener('click', function () {
  const ids = getSelectedUserIds();
  if (!ids.length) return;
  if (!confirm('Hapus semua SDM terpilih?')) return;
  const form = document.createElement('form');
  form.method = 'POST';
  form.style.display = 'none';
  form.innerHTML = `<input type="hidden" name="bulk_action" value="1">
    <input type="hidden" name="bulk_action_type" value="delete">` +
    ids.map(id => `<input type="hidden" name="bulk_user_ids[]" value="${id}">`).join('');
  document.body.appendChild(form);
  form.submit();
});

</script>

<?php require_once __DIR__ . '/../../includes/layout/footer.php'; ?>
