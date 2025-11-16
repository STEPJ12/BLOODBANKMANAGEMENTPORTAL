<?php
// Global security hardening

// Strengthen session cookies (set before session_start elsewhere)
@ini_set('session.cookie_httponly', '1');
@ini_set('session.cookie_samesite', 'Lax');
@ini_set('session.use_strict_mode', '1');
// If HTTPS is detected, mark cookies secure
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
    @ini_set('session.cookie_secure', '1');
}

// Send standard security headers once per request
if (!headers_sent()) {
    // Prevent MIME sniffing
    header('X-Content-Type-Options: nosniff');
    // Clickjacking protection
    header('X-Frame-Options: SAMEORIGIN');
    // Basic XSS protection via modern browsers
    header('X-XSS-Protection: 0'); // modern guidance; rely on CSP
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // HSTS (only if HTTPS)
    if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    // Content Security Policy (allow self and selected CDNs)
    $csp = [
        "default-src 'self'",
        "img-src 'self' data: https://*",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com data:",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "connect-src 'self'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
    // Prevent caching of authenticated pages
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

?>

