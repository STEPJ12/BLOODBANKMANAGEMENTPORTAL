<?php
/**
 * Email System Diagnostics
 * 
 * This page helps diagnose email system issues
 */

$results = [];

// Test 1: Check if mail() function exists
$results['mail_function'] = function_exists('mail');

// Test 2: Check PHP configuration
$results['php_version'] = phpversion();
$results['sendmail_path'] = ini_get('sendmail_path');
$results['smtp'] = ini_get('SMTP');
$results['smtp_port'] = ini_get('smtp_port');
$results['sendmail_from'] = ini_get('sendmail_from');

// Test 3: Check if we can create a socket connection
$results['socket_test'] = false;
try {
    $socket = fsockopen('smtp.gmail.com', 587, $errno, $errstr, 5);
    if ($socket) {
        $results['socket_test'] = true;
        fclose($socket);
    }
} catch (Exception $e) {
    $results['socket_error'] = $e->getMessage();
}

// Test 4: Check email configuration
try {
    require_once 'config/email.php';
    $config = getEmailConfig();
    $results['email_config'] = $config;
} catch (Exception $e) {
    $results['config_error'] = $e->getMessage();
}

// Test 5: Try to send a simple email
$results['simple_mail_test'] = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_simple'])) {
    $to = $_POST['test_email'] ?? '';
    if ($to) {
        $subject = 'Simple Test Email';
        $message = 'This is a simple test email.';
        $headers = 'From: noreply@bloodbank.com';
        
        $result = mail($to, $subject, $message, $headers);
        $results['simple_mail_test'] = $result;
        $results['simple_mail_message'] = $result ? 'Success' : 'Failed';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System Diagnostics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Email System Diagnostics</h3>
                    </div>
                    <div class="card-body">
                        
                        <!-- Test 1: Mail Function -->
                        <div class="mb-4">
                            <h5>1. PHP Mail Function</h5>
                            <div class="alert alert-<?php echo $results['mail_function'] ? 'success' : 'danger'; ?>">
                                <strong>Status:</strong> <?php echo $results['mail_function'] ? '✅ Available' : '❌ Not Available'; ?>
                            </div>
                        </div>
                        
                        <!-- Test 2: PHP Configuration -->
                        <div class="mb-4">
                            <h5>2. PHP Configuration</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Setting</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <th scope="row"><strong>PHP Version:</strong></th>
                                    <td><?php echo $results['php_version']; ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><strong>Sendmail Path:</strong></th>
                                    <td><?php echo $results['sendmail_path'] ?: 'Not set'; ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><strong>SMTP:</strong></th>
                                    <td><?php echo $results['smtp'] ?: 'Not set'; ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><strong>SMTP Port:</strong></th>
                                    <td><?php echo $results['smtp_port'] ?: 'Not set'; ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><strong>Sendmail From:</strong></th>
                                    <td><?php echo $results['sendmail_from'] ?: 'Not set'; ?></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Test 3: Socket Connection -->
                        <div class="mb-4">
                            <h5>3. Gmail SMTP Connection</h5>
                            <div class="alert alert-<?php echo $results['socket_test'] ? 'success' : 'danger'; ?>">
                                <strong>Status:</strong> <?php echo $results['socket_test'] ? '✅ Can connect to Gmail SMTP' : '❌ Cannot connect to Gmail SMTP'; ?>
                                <?php if (isset($results['socket_error'])): ?>
                                    <br><small>Error: <?php echo htmlspecialchars($results['socket_error']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Test 4: Email Configuration -->
                        <div class="mb-4">
                            <h5>4. Email Configuration</h5>
                            <?php if (isset($results['email_config'])): ?>
                                <div class="alert alert-info">
                                    <strong>SMTP Host:</strong> <?php echo htmlspecialchars($results['email_config']['smtp_host']); ?><br>
                                    <strong>SMTP Port:</strong> <?php echo htmlspecialchars($results['email_config']['smtp_port']); ?><br>
                                    <strong>From Address:</strong> <?php echo htmlspecialchars($results['email_config']['from_address']); ?><br>
                                    <strong>From Name:</strong> <?php echo htmlspecialchars($results['email_config']['from_name']); ?><br>
                                    <strong>Enabled:</strong> <?php echo $results['email_config']['enabled'] ? 'Yes' : 'No'; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <strong>Error:</strong> <?php echo htmlspecialchars($results['config_error'] ?? 'Unknown error'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Test 5: Simple Mail Test -->
                        <div class="mb-4">
                            <h5>5. Simple Mail Test</h5>
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-8">
                                        <input type="email" class="form-control" name="test_email" 
                                               value="scdavid.chmsu@gmail.com" placeholder="Enter email address">
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" name="test_simple" class="btn btn-primary">
                                            Test Simple Mail
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <?php if (isset($results['simple_mail_test'])): ?>
                                <div class="alert alert-<?php echo $results['simple_mail_test'] ? 'success' : 'danger'; ?> mt-2">
                                    <strong>Result:</strong> <?php echo $results['simple_mail_message'] ?? ($results['simple_mail_test'] ? 'Success' : 'Failed'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Recommendations -->
                        <div class="mb-4">
                            <h5>Recommendations</h5>
                            <div class="alert alert-warning">
                                <?php if (!$results['mail_function']): ?>
                                    <p><strong>Issue:</strong> PHP mail() function is not available.</p>
                                    <p><strong>Solution:</strong> Contact your hosting provider to enable mail functionality.</p>
                                <?php elseif (!$results['socket_test']): ?>
                                    <p><strong>Issue:</strong> Cannot connect to Gmail SMTP.</p>
                                    <p><strong>Solution:</strong> Check firewall settings or use a different email service.</p>
                                <?php elseif (isset($results['simple_mail_test']) && !$results['simple_mail_test']): ?>
                                    <p><strong>Issue:</strong> PHP mail() function is not working.</p>
                                    <p><strong>Solution:</strong> Configure your server's mail settings or use an external email service.</p>
                                <?php else: ?>
                                    <p><strong>Status:</strong> Email system appears to be working correctly!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="test-email.php" class="btn btn-secondary">Back to Email Test</a>
                            <a href="test-working-email.php" class="btn btn-info">Test Working Email</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
