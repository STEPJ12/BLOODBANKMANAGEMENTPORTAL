<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

$message = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = $_POST['provider'] ?? '';
    $apiKey = $_POST['api_key'] ?? '';
    $apiSecret = $_POST['api_secret'] ?? '';
    $senderId = $_POST['sender_id'] ?? '';
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $testPhone = $_POST['test_phone'] ?? '';
    
    if ($provider && $apiKey && $senderId) {
        // Deactivate current config
        executeQuery("UPDATE sms_config SET active = 0 WHERE active = 1");
        
        // Insert new config
        $result = insertRow("
            INSERT INTO sms_config (provider, api_key, api_secret, sender_id, enabled, active)
            VALUES (?, ?, ?, ?, ?, 1)
        ", [$provider, $apiKey, $apiSecret, $senderId, $enabled]);
        
        if ($result) {
            $success = true;
            $message = 'SMS configuration updated successfully!';
            
            // Test SMS if phone number provided
            if ($testPhone && $enabled) {
                require_once '../../includes/SMS/SMSNotificationService.php';
                $smsService = new SMSNotificationService();
                $testResult = $smsService->testSMS($testPhone);
                
                if ($testResult['success']) {
                    $message .= ' Test SMS sent successfully!';
                } else {
                    $message .= ' Test SMS failed: ' . ($testResult['error'] ?? 'Unknown error');
                }
            }
        } else {
            $message = 'Failed to update SMS configuration.';
        }
    } else {
        $message = 'Please fill in all required fields.';
    }
}

// Get current SMS configuration
$smsConfig = getRow("SELECT * FROM sms_config WHERE active = 1 ORDER BY id DESC LIMIT 1");
$smsStats = getRow("
    SELECT 
        COUNT(*) as total_sent,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM sms_logs 
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");

$pageTitle = "SMS Configuration - Negros First Portal";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .config-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="index.php">
                                <i class="bi bi-house"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="inventory.php">
                                <i class="bi bi-box"></i> Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="blood-requests.php">
                                <i class="bi bi-heart-pulse"></i> Blood Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="donations.php">
                                <i class="bi bi-droplet"></i> Donations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="sms-config.php">
                                <i class="bi bi-phone"></i> SMS Config
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">SMS Configuration</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- SMS Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Total Sent</h6>
                                    <h3><?php echo $smsStats['total_sent'] ?? 0; ?></h3>
                                </div>
                                <i class="bi bi-send fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Delivered</h6>
                                    <h3><?php echo $smsStats['delivered'] ?? 0; ?></h3>
                                </div>
                                <i class="bi bi-check-circle fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Failed</h6>
                                    <h3><?php echo $smsStats['failed'] ?? 0; ?></h3>
                                </div>
                                <i class="bi bi-x-circle fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Pending</h6>
                                    <h3><?php echo $smsStats['pending'] ?? 0; ?></h3>
                                </div>
                                <i class="bi bi-clock fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SMS Configuration Form -->
                <div class="card config-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> SMS Provider Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="provider" class="form-label">SMS Provider</label>
                                        <select class="form-select" id="provider" name="provider" required>
                                            <option value="">Select Provider</option>
                                            <option value="twilio" <?php echo ($smsConfig['provider'] ?? '') === 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                                            <option value="textlocal" <?php echo ($smsConfig['provider'] ?? '') === 'textlocal' ? 'selected' : ''; ?>>TextLocal</option>
                                            <option value="msg91" <?php echo ($smsConfig['provider'] ?? '') === 'msg91' ? 'selected' : ''; ?>>MSG91</option>
                                            <option value="semaphore" <?php echo ($smsConfig['provider'] ?? '') === 'semaphore' ? 'selected' : ''; ?>>Semaphore</option>
                                            <option value="sim800c" <?php echo ($smsConfig['provider'] ?? '') === 'sim800c' ? 'selected' : ''; ?>>SIM800C (Hardware)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sender_id" class="form-label">Sender ID</label>
                                        <input type="text" class="form-control" id="sender_id" name="sender_id" 
                                               value="<?php echo htmlspecialchars($smsConfig['sender_id'] ?? 'NegrosFirst'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="api_key" class="form-label">API Key / Account SID</label>
                                        <input type="text" class="form-control" id="api_key" name="api_key" 
                                               value="<?php echo htmlspecialchars($smsConfig['api_key'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="api_secret" class="form-label">API Secret / Auth Token</label>
                                        <input type="password" class="form-control" id="api_secret" name="api_secret" 
                                               value="<?php echo htmlspecialchars($smsConfig['api_secret'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="test_phone" class="form-label">Test Phone Number</label>
                                        <input type="tel" class="form-control" id="test_phone" name="test_phone" 
                                               placeholder="+639123456789">
                                        <small class="form-text text-muted">Optional: Send test SMS after saving</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enabled" name="enabled" 
                                                   <?php echo ($smsConfig['enabled'] ?? false) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enabled">
                                                Enable SMS Notifications
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-save"></i> Save Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- SMS Logs -->
                <div class="card config-card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list"></i> Recent SMS Logs</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $logs = executeQuery("
                            SELECT phone_number, message, provider, status, sent_at, error_message
                            FROM sms_logs 
                            ORDER BY sent_at DESC 
                            LIMIT 10
                        ");
                        ?>
                        
                        <?php if (!empty($logs)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Phone</th>
                                            <th>Message</th>
                                            <th>Provider</th>
                                            <th>Status</th>
                                            <th>Sent At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($log['phone_number']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($log['message'], 0, 50)) . '...'; ?></td>
                                                <td><?php echo htmlspecialchars($log['provider']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $log['status'] === 'delivered' ? 'success' : 
                                                            ($log['status'] === 'failed' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo ucfirst($log['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y H:i', strtotime($log['sent_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No SMS logs found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
