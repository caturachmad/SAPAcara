<?php
/**
 * security.php — Security utilities & middleware
 * 
 * Includes:
 * - Request validation
 * - Input sanitization
 * - Security headers
 * - Rate limiting helpers
 */

declare(strict_types=1);

// ────────────────────────────────────────────────────────────────────────────
// Security Headers
// ────────────────────────────────────────────────────────────────────────────

function setSecurityHeaders(): void {
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Security Policy
    $csp = "default-src 'self'; ";
    $csp .= "script-src 'self' 'unsafe-inline'; ";
    $csp .= "style-src 'self' 'unsafe-inline' https://fonts.Anthropicapis.com; ";
    $csp .= "img-src 'self' data: https:; ";
    $csp .= "font-src 'self' https://fonts.gstatic.com; ";
    $csp .= "connect-src 'self'; ";
    $csp .= "frame-ancestors 'self'";
    header("Content-Security-Policy: $csp");
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Feature policy / Permissions policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// ────────────────────────────────────────────────────────────────────────────
// Password Validation
// ────────────────────────────────────────────────────────────────────────────

function validatePasswordStrength(string $password): array {
    $errors = [];
    
    // Minimum length
    $minLength = (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8);
    if (strlen($password) < $minLength) {
        $errors[] = "Password minimal {$minLength} karakter.";
    }
    
    // Uppercase
    if (!empty($_ENV['PASSWORD_REQUIRE_UPPERCASE']) && !preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password harus mengandung huruf besar (A-Z).';
    }
    
    // Lowercase
    if (!empty($_ENV['PASSWORD_REQUIRE_LOWERCASE']) && !preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password harus mengandung huruf kecil (a-z).';
    }
    
    // Numbers
    if (!empty($_ENV['PASSWORD_REQUIRE_NUMBERS']) && !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password harus mengandung angka (0-9).';
    }
    
    // Special characters
    if (!empty($_ENV['PASSWORD_REQUIRE_SPECIAL']) && !preg_match('/[!@#$%^&*()_+=\-\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
        $errors[] = 'Password harus mengandung karakter spesial.';
    }
    
    return $errors;
}

// ────────────────────────────────────────────────────────────────────────────
// Input Sanitization
// ────────────────────────────────────────────────────────────────────────────

function sanitizeString(string $input, int $maxLength = 255): string {
    // Trim whitespace
    $input = trim($input);
    
    // Remove null bytes
    $input = str_replace("\0", '', $input);
    
    // Truncate if too long
    if (strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }
    
    return $input;
}

function sanitizeEmail(string $email): string {
    $email = trim(strtolower($email));
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return $email;
}

function sanitizePhoneNumber(string $phone): string {
    // Remove all non-digit characters except leading +
    $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';
    return $phone;
}

function sanitizeUrl(string $url): string {
    $url = trim($url);
    // Only allow http:// and https://
    if (!preg_match('/^https?:\/\//i', $url)) {
        return '';
    }
    return $url;
}

// ────────────────────────────────────────────────────────────────────────────
// Input Validation
// ────────────────────────────────────────────────────────────────────────────

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhoneNumber(string $phone): bool {
    // Indonesian phone format: 08xxxxxxxxx or +628xxxxxxxxx
    return (bool)preg_match('/^(?:\+62|0)[0-9]{9,12}$/', $phone);
}

function validateUrl(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function validateDates(string $startDate, string $endDate): bool {
    try {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        return $end >= $start;
    } catch (Exception) {
        return false;
    }
}

// ────────────────────────────────────────────────────────────────────────────
// SQL Injection Prevention - Prepared Statements
// (Already using PDO prepared statements, this is for reference)
// ────────────────────────────────────────────────────────────────────────────

/**
 * Safe query builder using PDO named placeholders
 * 
 * Example:
 *   $result = safePrepare($pdo, 
 *       "SELECT * FROM users WHERE email = :email AND status = :status",
 *       [':email' => $email, ':status' => 'active']
 *   )->fetch();
 */
function safePrepare(PDO $pdo, string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC) {
    try {
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        
        $stmt->execute();
        $stmt->setFetchMode($fetchMode);
        
        return $stmt;
    } catch (PDOException $e) {
        error_log('[Database Error] ' . $e->getMessage());
        throw new RuntimeException('Database query failed');
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Rate Limiting Helpers
// ────────────────────────────────────────────────────────────────────────────

function getClientIp(): string {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // Validate IP format
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    
    return '0.0.0.0';
}

// ────────────────────────────────────────────────────────────────────────────
// XSS Prevention
// ────────────────────────────────────────────────────────────────────────────

function escapeHtml(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function escapeJs(string $text): string {
    return json_encode($text);
}

// ────────────────────────────────────────────────────────────────────────────
// File Upload Validation
// ────────────────────────────────────────────────────────────────────────────

function validateFileUpload(
    array $file,
    array $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'],
    int $maxSizeMB = 20
): array {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'Tidak ada file yang di-upload.';
        return $errors;
    }
    
    // Check file size
    $maxBytes = $maxSizeMB * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        $errors[] = "Ukuran file terlalu besar (max {$maxSizeMB}MB).";
    }
    
    // Get file extension
    $fileName = $file['name'] ?? '';
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (empty($ext)) {
        $errors[] = 'File tidak memiliki extension.';
        return $errors;
    }
    
    // Check extension
    if (!in_array($ext, $allowedExtensions, true)) {
        $errors[] = "Extension file tidak diizinkan. Diperbolehkan: " . implode(', ', $allowedExtensions);
    }
    
    // Check MIME type
    $mimeType = mime_content_type($file['tmp_name']);
    $allowedMimes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];
    
    if (isset($allowedMimes[$ext]) && $mimeType !== $allowedMimes[$ext]) {
        $errors[] = "File MIME type tidak sesuai dengan extension.";
    }
    
    return $errors;
}

// ────────────────────────────────────────────────────────────────────────────
// CORS Helper (untuk API endpoints)
// ────────────────────────────────────────────────────────────────────────────

function setupCors(array $allowedOrigins = ['localhost']): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN');
        header('Access-Control-Max-Age: 86400');
    }
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Initialize security on page load
// ────────────────────────────────────────────────────────────────────────────

setSecurityHeaders();