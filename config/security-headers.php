<?php
/**
 * security-headers.php
 * 
 * Essential HTTP security headers for production deployment.
 * Include this file early in the HTTP response generation (before header.php).
 * 
 * Should be called once per request:
 *   require_once __DIR__ . '/security-headers.php';
 */

// Prevent MIME type sniffing (XSS protection)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
}

// Prevent clickjacking (iframe attacks)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
}

// Enable XSS filter in older browsers
if (!headers_sent()) {
    header('X-XSS-Protection: 1; mode=block');
}

// Referrer policy
if (!headers_sent()) {
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Permissions Policy (formerly Feature-Policy) — restrict dangerous APIs
if (!headers_sent()) {
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

// Content Security Policy (CSP) — restrict resource loading
// IMPORTANT: Adjust based on your needs; this is a basic policy
if (!headers_sent() && (defined('ENVIRONMENT') && ENVIRONMENT !== 'development')) {
    header("Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
        . "img-src 'self' data: https:; "
        . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self'; "
    );
}
