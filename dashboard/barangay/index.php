<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

// Set dashboard flag
$isDashboard = true;
$pageTitle = "Barangay Dashboard - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get barangay information
$barangayId = $_SESSION['user_id'];
$barangayRow = getRow("SELECT * FROM barangay_users WHERE id = ?", [$barangayId]);
$barangayName = $barangayRow['name'] ?? 'Barangay';

// Debug: Log barangay ID
secure_log("Barangay ID for pending requests: " . $barangayId);

// Get statistics
$totalDonors = getRow("SELECT COUNT(*) as count FROM donor_users WHERE barangay_id = ?", [$barangayId]);
$totalDonors = $totalDonors ? $totalDonors['count'] : 0;

$totalDonations = getRow("SELECT COUNT(*) as count FROM donations WHERE barangay_id = ?", [$barangayId]);
$totalDonations = $totalDonations ? $totalDonations['count'] : 0;

$totalDrives = getRow("SELECT COUNT(*) as count FROM blood_drives WHERE barangay_id = ?", [$barangayId]);
$totalDrives = $totalDrives ? $totalDrives['count'] : 0;

// Get blood requests count
$totalRequests = getRow("SELECT COUNT(*) as count FROM blood_requests WHERE barangay_id = ?", [$barangayId]);
$totalRequests = $totalRequests ? $totalRequests['count'] : 0;

// Get pending blood requests for this barangay that have NOT been referred yet
// This includes only:
// 1. Requests from patients in this barangay (br.barangay_id = ?)
// 2. That are still pending (status = 'Pending')
// 3. That have NOT been referred yet (r.id IS NULL)
$pendingRequests = executeQuery("
    SELECT DISTINCT br.*, pu.name AS patient_name, pu.blood_type, pu.phone, pu.address,
        br.hospital, br.reason, br.required_date, br.required_time, br.organization_type,
        br.request_form_path, br.blood_card_path
    FROM blood_requests br
    INNER JOIN patient_users pu ON br.patient_id = pu.id
    LEFT JOIN referrals r ON br.id = r.blood_request_id
    WHERE br.barangay_id = ?
    AND (LOWER(br.status) = 'pending' OR br.status = 'Pending')
    AND r.id IS NULL
    ORDER BY br.request_date DESC, br.created_at DESC
", [$barangayId]);

// Ensure $pendingRequests is an array (handle false return from executeQuery)
if ($pendingRequests === false) {
    $pendingRequests = [];
    secure_log("Pending requests query returned false for barangay_id: " . $barangayId);
} else {
    secure_log("Pending requests found: " . count($pendingRequests) . " for barangay_id: " . $barangayId);
    // Debug: Log first few requests
    if (count($pendingRequests) > 0) {
        secure_log("First pending request barangay_id: " . ($pendingRequests[0]['barangay_id'] ?? 'NULL'));
    }
}

// Debug information
secure_log("Barangay ID: " . $barangayId);
secure_log("Pending Requests Query Result: " . print_r($pendingRequests, true));

// Check if there are any blood requests at all
$allRequests = executeQuery("
    SELECT COUNT(*) as count 
    FROM blood_requests 
    WHERE barangay_id = ?
", [$barangayId]);
secure_log("Total Requests for Barangay: " . print_r($allRequests, true));

// Check if there are any pending requests
$pendingCount = executeQuery("
    SELECT COUNT(*) as count 
    FROM blood_requests 
    WHERE status = 'Pending' AND barangay_id = ?
", [$barangayId]);
secure_log("Pending Requests Count: " . print_r($pendingCount, true));

// Get upcoming blood drives
$upcomingDrives = executeQuery("
    SELECT * FROM blood_drives
    WHERE barangay_id = ? AND date >= CURDATE()
    ORDER BY date ASC
    LIMIT 5
", [$barangayId]);

// Ensure $upcomingDrives is an array (handle false return from executeQuery)
if ($upcomingDrives === false) {
    $upcomingDrives = [];
}

// Get recent donations
$recentDonations = executeQuery("
    SELECT d.*, du.name as donor_name, du.blood_type
    FROM donations d
    JOIN donor_users du ON d.donor_id = du.id
    WHERE d.barangay_id = ?
    ORDER BY d.donation_date DESC
    LIMIT 5
", [$barangayId]);

// Ensure $recentDonations is an array (handle false return from executeQuery)
if ($recentDonations === false) {
    $recentDonations = [];
}

// Get available blood banks for referrals
$bloodBanks = executeQuery("
    SELECT * FROM blood_banks
    ORDER BY name ASC
");

// Ensure $bloodBanks is an array (handle false return from executeQuery)
if ($bloodBanks === false) {
    $bloodBanks = [];
}
// Add enhanced CSS

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
    
    <?php include_once 'shared-styles.php'; ?>

    <!-- Enhanced Custom Styles for Unique Aesthetic Design -->
    <style>
        /* Override all gray colors to white */
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
        
        .table thead th {
            background: #f8f9fa !important;
            color: #2a363b !important;
        }
        
        .table thead th.text-muted {
            color: #2a363b !important;
        }
        
        /* Card body white background */
        .card-body {
            background: #ffffff !important;
        }
        
        /* Text muted - make it darker for visibility */
        .text-muted {
            color: #64748b !important;
        }
        
        /* Background is now handled in shared-styles.php */
        .dashboard-main {
            position: relative;
            z-index: 1;
        }

        /* Enhanced Welcome Card with Glassmorphism */
        .welcome-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%) !important;
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            border-radius: 24px !important;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.1),
                0 2px 8px rgba(59, 130, 246, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.5) !important;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            display: none;
        }

        .welcome-icon-wrapper {
        }

        .welcome-card:hover .welcome-icon-wrapper {
        }

        .welcome-card:hover {
        }

        .welcome-card h3 {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700 !important;
            font-size: 1.75rem !important;
            letter-spacing: -0.5px;
        }

        /* Enhanced Stat Cards with Unique Designs */
        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%) !important;
            backdrop-filter: blur(20px) saturate(180%);
            border: 2px solid rgba(255, 255, 255, 0.5) !important;
            border-radius: 20px !important;
            box-shadow: 
                0 10px 40px rgba(0, 0, 0, 0.08),
                0 2px 12px rgba(59, 130, 246, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.6) !important;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .stat-card::after {
            display: none;
        }

        .stat-card:hover::after {
        }

        .stat-card:hover {
        }

        /* Unique Icon Backgrounds for Each Stat Card */
        .stat-icon {
            position: relative;
            z-index: 2;
        }

        .stat-card:hover .stat-icon {
        }

        .stat-icon.donors {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(37, 99, 235, 0.1) 100%) !important;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.2);
        }

        .stat-icon.donations {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.1) 100%) !important;
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.2);
        }

        .stat-icon.drives {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(37, 99, 235, 0.1) 100%) !important;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.2);
        }

        .stat-icon.requests {
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.15) 0%, rgba(251, 191, 36, 0.1) 100%) !important;
            box-shadow: 0 8px 24px rgba(234, 179, 8, 0.2);
        }

        .stat-number {
            font-size: 3rem !important;
            font-weight: 800 !important;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #eab308 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.1 !important;
            letter-spacing: -2px;
        }

        .stat-card:hover .stat-number {
        }

        .stat-label {
            font-size: 0.95rem !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b !important;
            margin-top: 0.5rem;
        }

        .stat-description {
            font-size: 0.8rem !important;
            color: #94a3b8 !important;
            margin-top: 0.25rem;
            font-weight: 500 !important;
        }

        /* Enhanced Blood Requests Section */
        #bloodRequestsSection {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%) !important;
            backdrop-filter: blur(20px) saturate(180%);
            border: 2px solid rgba(255, 255, 255, 0.5) !important;
            border-radius: 24px !important;
            box-shadow: 
                0 12px 48px rgba(0, 0, 0, 0.1),
                0 4px 16px rgba(239, 68, 68, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.6) !important;
            overflow: hidden;
        }

        #bloodRequestsSection:hover {
        }

        .card-header.bg-gradient-light {
            background: linear-gradient(135deg, rgba(248, 249, 250, 0.95) 0%, rgba(241, 245, 249, 0.9) 100%) !important;
            border-bottom: 3px solid;
            border-image: linear-gradient(90deg, #3b82f6, #eab308) 1;
            padding: 2rem !important;
        }

        .card-header.bg-gradient-light h4 {
            font-size: 1.5rem !important;
            font-weight: 700 !important;
            background: linear-gradient(135deg, #3b82f6 0%, #eab308 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Enhanced Table Design */
        .table {
            border-radius: 16px;
            overflow: hidden;
            background: transparent;
        }

        .table thead th {
            background: #f8f9fa !important;
            color: #2a363b !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem !important;
            padding: 1.25rem 1rem !important;
            border-bottom: 2px solid rgba(59, 130, 246, 0.2) !important;
        }

        .table tbody tr {
            background: #ffffff !important;
            border-bottom: 1px solid rgba(226, 232, 240, 0.5);
        }

        .table tbody tr:hover {
            background: rgba(234, 179, 8, 0.1) !important;
        }

        .table tbody td {
            padding: 1.25rem 1rem !important;
            vertical-align: middle;
            font-weight: 500 !important;
            color: #2a363b !important;
            background: transparent !important;
        }
        
        .table tbody td * {
            color: #2a363b !important;
        }

        /* Enhanced Buttons */
        .referral-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%) !important;
            border: none !important;
            color: white !important;
            padding: 0.75rem 1.5rem !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.35), 0 2px 8px rgba(0, 0, 0, 0.1) !important;
            position: relative;
            overflow: hidden;
        }

        .referral-btn::before {
            display: none;
        }

        .referral-btn:hover::before {
        }

        .referral-btn:hover {
            background: linear-gradient(135deg, #eab308 0%, #fbbf24 50%, #f59e0b 100%) !important;
            color: #1e293b !important;
        }

        /* Enhanced Recent Donations Card */
        .card.border-0.shadow-sm {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%) !important;
            backdrop-filter: blur(20px) saturate(180%);
            border: 2px solid rgba(255, 255, 255, 0.5) !important;
            border-radius: 20px !important;
            box-shadow: 
                0 10px 40px rgba(0, 0, 0, 0.08),
                0 2px 12px rgba(59, 130, 246, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.6) !important;
        }

        .card.border-0.shadow-sm:hover {
        }

        /* Enhanced Quick Actions */
        .btn-outline-primary,
        .btn-outline-danger {
            border: 2px solid !important;
            border-radius: 12px !important;
            padding: 0.875rem 1.5rem !important;
            font-weight: 600 !important;
            position: relative;
            overflow: hidden;
        }

        .btn-outline-primary::before,
        .btn-outline-danger::before {
            display: none;
        }

        .btn-outline-primary:hover::before,
        .btn-outline-danger:hover::before {
        }

        .btn-outline-primary:hover,
        .btn-outline-danger:hover {
        }

        /* Enhanced Badge */
        .badge.bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            padding: 0.5rem 1rem !important;
            font-weight: 600 !important;
            letter-spacing: 0.5px;
        }

        /* Enhanced Empty State */
        .empty-state-icon {
            font-size: 5rem !important;
            background: linear-gradient(135deg, #3b82f6 0%, #eab308 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0.6;
        }

        /* Enhanced Modal */
        .modal-content {
            border-radius: 24px !important;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .modal-header.bg-gradient-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #eab308 100%) !important;
            padding: 2rem !important;
        }

        /* No Animations */
        .slide-up {
        }

        /* No Stagger Animation for Stat Cards */

        /* Sidebar Mobile Styles - Hide by default on mobile */
        @media (max-width: 991.98px) {
            .sidebar {
                left: -280px !important;
                transition: none !important;
                z-index: 1040 !important;
                position: fixed !important;
            }
            
            .sidebar.show {
                left: 0 !important;
                box-shadow: 2px 0 15px rgba(0,0,0,0.3) !important;
            }
            
            /* Ensure sidebar is always hidden initially on mobile */
            body:not(.sidebar-open) .sidebar:not(.show) {
                left: -280px !important;
            }
            
            /* Sidebar Overlay */
            #sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1030;
                display: none;
                opacity: 0;
                transition: none !important;
            }
            
            #sidebar-overlay.show {
                display: block;
                opacity: 1;
            }
            
            body.sidebar-open {
                overflow: hidden;
            }
            
            body.sidebar-open #sidebar-overlay {
                display: block;
                opacity: 1;
            }
        }
        
        /* Hamburger Menu Button */
        .header-toggle,
        .mobile-toggle {
            display: none;
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            z-index: 1062 !important;
            width: 44px;
            height: 44px;
            border-radius: 0.5rem;
            background: rgba(255, 255, 255, 0.2) !important;
            backdrop-filter: blur(10px);
            color: #fff !important;
            border: 2px solid rgba(255, 255, 255, 0.3) !important;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: none !important;
            pointer-events: auto !important;
            touch-action: manipulation;
            padding: 0;
            font-size: 1.25rem;
        }
        
        .header-toggle:hover,
        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.3) !important;
        }
        
        .header-toggle:active,
        .mobile-toggle:active {
        }
        
        .header-toggle i,
        .mobile-toggle i {
            font-size: 1.5rem;
            line-height: 1;
            display: block;
            color: #fff !important;
        }
        
        /* Responsive Enhancements */
        @media (max-width: 991.98px) {
            /* Show hamburger button */
            .header-toggle,
            .mobile-toggle {
                display: flex !important;
            }
            
            /* Dashboard Header - Full width on tablet and mobile */
            .dashboard-header {
                left: 0 !important;
                right: 0 !important;
                border-radius: 0 !important;
                padding: 1rem 1.5rem 1rem 4rem !important;
                min-height: 80px !important;
            }
            
            .dashboard-content {
                margin-left: 0 !important;
                padding-top: 100px !important;
            }
            
            /* Ensure header content doesn't overlap hamburger */
            .dashboard-header .header-content {
                padding-left: 0 !important;
            }
            
            /* Header content adjustments */
            .header-content h1 {
                font-size: 1.5rem !important;
            }
            
            .header-actions {
                flex-wrap: wrap;
                gap: 0.75rem !important;
            }
            
            /* Welcome card adjustments */
            .welcome-card {
                padding: 1.5rem !important;
            }
            
            .welcome-card h3 {
                font-size: 1.5rem !important;
            }
            
            .welcome-icon-wrapper {
                width: 100px !important;
                height: 100px !important;
            }
            
            .welcome-icon-wrapper i {
                font-size: 2.5rem !important;
            }
            
            /* Stat cards - 2 columns on tablet */
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .stat-number {
                font-size: 2.5rem !important;
            }
            
            .stat-card:hover {
                transform: translateY(-8px) scale(1.02) !important;
            }
        }
        
        @media (max-width: 768px) {
            /* Dashboard Header - Mobile adjustments */
            .dashboard-header {
                padding: 0.75rem 1rem !important;
                min-height: 70px !important;
            }
            
            .dashboard-content {
                padding-top: 90px !important;
            }
            
            /* Header content - Stack on mobile */
            .header-content h1 {
                font-size: 1.25rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            .header-content .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 0.5rem !important;
            }
            
            .status-indicator {
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
            }
            
            .header-content small {
                font-size: 0.75rem !important;
            }
            
            /* Header actions - Stack vertically on mobile */
            .header-actions {
                flex-direction: row !important;
                align-items: center !important;
                flex-wrap: wrap !important;
                gap: 0.5rem !important;
            }
            
            .user-dropdown {
                width: 100%;
                justify-content: space-between;
            }
            
            /* Welcome card - Full width, stacked content */
            .welcome-card {
                padding: 1.25rem !important;
            }
            
            .welcome-card .row {
                flex-direction: column-reverse;
            }
            
            .welcome-card .col-md-8,
            .welcome-card .col-md-4 {
                width: 100%;
                max-width: 100%;
                flex: 0 0 100%;
            }
            
            .welcome-card .col-md-4 {
                margin-bottom: 1rem;
            }
            
            .welcome-card h3 {
                font-size: 1.25rem !important;
            }
            
            .welcome-card p {
                font-size: 0.875rem;
            }
            
            .welcome-icon-wrapper {
                width: 80px !important;
                height: 80px !important;
            }
            
            .welcome-icon-wrapper i {
                font-size: 2rem !important;
            }
            
            /* Stat cards - Single column on mobile */
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .stat-number {
                font-size: 2rem !important;
            }
            
            .stat-label {
                font-size: 0.875rem !important;
            }
            
            .stat-description {
                font-size: 0.75rem !important;
            }
            
            /* Blood Requests Section */
            #bloodRequestsSection {
                border-radius: 16px !important;
            }
            
            .card-header.bg-gradient-light {
                padding: 1.25rem !important;
            }
            
            .card-header.bg-gradient-light h4 {
                font-size: 1.25rem !important;
            }
            
            .card-header.bg-gradient-light .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 1rem;
            }
            
            .pending-badge {
                font-size: 0.75rem !important;
                padding: 0.5rem 1rem !important;
            }
            
            /* Tables - Horizontal scroll on mobile */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table {
                min-width: 600px;
                font-size: 0.875rem;
            }
            
            .table thead th {
                padding: 0.75rem 0.5rem !important;
                font-size: 0.75rem !important;
            }
            
            .table tbody td {
                padding: 0.75rem 0.5rem !important;
                font-size: 0.875rem;
            }
            
            /* Buttons - Full width on mobile */
            .referral-btn {
                width: 100%;
                padding: 0.625rem 1rem !important;
                font-size: 0.875rem;
            }
            
            /* Recent Donations and Quick Actions - Stack on mobile */
            .row.g-4 > .col-md-8,
            .row.g-4 > .col-md-4 {
                margin-bottom: 1.5rem;
            }
            
            /* Quick Actions - Full width buttons */
            .card-body .d-grid .btn {
                width: 100%;
            }
            
            /* Modal adjustments */
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .modal-content {
                border-radius: 16px !important;
            }
            
            .modal-header {
                padding: 1.25rem !important;
            }
            
            .modal-body {
                padding: 1rem !important;
            }
            
            .modal-footer {
                padding: 1rem !important;
                flex-direction: column;
            }
            
            .modal-footer .btn {
                width: 100%;
                margin: 0.25rem 0;
            }
            
            /* Alert adjustments */
            .alert {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
            
            /* Empty state adjustments */
            .empty-state-icon {
                font-size: 3.5rem !important;
            }
        }
        
        @media (max-width: 576px) {
            /* Extra small devices */
            .dashboard-header {
                padding: 0.5rem 0.75rem !important;
                min-height: 60px !important;
            }
            
            .dashboard-content {
                padding-top: 80px !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            
            .dashboard-main {
                padding: 0.75rem !important;
            }
            
            .header-content h1 {
                font-size: 1.125rem !important;
            }
            
            .welcome-card {
                padding: 1rem !important;
                border-radius: 16px !important;
            }
            
            .welcome-card h3 {
                font-size: 1.125rem !important;
            }
            
            .stat-number {
                font-size: 1.75rem !important;
            }
            
            .stat-icon {
                width: 50px !important;
                height: 50px !important;
            }
            
            .stat-icon i {
                font-size: 1.5rem !important;
            }
            
            .card-header {
                padding: 1rem !important;
            }
            
            .card-body {
                padding: 1rem !important;
            }
            
            .table {
                font-size: 0.8125rem;
            }
            
            .btn {
                padding: 0.5rem 1rem !important;
                font-size: 0.875rem;
            }
        }

        /* Enhanced Colorful Header - Blue & Yellow Theme - Positioned beside sidebar */
        .dashboard-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 30%, #eab308 70%, #fbbf24 100%) !important;
            backdrop-filter: blur(20px) saturate(180%);
            border: none !important;
            border-radius: 0 20px 20px 0 !important;
            box-shadow: 
                0 12px 48px rgba(59, 130, 246, 0.3),
                0 4px 16px rgba(234, 179, 8, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
            position: fixed !important;
            top: 0 !important;
            left: 280px !important;
            right: 0 !important;
            height: auto !important;
            min-height: 100px !important;
            z-index: 1010 !important;
            overflow: visible !important;
            padding: 1.5rem 2rem !important;
            margin: 0 !important;
        }
        
        .dashboard-content {
            margin-left: 280px !important;
            padding-top: 120px !important;
        }

        .dashboard-header::before {
            display: none;
        }

        .dashboard-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .header-content h1 {
            color: white !important;
            font-weight: 800 !important;
            font-size: 2rem !important;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            letter-spacing: -0.5px;
            margin-bottom: 0.75rem !important;
        }

        .header-content .text-muted,
        .header-content small {
            color: rgba(255, 255, 255, 0.9) !important;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
            font-weight: 500 !important;
        }

        .status-indicator {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .status-indicator span {
            color: white !important;
            font-weight: 600 !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .status-dot {
            background: #10b981 !important;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
            width: 8px !important;
            height: 8px !important;
        }

        .header-actions {
            position: relative;
            z-index: 1020 !important;
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            flex-wrap: nowrap !important;
            gap: 0.75rem !important;
        }

        /* User Dropdown - Ensure it's visible */
        .dropdown {
            position: relative !important;
            z-index: 1020 !important;
        }

        .user-dropdown {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(20px) saturate(180%);
            border: 2px solid rgba(255, 255, 255, 0.5) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
            position: relative !important;
            z-index: 1021 !important;
        }

        .user-dropdown:hover {
            background: rgba(255, 255, 255, 1) !important;
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2) !important;
        }

        /* Aesthetic Avatar Icon */
        .user-dropdown .avatar {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%) !important;
            border-radius: 10px !important;
            padding: 0.4rem !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3) !important;
            transition: all 0.3s ease !important;
        }

        .user-dropdown:hover .avatar {
        }

        .user-dropdown .avatar i {
            color: #ffffff !important;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        /* Dropdown header icon */
        .avatar-icon-wrapper {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%) !important;
            border-radius: 12px !important;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3) !important;
        }

        .avatar-icon-wrapper i {
            font-size: 1.5rem;
            color: #ffffff !important;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        /* Dropdown item icons */
        .dropdown-item i {
            width: 20px;
            text-align: center;
            transition: all 0.3s ease !important;
        }

        .dropdown-item:hover i {
            transform: scale(1.2);
            color: #3b82f6 !important;
        }

        .dropdown-item[href*="profile"] i {
            color: #3b82f6 !important;
        }

        .dropdown-item[href*="notifications"] i {
            color: #eab308 !important;
        }

        .dropdown-item.text-danger i {
            color: #ef4444 !important;
        }

        /* Notification Bell Icon Styling - Override for visible bell */
        .notification-bell-btn i {
            color: #3b82f6 !important;
            filter: drop-shadow(0 2px 4px rgba(59, 130, 246, 0.3));
        }

        .notification-bell-btn:hover i {
            color: #eab308 !important;
        }

        /* No Notification badge animation */
        #notificationBadge {
        }

        /* Pending Badge Styling - Blue with aesthetic icon */
        .pending-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%) !important;
            color: #ffffff !important;
            border: none !important;
            font-weight: 700 !important;
            font-size: 0.875rem !important;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4), 0 2px 8px rgba(0, 0, 0, 0.15) !important;
            position: relative;
            overflow: hidden;
            padding: 0.6rem 1.2rem !important;
            letter-spacing: 0.5px;
        }

        .pending-badge::before {
            display: none;
        }

        .pending-badge:hover::before {
        }

        .pending-badge:hover {
        }

        .pending-badge i {
            font-size: 1rem !important;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        /* User Dropdown Menu - Hidden by default, shown only on click */
        .dropdown-menu {
            z-index: 1050 !important;
            position: absolute !important;
            display: none !important; /* Hidden by default */
            visibility: hidden !important;
            opacity: 0 !important;
            background: #ffffff !important;
            backdrop-filter: blur(20px) saturate(180%) !important;
            border: 2px solid rgba(59, 130, 246, 0.2) !important;
            border-radius: 12px !important;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2), 0 4px 16px rgba(59, 130, 246, 0.15) !important;
            margin-top: 0.5rem !important;
            min-width: 250px !important;
            transition: none !important;
        }

        /* Show dropdown menu only when it has the .show class (when clicked) */
        .dropdown-menu.show {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /* Notification Dropdown - Hidden by default, shown only on click */
        #notificationDropdown {
            position: relative !important;
            z-index: 1021 !important;
        }

        #notificationDropdownMenu,
        .notification-dropdown {
            z-index: 1050 !important;
            position: absolute !important;
            display: none !important; /* Hidden by default */
            visibility: hidden !important;
            opacity: 0 !important;
        }

        /* Show notification dropdown only when it has the .show class */
        #notificationDropdownMenu.show,
        .notification-dropdown.show {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /* Ensure dropdown items are visible */
        .dropdown-item {
            color: #2a363b !important;
            background: transparent !important;
            padding: 0.75rem 1.25rem !important;
            transition: none !important;
        }

        .dropdown-item:hover,
        .dropdown-item:focus {
            background: rgba(59, 130, 246, 0.1) !important;
            color: #1e40af !important;
        }

        .dropdown-header {
            color: #2a363b !important;
            background: transparent !important;
            font-weight: 600 !important;
            padding: 0.75rem 1.25rem !important;
        }

        .dropdown-divider {
            margin: 0.5rem 0 !important;
            border-color: rgba(59, 130, 246, 0.2) !important;
        }

        /* Custom Scrollbar */
        .dashboard-main::-webkit-scrollbar {
            width: 8px;
        }

        .dashboard-main::-webkit-scrollbar-track {
            background: rgba(241, 245, 249, 0.5);
            border-radius: 10px;
        }

        .dashboard-main::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3b82f6 0%, #eab308 100%);
            border-radius: 10px;
        }

        .dashboard-main::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #eab308 0%, #3b82f6 100%);
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
                            <h1 class="mb-1">BHW Dashboard</h1>
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
            <!-- Welcome Banner -->
            <div class="welcome-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3>Welcome, <?php echo htmlspecialchars($barangayName); ?>!</h3>
                        <p>Manage blood donation drives and coordinate with donors in your community through our enhanced portal.</p>

                        <?php if (is_array($pendingRequests) && count($pendingRequests) > 0): ?>
                            <div class="alert-soft" role="alert">
                                <!-- Add data-bs-dismiss so the close button actually dismisses the alert -->
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                You have <strong><?php echo count($pendingRequests); ?></strong> pending blood request(s) that need your attention.
                                <a href="#bloodRequestsSection" class="fw-medium text-decoration-none ms-2">View now →</a>
                            </div>
                        <?php elseif (is_array($upcomingDrives) && count($upcomingDrives) > 0): ?>
                            <div class="alert alert-info border-0">
                                <i class="bi bi-calendar-check me-2"></i>
                                You have an upcoming blood drive on
                                <strong><?php echo date('F j, Y', strtotime($upcomingDrives[0]['date'])); ?></strong>
                                at <strong><?php echo $upcomingDrives[0]['location']; ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success border-0">
                                <i class="bi bi-check-circle me-2"></i>
                                All systems running smoothly. Ready to help save lives in your community!
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="position-relative">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle welcome-icon-wrapper" style="width: 120px; height: 120px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #eab308 100%); color: white; box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3); position: relative; overflow: hidden;">
                                <div class="position-absolute" style="display: none;"></div>
                                <i class="bi bi-buildings" style="font-size: 3rem; position: relative; z-index: 1;"></i>
                            </div>
                            <div class="position-absolute" style="top: -8px; right: -8px; z-index: 10;">
                                <span class="badge bg-success rounded-pill px-3 py-2" style="box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);">
                                    <i class="bi bi-check-circle me-1"></i>Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Blood Requests Section -->
            <div id="bloodRequestsSection" class="card border-0 shadow-custom mb-4 slide-up">
                <div class="card-header bg-gradient-light border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-1 text-primary-custom">
                                <i class="bi bi-clipboard2-pulse me-2"></i>
                                Blood Requests
                            </h4>
                            <p class="text-muted mb-0">Review and process referral from patients</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge pending-badge rounded-pill px-3 py-2">
                                <i class="bi bi-clock-fill me-1"></i><?php echo is_array($pendingRequests) ? count($pendingRequests) : 0; ?> Pending
                            </span>
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="Refresh requests">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if (is_array($pendingRequests) && count($pendingRequests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                        <th class="fw-bold text-muted">Patient</th>
                            <th class="fw-bold text-muted">Required By</th>
                                        <th class="fw-bold text-muted">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingRequests as $index => $request): ?>
                                        <tr>
                                            <td><?php echo $request['patient_name']; ?></td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($request['required_date'] ?? $request['request_date'])); ?>
                                                <br>
                                                <small class="text-muted"><?php echo !empty($request['required_time']) ? date('g:i A', strtotime($request['required_time'])) : ''; ?></small>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary referral-btn"
                                                    data-request-id="<?php echo $request['id']; ?>"
                                                    data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                                    data-organization="<?php echo htmlspecialchars($request['organization_type'] ?? ''); ?>"
                                                    data-blood-type="<?php echo htmlspecialchars($request['blood_type']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($request['phone']); ?>"
                                                    data-units-requested="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                                    data-required-date="<?php echo htmlspecialchars($request['required_date'] ?? $request['request_date']); ?>"
                                                    data-required-time="<?php echo htmlspecialchars($request['required_time'] ?? ''); ?>"
                                                    data-urgency="<?php echo htmlspecialchars($request['urgency']); ?>"
                                                    data-reason="<?php echo htmlspecialchars($request['reason']); ?>"
                                                    data-request-form-path="<?php echo htmlspecialchars($request['request_form_path'] ?? ''); ?>"
                                                    data-blood-card-path="<?php echo htmlspecialchars($request['blood_card_path'] ?? ''); ?>">
                                                    <i class="bi bi-file-earmark-text"></i> Referral
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-4">
                                <i class="bi bi-clipboard2-check"></i>
                            </div>
                            <h5 class="text-primary-custom mb-3">No Pending Requests</h5>
                            <p class="text-muted mb-4">All blood requests have been processed successfully.</p>
                            <div class="d-flex justify-content-center gap-2">
                                <button class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-clock-history me-1"></i>View History
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Donations and Quick Actions Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="card-title mb-0">Recent Donations</h4>
                        </div>
                        <div class="card-body">
                            <?php if (is_array($recentDonations) && count($recentDonations) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Donor</th>
                                                <th>Blood Type</th>
                                                <th>units</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentDonations as $donation): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                                    <td><?php echo $donation['donor_name']; ?></td>
                                                    <td>
                                                        <span class="badge bg-danger"><?php echo $donation['blood_type']; ?></span>
                                                    </td>
                                                    <td><?php echo $donation['units']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="donations.php" class="btn btn-sm btn-outline-primary">View All Donations</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="display-4 text-muted mb-3">
                                        <i class="bi bi-droplet"></i>
                                    </div>
                                    <p class="mb-3">No recent donations recorded.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="card-title mb-0">Quick Actions</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-3">
                                
                                <a href="donations.php" class="btn btn-outline-primary">
                                    <i class="bi bi-journal-text me-2"></i>View Donations
                                </a>
                                
                                <a href="blood-requests.php" class="btn btn-outline-danger">
                                    <i class="bi bi-clipboard2-pulse me-2"></i>Manage Blood Requests
                                </a>
                                <a href="referrals.php" class="btn btn-outline-primary">
                                    <i class="bi bi-calendar-plus me-2"></i>Manage referrals
                                </a>
                                
                            </div>

                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Only one modal outside the loop -->
<div class="modal fade" id="referralModal" tabindex="-1" aria-labelledby="referralModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient-primary text-white border-0">
                <h5 class="modal-title fw-bold" id="referralModalLabel">
                    <i class="bi bi-send me-2"></i>Refer Patient to Blood Bank
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Add enctype for file upload -->
            <form action="process-referral.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="modalRequestId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Patient Information</h6>
                            <p class="mb-1"><strong>Name:</strong> <span id="modalPatientName"></span></p>
                            <p class="mb-1"><strong>Blood Type:</strong> <span id="modalBloodType"></span></p>
                            <p class="mb-1"><strong>Contact:</strong> <span id="modalPhone"></span></p>
                            <p class="mb-1"><strong>Units Needed:</strong> <span id="modalUnitsRequested"></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Request Details</h6>
                            <p class="mb-1"><strong>Date Requested:</strong> <span id="modalRequestDate"></span></p>
                            <p class="mb-1"><strong>Reason:</strong> <span id="modalReason"></span></p>
                            <div class="mt-3" id="modalDocumentsSection">
                                <h6 class="mb-2">Documents</h6>
                                <div id="modalRequestFormLink" class="mb-2" style="display: none;">
                                    <a href="#" id="requestFormLink" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>View Hospital Request Form
                                    </a>
                                </div>
                                <div id="modalBloodCardLink" class="mb-2" style="display: none;">
                                    <a href="#" id="bloodCardLink" target="_blank" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-card-image me-1"></i>View Blood Card
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="bloodBankSelect" class="form-label">Select Blood Bank</label>
                        <select class="form-select" id="bloodBankSelect" name="blood_bank_id" required>
                            <option value="">-- Select Blood Bank --</option>
                            <?php foreach ($bloodBanks as $bank): ?>
                                <option value="<?php echo $bank['id']; ?>"><?php echo $bank['name']; ?> - <?php echo $bank['address']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Select the blood bank where the patient has chosen to receive blood.</div>
                    </div>
                    <div class="mb-3">
                        <label for="referralDocument" class="form-label">Upload Referral Document</label>
                        <input class="form-control" type="file" id="referralDocument" name="referral_document" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">Accepted formats: PDF, JPG, PNG. Max size: 5MB.</div>
                    </div>
                    <!-- Referral date is set automatically by the system -->
                    <input type="hidden" id="referralDate" name="referral_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="modal-footer border-0 pt-4">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary-custom flex-fill">
                            <i class="bi bi-send me-1"></i>Send Referral
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const sidebar = document.querySelector('.sidebar');
    let headerToggle = document.querySelector('.header-toggle');
    const mobileToggle = document.querySelector('.mobile-toggle');
    
    // If header toggle doesn't exist, create it (fallback)
    if (!headerToggle && window.innerWidth < 992) {
        const header = document.querySelector('.dashboard-header');
        if (header) {
            headerToggle = document.createElement('button');
            headerToggle.className = 'header-toggle';
            headerToggle.setAttribute('type', 'button');
            headerToggle.setAttribute('aria-label', 'Toggle sidebar');
            headerToggle.setAttribute('aria-expanded', 'false');
            headerToggle.innerHTML = '<i class="bi bi-list"></i>';
            const headerContent = header.querySelector('.header-content') || header.querySelector('.container-fluid');
            if (headerContent) {
                headerContent.insertBefore(headerToggle, headerContent.firstChild);
            } else {
                header.insertBefore(headerToggle, header.firstChild);
            }
        }
    }
    
    // Show the hamburger button on mobile/tablet (will be handled by handleResize)
    
    // Create overlay if it doesn't exist
    let overlay = document.querySelector('#sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    
    // Ensure sidebar is hidden on mobile by default
    if (window.innerWidth < 992 && sidebar) {
        sidebar.classList.remove('show');
        sidebar.style.left = '-280px';
    }
    
    // Toggle sidebar function
    function toggleSidebar() {
        if (sidebar) {
            sidebar.classList.toggle('show');
            document.body.classList.toggle('sidebar-open');
            if (overlay) {
                overlay.classList.toggle('show');
            }
            
            // Update aria-expanded
            const toggleBtn = headerToggle || mobileToggle;
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', sidebar.classList.contains('show') ? 'true' : 'false');
            }
        }
    }
    
    // Close sidebar function
    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('show');
            document.body.classList.remove('sidebar-open');
            if (overlay) {
                overlay.classList.remove('show');
            }
            
            const toggleBtn = headerToggle || mobileToggle;
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', 'false');
            }
        }
    }
    
    // Add click handlers to toggle buttons
    if (headerToggle) {
        headerToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
    }
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
    }
    
    // Close sidebar when clicking overlay
    if (overlay) {
        overlay.addEventListener('click', function() {
            closeSidebar();
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 992 && sidebar && sidebar.classList.contains('show')) {
            if (!sidebar.contains(e.target) && 
                !headerToggle?.contains(e.target) && 
                !mobileToggle?.contains(e.target)) {
                closeSidebar();
            }
        }
    });
    
    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
    
    // Close sidebar when clicking nav links on mobile
    if (sidebar) {
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    closeSidebar();
                }
            });
        });
    }
    
    // Handle window resize
    const handleResize = function() {
        if (window.innerWidth >= 992) {
            // On desktop, ensure sidebar is visible and hamburger is hidden
            if (sidebar) {
                sidebar.classList.remove('show');
                sidebar.style.left = '0';
            }
            document.body.classList.remove('sidebar-open');
            if (overlay) {
                overlay.classList.remove('show');
            }
            if (headerToggle) {
                headerToggle.style.display = 'none';
            }
        } else {
            // On mobile/tablet, show hamburger and hide sidebar
            if (headerToggle) {
                headerToggle.style.display = 'flex';
            }
            if (sidebar && !sidebar.classList.contains('show')) {
                sidebar.style.left = '-280px';
            }
        }
    };
    
    window.addEventListener('resize', handleResize);
    // Call once on load
    handleResize();
    
    var referralModalEl = document.getElementById('referralModal');
    var referralModal = new bootstrap.Modal(referralModalEl);

    document.querySelectorAll('.referral-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var org = btn.getAttribute('data-organization') || '';
            var reqDate = btn.getAttribute('data-required-date') || btn.getAttribute('data-request-date') || '';
            var reqTime = btn.getAttribute('data-required-time') || '';

            document.getElementById('modalRequestId').value = btn.getAttribute('data-request-id');
            document.getElementById('modalPatientName').textContent = btn.getAttribute('data-patient-name');
            document.getElementById('modalBloodType').textContent = btn.getAttribute('data-blood-type');
            document.getElementById('modalPhone').textContent = btn.getAttribute('data-phone');
            document.getElementById('modalUnitsRequested').textContent = btn.getAttribute('data-units-requested');
            // Show required date/time
            var reqDisplay = '';
            if (reqDate) reqDisplay += new Date(reqDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            if (reqTime) reqDisplay += (reqDisplay ? ' ' : '') + ' ' + (new Date('1970-01-01T' + reqTime).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
            document.getElementById('modalRequestDate').textContent = reqDisplay;
            document.getElementById('modalReason').textContent = btn.getAttribute('data-reason');

            // Handle document links
            var requestFormPath = btn.getAttribute('data-request-form-path') || '';
            var bloodCardPath = btn.getAttribute('data-blood-card-path') || '';
            var requestFormLink = document.getElementById('requestFormLink');
            var bloodCardLink = document.getElementById('bloodCardLink');
            var requestFormLinkDiv = document.getElementById('modalRequestFormLink');
            var bloodCardLinkDiv = document.getElementById('modalBloodCardLink');

            if (requestFormPath && requestFormLink && requestFormLinkDiv) {
                var requestId = btn.getAttribute('data-request-id');
                requestFormLink.href = 'view-request-form.php?request_id=' + encodeURIComponent(requestId);
                requestFormLinkDiv.style.display = 'block';
            } else if (requestFormLinkDiv) {
                requestFormLinkDiv.style.display = 'none';
            }

            if (bloodCardPath && bloodCardLink && bloodCardLinkDiv) {
                var requestId = btn.getAttribute('data-request-id');
                bloodCardLink.href = 'view-blood-card.php?request_id=' + encodeURIComponent(requestId);
                bloodCardLinkDiv.style.display = 'block';
            } else if (bloodCardLinkDiv) {
                bloodCardLinkDiv.style.display = 'none';
            }

            // Auto-select blood bank based on organization chosen by patient
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
                // If not found, keep the current selection (user can change)
            }

            // Set hidden referral date to today (already set in HTML) but keep it synchronized with reqDate if needed
            var referralDateInput = document.getElementById('referralDate');
            if (referralDateInput && reqDate) {
                referralDateInput.value = reqDate;
            }

            referralModal.show();
        });
    });

    // Ensure dropdowns are properly initialized and visible
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all Bootstrap dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });

        // Ensure dropdowns show when clicked
        document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                var dropdown = bootstrap.Dropdown.getInstance(this);
                if (dropdown) {
                    dropdown.show();
                }
            });
        });

        // Prevent dropdown from closing when clicking inside
        document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
            menu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    });

});
</script>
<?php include_once '../../includes/footer.php'; ?>