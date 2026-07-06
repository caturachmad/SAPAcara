<?php
/**
 * db.php — Database Connection Configuration
 * 
 * PRODUCTION CHECKLIST:
 * ✓ .env file exists dan berisi kredensial
 * ✓ File .env TIDAK di-commit ke repository
 * ✓ Database user memiliki least privilege
 * ✓ Backup database dijadwalkan regular
 */

declare(strict_types=1);

// ────────────────────────────────────────────────────────────────────────────
// Load environment variables from .env file
// ────────────────────────────────────────────────────────────────────────────

(function () {
    $envFile = __DIR__ . '/../.env';
    
    if (!file_exists($envFile)) {
        error_log('[CRITICAL] File .env tidak ditemukan pada ' . $envFile);
        http_response_code(500);
        die(json_encode([
            'error' => 'Configuration Error',
            'message' => 'Database configuration file not found. Please contact administrator.'
        ], JSON_UNESCAPED_SLASHES));
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        error_log('[CRITICAL] Cannot read .env file: ' . $envFile);
        http_response_code(500);
        die(json_encode([
            'error' => 'Configuration Error',
            'message' => 'Failed to read configuration. Please contact administrator.'
        ]));
    }
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines dan comments
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        
        // Parse line dengan format KEY=VALUE
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        
        // Remove quotes if present
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        
        // Validate key format (alphanumeric + underscore only)
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
            error_log('[WARNING] Invalid env key format: ' . $key);
            continue;
        }
        
        $_ENV[$key] = $value;
    }
})();

// ────────────────────────────────────────────────────────────────────────────
// Define constants from .env with validation
// ────────────────────────────────────────────────────────────────────────────

$dbHost = $_ENV['DB_HOST'] ?? '';
$dbUser = $_ENV['DB_USER'] ?? '';
$dbPass = $_ENV['DB_PASS'] ?? '';
$dbName = $_ENV['DB_NAME'] ?? '';
$baseUrl = $_ENV['BASE_URL'] ?? '';

// Validate required variables
$missing = array_filter([
    'DB_HOST' => empty($dbHost),
    'DB_USER' => empty($dbUser),
    'DB_NAME' => empty($dbName),
    'BASE_URL' => empty($baseUrl),
]);

if (!empty($missing)) {
    error_log('[CRITICAL] Missing required environment variables: ' . implode(', ', array_keys($missing)));
    http_response_code(500);
    die(json_encode([
        'error' => 'Configuration Error',
        'message' => 'Required environment variables are missing. Please contact administrator.'
    ]));
}

define('DB_HOST', $dbHost);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_NAME', $dbName);
define('BASE_URL', $baseUrl);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Validate BASE_URL format
if (!filter_var(BASE_URL, FILTER_VALIDATE_URL)) {
    error_log('[WARNING] BASE_URL is not valid URL format: ' . BASE_URL);
}

// ────────────────────────────────────────────────────────────────────────────
// PDO Connection dengan error handling dan timeout
// ────────────────────────────────────────────────────────────────────────────

try {
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=utf8mb4;port=3306",
        DB_HOST,
        DB_NAME
    );
    
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            // Error handling
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            
            // Fetch mode
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            
            // SECURITY: Disable prepared statement emulation
            PDO::ATTR_EMULATE_PREPARES => false,
            
            // Connection timeout (seconds)
            PDO::ATTR_TIMEOUT => 5,
            
            // Case folding
            PDO::ATTR_CASE => PDO::CASE_LOWER,
            
            // Connection charset
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            
            // Persistent connection untuk long-running processes
            PDO::ATTR_PERSISTENT => false,
        ]
    );
    
    // Test connection
    $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    
} catch (PDOException $e) {
    // SECURITY: Log full error but show generic message to user
    $errorId = uniqid('db_error_');
    error_log(sprintf(
        '[DB_CONNECTION_ERROR] %s | Host: %s | Database: %s | Error: %s',
        $errorId,
        DB_HOST,
        DB_NAME,
        $e->getMessage()
    ));
    
    http_response_code(500);
    die(json_encode([
        'error' => 'Database Connection Failed',
        'errorId' => $errorId,
        'message' => 'Unable to connect to database. Please contact administrator with error ID: ' . $errorId
    ], JSON_UNESCAPED_SLASHES));
}

// ────────────────────────────────────────────────────────────────────────────
// Register shutdown function untuk graceful connection close
// ────────────────────────────────────────────────────────────────────────────

register_shutdown_function(function () use ($pdo) {
    if ($pdo !== null) {
        $pdo = null; // Close connection
    }
});