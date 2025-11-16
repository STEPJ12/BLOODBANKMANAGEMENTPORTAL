<?php
// Start session
session_start();

require_once 'config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = $email_err = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    // Basic email validation
    if (empty($email)) {
        $email_err = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = 'Please enter a valid email address.';
    } else {
        // Check if email exists in any user table
        $found = false;
        $userRole = '';
        $userTables = [
            'donor_users' => 'donor',
            'patient_users' => 'patient', 
            'redcross_users' => 'redcross',
            'barangay_users' => 'barangay',
            'negrosfirst_users' => 'negrosfirst',
            'admin_users' => 'admin'
        ];
        
        foreach ($userTables as $table => $role) {
            $user = getRow("SELECT * FROM $table WHERE email = ?", [$email]);
            if ($user) {
                $found = true;
                $userRole = $role;
                break;
            }
        }
        if ($found) {
            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            // Remove any existing reset for this email
            executeQuery("DELETE FROM password_resets WHERE email = ?", [$email]);
            // Store the token with role information (handle case where role column might not exist)
            try {
                $result = insertRow("INSERT INTO password_resets (email, token, expires_at, role) VALUES (?, ?, ?, ?)", [$email, $token, $expires_at, $userRole]);
            } catch (Exception $e) {
                // Fallback if role column doesn't exist yet
                $result = insertRow("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)", [$email, $token, $expires_at]);
            }
            if (!$result) {
                die('Failed to insert password reset token. Check your database connection and table structure.');
            }
            // Prepare the reset link
            $reset_link = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset-password.php?token=' . $token;

            // Include PHPMailer manually
            require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
            require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
            require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
            
            // Send email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                //Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'davidstephanie022@gmail.com';
                $mail->Password   = 'jlncsfeplcgjbrbu'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                //Recipients
                $mail->setFrom('davidstephanie022@gmail.com', 'BloodBankApp');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "Click the link below to reset your password:<br><a href='$reset_link'>$reset_link</a><br>This link will expire in 1 hour.";

                $mail->send();
                $success = 'If this email exists in our system, a password reset link has been sent to your email address.';
            } catch (Exception $e) {
                $success = 'There was an error sending the reset email: ' . $mail->ErrorInfo;
            }
        } else {
            $success = 'If this email exists in our system, a password reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Blood Bank Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .forgot-container { max-width: 400px; margin: 100px auto; }
        .forgot-header { text-align: center; margin-bottom: 30px; }
        .forgot-logo { font-size: 48px; color: #dc3545; margin-bottom: 20px; }
        .forgot-card { border: none; border-radius: 10px; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); }
        .forgot-form { padding: 30px; }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="forgot-logo"><i class="bi bi-droplet-fill"></i></div>
            <h2>Forgot Password</h2>
            <p class="text-muted">Enter your email to reset your password</p>
        </div>
        <div class="card forgot-card">
            <div class="card-body forgot-form">
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><?php echo $success; ?></div>
                <?php else: ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" placeholder="Enter your email" required>
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Send Reset Link</button>
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