<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

// Set page title
$pageTitle = "Donations - Barangay Dashboard";
$isDashboard = true;

// Include database connection
require_once '../../config/db.php';

// Get barangay information
$barangayId = $_SESSION['user_id'];
$barangayRow = getRow("SELECT * FROM barangay_users WHERE id = ?", [$barangayId]);
$barangayName = $barangayRow['name'] ?? 'Barangay';

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SECURITY: Validate and sanitize all inputs
    // Donor ID - must be integer and belong to this barangay
    // NOTE: sanitize() accepts user input (intended), but $donorId is validated as integer below
    $donorIdInput = isset($_POST['donor_id']) ? sanitize($_POST['donor_id']) : '';
    
    // SECURITY: Validate as integer - $donorId can ONLY be an integer (1+) or false after this
    // This ensures $donorId is safe for use in SQL queries and logging
    $donorId = filter_var($donorIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    
    // Validate donor exists and belongs to this barangay
    if (!$donorId) {
        $message = "Invalid donor selected.";
        $messageType = "danger";
    } else {
        // SECURITY: Additional validation - ensure donor belongs to this barangay
        // This prevents unauthorized access to other barangays' donors
        $donorCheck = getRow("SELECT id FROM donor_users WHERE id = ? AND barangay_id = ?", [$donorId, $barangayId]);
        if (!$donorCheck) {
            $donorId = false; // Reset if validation fails
            $message = "Donor not found or does not belong to your barangay.";
            $messageType = "danger";
        }
    }
    
    // SECURITY NOTE: At this point, $donorId is guaranteed to be:
    // - An integer >= 1 (validated by FILTER_VALIDATE_INT)
    // - Existing in database and belonging to this barangay (validated by database query)
    // - Safe for use in SQL (via parameterized queries) and logging (via secure_log)
    
    // Blood type - whitelist validation
    $bloodTypeInput = isset($_POST['blood_type']) ? sanitize($_POST['blood_type']) : '';
    $allowedBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    $bloodType = in_array($bloodTypeInput, $allowedBloodTypes, true) ? $bloodTypeInput : '';
    
    // Units - must be positive integer
    $unitsInput = isset($_POST['units']) ? sanitize($_POST['units']) : '';
    $units = filter_var($unitsInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
    
    // Donation date - validate format
    $donationDateInput = isset($_POST['donation_date']) ? sanitize($_POST['donation_date']) : '';
    $donationDate = '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $donationDateInput)) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $donationDateInput);
        if ($dateObj && $dateObj->format('Y-m-d') === $donationDateInput) {
            // Ensure date is not in the future
            $today = new DateTime();
            if ($dateObj <= $today) {
                $donationDate = $donationDateInput;
            }
        }
    }
    
    // Organization type - whitelist validation
    $organizationTypeInput = isset($_POST['organization_type']) ? sanitize($_POST['organization_type']) : '';
    $allowedOrgTypes = ['redcross', 'negrosfirst'];
    $organizationType = in_array(strtolower($organizationTypeInput), $allowedOrgTypes, true) ? strtolower($organizationTypeInput) : '';
    
    // Organization ID - must be positive integer
    $organizationIdInput = isset($_POST['organization_id']) ? sanitize($_POST['organization_id']) : '';
    $organizationId = filter_var($organizationIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    
    // Blood drive ID - optional, must be positive integer if provided
    $bloodDriveId = null;
    if (!empty($_POST['blood_drive_id'])) {
        $bloodDriveIdInput = sanitize($_POST['blood_drive_id']);
        $bloodDriveId = filter_var($bloodDriveIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    }
    
    // Health status - sanitize and limit length
    $healthStatus = isset($_POST['health_status']) ? substr(sanitize($_POST['health_status']), 0, 50) : '';
    
    // Hemoglobin level - validate as numeric
    $hemoglobinLevelInput = isset($_POST['hemoglobin_level']) ? sanitize($_POST['hemoglobin_level']) : '';
    $hemoglobinLevel = filter_var($hemoglobinLevelInput, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 20]]) ?: '';
    
    // Blood pressure - sanitize and validate format (e.g., "120/80")
    $bloodPressureInput = isset($_POST['blood_pressure']) ? sanitize($_POST['blood_pressure']) : '';
    $bloodPressure = preg_match('/^\d{2,3}\/\d{2,3}$/', $bloodPressureInput) ? $bloodPressureInput : '';
    
    // Pulse rate - validate as integer
    $pulseRateInput = isset($_POST['pulse_rate']) ? sanitize($_POST['pulse_rate']) : '';
    $pulseRate = filter_var($pulseRateInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 40, 'max_range' => 200]]) ?: '';
    
    // Temperature - validate as float
    $temperatureInput = isset($_POST['temperature']) ? sanitize($_POST['temperature']) : '';
    $temperature = filter_var($temperatureInput, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 35.0, 'max_range' => 42.0]]) ?: '';
    
    // Notes - sanitize and limit length
    $notes = isset($_POST['notes']) ? substr(sanitize($_POST['notes']), 0, 1000) : '';

    // SECURITY: Validate all required fields before proceeding
    if (!$donorId || !$bloodType || !$units || !$donationDate || !$organizationType) {
        $message = "Please fill in all required fields with valid data.";
        $messageType = "danger";
    } else {
        // Insert new donation
        $sql = "INSERT INTO donations (donor_id, blood_type, units, donation_date, organization_type, organization_id, barangay_id, blood_drive_id, health_status, hemoglobin_level, blood_pressure, pulse_rate, temperature, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $donorId, $bloodType, $units, $donationDate, $organizationType, $organizationId,
            $barangayId, $bloodDriveId, $healthStatus, $hemoglobinLevel, $bloodPressure,
            $pulseRate, $temperature, 'Completed', $notes
        ];

        $donationId = insertRow($sql, $params);

    if ($donationId) {
        // Update donor's last donation date
        updateRow("UPDATE donor_users SET last_donation_date = ? WHERE id = ?", [$donationDate, $donorId]);

        // Update blood drive collected units if applicable
        if ($bloodDriveId) {
            updateRow("UPDATE blood_drives SET units = units + ? WHERE id = ?", [$units, $bloodDriveId]);
        }

        // Add to blood inventory (expiry: 35 days from donation date)
        $expiryDate = date('Y-m-d', strtotime($donationDate . ' + 35 days'));
        insertRow(
            "INSERT INTO blood_inventory (blood_type, units, expiry_date, status, organization_type, organization_id) VALUES (?, ?, ?, ?, ?, ?)",
            [$bloodType, $units, $expiryDate, 'Available', $organizationType, $organizationId]
        );

        // Create notification for donor
        $notificationTitle = "Thank You for Your Donation";
        $notificationMessage = "Thank you for donating blood on " . date('F j, Y', strtotime($donationDate)) . ". Your donation can save up to 3 lives!";

        insertRow(
            "INSERT INTO notifications (user_role, user_id, title, message) VALUES (?, ?, ?, ?)",
            ['donor', $donorId, $notificationTitle, $notificationMessage]
        );
        
        // Send SMS notification to donor
        try {
            require_once '../../includes/sim800c_sms.php';
            require_once '../../includes/notification_templates.php';
            
            // Get donor information
            $donorInfo = getRow("SELECT name, phone FROM donor_users WHERE id = ?", [$donorId]);
            if ($donorInfo && !empty($donorInfo['phone'])) {
                $donorPhone = $donorInfo['phone'];
                $donorName = $donorInfo['name'] ?? '';
                
                // Try to decrypt phone number if encrypted
                if (function_exists('decrypt_value')) {
                    $decryptedPhone = decrypt_value($donorPhone);
                    if (!empty($decryptedPhone)) {
                        $donorPhone = $decryptedPhone;
                    }
                }
                
                if (!empty($donorPhone) && trim($donorPhone) !== '') {
                    // Get barangay name
                    $barangayInfo = getRow("SELECT name FROM barangay_users WHERE id = ?", [$barangayId]);
                    $barangayName = $barangayInfo['name'] ?? 'your barangay';
                    
                    // Build professional SMS message
                    $formattedDate = date('F j, Y', strtotime($donationDate));
                    $smsMessage = "Hello {$donorName}, this is from {$barangayName}. ";
                    $smsMessage .= "Thank you for donating blood on {$formattedDate}. ";
                    $smsMessage .= "Your donation can save up to 3 lives! ";
                    $smsMessage .= "Blood Type: {$bloodType}, Units: {$units}. ";
                    $smsMessage .= "We truly appreciate your kindness and support!";
                    $smsMessage = format_notification_message($smsMessage);
                    
                    // SECURITY: Use secure_log instead of error_log to prevent log injection
                    if (function_exists('secure_log')) {
                        secure_log("Barangay donation SMS sending", [
                            'donor_id' => $donorId,
                            'phone_prefix' => substr($donorPhone, 0, 4)
                        ]);
                    }
                    
                    $smsResult = send_sms_sim800c($donorPhone, $smsMessage);
                    
                    if ($smsResult['success']) {
                        if (function_exists('secure_log')) {
                            secure_log("Barangay donation SMS sent successfully");
                        }
                    } else {
                        $smsError = isset($smsResult['error']) ? substr($smsResult['error'], 0, 200) : 'Unknown error';
                        if (function_exists('secure_log')) {
                            secure_log("Barangay donation SMS failed", ['error' => $smsError]);
                        }
                    }
                }
            }
        } catch (Exception $smsEx) {
            // SECURITY: Use secure_log for exception logging
            if (function_exists('secure_log')) {
                secure_log("Barangay donation SMS exception", [
                    'error' => substr($smsEx->getMessage(), 0, 200)
                ]);
            }
            // Don't block donation if SMS fails
        }

        // Add reward points to donor
        updateRow("UPDATE donor_rewards SET points = points + 10 WHERE donor_id = ?", [$donorId]);

            $message = "Donation recorded successfully!";
            $messageType = "success";
        } else {
            $message = "Error recording donation. Please try again.";
            $messageType = "danger";
        }
    }
}

// Get all donations from this barangay
$donations = executeQuery("
    SELECT d.*, du.name as donor_name, du.blood_type as donor_blood_type, bd.title as blood_drive_title
    FROM donations d
    JOIN donor_users du ON d.donor_id = du.id
    LEFT JOIN blood_drives bd ON d.blood_drive_id = bd.id
    WHERE d.barangay_id = ?
    ORDER BY d.donation_date DESC
", [$barangayId]);

// Get all donors from this barangay
$donors = executeQuery("SELECT id, name, blood_type FROM donor_users WHERE barangay_id = ? ORDER BY name", [$barangayId]);

// Get all blood drives from this barangay
$bloodDrives = executeQuery("SELECT id, title, date FROM blood_drives WHERE barangay_id = ? AND status != 'Cancelled' ORDER BY date DESC", [$barangayId]);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>
    
    <?php
    // Determine the correct path for CSS files - MUST be defined before use
    $basePath = '';
    if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
        $basePath = '../../';
    }
    ?>
    
    <link rel="stylesheet" href="../../css/barangay-portal.css">

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
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/dashboard.css">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    
    <?php include_once 'shared-styles.php'; ?>
    
    <style>
        /* Override table row backgrounds to white */
        .table tbody tr {
            background: #ffffff !important;
            color: #2a363b !important;
        }
        
        .table tbody tr:hover {
            background: rgba(234, 179, 8, 0.1) !important;
            color: #2a363b !important;
        }
        
        .table tbody td {
            background: transparent !important;
            color: #2a363b !important;
        }
        
        .table tbody td * {
            color: #2a363b !important;
        }
        
        /* Ensure card body has white background */
        .card-body {
            background: #ffffff !important;
        }
        
        /* Search bar styling - change from gray to white with blue border */
        #donationSearch {
            background: #ffffff !important;
            color: #2a363b !important;
            border: 2px solid rgba(59, 130, 246, 0.3) !important;
            border-radius: 8px 0 0 8px !important;
            padding: 0.75rem 1rem !important;
            transition: all 0.3s ease !important;
        }
        
        #donationSearch:focus {
            background: #ffffff !important;
            color: #2a363b !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
            outline: none !important;
        }
        
        #donationSearch::placeholder {
            color: #94a3b8 !important;
        }
        
        /* Search button styling - change from gray to blue */
        .input-group .btn-outline-secondary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            border: 2px solid #3b82f6 !important;
            border-left: none !important;
            color: #ffffff !important;
            border-radius: 0 8px 8px 0 !important;
            padding: 0.75rem 1rem !important;
            transition: all 0.3s ease !important;
        }
        
        .input-group .btn-outline-secondary:hover {
            background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%) !important;
            border-color: #eab308 !important;
            color: #1e293b !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(234, 179, 8, 0.4) !important;
        }
        
        .input-group .btn-outline-secondary i {
            color: inherit !important;
        }
        
        /* Table header styling */
        .table thead th {
            background: #f8f9fa !important;
            color: #2a363b !important;
            font-weight: 600 !important;
        }
        
        /* Empty state styling - make it visible */
        .table tbody td.text-center {
            background: #ffffff !important;
        }
        
        .table tbody td.text-center .text-muted {
            color: #64748b !important;
        }
        
        .table tbody td.text-center .text-muted i {
            color: #64748b !important;
            font-size: 3rem !important;
        }
        
        .table tbody td.text-center .text-muted p {
            color: #64748b !important;
            font-size: 1rem !important;
            margin-top: 1rem !important;
        }
        
        /* Action buttons visibility */
        .table tbody td .btn {
            color: #ffffff !important;
            font-weight: 500 !important;
        }
        
        .table tbody td .btn-outline-primary {
            border: 2px solid #3b82f6 !important;
            color: #3b82f6 !important;
            background: rgba(255, 255, 255, 0.8) !important;
        }
        
        .table tbody td .btn-outline-primary:hover {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            color: #ffffff !important;
            border-color: #3b82f6 !important;
        }
        
        .table tbody td .btn-outline-secondary {
            border: 2px solid #64748b !important;
            color: #64748b !important;
            background: rgba(255, 255, 255, 0.8) !important;
        }
        
        .table tbody td .btn-outline-secondary:hover {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%) !important;
            color: #ffffff !important;
            border-color: #64748b !important;
        }
        
        .table tbody td .btn-outline-danger {
            border: 2px solid #ef4444 !important;
            color: #ef4444 !important;
            background: rgba(255, 255, 255, 0.8) !important;
        }
        
        .table tbody td .btn-outline-danger:hover {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            color: #ffffff !important;
            border-color: #ef4444 !important;
        }
        
        .table tbody td .btn i {
            color: inherit !important;
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
                            <h1 class="mb-1">Donations Management</h1>
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
        <div class="dashboard-main">
            <div class="col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">Donation Records</h4>
                            <div class="input-group" style="width: 300px;">
                                <input type="text" id="donationSearch" class="form-control" placeholder="Search donations...">
                                <button class="btn btn-outline-secondary" type="button">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Donor</th>
                                            <th>Blood Type</th>
                                            <th>units</th>
                                            <th>Blood Drive</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($donations && count($donations) > 0): ?>
                                            <?php foreach ($donations as $donation): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                                    <td><?php echo $donation['donor_name']; ?></td>
                                                    <td>
                                                        <span class="badge bg-danger"><?php echo $donation['blood_type']; ?></span>
                                                    </td>
                                                    <td><?php echo (int)$donation['units']; ?> unit(s)</td>
                                                    <td>
                                                        <?php if ($donation['blood_drive_title']): ?>
                                                            <?php echo $donation['blood_drive_title']; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusClass = 'success';
                                                        if ($donation['status'] === 'Rejected') {
                                                            $statusClass = 'danger';
                                                        } elseif ($donation['status'] === 'Processing') {
                                                            $statusClass = 'warning';
                                                        }
                                                        ?>
                                                        <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $donation['status']; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-primary view-donation-btn"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#viewDonationModal"
                                                                data-id="<?php echo (int)$donation['id']; ?>"
                                                                data-date="<?php echo htmlspecialchars($donation['donation_date']); ?>"
                                                                data-donor="<?php echo htmlspecialchars($donation['donor_name']); ?>"
                                                                data-blood-type="<?php echo htmlspecialchars($donation['blood_type']); ?>"
                                                                data-units="<?php echo htmlspecialchars($donation['units']); ?>"
                                                                data-blood-drive="<?php echo htmlspecialchars($donation['blood_drive_title'] ?? 'N/A'); ?>"
                                                                data-status="<?php echo htmlspecialchars($donation['status']); ?>"
                                                                data-health-status="<?php echo htmlspecialchars($donation['health_status'] ?? ''); ?>"
                                                                data-hemoglobin="<?php echo htmlspecialchars($donation['hemoglobin_level'] ?? ''); ?>"
                                                                data-blood-pressure="<?php echo htmlspecialchars($donation['blood_pressure'] ?? ''); ?>"
                                                                data-pulse-rate="<?php echo htmlspecialchars($donation['pulse_rate'] ?? ''); ?>"
                                                                data-temperature="<?php echo htmlspecialchars($donation['temperature'] ?? ''); ?>"
                                                                data-notes="<?php echo htmlspecialchars($donation['notes'] ?? ''); ?>">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                            <a href="donation-edit.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteDonationModal<?php echo $donation['id']; ?>">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>

                                                        <!-- Delete Modal -->
                                                        <div class="modal fade" id="deleteDonationModal<?php echo $donation['id']; ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Confirm Deletion</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        Are you sure you want to delete this donation record?
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <a href="donation-delete.php?id=<?php echo $donation['id']; ?>" class="btn btn-danger">Delete</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="bi bi-droplet fs-1 d-block mb-3"></i>
                                                        <p>No donation records found.</p>
                                                    </div>
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

<!-- View Donation Modal -->
<div class="modal fade" id="viewDonationModal" tabindex="-1" aria-labelledby="viewDonationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewDonationModalLabel"><i class="bi bi-eyedropper me-2"></i>Donation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Date:</strong> <span id="vd-date"></span></div>
                        <div class="mb-2"><strong>Donor:</strong> <span id="vd-donor"></span></div>
                        <div class="mb-2"><strong>Blood Type:</strong> <span class="badge bg-danger" id="vd-blood-type"></span></div>
                        <div class="mb-2"><strong>Units:</strong> <span id="vd-units"></span></div>
                        <div class="mb-2"><strong>Status:</strong> <span class="badge" id="vd-status"></span></div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Blood Drive:</strong> <span id="vd-blood-drive"></span></div>
                        <div class="mb-2"><strong>Health Status:</strong> <span id="vd-health-status"></span></div>
                        <div class="mb-2"><strong>Hemoglobin:</strong> <span id="vd-hemoglobin"></span></div>
                        <div class="mb-2"><strong>Blood Pressure:</strong> <span id="vd-blood-pressure"></span></div>
                        <div class="mb-2"><strong>Pulse Rate:</strong> <span id="vd-pulse-rate"></span></div>
                        <div class="mb-2"><strong>Temperature:</strong> <span id="vd-temperature"></span></div>
                    </div>
                </div>
                <hr>
                <div>
                    <strong>Notes:</strong>
                    <p class="mb-0" id="vd-notes"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
            </div>
        </div>
    </div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Auto-fill blood type when donor is selected
document.addEventListener('DOMContentLoaded', function() {
    const donorSelect = document.getElementById('donor_id');
    const bloodTypeSelect = document.getElementById('blood_type');

    if (donorSelect && bloodTypeSelect) {
        donorSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.dataset.bloodType) {
                bloodTypeSelect.value = selectedOption.dataset.bloodType;
            }
        });
    }

    // Search functionality
    const searchInput = document.getElementById('donationSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // View modal population
    document.querySelectorAll('.view-donation-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const get = (attr) => this.getAttribute(attr) || '';
            const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '—'; };

            setText('vd-date', new Date(get('data-date')).toLocaleDateString());
            setText('vd-donor', get('data-donor'));
            setText('vd-blood-type', get('data-blood-type'));
            setText('vd-units', get('data-units'));
            setText('vd-blood-drive', get('data-blood-drive'));
            setText('vd-health-status', get('data-health-status'));
            setText('vd-hemoglobin', get('data-hemoglobin'));
            setText('vd-blood-pressure', get('data-blood-pressure'));
            setText('vd-pulse-rate', get('data-pulse-rate'));
            setText('vd-temperature', get('data-temperature'));
            setText('vd-notes', get('data-notes'));

            const status = get('data-status') || '';
            const statusEl = document.getElementById('vd-status');
            if (statusEl) {
                statusEl.textContent = status;
                statusEl.classList.remove('bg-success','bg-danger','bg-warning');
                let cls = 'bg-success';
                if (status.toLowerCase() === 'rejected') cls = 'bg-danger';
                else if (status.toLowerCase() === 'processing') cls = 'bg-warning';
                statusEl.classList.add(cls);
            }
        });
    });
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once '../../includes/footer.php'; ?>