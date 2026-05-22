<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Sesi & login ─────────────────────────────────────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/modules/auth/login.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

// ── Role checks ───────────────────────────────────────────────────────────────

function isSuperAdmin(): bool {
    return (currentUser()['role_sistem'] ?? '') === 'superadmin';
}

function isAdmin(): bool {
    $role = currentUser()['role_sistem'] ?? '';
    return $role === 'admin' || $role === 'superadmin';
}

function isStrictAdmin(): bool {
    return (currentUser()['role_sistem'] ?? '') === 'admin';
}

function getRoleLabel(string $role = ''): string {
    if ($role === '') $role = currentUser()['role_sistem'] ?? 'staff';
    return match($role) {
        'superadmin' => 'Super Admin',
        'admin'      => 'Admin',
        default      => 'Staff',
    };
}

// ── Permission check ──────────────────────────────────────────────────────────
//
// Alur: superadmin → selalu true (bypass DB).
//       Role lain  → cek $_SESSION['permissions'] yang di-load saat login.
//       Jika session kosong (misal session lama sebelum fitur ini), fallback ke
//       nilai default konservatif (false).

function hasPermission(string $feature): bool {
    if (isSuperAdmin()) return true;

    $perms = $_SESSION['permissions'] ?? [];
    return (bool)($perms[$feature] ?? false);
}

// Load semua permission role dari DB ke session — dipanggil saat login.
function loadPermissions(PDO $pdo): void {
    $role = currentUser()['role_sistem'] ?? 'staff';
    if ($role === 'superadmin') {
        // superadmin tidak perlu di-cache — hasPermission() bypass DB
        $_SESSION['permissions'] = [];
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT feature, is_allowed FROM role_permissions WHERE role = ?"
    );
    $stmt->execute([$role]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['feature' => is_allowed]

    $_SESSION['permissions'] = array_map('boolval', $rows);
}

// Refresh permission session — dipanggil setelah admin mengubah matrix di halaman settings.
function refreshPermissions(PDO $pdo): void {
    loadPermissions($pdo);
}

// ── Event-level helpers ───────────────────────────────────────────────────────

function isPIC(int $eventId, PDO $pdo): bool {
    $uid = $_SESSION['user_id'] ?? 0;
    $q   = $pdo->prepare(
        "SELECT 1 FROM event_panitia WHERE event_id=? AND user_id=? AND peran_acara='pic' LIMIT 1"
    );
    $q->execute([$eventId, $uid]);
    return (bool)$q->fetchColumn();
}

function isEventAdmin(int $eventId, PDO $pdo): bool {
    $uid = $_SESSION['user_id'] ?? 0;
    $q   = $pdo->prepare(
        "SELECT 1 FROM event_panitia WHERE event_id=? AND user_id=? AND is_event_admin=1 LIMIT 1"
    );
    $q->execute([$eventId, $uid]);
    return (bool)$q->fetchColumn();
}

// ── Flash & notifikasi ────────────────────────────────────────────────────────

function setFlash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function addNotif(PDO $pdo, int $userId, string $judul, string $pesan, string $link = '', string $tipe = 'info'): void {
    $pdo->prepare(
        "INSERT INTO notifications (user_id, judul, pesan, link, tipe) VALUES (?,?,?,?,?)"
    )->execute([$userId, $judul, $pesan, $link, $tipe]);
}

function countUnreadNotif(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
    );
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
