<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// SECURITY: Sanitize and validate all inputs
$roleInput = isset($_GET['role']) ? sanitize($_GET['role']) : 'donor';
$emailInput = isset($_GET['email']) ? sanitize($_GET['email']) : '';
$tokenInput = isset($_GET['token']) ? sanitize($_GET['token']) : '';

// SECURITY: Whitelist role values to prevent SQL injection via table name
$allowedRoles = ['donor', 'patient', 'barangay'];
$role = in_array(strtolower($roleInput), $allowedRoles, true) ? strtolower($roleInput) : 'donor';

// SECURITY: Map roles to table names using switch - prevents any string interpolation
// Table names cannot be parameterized in SQL, so whitelist validation is the secure approach
switch ($role) {
    case 'donor':
        $table = 'donor_users';
        break;
    case 'patient':
        $table = 'patient_users';
        break;
    case 'barangay':
        $table = 'barangay_users';
        break;
    default:
        $table = 'donor_users';
        $role = 'donor'; // Reset to default if invalid
        break;
}

// SECURITY: Validate and sanitize email
$email = filter_var(trim($emailInput), FILTER_SANITIZE_EMAIL);
$email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';

// SECURITY: Sanitize token - alphanumeric and basic URL-safe characters only
$token = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($tokenInput));

$message = '';
$success = false;

if ($email && $token && $role) {
    // SECURITY: Use parameterized query - $table is validated via switch statement above
    // Table names cannot be parameterized in SQL, but $table is guaranteed safe via whitelist validation
    $user = getRow("SELECT id, email_verified, TRIM(email) as trimmed_email FROM `{$table}` WHERE TRIM(email) = ?", [trim($email)]);
    
    // SECURITY: Use secure_log instead of error_log to prevent log injection
    if (function_exists('secure_log')) {
        secure_log("Email verification attempt", [
            'email' => substr($email, 0, 100),
            'role' => $role,
            'table' => $table,
            'has_user' => $user ? 'yes' : 'no'
        ]);
    }
    
    if ($user && !$user['email_verified']) {
        // SECURITY: Validate role again before using in query
        $roleParam = in_array($role, $allowedRoles, true) ? $role : 'donor';
        $ver = getRow("SELECT id, expires_at, consumed_at FROM email_verifications WHERE user_id = ? AND role = ? AND token = ? ORDER BY id DESC LIMIT 1", [
            $user['id'],
            $roleParam,
            $token
        ]);
        
        if ($ver && !$ver['consumed_at'] && strtotime($ver['expires_at']) > time()) {
            // SECURITY: Use parameterized query - $table is validated via switch statement above
            executeQuery("UPDATE `{$table}` SET email_verified = 1 WHERE id = ?", [$user['id']]);
            executeQuery("UPDATE email_verifications SET consumed_at = NOW() WHERE id = ?", [$ver['id']]);
            $success = true;
            $message = 'Your email has been successfully verified. You may now log in.';
        } elseif ($ver && $ver['consumed_at']) {
            $message = 'This verification link has already been used.';
        } else {
            $message = 'This verification link is invalid or has expired.';
        }
    } elseif ($user && $user['email_verified']) {
        $success = true;
        $message = 'Your email is already verified. You may log in.';
    } else {
        $message = 'Account not found.';
    }
} else {
    $message = 'Invalid verification link.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style> body{background:#f8f9fa} .container{max-width:600px;margin:80px auto} </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h3 class="mb-3">Email Verification</h3>
                <?php if ($message): ?>
                    <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <a href="login.php?role=<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-danger">Go to Login</a>
                <?php endif; ?>
        </div>
    </div>
</body>
</html>

