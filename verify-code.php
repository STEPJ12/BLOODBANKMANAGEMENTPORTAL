<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/api_keys.php';

// Validate and sanitize role
$role = isset($_GET['role']) && in_array($_GET['role'], ['donor','patient','redcross','barangay','negrosfirst','admin'], true) ? sanitize($_GET['role']) : 'donor';
// Sanitize email from GET parameter - validate format before accepting
$emailRaw = isset($_GET['email']) ? $_GET['email'] : '';
$email = '';
if (!empty($emailRaw)) {
    $emailCleaned = filter_var(trim($emailRaw), FILTER_SANITIZE_EMAIL);
    // Only accept if valid email format to prevent malicious content
    if (filter_var($emailCleaned, FILTER_VALIDATE_EMAIL)) {
        $email = $emailCleaned;
    }
}
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (rate_limit_exceeded('verify_code:' . $role, 20, 600)) {
        $message = 'Too many attempts. Please wait a few minutes and try again.';
    }
    // Validate and sanitize role
    $postRole = isset($_POST['role']) && in_array($_POST['role'], ['donor','patient','redcross','barangay','negrosfirst','admin'], true) ? sanitize($_POST['role']) : $role;
    $role = $postRole;
    // Sanitize and validate email - prevent malicious content propagation
    $postEmailRaw = isset($_POST['email']) ? $_POST['email'] : '';
    $postEmail = '';
    if (!empty($postEmailRaw)) {
        // Sanitize email input to prevent injection
        $postEmailCleaned = filter_var(trim($postEmailRaw), FILTER_SANITIZE_EMAIL);
        // Validate email format - only accept if valid
        if (filter_var($postEmailCleaned, FILTER_VALIDATE_EMAIL)) {
            $postEmail = $postEmailCleaned;
        }
    }
    // Only update email if we got a valid one from POST
    if (!empty($postEmail)) {
        $email = $postEmail;
    }
    
    // Sanitize verification code - only allow numeric, limit length to prevent malicious content
    $codeRaw = isset($_POST['code']) ? $_POST['code'] : '';
    $code = '';
    if (!empty($codeRaw)) {
        // Remove all non-numeric characters and limit to 6 digits
        $codeCleaned = substr(preg_replace('/[^0-9]/', '', $codeRaw), 0, 6);
        // Pad to 6 digits with leading zeros to match database format
        $code = str_pad($codeCleaned, 6, '0', STR_PAD_LEFT);
    }

    // Additional validation - ensure email and code are valid
    if (empty($email) || empty($code) || strlen($code) < 6) {
        $message = 'Email and a valid 6-digit code are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } elseif (strlen($code) !== 6) {
        $message = 'Verification code must be exactly 6 digits.';
    } else {
        // Map roles to their corresponding table names
        $tableMap = [
            'donor' => 'donor_users',
            'patient' => 'patient_users', 
            'redcross' => 'redcross_users',
            'barangay' => 'barangay_users',
            'negrosfirst' => 'negrosfirst_users',
            'admin' => 'admin_users'
        ];
        $table = $tableMap[$role] ?? 'donor_users';
        $user = getRow("SELECT id FROM $table WHERE email = ?", [$email]);
        if (!$user) {
            $message = 'Account not found for the provided email.';
        } else {
            // Debug: Check what codes exist for this user (sanitized logging)
            $debugCodes = executeQuery("SELECT id, code, expires_at, consumed_at, created_at FROM email_verifications WHERE user_id = ? AND role = ? ORDER BY id DESC", [$user['id'], $role]);
            // Use secure_log instead of error_log for user-controlled data
            if (function_exists('secure_log')) {
                secure_log("Email verification attempt", [
                    'user_id' => (int)$user['id'],
                    'role' => $role,
                    'code_length' => strlen($code)
                ]);
            }
            
            // First try to find a non-consumed, non-expired code
            $row = getRow(
                "SELECT id, expires_at, consumed_at FROM email_verifications WHERE user_id = ? AND role = ? AND code = ? AND (consumed_at IS NULL OR consumed_at = '') AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
                [ $user['id'], $role, $code ]
            );
            // If no valid code found, check any matching code (for better error messages)
            if (!$row) {
                $row = getRow(
                    "SELECT id, expires_at, consumed_at FROM email_verifications WHERE user_id = ? AND role = ? AND code = ? ORDER BY id DESC LIMIT 1",
                    [ $user['id'], $role, $code ]
                );
            }
            if (!$row) {
                $message = 'Invalid verification code. Please check the code and try again.';
            } elseif (!empty($row['consumed_at'])) {
                $message = 'This code has already been used.';
            } elseif (strtotime($row['expires_at']) <= time()) {
                $message = 'This code has expired. Please request a new verification email.';
            } else {
                updateRow("UPDATE email_verifications SET consumed_at = NOW() WHERE id = ?", [$row['id']]);
                // Auto-login and set Remember Me cookie if requested earlier
                $userRow = getRow("SELECT id, name FROM $table WHERE email = ?", [$email]);
                if ($userRow) {
                    $_SESSION['user_id'] = $userRow['id'];
                    $_SESSION['user_name'] = $userRow['name'] ?? 'User';
                    $_SESSION['role'] = $role;
                    // If remember=1 query param present, set cookie now
                    if (isset($_GET['remember']) && $_GET['remember'] === '1') {
                        $exp = time() + 60*60*24*30;
                        $payload = base64_encode(json_encode(['id' => (int)$userRow['id'], 'role' => $role, 'exp' => $exp]));
                        require_once __DIR__ . '/config/db.php';
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
                    // Validate role before redirect to prevent path traversal
                    $validRoles = ['donor', 'patient', 'redcross', 'barangay', 'negrosfirst', 'admin'];
                    if (!in_array($role, $validRoles, true)) {
                        $role = 'donor'; // Default fallback
                    }
                    header("Location: dashboard/" . $role . "/index.php");
                    exit;
                }
                $success = true;
                $message = 'Your email has been verified successfully. You may now log in.';
            }
        }
    }
}

// Resend handler via GET action - additional security checks
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['resend']) && $_GET['resend'] === '1') {
    require_once __DIR__ . '/includes/functions.php';
    
    // Re-validate and sanitize email from GET parameter to prevent malicious content propagation
    $resendEmailRaw = isset($_GET['email']) ? $_GET['email'] : '';
    $resendEmail = '';
    if (!empty($resendEmailRaw)) {
        $resendEmailCleaned = filter_var(trim($resendEmailRaw), FILTER_SANITIZE_EMAIL);
        // Only accept if valid email format
        if (filter_var($resendEmailCleaned, FILTER_VALIDATE_EMAIL)) {
            $resendEmail = $resendEmailCleaned;
        }
    }
    
    // Re-validate role from GET parameter
    $resendRole = isset($_GET['role']) && in_array($_GET['role'], ['donor','patient','redcross','barangay','negrosfirst','admin'], true) 
        ? sanitize($_GET['role']) 
        : $role;
    
    // Only proceed if we have valid email and role
    if (!empty($resendEmail) && in_array($resendRole, ['donor','patient','redcross','barangay','negrosfirst','admin'], true)) {
        // Call function with validated and sanitized inputs
        if (generateAndSendVerificationCode($resendEmail, $resendRole)) {
            $message = 'A new verification code has been sent.';
            $email = $resendEmail; // Update email for display
            $role = $resendRole; // Update role for display
        } else {
            $message = 'Failed to resend the verification code. Please check your email address.';
        }
    } else {
        $message = 'Invalid email address or role specified.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Blood Bank Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verification-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .verification-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .verification-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .verification-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .verification-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .verification-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .verification-body {
            padding: 2rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .verification-code-input {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5rem;
            font-family: 'Courier New', monospace;
        }

        .btn-verify {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-verify::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-verify:hover::before {
            left: 100%;
        }

        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(220, 53, 69, 0.3);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn-resend {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            color: #6c757d;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-resend:hover {
            background: #e9ecef;
            border-color: #dee2e6;
            color: #495057;
            transform: translateY(-1px);
        }

        .btn-back {
            background: transparent;
            border: 2px solid #dc3545;
            color: #dc3545;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-back:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        .success-container {
            text-align: center;
            padding: 2rem;
        }

        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
            animation: bounce 0.6s ease-out;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .success-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #28a745;
            margin-bottom: 1rem;
        }

        .success-message {
            color: #6c757d;
            margin-bottom: 2rem;
        }

        .role-badge {
            display: inline-block;
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        /* Mobile Responsive */
        @media (max-width: 576px) {
            .verification-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .verification-header {
                padding: 1.5rem;
            }
            
            .verification-body {
                padding: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .verification-code-input {
                font-size: 1.25rem;
                letter-spacing: 0.25rem;
            }
        }

        /* Loading animation */
        .btn-verify.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-verify.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-header">
            <div class="verification-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <h1 class="verification-title">Email Verification</h1>
            <p class="verification-subtitle">Enter the verification code sent to your email</p>
        </div>

        <div class="verification-body">
            <?php if ($message): ?>
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                    <i class="bi <?php echo $success ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <div class="role-badge">
                    <i class="bi bi-person-badge me-1"></i>
                    <?php echo htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8'); ?> Account
                </div>

                <form method="POST" id="verificationForm">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                    
                    <div class="form-floating">
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               placeholder="Email address" required>
                        <label for="email">
                            <i class="bi bi-envelope me-2"></i>Email Address
                        </label>
                    </div>

                    <div class="form-floating">
                        <input type="text" class="form-control verification-code-input" id="code" name="code" 
                               placeholder="Verification Code" minlength="6" maxlength="6" 
                               pattern="[0-9]{6}" required autocomplete="off">
                        <label for="code">
                            <i class="bi bi-key me-2"></i>Verification Code
                        </label>
                    </div>

                    <button type="submit" class="btn btn-verify w-100" id="verifyBtn">
                        <i class="bi bi-shield-check me-2"></i>Verify Email
                    </button>
                </form>

                <div class="action-buttons">
                    <a href="verify-code.php?role=<?php echo htmlspecialchars($role); ?>&email=<?php echo htmlspecialchars($email); ?>&resend=1<?php echo (isset($_GET['remember']) && $_GET['remember']==='1') ? '&remember=1' : ''; ?>" 
                       class="btn btn-resend">
                        <i class="bi bi-arrow-clockwise me-2"></i>Resend Code
                    </a>
                    <a href="login.php?role=<?php echo htmlspecialchars($role); ?>" class="btn btn-back">
                        <i class="bi bi-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-container">
                    <div class="success-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h2 class="success-title">Verification Successful!</h2>
                    <p class="success-message">Your email has been verified. You can now access your account.</p>
                    <a href="login.php?role=<?php echo htmlspecialchars($role); ?>" class="btn btn-verify">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-format verification code input
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                document.getElementById('verificationForm').submit();
            }
        });

        // Loading state for form submission
        document.getElementById('verificationForm').addEventListener('submit', function() {
            const btn = document.getElementById('verifyBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<span>Verifying...</span>';
        });

        // Auto-focus on code input if email is pre-filled
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const codeInput = document.getElementById('code');
            
            if (emailInput.value) {
                codeInput.focus();
            }
        });
    </script>
</body>
</html>

