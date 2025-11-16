<?php
/**
 * Negros First Security Functions
 * Account lockout, OTP, session management, and enhanced security
 */

require_once __DIR__ . '/../config/db.php';

/**
 * Check if account is locked
 */
function isAccountLockedNF(int $userId, string $role = 'negrosfirst'): bool {
    $lockout = getRow(
        "SELECT locked_until FROM account_lockouts WHERE user_id = ? AND user_role = ?",
        [$userId, $role]
    );
    
    if (!$lockout || !$lockout['locked_until']) {
        return false;
    }
    
    $lockedUntil = strtotime($lockout['locked_until']);
    if ($lockedUntil > time()) {
        return true;
    }
    
    // Lock expired, clear it
    updateRow(
        "UPDATE account_lockouts SET locked_until = NULL, failed_attempts = 0 WHERE user_id = ? AND user_role = ?",
        [$userId, $role]
    );
    
    return false;
}

/**
 * Record failed login attempt
 */
function recordFailedLoginNF(int $userId, string $role = 'negrosfirst', int $maxAttempts = 5, int $lockoutMinutes = 15): void {
    $lockout = getRow(
        "SELECT * FROM account_lockouts WHERE user_id = ? AND user_role = ?",
        [$userId, $role]
    );
    
    if (!$lockout) {
        insertRow(
            "INSERT INTO account_lockouts (user_id, user_role, failed_attempts, last_failed_attempt) VALUES (?, ?, 1, NOW())",
            [$userId, $role]
        );
        return;
    }
    
    $failedAttempts = (int)$lockout['failed_attempts'] + 1;
    
    if ($failedAttempts >= $maxAttempts) {
        // Lock account
        $lockedUntil = date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60));
        updateRow(
            "UPDATE account_lockouts SET failed_attempts = ?, locked_until = ?, last_failed_attempt = NOW() WHERE user_id = ? AND user_role = ?",
            [$failedAttempts, $lockedUntil, $userId, $role]
        );
        audit_log($userId, $role, 'account_locked', "Account locked after {$failedAttempts} failed attempts. Locked until: {$lockedUntil}");
    } else {
        updateRow(
            "UPDATE account_lockouts SET failed_attempts = ?, last_failed_attempt = NOW() WHERE user_id = ? AND user_role = ?",
            [$failedAttempts, $userId, $role]
        );
    }
}

/**
 * Clear failed login attempts (on successful login)
 */
function clearFailedLoginAttemptsNF(int $userId, string $role = 'negrosfirst'): void {
    updateRow(
        "UPDATE account_lockouts SET failed_attempts = 0, locked_until = NULL WHERE user_id = ? AND user_role = ?",
        [$userId, $role]
    );
}

/**
 * Generate and store OTP
 */
function generateOTPNF(int $userId, string $email, string $role = 'negrosfirst', string $purpose = 'login', int $expiryMinutes = 10): ?string {
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
 * Verify OTP
 */
function verifyOTPNF(int $userId, string $otp, string $role = 'negrosfirst', string $purpose = 'login'): bool {
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
 * Send OTP email
 */
function sendOTPEmailNF(string $email, string $name, string $otp): bool {
    require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';
    require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
    require_once __DIR__ . '/../config/api_keys.php';
    
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
        $fromName = getenv('MAIL_FROM_NAME') ?: 'Negros First Provincial Blood Bank';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($email, $name ?: $email);
        $mail->isHTML(true);
        $mail->Subject = 'Negros First Login - One-Time Password (OTP)';
        $mail->Body = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #b31217;">Negros First Provincial Blood Bank</h2>
            <p>Hello ' . htmlspecialchars($name ?: $email) . ',</p>
            <p>You have requested to log in to your Negros First account. Please use the following One-Time Password (OTP) to complete your login:</p>
            <div style="background-color: #f8f9fa; border: 2px solid #b31217; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;">
                <h1 style="color: #b31217; font-size: 32px; letter-spacing: 4px; margin: 0;">' . htmlspecialchars($otp) . '</h1>
            </div>
            <p><strong>This OTP will expire in 10 minutes.</strong></p>
            <p>If you did not request this OTP, please ignore this email or contact support immediately.</p>
            <p>For security reasons, never share your OTP with anyone.</p>
            <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
            <p style="color: #666; font-size: 12px;">This is an automated message from the Negros First Provincial Blood Bank Portal.</p>
        </div>';
        $mail->AltBody = "Negros First Login OTP\n\nHello {$name},\n\nYour One-Time Password (OTP) is: {$otp}\n\nThis OTP will expire in 10 minutes.\n\nIf you did not request this, please ignore this email.";
        
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('OTP Email error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Validate password strength
 */
function validatePasswordStrengthNF(string $password): array {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    if (strlen($password) > 12) {
        $errors[] = 'Password must not exceed 12 characters';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Track session activity
 */
function trackSessionActivityNF(int $userId, string $role = 'negrosfirst'): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $sessionId = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Update or insert session activity
    $existing = getRow(
        "SELECT id FROM session_activities WHERE user_id = ? AND user_role = ? AND session_id = ?",
        [$userId, $role, $sessionId]
    );
    
    if ($existing) {
        updateRow(
            "UPDATE session_activities SET last_activity = NOW(), ip = ?, user_agent = ? WHERE id = ?",
            [$ip, $userAgent, $existing['id']]
        );
    } else {
        insertRow(
            "INSERT INTO session_activities (user_id, user_role, session_id, ip, user_agent, last_activity) VALUES (?, ?, ?, ?, ?, NOW())",
            [$userId, $role, $sessionId, $ip, $userAgent]
        );
    }
    
    // Set last activity timestamp in session
    $_SESSION['last_activity'] = time();
}

/**
 * Check session timeout (10-15 minutes)
 */
function checkSessionTimeoutNF(int $timeoutMinutes = 15): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    $timeSinceActivity = time() - $_SESSION['last_activity'];
    
    if ($timeSinceActivity > ($timeoutMinutes * 60)) {
        // Session expired
        session_destroy();
        return false;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Clean up old session activities and expired OTPs
 */
function cleanupSecurityDataNF(): void {
    // Delete expired OTPs (older than 1 hour)
    executeQuery(
        "DELETE FROM otp_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    
    // Delete old session activities (older than 7 days)
    executeQuery(
        "DELETE FROM session_activities WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    
    // Clear expired lockouts
    executeQuery(
        "UPDATE account_lockouts SET locked_until = NULL WHERE locked_until < NOW()"
    );
}

