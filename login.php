<?php
// Start session
session_start();

// Include database connection and helpers
require_once 'config/db.php';

// Remember Me: auto-login if any role-specific cookie present and session not set
if (!isset($_SESSION['user_id'])) {
    foreach ($_COOKIE as $k => $v) {
        if (strpos($k, 'REMEMBER_BBP_') === 0) {
            $parts = explode('.', $v, 2);
            if (count($parts) === 2) {
                $payload = base64_decode($parts[0], true);
                $sig = $parts[1];
                if ($payload !== false) {
                    $expected = hash_hmac('sha256', $parts[0], bin2hex(getAppSecret()));
                    if (hash_equals($expected, $sig)) {
                        $data = json_decode($payload, true);
                        if (is_array($data) && isset($data['id'], $data['role'], $data['exp']) && time() < (int)$data['exp']) {
                            $_SESSION['user_id'] = (int)$data['id'];
                            $_SESSION['role'] = preg_replace('/[^a-z]/', '', (string)$data['role']);
                            $table = $_SESSION['role'] . '_users';
                            if ($_SESSION['role'] === 'redcross') { $table = 'redcross_users'; }
                            if ($_SESSION['role'] === 'barangay') { $table = 'barangay_users'; }
                            if ($_SESSION['role'] === 'negrosfirst') { $table = 'negrosfirst_users'; }
                            $user = getRow("SELECT name FROM $table WHERE id = ?", [$_SESSION['user_id']]);
                            $_SESSION['user_name'] = $user['name'] ?? 'User';
                            header("Location: dashboard/" . $_SESSION['role'] . "/index.php");
                            exit;
                        }
                    }
                }
            }
        }
    }
}

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Redirect to appropriate dashboard
    header("Location: dashboard/" . $_SESSION['role'] . "/index.php");
    exit;
}

// Set default role
$role = isset($_GET['role']) ? $_GET['role'] : 'donor';

// Handle OTP verification step
$step = $_GET['step'] ?? 'login';
$error = '';
$success = '';

// Include OTP functions
require_once __DIR__ . '/includes/functions.php';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'verify_otp') {
        // OTP verification for donor/patient
        $userId = (int)($_SESSION['temp_user_id'] ?? 0);
        $otp = sanitize($_POST['otp'] ?? '');
        $role = $_SESSION['temp_user_role'] ?? 'donor';
        
        if (empty($otp) || strlen($otp) !== 6) {
            $error = 'Please enter a valid 6-digit OTP code.';
        } else if ($userId <= 0) {
            $error = 'Invalid session. Please start over.';
            unset($_SESSION['temp_user_id'], $_SESSION['temp_user_email'], $_SESSION['temp_user_name'], $_SESSION['temp_user_role']);
        } else {
            if (verifyOTPForLogin($userId, $otp, $role, 'login')) {
                // Get user details
                $table = $role . '_users';
                $user = getRow("SELECT * FROM $table WHERE id = ?", [$userId]);
                
                if ($user) {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['role'] = $role;
                    
                    // Handle Remember Me if requested
                    $rememberMe = $_SESSION['temp_remember'] ?? false;
                    if ($rememberMe) {
                        $exp = time() + 60*60*24*30;
                        $payload = base64_encode(json_encode(['id' => (int)$user['id'], 'role' => $role, 'exp' => $exp]));
                        $sig = hash_hmac('sha256', $payload, bin2hex(getAppSecret()));
                        $cookie = $payload . '.' . $sig;
                        $cookieName = 'REMEMBER_BBP_' . strtoupper($role);
                        setcookie($cookieName, $cookie, [
                            'expires' => $exp,
                            'path' => '/',
                            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    }
                    
                    // Clean up temp session
                    unset($_SESSION['temp_user_id'], $_SESSION['temp_user_email'], $_SESSION['temp_user_name'], $_SESSION['temp_user_role'], $_SESSION['temp_remember']);
                    
                    // Log successful login
                    audit_log($user['id'], $role, 'login_success_otp', 'email=' . $user['email']);
                    
                    header("Location: dashboard/$role/index.php");
                    exit;
                } else {
                    $error = 'User not found. Please contact support.';
                }
            } else {
                $error = 'Invalid or expired OTP code. Please try again.';
                audit_log($userId, $role, 'otp_verification_failed', 'Invalid OTP entered');
            }
        }
    } else {
        // Login step - email and password
    // CSRF check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form token. Please refresh and try again.';
    } else {
        // Rate limit to mitigate brute force (max 10 attempts per 10 minutes)
        if (rate_limit_exceeded('login:' . $role, 10, 600)) {
            $error = 'Too many attempts. Please wait a few minutes and try again.';
            } else {
        // Validate and sanitize input
        $email = normalize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = normalize_input($_POST['role'] ?? 'donor');
                
        // Email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        }

        if (!$error) {
            // Determine table based on role
            $table = $role . '_users';

            // Get user from database
            $user = getRow("SELECT * FROM $table WHERE email = ?", [$email]);

            if ($user && password_verify($password, $user['password'])) {
                        // For donor/patient: require OTP verification
                if (in_array($role, ['donor','patient'], true)) {
                            // Generate and send OTP
                            $otp = generateOTPForLogin($user['id'], $user['email'], $role, 'login', 10);
                            
                            if ($otp && sendOTPEmailForLogin($user['email'], $user['name'], $otp, $role)) {
                                // Store temp session data for OTP verification
                                $_SESSION['temp_user_id'] = $user['id'];
                                $_SESSION['temp_user_email'] = $user['email'];
                                $_SESSION['temp_user_name'] = $user['name'];
                                $_SESSION['temp_user_role'] = $role;
                                $_SESSION['temp_remember'] = !empty($_POST['remember']);
                                
                                audit_log($user['id'], $role, 'otp_sent', 'OTP sent to email: ' . $user['email']);
                                
                                // Redirect to OTP verification
                                header("Location: login.php?role=" . urlencode($role) . "&step=verify_otp");
                    exit;
                            } else {
                                $error = 'Failed to send OTP email. Please try again or contact support.';
                                audit_log($user['id'], $role, 'otp_send_failed', 'Failed to send OTP to: ' . $user['email']);
                }
                        } else {
                // For barangay, redcross, negrosfirst: login directly
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $role;
                if (!empty($_POST['remember'])) {
                    $exp = time() + 60*60*24*30;
                    $payload = base64_encode(json_encode(['id' => (int)$user['id'], 'role' => $role, 'exp' => $exp]));
                    $sig = hash_hmac('sha256', $payload, bin2hex(getAppSecret()));
                    $cookie = $payload . '.' . $sig;
                    $cookieName = 'REMEMBER_BBP_' . strtoupper($role);
                    setcookie($cookieName, $cookie, [
                        'expires' => $exp,
                        'path' => '/',
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }
                            audit_log($user['id'], $role, 'login_success', 'email=' . $email);
                header("Location: dashboard/$role/index.php");
                exit;
                        }
            } else {
                $error = "Invalid email or password. Please try again.";
                audit_log(null, $role, 'login_failed', 'email=' . $email);
            }
                }
            }
        }
    }
}

// Resend OTP
if (isset($_GET['resend_otp']) && isset($_SESSION['temp_user_id'])) {
    $userId = (int)$_SESSION['temp_user_id'];
    $role = $_SESSION['temp_user_role'] ?? 'donor';
    $table = $role . '_users';
    $user = getRow("SELECT * FROM $table WHERE id = ?", [$userId]);
    
    if ($user) {
        $otp = generateOTPForLogin($userId, $user['email'], $role, 'login', 10);
        if ($otp && sendOTPEmailForLogin($user['email'], $user['name'], $otp, $role)) {
            $success = 'A new OTP has been sent to your email.';
            audit_log($userId, $role, 'otp_resent', 'OTP resent to: ' . $user['email']);
        } else {
            $error = 'Failed to resend OTP. Please try again.';
        }
    }
}

// Page title
$pageTitle = "Login - Blood Bank Portal";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 450px;
            margin: 100px auto;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .login-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .login-tabs .nav-link {
            border-radius: 0;
            padding: 15px 0;
            font-weight: 500;
        }
        .login-tabs .nav-link.active {
            background-color: transparent;
            border-bottom: 3px solid #dc3545;
            color: #dc3545;
        }
        .login-form {
            padding: 30px;
        }
        .form-control {
            padding: 12px;
            border-radius: 5px;
        }
        .btn-login {
            padding: 12px;
            font-weight: 500;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <i class="bi bi-droplet-fill"></i>
            </div>
            <h2>Blood Bank Portal</h2>
            <p class="text-muted"><?php echo ($step === 'verify_otp' && in_array($role, ['donor', 'patient'], true)) ? 'Verify Your Identity' : 'Sign in to your account'; ?></p>
        </div>

        <div class="card login-card">
            <div class="card-header bg-white p-0">
                <ul class="nav nav-tabs login-tabs nav-fill" id="loginTabs">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($role === 'donor') ? 'active' : ''; ?>" href="login.php?role=donor">
                            <i class="bi bi-droplet me-2"></i>Donor
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($role === 'patient') ? 'active' : ''; ?>" href="login.php?role=patient">
                            <i class="bi bi-person me-2"></i>Patient
                        </a>
                    </li>
                  
                </ul>
            </div>
            <div class="card-body login-form">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($step === 'verify_otp' && in_array($role, ['donor', 'patient'], true)): ?>
                    <!-- OTP Verification Form -->
                    <div class="alert alert-info">
                        <i class="bi bi-shield-check me-2"></i>
                        <strong>Security Check:</strong> We've sent a 6-digit OTP code to <strong><?php echo htmlspecialchars($_SESSION['temp_user_email'] ?? ''); ?></strong>
                    </div>
                    
                    <form method="POST" action="" autocomplete="off">
                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                        
                        <div class="mb-4">
                            <label for="otp" class="form-label text-center d-block">Enter OTP Code</label>
                            <input type="text" class="form-control text-center" id="otp" name="otp" 
                                   placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus
                                   style="font-size: 1.5rem; letter-spacing: 0.5rem; font-weight: bold; font-family: 'Courier New', monospace;">
                            <small class="text-muted d-block text-center mt-2">Check your email for the 6-digit code</small>
                        </div>
                        
                        <button type="submit" class="btn btn-danger w-100 btn-login">
                            <i class="bi bi-shield-check me-2"></i>Verify OTP
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="login.php?role=<?php echo urlencode($role); ?>&resend_otp=1" class="text-danger text-decoration-none">
                            <i class="bi bi-arrow-clockwise me-1"></i>Resend OTP
                        </a>
                    </div>
                    
                    <div class="text-center mt-2">
                        <a href="login.php?role=<?php echo urlencode($role); ?>" class="text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Regular Login Form -->
                <form method="POST" action="" autocomplete="off">
                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                    <!-- Autofill guard -->
                    <input type="text" name="_fake_user" autocomplete="off" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;border:0;padding:0;margin:0" tabindex="-1">

                    <?php if ($role === 'redcross' || $role === 'negrosfirst'): ?>
                        <fieldset class="mb-4">
                            <legend class="form-label">Select Organization</legend>
                            <div class="d-flex">
                                <div class="form-check me-4">
                                    <input class="form-check-input" type="radio" name="role" id="redcross" value="redcross" <?php echo ($role === 'redcross') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="redcross">
                                        Red Cross
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" id="negrosfirst" value="negrosfirst" <?php echo ($role === 'negrosfirst') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="negrosfirst">
                                        Negros First
                                    </label>
                                </div>
                            </div>
                        </fieldset>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" autocomplete="username" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" autocomplete="new-password" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        <a href="forgot-password.php" class="text-decoration-none">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </button>
                </form>

                <?php if ($role === 'donor' || $role === 'patient'): ?>
                    <div class="text-center mt-4">
                            <p class="mb-0">Don't have an account? <a href="register.php?role=<?php echo urlencode($role); ?>" class="text-decoration-none">Register now</a></p>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="login-footer text-muted">
            <p>&copy; <?php echo date('Y'); ?> Blood Bank Portal. All rights reserved.</p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-hide alerts to prevent duplication on refresh
    document.addEventListener('DOMContentLoaded', function() {
        var alerts = document.querySelectorAll('.alert');
        if (alerts.length) {
            setTimeout(function() {
                alerts.forEach(function(a){
                    a.classList.add('fade');
                    a.style.transition = 'opacity 0.5s';
                    a.style.opacity = '0';
                    setTimeout(function(){ a.remove(); }, 600);
                });
            }, 5000);
        }
        
        // Auto-format OTP input (only numbers, 6 digits max)
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                // Limit to 6 digits
                if (this.value.length > 6) {
                    this.value = this.value.substring(0, 6);
                }
                // Auto-submit when 6 digits are entered
                if (this.value.length === 6) {
                    this.form.submit();
                }
            });
            
            // Focus on OTP input when page loads
            otpInput.focus();
        }
    });
    </script>
</body>
</html>
