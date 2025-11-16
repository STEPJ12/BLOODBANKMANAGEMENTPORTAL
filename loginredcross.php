<?php
// Start session
session_start();

// Include security functions (includes db.php)
require_once 'includes/redcross_security.php';

// Clean up old security data periodically
if (rand(1, 100) === 1) {
    cleanupSecurityData();
}

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'redcross') {
    // Check session timeout (30 minutes)
    if (!checkSessionTimeout(30)) {
        session_destroy();
        header("Location: loginredcross.php?expired=1");
        exit;
    }
    header("Location: dashboard/redcross/index.php");
    exit;
}

// Handle OTP verification step
$step = $_GET['step'] ?? 'login';
// If we have temp session data, we're in OTP verification mode
if (isset($_SESSION['temp_user_id']) && $step !== 'verify_otp') {
    $step = 'verify_otp';
}
$error = '';
$success = '';

// Handle success/error messages from redirects
if (isset($_GET['success'])) {
    $success = htmlspecialchars(urldecode($_GET['success']));
}
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'resend_failed') {
        $error = 'Failed to resend OTP. Please try again.';
    } else {
        $error = htmlspecialchars(urldecode($_GET['error']));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'verify_otp') {
        // OTP verification
        $userId = (int)($_SESSION['temp_user_id'] ?? 0);
        $otp = sanitize($_POST['otp'] ?? '');
        
        if (empty($otp) || strlen($otp) !== 6) {
            $error = 'Please enter a valid 6-digit OTP code.';
        } else if ($userId <= 0) {
            $error = 'Invalid session. Please start over.';
            unset($_SESSION['temp_user_id'], $_SESSION['temp_user_email'], $_SESSION['temp_user_name']);
        } else {
            if (verifyOTP($userId, $otp, 'redcross', 'login')) {
                // OTP verified successfully
                $user = getRow("SELECT * FROM redcross_users WHERE id = ?", [$userId]);
                
                if ($user) {
                    // Clear failed login attempts
                    clearFailedLoginAttempts($userId, 'redcross');
                    
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['role'] = 'redcross';
                    $_SESSION['last_activity'] = time();
                    
                    // Track session activity
                    trackSessionActivity($user['id'], 'redcross');
                    
                    // Remember me cookie
                    if (!empty($_POST['rememberMe'])) {
                        $exp = time() + 60*60*24*30;
                        $payload = base64_encode(json_encode(['id' => (int)$user['id'], 'role' => 'redcross', 'exp' => $exp]));
                        $sig = hash_hmac('sha256', $payload, bin2hex(getAppSecret()));
                        $cookie = $payload . '.' . $sig;
                        $cookieName = 'REMEMBER_BBP_REDCROSS';
                        setcookie($cookieName, $cookie, [
                            'expires' => $exp,
                            'path' => '/',
                            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    }
                    
                    // Clean up temp session
                    unset($_SESSION['temp_user_id'], $_SESSION['temp_user_email'], $_SESSION['temp_user_name']);
                    
                    // Log successful login
                    audit_log($user['id'], 'redcross', 'login_success_otp', 'email=' . $user['email']);
                    
                    header("Location: dashboard/redcross/index.php");
                    exit;
                } else {
                    $error = 'User not found. Please contact support.';
                }
            } else {
                $error = 'Invalid or expired OTP code. Please try again.';
                audit_log($userId, 'redcross', 'otp_verification_failed', 'Invalid OTP entered');
            }
        }
    } else {
        // Login step - email and password
        // Rate limiting
        if (rate_limit_exceeded('login:redcross', 10, 600)) {
            $error = 'Too many login attempts. Please wait a few minutes and try again.';
            audit_log(null, 'redcross', 'rate_limit_exceeded', 'Too many login attempts from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        } else {
            $email = normalize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } else {
                $table = 'redcross_users';
                $user = getRow("SELECT * FROM $table WHERE email = ?", [$email]);
                
                if ($user) {
                    // Check if account is locked
                    if (isAccountLocked($user['id'], 'redcross')) {
                        $lockout = getRow(
                            "SELECT locked_until FROM account_lockouts WHERE user_id = ? AND user_role = ?",
                            [$user['id'], 'redcross']
                        );
                        $lockedUntil = $lockout['locked_until'] ?? null;
                        $error = 'Your account has been temporarily locked due to multiple failed login attempts. ';
                        if ($lockedUntil) {
                            $timeRemaining = strtotime($lockedUntil) - time();
                            if ($timeRemaining > 0) {
                                $minutes = ceil($timeRemaining / 60);
                                $error .= "Please try again in {$minutes} minute(s).";
                            }
                        }
                        audit_log($user['id'], 'redcross', 'login_blocked_locked', 'email=' . $email);
                    } else if (password_verify($password, $user['password'])) {
                        // Password correct - generate and send OTP
                        $otp = generateOTP($user['id'], $user['email'], 'redcross', 'login', 10);
                        
                        if ($otp && sendOTPEmail($user['email'], $user['name'], $otp)) {
                            // Store temp session data for OTP verification
                            $_SESSION['temp_user_id'] = $user['id'];
                            $_SESSION['temp_user_email'] = $user['email'];
                            $_SESSION['temp_user_name'] = $user['name'];
                            
                            audit_log($user['id'], 'redcross', 'otp_sent', 'OTP sent to email: ' . $user['email']);
                            
                            // Redirect to OTP verification
                            header("Location: loginredcross.php?step=verify_otp");
                            exit;
                        } else {
                            $error = 'Failed to send OTP email. Please try again or contact support.';
                            audit_log($user['id'], 'redcross', 'otp_send_failed', 'Failed to send OTP to: ' . $user['email']);
                        }
                    } else {
                        // Invalid password - record failed attempt
                        recordFailedLogin($user['id'], 'redcross', 5, 15);
                        $error = "Invalid email or password. Please try again.";
                        audit_log($user['id'], 'redcross', 'login_failed', 'email=' . $email . ', reason=invalid_password');
                    }
                } else {
                    // User not found - don't reveal this
                    $error = "Invalid email or password. Please try again.";
                    audit_log(null, 'redcross', 'login_failed', 'email=' . $email . ', reason=user_not_found');
                }
            }
        }
    }
}

// Resend OTP
if (isset($_GET['resend_otp']) && isset($_SESSION['temp_user_id'])) {
    $userId = (int)$_SESSION['temp_user_id'];
    $user = getRow("SELECT * FROM redcross_users WHERE id = ?", [$userId]);
    
    if ($user) {
        $otp = generateOTP($userId, $user['email'], 'redcross', 'login', 10);
        if ($otp && sendOTPEmail($user['email'], $user['name'], $otp)) {
            audit_log($userId, 'redcross', 'otp_resent', 'OTP resent to: ' . $user['email']);
            // Redirect back to OTP verification page with success message
            header("Location: loginredcross.php?step=verify_otp&success=" . urlencode('A new OTP has been sent to your email.'));
            exit;
        } else {
            // Still redirect to stay on OTP page with error
            header("Location: loginredcross.php?step=verify_otp&error=resend_failed");
            exit;
        }
    } else {
        // User not found, redirect to login
        header("Location: loginredcross.php");
        exit;
    }
}

// Page title
$pageTitle = "Red Cross Login - Blood Bank Portal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('imgs/headerrc.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            padding: 2.5rem 2.5rem 2rem 2.5rem;
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .login-logo {
            width: 120px;
            margin: 0 auto 1.75rem;
            display: block;
            transition: transform 0.3s ease;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }
        .login-logo:hover {
            transform: scale(1.05);
        }
        .login-title {
            font-weight: 700;
            color: #b31217;
            margin-bottom: 0.75rem;
            font-size: 1.5rem;
            letter-spacing: -0.3px;
            text-align: center;
            line-height: 1.4;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        .welcome-text {
            text-align: center;
            margin-bottom: 2rem;
            color: #666;
            font-size: 0.95rem;
        }
        .form-control {
            padding: 0.85rem 1.25rem;
            border-radius: 0.75rem;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            width: 100%;
            background-color: rgba(255, 255, 255, 0.9);
        }
        .form-control:focus {
            border-color: #b31217;
            box-shadow: 0 0 0 0.2rem rgba(229,45,39,.15);
            transform: translateY(-1px);
            background-color: #fff;
        }
        .form-control::placeholder {
            color: #adb5bd;
        }
        .form-label {
            font-weight: 600;
            color: #444;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
            display: block;
        }
        .btn-danger {
            background: linear-gradient(90deg, #e52d27 0%, #b31217 100%);
            border: none;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 0.85rem 1.5rem;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 0.95rem;
            width: 100%;
            display: block;
            margin-top: 1rem;
            box-shadow: 0 4px 15px rgba(179, 18, 23, 0.2);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(179, 18, 23, 0.3);
        }
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem 0;
        }
        .form-check-label {
            color: #666;
            font-size: 0.9rem;
        }
        .login-footer {
            position: fixed;
            bottom: 1rem;
            left: 0;
            right: 0;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        .otp-input-group {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin: 1.5rem 0;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            border: 2px solid #e0e0e0;
            border-radius: 0.75rem;
        }
        .otp-input:focus {
            border-color: #b31217;
            box-shadow: 0 0 0 0.2rem rgba(229,45,39,.15);
        }
        .security-info {
            background-color: #f8f9fa;
            border-left: 4px solid #b31217;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        @media (max-width: 576px) {
            .login-card {
                padding: 2rem 1.5rem 1.5rem 1.5rem;
                margin: 1rem;
                width: calc(100% - 2rem);
            }
            .login-title {
                font-size: 1.35rem;
            }
            .login-logo {
                width: 100px;
            }
            .login-footer {
                position: relative;
                margin-top: 2rem;
            }
            .form-control {
                padding: 0.75rem 1rem;
            }
            .otp-input {
                width: 45px;
                height: 55px;
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center">
            <img src="assets/img/rclgo.png" alt="Red Cross Logo" class="login-logo">
            <div class="login-title">Philippine Red Cross - Bacolod Chapter</div>
            <p class="welcome-text text-muted"><?php echo $step === 'verify_otp' ? 'Verify Your Identity' : 'Login to access your account'; ?></p>
        </div>
        
        <?php if (isset($_GET['expired'])): ?>
            <div class="alert alert-warning">Your session has expired due to inactivity. Please log in again.</div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger mt-2" id="login-error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success mt-2" id="login-success-msg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($step === 'verify_otp'): ?>
            <!-- OTP Verification Form -->
            <form method="POST" action="">
                <div class="security-info">
                    <i class="bi bi-shield-check me-2"></i>
                    <strong>Security Check:</strong> We've sent a 6-digit OTP code to <strong><?php echo htmlspecialchars($_SESSION['temp_user_email'] ?? ''); ?></strong>
                </div>
                
                <div class="mb-3">
                    <label for="otp" class="form-label text-center d-block">Enter OTP Code</label>
                    <input type="text" class="form-control text-center" id="otp" name="otp" 
                           placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus
                           style="font-size: 1.5rem; letter-spacing: 0.5rem; font-weight: bold;">
                    <small class="text-muted d-block text-center mt-2">Check your email for the 6-digit code</small>
                </div>
                
                <input type="hidden" name="rememberMe" value="<?php echo htmlspecialchars($_POST['rememberMe'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                
                <button type="submit" class="btn btn-danger w-100">Verify OTP</button>
                
                <div class="text-center mt-3">
                    <a href="loginredcross.php?resend_otp=1" class="text-danger text-decoration-none">
                        <i class="bi bi-arrow-clockwise me-1"></i>Resend OTP
                    </a>
                </div>
                
                <div class="text-center mt-2">
                    <a href="loginredcross.php" class="text-muted text-decoration-none small">
                        <i class="bi bi-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </form>
        <?php else: ?>
            <!-- Login Form -->
            <form method="POST" action="" id="loginForm" autocomplete="off">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="Enter your email" required autofocus 
                           pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                           autocomplete="off">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required autocomplete="off">
                </div>
                <div class="form-options">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe" value="1">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-danger w-100">Login</button>
            </form>
        <?php endif; ?>
    </div>
    
    <div class="login-footer">
        &copy; <?php echo date('Y'); ?> Philippine Red Cross Blood Bank Portal
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-hide feedback messages
    setTimeout(function() {
        var errorMsg = document.getElementById('login-error-msg');
        if (errorMsg) {
            errorMsg.style.display = 'none';
        }
        var successMsg = document.getElementById('login-success-msg');
        if (successMsg) {
            successMsg.style.display = 'none';
        }
    }, 5000);
    
    // OTP input formatting
    var otpInput = document.getElementById('otp');
    if (otpInput) {
        otpInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    }
    </script>
</body>
</html>