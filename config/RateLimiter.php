<?php
/**
 * RateLimiter.php — Rate limit percobaan login, berbasis tabel DB
 * (bukan Redis, karena hosting sekolah umumnya gak punya Redis).
 *
 * Pakai di modules/auth/login.php:
 *
 *   $limiter = new RateLimiter($pdo);
 *   $identifier = ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $email;
 *
 *   if ($limiter->isBlocked($identifier)) {
 *       $error = 'Terlalu banyak percobaan. Coba lagi dalam ' .
 *                $limiter->getRetryAfterSeconds($identifier) . ' detik.';
 *   } else {
 *       // ... proses login ...
 *       $limiter->recordAttempt($identifier, $loginBerhasil);
 *   }
 */
declare(strict_types=1);

class RateLimiter
{
    private PDO $pdo;
    private int $maxAttempts;
    private int $windowSeconds;

    public function __construct(PDO $pdo, int $maxAttempts = 5, int $windowSeconds = 60)
    {
        $this->pdo = $pdo;
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
    }

    public function isBlocked(string $identifier): bool
    {
        return $this->countRecentFailures($identifier) >= $this->maxAttempts;
    }

    public function getRetryAfterSeconds(string $identifier): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT MIN(created_at) FROM login_attempts
             WHERE identifier = ? AND success = 0
               AND created_at >= (NOW() - INTERVAL ? SECOND)"
        );
        $stmt->execute([$identifier, $this->windowSeconds]);
        $oldest = $stmt->fetchColumn();

        if (!$oldest) {
            return 0;
        }
        $elapsed = time() - strtotime($oldest);
        return max(0, $this->windowSeconds - $elapsed);
    }

    public function recordAttempt(string $identifier, bool $success): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO login_attempts (identifier, success, ip_address, created_at)
                 VALUES (?, ?, ?, NOW())"
            )->execute([$identifier, $success ? 1 : 0, $_SERVER['REMOTE_ADDR'] ?? null]);

            // Kalau login berhasil, bersihkan histori gagal untuk identifier ini
            if ($success) {
                $this->pdo->prepare(
                    "DELETE FROM login_attempts WHERE identifier = ? AND success = 0"
                )->execute([$identifier]);
            }
        } catch (Exception $e) {
            error_log('[RateLimiter] gagal mencatat percobaan login: ' . $e->getMessage());
        }
    }

    private function countRecentFailures(string $identifier): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE identifier = ? AND success = 0
               AND created_at >= (NOW() - INTERVAL ? SECOND)"
        );
        $stmt->execute([$identifier, $this->windowSeconds]);
        return (int)$stmt->fetchColumn();
    }
}
