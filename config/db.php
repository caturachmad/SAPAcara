<?php
// ── Load .env ────────────────────────────────────────────────────────────────
// Kredensial TIDAK boleh di-hardcode. Buat file .env dari .env.example.

(function () {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) {
        die(json_encode(['error' => 'File .env tidak ditemukan. Salin .env.example ke .env dan isi kredensialnya.']));
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val, " \t\"'");
    }
})();

define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
define('DB_USER',    $_ENV['DB_USER']    ?? '');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_NAME',    $_ENV['DB_NAME']    ?? '');
define('BASE_URL',   $_ENV['BASE_URL']   ?? '');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Koneksi database gagal: ' . $e->getMessage()]));
}
