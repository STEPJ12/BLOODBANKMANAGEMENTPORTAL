<?php
// Common functions for the application

/**
 * Sanitize user input
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('has_role')) {
    function has_role($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }
}

/**
 * Check if user has specific role
 */
if (!function_exists('hasRole')) {
    function hasRole($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }
}

/**
 * Redirect to a URL
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

/**
 * Get blood type label from value
 */
function getBloodTypeLabel($value) {
    $types = [
        'a_pos' => 'A+',
        'a_neg' => 'A-',
        'b_pos' => 'B+',
        'b_neg' => 'B-',
        'ab_pos' => 'AB+',
        'ab_neg' => 'AB-',
        'o_pos' => 'O+',
        'o_neg' => 'O-',
        'unknown' => 'Unknown'
    ];

    return isset($types[$value]) ? $types[$value] : 'Unknown';
}

/**
 * Format date for display
 */
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'high':
        case 'completed':
        case 'fulfilled':
        case 'approved':
            return 'bg-green-100 text-green-800';
        case 'medium':
        case 'scheduled':
            return 'bg-blue-100 text-blue-800';
        case 'low':
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'critical':
        case 'rejected':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

/**
 * Get organization name from value
 */
function getOrganizationName($value) {
    $orgs = [
        'red_cross' => 'Red Cross',
        'negros_first' => 'Negros First',
        'any' => 'Any Available'
    ];

    return isset($orgs[$value]) ? $orgs[$value] : 'Unknown';
}

/**
 * Get urgency level label
 */
function getUrgencyLabel($value) {
    $levels = [
        'critical' => 'Critical (Immediate)',
        'urgent' => 'Urgent (24 hours)',
        'normal' => 'Normal (2-3 days)',
        'scheduled' => 'Scheduled Procedure'
    ];

    return isset($levels[$value]) ? $levels[$value] : 'Normal';
}

/**
 * Get role label from value
 */
function getRoleLabel($value) {
    $roles = [
        'patient' => 'Patient',
        'donor' => 'Donor',
        'bloodbank' => 'Blood Bank Staff',
        'barangay' => 'Barangay Staff',
        'admin' => 'Admin'
    ];

    return isset($roles[$value]) ? $roles[$value] : 'Unknown';
}

/**
 * Send verification email using PHPMailer
 * 
 * SECURITY NOTE: This function sanitizes and validates all input parameters internally.
 * Inputs are sanitized immediately upon function entry to prevent malicious content propagation.
 * 
 * @param string $toEmail Email address (will be sanitized and validated)
 * @param string $toName Recipient name (will be sanitized)
 * @param string $token Verification token
 * @param string $role User role (will be validated against whitelist)
 * @param string|null $code Verification code (optional)
 * @return bool True on success, false on failure
 */
function sendVerificationEmail(string $toEmail, string $toName, string $token, string $role, ?string $code = null): bool {
    require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';
    require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
    require_once __DIR__ . '/../config/api_keys.php';

    // Sanitize email and role before using in URL to prevent malicious content propagation
    $toEmailSanitized = filter_var($toEmail, FILTER_SANITIZE_EMAIL);
    $toEmailValid = filter_var($toEmailSanitized, FILTER_VALIDATE_EMAIL);
    if ($toEmailValid === false) {
        return false; // Invalid email, cannot send
    }
    
    // Validate role
    $validRoles = ['donor', 'patient', 'redcross', 'barangay', 'negrosfirst', 'admin'];
    if (!in_array($role, $validRoles, true)) {
        return false; // Invalid role
    }
    
    // Use sanitized and validated values
    $toEmail = $toEmailValid;
    $roleSanitized = sanitize($role);
    
    // Sanitize toName to prevent malicious content propagation in email
    $toNameSanitized = sanitize($toName ?? '');
    // Use sanitized name or fallback to validated email
    $toNameSafe = !empty(trim($toNameSanitized)) ? trim($toNameSanitized) : $toEmail;

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $baseUrl = rtrim($baseUrl, '/\\');
    $codeUrl = $baseUrl . '/verify-code.php?role=' . urlencode($roleSanitized) . '&email=' . urlencode($toEmail);

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $smtpUser = getenv('SMTP_USER') ?: (defined('SMTP_USER') ? SMTP_USER : '');
        if ($smtpUser !== '') {
            $mail->isSMTP();
            $mail->Host = getenv('SMTP_HOST') ?: (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = getenv('SMTP_PASS') ?: (defined('SMTP_PASS') ? SMTP_PASS : '');
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)(getenv('SMTP_PORT') ?: (defined('SMTP_PORT') ? SMTP_PORT : 587));
            if (defined('SMTP_VERIFY_PEER') && SMTP_VERIFY_PEER === false) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ]
                ];
            }
            if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
                $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            }
        } else {
            // Fallback to PHP mail() via PHPMailer
            $mail->isMail();
        }

        $from = getenv('MAIL_FROM') ?: ((defined('MAIL_FROM') && MAIL_FROM !== '') ? MAIL_FROM : ($smtpUser ?: 'no-reply@example.com'));
        $fromName = getenv('MAIL_FROM_NAME') ?: 'Blood Bank Portal';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($toEmail, $toNameSafe);
        $mail->isHTML(true);
        $mail->Subject = 'Verify your email address';
        // Use sanitized name for email body (already sanitized above as $toNameSafe)
        $mail->Body = '<p>Hello ' . htmlspecialchars($toNameSafe) . ',</p>' .
                      '<p>Please verify your account using the verification code below:</p>' .
                      ($code ? '<p style="font-size:18px"><strong>Verification Code: ' . htmlspecialchars($code) . '</strong></p>' : '') .
                      '<p>You can enter this code on the following page:</p>' .
                      '<p><a href="' . htmlspecialchars($codeUrl) . '">Enter Verification Code</a></p>' .
                      '<p>This code will expire in 10 minutes.</p>' .
                      '<p>Thank you,<br>Blood Bank Portal</p>';
        $mail->AltBody = 'Please verify your account using this code: ' . ($code ?: '') . "\n" .
                         'Enter it here: ' . $codeUrl . "\n" .
                         "This code will expire in 10 minutes.";

        $mail->send();
        return true;
    } catch (Throwable $e) {
        // Use secure_log if available, otherwise use error_log without sensitive data
        if (function_exists('secure_log')) {
            secure_log("Verification email send error", [
                'error' => substr($e->getMessage(), 0, 200)
            ]);
        } else {
            error_log('Mail error: ' . substr($e->getMessage(), 0, 200));
        }
        // Attempt to log code details for debugging when in dev (using sanitized data only)
        try {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
            $logPath = $logDir . '/verification.log';
            // Only log validated email (already sanitized) and error message
            // Do not log code or other sensitive data
            @file_put_contents($logPath, date('c') . ' to=' . substr($toEmail, 0, 50) . ' role=' . $roleSanitized . ' error=' . substr($e->getMessage(), 0, 200) . PHP_EOL, FILE_APPEND);
        } catch (Throwable $e2) { /* ignore */ }
        return false;
    }
}

/**
 * Generate a new verification token+code and send email
 */
function generateAndSendVerificationCode(string $email, string $role): bool {
    // CRITICAL: Sanitize email FIRST before any string operations to prevent malicious content propagation
    // Step 1: Sanitize email input immediately to remove malicious content
    $emailSanitized = filter_var($email, FILTER_SANITIZE_EMAIL);
    // Step 2: Trim whitespace from sanitized email
    $emailTrimmed = trim($emailSanitized);
    // Step 3: Validate email format - only proceed if valid
    $emailValid = filter_var($emailTrimmed, FILTER_VALIDATE_EMAIL);
    if ($emailValid === false) {
        return false;
    }
    // Step 4: Assign validated email (safe to use now)
    $email = $emailValid;
    
    // Validate and sanitize role to prevent malicious content propagation
    $validRoles = ['donor', 'patient', 'redcross', 'barangay', 'negrosfirst', 'admin'];
    if (!in_array($role, $validRoles, true)) {
        return false;
    }
    // Sanitize role (even though whitelisted, ensure no malicious content)
    $role = sanitize($role);
    
    // Map roles to their corresponding table names (using validated role)
    $tableMap = [
        'donor' => 'donor_users',
        'patient' => 'patient_users', 
        'redcross' => 'redcross_users',
        'barangay' => 'barangay_users',
        'negrosfirst' => 'negrosfirst_users',
        'admin' => 'admin_users'
    ];
    
    $table = $tableMap[$role] ?? 'donor_users';
    $user = getRow("SELECT id, name FROM $table WHERE email = ?", [$email]);
    if (!$user) {
        return false;
    }
    
    // Clean up any existing verification codes for this user and role
    executeQuery("DELETE FROM email_verifications WHERE user_id = ? AND role = ?", [$user['id'], $role]);
    
    // Generate a unique code (retry if duplicate)
    $code = '';
    $attempts = 0;
    do {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $attempts++;
        
        // Check if code already exists
        $existing = getRow("SELECT id FROM email_verifications WHERE code = ?", [$code]);
        if (!$existing || $attempts > 10) {
            break;
        }
    } while ($existing && $attempts <= 10);
    
    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
    
    // Log the code generation for debugging (sanitized)
    if (function_exists('secure_log')) {
        secure_log("Generating verification code", [
            'user_id' => (int)$user['id'],
            'role' => $role
        ]);
    }
    
    $ok = insertRow(
        "INSERT INTO email_verifications (user_id, role, token, code, expires_at) VALUES (?, ?, ?, ?, ?)",
        [ (int)$user['id'], $role, $token, $code, $expiresAt ]
    );
    
    if ($ok === false) {
        return false;
    }
    
    // Sanitize user name before passing to email function to prevent malicious content propagation
    $userNameRaw = $user['name'] ?? $email;
    $userNameSanitized = sanitize($userNameRaw);
    // Ensure name is not empty (fallback to email if sanitization results in empty)
    $userName = !empty(trim($userNameSanitized)) ? trim($userNameSanitized) : $email;
    
    return sendVerificationEmail($email, $userName, $token, $role, $code);
}

/**
 * Generate OTP for donor/patient login
 */
function generateOTPForLogin(int $userId, string $email, string $role = 'donor', string $purpose = 'login', int $expiryMinutes = 10): ?string {
    // Generate 6-digit OTP
    $otp = sprintf('%06d', random_int(100000, 999999));
    
    // Delete any existing unused OTPs for this user
    executeQuery(
        "DELETE FROM otp_codes WHERE user_id = ? AND user_role = ? AND purpose = ? AND used_at IS NULL",
        [$userId, $role, $purpose]
    );
    
    // Store new OTP
    $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    
    insertRow(
        "INSERT INTO otp_codes (user_id, user_role, email, otp_code, purpose, expires_at, ip) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$userId, $role, $email, $otp, $purpose, $expiresAt, $ip]
    );
    
    return $otp;
}

/**
 * Verify OTP for donor/patient login
 */
function verifyOTPForLogin(int $userId, string $otp, string $role = 'donor', string $purpose = 'login'): bool {
    $otpRecord = getRow(
        "SELECT * FROM otp_codes WHERE user_id = ? AND user_role = ? AND otp_code = ? AND purpose = ? AND used_at IS NULL",
        [$userId, $role, $otp, $purpose]
    );
    
    if (!$otpRecord) {
        return false;
    }
    
    // Check expiry
    if (strtotime($otpRecord['expires_at']) < time()) {
        return false;
    }
    
    // Mark as used
    updateRow(
        "UPDATE otp_codes SET used_at = NOW() WHERE id = ?",
        [$otpRecord['id']]
    );
    
    return true;
}

/**
 * Send OTP email for donor/patient login
 */
function sendOTPEmailForLogin(string $email, string $name, string $otp, string $role = 'donor'): bool {
    require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';
    require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
    require_once __DIR__ . '/../config/api_keys.php';
    
    // Sanitize and validate email FIRST to prevent malicious content propagation
    $emailSanitized = filter_var($email, FILTER_SANITIZE_EMAIL);
    $emailValid = filter_var($emailSanitized, FILTER_VALIDATE_EMAIL);
    if ($emailValid === false) {
        return false; // Invalid email, cannot send
    }
    $email = $emailValid;
    
    // Validate and sanitize role
    $validRoles = ['donor', 'patient', 'redcross', 'barangay', 'negrosfirst', 'admin'];
    if (!in_array($role, $validRoles, true)) {
        return false; // Invalid role
    }
    $role = sanitize($role);
    
    // Sanitize name to prevent XSS in email content
    $name = sanitize($name);
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $smtpUser = getenv('SMTP_USER') ?: (defined('SMTP_USER') ? SMTP_USER : '');
        
        if ($smtpUser !== '') {
            $mail->isSMTP();
            $mail->Host = getenv('SMTP_HOST') ?: (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = getenv('SMTP_PASS') ?: (defined('SMTP_PASS') ? SMTP_PASS : '');
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)(getenv('SMTP_PORT') ?: (defined('SMTP_PORT') ? SMTP_PORT : 587));
            
            if (defined('SMTP_VERIFY_PEER') && SMTP_VERIFY_PEER === false) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ]
                ];
            }
        } else {
            $mail->isMail();
        }
        
        $from = getenv('MAIL_FROM') ?: ((defined('MAIL_FROM') && MAIL_FROM !== '') ? MAIL_FROM : ($smtpUser ?: 'no-reply@example.com'));
        $fromName = getenv('MAIL_FROM_NAME') ?: 'Blood Bank Portal';
        $mail->setFrom($from, $fromName);
        
        // Use sanitized name for email address to prevent malicious content propagation
        // $name was already sanitized earlier in the function
        $nameSafe = !empty(trim($name)) ? trim($name) : $email;
        $mail->addAddress($email, $nameSafe);
        $mail->isHTML(true);
        $mail->Subject = 'Login Verification - One-Time Password (OTP)';
        // Use sanitized name for email body (already sanitized as $nameSafe above)
        $mail->Body = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #dc3545;">Blood Bank Portal</h2>
            <p>Hello ' . htmlspecialchars($nameSafe) . ',</p>
            <p>You have requested to log in to your ' . ucfirst($role) . ' account. Please use the following One-Time Password (OTP) to complete your login:</p>
            <div style="background-color: #f8f9fa; border: 2px solid #dc3545; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;">
                <h1 style="color: #dc3545; font-size: 32px; letter-spacing: 4px; margin: 0;">' . htmlspecialchars($otp) . '</h1>
            </div>
            <p><strong>This OTP will expire in 10 minutes.</strong></p>
            <p>If you did not request this OTP, please ignore this email or contact support immediately.</p>
            <p>For security reasons, never share your OTP with anyone.</p>
            <p>Thank you,<br>Blood Bank Portal Team</p>
        </div>';
        $mail->AltBody = 'Hello ' . $nameSafe . ",\n\n" .
                         'You have requested to log in to your ' . ucfirst($role) . ' account. Please use the following One-Time Password (OTP) to complete your login:\n\n' .
                         'OTP Code: ' . $otp . "\n\n" .
                         "This OTP will expire in 10 minutes.\n\n" .
                         "If you did not request this OTP, please ignore this email or contact support immediately.\n\n" .
                         "Thank you,\nBlood Bank Portal Team";

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('OTP Email error: ' . $e->getMessage());
        return false;
    }
}
?>
