<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Attempt auto-login via Remember Me cookie
    // Try role-specific remember-me cookies
    $possibleCookies = array_filter($_COOKIE, function($k){ return strpos($k, 'REMEMBER_BBP_') === 0; }, ARRAY_FILTER_USE_KEY);
    $cookie = null;
    if (!empty($possibleCookies)) {
        // Prefer cookie that matches requested area if available
        $cookie = reset($possibleCookies);
    }
    if ($cookie) {
        $parts = explode('.', $cookie, 2);
        if (count($parts) === 2) {
            $payloadB64 = $parts[0];
            $sig = $parts[1];
            $payload = base64_decode($payloadB64, true);
            if ($payload !== false) {
                $expected = hash_hmac('sha256', $payloadB64, bin2hex(getAppSecret()));
                if (hash_equals($expected, $sig)) {
                    $data = json_decode($payload, true);
                    if (is_array($data) && isset($data['id'], $data['role'], $data['exp']) && time() < (int)$data['exp']) {
                        $_SESSION['user_id'] = (int)$data['id'];
                        $_SESSION['role'] = preg_replace('/[^a-z]/', '', (string)$data['role']);
                    }
                }
            }
        }
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: /Blood-Bank/login.php');
        exit();
    }
}

// Function to check if user has required role
function checkRole($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header('Location: /Blood-Bank/login.php');
        exit();
    }
}

// Function to check if user is active
function checkUserStatus() {
    // Only check user status if we have a valid database connection
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $user_id = $_SESSION['user_id'];
        
        $query = "SELECT status FROM users WHERE id = ?";
        $stmt = mysqli_prepare($GLOBALS['conn'], $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                if ($row['status'] !== 'active') {
                    session_destroy();
                    header('Location: /Blood-Bank/login.php?error=inactive');
                    exit();
                }
            } else {
                session_destroy();
                header('Location: /Blood-Bank/login.php?error=invalid');
                exit();
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}

// Check user status
checkUserStatus();
?> 