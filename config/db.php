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

// Email configuration from .env (for security: never hardcode credentials)
define('MAIL_HOST',      $_ENV['MAIL_HOST']      ?? 'smtp.gmail.com');
define('MAIL_PORT',      $_ENV['MAIL_PORT']      ?? 587);
define('MAIL_USERNAME',  $_ENV['MAIL_USERNAME']  ?? '');
define('MAIL_PASSWORD',  $_ENV['MAIL_PASSWORD']  ?? '');
define('MAIL_FROM',      $_ENV['MAIL_FROM']      ?? '');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'SAPAcara – Manajemen Acara Sekolah');
define('APP_NAME',       'SAPAcara');

try {
    $dbDriver = $_ENV['DB_DRIVER'] ?? 'mysql';
    if ($dbDriver === 'sqlite') {
        $dbFile = $_ENV['DB_FILE'] ?? __DIR__ . '/../data/demo.sqlite';
        $pdo = new PDO("sqlite:" . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } else {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
} catch (PDOException $e) {
    die(json_encode(['error' => 'Koneksi database gagal: ' . $e->getMessage()]));
}
