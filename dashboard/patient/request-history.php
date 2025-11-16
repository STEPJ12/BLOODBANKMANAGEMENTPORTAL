<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../login.php?role=patient");
    exit;
}

// Set page title
$pageTitle = "Request History - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get patient information
$patientId = $_SESSION['user_id'];
$patient = getRow("SELECT * FROM patient_users WHERE id = ?", [$patientId]);

// Get blood request history
$requestHistory = executeQuery("
    SELECT * FROM blood_requests
    WHERE patient_id = ?
    ORDER BY request_date DESC
", [$patientId]);

// Enhanced frequency tracking for blood requests
$requestFrequencyStats = [
    'total_requests' => count($requestHistory),
    'total_units_requested' => array_sum(array_column($requestHistory ?: [], 'units_requested')),
    'total_units_approved' => array_sum(array_column($requestHistory ?: [], 'units_approved')),
    'last_request' => !empty($requestHistory) ? $requestHistory[0]['request_date'] : null,
    'first_request' => !empty($requestHistory) ? end($requestHistory)['request_date'] : null,
    'requests_this_year' => 0,
    'requests_last_6_months' => 0,
    'requests_last_3_months' => 0,
    'request_frequency_category' => 'No Requests',
    'approval_rate' => 0
];

// Calculate requests by time periods
$currentYear = date('Y');
$sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
$threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));

if (is_array($requestHistory)) {
    $approvedCount = 0;
    foreach ($requestHistory as $request) {
        $requestDate = $request['request_date'];
        if (date('Y', strtotime($requestDate)) == $currentYear) {
            $requestFrequencyStats['requests_this_year']++;
        }
        if ($requestDate >= $sixMonthsAgo) {
            $requestFrequencyStats['requests_last_6_months']++;
        }
        if ($requestDate >= $threeMonthsAgo) {
            $requestFrequencyStats['requests_last_3_months']++;
        }
        if ($request['status'] === 'approved') {
            $approvedCount++;
        }
    }
    
    // Calculate approval rate
    if (count($requestHistory) > 0) {
        $requestFrequencyStats['approval_rate'] = round(($approvedCount / count($requestHistory)) * 100, 1);
    }
}

// Determine request frequency category
if ($requestFrequencyStats['total_requests'] == 0) {
    $requestFrequencyStats['request_frequency_category'] = 'No Requests';
} elseif ($requestFrequencyStats['total_requests'] == 1) {
    $requestFrequencyStats['request_frequency_category'] = 'First Time Requester';
} elseif ($requestFrequencyStats['requests_last_6_months'] >= 3) {
    $requestFrequencyStats['request_frequency_category'] = 'Frequent Requester';
} elseif ($requestFrequencyStats['requests_last_6_months'] >= 1) {
    $requestFrequencyStats['request_frequency_category'] = 'Regular Requester';
} elseif ($requestFrequencyStats['total_requests'] > 1) {
    $requestFrequencyStats['request_frequency_category'] = 'Occasional Requester';
} else {
    $requestFrequencyStats['request_frequency_category'] = 'New Requester';
}

// Monthly request pattern
$monthlyRequests = executeQuery("
    SELECT 
        DATE_FORMAT(request_date, '%Y-%m') as month,
        COUNT(*) as request_count,
        SUM(units_requested) as total_units_requested,
        SUM(units_approved) as total_units_approved
    FROM blood_requests 
    WHERE patient_id = ? 
    AND request_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(request_date, '%Y-%m')
    ORDER BY month DESC
", [$patientId]) ?: [];


?>
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Blood Bank Portal - Request History">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <!-- Universal Print Functions -->
    <script src="../../assets/js/universal-print.js"></script>

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

    <style>
        /* Full height layout */
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .dashboard-container {
            flex: 1;
            display: flex;
            min-height: 100vh;
            width: 95%;
            position: relative;
        }

        /* Red Theme for Request History Page */
        :root {
            --patient-primary: #DC2626; /* Red */
            --patient-primary-dark: #B91C1C;
            --patient-primary-light: #EF4444;
            --patient-accent: #F87171;
            --patient-accent-dark: #DC2626;
            --patient-accent-light: #FEE2E2;
            --patient-cream: #FEF2F2;
            --patient-cream-light: #FEE2E2;
        }
        
        .dashboard-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0;
            margin-left: 280px;
            padding-top: 100px; /* Space for fixed header */
            position: relative;
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 50%, #FECACA 100%);
            overflow: hidden;
        }

        .dashboard-content::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(220, 38, 38, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 20s ease-in-out infinite;
            z-index: 0;
        }

        .dashboard-content::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(185, 28, 28, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 25s ease-in-out infinite reverse;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(30px, -30px) rotate(180deg); }
        }

        .dashboard-main {
            position: relative;
            z-index: 1;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%); /* Red gradient */
            color: white;
            border-bottom: none;
            position: fixed;
            top: 0;
            left: 280px; /* Position after sidebar */
            right: 0;
            z-index: 1021;
            height: 100px;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.3);
            padding: 0 2rem;
            overflow: visible;
            display: flex;
            align-items: center;
        }

        .dashboard-header .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .dashboard-header .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            position: relative;
        }
        
        .dashboard-header .page-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
        }

        .dashboard-header .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            z-index: 1021;
        }

        .dashboard-header .dropdown {
            position: relative;
            z-index: 1021;
        }

        .dashboard-header .dropdown-menu {
            position: absolute !important;
            right: 0 !important;
            left: auto !important;
            top: 100% !important;
            margin-top: 0.5rem !important;
            z-index: 1050 !important;
            min-width: 200px;
        }

        .dashboard-header .btn-outline-secondary {
            border-color: rgba(255, 255, 255, 0.3) !important;
            color: white !important;
            background: rgba(255, 255, 255, 0.1) !important;
            padding: 0.625rem 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .dashboard-header .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .dashboard-header .btn-outline-secondary span {
            color: white !important;
        }

        .dashboard-header .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
        }

        .dashboard-header .avatar i {
            color: white;
            font-size: 1.25rem;
        }

        /* Notification Bell in Header */
        .dashboard-header .notification-bell .btn {
            border-color: rgba(255, 255, 255, 0.3) !important;
            color: white !important;
            background: rgba(255, 255, 255, 0.1) !important;
            padding: 0.625rem 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .dashboard-header .notification-bell .btn:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .dashboard-header .notification-bell .badge {
            background: #EF4444 !important;
            color: white;
        }

        .dashboard-header .notification-bell .btn i {
            color: white !important;
        }
        
        /* Red Theme - Additional Component Styles */
        .btn-primary, .btn-primary-custom {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            border: none;
            color: white;
            font-weight: 600;
        }
        
        .btn-primary:hover, .btn-primary-custom:hover {
            background: linear-gradient(135deg, #B91C1C 0%, #991B1B 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid #DC2626;
            color: #DC2626;
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: #DC2626;
            color: white;
        }
        
        .card-header.bg-gradient-light {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
            color: white;
        }
        
        .card-header .card-title {
            color: white !important;
        }
        
        .badge.bg-primary, .badge.bg-danger {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
            color: white;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            color: white;
        }
        
        .text-primary-custom {
            color: #DC2626 !important;
        }
        
        a.text-primary-custom:hover {
            color: #B91C1C !important;
        }

        /* Enhanced Card Styles */
        .card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #DC2626, #B91C1C, #991B1B);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .card:hover::before {
            transform: scaleX(1);
        }

        .card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 16px 40px rgba(220, 38, 38, 0.2);
        }

        .table thead th {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            color: white;
            font-weight: 600;
            border: none;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
        }
        
        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .breadcrumb-item.active {
            color: white;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(220, 38, 38, 0.05);
        }
        
        .modal-header.bg-gradient-primary {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
            color: white;
        }

        /* White text and borders in header */
.dashboard-header .btn-outline-secondary {
    border-color: white !important;
    color: white !important;
}

.dashboard-header .btn-outline-secondary:hover {
    border-color: white !important;
    color: white !important;
    background: rgba(255, 255, 255, 0.2) !important;
}

.dashboard-header .btn-outline-secondary span {
    color: white !important;
}

.dashboard-header .btn-outline-secondary i {
    color: white !important;
}

.dashboard-header #notificationDropdown {
    border-color: white !important;
    color: white !important;
}

.dashboard-header #notificationDropdown i {
    color: white !important;
}

.dashboard-header #userDropdown {
    border-color: white !important;
    color: white !important;
}

.dashboard-header #userDropdown span {
    color: white !important;
}

.dashboard-header #userDropdown i {
    color: white !important;
}

.dashboard-header .avatar i {
    color: white !important;
}
        
        .dashboard-header .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        /* Text Colors - Red Theme */
        .text-danger {
            color: #DC2626 !important;
        }
        
        .text-success {
            color: #DC2626 !important;
        }
        
        .text-warning {
            color: #F59E0B !important;
        }
        
        .text-primary {
            color: #DC2626 !important;
        }
        
        .display-4.text-danger {
            color: #DC2626 !important;
        }
        
        .display-4.text-success {
            color: #DC2626 !important;
        }
        
        .display-4.text-warning {
            color: #F59E0B !important;
        }
        
        .page-title {
            color: white !important;
        }
        
        .card h3, .card h4, .card h5 {
            color: #1F2937;
        }
        
        .card p {
            color: #4B5563;
        }
        
        .table {
            color: #1F2937;
        }
        
        .table th {
            color: #374151;
            font-weight: 600;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
            border: none;
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #B91C1C 0%, #991B1B 100%) !important;
            color: white;
        }
        
        .btn-outline-danger {
            border: 2px solid #DC2626 !important;
            color: #DC2626 !important;
        }
        
        .btn-outline-danger:hover {
            background: #DC2626 !important;
            color: white !important;
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
            color: white;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
            color: white;
        }
        
        .badge.bg-warning {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%) !important;
            color: white;
        }
        
        .dashboard-header .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            z-index: 1021;
        }
        
        .dashboard-header .dropdown {
            position: relative;
            z-index: 1021;
        }
        
        .dashboard-header .dropdown-menu {
            position: absolute !important;
            right: 0 !important;
            left: auto !important;
            top: 100% !important;
            margin-top: 0.5rem !important;
            z-index: 1050 !important;
            transform: none !important;
        }

        .dashboard-main {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem !important;
        }

        /* Table improvements */
        .table-responsive {
            margin: 0;
            border-radius: 0.5rem;
            background-color: #fff;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        /* Card improvements */
        .card {
            height: 100%;
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        /* Enhanced Stat Cards - Matching Dashboard Design */
        .stat-card-enhanced {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .stat-card-enhanced::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--gradient-start, #DC2626), var(--gradient-end, #991B1B));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .stat-card-enhanced:hover::before {
            transform: scaleX(1);
        }

        .stat-card-enhanced:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 16px 40px rgba(220, 38, 38, 0.2);
        }

        .stat-icon-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--icon-gradient-start, #DC2626), var(--icon-gradient-end, #991B1B));
            box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-icon-wrapper::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            animation: rotate 3s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .stat-icon-wrapper i {
            font-size: 2.5rem;
            color: white;
            position: relative;
            z-index: 1;
        }

        .stat-card-enhanced:hover .stat-icon-wrapper {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 12px 30px rgba(220, 38, 38, 0.4);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            color: #64748b;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Card Type - Pending */
        .stat-card-enhanced[data-type="pending"] {
            --gradient-start: #F59E0B;
            --gradient-end: #D97706;
            --icon-gradient-start: #F59E0B;
            --icon-gradient-end: #D97706;
        }

        .stat-card-enhanced[data-type="pending"] .stat-icon-wrapper {
            background: linear-gradient(135deg, #F59E0B, #D97706);
        }

        .stat-card-enhanced[data-type="pending"] .stat-number {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Card Type - Approved */
        .stat-card-enhanced[data-type="approved"] {
            --gradient-start: #DC2626;
            --gradient-end: #B91C1C;
            --icon-gradient-start: #DC2626;
            --icon-gradient-end: #B91C1C;
        }

        .stat-card-enhanced[data-type="approved"] .stat-icon-wrapper {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
        }

        .stat-card-enhanced[data-type="approved"] .stat-number {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Card Type - Total/Completed */
        .stat-card-enhanced[data-type="total"] {
            --gradient-start: #DC2626;
            --gradient-end: #B91C1C;
            --icon-gradient-start: #DC2626;
            --icon-gradient-end: #B91C1C;
        }

        /* Status cards grid */
        .status-cards {
            margin: 0;
        }

        .status-card-clickable.active {
            border: 2px solid #DC2626;
            box-shadow: 0 16px 40px rgba(220, 38, 38, 0.3);
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .dashboard-content {
                margin-left: 0;
                padding-top: 100px;
            }
            
            .dashboard-header {
                left: 0;
                padding: 1rem;
                height: auto;
            }
        }

        @media (max-width: 767.98px) {
            .dashboard-container {
                flex-direction: column;
            }

            .dashboard-header .header-content {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .dashboard-header .page-title {
                font-size: 1.1rem;
                flex: 1;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .dashboard-header .header-actions {
                gap: 0.5rem;
            }
            
            .dashboard-header .header-actions .btn {
                padding: 0.5rem;
            }
            
            .dashboard-header .header-actions span:not(.badge) {
                display: none;
            }

            .dashboard-main {
                padding: 1rem !important;
            }

            .table-responsive {
                border-radius: 0.25rem;
            }
        }

        @media (max-width: 575.98px) {
            .dashboard-header {
                padding: 0.75rem 1rem;
            }
            
            .dashboard-header .header-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Print styles */
        @media print {
            body {
                min-height: auto;
            }

            .dashboard-container {
                display: block;
                min-height: auto;
            }

            .dashboard-content {
                margin: 0;
                min-height: auto;
            }

            .card {
                break-inside: avoid;
            }
        }
        /* Enhanced button styles */
        .btn-primary {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #B91C1C 0%, #991B1B 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
    }
    </style>
</head>
<body class="h-100">

<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <div class="header-content">
                <h2 class="page-title">Blood Request History</h2>
                <div class="header-actions">
                    <?php include_once '../../includes/notification_bell.php'; ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar me-2">
                                <i class="bi bi-person-circle fs-4"></i>
                            </div>
                            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Patient'); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-main">
            <div class="container-fluid px-0">
                <?php if (count($requestHistory) > 0): ?>
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-3">
                                <h4 class="card-title mb-0" style="font-weight: 700; color: #1F2937;">Request Status Summary</h4>
                            </div>
                            <div class="card-body">
                                <div class="row row-cols-1 row-cols-md-3 g-4 status-cards">
                                    <?php
                                    $pendingCount = 0;
                                    $approvedCount = 0;
                                    $completedCount = 0;

                                    foreach ($requestHistory as $request) {
                                        switch (strtolower($request['status'])) {
                                            case 'pending':
                                                $pendingCount++;
                                                break;
                                            case 'approved':
                                                $approvedCount++;
                                                break;
                                            case 'fulfilled':
                                            case 'completed':
                                                $completedCount++;
                                                break;
                                        }
                                    }
                                    ?>
                                    <div class="col">
                                        <div class="stat-card-enhanced status-card-clickable" data-status="Pending" data-type="pending" style="cursor: pointer;">
                                            <div class="card-body text-center p-4">
                                                <div class="stat-icon-wrapper">
                                                    <i class="bi bi-hourglass-split"></i>
                                                </div>
                                                <div class="stat-number"><?php echo $pendingCount; ?></div>
                                                <p class="stat-label mb-0">Pending</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="stat-card-enhanced status-card-clickable" data-status="Approved" data-type="approved" style="cursor: pointer;">
                                            <div class="card-body text-center p-4">
                                                <div class="stat-icon-wrapper">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                </div>
                                                <div class="stat-number"><?php echo $approvedCount; ?></div>
                                                <p class="stat-label mb-0">Approved</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="stat-card-enhanced status-card-clickable" data-status="Fulfilled" data-type="total" style="cursor: pointer;">
                                            <div class="card-body text-center p-4">
                                                <div class="stat-icon-wrapper">
                                                    <i class="bi bi-droplet-fill"></i>
                                                </div>
                                                <div class="stat-number"><?php echo $completedCount; ?></div>
                                                <p class="stat-label mb-0">Completed</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row g-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-3">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                    <h4 class="card-title mb-0" style="font-weight: 700; color: #1F2937;">Your Blood Requests</h4>
                                    <div class="d-flex gap-2">
                                        <a href="request-blood.php" class="btn btn-primary btn-sm w-100 w-md-auto" style="border-radius: 12px; padding: 0.625rem 1.25rem; font-weight: 600;">
                                            <i class="bi bi-plus-circle me-1"></i> New Request
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0 p-md-3">
                                <?php if (count($requestHistory) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th class="d-none d-md-table-cell">Request Date</th>
                                                    <th>Blood Type</th>
                                                    <th>Units</th>
                                                    <th class="d-none d-md-table-cell">Organization</th>
                                                    <th class="d-none d-lg-table-cell">Required By</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($requestHistory as $request): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-danger"><?php echo $request['blood_type']; ?></span>
                                                        </td>
                                                        <td><?php echo $request['units_requested']; ?> unit(s)</td>
                                                        <td class="d-none d-md-table-cell">
                                                            <?php
                                                            $orgName = 'Unknown';
                                                            if ($request['organization_type'] === 'redcross') {
                                                                $orgName = 'Red Cross';
                                                            } elseif ($request['organization_type'] === 'negrosfirst') {
                                                                $orgName = 'Negros First';
                                                            }
                                                            echo $orgName;
                                                            ?>
                                                        </td>
                                                        <td class="d-none d-lg-table-cell"><?php echo date('M d, Y', strtotime($request['required_date'])); ?></td>
                                                        <td>
                                                            <?php
                                                            $statusClass = 'secondary';
                                                            $statusDisplay = $request['status'];
                                                            switch (strtolower($request['status'])) {
                                                                case 'pending':
                                                                    $statusClass = 'warning';
                                                                    break;
                                                                case 'approved':
                                                                    $statusClass = 'success';
                                                                    break;
                                                                case 'fulfilled':
                                                                    $statusClass = 'primary';
                                                                    $statusDisplay = 'Completed';
                                                                    break;
                                                                case 'completed':
                                                                    $statusClass = 'primary';
                                                                    $statusDisplay = 'Completed';
                                                                    break;
                                                                case 'rejected':
                                                                    $statusClass = 'danger';
                                                                    break;
                                                                default:
                                                                    $statusClass = 'secondary';
                                                            }
                                                            ?>
                                                            <span class="badge bg-<?php echo $statusClass; ?>" data-status-value="<?php echo htmlspecialchars($request['status']); ?>"><?php echo htmlspecialchars($statusDisplay); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-2">
                                                                <button class="btn btn-sm btn-outline-primary"
                                                                        onclick="viewRequestDetails(<?php echo $request['id']; ?>)"
                                                                        title="View Details">
                                                                    <i class="bi bi-eye d-md-none"></i>
                                                                    <span class="d-none d-md-inline"><i class="bi bi-eye me-1"></i> View</span>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <!-- Mobile-only row with additional information -->
                                                    <tr class="d-md-none border-0">
                                                        <td colspan="5" class="pt-0 pb-3">
                                                            <div class="d-flex flex-column gap-1 text-muted small">
                                                                <div>
                                                                    <strong>Request Date:</strong> <?php echo date('M d, Y', strtotime($request['request_date'])); ?>
                                                                </div>
                                                                <div>
                                                                    <strong>Organization:</strong> <?php echo $orgName; ?>
                                                                </div>
                                                                <div>
                                                                    <strong>Required By:</strong> <?php echo date('M d, Y', strtotime($request['required_date'])); ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bi bi-clipboard-x fs-1 d-block mb-3"></i>
                                            <p class="mb-3">No blood requests found.</p>
                                            <a href="request-blood.php" class="btn btn-primary">Request Blood</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Request Details Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewRequestModalLabel">Blood Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// View request details function
function viewRequestDetails(requestId) {
    // Get the request data from the table row
    const requestData = getRequestData(requestId);
    if (!requestData) return;
    
    // Populate modal content
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Request Information</h6>
                <dl class="row">
                    <dt class="col-sm-4">Request ID:</dt>
                    <dd class="col-sm-8">#${requestData.id}</dd>
                    <dt class="col-sm-4">Blood Type:</dt>
                    <dd class="col-sm-8"><span class="badge bg-danger">${requestData.bloodType}</span></dd>
                    <dt class="col-sm-4">Units Requested:</dt>
                    <dd class="col-sm-8">${requestData.units} unit(s)</dd>
                    <dt class="col-sm-4">Organization:</dt>
                    <dd class="col-sm-8">${requestData.organization}</dd>
                    <dt class="col-sm-4">Status:</dt>
                    <dd class="col-sm-8"><span class="badge bg-${requestData.statusClass}">${requestData.status}</span></dd>
                </dl>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Timeline</h6>
                <dl class="row">
                    <dt class="col-sm-4">Request Date:</dt>
                    <dd class="col-sm-8">${requestData.requestDate}</dd>
                    <dt class="col-sm-4">Required By:</dt>
                    <dd class="col-sm-8">${requestData.requiredDate}</dd>
                </dl>
            </div>
        </div>
    `;
    
    document.getElementById('requestDetailsContent').innerHTML = content;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
    modal.show();
}

// Helper function to get request data from the table
function getRequestData(requestId) {
    // This is a simplified version - in a real implementation, you might want to fetch this via AJAX
    // For now, we'll extract data from the visible table rows
    const rows = document.querySelectorAll('tbody tr');
    for (let row of rows) {
        const viewButton = row.querySelector('button[onclick*="' + requestId + '"]');
        if (viewButton) {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 6) {
                return {
                    id: requestId,
                    bloodType: cells[1].textContent.trim(),
                    units: cells[2].textContent.trim(),
                    organization: cells[3].textContent.trim(),
                    requiredDate: cells[4].textContent.trim(),
                    status: cells[5].querySelector('.badge').textContent.trim(),
                    statusClass: cells[5].querySelector('.badge').className.match(/bg-(\w+)/)?.[1] || 'secondary',
                    requestDate: cells[0].textContent.trim()
                };
            }
        }
    }
    return null;
}

// Status card filtering functionality
document.addEventListener('DOMContentLoaded', function() {
    const statusCards = document.querySelectorAll('.status-card-clickable');
    const tableRows = document.querySelectorAll('tbody tr');
    
    // Add hover effect
    statusCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                    this.style.boxShadow = '0 16px 40px rgba(220, 38, 38, 0.2)';
                }
        });
        
        card.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                    this.style.transform = 'translateY(0) scale(1)';
                    this.style.boxShadow = '0 8px 24px rgba(0, 0, 0, 0.08)';
            }
        });
        
        // Click handler
        card.addEventListener('click', function() {
            const selectedStatus = this.getAttribute('data-status');
            
            // Remove active class from all cards
            statusCards.forEach(c => {
                c.classList.remove('active');
                c.style.transform = 'translateY(0) scale(1)';
                c.style.boxShadow = '0 8px 24px rgba(0, 0, 0, 0.08)';
                c.style.border = '1px solid rgba(255, 255, 255, 0.3)';
            });
            
            // Add active class to clicked card
            this.classList.add('active');
            this.style.transform = 'translateY(-8px) scale(1.02)';
            this.style.boxShadow = '0 16px 40px rgba(220, 38, 38, 0.3)';
            this.style.border = '2px solid #DC2626';
            
            // Filter table rows
            let visibleCount = 0;
            tableRows.forEach(row => {
                // Skip mobile-only rows (they will be hidden/shown with their parent row)
                if (row.classList.contains('d-md-none')) {
                    return;
                }
                
                const statusBadge = row.querySelector('[data-status-value]');
                if (statusBadge) {
                    const rowStatus = statusBadge.getAttribute('data-status-value');
                    // Handle both "Fulfilled" and "Completed" for the Completed card
                    if (selectedStatus === 'Fulfilled' && (rowStatus === 'Fulfilled' || rowStatus === 'Completed')) {
                        row.style.display = '';
                        visibleCount++;
                        // Show associated mobile row if exists
                        const nextRow = row.nextElementSibling;
                        if (nextRow && nextRow.classList.contains('d-md-none')) {
                            nextRow.style.display = '';
                        }
                    } else if (rowStatus === selectedStatus) {
                        row.style.display = '';
                        visibleCount++;
                        // Show associated mobile row if exists
                        const nextRow = row.nextElementSibling;
                        if (nextRow && nextRow.classList.contains('d-md-none')) {
                            nextRow.style.display = '';
                        }
                    } else {
                        row.style.display = 'none';
                        // Hide associated mobile row if exists
                        const nextRow = row.nextElementSibling;
                        if (nextRow && nextRow.classList.contains('d-md-none')) {
                            nextRow.style.display = 'none';
                        }
                    }
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show message if no results
            const tbody = document.querySelector('tbody');
            let noResultsMsg = document.getElementById('no-results-message');
            if (visibleCount === 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('tr');
                    noResultsMsg.id = 'no-results-message';
                    noResultsMsg.innerHTML = '<td colspan="7" class="text-center py-5"><div class="text-muted"><i class="bi bi-inbox fs-1 d-block mb-3"></i><p class="mb-0">No ' + selectedStatus.toLowerCase() + ' requests found.</p></div></td>';
                    tbody.appendChild(noResultsMsg);
                }
            } else {
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        });
    });
    
    // Add "Show All" functionality - double click to reset
    statusCards.forEach(card => {
        card.addEventListener('dblclick', function() {
            // Remove active class from all cards
            statusCards.forEach(c => {
                c.classList.remove('active');
                c.style.transform = 'translateY(0) scale(1)';
                c.style.boxShadow = '0 8px 24px rgba(0, 0, 0, 0.08)';
                c.style.border = '1px solid rgba(255, 255, 255, 0.3)';
            });
            
            // Show all rows
            tableRows.forEach(row => {
                row.style.display = '';
            });
            
            // Remove no results message if exists
            const noResultsMsg = document.getElementById('no-results-message');
            if (noResultsMsg) {
                noResultsMsg.remove();
            }
        });
    });
});
</script>
</body>
</html>
