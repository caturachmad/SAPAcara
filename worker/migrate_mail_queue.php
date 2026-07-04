<?php
// Parse .env to get DB connection info (do not rely on config/db.php which may require unavailable PDO drivers)
$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k,$v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
}

$driver = $env['DB_DRIVER'] ?? 'mysql';
try {
    if ($driver === 'sqlite') {
        $dbFile = $env['DB_FILE'] ?? (__DIR__ . '/../data/demo.sqlite');
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "CREATE TABLE IF NOT EXISTS mail_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            to_email TEXT NOT NULL,
            to_name TEXT,
            subject TEXT NOT NULL,
            body_html TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'queued',
            attempts INTEGER NOT NULL DEFAULT 0,
            last_error TEXT,
            created_at DATETIME NOT NULL DEFAULT (datetime('now')),
            updated_at DATETIME
        );";
        $pdo->exec($sql);
        echo "Created mail_queue table in SQLite DB file ($dbFile).\n";
    } else {
        $host = $env['DB_HOST'] ?? 'localhost';
        $name = $env['DB_NAME'] ?? 'siakad_db';
        $user = $env['DB_USER'] ?? 'root';
        $pass = $env['DB_PASS'] ?? '';
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $sql = "CREATE TABLE IF NOT EXISTS mail_queue (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          to_email VARCHAR(255) NOT NULL,
          to_name VARCHAR(255) DEFAULT NULL,
          subject VARCHAR(255) NOT NULL,
          body_html LONGTEXT NOT NULL,
          status ENUM('queued','processing','sent','failed') NOT NULL DEFAULT 'queued',
          attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
          last_error TEXT DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          INDEX (status),
          INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($sql);
        echo "Created mail_queue table in MySQL DB ($host/$name).\n";
    }
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
