<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

// Set page title
$pageTitle = "Blood Drives Management - Blood Bank Portal";
$isDashboard = true; // Enable notification dropdown

// Get Negros First information
$negrosFirstId = $_SESSION['user_id'];
$negrosFirst = getRow("SELECT * FROM negrosfirst_users WHERE id = ?", [$negrosFirstId]);

// Process form submission
$success = false;
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            // Validate input
            $title = sanitize($_POST['title']);
            $date = sanitize($_POST['date']);
            $time = sanitize($_POST['time']);
            $endTime = sanitize($_POST['end_time'] ?? '');
            $barangayId = sanitize($_POST['barangay_id']);
            $location = sanitize($_POST['location']);
            $address = sanitize($_POST['address']);
            $requirements = sanitize($_POST['requirements']);
            try {
                // Insert new blood drive
                $query = "INSERT INTO blood_drives (
                    title, date, start_time, end_time, barangay_id, organization_type, organization_id,
                    location, address, requirements, status, created_at
                ) VALUES (
                    :title, :date, :start_time, :end_time, :barangay_id, 'negrosfirst', :organization_id,
                    :location, :address, :requirements, 'Scheduled', NOW()
                )";

                $params = [
                    ':title' => $title,
                    ':date' => $date,
                    ':start_time' => $time,
                    ':end_time' => !empty($endTime) ? $endTime : null,
                    ':barangay_id' => $barangayId,
                    ':organization_id' => $negrosFirstId,
                    ':location' => $location,
                    ':address' => $address,
                    ':requirements' => $requirements
                ];

                $conn = getConnection();
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                
                $driveId = $conn->lastInsertId();

                // Send notifications to all donors and patients
                try {
                    require_once '../../includes/notification_templates.php';
                    require_once '../../includes/sim800c_sms.php';
                    
                    // Prepare blood drive data for notification template
                    $driveData = [
                        'title' => $title,
                        'date' => $date,
                        'time' => $time,
                        'location' => $location,
                        'address' => $address,
                        'requirements' => $requirements
                    ];
                    
                    $smsSentCount = 0;
                    $smsErrorCount = 0;
                    $notifCount = 0;
                    
                    // Get barangay name for location
                    $barangay = getRow("SELECT name FROM barangay_users WHERE id = ?", [$barangayId]);
                    $barangayName = $barangay['name'] ?? '';
                    if ($barangayName) {
                        $driveData['location'] = $location . ($location ? ', ' : '') . $barangayName;
                    }
                    
                    // Send notifications to all patients (in-app for all, SMS for those with phones)
                    $patients = executeQuery("SELECT id, name, phone FROM patient_users", []);
                    
                    if (!empty($patients) && is_array($patients)) {
                        foreach ($patients as $patient) {
                            $patientId = $patient['id'] ?? null;
                            $patientName = $patient['name'] ?? 'Patient';
                            $patientPhone = $patient['phone'] ?? '';
                            
                            if (!$patientId) continue;
                            
                            // Send notification with SMS using centralized helper
                            $notifResult = send_notification_with_sms(
                                $patientId,
                                'patient',
                                'blood_drive',
                                'New Blood Drive Scheduled',
                                $driveData,
                                'negrosfirst'
                            );
                            
                            if ($notifResult['notification_success']) {
                                $notifCount++;
                            }
                            
                            if ($notifResult['sms_success']) {
                                $smsSentCount++;
                            } elseif (!empty($notifResult['sms_error'])) {
                                $smsErrorCount++;
                            }
                        }
                    }
                    
                    // Send notifications to all donors (in-app for all, SMS for those with phones)
                    $donors = executeQuery("SELECT id, name, phone FROM donor_users", []);
                    
                    if (!empty($donors) && is_array($donors)) {
                        foreach ($donors as $donor) {
                            $donorId = $donor['id'] ?? null;
                            $donorName = $donor['name'] ?? 'Donor';
                            $donorPhone = $donor['phone'] ?? '';
                            
                            if (!$donorId) continue;
                            
                            // Send notification with SMS using centralized helper
                            $notifResult = send_notification_with_sms(
                                $donorId,
                                'donor',
                                'blood_drive',
                                'New Blood Drive Scheduled',
                                $driveData,
                                'negrosfirst'
                            );
                            
                            if ($notifResult['notification_success']) {
                                $notifCount++;
                            }
                            
                            if ($notifResult['sms_success']) {
                                $smsSentCount++;
                            } elseif (!empty($notifResult['sms_error'])) {
                                $smsErrorCount++;
                            }
                        }
                    }
                    
                    if (function_exists('secure_log')) {
                        secure_log("Blood drive notifications sent", [
                            'notifications' => $notifCount,
                            'sms_sent' => $smsSentCount,
                            'sms_failed' => $smsErrorCount
                        ]);
                    }
                    
                    // Store notification stats in session for display
                    $_SESSION['blood_drive_notifications'] = [
                        'in_app' => $notifCount,
                        'sms_sent' => $smsSentCount,
                        'sms_failed' => $smsErrorCount
                    ];
                    
                } catch (Exception $notifEx) {
                    // Log error but don't fail the blood drive creation
                    if (function_exists('secure_log')) {
                        secure_log("Error sending blood drive notifications", ['error' => substr($notifEx->getMessage(), 0, 200)]);
                    }
                }

                $success = true;
            } catch (Exception $e) {
                $error = "Failed to create blood drive: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'update') {
            // Handle update action
            $driveId = sanitize($_POST['drive_id']);
            $status = sanitize($_POST['status']);

            try {
                $query = "UPDATE blood_drives SET status = :status WHERE id = :id AND organization_type = 'negrosfirst' AND organization_id = :org_id";
                $params = [
                    ':status' => $status,
                    ':id' => $driveId,
                    ':org_id' => $negrosFirstId
                ];

                $conn = getConnection();
                $stmt = $conn->prepare($query);
                $stmt->execute($params);

                $success = true;
            } catch (Exception $e) {
                $error = "Failed to update blood drive: " . $e->getMessage();
            }
        }
    }
}

// Get all blood drives for this Negros First
$bloodDrives = executeQuery("
    SELECT bd.*, bu.name as barangay_name,
    (SELECT COUNT(*) FROM donor_appointments WHERE blood_drive_id = bd.id) as registered_donors
    FROM blood_drives bd
    JOIN barangay_users bu ON bd.barangay_id = bu.id
    WHERE bd.organization_type = 'negrosfirst'
    AND bd.organization_id = ?
    ORDER BY bd.date DESC
", [$negrosFirstId]);

// Get all barangays for the dropdown
$barangays = executeQuery("SELECT * FROM barangay_users ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

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
</head>
<style>
    :root {
        --primary-color: #1a365d;
        --secondary-color: #2d3748;
        --accent-color: #e53e3e;
        --success-color: #38a169;
        --warning-color: #d69e2e;
        --info-color: #3182ce;
        --light-bg: #f7fafc;
        --white: #ffffff;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-600: #4b5563;
        --gray-800: #1f2937;
        --border-radius: 12px;
        --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --box-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card { border: 1px solid var(--gray-200); border-radius: var(--border-radius); box-shadow: var(--box-shadow); }
    .card-header { border-bottom: 1px solid var(--gray-200); background: var(--white); }
    .table thead th { background: var(--gray-100); color: var(--gray-800); border-bottom: 2px solid var(--gray-200); }
    .badge { border-radius: 20px; padding: 0.35rem 0.6rem; }
    .modal-content { border-radius: var(--border-radius); box-shadow: var(--box-shadow-lg); }
    .modal-header, .modal-footer { background: var(--white); border-color: var(--gray-200); }
</style>

<body>
    <div class="dashboard-container">
        <!-- Include sidebar -->
        <?php include_once '../../includes/sidebar.php'; ?>

        <div class="dashboard-content">
            <div class="dashboard-header p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="h4 mb-0">Blood Drives Management</h2>
                    
                </div>
            </div>
            
            <!-- Notification Dropdown -->
            <?php
            // Load notification data
            $notifCount = 0; $notifList = [];
            try {
                $notifCount = getCount("SELECT COUNT(*) FROM notifications WHERE user_role = 'negrosfirst' AND is_read = 0");
                $notifList = executeQuery("SELECT id, title, message, created_at, is_read FROM notifications WHERE user_role='negrosfirst' ORDER BY created_at DESC LIMIT 10");
                if (!is_array($notifList)) { $notifList = []; }
            } catch (Exception $e) { /* ignore rendering errors */ }
            ?>
            <style>
                .nf-topbar { position: fixed; top: 10px; right: 16px; z-index: 1100; }
                .nf-dropdown { position: absolute; right: 0; top: 42px; width: 320px; display: none; }
                .nf-dropdown.show { display: block; }
                .nf-notif-item { white-space: normal; }
                @media (max-width: 991.98px) { .nf-topbar { top: 8px; right: 12px; } }
            </style>
            <div class="nf-topbar">
                <button id="nfBellBtn" class="btn btn-light position-relative shadow-sm">
                    <i class="bi bi-bell"></i>
                    <span id="nfBellBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: <?php echo ((int)$notifCount>0)?'inline':'none'; ?>;">
                        <?php echo (int)$notifCount; ?>
                    </span>
                </button>
                <div id="nfDropdown" class="nf-dropdown card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Notifications</span>
                        <a class="small text-decoration-none" href="../../dashboard/negrosfirst/notifications.php">View All</a>
                    </div>
                    <div id="nfList" class="list-group list-group-flush" style="max-height: 360px; overflow:auto;">
                        <?php if (!empty($notifList)): foreach ($notifList as $n): ?>
                            <div class="list-group-item nf-notif-item">
                                <div class="d-flex justify-content-between">
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($n['title']); ?></div>
                                    <div class="text-muted small"><?php echo date('M d, g:i A', strtotime($n['created_at'])); ?></div>
                                </div>
                                <div class="small text-muted"><?php echo htmlspecialchars($n['message']); ?></div>
                            </div>
                        <?php endforeach; else: ?>
                            <div class="list-group-item text-center text-muted py-3">No notifications</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="dashboard-main p-3">
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Blood drive created successfully!
                        <?php if (isset($_SESSION['blood_drive_notifications'])): 
                            $notifStats = $_SESSION['blood_drive_notifications'];
                        ?>
                            <br><small>
                                <i class="bi bi-bell me-1"></i>
                                Notifications sent: <?php echo $notifStats['in_app']; ?> in-app notification(s), 
                                <?php echo $notifStats['sms_sent']; ?> SMS message(s) sent successfully.
                                <?php if ($notifStats['sms_failed'] > 0): ?>
                                    <span class="text-warning"><?php echo $notifStats['sms_failed']; ?> SMS failed.</span>
                                <?php endif; ?>
                            </small>
                        <?php 
                            unset($_SESSION['blood_drive_notifications']);
                        endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Create New Blood Drive Button -->
                <div class="mb-4 d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDriveModal">
                        <i class="bi bi-plus-circle me-2"></i>Create New Blood Drive
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="printReport()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="exportDrivesCsv">
                        <i class="bi bi-filetype-csv me-1"></i> Export CSV
                    </button>
                </div>

                <!-- Blood Drives List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="drivesTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Barangay</th>
                                        
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bloodDrives as $drive): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($drive['title']); ?></td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($drive['date'])); ?><br>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($drive['start_time'] ?? '00:00:00')); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($drive['location'] ?? 'Not specified'); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($drive['address'] ?? 'Not specified'); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($drive['barangay_name']); ?></td>
                                           
                                            <td>
                                                <span class="badge bg-<?php echo $drive['status'] === 'Scheduled' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $drive['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1 view-drive-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewDriveModal"
                                                        data-drive-id="<?php echo $drive['id']; ?>"
                                                        data-drive-title="<?php echo htmlspecialchars($drive['title'], ENT_QUOTES); ?>"
                                                        data-drive-date="<?php echo htmlspecialchars($drive['date']); ?>"
                                                        data-drive-time="<?php echo htmlspecialchars($drive['start_time'] ?? '00:00:00'); ?>"
                                                        data-drive-end-time="<?php echo htmlspecialchars($drive['end_time'] ?? '00:00:00'); ?>"
                                                        data-drive-location="<?php echo htmlspecialchars($drive['location'] ?? 'Not specified', ENT_QUOTES); ?>"
                                                        data-drive-address="<?php echo htmlspecialchars($drive['address'] ?? 'Not specified', ENT_QUOTES); ?>"
                                                        data-drive-barangay="<?php echo htmlspecialchars($drive['barangay_name'], ENT_QUOTES); ?>"
                                                        data-drive-status="<?php echo htmlspecialchars($drive['status']); ?>"
                                                        data-drive-requirements="<?php echo htmlspecialchars($drive['requirements'] ?? '', ENT_QUOTES); ?>"
                                                        data-drive-registered="<?php echo htmlspecialchars($drive['registered_donors'] ?? 0); ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#updateStatusModal"
                                                        data-drive-id="<?php echo $drive['id']; ?>"
                                                        data-drive-status="<?php echo $drive['status']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Blood Drive Modal -->
    <div class="modal fade" id="createDriveModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Blood Drive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="title" class="form-label">Drive Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-6">
                                <label for="barangay_id" class="form-label">Barangay</label>
                                <select class="form-select" id="barangay_id" name="barangay_id" required>
                                    <option value="">Select Barangay</option>
                                    <?php foreach ($barangays as $barangay): ?>
                                        <option value="<?php echo $barangay['id']; ?>">
                                            <?php echo htmlspecialchars($barangay['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="time" name="time" required>
                            </div>
                            <div class="col-md-4">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">Venue Name</label>
                            <input type="text" class="form-control" id="location" name="location" required>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Location Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="requirements" class="form-label">Requirements</label>
                            <textarea class="form-control" id="requirements" name="requirements" rows="3" 
                                      placeholder="List any specific requirements for donors..."></textarea>
                        </div>


                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Create Blood Drive
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Drive Details Modal -->
    <div class="modal fade" id="viewDriveModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Drive Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="driveDetails">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Drive Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="drive_id" id="update_drive_id">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Scheduled">Scheduled</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle view drive details
        const viewDriveModal = document.getElementById('viewDriveModal');
        if (viewDriveModal) {
            viewDriveModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                // Get drive data from button attributes
                const driveId = button.getAttribute('data-drive-id');
                const title = button.getAttribute('data-drive-title') || 'N/A';
                const date = button.getAttribute('data-drive-date') || '';
                const time = button.getAttribute('data-drive-time') || '00:00:00';
                const endTime = button.getAttribute('data-drive-end-time') || '00:00:00';
                const location = button.getAttribute('data-drive-location') || 'Not specified';
                const address = button.getAttribute('data-drive-address') || 'Not specified';
                const barangay = button.getAttribute('data-drive-barangay') || 'N/A';
                const status = button.getAttribute('data-drive-status') || 'N/A';
                const requirements = button.getAttribute('data-drive-requirements') || '';
                const registeredDonors = button.getAttribute('data-drive-registered') || '0';
                
                // Format date and time
                let formattedDate = 'N/A';
                let formattedTime = '';
                let formattedEndTime = '';
                if (date) {
                    try {
                        const dateObj = new Date(date + 'T00:00:00');
                        formattedDate = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                    } catch (e) {
                        formattedDate = date;
                    }
                }
                
                if (time && time !== '00:00:00') {
                    try {
                        const timeStr = time.includes(':') ? time : time.substring(0, 5);
                        const [hours, minutes] = timeStr.split(':');
                        const hour12 = parseInt(hours) % 12 || 12;
                        const ampm = parseInt(hours) >= 12 ? 'PM' : 'AM';
                        formattedTime = `${hour12}:${minutes.padStart(2, '0')} ${ampm}`;
                    } catch (e) {
                        formattedTime = time;
                    }
                }
                
                if (endTime && endTime !== '00:00:00') {
                    try {
                        const timeStr = endTime.includes(':') ? endTime : endTime.substring(0, 5);
                        const [hours, minutes] = timeStr.split(':');
                        const hour12 = parseInt(hours) % 12 || 12;
                        const ampm = parseInt(hours) >= 12 ? 'PM' : 'AM';
                        formattedEndTime = `${hour12}:${minutes.padStart(2, '0')} ${ampm}`;
                    } catch (e) {
                        formattedEndTime = endTime;
                    }
                }
                
                // Build modal content
                let modalContent = `
                    <div class="mb-3">
                        <h5 class="mb-3">${escapeHtml(title)}</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <i class="bi bi-calendar-event text-success me-2"></i>
                                    <strong>Date:</strong> ${escapeHtml(formattedDate)}
                                </p>
                                ${formattedTime ? `
                                <p class="mb-2">
                                    <i class="bi bi-clock text-primary me-2"></i>
                                    <strong>Start Time:</strong> ${escapeHtml(formattedTime)}
                                </p>
                                ` : ''}
                                ${formattedEndTime ? `
                                <p class="mb-2">
                                    <i class="bi bi-clock-fill text-primary me-2"></i>
                                    <strong>End Time:</strong> ${escapeHtml(formattedEndTime)}
                                </p>
                                ` : ''}
                                <p class="mb-2">
                                    <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                                    <strong>Location:</strong> ${escapeHtml(location)}
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <i class="bi bi-pin-map-fill text-primary me-2"></i>
                                    <strong>Address:</strong> ${escapeHtml(address)}
                                </p>
                                <p class="mb-2">
                                    <i class="bi bi-building text-info me-2"></i>
                                    <strong>Barangay:</strong> ${escapeHtml(barangay)}
                                </p>
                                <p class="mb-2">
                                    <i class="bi bi-flag text-warning me-2"></i>
                                    <strong>Status:</strong> 
                                    <span class="badge bg-${status === 'Scheduled' ? 'success' : status === 'Completed' ? 'primary' : 'secondary'}">
                                        ${escapeHtml(status)}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <p class="mb-2">
                                <i class="bi bi-people-fill text-info me-2"></i>
                                <strong>Registered Donors:</strong> ${escapeHtml(registeredDonors)}
                            </p>
                        </div>
                        ${requirements ? `
                            <div class="mt-3 p-3 bg-light rounded">
                                <h6 class="mb-2">
                                    <i class="bi bi-list-check me-2"></i>Requirements:
                                </h6>
                                <p class="mb-0">${escapeHtml(requirements).replace(/\n/g, '<br>')}</p>
                            </div>
                        ` : ''}
                    </div>
                `;
                
                document.getElementById('driveDetails').innerHTML = modalContent;
            });
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Handle update status
        const updateStatusModal = document.getElementById('updateStatusModal');
        if (updateStatusModal) {
            updateStatusModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const driveId = button.getAttribute('data-drive-id');
                const currentStatus = button.getAttribute('data-drive-status');
                
                document.getElementById('update_drive_id').value = driveId;
                document.getElementById('status').value = currentStatus;
            });
        }
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Export CSV for drives
        const exportBtn = document.getElementById('exportDrivesCsv');
        const table = document.getElementById('drivesTable');
        if (exportBtn && table) {
            exportBtn.addEventListener('click', function() {
                const rows = Array.from(table.querySelectorAll('tr')).map(tr =>
                    Array.from(tr.querySelectorAll('th,td'))
                        .slice(0, 5) // Title, Date&Time, Location, Barangay, Status
                        .map(td => '"' + (td.innerText || '').replace(/"/g, '""') + '"')
                        .join(',')
                );
                const csv = rows.join('\r\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'blood_drives.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        }
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Title Case function: Capitalize first letter of each word, allow only 1 space between words
        function toTitleCase(str, trimSpaces) {
            if (!str) return '';
            
            // Preserve trailing spaces during typing
            const hasTrailingSpace = str.endsWith(' ') && trimSpaces === false;
            const hasLeadingSpace = str.startsWith(' ');
            
            // Only collapse multiple spaces (2 or more) to single space - preserve single spaces
            str = str.replace(/\s{2,}/g, ' ');
            
            // Only trim if explicitly requested (for blur event, not during typing)
            if (trimSpaces !== false) {
                str = str.trim();
            }
            
            // Split by single space and capitalize first letter of each word
            const words = str.split(' ').filter(function(word) {
                return word.length > 0; // Remove empty strings from split
            }).map(function(word) {
                // If word starts with a number, don't apply title case (keep as is)
                if (/^\d/.test(word)) {
                    return word;
                }
                // Capitalize first letter, lowercase the rest
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            });
            
            let result = words.join(' '); // Join with single space
            
            // Restore trailing space if it existed (during typing)
            if (hasTrailingSpace && result.length > 0) {
                result += ' ';
            }
            
            // Restore leading space if it existed (during typing)
            if (hasLeadingSpace && trimSpaces === false && result.length > 0) {
                result = ' ' + result;
            }
            
            return result;
        }

        // Apply to all text inputs and textareas
        const textInputs = document.querySelectorAll('input[type="text"], textarea');
        textInputs.forEach(function(input) {
            // Check if this is the requirements field (allow numbers)
            const isRequirements = input.id === 'requirements' || input.name === 'requirements';
            
            input.addEventListener('input', function(e) {
                const cursorPos = this.selectionStart;
                let value = this.value;
                const originalLength = value.length;
                
                // Step 1: Remove invalid characters
                // Requirements field allows numbers, other fields don't
                if (isRequirements) {
                    value = value.replace(/[^a-zA-Z0-9\s.,!?()-]/g, ''); // Allow numbers for requirements
                } else {
                    value = value.replace(/[^a-zA-Z\s.,!?()-]/g, ''); // No numbers for other fields
                }
                
                // Step 2: Collapse multiple spaces to single (ALLOWS SINGLE SPACES)
                // Only replace 2+ consecutive spaces, single spaces remain untouched
                value = value.replace(/\s{2,}/g, ' ');
                
                // Step 3: Apply Title Case formatting (preserves single spaces)
                // For requirements, we still apply title case but numbers are preserved
                // Don't trim during typing to allow trailing spaces
                const formatted = toTitleCase(value, false);
                
                // Only update if value actually changed
                if (this.value !== formatted) {
                    this.value = formatted;
                    
                    // Calculate cursor adjustment
                    const lengthDiff = formatted.length - originalLength;
                    let newPos = Math.max(0, Math.min(cursorPos + lengthDiff, formatted.length));
                    
                    // Restore cursor position
                    setTimeout(() => {
                        this.setSelectionRange(newPos, newPos);
                    }, 0);
                }
            });
            
            // Final formatting on blur - only trim leading/trailing spaces
            input.addEventListener('blur', function() {
                // Requirements field allows numbers, other fields don't
                if (isRequirements) {
                    let value = this.value
                        .replace(/[^a-zA-Z0-9\s.,!?()-]/g, '')  // Remove invalid chars (allow numbers)
                        .replace(/\s{2,}/g, ' ')                // Collapse 2+ spaces to 1 (keeps single spaces)
                        .trim();                                 // Only trim start/end
                    this.value = toTitleCase(value);
                } else {
                    let value = this.value
                        .replace(/[^a-zA-Z\s.,!?()-]/g, '')  // Remove invalid chars
                        .replace(/\s{2,}/g, ' ')             // Collapse 2+ spaces to 1 (keeps single spaces)
                        .trim();                              // Only trim start/end
                    this.value = toTitleCase(value);
                }
            });
        });

        // Numbers only for number fields
        const numberInputs = document.querySelectorAll('input[type="number"]');
        numberInputs.forEach(function(input) {
            input.addEventListener('input', function() {
                // Remove any non-digit characters (spaces not allowed)
                this.value = this.value.replace(/[^\d]/g, '');
            });
        });

        // Fix notification dropdown - simple and direct approach
        const bellBtn = document.getElementById('nfBellBtn');
        const dropdown = document.getElementById('nfDropdown');
        
        if (bellBtn && dropdown) {
            console.log('Notification dropdown elements found');
            
            // Bell click handler
            bellBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Bell clicked');
                
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    console.log('Closing dropdown');
                } else {
                    dropdown.classList.add('show');
                    console.log('Opening dropdown');
                }
            });
            
            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!bellBtn.contains(e.target) && !dropdown.contains(e.target)) {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                        console.log('Closing dropdown - outside click');
                    }
                }
            });
            
            // Close on notification item click
            dropdown.addEventListener('click', function(e) {
                if (e.target.closest('.list-group-item') || e.target.closest('a')) {
                    dropdown.classList.remove('show');
                    console.log('Closing dropdown - notification click');
                }
            });
            
            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    console.log('Closing dropdown - Escape key');
                }
            });
        } else {
            console.log('Notification dropdown elements not found');
        }
    });

    // Auto-hide feedback messages after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                if (alert && alert.parentNode) {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 500);
                }
            }, 5000);
        });
    });
    </script>

    <!-- Universal Print Script -->
    <script src="../../assets/js/universal-print.js"></script>
</body>
</html>