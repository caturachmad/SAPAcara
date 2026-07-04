<?php
// Temporary mysqli-based mail queue worker to run without PDO
// Usage: provide DB and MAIL env vars or rely on .env values

// Load .env into $env
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
// Allow overriding via environment variables
$get = fn($k, $default='') => getenv($k) !== false ? getenv($k) : ($env[$k] ?? $default);
$driver = $get('DB_DRIVER', 'mysql');
$host   = $get('DB_HOST', '127.0.0.1');
$port   = $get('DB_PORT', '3306');
$user   = $get('DB_USER', 'root');
$pass   = $get('DB_PASS', '');
$dbname = $get('DB_NAME', 'siakad_db');

$limit = $argv[1] ?? 20;
$limit = (int)$limit; if ($limit<=0) $limit = 20;

// Mail settings
$mailHost = $get('MAIL_HOST', $env['MAIL_HOST'] ?? '');
$mailPort = (int)$get('MAIL_PORT', $env['MAIL_PORT'] ?? 587);
$mailUser = $get('MAIL_USERNAME', $env['MAIL_USERNAME'] ?? '');
$mailPass = $get('MAIL_PASSWORD', $env['MAIL_PASSWORD'] ?? '');
$mailFrom = $get('MAIL_FROM', $env['MAIL_FROM'] ?? '');
$mailFromName = $get('MAIL_FROM_NAME', $env['MAIL_FROM_NAME'] ?? 'SAPAcara');

if ($driver !== 'mysql') {
    echo "Worker currently supports MySQL via mysqli only. Use DB_DRIVER=mysql or override env vars.\n";
    exit(2);
}

$mysqli = new mysqli($host, $user, $pass, $dbname, (int)$port);
if ($mysqli->connect_errno) {
    echo "mysqli connect failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n";
    exit(1);
}
$mysqli->set_charset('utf8mb4');

// Fetch queued items and mark as processing inside transaction
$ids = [];
$mysqli->begin_transaction();
$res = $mysqli->query("SELECT id FROM mail_queue WHERE status='queued' ORDER BY created_at LIMIT " . intval($limit) . " FOR UPDATE");
if ($res) {
    while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
    $res->free();
}
if (empty($ids)) {
    $mysqli->commit();
    echo "No queued emails.\n";
    exit(0);
}
$in = implode(',', $ids);
$upd = $mysqli->query("UPDATE mail_queue SET status='processing', updated_at=NOW() WHERE id IN ($in)");
$mysqli->commit();

require_once __DIR__ . '/../vendor/autoload.php';

foreach ($ids as $id) {
    $rowRes = $mysqli->query("SELECT * FROM mail_queue WHERE id=" . intval($id));
    if (!$rowRes) continue;
    $row = $rowRes->fetch_assoc();
    $rowRes->free();
    if (!$row) continue;

    $to = $row['to_email'];
    $toName = $row['to_name'] ?? '';
    $subject = $row['subject'];
    $body = $row['body_html'];

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        if ($mailHost) {
            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = !empty($mailUser) || !empty($mailPass);
            if (!empty($mailUser)) $mail->Username = $mailUser;
            if (!empty($mailPass)) $mail->Password = $mailPass;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mailPort ?: 587;
        }
        $mail->CharSet = 'UTF-8';
        if ($mailFrom) $mail->setFrom($mailFrom, $mailFromName);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        $stmt = $mysqli->prepare("UPDATE mail_queue SET status='sent', attempts = attempts+1, last_error=NULL, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        echo "Sent id=$id to $to\n";
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        $stmt = $mysqli->prepare("UPDATE mail_queue SET status='failed', attempts = attempts+1, last_error=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('si', $err, $id);
        $stmt->execute();
        $stmt->close();
        echo "Failed id=$id to $to - $err\n";
    }
}

$mysqli->close();
echo "Worker finished.\n";
exit(0);
