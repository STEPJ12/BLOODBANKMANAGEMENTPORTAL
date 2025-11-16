<?php
// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear Remember Me cookies for all roles
foreach ($_COOKIE as $k => $v) {
    if (strpos($k, 'REMEMBER_BBP_') === 0) {
        setcookie($k, '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
        unset($_COOKIE[$k]);
    }
}

// Destroy the session
session_destroy();

// Check if there's a redirect parameter
if (isset($_GET['redirect'])) {
    $redirect = $_GET['redirect'];
    // Only allow specific redirects for security
    $allowed_redirects = ['redcrossportal.php', 'negrosfirstportal.php', 'barangay-portal.php'];
    
    if (in_array($redirect, $allowed_redirects)) {
        header("Location: " . $redirect);
        exit();
    }
}

// Default redirect to login page if no specific redirect or not allowed
header("Location: login.php");
exit();
