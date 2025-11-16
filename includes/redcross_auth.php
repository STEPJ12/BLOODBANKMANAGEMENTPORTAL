<?php
/**
 * Red Cross Authentication & Security Middleware
 * Include this file at the top of every Red Cross dashboard page
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/redcross_security.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'redcross') {
    header("Location: ../../loginredcross.php");
    exit;
}

// Check session timeout (30 minutes for active users)
if (!checkSessionTimeout(30)) {
    session_destroy();
    audit_log($_SESSION['user_id'] ?? null, 'redcross', 'session_timeout', 'Session expired due to inactivity');
    header("Location: ../../loginredcross.php?expired=1");
    exit;
}

// Track session activity
trackSessionActivity($_SESSION['user_id'], 'redcross');

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Log page access (optional - only for sensitive pages)
$currentPage = basename($_SERVER['PHP_SELF']);
$sensitivePages = ['donor-registration.php', 'update-inventory.php', 'maintenance.php'];
if (in_array($currentPage, $sensitivePages)) {
    audit_log(
        $_SESSION['user_id'],
        'redcross',
        'page_access',
        'Accessed: ' . $currentPage . ' from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
    );
}

