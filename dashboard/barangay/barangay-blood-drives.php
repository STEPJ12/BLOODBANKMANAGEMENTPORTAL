<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

// Set page title
$pageTitle = "Blood Drives - Barangay Dashboard";
$isDashboard = true;

// Include database connection
require_once '../../config/db.php';

// Get barangay information
$barangayId = $_SESSION['user_id'];
$barangayRow = getRow("SELECT * FROM barangay_users WHERE id = ?", [$barangayId]);
$barangayName = $barangayRow['name'] ?? 'Barangay';
$barangay = $barangayRow; // Keep for backward compatibility

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $driveDate = sanitize($_POST['date']);
    $startTime = sanitize($_POST['start_time']);
    $endTime = sanitize($_POST['end_time']);
    $location = sanitize($_POST['location']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $province = sanitize($_POST['province']);
    $targetunits = sanitize($_POST['target_units']);

    // Insert new blood drive
    $sql = "INSERT INTO blood_drives (title, description, date, start_time, end_time, location, address, city, province, target_units, organization_type, organization_id, barangay_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        $title, $description, $driveDate, $startTime, $endTime, $location, $address,
        $city, $province, $targetunits, 'barangay', $barangayId, $barangayId, 'Scheduled'
    ];

    $driveId = insertRow($sql, $params);

    if ($driveId) {
        // Create notification for all donors in this barangay
        $notificationTitle = "New Blood Drive: $title";
        $notificationMessage = "A new blood drive has been scheduled on " . date('F j, Y', strtotime($driveDate)) . " at $location. Please consider donating!";

        $donors = executeQuery("SELECT id, name, phone FROM donor_users WHERE barangay_id = ?", [$barangayId]);

        // Send SMS notifications
        try {
            require_once '../../includes/sim800c_sms.php';
            require_once '../../includes/notification_templates.php';
            
            $barangayInfo = getRow("SELECT name FROM barangay_users WHERE id = ?", [$barangayId]);
            $barangayName = $barangayInfo['name'] ?? 'your barangay';
            $formattedDate = date('F j, Y', strtotime($driveDate));
            $formattedTime = '';
            if (!empty($startTime)) {
                $formattedTime = ' from ' . date('h:i A', strtotime($startTime));
                if (!empty($endTime)) {
                    $formattedTime .= ' to ' . date('h:i A', strtotime($endTime));
                }
            }
            
            $smsSentCount = 0;
            $smsErrorCount = 0;
            
            if ($donors) {
                foreach ($donors as $donor) {
                    $donorId = $donor['id'];
                    
                    // Create in-app notification
                    insertRow(
                        "INSERT INTO notifications (user_role, user_id, title, message) VALUES (?, ?, ?, ?)",
                        ['donor', $donorId, $notificationTitle, $notificationMessage]
                    );
                    
                    // Send SMS if phone number exists
                    $donorPhone = $donor['phone'] ?? '';
                    $donorName = $donor['name'] ?? 'Donor';
                    
                    if (!empty($donorPhone)) {
                        // Try to decrypt phone number if encrypted
                        if (function_exists('decrypt_value')) {
                            $decryptedPhone = decrypt_value($donorPhone);
                            if (!empty($decryptedPhone)) {
                                $donorPhone = $decryptedPhone;
                            }
                        }
                        
                        if (!empty($donorPhone) && trim($donorPhone) !== '') {
                            // Build professional SMS message
                            $smsMessage = "Hello {$donorName}, this is from {$barangayName}. ";
                            $smsMessage .= "A new blood drive has been scheduled on {$formattedDate}{$formattedTime} at {$location}. ";
                            $smsMessage .= "Please consider donating! Your contribution helps save lives. Thank you!";
                            $smsMessage = format_notification_message($smsMessage);
                            
                            try {
                                $smsResult = send_sms_sim800c($donorPhone, $smsMessage);
                                if ($smsResult['success']) {
                                    $smsSentCount++;
                                } else {
                                    $smsErrorCount++;
                                }
                            } catch (Exception $smsEx) {
                                $smsErrorCount++;
                                error_log('[BARANGAY_SMS_ERR] Exception sending blood drive SMS to donor ID ' . $donorId . ': ' . $smsEx->getMessage());
                            }
                        }
                    }
                }
            }
            
            error_log('[BARANGAY_SMS] Blood drive SMS summary - Sent: ' . $smsSentCount . ', Failed: ' . $smsErrorCount);
        } catch (Exception $smsEx) {
            error_log('[BARANGAY_SMS_ERR] Exception in blood drive SMS: ' . $smsEx->getMessage());
            // Still create notifications even if SMS fails
        }

        $message = "Blood drive scheduled successfully!";
        $messageType = "success";
    } else {
        $message = "Error scheduling blood drive. Please try again.";
        $messageType = "danger";
    }
}

// Get all blood drives organized by this barangay
$bloodDrives = executeQuery("
    SELECT * FROM blood_drives
    WHERE barangay_id = ?
    ORDER BY date DESC
", [$barangayId]);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $basePath; ?>assets/images/favicon.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons - CDN with fallback -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
 
    <!-- Custom Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <?php
    // Determine the correct path for CSS files
    $basePath = '';
    if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
        $basePath = '../../';
        echo '<link rel="stylesheet" href="' . $basePath . 'assets/css/dashboard.css">';
    }
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    <link rel="stylesheet" href="../../css/barangay-portal.css">
    
    <style>
    /* Ensure Bootstrap Icons display properly */
    .bi {
        display: inline-block;
        font-family: "bootstrap-icons" !important;
        font-style: normal;
        font-weight: normal !important;
        font-variant: normal;
        text-transform: none;
        line-height: 1;
        vertical-align: -0.125em;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    </style>

    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
</head>
<body>
<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <!-- Header positioned beside sidebar -->
    <div class="dashboard-header">
            <!-- Hamburger Menu Button (fallback if JS doesn't create it) -->
            <button class="header-toggle" type="button" aria-label="Toggle sidebar" aria-expanded="false" style="display: none;">
                <i class="bi bi-list"></i>
            </button>
            <div class="container-fluid">
                <div class="row align-items-center g-2">
                    <div class="col-lg-8 col-md-7 col-12">
                        <div class="header-content">
                            <h1 class="mb-1">Blood Drives Management</h1>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-5 col-12">
                        <div class="header-actions d-flex align-items-center justify-content-end gap-2">
                            <!-- Notification Bell - Moved to left of user dropdown -->
                            <?php include_once '../../includes/notification_bell.php'; ?>
                            
                            <!-- User Dropdown -->
                            <div class="dropdown">
                                <button class="btn user-dropdown dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar me-2">
                                        <i class="bi bi-person-badge-fill"></i>
                                    </div>
                                    <div class="user-info text-start d-none d-md-block">
                                        <span class="fw-medium d-block"><?php echo htmlspecialchars($barangayName); ?></span>
                                        <small class="text-muted">BHW Admin</small>
                                    </div>
                                    <span class="d-md-none fw-medium"><?php echo htmlspecialchars(substr($barangayName, 0, 15)) . (strlen($barangayName) > 15 ? '...' : ''); ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li class="dropdown-header">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-icon-wrapper me-2">
                                                <i class="bi bi-person-badge-fill"></i>
                                            </div>
                                            <div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($barangayName); ?></div>
                                                <small class="text-muted">Barangay Health Worker</small>
                                            </div>
                                        </div>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-gear-fill me-2"></i>Profile Settings</a></li>
                                   
                                    <li><a class="dropdown-item" href="notifications.php"><i class="bi bi-bell-fill me-2"></i>Notifications</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="../../logout.php"><i class="bi bi-power me-2"></i>Log Out</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <div class="dashboard-content">
        <div class="dashboard-main p-3">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12 mb-4">
                    <div class="card border-0 shadow-custom slide-up">
                        <div class="card-header bg-gradient-light border-0 py-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="card-title mb-1 text-primary-custom">
                                        <i class="bi bi-calendar-plus me-2"></i>
                                        Schedule New Blood Drive
                                    </h4>
                                    <p class="text-muted mb-0">Organize a blood donation event in your community</p>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="Save as draft">
                                        <i class="bi bi-save"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="Clear form">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <form action="" method="POST" class="row g-4">
                                <div class="col-md-6">
                                    <label for="title" class="form-label fw-bold">
                                        <i class="bi bi-card-text me-1"></i>Title <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-enhanced" id="title" name="title" placeholder="e.g., Community Blood Drive 2024" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="date" class="form-label fw-bold">
                                        <i class="bi bi-calendar-date me-1"></i>Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control form-control-enhanced" id="date" name="date" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="start_time" class="form-label fw-bold">
                                        <i class="bi bi-clock me-1"></i>Start Time <span class="text-danger">*</span>
                                    </label>
                                    <input type="time" class="form-control form-control-enhanced" id="start_time" name="start_time" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_time" class="form-label fw-bold">
                                        <i class="bi bi-clock-fill me-1"></i>End Time <span class="text-danger">*</span>
                                    </label>
                                    <input type="time" class="form-control form-control-enhanced" id="end_time" name="end_time" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="location" class="form-label fw-bold">
                                        <i class="bi bi-geo-alt me-1"></i>Location Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-enhanced" id="location" name="location" placeholder="e.g., Barangay Hall" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="target_units" class="form-label fw-bold">
                                        <i class="bi bi-bullseye me-1"></i>Target Units <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" step="1" class="form-control form-control-enhanced" id="target_units" name="target_units" min="1" placeholder="e.g., 50" required>
                                </div>
                                <div class="col-md-12">
                                    <label for="address" class="form-label fw-bold">
                                        <i class="bi bi-house me-1"></i>Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-enhanced" id="address" name="address" placeholder="Complete address of the venue" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="city" class="form-label fw-bold">
                                        <i class="bi bi-building me-1"></i>City <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-enhanced" id="city" name="city" value="<?php echo $barangay['city']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="province" class="form-label fw-bold">
                                        <i class="bi bi-map me-1"></i>Province <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-enhanced" id="province" name="province" value="<?php echo $barangay['province']; ?>" required>
                                </div>
                                <div class="col-md-12">
                                    <label for="description" class="form-label fw-bold">
                                        <i class="bi bi-chat-square-text me-1"></i>Description <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control form-control-enhanced" id="description" name="description" rows="4" placeholder="Describe the blood drive event, its purpose, and any special instructions..." required></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary-custom">
                                            <i class="bi bi-calendar-plus me-2"></i>Schedule Blood Drive
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="this.form.reset()">
                                            <i class="bi bi-arrow-clockwise me-2"></i>Clear Form
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="card border-0 shadow-custom slide-up">
                        <div class="card-header bg-gradient-light border-0 py-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="card-title mb-1 text-primary-custom">
                                        <i class="bi bi-calendar-event me-2"></i>
                                        All Blood Drives
                                    </h4>
                                    <p class="text-muted mb-0">View and manage your scheduled blood drives</p>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary active">All</button>
                                        <button type="button" class="btn btn-outline-primary">Upcoming</button>
                                        <button type="button" class="btn btn-outline-primary">Past</button>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="Export data">
                                            <i class="bi bi-download"></i>
                                        </button>
                                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="Refresh">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th class="fw-bold text-muted">Title</th>
                                            <th class="fw-bold text-muted">Date & Time</th>
                                            <th class="fw-bold text-muted">Location</th>
                                            <th class="fw-bold text-muted">Progress</th>
                                            <th class="fw-bold text-muted">Status</th>
                                            <th class="fw-bold text-muted">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($bloodDrives && count($bloodDrives) > 0): ?>
                                            <?php foreach ($bloodDrives as $drive): ?>
                                                <?php
                                                $statusClass = 'secondary';
                                                switch ($drive['status']) {
                                                    case 'Scheduled':
                                                        $statusClass = 'primary';
                                                        break;
                                                    case 'Ongoing':
                                                        $statusClass = 'success';
                                                        break;
                                                    case 'Completed':
                                                        $statusClass = 'info';
                                                        break;
                                                    case 'Cancelled':
                                                        $statusClass = 'danger';
                                                        break;
                                                }
                                                ?>
                                                <tr class="hover-row">
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="calendar-icon me-3 text-center">
                                                                <div class="bg-primary text-white rounded-top px-2 py-1">
                                                                    <small><?php echo date('M', strtotime($drive['date'])); ?></small>
                                                                </div>
                                                                <div class="border border-top-0 rounded-bottom px-2 py-1">
                                                                    <strong><?php echo date('d', strtotime($drive['date'])); ?></strong>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-1 fw-bold"><?php echo $drive['title']; ?></h6>
                                                                <small class="text-muted">
                                                                    <i class="bi bi-people me-1"></i>Community Event
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div><?php echo date('F j, Y', strtotime($drive['date'])); ?></div>
                                                        <div class="small text-muted">
                                                            <?php echo date('g:i A', strtotime($drive['start_time'])); ?> -
                                                            <?php echo date('g:i A', strtotime($drive['end_time'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div><?php echo $drive['location']; ?></div>
                                                        <div class="small text-muted"><?php echo $drive['address']; ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="progress-section">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="fw-bold text-primary-custom"><?php echo $drive['units']; ?></span>
                                                                <small class="text-muted">/ <?php echo $drive['target_units']; ?> units</small>
                                                            </div>
                                                            <?php
                                                            $percentage = ($drive['target_units'] > 0) ? ($drive['units'] / $drive['target_units']) * 100 : 0;
                                                            $progressClass = $percentage >= 100 ? 'bg-success' : ($percentage >= 75 ? 'bg-info' : ($percentage >= 50 ? 'bg-warning' : 'bg-danger'));
                                                            ?>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar <?php echo $progressClass; ?>" role="progressbar" style="width: <?php echo min($percentage, 100); ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                            <small class="text-muted"><?php echo round($percentage, 1); ?>% achieved</small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusIcon = '';
                                                        switch ($drive['status']) {
                                                            case 'Scheduled':
                                                                $statusIcon = 'bi-calendar-check';
                                                                break;
                                                            case 'Ongoing':
                                                                $statusIcon = 'bi-play-circle';
                                                                break;
                                                            case 'Completed':
                                                                $statusIcon = 'bi-check-circle';
                                                                break;
                                                            case 'Cancelled':
                                                                $statusIcon = 'bi-x-circle';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                                            <i class="bi <?php echo $statusIcon; ?> me-1"></i><?php echo $drive['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="drive-details.php?id=<?php echo $drive['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View details">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <?php if ($drive['status'] === 'Scheduled'): ?>
                                                                <a href="drive-edit.php?id=<?php echo $drive['id']; ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Edit drive">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteDriveModal<?php echo $drive['id']; ?>" data-bs-toggle="tooltip" title="Cancel drive">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-outline-secondary" disabled data-bs-toggle="tooltip" title="Cannot edit completed/cancelled drives">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Delete Modal -->
                                                        <div class="modal fade" id="deleteDriveModal<?php echo $drive['id']; ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Confirm Cancellation</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        Are you sure you want to cancel blood drive: <strong><?php echo $drive['title']; ?></strong>?
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                        <a href="drive-cancel.php?id=<?php echo $drive['id']; ?>" class="btn btn-danger">Cancel Drive</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5">
                                                    <div class="empty-state-icon mb-4">
                                                        <i class="bi bi-calendar-x"></i>
                                                    </div>
                                                    <h5 class="text-primary-custom mb-3">No Blood Drives Scheduled</h5>
                                                    <p class="text-muted mb-4">Start organizing blood donation events in your community.</p>
                                                    <button class="btn btn-primary-custom btn-sm" onclick="document.getElementById('title').focus()">
                                                        <i class="bi bi-calendar-plus me-1"></i>Schedule Your First Drive
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Filter blood drives by status
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.btn-group .btn');
    const tableRows = document.querySelectorAll('tbody tr');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');

            const filter = this.textContent.trim().toLowerCase();

            if (filter === 'all') {
                tableRows.forEach(row => {
                    row.style.display = '';
                });
                return;
            }

            const today = new Date();

            tableRows.forEach(row => {
                if (row.querySelector('td:first-child')) {
                    const dateText = row.querySelector('td:nth-child(2)').textContent.trim();
                    const driveDate = new Date(dateText.split('\n')[0]);

                    if (filter === 'upcoming' && driveDate >= today) {
                        row.style.display = '';
                    } else if (filter === 'past' && driveDate < today) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });
    });
});
</script>
</body>
</html>
<?php include_once '../../includes/footer.php'; ?>