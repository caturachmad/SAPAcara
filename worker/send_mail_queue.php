<?php
// CLI worker to process queued mail entries
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

if (php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from CLI.\n";
    exit(1);
}

$limit = $argv[1] ?? 20;
$limit = (int)$limit;
if ($limit <= 0) $limit = 20;

try {
    // Fetch queued items with SKIP LOCKED to allow parallel workers
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM mail_queue WHERE status='queued' ORDER BY created_at LIMIT ? FOR UPDATE");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Mark as processing
    $ids = array_column($items, 'id');
    if (empty($ids)) {
        $pdo->commit();
        echo "No queued emails.\n";
        exit(0);
    }
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $upd = $pdo->prepare("UPDATE mail_queue SET status='processing', updated_at=NOW() WHERE id IN ($in)");
    $upd->execute($ids);
    $pdo->commit();

    foreach ($items as $it) {
        try {
            // Attempt to send
            $sent = sendMail($it['to_email'], $it['to_name'] ?? '', $it['subject'], $it['body_html']);
            if ($sent) {
                $u = $pdo->prepare("UPDATE mail_queue SET status='sent', attempts=attempts+1, last_error=NULL, updated_at=NOW() WHERE id=?");
                $u->execute([$it['id']]);
                echo "Sent: {$it['to_email']} (id={$it['id']})\n";
            } else {
                $err = $GLOBALS['last_mail_error'] ?? 'Unknown error';
                $u = $pdo->prepare("UPDATE mail_queue SET status='failed', attempts=attempts+1, last_error=?, updated_at=NOW() WHERE id=?");
                $u->execute([$err, $it['id']]);
                echo "Failed: {$it['to_email']} (id={$it['id']}) - {$err}\n";
            }
        } catch (Exception $e) {
            $pdo->prepare("UPDATE mail_queue SET status='failed', attempts=attempts+1, last_error=?, updated_at=NOW() WHERE id=?")->execute([$e->getMessage(), $it['id']]);
            echo "Exception sending id={$it['id']}: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Worker error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
