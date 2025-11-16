<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

$isDashboard = true; // Enable notification dropdown
$pageTitle = "Appointments - Negros First";

// Set organization ID for Negros First
$negrosFirstId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment_status'])) {
    $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
    if ($appointment_id <= 0 && isset($_GET['aid'])) {
        $appointment_id = (int)$_GET['aid'];
    }
    $status = trim($_POST['status']);
    // Normalize common variants to match allowed values
    if (strcasecmp($status, 'approved') === 0) { $status = 'Approved'; }
    if (strcasecmp($status, 'scheduled') === 0) { $status = 'Scheduled'; }
    if (strcasecmp($status, 'completed') === 0) { $status = 'Completed'; }
    if (strcasecmp($status, 'rejected') === 0) { $status = 'Rejected'; }
    if (strcasecmp($status, 'no show') === 0 || strcasecmp($status, 'no_show') === 0) { $status = 'No Show'; }
    $notes = trim($_POST['status_notes']);
    $collected_units = isset($_POST['collected_units']) ? (int)$_POST['collected_units'] : 1;
    if ($collected_units < 1) { $collected_units = 1; }
    if ($collected_units > 2) { $collected_units = 2; }

    // If status is 'Approved', set to 'Scheduled'
    if ($status === 'Approved') {
        $status = 'Scheduled';
    }

    // Update the appointment in the database (with organization check for security)
    $update_sql = "UPDATE donor_appointments SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n', ?), updated_at = NOW() WHERE id = ? AND organization_type = 'negrosfirst' AND organization_id = ?";
    $status_note = "[" . date('Y-m-d H:i:s') . "] Status changed to $status: $notes";
    $updated = updateRow($update_sql, [$status, $status_note, $appointment_id, $negrosFirstId]);

    if ($updated) {
        // Get appointment and donor information for notification and follow-up actions
        $get_appt_sql = "SELECT a.donor_id, a.appointment_date, a.appointment_time, a.organization_type, a.organization_id, a.location, d.blood_type 
             FROM donor_appointments a
             JOIN donor_users d ON a.donor_id = d.id
                        WHERE a.id = ? AND a.organization_type = 'negrosfirst' AND a.organization_id = ?";
        $appt = getRow($get_appt_sql, [$appointment_id, $negrosFirstId]);

        if ($appt) {
            // Use professional notification templates with centralized helper
            require_once '../../includes/notification_templates.php';
            
            // Prepare notification data based on status
            $notification_title = '';
            $notification_type = 'appointment';
            $notification_data = [
                'date' => $appt['appointment_date'],
                'time' => $appt['appointment_time'],
                'location' => $appt['location'] ?? '',
                'notes' => $notes ?? ''
            ];
            
            switch($status) {
                case 'Scheduled':
                    $notification_title = 'Blood Donation Appointment Scheduled';
                    $notification_type = 'appointment';
                    $notification_data['status'] = 'Scheduled';
                    break;
                case 'Rejected':
                    $notification_title = 'Donation Appointment Rejected';
                    $notification_type = 'rejected';
                    $notification_data['type'] = 'appointment';
                    $notification_data['reason'] = $notes ?? '';
                    break;
                case 'Completed':
                    $notification_title = 'Donation Completed';
                    $notification_type = 'completed';
                    $notification_data['type'] = 'donation';
                    $notification_data['units'] = $collected_units ?? 1;
                    $notification_data['blood_type'] = $appt['blood_type'] ?? '';
                    break;
                case 'No Show':
                    $notification_title = 'Missed Appointment';
                    $notification_type = 'rejected';
                    $notification_data['type'] = 'appointment';
                    $notification_data['reason'] = 'No show - appointment was missed. Please reschedule if you would still like to donate.';
                    break;
            }

            if ($notification_title) {
                // Send notification with SMS using centralized helper
                send_notification_with_sms(
                    $appt['donor_id'],
                    'donor',
                    $notification_type,
                    $notification_title,
                    $notification_data,
                    'negrosfirst'
                );
            }
        }

        // If status is Completed, update donor record, create donation entry, and add inventory unit
        if ($status === 'Completed' && $appt) {
            // Update donor's last donation date and count
            updateRow(
                "UPDATE donor_users SET last_donation_date = ?, donation_count = donation_count + 1, updated_at = NOW() WHERE id = ?",
                [$appt['appointment_date'], $appt['donor_id']]
            );

            // Insert donation record (use collected units)
            insertRow(
                "INSERT INTO donations (donor_id, donation_date, units, blood_type, status, organization_type, organization_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'Completed', ?, ?, NOW(), NOW())",
                [$appt['donor_id'], $appt['appointment_date'], $collected_units, $appt['blood_type'], $appt['organization_type'], $appt['organization_id']]
            );

            // Add to blood inventory with 35-day expiry from appointment completion date
            insertRow(
                "INSERT INTO blood_inventory (organization_type, organization_id, blood_type, units, status, source, expiry_date, created_at)
                 VALUES (?, ?, ?, ?, 'Available', 'Appointment', DATE_ADD(?, INTERVAL 35 DAY), NOW())",
                [$appt['organization_type'], $appt['organization_id'], $appt['blood_type'], $collected_units, $appt['appointment_date']]
            );
        }

        // Success message or redirect
        // Customize message based on status
        $statusMessages = [
            'Scheduled' => 'Appointment has been scheduled successfully!',
            'Completed' => 'Appointment marked as completed! Blood inventory has been increased. <a href="enhanced-inventory.php" class="alert-link">View Inventory</a>',
            'Rejected' => 'Appointment has been rejected.',
            'No Show' => 'Appointment has been marked as No Show.',
            'Cancelled' => 'Appointment has been cancelled.'
        ];
        
        $_SESSION['message'] = $statusMessages[$status] ?? 'Appointment status updated successfully!';
        $_SESSION['message_type'] = 'success';
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;

    } else {
        // Error message
        $_SESSION['message'] = 'Failed to update appointment status. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Get appointments for Negros First with full donor information
$appointments_query = "SELECT a.*, 
                      d.id as donor_id,
                      d.name as donor_name,
                      d.blood_type,
                      d.phone,
                      d.email,
                      d.gender,
                      d.date_of_birth,
                      d.address,
                      d.city,
                      d.last_donation_date,
                      d.donation_count,
                      d.created_at AS donor_registration_date,
                      (SELECT status FROM donor_interviews WHERE appointment_id = a.id ORDER BY created_at DESC LIMIT 1) as interview_status,
                      (SELECT responses_json FROM donor_interviews WHERE appointment_id = a.id ORDER BY created_at DESC LIMIT 1) as interview_responses,
                      (SELECT created_at FROM donor_interviews WHERE appointment_id = a.id ORDER BY created_at DESC LIMIT 1) as interview_created_at
                      FROM donor_appointments a
                      JOIN donor_users d ON a.donor_id = d.id
                      WHERE a.organization_type = 'negrosfirst'
                      AND a.organization_id = ?
                      ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$appointments_result = executeQuery($appointments_query, [$negrosFirstId]);

// Ensure it's an array
if (!is_array($appointments_result)) {
    $appointments_result = [];
}

// Handle session messages for feedback
$message = '';
$alertType = 'success';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $alertType = $_SESSION['message_type'] ?? 'success';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Determine the correct path for CSS files
$basePath = '';
if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
    $basePath = '../../';
}

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

    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

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

    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
</head>
<body>
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

<div class="dashboard-container">
    <?php include_once '../../includes/sidebar.php'; ?>
    <div class="dashboard-content">
        <div class="dashboard-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h4 mb-0">Appointments</h2>
            </div>
        </div>
        
        <!-- Controls below header -->
        <div class="p-3 pb-0">
            <div class="d-flex gap-2 justify-content-end">
                <select id="statusFilter" class="form-select" style="max-width:200px;">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Scheduled">Scheduled</option>
                    <option value="Completed">Completed</option>
                    <option value="No Show">No Show</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <button type="button" class="btn btn-outline-secondary" onclick="printAppointmentsReport()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Feedback Messages -->
        <?php if ($message): ?>
            <div class="p-3 pb-0">
                <div class="alert alert-<?php echo htmlspecialchars($alertType); ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php 
                        echo match($alertType) {
                            'success' => 'check-circle-fill',
                            'danger' => 'exclamation-triangle-fill',
                            'warning' => 'exclamation-circle-fill',
                            'info' => 'info-circle-fill',
                            default => 'check-circle-fill'
                        };
                    ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
        
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
            .nf-topbar { position: fixed; top: 15px; right: 20px; z-index: 1100; }
            .nf-dropdown { position: absolute; right: 0; top: 42px; width: 320px; display: none; }
            .nf-dropdown.show { display: block; }
            .nf-notif-item { white-space: normal; }
            @media (max-width: 991.98px) { .nf-topbar { top: 12px; right: 15px; } }
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
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Upcoming Appointments</h5>
                    <div class="table-responsive">
                        <table class="table table-hover" id="appointmentsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Donor</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($appointments_result && count($appointments_result) > 0): ?>
                                    <?php foreach ($appointments_result as $appointment): ?>
                                    <tr data-appt-id="<?php echo (int)$appointment['id']; ?>">
                                            <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                            <td><?php echo $appointment['donor_name']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($appointment['status']) {
                                                        'Scheduled' => 'primary',
                                                        'Completed' => 'success',
                                                        'Cancelled' => 'danger',
                                                        'No Show' => 'warning',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo $appointment['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#donorDetailsModal"
                                                            data-id="<?php echo $appointment['id']; ?>"
                                                            data-donor-id="<?php echo $appointment['donor_id']; ?>"
                                                            data-donor-name="<?php echo htmlspecialchars($appointment['donor_name'], ENT_QUOTES); ?>"
                                                            data-blood-type="<?php echo htmlspecialchars($appointment['blood_type'] ?? '', ENT_QUOTES); ?>"
                                                            data-gender="<?php echo htmlspecialchars($appointment['gender'] ?? '', ENT_QUOTES); ?>"
                                                            data-dob="<?php echo htmlspecialchars($appointment['date_of_birth'] ?? '', ENT_QUOTES); ?>"
                                                            data-phone="<?php echo htmlspecialchars($appointment['phone'] ?? '', ENT_QUOTES); ?>"
                                                            data-email="<?php echo htmlspecialchars($appointment['email'] ?? '', ENT_QUOTES); ?>"
                                                            data-address="<?php echo htmlspecialchars($appointment['address'] ?? '', ENT_QUOTES); ?>"
                                                            data-city="<?php echo htmlspecialchars($appointment['city'] ?? '', ENT_QUOTES); ?>"
                                                            data-registered="<?php echo htmlspecialchars($appointment['donor_registration_date'] ?? '', ENT_QUOTES); ?>"
                                                            data-donation-count="<?php echo htmlspecialchars($appointment['donation_count'] ?? 0); ?>"
                                                            data-last-donation="<?php echo htmlspecialchars($appointment['last_donation_date'] ?? '', ENT_QUOTES); ?>"
                                                            data-appt-status="<?php echo htmlspecialchars($appointment['status'] ?? '', ENT_QUOTES); ?>"
                                                            data-appt-date="<?php echo htmlspecialchars(date('M d, Y', strtotime($appointment['appointment_date'])), ENT_QUOTES); ?>"
                                                            data-appt-time="<?php echo htmlspecialchars(date('h:i A', strtotime($appointment['appointment_time'])), ENT_QUOTES); ?>"
                                                            data-location="<?php echo htmlspecialchars($appointment['location'] ?? '', ENT_QUOTES); ?>"
                                                            data-notes="<?php echo htmlspecialchars($appointment['notes'] ?? '', ENT_QUOTES); ?>"
                                                            data-interview-status="<?php echo htmlspecialchars($appointment['interview_status'] ?? '', ENT_QUOTES); ?>"
                                                            data-interview-responses="<?php echo htmlspecialchars($appointment['interview_responses'] ?? '', ENT_QUOTES); ?>"
                                                            data-interview-created="<?php echo htmlspecialchars($appointment['interview_created_at'] ?? '', ENT_QUOTES); ?>"
                                                            title="View Donor Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if ($appointment['status'] === 'Scheduled'): ?>
                                                        <button type="button" class="btn btn-primary btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#updateStatusModal"
                                                            data-id="<?php echo $appointment['id']; ?>"
                                                            data-donor="<?php echo $appointment['donor_name']; ?>"
                                                            data-status="Completed"
                                                            data-current-status="<?php echo $appointment['status']; ?>">
                                                        <i class="bi bi-check-circle"></i> Complete
                                                    </button>
                                                    <button type="button" class="btn btn-warning btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#updateStatusModal"
                                                            data-id="<?php echo $appointment['id']; ?>"
                                                            data-donor="<?php echo $appointment['donor_name']; ?>"
                                                            data-status="No Show"
                                                            data-current-status="<?php echo $appointment['status']; ?>">
                                                        <i class="bi bi-person-x"></i> No Show
                                                    </button>
                                                <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No appointments found.</td>
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

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="updateStatusModalLabel">Update Appointment Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="appointment_id" name="appointment_id">

                    <div class="mb-3">
                        <label class="form-label" for="modal_donor_name">Donor</label>
                        <input type="text" class="form-control" id="modal_donor_name" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <!-- Options will be populated by JavaScript -->
                        </select>
                    </div>

                    <div class="mb-3" id="units_collected_group" style="display:none;">
                        <label for="collected_units" class="form-label">Units Collected</label>
                        <input type="number" step="1" min="1" max="2" value="1" class="form-control" id="collected_units" name="collected_units">
                        <div class="form-text">Typical whole blood donation yields 1 unit. Set to 2 only if applicable.</div>
                    </div>

                    <div class="mb-3">
                        <label for="status_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="status_notes" name="status_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_appointment_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Donor Details Modal -->
<div class="modal fade" id="donorDetailsModal" tabindex="-1" aria-labelledby="donorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="donorDetailsModalLabel">Donor Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2">Personal Information</h6>
                        <div id="personalInfo">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Donation History -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2">Donation History</h6>
                        <div id="donationHistory">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Interview Details -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2">Interview Details</h6>
                        <div id="interviewDetails">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Appointment Schedule -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2">Appointment Schedule</h6>
                        <div id="appointmentSchedule">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="donorModalApproveBtn" class="btn btn-success d-none">
                    <i class="bi bi-check-circle me-1"></i>Approve
                </button>
                <button type="button" id="donorModalRejectBtn" class="btn btn-danger d-none">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle update status modal
        const updateStatusModal = document.getElementById('updateStatusModal');
        if (updateStatusModal) {
            updateStatusModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;

                // Get data attributes from the button
                const id = button.getAttribute('data-id');
                const donor = button.getAttribute('data-donor');
                const status = button.getAttribute('data-status'); // intended action
                const currentStatus = button.getAttribute('data-current-status'); // actual current status

                // Try button data-id, else fallback to row's data-appt-id
                let resolvedId = id;
                if (!resolvedId) {
                    const row = button.closest('tr');
                    if (row && row.dataset.apptId) {
                        resolvedId = row.dataset.apptId;
                    }
                }
                // Set values in the form
                const hiddenIdInput = document.getElementById('appointment_id');
                if (hiddenIdInput) {
                    hiddenIdInput.value = resolvedId || '';
                }
                // Keep a fallback copy on the modal element
                updateStatusModal.dataset.appointmentId = resolvedId || '';
                // Also append the ID to the form action as a fallback query param
                const modalForm = updateStatusModal.querySelector('form');
                if (modalForm) {
                    const baseAction = window.location.pathname;
                    const url = new URL(window.location.origin + baseAction);
                    if (resolvedId) { url.searchParams.set('aid', resolvedId); }
                    modalForm.setAttribute('action', url.pathname + url.search);
                }
                document.getElementById('modal_donor_name').value = donor;

                // Update status dropdown based on current status
                const statusSelect = document.getElementById('status');
                statusSelect.innerHTML = ''; // Clear existing options
                let options = '';
                if (currentStatus === 'Pending') {
                    options = `
                        <option value="Scheduled">Scheduled</option>
                        <option value="Rejected">Reject</option>
                    `;
                } else if (currentStatus === 'Scheduled') {
                    options = `
                        <option value="Completed">Completed</option>
                        <option value="No Show">No Show</option>
                    `;
                }
                statusSelect.innerHTML = options;
                // Pre-select the intended action if available
                if (status) {
                    statusSelect.value = status;
                }

                // Update modal title and notes label based on intended action
                const modalTitle = updateStatusModal.querySelector('.modal-title');
                const notesLabel = updateStatusModal.querySelector('label[for="status_notes"]');
                const notesInput = document.getElementById('status_notes');

                // Show/hide units collected field
                const unitsCollectedGroup = document.getElementById('units_collected_group');
                if (unitsCollectedGroup) {
                    unitsCollectedGroup.style.display = (status === 'Completed') ? 'block' : 'none';
                }

                switch(status) {
                    case 'Approved':
                        modalTitle.textContent = 'Schedule Donation Appointment';
                        notesLabel.textContent = 'Additional Instructions (Optional)';
                        notesInput.placeholder = 'Add any special instructions for the donor';
                        notesInput.required = false;
                        break;
                    case 'Rejected':
                        modalTitle.textContent = 'Reject Donation Appointment';
                        notesLabel.textContent = 'Reason for Rejection';
                        notesInput.placeholder = 'Please provide a reason for rejecting this appointment';
                        notesInput.required = true;
                        break;
                    case 'Completed':
                        modalTitle.textContent = 'Complete Donation';
                        notesLabel.textContent = 'Additional Notes (Optional)';
                        notesInput.placeholder = 'Add any notes about the donation';
                        notesInput.required = false;
                        break;
                    case 'No Show':
                        modalTitle.textContent = 'Mark as No Show';
                        notesLabel.textContent = 'Notes (Optional)';
                        notesInput.placeholder = 'Add any notes about the missed appointment';
                        notesInput.required = false;
                        break;
                }
            });

            // As a final safety, on shown ensure the ID is present
            updateStatusModal.addEventListener('shown.bs.modal', function() {
                const hiddenIdInput = document.getElementById('appointment_id');
                if (!hiddenIdInput.value) {
                    const fallbackId = updateStatusModal.dataset.appointmentId || '';
                    if (fallbackId) {
                        hiddenIdInput.value = fallbackId;
                    }
                }
            });

            // Ensure hidden appointment_id exists on submit
            const modalForm = updateStatusModal.querySelector('form');
            if (modalForm) {
                modalForm.addEventListener('submit', function(e) {
                    const hiddenId = document.getElementById('appointment_id');
                    if (!hiddenId || !hiddenId.value) {
                        const fallbackId = updateStatusModal.dataset.appointmentId || '';
                        if (fallbackId && hiddenId) {
                            hiddenId.value = fallbackId;
                        } else {
                            e.preventDefault();
                            alert('No appointment selected. Please close the modal and click an action button again.');
                            return false;
                        }
                    }
                });
            }
        }
        // Handle donor details modal
        const donorDetailsModal = document.getElementById('donorDetailsModal');
        if (donorDetailsModal) {
            donorDetailsModal.addEventListener('show.bs.modal', function(event) {
                const btn = event.relatedTarget;
                
                const personalInfo = document.getElementById('personalInfo');
                const donationHistory = document.getElementById('donationHistory');
                const interviewDetails = document.getElementById('interviewDetails');
                const appointmentSchedule = document.getElementById('appointmentSchedule');
                
                // Helper function to escape HTML
                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                // Get data from button attributes
                const donorName = btn.getAttribute('data-donor-name') || '';
                const bloodType = btn.getAttribute('data-blood-type') || '';
                const gender = btn.getAttribute('data-gender') || '';
                const dob = btn.getAttribute('data-dob') || '';
                const phone = btn.getAttribute('data-phone') || '';
                const email = btn.getAttribute('data-email') || '';
                const address = btn.getAttribute('data-address') || '';
                const city = btn.getAttribute('data-city') || '';
                const registered = btn.getAttribute('data-registered') || '';
                const donationCount = btn.getAttribute('data-donation-count') || '0';
                const lastDonation = btn.getAttribute('data-last-donation') || 'Never';
                const apptStatus = btn.getAttribute('data-appt-status') || '';
                const apptDate = btn.getAttribute('data-appt-date') || '';
                const apptTime = btn.getAttribute('data-appt-time') || '';
                const location = btn.getAttribute('data-location') || '';
                const notes = btn.getAttribute('data-notes') || 'No notes available';
                const interviewStatus = btn.getAttribute('data-interview-status') || '';
                const interviewResponses = btn.getAttribute('data-interview-responses') || '';
                const interviewCreated = btn.getAttribute('data-interview-created') || '';
                
                // Format registration date
                let formattedRegistered = '';
                if (registered) {
                    try {
                        formattedRegistered = new Date(registered).toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        });
                    } catch (e) {
                        formattedRegistered = registered;
                    }
                }
                
                // Populate Personal Information
                personalInfo.innerHTML = `
                    <p><strong>Name:</strong> ${escapeHtml(donorName)}</p>
                    <p><strong>Blood Type:</strong> ${escapeHtml(bloodType)}</p>
                    <p><strong>Gender:</strong> ${escapeHtml(gender)}</p>
                    <p><strong>Date of Birth:</strong> ${escapeHtml(dob)}</p>
                    <p><strong>Phone:</strong> ${escapeHtml(phone)}</p>
                    <p><strong>Email:</strong> ${escapeHtml(email)}</p>
                    <p><strong>Address:</strong> ${escapeHtml(address + (city ? ', ' + city : ''))}</p>
                    <p><strong>Registration Date:</strong> ${escapeHtml(formattedRegistered)}</p>
                `;
                
                // Populate Donation History
                donationHistory.innerHTML = `
                    <p><strong>Total Donations:</strong> ${escapeHtml(donationCount)}</p>
                    <p><strong>Last Donation:</strong> ${escapeHtml(lastDonation === 'Never' ? 'Never' : lastDonation)}</p>
                `;
                
                // Populate Interview Details
                if (!interviewResponses) {
                    interviewDetails.innerHTML = `<p><em>No interview data available.</em></p>`;
                } else {
                    // Interview questions mapping
                    const interviewQuestions = {
                        'q1': 'Do you feel well and healthy today?',
                        'q2': 'Have you ever been refused as a blood donor or told not to donate blood for any reasons?',
                        'q3': 'Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?',
                        'q4': 'Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?',
                        'q5': 'Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?',
                        'q6': 'In the last 3 DAYS have you taken aspirin?',
                        'q7': 'In the past 3 MONTHS have you donated whole blood, platelets or plasma?',
                        'q8': 'In the past 4 WEEKS have you taken any medications and/or vaccinations?',
                        'q9': 'Been to any places in the Philippines or countries infected with ZIKA Virus?',
                        'q10': 'Had sexual contact with a person who was confirmed to have ZIKA Virus infection?',
                        'q11': 'Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?',
                        'q12': 'Received blood, blood products and/or had tissue/organ transplant or graft?',
                        'q13': 'Had surgical operation or dental extraction?',
                        'q14': 'Had a tattoo applied, ear and body piercing, acupuncture, needle stick injury or accidental contact with blood?',
                        'q15': 'Had sexual contact with high risks individuals or in exchange for material or monetary gain?',
                        'q16': 'Engaged in unprotected, unsafe or casual sex?',
                        'q17': 'Had jaundice/hepatitis/personal contact with person who had hepatitis?',
                        'q18': 'Been incarcerated, jailed or imprisoned?',
                        'q19': 'Spent time or have relatives in the United Kingdom or Europe?',
                        'q20': 'Travelled or lived outside of your place of residence or outside the Philippines?',
                        'q21': 'Taken prohibited drugs (orally, by nose, or by injection)?',
                        'q22': 'Used clotting factor concentrates?',
                        'q23': 'Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?',
                        'q24': 'Had Malaria or Hepatitis in the past?',
                        'q25': 'Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?',
                        'q27': 'Cancer, blood disease or bleeding disorder (haemophilia)?',
                        'q28': 'Heart disease/surgery, rheumatic fever or chest pains?',
                        'q29': 'Lung disease, tuberculosis or asthma?',
                        'q30': 'Kidney disease, thyroid disease, diabetes, epilepsy?',
                        'q31': 'Chicken pox and/or cold sores?',
                        'q32': 'Any other chronic medical condition or surgical operations?',
                        'is_female': 'Are you female?',
                        'q34_current_pregnant': 'Are you currently pregnant or have you ever been pregnant?',
                        'q35_last_childbirth': 'When was your last childbirth?',
                        'q35_miscarriage_1y': 'In the past 1 YEAR, did you have a miscarriage or abortion?',
                        'q36_breastfeeding': 'Are you currently breastfeeding?',
                        'q37_lmp_date': 'When was your last menstrual period?'
                    };
                    
                    let parsed = null;
                    try { 
                        parsed = JSON.parse(interviewResponses); 
                    } catch(e) { 
                        parsed = null; 
                    }
                    
                    let ivHtml = '';
                    if (interviewCreated) {
                        try {
                            const formattedDate = new Date(interviewCreated).toLocaleDateString('en-US', { 
                                year: 'numeric', 
                                month: 'short', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            ivHtml += `<p><strong>Submitted:</strong> ${escapeHtml(formattedDate)}</p>`;
                        } catch (e) {
                            ivHtml += `<p><strong>Submitted:</strong> ${escapeHtml(interviewCreated)}</p>`;
                        }
                    }
                    
                    if (parsed && typeof parsed === 'object') {
                        ivHtml += '<div class="mt-3" style="max-height: 400px; overflow-y: auto;">';
                        ivHtml += '<table class="table table-sm table-bordered table-striped">';
                        ivHtml += '<thead class="table-light"><tr><th style="width: 65%;">Question</th><th style="width: 35%;">Answer</th></tr></thead>';
                        ivHtml += '<tbody>';
                        
                        // Sort questions by key to maintain order
                        const sortedKeys = Object.keys(parsed).sort((a, b) => {
                            const numA = Number.parseInt(a.replace(/[^0-9]/g, ''), 10) || 999;
                            const numB = Number.parseInt(b.replace(/[^0-9]/g, ''), 10) || 999;
                            if (numA !== numB) return numA - numB;
                            return a.localeCompare(b);
                        });
                        
                        sortedKeys.forEach(k => {
                            // Skip empty values for optional fields
                            const value = parsed[k];
                            if (!value || value === '' || value === 'null' || value === null) {
                                return;
                            }
                            
                            const questionText = interviewQuestions[k] || k.replace(/^q/, 'Question ').replace(/_/g, ' ');
                            let displayValue = '';
                            
                            if (Array.isArray(value)) {
                                displayValue = value.join(', ');
                            } else {
                                displayValue = String(value);
                            }
                            
                            // Format yes/no answers
                            if (displayValue.toLowerCase() === 'yes') {
                                displayValue = '<span class="badge bg-success">Yes</span>';
                            } else if (displayValue.toLowerCase() === 'no') {
                                displayValue = '<span class="badge bg-secondary">No</span>';
                            } else if (displayValue) {
                                displayValue = escapeHtml(displayValue);
                            } else {
                                return; // Skip if empty
                            }
                            
                            ivHtml += `<tr>`;
                            ivHtml += `<td><small>${escapeHtml(questionText)}</small></td>`;
                            ivHtml += `<td>${displayValue}</td>`;
                            ivHtml += `</tr>`;
                        });
                        
                        ivHtml += '</tbody></table>';
                        ivHtml += '</div>';
                    } else {
                        ivHtml += `<pre class="mt-3" style="white-space:pre-wrap;word-wrap:break-word;font-size: 0.875rem;">${escapeHtml(interviewResponses)}</pre>`;
                    }
                    interviewDetails.innerHTML = ivHtml;
                }
                
                // Populate Appointment Schedule
                appointmentSchedule.innerHTML = `
                    <p><strong>Current Status:</strong> ${escapeHtml(apptStatus)}</p>
                    <p><strong>Date:</strong> ${escapeHtml(apptDate)}</p>
                    <p><strong>Time:</strong> ${escapeHtml(apptTime)}</p>
                    <p><strong>Location:</strong> ${escapeHtml(location || 'Not specified')}</p>
                    <p><strong>Notes:</strong> ${escapeHtml(notes)}</p>
                `;
                
                // Wire approve/reject buttons based on appointment status
                wireQuickActions(btn);
            });
        }
        
        // Function to wire quick action buttons (approve/reject) in donor details modal
        function wireQuickActions(btnEl) {
            const approveBtn = document.getElementById('donorModalApproveBtn');
            const rejectBtn = document.getElementById('donorModalRejectBtn');
            const apptStatus = btnEl.getAttribute('data-appt-status') || '';
            const appointmentId = btnEl.getAttribute('data-id') || '';
            const donorName = btnEl.getAttribute('data-donor-name') || '';
            
            // If appointment is Pending, show approve/reject buttons
            if (apptStatus === 'Pending') {
                approveBtn.classList.remove('d-none');
                rejectBtn.classList.remove('d-none');
                
                // Close donor details modal and open update status modal with Scheduled status
                approveBtn.onclick = function() {
                    const donorDetailsModal = bootstrap.Modal.getInstance(document.getElementById('donorDetailsModal'));
                    if (donorDetailsModal) {
                        donorDetailsModal.hide();
                    }
                    
                    // Find and trigger the Scheduled button for this appointment
                    setTimeout(() => {
                        const scheduledBtn = document.querySelector(`button[data-id="${appointmentId}"][data-status="Scheduled"]`);
                        if (scheduledBtn) {
                            scheduledBtn.click();
                        } else {
                            // Fallback: manually open update status modal
                            openUpdateStatusModal(appointmentId, donorName, 'Scheduled', 'Pending');
                        }
                    }, 300);
                };
                
                // Close donor details modal and open update status modal with Rejected status
                rejectBtn.onclick = function() {
                    const donorDetailsModal = bootstrap.Modal.getInstance(document.getElementById('donorDetailsModal'));
                    if (donorDetailsModal) {
                        donorDetailsModal.hide();
                    }
                    
                    // Find and trigger the Reject button for this appointment
                    setTimeout(() => {
                        const rejectBtnAction = document.querySelector(`button[data-id="${appointmentId}"][data-status="Rejected"]`);
                        if (rejectBtnAction) {
                            rejectBtnAction.click();
                        } else {
                            // Fallback: manually open update status modal
                            openUpdateStatusModal(appointmentId, donorName, 'Rejected', 'Pending');
                        }
                    }, 300);
                };
            } else {
                // Hide buttons for non-pending appointments
                approveBtn.classList.add('d-none');
                rejectBtn.classList.add('d-none');
                approveBtn.onclick = null;
                rejectBtn.onclick = null;
            }
        }
        
        // Helper function to open update status modal programmatically
        function openUpdateStatusModal(appointmentId, donorName, status, currentStatus) {
            const updateStatusModal = document.getElementById('updateStatusModal');
            if (!updateStatusModal) return;
            
            // Create a temporary button element with the necessary data attributes
            const tempButton = document.createElement('button');
            tempButton.setAttribute('data-id', appointmentId);
            tempButton.setAttribute('data-donor', donorName);
            tempButton.setAttribute('data-status', status);
            tempButton.setAttribute('data-current-status', currentStatus);
            tempButton.style.display = 'none';
            document.body.appendChild(tempButton);
            
            // Trigger the modal with this button as the related target
            const modal = new bootstrap.Modal(updateStatusModal);
            
            // Use a custom event to simulate the button click
            const modalEvent = new CustomEvent('show.bs.modal', {
                bubbles: true,
                cancelable: true,
                detail: {}
            });
            modalEvent.relatedTarget = tempButton;
            
            // Manually trigger the show event handler
            updateStatusModal.dispatchEvent(modalEvent);
            
            // Show the modal
            modal.show();
            
            // Clean up the temporary button after modal is shown
            updateStatusModal.addEventListener('shown.bs.modal', function cleanup() {
                document.body.removeChild(tempButton);
                updateStatusModal.removeEventListener('shown.bs.modal', cleanup);
            }, { once: true });
        }

        // Client-side status filter
        const filter = document.getElementById('statusFilter');
        const table = document.getElementById('appointmentsTable');
        const tbody = table ? table.querySelector('tbody') : null;
        if (filter && tbody) {
            filter.addEventListener('change', function() {
                const value = this.value;
                tbody.querySelectorAll('tr').forEach(tr => {
                    const statusBadge = tr.querySelector('td:nth-child(6) .badge');
                    const statusText = statusBadge ? statusBadge.textContent.trim() : '';
                    tr.style.display = !value || statusText === value ? '' : 'none';
                });
            });
        }

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
        
        // Auto-hide feedback messages after 5 seconds
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