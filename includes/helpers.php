<?php
/**
 * helpers.php — Utility functions
 */

declare(strict_types=1);

// ────────────────────────────────────────────────────────────────────────────
// Flash Messages
// ────────────────────────────────────────────────────────────────────────────

function setFlash(string $message, string $type = 'success'): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['flash_' . uniqid()] = [
        'message' => $message,
        'type' => $type,
        'timestamp' => time()
    ];
}

function getFlashes(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $flashes = [];
    
    foreach ($_SESSION as $key => $value) {
        if (strpos($key, 'flash_') === 0) {
            $flashes[] = $value;
            unset($_SESSION[$key]);
        }
    }
    
    return $flashes;
}

// ────────────────────────────────────────────────────────────────────────────
// Date/Time Formatting
// ────────────────────────────────────────────────────────────────────────────

function formatDate(string $date, string $format = 'd M Y'): string {
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception) {
        return 'Invalid Date';
    }
}

function formatDateTime(string $dateTime, string $format = 'd M Y H:i'): string {
    try {
        $dt = new DateTime($dateTime);
        return $dt->format($format);
    } catch (Exception) {
        return 'Invalid DateTime';
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Pagination
// ────────────────────────────────────────────────────────────────────────────

class Pagination {
    private int $total;
    private int $perPage;
    private int $currentPage;
    
    public function __construct(int $total, int $perPage = 10, int $currentPage = 1) {
        $this->total = $total;
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, min($currentPage, $this->getTotalPages()));
    }
    
    public function getOffset(): int {
        return ($this->currentPage - 1) * $this->perPage;
    }
    
    public function getTotalPages(): int {
        return (int)ceil($this->total / $this->perPage);
    }
    
    public function getCurrentPage(): int {
        return $this->currentPage;
    }
    
    public function getPageRange(): array {
        $pages = [];
        $totalPages = $this->getTotalPages();
        $window = 5;
        
        $start = max(1, $this->currentPage - floor($window / 2));
        $end = min($totalPages, $start + $window - 1);
        $start = max(1, $end - $window + 1);
        
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }
        
        return $pages;
    }
    
    public function hasNextPage(): bool {
        return $this->currentPage < $this->getTotalPages();
    }
    
    public function hasPreviousPage(): bool {
        return $this->currentPage > 1;
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Number Formatting
// ────────────────────────────────────────────────────────────────────────────

function formatCurrency(float $amount, string $currency = 'IDR'): string {
    if ($currency === 'IDR') {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
    return number_format($amount, 2, '.', ',');
}

function formatFileSize(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    
    for ($i = 0; $i < count($units) && $bytes >= 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

// ────────────────────────────────────────────────────────────────────────────
// Array Utilities
// ────────────────────────────────────────────────────────────────────────────

function arrayGroupBy(array $array, string $key): array {
    $result = [];
    
    foreach ($array as $item) {
        $groupKey = $item[$key] ?? 'ungrouped';
        if (!isset($result[$groupKey])) {
            $result[$groupKey] = [];
        }
        $result[$groupKey][] = $item;
    }
    
    return $result;
}

function arrayColumn2d(array $array, string $key): array {
    return array_reduce($array, function ($carry, $item) use ($key) {
        $carry[$item[$key] ?? uniqid()] = $item;
        return $carry;
    }, []);
}

// ────────────────────────────────────────────────────────────────────────────
// Redirect with safety check
// ────────────────────────────────────────────────────────────────────────────

function redirect(string $url, int $statusCode = 302): never {
    // Ensure URL is safe (internal redirect)
    if (!preg_match('#^(?:/?[a-zA-Z0-9\-._~:/?#\[\]@!$&\'()*+,;=%]+)?$#', $url)) {
        $url = BASE_URL;
    }
    
    header('Location: ' . $url, true, $statusCode);
    exit;
}

// ────────────────────────────────────────────────────────────────────────────
// Request method check
// ────────────────────────────────────────────────────────────────────────────

function isPost(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function isGet(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

function isPut(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'PUT';
}

function isDelete(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'DELETE';
}

function isAjax(): bool {
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

// ────────────────────────────────────────────────────────────────────────────
// JSON Response Helper
// ────────────────────────────────────────────────────────────────────────────

function jsonResponse(array $data, int $statusCode = 200): never {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonSuccess(array $data = [], string $message = 'Success'): never {
    jsonResponse([
        'status' => 'success',
        'message' => $message,
        'data' => $data
    ]);
}

function jsonError(string $message, int $statusCode = 400, array $errors = []): never {
    jsonResponse([
        'status' => 'error',
        'message' => $message,
        'errors' => $errors
    ], $statusCode);
}