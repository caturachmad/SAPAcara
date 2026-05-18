<?php
if (session_status() === PHP_SESSION_NONE) session_start();

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

function isSuperAdmin(): bool {
    return (currentUser()['role_sistem'] ?? '') === 'superadmin';
}

function isPIC(int $eventId, PDO $pdo): bool {
    $uid = $_SESSION['user_id'] ?? 0;
    $q = $pdo->prepare("SELECT 1 FROM event_panitia WHERE event_id=? AND user_id=? AND peran_acara='pic' LIMIT 1");
    $q->execute([$eventId, $uid]);
    return (bool)$q->fetchColumn();
}

function isEventAdmin(int $eventId, PDO $pdo): bool {
    $uid = $_SESSION['user_id'] ?? 0;
    $q = $pdo->prepare("SELECT 1 FROM event_panitia WHERE event_id=? AND user_id=? AND is_event_admin=1 LIMIT 1");
    $q->execute([$eventId, $uid]);
    return (bool)$q->fetchColumn();
}

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
    $pdo->prepare("INSERT INTO notifications (user_id, judul, pesan, link, tipe) VALUES (?,?,?,?,?)")
        ->execute([$userId, $judul, $pesan, $link, $tipe]);
}

function countUnreadNotif(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}
