<?php
// Start session
session_start();
require_once 'config/db.php';

$token = isset($_GET['token']) ? sanitize($_GET['token']) : '';
$error = $success = '';
$password = $confirm_password = '';
$password_err = $confirm_password_err = '';

// Step 1: Validate token
if (!$token) {
    $error = 'Invalid or missing token.';
} else {
    $reset = getRow("SELECT * FROM password_resets WHERE token = ?", [$token]);
    if (!$reset || strtotime($reset['expires_at']) < time()) {
        $error = 'This reset link is invalid or has expired.';
    }
}

// Step 2: Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $reset) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    // Password validation (same as registration)
    if (empty($password)) {
        $password_err = 'Please enter a password.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=[\]{};\':\"\\|,.<>\/?]).{8,}$/', $password)) {
        $password_err = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
    }
    if (empty($confirm_password)) {
        $confirm_password_err = 'Please confirm password.';
    } elseif ($password !== $confirm_password) {
        $confirm_password_err = 'Passwords do not match.';
    }
    if (empty($password_err) && empty($confirm_password_err)) {
        // Find user in the appropriate table based on role
        $email = $reset['email'];
        $userRole = $reset['role'] ?? 'donor';
        $updated = false;
        
        // If role is not available, try to find user in all tables
        if (empty($userRole) || $userRole === 'donor') {
            // Whitelist mapping for table names to prevent SQL injection
            $userTables = [
                'donor_users' => 'donor',
                'patient_users' => 'patient', 
                'redcross_users' => 'redcross',
                'barangay_users' => 'barangay',
                'negrosfirst_users' => 'negrosfirst',
                'admin_users' => 'admin'
            ];
            
            foreach ($userTables as $table => $role) {
                // Table name is from whitelist, safe to use
                $user = getRow("SELECT * FROM $table WHERE email = ?", [$email]);
                if ($user) {
                    $userRole = $role;
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    // Table name is from whitelist, safe to use
                    executeQuery("UPDATE $table SET password = ? WHERE email = ?", [$hashed, $email]);
                    $updated = true;
                    break;
                }
            }
        } else {
            // Whitelist mapping for table names to prevent SQL injection
            $tableMap = [
                'donor' => 'donor_users',
                'patient' => 'patient_users', 
                'redcross' => 'redcross_users',
                'barangay' => 'barangay_users',
                'negrosfirst' => 'negrosfirst_users',
                'admin' => 'admin_users'
            ];
            
            // Validate userRole is in whitelist before using in table map
            if (!in_array($userRole, array_keys($tableMap), true)) {
                $error = 'Invalid user role.';
            } else {
                $table = $tableMap[$userRole];
                $user = getRow("SELECT * FROM $table WHERE email = ?", [$email]);
                
                if ($user) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    executeQuery("UPDATE $table SET password = ? WHERE email = ?", [$hashed, $email]);
                    $updated = true;
                }
            }
        }
        
        // Invalidate the token
        executeQuery("DELETE FROM password_resets WHERE token = ?", [$token]);
        
        if ($updated) {
            // Auto-login user after successful password reset
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'] ?? 'User';
            $_SESSION['role'] = $userRole;
            
            // Redirect to appropriate dashboard - validate role before redirect
            $validRoles = ['donor', 'patient', 'redcross', 'barangay', 'negrosfirst', 'admin'];
            if (!in_array($userRole, $validRoles, true)) {
                $userRole = 'donor'; // Default fallback
            }
            $dashboardUrl = "dashboard/" . $userRole . "/index.php";
            header("Location: " . $dashboardUrl);
            exit;
        } else {
            $error = 'User not found.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Blood Bank Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .reset-container { max-width: 400px; margin: 100px auto; }
        .reset-header { text-align: center; margin-bottom: 30px; }
        .reset-logo { font-size: 48px; color: #dc3545; margin-bottom: 20px; }
        .reset-card { border: none; border-radius: 10px; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); }
        .reset-form { padding: 30px; }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <div class="reset-logo"><i class="bi bi-droplet-fill"></i></div>
            <h2>Reset Password</h2>
            <p class="text-muted">Enter your new password</p>
        </div>
        <div class="card reset-card">
            <div class="card-body reset-form">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Enter new password" required>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($password_err, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <small class="text-muted">Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.</small>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($confirm_password_err, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Reset Password</button>
                </form>
                <?php endif; ?>
                <div class="text-center mt-3">
                    <a href="login.php" class="text-decoration-none">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 