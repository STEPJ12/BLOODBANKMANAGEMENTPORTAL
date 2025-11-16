<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

$pageTitle = "Blood Requests - Blood Bank Portal";
$isDashboard = true;

// Include database connection
require_once '../../config/db.php';

// Get barangay information
$barangayId = $_SESSION['user_id'];
$barangayRow = getRow("SELECT * FROM barangay_users WHERE id = ?", [$barangayId]);
$barangayName = $barangayRow['name'] ?? 'Barangay';
$barangay = $barangayRow; // Keep for backward compatibility

// Debug: Log barangay ID
error_log("Barangay ID for blood requests: " . $barangayId);

// Get available blood banks for referrals
$bloodBanks = executeQuery("
    SELECT * FROM blood_banks
    ORDER BY name ASC
");

// Get all blood requests for this barangay
// This includes:
// 1. Requests from patients in this barangay (br.barangay_id = ?)
// 2. Requests that have been referred by this barangay (from referrals table)
$bloodRequests = executeQuery("
    SELECT DISTINCT br.*, pu.name AS patient_name, pu.blood_type, pu.phone, pu.address,
        br.hospital, br.reason, br.required_date, br.organization_type,
        br.request_form_path,
        r.referral_date, r.status AS referral_status
    FROM blood_requests br
    INNER JOIN patient_users pu ON br.patient_id = pu.id
    LEFT JOIN referrals r ON br.id = r.blood_request_id
    WHERE (br.barangay_id = ? OR r.barangay_id = ?)
    ORDER BY 
        CASE 
            WHEN LOWER(br.status) = 'pending' AND r.id IS NULL THEN 1
            WHEN LOWER(br.status) = 'pending' AND r.id IS NOT NULL THEN 2
            WHEN LOWER(br.status) = 'approved' THEN 3
            WHEN LOWER(br.status) = 'completed' THEN 4
            ELSE 5
        END,
        CASE 
            WHEN br.urgency = 'high' THEN 1
            WHEN br.urgency = 'medium' THEN 2
            ELSE 3
        END,
        br.request_date DESC
", [$barangayId, $barangayId]);

// Ensure $bloodRequests is an array (handle false return from executeQuery)
if ($bloodRequests === false) {
    $bloodRequests = [];
    error_log("Blood requests query returned false for barangay_id: " . $barangayId);
} else {
    error_log("Blood requests found: " . count($bloodRequests) . " for barangay_id: " . $barangayId);
    // Debug: Log first few requests
    if (count($bloodRequests) > 0) {
        error_log("First request barangay_id: " . ($bloodRequests[0]['barangay_id'] ?? 'NULL'));
    }
}

// Count pending requests
$pendingCount = 0;
if (is_array($bloodRequests)) {
    $pendingCount = count(array_filter($bloodRequests, function($request) {
        return strtolower($request['status']) === 'pending';
    }));
}

// Add enhanced CSS
echo '<link rel="stylesheet" href="../../css/barangay-portal.css">';
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

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $basePath; ?>assets/images/favicon.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons - CDN with fallback -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
    <!-- Fallback for offline use -->
    

    <!-- Custom Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/dashboard.css">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">

    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
    
    <!-- Universal Print Script -->
    <script src="<?php echo $basePath; ?>assets/js/universal-print.js"></script>
    
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
        
        .table tbody tr.hover-row {
            background: #ffffff !important;
        }
        
        .table tbody tr.hover-row:hover {
            background: rgba(234, 179, 8, 0.1) !important;
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
        
        /* Ensure table header is visible */
        .table thead th {
            background: #f8f9fa !important;
            color: #2a363b !important;
        }
        
        /* Actions column buttons - ensure visibility */
        .table tbody td .btn {
            color: #ffffff !important;
            font-weight: 500 !important;
        }
        
        .table tbody td .btn i {
            color: inherit !important;
        }
        
        /* Refer button styling */
        .table tbody td .referral-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            border: none !important;
            color: #ffffff !important;
            padding: 0.5rem 1rem !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3) !important;
            transition: all 0.3s ease !important;
        }
        
        .table tbody td .referral-btn:hover {
            background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%) !important;
            color: #1e293b !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(234, 179, 8, 0.4) !important;
        }
        
        .table tbody td .referral-btn i {
            color: inherit !important;
        }
        
        /* Processed button styling - make it visible */
        .table tbody td .btn-outline.btn-sm[disabled] {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            border: 2px solid #10b981 !important;
            color: #ffffff !important;
            padding: 0.5rem 1rem !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
            opacity: 1 !important;
            cursor: not-allowed !important;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3) !important;
        }
        
        .table tbody td .btn-outline.btn-sm[disabled] i {
            color: #ffffff !important;
        }
        
        /* Ensure all action buttons are visible */
        .table tbody td .btn-sm {
            font-size: 0.875rem !important;
            line-height: 1.5 !important;
        }
        
        .table tbody td .btn-sm i {
            font-size: 0.875rem !important;
            margin-right: 0.25rem !important;
        }

        /* Notification dropdown - ensure it's on top */
        .notification-bell {
            position: relative !important;
            z-index: 1050 !important;
        }
        
        #notificationDropdownMenu,
        .notification-dropdown,
        ul.notification-dropdown,
        .dropdown-menu.notification-dropdown {
            z-index: 9999 !important;
            position: absolute !important;
            top: 100% !important;
            right: 0 !important;
            left: auto !important;
            margin-top: 0.5rem !important;
        }
        
        /* Ensure modal doesn't interfere */
        .modal {
            z-index: 1055 !important;
        }
        
        .modal-backdrop {
            z-index: 1050 !important;
        }
        
        /* Refresh and Back buttons styling */
        .refresh-btn,
        .btn-outline-primary.btn-sm {
            border: 2px solid #3b82f6 !important;
            color: #3b82f6 !important;
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(10px) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
        }

        .refresh-btn:hover,
        .btn-outline-primary.btn-sm:hover {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            color: #ffffff !important;
            border-color: #3b82f6 !important;
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4), 0 2px 8px rgba(0, 0, 0, 0.1) !important;
        }

        .refresh-btn i,
        .btn-outline-primary.btn-sm i {
            transition: transform 0.3s ease !important;
        }

        .refresh-btn:hover i,
        .btn-outline-primary.btn-sm:hover i {
            transform: rotate(180deg);
        }

        .back-btn,
        .btn-outline-secondary.btn-sm {
            border: 2px solid #64748b !important;
            color: #64748b !important;
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(10px) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            text-decoration: none !important;
        }

        .back-btn:hover,
        .btn-outline-secondary.btn-sm:hover {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%) !important;
            color: #ffffff !important;
            border-color: #64748b !important;
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.4), 0 2px 8px rgba(0, 0, 0, 0.1) !important;
        }

        /* Modal buttons styling */
        .modal-footer .btn-outline-secondary {
            border: 2px solid #64748b !important;
            color: #64748b !important;
            background: rgba(255, 255, 255, 0.9) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            padding: 0.75rem 1.5rem !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        .modal-footer .btn-outline-secondary:hover {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%) !important;
            color: #ffffff !important;
            border-color: #64748b !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.4) !important;
        }

        .modal-footer .btn-primary-custom {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%) !important;
            border: none !important;
            color: white !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            padding: 0.75rem 1.5rem !important;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.35), 0 2px 8px rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        .modal-footer .btn-primary-custom:hover {
            background: linear-gradient(135deg, #eab308 0%, #fbbf24 50%, #f59e0b 100%) !important;
            color: #1e293b !important;
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 24px rgba(234, 179, 8, 0.45), 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        /* Pending Badge Styling - Blue with aesthetic icon */
        .pending-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%) !important;
            color: #ffffff !important;
            border: none !important;
            font-weight: 700 !important;
            font-size: 0.875rem !important;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4), 0 2px 8px rgba(0, 0, 0, 0.15) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
            padding: 0.6rem 1.2rem !important;
            letter-spacing: 0.5px;
        }

        .pending-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .pending-badge:hover::before {
            left: 100%;
        }

        .pending-badge:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5), 0 4px 12px rgba(0, 0, 0, 0.2) !important;
        }

        .pending-badge i {
            font-size: 1rem !important;
            animation: pulse-icon 2s ease-in-out infinite;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        @keyframes pulse-icon {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.9;
            }
        }

        /* Refresh header button styling */
        .refresh-header-btn {
            border: 2px solid #3b82f6 !important;
            color: #3b82f6 !important;
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px) !important;
            border-radius: 10px !important;
            padding: 0.5rem 0.75rem !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2) !important;
        }

        .refresh-header-btn:hover {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            color: #ffffff !important;
            border-color: #3b82f6 !important;
            transform: translateY(-2px) rotate(180deg) scale(1.1);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4), 0 2px 8px rgba(0, 0, 0, 0.1) !important;
        }

        .refresh-header-btn i {
            transition: transform 0.3s ease !important;
        }
    </style>
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
                            <h1 class="mb-1">Blood Requests Management</h1>
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
            <div class="card border-0 shadow-custom mb-4 slide-up">
                <div class="card-header bg-gradient-light border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-1 text-primary-custom">
                                <i class="bi bi-clipboard2-pulse me-2"></i>
                                All Blood Requests
                            </h4>
                            <p class="text-muted mb-0">Manage and issue referrals from patients
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert" id="successAlert">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert" id="errorAlert">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (is_array($bloodRequests) && count($bloodRequests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th class="fw-bold text-muted">Request Date</th>
                                        <th class="fw-bold text-muted">Patient</th>
                                        <th class="fw-bold text-muted">Blood Type</th>
                                        <th class="fw-bold text-muted">Units</th>
                                       
                                        <th class="fw-bold text-muted">Status</th>
                                        <th class="fw-bold text-muted">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bloodRequests as $request): ?>
                                        <tr class="hover-row">
                                            <td data-label="Request Date">
                                                <div class="fw-bold"><?php echo date('M d, Y', strtotime($request['request_date'])); ?></div>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($request['request_date'])); ?></small>
                                            </td>
                                            <td data-label="Patient">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-3">
                                                        <?php echo strtoupper(substr($request['patient_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($request['patient_name']); ?></h6>
                                                        <small class="text-muted">
                                                            <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($request['phone']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Blood Type">
                                                <span class="badge-blood-type"><?php echo htmlspecialchars($request['blood_type']); ?></span>
                                            </td>
                                            <td data-label="Units">
                                                <span class="fw-bold text-primary-custom"><?php echo htmlspecialchars($request['units_requested']); ?></span>
                                                <small class="text-muted d-block">units</small>
                                            </td>
                                           
                                            <td data-label="Status">
                                                <?php
                                                $status = strtolower($request['status'] ?? 'pending');
                                                $referralStatus = strtolower($request['referral_status'] ?? '');
                                                
                                                // Determine display status: if there's a referral, show referral info, otherwise show main status
                                                $displayStatus = $status;
                                                $statusClass = '';
                                                $statusIcon = '';
                                                
                                                // Check if request has been referred (has referral record)
                                                $hasReferral = !empty($request['referral_status']);
                                                
                                                switch($status) {
                                                    case 'pending':
                                                        if ($hasReferral) {
                                                            // If referred, show as "Referred" with info badge
                                                            $statusClass = 'badge bg-info text-dark';
                                                            $statusIcon = 'bi-arrow-right-circle';
                                                            $displayStatus = 'referred';
                                                        } else {
                                                            // Pending and not referred yet
                                                            $statusClass = 'badge bg-warning text-dark';
                                                            $statusIcon = 'bi-clock';
                                                        }
                                                        break;
                                                    case 'approved':
                                                        $statusClass = 'badge bg-primary text-white';
                                                        $statusIcon = 'bi-check-circle';
                                                        break;
                                                    case 'rejected':
                                                        $statusClass = 'badge bg-danger text-white';
                                                        $statusIcon = 'bi-x-circle';
                                                        break;
                                                    case 'completed':
                                                        $statusClass = 'badge bg-success text-white';
                                                        $statusIcon = 'bi-check-circle-fill';
                                                        break;
                                                    default:
                                                        $statusClass = 'badge bg-secondary text-white';
                                                        $statusIcon = 'bi-question-circle';
                                                }
                                                ?>
                                                <span class="<?php echo $statusClass; ?>">
                                                    <i class="bi <?php echo $statusIcon; ?> me-1"></i><?php echo ucfirst($displayStatus); ?>
                                                </span>
                                            </td>
                                            <td data-label="Actions">
                                                <?php if ($status === 'pending' && !$hasReferral): ?>
                                                    <button type="button" class="btn btn-primary btn-sm referral-btn"
                                                        data-request-id="<?php echo $request['id']; ?>"
                                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                                        data-organization="<?php echo htmlspecialchars($request['organization_type'] ?? ''); ?>"
                                                        data-blood-type="<?php echo htmlspecialchars($request['blood_type']); ?>"
                                                        data-phone="<?php echo htmlspecialchars($request['phone']); ?>"
                                                        data-units-requested="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                                        data-request-date="<?php echo htmlspecialchars($request['request_date']); ?>"
                                                        data-urgency="<?php echo htmlspecialchars($request['urgency']); ?>"
                                                        data-reason="<?php echo htmlspecialchars($request['reason']); ?>"
                                                        data-request-form-path="<?php echo htmlspecialchars($request['request_form_path'] ?? ''); ?>">
                                                        <i class="bi bi-send me-1"></i>Refer
                                                    </button>
                                                <?php elseif ($hasReferral && $status === 'pending'): ?>
                                                    <!-- No actions for referred requests -->
                                                    <span class="text-muted">-</span>
                                                <?php else: ?>
                                                    <button class="btn btn-outline btn-sm" disabled>
                                                        <i class="bi bi-check-circle-fill me-1"></i>Processed
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-4">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <h5 class="text-primary-custom mb-3">No Blood Requests Found</h5>
                            <p class="text-muted mb-4">There are currently no blood requests from patients in your community.</p>
                            <div class="d-flex justify-content-center gap-2">
                                <button class="btn btn-outline-primary btn-sm refresh-btn">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary btn-sm back-btn">
                                    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Referral Modal (copied and adapted from index.php in barangay dashboard) -->
<div class="modal fade" id="referralModal" tabindex="-1" aria-labelledby="referralModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient-primary text-white border-0">
                <h5 class="modal-title fw-bold" id="referralModalLabel">
                    <i class="bi bi-send me-2"></i>Issue Blood Bank Referral
                </h5>
                <button type="button" class="btn-close btn-close-white" aria-label="Close" id="modalCloseBtn" data-bs-dismiss="modal"></button>
            </div>
            <form action="process-referral.php" method="post" enctype="multipart/form-data" id="referralForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="request_id" id="modalRequestId">
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="info-section">
                                <h6 class="section-title mb-3">
                                    <i class="bi bi-person-fill me-2"></i>Patient Information
                                </h6>
                                <div class="info-card p-3 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person text-primary me-2"></i>
                                        <div>
                                            <div class="fw-bold">Patient Name: <span id="modalPatientName" class="ms-2 fw-semibold"></span></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-card p-3 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-droplet-fill text-danger me-2"></i>
                                        <div>
                                            <div class="fw-bold">Blood Type: <span id="modalBloodType" class="ms-2 fw-semibold"></span></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-card p-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-telephone text-info me-2"></i>
                                        <div>
                                            <div class="fw-bold">Contact: <span id="modalPhone" class="ms-2 fw-semibold"></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-section">
                                <h6 class="section-title mb-3">
                                    <i class="bi bi-clipboard-data me-2"></i>Request Details
                                </h6>
                                <div class="info-card p-3 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-hash text-warning me-2"></i>
                                        <div>
                                            <div class="fw-bold">Units Needed: <span id="modalUnitsRequested" class="ms-2 fw-semibold"></span></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-card p-3 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar text-success me-2"></i>
                                        <div>
                                            <div class="fw-bold">Date Requested: <span id="modalRequestDate" class="ms-2 fw-semibold"></span></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-card p-3 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-chat-text text-secondary me-2"></i>
                                        <div>
                                            <div class="fw-bold">Reason: <span id="modalReason" class="ms-2 fw-semibold"></span></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-card p-3" id="modalDocumentsSection">
                                    <h6 class="fw-bold mb-2">Documents</h6>
                                    <div id="modalRequestFormLink" class="mb-2">
                                        <a href="#" id="requestFormLink" target="_blank" class="btn btn-sm btn-outline-primary" rel="noopener noreferrer" onclick="event.stopPropagation(); return true;">
                                            <i class="bi bi-file-earmark-pdf me-1"></i>View Hospital Request Form
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="referral-form-section">
                        <h6 class="section-title mb-3">
                            <i class="bi bi-hospital me-2"></i>Referral Information
                        </h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="bloodBankSelect" class="form-label fw-bold">
                                    <i class="bi bi-building me-1"></i>Select Blood Bank:
                                </label>
                                <select class="form-select form-select-enhanced" id="bloodBankSelect" name="blood_bank_id" required>
                                    <option value="">-- Select Blood Bank --</option>
                                    <?php foreach ($bloodBanks as $bank): ?>
                                        <option value="<?php echo $bank['id']; ?>"><?php echo $bank['name']; ?> - <?php echo $bank['address']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the blood bank where the patient will receive blood.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="referralDocument" class="form-label fw-bold">
                                    <i class="bi bi-file-earmark-arrow-up me-1"></i>Upload Referral Document:
                                </label>
                                <input class="form-control form-control-enhanced" type="file" id="referralDocument" name="referral_document" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="form-text">Accepted formats: PDF, JPG, PNG. Max size: 5MB.</div>
                            </div>
                            <!-- Referral date removed from modal UI; system will set referral date server-side -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-4">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-outline-secondary flex-fill" id="modalCancelBtn" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary-custom flex-fill">
                            <i class="bi bi-send me-1"></i>Issue Referral
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</body>

<!-- Bootstrap JS (required for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Global modal variable
var referralModal = null;

// Function to close modal - more robust
function closeReferralModal() {
    var modalEl = document.getElementById('referralModal');
    if (!modalEl) {
        // If modal doesn't exist, just refresh
        window.location.reload();
        return;
    }
    
    // Try multiple methods to ensure it closes
    try {
        // Method 1: Use global modal instance
        if (referralModal) {
            referralModal.hide();
            // Wait a bit and force close if needed, then refresh
            setTimeout(function() {
                if (modalEl.classList.contains('show')) {
                    forceCloseModal(modalEl);
                }
                // Refresh the page after closing
                window.location.reload();
            }, 100);
            return;
        }
        
        // Method 2: Get Bootstrap instance
        var bsModal = bootstrap.Modal.getInstance(modalEl);
        if (bsModal) {
            bsModal.hide();
            setTimeout(function() {
                if (modalEl.classList.contains('show')) {
                    forceCloseModal(modalEl);
                }
                // Refresh the page after closing
                window.location.reload();
            }, 100);
            return;
        }
        
        // Method 3: Create new instance and hide
        var newModal = new bootstrap.Modal(modalEl);
        newModal.hide();
        setTimeout(function() {
            if (modalEl.classList.contains('show')) {
                forceCloseModal(modalEl);
            }
            // Refresh the page after closing
            window.location.reload();
        }, 100);
        return;
    } catch (e) {
        console.error('Error closing modal:', e);
        forceCloseModal(modalEl);
        // Refresh the page even if there was an error
        setTimeout(function() {
            window.location.reload();
        }, 100);
    }
}

function forceCloseModal(modalEl) {
    if (!modalEl) return;
    
    // Force remove all modal classes and attributes
    modalEl.classList.remove('show');
    modalEl.classList.remove('fade');
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.setAttribute('style', 'display: none !important; padding-right: 0px !important;');
    modalEl.removeAttribute('aria-modal');
    
    // Remove body classes
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    // Remove all backdrops
    var backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(function(backdrop) {
        backdrop.remove();
    });
    
    // Remove any inline styles from body
    if (document.body.style.paddingRight === '0px' || document.body.style.paddingRight === '') {
        document.body.style.paddingRight = '';
    }
}

// Auto-dismiss alert messages after 5 seconds
function autoDismissAlerts() {
    var successAlert = document.getElementById('successAlert');
    var errorAlert = document.getElementById('errorAlert');
    
    if (successAlert) {
        setTimeout(function() {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(successAlert);
            if (bsAlert) {
                bsAlert.close();
            } else {
                successAlert.style.display = 'none';
            }
        }, 5000); // 5 seconds
    }
    
    if (errorAlert) {
        setTimeout(function() {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(errorAlert);
            if (bsAlert) {
                bsAlert.close();
            } else {
                errorAlert.style.display = 'none';
            }
        }, 5000); // 5 seconds
    }
}

// Wait for Bootstrap to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts
    autoDismissAlerts();
    
    // Handle form submission
    var referralForm = document.getElementById('referralForm');
    if (referralForm) {
        referralForm.addEventListener('submit', function(e) {
            // Let the form submit normally (it will redirect via process-referral.php)
            // The page will refresh automatically after the redirect
        });
    }
    
    // Wait a bit for Bootstrap to initialize
    setTimeout(function() {
        // Initialize modal once outside the loop
        var referralModalEl = document.getElementById('referralModal');
        if (!referralModalEl) {
            console.error('Referral modal element not found');
            return;
        }
        
        try {
            referralModal = new bootstrap.Modal(referralModalEl, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
        } catch (e) {
            console.error('Error initializing modal:', e);
        }
        
        // Ensure close buttons work - add explicit listeners as backup
        var modalCloseBtn = document.getElementById('modalCloseBtn');
        var modalCancelBtn = document.getElementById('modalCancelBtn');
        
        // Use Bootstrap's native dismiss first, then force close if needed, then refresh
        if (modalCloseBtn) {
            modalCloseBtn.addEventListener('click', function(e) {
                // Let Bootstrap handle it first, then force close after a delay, then refresh
                setTimeout(function() {
                    if (referralModalEl.classList.contains('show')) {
                        forceCloseModal(referralModalEl);
                    }
                    // Refresh the page after closing
                    window.location.reload();
                }, 200);
            });
        }
        
        if (modalCancelBtn) {
            modalCancelBtn.addEventListener('click', function(e) {
                // Let Bootstrap handle it first, then force close after a delay, then refresh
                setTimeout(function() {
                    if (referralModalEl.classList.contains('show')) {
                        forceCloseModal(referralModalEl);
                    }
                    // Refresh the page after closing
                    window.location.reload();
                }, 200);
            });
        }
        
        // Also handle any buttons with data-bs-dismiss as backup
        var dismissButtons = referralModalEl.querySelectorAll('[data-bs-dismiss="modal"]');
        dismissButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                // Let Bootstrap handle it first, then force close after a delay, then refresh
                setTimeout(function() {
                    if (referralModalEl.classList.contains('show')) {
                        forceCloseModal(referralModalEl);
                    }
                    // Refresh the page after closing
                    window.location.reload();
                }, 200);
            });
        });
        
        // Handle backdrop click - refresh on backdrop close
        referralModalEl.addEventListener('click', function(e) {
            if (e.target === referralModalEl) {
                closeReferralModal();
            }
        });
        
        // Handle ESC key - refresh on ESC close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && referralModalEl.classList.contains('show')) {
                closeReferralModal();
            }
        });
        
        // Ensure close buttons work
        referralModalEl.addEventListener('hidden.bs.modal', function() {
            // Reset form if needed
            var form = referralModalEl.querySelector('form');
            if (form) {
                form.reset();
            }
        });
        
        // Initialize notification bell dropdown
        var notificationDropdown = document.getElementById('notificationDropdown');
        if (notificationDropdown) {
            try {
                // Initialize Bootstrap dropdown
                var notificationDropdownInstance = new bootstrap.Dropdown(notificationDropdown);
                
                // Ensure dropdown opens on click
                notificationDropdown.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (notificationDropdownInstance) {
                        notificationDropdownInstance.toggle();
                    }
                });
            } catch (e) {
                console.error('Error initializing notification dropdown:', e);
            }
        }

        // Set up referral button handlers
        document.querySelectorAll('.referral-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var org = btn.getAttribute('data-organization') || '';
            var modalRequestId = document.getElementById('modalRequestId');
            var modalPatientName = document.getElementById('modalPatientName');
            var modalBloodType = document.getElementById('modalBloodType');
            var modalPhone = document.getElementById('modalPhone');
            var modalUnitsRequested = document.getElementById('modalUnitsRequested');
            var modalRequestDate = document.getElementById('modalRequestDate');
            var modalReason = document.getElementById('modalReason');

            if (!modalRequestId || !modalPatientName || !modalBloodType || !modalPhone || !modalUnitsRequested || !modalRequestDate || !modalReason) {
                console.error('Modal elements not found');
                return;
            }

            modalRequestId.value = btn.getAttribute('data-request-id');
            modalPatientName.textContent = btn.getAttribute('data-patient-name');
            modalBloodType.textContent = btn.getAttribute('data-blood-type');
            modalPhone.textContent = btn.getAttribute('data-phone');
            modalUnitsRequested.textContent = btn.getAttribute('data-units-requested');

            // Format request date nicely if provided
            var reqDate = btn.getAttribute('data-request-date') || '';
            var reqTime = btn.getAttribute('data-required-time') || '';
            var reqDisplay = '';
            if (reqDate) {
                try {
                    var d = new Date(reqDate);
                    reqDisplay = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                } catch (e) {
                    reqDisplay = reqDate;
                }
            }
            if (reqTime) {
                try {
                    var t = new Date('1970-01-01T' + reqTime);
                    reqDisplay += (reqDisplay ? ' ' : '') + t.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                } catch (e) {
                    reqDisplay += (reqDisplay ? ' ' : '') + reqTime;
                }
            }
            modalRequestDate.textContent = reqDisplay;
            modalReason.textContent = btn.getAttribute('data-reason');

            // Handle document links
            var requestFormPath = btn.getAttribute('data-request-form-path') || '';
            var requestFormLink = document.getElementById('requestFormLink');
            var requestFormLinkDiv = document.getElementById('modalRequestFormLink');
            var requestId = btn.getAttribute('data-request-id');

            // Always show and set request form link if requestId exists
            if (requestId && requestFormLink && requestFormLinkDiv) {
                var linkUrl = 'view-request-form.php?request_id=' + encodeURIComponent(requestId);
                requestFormLink.href = linkUrl;
                requestFormLinkDiv.style.display = 'block';
                
                // Remove any existing click handlers and ensure the link works
                requestFormLink.onclick = function(e) {
                    e.stopPropagation();
                    window.open(linkUrl, '_blank');
                    return false;
                };
                
                console.log('Request Form Link set:', linkUrl, 'Path from data:', requestFormPath);
            } else {
                if (requestFormLinkDiv) {
                    requestFormLinkDiv.style.display = 'none';
                }
                if (requestFormLink) {
                    requestFormLink.href = '#';
                    requestFormLink.onclick = null;
                }
            }

            // Auto-select blood bank based on patient's chosen organization (organization_type)
            var bloodBankSelect = document.getElementById('bloodBankSelect');
            if (bloodBankSelect && org) {
                var found = false;
                var orgLower = org.toLowerCase();
                for (var i = 0; i < bloodBankSelect.options.length; i++) {
                    var opt = bloodBankSelect.options[i];
                    var txt = (opt.text || '').toLowerCase();
                    if (orgLower.indexOf('redcross') !== -1 || orgLower.indexOf('red cross') !== -1) {
                        if (txt.indexOf('red cross') !== -1 || txt.indexOf('redcross') !== -1) { opt.selected = true; found = true; break; }
                    } else if (orgLower.indexOf('negros') !== -1 || orgLower.indexOf('negrosfirst') !== -1 || orgLower.indexOf('negros first') !== -1) {
                        if (txt.indexOf('negros') !== -1) { opt.selected = true; found = true; break; }
                    }
                }
            }

            // Show the modal
            referralModal.show();
        });
        });
        
    }, 100); // Small delay to ensure Bootstrap is loaded
});
</script>
<?php include_once '../../includes/footer.php'; ?>