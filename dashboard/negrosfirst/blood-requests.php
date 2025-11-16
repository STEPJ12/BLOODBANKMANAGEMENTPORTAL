<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

// Set page title
$pageTitle = "Blood Requests - Blood Bank Portal";
$isDashboard = true; // Enable notification dropdown

// Get Negros First information
$negrosFirstId = $_SESSION['user_id'];

// Process request action (approve/reject)
$message = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_request'])) {
        $requestId = sanitize($_POST['request_id']);
        
        // Allow approve if status is Pending, Referred, NULL, or empty
        $request = getRow("SELECT id, patient_id, blood_type, units_requested 
            FROM blood_requests 
            WHERE id = ? 
              AND organization_type = 'negrosfirst' 
              AND (
                TRIM(LOWER(status)) = 'pending'
                OR TRIM(LOWER(status)) = 'referred'
                OR status IS NULL
                OR TRIM(status) = ''
              )", 
            [$requestId]
        );
        
        if (!$request) {
            $message = 'Invalid request or request is not pending.';
            $alertType = 'danger';
        } else {
            // Update the request status
            $updateResult = executeQuery("
                UPDATE blood_requests
                SET status = 'Approved', processed_date = NOW(), notes = ?
                WHERE id = ? AND organization_type = 'negrosfirst'
            ", [sanitize($_POST['notes'] ?? ''), $requestId]);

            if ($updateResult !== false) {
                // Send notification with SMS using template
                require_once '../../includes/notification_templates.php';
                
                $notificationData = [
                    'units_requested' => $request['units_requested'],
                    'blood_type' => $request['blood_type'],
                    'request_id' => $requestId
                ];
                
                $notifResult = send_notification_with_sms(
                    $request['patient_id'],
                    'patient',
                    'approved',
                    'Blood Request Approved',
                    $notificationData,
                    'negrosfirst'
                );

                $message = 'Blood request has been approved successfully.';
                $alertType = 'success';
            } else {
                $message = 'Failed to approve blood request. Please try again.';
                $alertType = 'danger';
            }
        }
    } elseif (isset($_POST['complete_request'])) {
        // Handle marking request as Completed (includes inventory decrease)
        $requestId = sanitize($_POST['request_id']);
        
        // Get the request details
        $request = getRow("
            SELECT id, patient_id, blood_type, units_requested, status 
            FROM blood_requests 
            WHERE id = ? AND organization_type = 'negrosfirst'
        ", [$requestId]);
        
        if (!$request) {
            $message = 'Invalid request.';
            $alertType = 'danger';
        } else {
            $bloodType = $request['blood_type'];
            $units = (int)$request['units_requested'];
            $currentStatus = $request['status'];
            
            // Only decrease inventory if not already completed
            // This prevents double-decrementing inventory
            if (strcasecmp(trim($currentStatus), 'completed') !== 0) {
                
                // Check if enough blood is available
                $availableunits = getRow("
                    SELECT SUM(units) as total_units
                    FROM blood_inventory
                    WHERE blood_type = ? AND organization_type = 'negrosfirst' AND status = 'Available'
                ", [$bloodType]);
                
                if ($availableunits && $availableunits['total_units'] >= $units) {
                    // Begin transactional completion with FIFO (First In, First Out based on expiry date)
                    beginTransaction();
                    try {
                        // Fetch available inventory batches ordered by earliest expiry (NULLs last)
                        // This ensures we use the oldest blood first (FIFO) based on expiry date
                        $batches = executeQuery("
                            SELECT id, units, expiry_date
                            FROM blood_inventory
                            WHERE blood_type = ? 
                            AND organization_type = 'negrosfirst' 
                            AND organization_id = ?
                            AND status = 'Available'
                            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                            ORDER BY (expiry_date IS NULL) ASC, expiry_date ASC
                        ", [$bloodType, $negrosFirstId]);
                        
                        $remaining = $units;
                        if (!is_array($batches) || count($batches) === 0) {
                            throw new Exception('No available inventory for selected blood type.');
                        }
                        
                        // Update inventory by using the oldest units first (earliest expiry first)
                        foreach ($batches as $batch) {
                            if ($remaining <= 0) break;
                            $use = min((int)$batch['units'], $remaining);
                            $newUnits = (int)$batch['units'] - $use;
                            
                            if ($newUnits > 0) {
                                // If using partial units, update the count
                                updateRow("UPDATE blood_inventory SET units = ?, updated_at = NOW() WHERE id = ?", [$newUnits, $batch['id']]);
                            } else {
                                // If using all units in this batch, mark as Used
                                updateRow("UPDATE blood_inventory SET units = 0, status = 'Used', updated_at = NOW() WHERE id = ?", [$batch['id']]);
                            }
                            $remaining -= $use;
                        }
                        
                        if ($remaining > 0) {
                            // Not enough units to complete
                            throw new Exception('Insufficient inventory to complete this request.');
                        }
                        
                        // Update blood request status to Completed
                        $result = executeQuery("
                            UPDATE blood_requests
                            SET status = 'Completed', processed_date = NOW(), notes = ?
                            WHERE id = ? AND organization_type = 'negrosfirst'
                        ", [sanitize($_POST['notes'] ?? ''), $requestId]);
                        
                        if ($result === false) {
                            throw new Exception('Failed to update request status.');
                        }
                        
                        commitTransaction();
                        
                        // Send notification with SMS using template (after successful commit)
                        require_once '../../includes/notification_templates.php';
                        
                        $notificationData = [
                            'type' => 'request',
                            'units_requested' => $units,
                            'blood_type' => $bloodType,
                            'date' => date('Y-m-d'),
                            'pickup_completed' => false
                        ];
                        
                        send_notification_with_sms(
                            $request['patient_id'],
                            'patient',
                            'completed',
                            'Blood Request Completed',
                            $notificationData,
                            'negrosfirst'
                        );
                        
                        $message = 'Blood request has been completed successfully and inventory updated.';
                        $alertType = 'success';
                        
                        // Redirect to history page after completion
                        $_SESSION['message'] = $message;
                        $_SESSION['message_type'] = $alertType;
                        header("Location: blood-request-history.php");
                        exit;
                    } catch (Exception $e) {
                        rollbackTransaction();
                        if (function_exists('secure_log')) {
                            secure_log("Error completing blood request", [
                                'request_id' => $requestId,
                                'error' => substr($e->getMessage(), 0, 200)
                            ]);
                        }
                        $message = 'Failed to complete blood request: ' . $e->getMessage();
                        $alertType = 'danger';
                    }
                } else {
                    $message = 'Not enough blood units available to complete this request.';
                    $alertType = 'danger';
                }
            } else {
                // Already completed, just update status without touching inventory
                $result = executeQuery("
                    UPDATE blood_requests
                    SET status = 'Completed', processed_date = NOW(), notes = ?
                    WHERE id = ? AND organization_type = 'negrosfirst'
                ", [sanitize($_POST['notes'] ?? ''), $requestId]);
                
                if ($result !== false) {
                    $message = 'Blood request status updated to Completed.';
                    $alertType = 'success';
                    
                    // Redirect to history page after completion
                    $_SESSION['message'] = $message;
                    $_SESSION['message_type'] = $alertType;
                    header("Location: blood-request-history.php");
                    exit;
                } else {
                    $message = 'Failed to update request status.';
                    $alertType = 'danger';
                }
            }
        }
    }
}

// Get blood requests
// SECURITY: Validate and whitelist status parameter to prevent SQL injection
$statusInput = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
// Whitelist of allowed status values - only allow specific values
$allowedStatuses = ['all', 'Pending', 'Approved', 'Completed', 'pending', 'approved', 'completed'];
// Normalize to lowercase for comparison
$statusLower = strtolower(trim($statusInput));
// If not in whitelist, default to 'all'
if (!in_array($statusInput, $allowedStatuses, true) && !in_array($statusLower, ['all', 'pending', 'approved', 'completed'], true)) {
    $statusInput = 'all';
    $statusLower = 'all';
}
// Normalize status for SQL (use capitalized version for consistency)
if ($statusLower === 'pending') {
    $status = 'Pending';
} elseif ($statusLower === 'approved') {
    $status = 'Approved';
} elseif ($statusLower === 'completed') {
    $status = 'Completed';
} else {
    $status = 'all';
}

// SECURITY: Build query with parameterized placeholders - no string interpolation
// This prevents any possibility of SQL injection even if validation is bypassed
// Both query strings below are COMPLETELY STATIC - no variables, no interpolation
$queryParams = [];

// Base query with WHERE clause that uses parameterized values
if ($status !== 'all') {
    // Status is validated against whitelist, then used as parameter
    // SECURITY: This is a static query string - no user input is ever interpolated into it
    $query = "
        SELECT br.*, pu.name as patient_name, pu.phone, pu.blood_type as patient_blood_type,
               b.name as barangay_name, br.has_blood_card, br.request_form_path, br.blood_card_path,
               r.referral_document_name, r.referral_document_type, r.referral_date, r.id as referral_id,
               CASE 
                   WHEN br.has_blood_card = 1 THEN 'Direct Request'
                   WHEN r.id IS NULL AND br.barangay_id IS NOT NULL THEN 'Pending Barangay Referral'
                   WHEN r.id IS NOT NULL THEN CONCAT('Referred by ', b.name)
                   ELSE 'Direct Request'
               END as referral_status,
               COALESCE(br.status, 'Pending') as status
        FROM blood_requests br
        JOIN patient_users pu ON br.patient_id = pu.id
        LEFT JOIN barangay_users b ON br.barangay_id = b.id
        LEFT JOIN referrals r ON br.id = r.blood_request_id
        WHERE br.organization_type = 'negrosfirst' AND br.status = ?
        ORDER BY
            CASE
                WHEN br.status = 'Pending' THEN 1
                WHEN br.status = 'Approved' THEN 2
                WHEN br.status = 'Completed' THEN 3
            END,
            br.request_date DESC
    ";
    $queryParams[] = $status;
} else {
    // No status filter - query without status parameter
    // SECURITY: This is a static query string - no user input is ever interpolated into it
    $query = "
        SELECT br.*, pu.name as patient_name, pu.phone, pu.blood_type as patient_blood_type,
               b.name as barangay_name, br.has_blood_card, br.request_form_path, br.blood_card_path,
               r.referral_document_name, r.referral_document_type, r.referral_date, r.id as referral_id,
               CASE 
                   WHEN br.has_blood_card = 1 THEN 'Direct Request'
                   WHEN r.id IS NULL AND br.barangay_id IS NOT NULL THEN 'Pending Barangay Referral'
                   WHEN r.id IS NOT NULL THEN CONCAT('Referred by ', b.name)
                   ELSE 'Direct Request'
               END as referral_status,
               COALESCE(br.status, 'Pending') as status
        FROM blood_requests br
        JOIN patient_users pu ON br.patient_id = pu.id
        LEFT JOIN barangay_users b ON br.barangay_id = b.id
        LEFT JOIN referrals r ON br.id = r.blood_request_id
        WHERE br.organization_type = 'negrosfirst'
        ORDER BY
            CASE
                WHEN br.status = 'Pending' THEN 1
                WHEN br.status = 'Approved' THEN 2
                WHEN br.status = 'Completed' THEN 3
            END,
            br.request_date DESC
    ";
}

// SECURITY: Execute with parameterized query to prevent SQL injection
// IMPORTANT: $query contains ONLY static SQL strings (both branches above are hardcoded)
// No user input is ever interpolated into $query - all user data goes through $queryParams
// $queryParams contains validated values that are bound via PDO prepared statements
$bloodRequests = executeQuery($query, $queryParams);

// Add debugging (using secure_log for any user-controlled data)
if ($bloodRequests === false) {
    if (function_exists('secure_log')) {
        secure_log("Database query failed for blood requests");
    }
    $bloodRequests = [];
} else if (empty($bloodRequests)) {
    if (function_exists('secure_log')) {
        secure_log("No blood requests found", ['status_filter' => $status]);
    }
    
    // Check if the tables exist and have data (removed detailed logging)
    $checkTables = executeQuery("
        SELECT 
            (SELECT COUNT(*) FROM blood_requests) as blood_requests_count,
            (SELECT COUNT(*) FROM patient_users) as patient_users_count,
            (SELECT COUNT(*) FROM blood_requests WHERE organization_type = 'negrosfirst') as negrosfirst_requests_count
    ");
}

// Initialize $bloodRequests as an empty array if the query fails
if ($bloodRequests === false) {
    $bloodRequests = [];
}

// Function to fetch counts safely
function getCountValue($query) {
    $result = executeQuery($query);
    return isset($result[0]) ? (int)array_values($result[0])[0] : 0;
}

// NEGROSFIRST organization counts
$pendingCount = getCountValue("SELECT COUNT(*) FROM blood_requests WHERE organization_type = 'negrosfirst' AND status = 'Pending'");
$approvedCount = getCountValue("SELECT COUNT(*) FROM blood_requests WHERE organization_type = 'negrosfirst' AND status = 'Approved'");
$completedCount = getCountValue("SELECT COUNT(*) FROM blood_requests WHERE organization_type = 'negrosfirst' AND status = 'Completed'");
$totalCount = $pendingCount + $approvedCount + $completedCount;
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
    body {
        font-family: sans-serif;
        background-color: #f8f9fa;
        margin: 0;
        padding: 0;
        overflow-x: hidden;
    }

    .container {
        margin-top: 20px;
        width: 100%;
        padding-right: 15px;
        padding-left: 15px;
        margin-right: auto;
        margin-left: auto;
    }

    .dashboard-container {
        display: flex;
        width: 100%;
        position: relative;
    }

    .dashboard-content {
        flex: 1;
        min-width: 0;
        height: 100vh;
        overflow-y: auto;
        padding: 0;
        margin-left: 300px; /* Sidebar width */
        transition: margin-left 0.3s ease;
    }

    /* Responsive sidebar */
    @media (max-width: 991.98px) {
        .dashboard-content {
            margin-left: 0;
        }
    }
    .dashboard-header .breadcrumb {
        margin-left: 35rem;
    }

    /* Card styles */
    .card {
        margin-bottom: 1rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
    }

    .card-header {
        background: var(--white);
        border-bottom: 1px solid var(--gray-200);
    }

    .card-body {
        padding: 1.25rem;
    }

    /* Table responsive styles */
    .table-responsive {
        margin: 0;
        padding: 0;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .table {
        width: 100%;
        margin-bottom: 0;
    }

    /* Stats cards responsive grid */
    @media (max-width: 767.98px) {
        .col-md-3 {
            margin-bottom: 1rem;
        }
    }

    /* Modal responsive styles */
    .modal-dialog {
        margin: 1rem;
        max-width: 100%;
    }

    @media (min-width: 576px) {
        .modal-dialog {
            max-width: 500px;
            margin: 1.75rem auto;
        }
        .modal-dialog-lg {
            max-width: 800px;
        }
    }

    /* Button and dropdown responsive styles */
    .dropdown-menu {
        min-width: 200px;
    }

    @media (max-width: 575.98px) {
        .btn-group {
            display: flex;
            width: 100%;
        }
        .dropdown-menu {
            width: 100%;
        }
        .card-header {
            flex-direction: column;
            gap: 1rem;
        }
        .card-header .btn-group {
            width: 100%;
            justify-content: center;
        }
    }

    /* Header responsive styles */
    .dashboard-header {
        padding: 1rem;
        background: white;
        border-bottom: 1px solid #dee2e6;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .dashboard-main {
        padding-top: 1rem;
    }

    @media (max-width: 575.98px) {
        .dashboard-header .d-flex {
            flex-direction: column;
            gap: 0.5rem;
        }
        .breadcrumb {
            margin-top: 0.5rem;
        }
    }

    /* Table cell responsive behavior */
    @media (max-width: 767.98px) {
        .table td, .table th {
            min-width: 120px;
        }
        .table td:first-child, .table th:first-child {
            position: sticky;
            left: 0;
            background: white;
            z-index: 1;
        }
    }

    /* Action buttons responsive styles */
    .dropdown-toggle {
        white-space: nowrap;
    }

    @media (max-width: 575.98px) {
        .dropdown {
            width: 100%;
        }
        .dropdown-toggle {
            width: 100%;
            text-align: left;
        }
    }
   

    /* Update modal styles */
    .modal {
        transition: all 0.3s ease-in-out;
        pointer-events: auto !important;
    }
    .modal-backdrop {
        transition: all 0.3s ease-in-out;
        pointer-events: auto !important;
    }
    .modal.fade {
        transition: all 0.3s ease-in-out;
    }
    .modal-backdrop.fade {
        transition: all 0.3s ease-in-out;
    }
    .modal.show {
        display: block !important;
        pointer-events: auto !important;
    }
    .modal-backdrop.show {
        opacity: 0.5;
        pointer-events: auto !important;
    }
    /* Prevent body scroll when modal is open */
    body.modal-open {
        overflow: hidden;
        padding-right: 0 !important;
    }
    /* Prevent unwanted interactions */
    .modal-dialog {
        pointer-events: auto !important;
    }

    /* Modern table look */
.table th, .table td {
    vertical-align: middle;
    font-size: 1rem;
}
.table thead th {
    background-color: var(--gray-100);
    color: var(--gray-800);
    border-bottom: 2px solid var(--gray-200);
}
.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}
.badge {
    font-size: 0.9em;
    padding: 0.45em 0.7em;
    border-radius: 20px;
}

/* Modal styles */
.modal-content {
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
}
.modal-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}
.modal-footer {
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
}
</style>

<body>

<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h4 mb-0">Blood Requests</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Blood Requests</li>
                    </ol>
                </nav>
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
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                    <a href="?status=all" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm hover-card" style="cursor: pointer; transition: transform 0.2s;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Requests</h6>
                                        <h3 class="mb-0"><?php echo $totalCount; ?></h3>
                                    </div>
                                    <div class="bg-light rounded-circle p-3">
                                        <i class="bi bi-clipboard-check fs-4 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                    <a href="?status=Pending" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm hover-card" style="cursor: pointer; transition: transform 0.2s;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Pending</h6>
                                        <h3 class="mb-0"><?php echo $pendingCount; ?></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                        <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3 col-sm-6 mb-3 mb-sm-0">
                    <a href="?status=Approved" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm hover-card" style="cursor: pointer; transition: transform 0.2s;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Approved</h6>
                                        <h3 class="mb-0"><?php echo $approvedCount; ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                        <i class="bi bi-check-circle fs-4 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3 col-sm-6">
                    <a href="blood-request-history.php" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm hover-card" style="cursor: pointer; transition: transform 0.2s;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Completed</h6>
                                        <h3 class="mb-0"><?php echo $completedCount; ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                        <i class="bi bi-check-circle fs-4 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <?php
                        if ($status === 'all') {
                            echo 'All Blood Requests';
                        } else {
                            echo ucfirst($status) . ' Blood Requests';
                        }
                        ?>
                    </h4>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="printRequestsReport()">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                        <button class="btn btn-sm btn-primary" id="exportRequestsCsv">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </button>
                        <a href="blood-request-history.php" class="btn btn-sm btn-outline-info ms-2">
                            <i class="bi bi-clock-history me-1"></i> View History
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($bloodRequests) > 0): ?>
                        <div class="table-responsive">
                          <table class="table table-bordered table-striped">
                            <thead>
               <tr>
                   <th>Request ID</th>
                   <th>Patient Name</th>
                   <th>Requested Date </th>
                   <th>Status</th>
                   <th>Referral Status</th>
                   <th>Actions</th>
               </tr>
           </thead>
                                <tbody>
                                    <?php foreach ($bloodRequests as $request): ?>
                                        <tr>
                                            <td>#<?php echo str_pad($request['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                            <td><?php echo $request['patient_name']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($request['required_date'])); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = 'secondary';
                                                switch (strtolower($request['status'])) {
                                                    case 'pending':
                                                        $statusClass = 'warning';
                                                        break;
                                                    case 'approved':
                                                        $statusClass = 'success';
                                                        break;
                                                    case 'completed':
                                                        $statusClass = 'info';
                                                        break;
                                                    default:
                                                        $statusClass = 'warning';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $request['status'] ?: 'Pending'; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($request['has_blood_card']): ?>
                                                    <span class="badge bg-success">Direct Request</span>
                                                <?php elseif ($request['referral_id']): ?>
                                                    <span class="badge bg-info">Referred by <?php echo htmlspecialchars($request['barangay_name']); ?></span>
                                                <?php elseif ($request['barangay_id']): ?>
                                                    <span class="badge bg-warning text-dark">Pending Barangay Referral</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Direct Request</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="actionDropdown<?php echo $request['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="actionDropdown<?php echo $request['id']; ?>">
                                                        <li>
                                                            <a class="dropdown-item view-details-btn" href="#"
                                                               data-request-id="<?php echo $request['id']; ?>"
                                                               data-patient-name="<?php echo htmlspecialchars($request['patient_name'] ?? ''); ?>"
                                                               data-phone="<?php echo htmlspecialchars($request['phone'] ?? ''); ?>"
                                                               data-blood-type="<?php echo htmlspecialchars($request['blood_type'] ?? ''); ?>"
                                                               data-units="<?php echo htmlspecialchars($request['units_requested'] ?? ''); ?>"
                                                               data-required-date="<?php echo htmlspecialchars(!empty($request['required_date']) ? date('M d, Y', strtotime($request['required_date'])) : ''); ?>"
                                                               data-status="<?php echo htmlspecialchars($request['status'] ?? ''); ?>"
                                                               data-hospital="<?php echo htmlspecialchars($request['hospital'] ?? ''); ?>"
                                                               data-doctor="<?php echo htmlspecialchars($request['doctor_name'] ?? ''); ?>"
                                                               data-reason="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>"
                                                               data-request-form="<?php echo htmlspecialchars($request['request_form_path'] ?? ''); ?>"
                                                               data-blood-card="<?php echo htmlspecialchars($request['blood_card_path'] ?? ''); ?>"
                                                               data-patient-id="<?php echo $request['patient_id'] ?? ''; ?>"
                                                               data-referral-id="<?php echo $request['referral_id'] ?? ''; ?>"
                                                               data-referral-document-name="<?php echo htmlspecialchars($request['referral_document_name'] ?? ''); ?>"
                                                               data-referral-document-type="<?php echo htmlspecialchars($request['referral_document_type'] ?? ''); ?>"
                                                               data-referral-date="<?php echo htmlspecialchars($request['referral_date'] ?? ''); ?>"
                                                               data-barangay-name="<?php echo htmlspecialchars($request['barangay_name'] ?? ''); ?>"
                                                            >
                                                                <i class="bi bi-eye me-2"></i> View Details
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                                                            </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-clipboard-x fs-1 d-block mb-3"></i>
                                <p>No blood requests found.</p>
                                                                        </div>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                            </div>
                                                                    </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

<!-- Place this just before </body> -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
        <h5 class="modal-title" id="viewRequestModalLabel">Blood Request Details</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
      <div class="modal-body" id="viewRequestModalBody">
        <!-- Content will be injected by JS -->
                                                                        </div>
      <div class="modal-footer" id="viewRequestModalFooter">
        <!-- Buttons will be injected by JS -->
                                                                    </div>
                                                            </div>
                                                        </div>
                                                    </div>

<form id="completeForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="request_id" id="complete_request_id">
    <input type="hidden" name="complete_request" value="1">
    <input type="hidden" name="notes" value="">
</form>

<style>
    .hover-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-details-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            // Get all data attributes
            var requestId = this.getAttribute('data-request-id');
            var patientName = this.getAttribute('data-patient-name');
            var phone = this.getAttribute('data-phone');
            var bloodType = this.getAttribute('data-blood-type');
            var units = this.getAttribute('data-units');
            var requiredDate = this.getAttribute('data-required-date');
            var status = this.getAttribute('data-status');
            var hospital = this.getAttribute('data-hospital');
            var doctor = this.getAttribute('data-doctor');
            var reason = this.getAttribute('data-reason');
            var requestForm = this.getAttribute('data-request-form');
            var bloodCard = this.getAttribute('data-blood-card');
            var patientId = this.getAttribute('data-patient-id');
            var referralId = this.getAttribute('data-referral-id');
            var referralDocumentName = this.getAttribute('data-referral-document-name');
            var referralDocumentType = this.getAttribute('data-referral-document-type');
            var referralDate = this.getAttribute('data-referral-date');
            var barangayName = this.getAttribute('data-barangay-name');

            // Build modal content
            var html = `
                <div class="row mb-2">
                    <div class="col-md-6 mb-2"><strong>Patient Name:</strong> <span class="text-primary">${patientName}</span></div>
                    <div class="col-md-6 mb-2"><strong>Contact:</strong> <span class="text-muted">${phone}</span></div>
                    <div class="col-md-6 mb-2"><strong>Blood Type:</strong> <span class="badge bg-danger fs-6">${bloodType}</span></div>
                    <div class="col-md-6 mb-2"><strong>Units:</strong> <span class="fw-bold">${units}</span></div>
                    <div class="col-md-6 mb-2"><strong>Required Date:</strong> <span>${requiredDate}</span></div>
                    <div class="col-md-6 mb-2"><strong>Status:</strong> <span class="badge bg-info text-dark">${status}</span></div>
                    <div class="col-md-6 mb-2"><strong>Hospital/Clinic:</strong> <span>${hospital}</span></div>
                    <div class="col-md-6 mb-2"><strong>Doctor:</strong> <span>${doctor ? doctor : 'N/A'}</span></div>
                    <div class="col-12 mb-2"><strong>Reason:</strong> <span>${reason}</span></div>
                </div>
                <div class="mt-3"><strong>Attached Documents:</strong></div>
                <ul class="list-group list-group-flush mb-2">
                    <li class="list-group-item">
                        <strong>Hospital Request Form:</strong>
                        ${requestForm ? `<a href="view-request-form.php?request_id=${requestId}" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-eye me-1"></i>View File</a> <a href="download-request-form.php?request_id=${requestId}" class="btn btn-sm btn-outline-success ms-1"><i class="bi bi-download me-1"></i>Download</a>` : '<span class="text-muted ms-2">No file attached</span>'}
                    </li>
                    <li class="list-group-item">
                        <strong>Blood Card:</strong>
                        ${bloodCard ? `<a href="view-blood-card.php?request_id=${requestId}" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-eye me-1"></i>View File</a> <a href="download-blood-card.php?request_id=${requestId}" class="btn btn-sm btn-outline-success ms-1"><i class="bi bi-download me-1"></i>Download</a>` : '<span class="text-muted ms-2">No file attached</span>'}
                    </li>
                </ul>
            `;

            var referralHtml = '';
            if (referralId && referralDocumentName) {
                referralHtml = `
                    <div class="mt-3"><strong>Barangay Referral:</strong></div>
                    <div class="mb-2">
                        <span class="badge bg-info text-dark">Referred by: ${barangayName ? barangayName : 'Barangay'}</span>
                    </div>
                    <div class="mb-2">
                        <strong>Referral Date:</strong> <span>${referralDate ? referralDate : ''}</span>
                    </div>
                    <div>
                        <strong>Referral Document:</strong>
                        <span class="ms-2">${referralDocumentName}</span>
                        <div class="mt-2">
                            <a href="view-referral.php?request_id=${requestId}" target="_blank" class="btn btn-outline-info btn-sm me-2"><i class="bi bi-eye me-1"></i>View</a>
                            <a href="download-referral.php?request_id=${requestId}" target="_blank" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>Download</a>
                        </div>
                    </div>
                `;
            } else if (barangayName) {
                referralHtml = `
                    <div class="mt-3"><strong>Barangay Referral:</strong></div>
                    <div>
                        <span class="badge bg-warning text-dark">Pending Barangay Referral</span>
                    </div>
                `;
            }

            document.getElementById('viewRequestModalBody').innerHTML = html + referralHtml;

            // Build modal footer with action buttons
            var footerHtml = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
            var normalizedStatus = (status || '').trim().toLowerCase();

            if (!normalizedStatus || normalizedStatus === 'pending') {
                footerHtml += `
                    <form id="actionForm${requestId}" method="POST" action="" style="display:inline;">
                        <input type="hidden" name="request_id" value="${requestId}">
                        <button type="submit" name="approve_request" class="btn btn-success">Approve</button>
                    </form>
                `;
            } else if (normalizedStatus === 'approved') {
                footerHtml += ` <button type="button" class="btn btn-primary" id="completeBtn">Mark as Completed</button>`;
            }
            document.getElementById('viewRequestModalFooter').innerHTML = footerHtml;

            // Show modal
            var modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
            modal.show();

            // Complete button handler
            setTimeout(function() {
                var completeBtn = document.getElementById('completeBtn');
                if (completeBtn) {
                    completeBtn.onclick = function() {
                        document.getElementById('complete_request_id').value = requestId;
                        var notes = prompt('Add any notes for completion (optional):');
                        if (notes !== null) {
                            document.getElementById('completeForm').querySelector('input[name="notes"]').value = notes || '';
                            if (confirm('Mark this request as Completed? This will decrease inventory.')) {
                                document.getElementById('completeForm').submit();
                            }
                        }
                    };
                }
            }, 200);
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
    
    // Export CSV functionality
    const exportBtn = document.getElementById('exportRequestsCsv');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const table = document.querySelector('.table-responsive table');
            if (!table) return;
            
            const headers = Array.from(table.querySelectorAll('thead th'))
                .slice(0, -1) // Exclude Actions column
                .map(th => '"' + (th.innerText || '').replace(/"/g, '""') + '"');
            
            const rows = Array.from(table.querySelectorAll('tbody tr'))
                .filter(tr => tr.style.display !== 'none')
                .map(tr => Array.from(tr.querySelectorAll('td'))
                .slice(0, -1) // Exclude Actions column
                .map(td => {
                    // Remove badges and get clean text
                    const badge = td.querySelector('.badge');
                    if (badge) {
                        return '"' + badge.innerText.replace(/"/g, '""') + '"';
                    }
                    return '"' + (td.innerText || '').replace(/"/g, '""') + '"';
                })
                .join(','));
            
            const csv = [headers.join(','), ...rows].join('\r\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'blood_requests_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }
});
</script>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
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

<script>
function printRequestsReport() {
    const table = document.querySelector('.table-responsive table, table.table');
    if (!table) {
        alert('No blood requests data found to print.');
        return;
    }
    
    // Get page title
    const pageTitle = document.querySelector('.card-title, h4').textContent || 'Blood Requests Report';
    
    // Extract headers (excluding Actions column)
    const headers = Array.from(table.querySelectorAll('thead th'))
        .filter((th, index) => {
            const text = (th.textContent || '').trim().toLowerCase();
            return !text.includes('action');
        })
        .map(th => (th.textContent || '').trim());
    
    // Extract rows (excluding Actions column)
    const rows = Array.from(table.querySelectorAll('tbody tr'))
        .filter(tr => tr.style.display !== 'none')
        .map(tr => {
            const cells = Array.from(tr.querySelectorAll('td'))
                .filter((td, index) => {
                    // Check if corresponding header is Actions
                    const headerRow = table.querySelector('thead tr');
                    if (headerRow) {
                        const headerCells = Array.from(headerRow.querySelectorAll('th'));
                        if (headerCells[index]) {
                            const headerText = (headerCells[index].textContent || '').trim().toLowerCase();
                            return !headerText.includes('action');
                        }
                    }
                    return true;
                })
                .map(td => {
                    // Extract text from badges
                    const badge = td.querySelector('.badge');
                    if (badge) {
                        return badge.textContent.trim();
                    }
                    return (td.textContent || '').trim();
                });
            return cells;
        });
    
    // Build table HTML
    let tableHTML = '<table style="width:100%; border-collapse:collapse; margin:20px 0;">';
    
    // Headers
    tableHTML += '<thead><tr>';
    headers.forEach(header => {
        tableHTML += `<th style="background-color:#f8f9fa; padding:12px 8px; border:1px solid #ddd; font-weight:bold; text-align:left;">${header}</th>`;
    });
    tableHTML += '</tr></thead>';
    
    // Rows
    tableHTML += '<tbody>';
    rows.forEach(row => {
        tableHTML += '<tr>';
        row.forEach((cell, index) => {
            // Style badges based on content
            let cellStyle = 'padding:12px 8px; border:1px solid #ddd;';
            if (headers[index] && headers[index].toLowerCase().includes('status')) {
                const status = cell.toLowerCase();
                if (status.includes('pending')) {
                    cell = `<span style="background-color:#ffc107; color:#212529; padding:4px 8px; border-radius:4px; font-weight:bold;">${cell}</span>`;
                } else if (status.includes('approved')) {
                    cell = `<span style="background-color:#28a745; color:#fff; padding:4px 8px; border-radius:4px; font-weight:bold;">${cell}</span>`;
                } else if (status.includes('completed')) {
                    cell = `<span style="background-color:#17a2b8; color:#fff; padding:4px 8px; border-radius:4px; font-weight:bold;">${cell}</span>`;
                }
            } else if (headers[index] && headers[index].toLowerCase().includes('blood type')) {
                cell = `<span style="background-color:#dc3545; color:#fff; padding:4px 8px; border-radius:4px; font-weight:bold;">${cell}</span>`;
            }
            tableHTML += `<td style="${cellStyle}">${cell || ''}</td>`;
        });
        tableHTML += '</tr>';
    });
    tableHTML += '</tbody></table>';
    
    // Generate print document
    const content = `
        <div style="margin-bottom:20px;">
            <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Total Records:</strong> ${rows.length}</p>
        </div>
        ${tableHTML}
    `;
    
    generatePrintDocument(pageTitle, content);
}
</script>

</body>
</html>
