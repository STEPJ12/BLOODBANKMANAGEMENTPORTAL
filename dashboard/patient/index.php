<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../login.php?role=patient");
    exit;
}

// Set dashboard flag
$isDashboard = true;
$pageTitle = "Patient Dashboard - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get patient information
$patientId = $_SESSION['user_id'];
$patient = getRow("SELECT * FROM patient_users WHERE id = ?", [$patientId]);

// Get blood request statistics
$totalRequests = getRow("SELECT COUNT(*) as count FROM blood_requests WHERE patient_id = ?", [$patientId]);
$totalRequests = $totalRequests ? $totalRequests['count'] : 0;

$pendingRequests = getRow("SELECT COUNT(*) as count FROM blood_requests WHERE patient_id = ? AND status = 'pending'", [$patientId]);
$pendingRequests = $pendingRequests ? $pendingRequests['count'] : 0;

$approvedRequests = getRow("SELECT COUNT(*) as count FROM blood_requests WHERE patient_id = ? AND status = 'approved'", [$patientId]);
$approvedRequests = $approvedRequests ? $approvedRequests['count'] : 0;

// Get recent blood requests
$recentRequests = executeQuery("
    SELECT * FROM blood_requests
    WHERE patient_id = ?
    ORDER BY request_date DESC
    LIMIT 5
", [$patientId]);

// Get unread notifications count for patient
$unreadNotifications = getRow("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND user_role = 'patient' AND is_read = 0", [$patientId]);
$unreadCount = $unreadNotifications ? $unreadNotifications['count'] : 0;

// Get recent notifications (limit 5)
$recentNotifications = executeQuery("SELECT * FROM notifications WHERE user_id = ? AND user_role = 'patient' ORDER BY created_at DESC LIMIT 5", [$patientId]);

// Mark notifications as read
executeQuery(
    "UPDATE notifications SET is_read = 1 WHERE user_id = ?",
    [$_SESSION['user_id']]
);

// Include header
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">



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
    
    <!-- Shared Patient Dashboard Styles -->
    <?php include_once 'shared-styles.php'; ?>
    
    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
    
    <!-- QR Code library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- PDF export libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
    <!-- Page-specific color theme override -->
    <style>
    /* Enhanced Red Theme for Dashboard */
    :root {
        --patient-primary: #DC2626;
        --patient-primary-dark: #B91C1C;
        --patient-primary-light: #EF4444;
        --patient-accent: #F87171;
        --patient-accent-dark: #DC2626;
        --patient-accent-light: #FEE2E2;
        --patient-cream: #FEF2F2;
        --patient-cream-light: #FEE2E2;
        --patient-header-gradient: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
        --patient-bg-gradient: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
    }

    /* Override Sidebar for Patient Dashboard - Red Theme */
    .sidebar {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%) !important;
        border-right: none;
        box-shadow: 4px 0 20px rgba(220, 38, 38, 0.3);
    }

    .sidebar-header {
        background: linear-gradient(135deg, rgba(220, 38, 38, 0.2) 0%, rgba(185, 28, 28, 0.2) 100%) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
    }

    .sidebar-logo {
        background: rgba(255, 255, 255, 0.2) !important;
        border: 2px solid rgba(255, 255, 255, 0.3) !important;
    }

    .sidebar .nav-link {
        color: white !important;
    }

    .sidebar .nav-link:hover {
        background: rgba(255, 255, 255, 0.15) !important;
        color: white !important;
        transform: translateX(5px);
        box-shadow: 0 2px 8px rgba(255, 255, 255, 0.2);
    }

    .sidebar .nav-link.active {
        background: rgba(255, 255, 255, 0.25) !important;
        color: white !important;
        box-shadow: 0 2px 8px rgba(255, 255, 255, 0.3);
        border-left: 4px solid #F87171 !important;
        font-weight: 600;
    }

    .sidebar .nav-link i {
        color: white !important;
    }

    .sidebar .nav-link:hover i,
    .sidebar .nav-link.active i {
        color: white !important;
    }

    .sidebar-footer {
        background: rgba(185, 28, 28, 0.3) !important;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
    }

    .sidebar-footer .btn {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%) !important;
        border: 1px solid rgba(255, 255, 255, 0.3) !important;
        color: white !important;
    }

    .sidebar-footer .btn:hover {
        background: rgba(255, 255, 255, 0.3) !important;
        border-color: rgba(255, 255, 255, 0.5) !important;
        color: white !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(185, 28, 28, 0.2) !important;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3) !important;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5) !important;
    }

    /* Enhanced Dashboard Styles */
    .dashboard-content {
        background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 50%, #FECACA 100%);
        position: relative;
        overflow: hidden;
        margin-left: 280px; /* Only sidebar */
        padding-top: 100px; /* Space for top header */
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
        padding: 1.5rem;
    }

    /* Top Header */
    .dashboard-header {
        position: fixed;
        top: 0;
        left: 280px; /* After sidebar */
        right: 0;
        height: 100px;
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
        color: white;
        z-index: 1020;
        box-shadow: 0 4px 20px rgba(220, 38, 38, 0.3);
        display: flex;
        align-items: center;
        padding: 0 2rem;
        overflow: visible;
    }

    .dashboard-header .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        position: relative;
    }

    .dashboard-header .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: white !important;
        margin: 0;
        letter-spacing: 0.5px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .dashboard-header .header-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        position: relative;
        z-index: 1021;
    }

    /* Enhanced Welcome Banner */
    .welcome-banner {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(99, 102, 241, 0.15);
        position: relative;
        overflow: hidden;
    }

    .welcome-banner::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
    }

    .welcome-banner h3 {
        background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }

    .welcome-banner p {
        color: #64748b;
        font-size: 1.05rem;
    }

    /* Enhanced Blood Type Badge */
    .blood-type-badge {
        position: relative;
        display: inline-block;
        padding: 1.5rem 3rem;
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
        color: white;
        border-radius: 20px;
        font-size: 2rem;
        font-weight: 800;
        box-shadow: 0 10px 30px rgba(220, 38, 38, 0.4);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        overflow: hidden;
    }

    .blood-type-badge::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        transform: rotate(45deg);
        transition: all 0.6s;
    }

    .blood-type-badge:hover {
        transform: scale(1.1) rotate(2deg);
        box-shadow: 0 15px 40px rgba(220, 38, 38, 0.5);
    }

    .blood-type-badge:hover::before {
        left: 100%;
    }

    .blood-type-badge span {
        position: relative;
        z-index: 1;
    }

    /* Enhanced Stat Cards */
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

    /* Card Type 1 - Total Requests */
    .stat-card-enhanced[data-type="total"] {
        --gradient-start: #DC2626;
        --gradient-end: #B91C1C;
        --icon-gradient-start: #DC2626;
        --icon-gradient-end: #B91C1C;
    }

    /* Card Type 2 - Pending */
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

    /* Card Type 3 - Approved */
    .stat-card-enhanced[data-type="approved"] {
        --gradient-start: #10B981;
        --gradient-end: #059669;
        --icon-gradient-start: #10B981;
        --icon-gradient-end: #059669;
    }

    .stat-card-enhanced[data-type="approved"] .stat-icon-wrapper {
        background: linear-gradient(135deg, #10B981, #059669);
    }

    .stat-card-enhanced[data-type="approved"] .stat-number {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Enhanced Quick Actions Card */
    .quick-actions-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .quick-actions-card .card-header {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
        color: white;
        border: none;
        padding: 1.25rem 1.5rem;
    }

    .quick-actions-card .card-header h4 {
        color: white;
        font-weight: 700;
        margin: 0;
    }

    .action-btn {
        border-radius: 12px;
        padding: 0.875rem 1.25rem;
        font-weight: 600;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border: none;
    }

    .action-btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .action-btn:hover::before {
        width: 300px;
        height: 300px;
    }

    .action-btn span {
        position: relative;
        z-index: 1;
    }

    /* Enhanced Table Card */
    .table-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .table-card .card-header {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
        color: white;
        border: none;
        padding: 1.25rem 1.5rem;
    }

    .table-card .card-header h4 {
        color: white;
        font-weight: 700;
        margin: 0;
    }

    .table-card .table {
        margin-bottom: 0;
    }

    .table-card .table thead th {
        background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
        color: #1F2937;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        padding: 1rem;
        border-bottom: 2px solid #FECACA;
    }

    .table-card .table tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid #FEE2E2;
    }

    .table-card .table tbody tr:hover {
        background: linear-gradient(90deg, rgba(220, 38, 38, 0.05) 0%, rgba(185, 28, 28, 0.05) 100%);
        transform: translateX(5px);
    }

    .table-card .table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
    }

    /* Enhanced Badges */
    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .status-badge.bg-success {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important;
    }

    .status-badge.bg-warning {
        background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%) !important;
    }

    .status-badge.bg-danger {
        background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%) !important;
    }

    /* Alert Enhancement */
    .alert-info {
        background: linear-gradient(135deg, rgba(220, 38, 38, 0.1) 0%, rgba(185, 28, 28, 0.1) 100%);
        border: 1px solid rgba(220, 38, 38, 0.2);
        border-left: 4px solid #DC2626;
        border-radius: 12px;
        color: #B91C1C;
        font-weight: 500;
    }

    /* Empty State */
    .empty-state {
        padding: 3rem 2rem;
    }

    .empty-state-icon {
        width: 120px;
        height: 120px;
        margin: 0 auto 1.5rem;
        background: linear-gradient(135deg, rgba(220, 38, 38, 0.1) 0%, rgba(185, 28, 28, 0.1) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .empty-state-icon i {
        font-size: 4rem;
        background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Header User Dropdown */
    .dashboard-header .dropdown {
        position: relative;
        z-index: 1021;
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

    .dashboard-header .dropdown-menu {
        position: absolute !important;
        right: 0 !important;
        left: auto !important;
        top: 100% !important;
        margin-top: 0.5rem !important;
        z-index: 1050 !important;
        min-width: 200px;
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

    .dashboard-header #notificationDropdown {
        border-color: rgba(255, 255, 255, 0.3) !important;
        color: white !important;
        background: rgba(255, 255, 255, 0.1) !important;
    }

    .dashboard-header #notificationDropdown i {
        color: white !important;
    }

    /* Responsive Design */
    @media (max-width: 991.98px) {
        .dashboard-content {
            margin-left: 0;
            padding-top: 100px;
        }

        .dashboard-header {
            left: 0;
            padding: 1rem;
        }
    }

    @media (max-width: 768px) {
        .welcome-banner h3 {
            font-size: 1.5rem;
        }

        .stat-number {
            font-size: 2rem;
        }

        .stat-icon-wrapper {
            width: 60px;
            height: 60px;
        }

        .stat-icon-wrapper i {
            font-size: 2rem;
        }

        .dashboard-content {
            margin-left: 0;
        }
    }

    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
    }

    /* Loading Animation for Numbers */
    @keyframes countUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .stat-number {
        animation: countUp 0.6s ease-out;
    }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <!-- Top Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h2 class="page-title">Patient Dashboard</h2>
                <div class="header-actions">
                    <?php include_once '../../includes/notification_bell.php'; ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar me-2">
                            <i class="bi bi-person-circle"></i>
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

    <div class="dashboard-content">
        <div class="dashboard-main">
                        <!-- Welcome Banner -->
            <div class="card welcome-banner border-0 mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3>Welcome back, <?php echo htmlspecialchars($patient['name'] ?? 'Patient'); ?>! 👋</h3>
                            <p class="mb-0">Manage your blood requests and medical information from your patient dashboard.</p>

                            <?php if ($pendingRequests > 0): ?>
                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    You have <strong><?php echo $pendingRequests; ?></strong> pending blood request<?php echo $pendingRequests > 1 ? 's' : ''; ?>.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="blood-type-badge">
                                <span><?php echo htmlspecialchars($patient['blood_type'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <a href="request-history.php" class="text-decoration-none">
                        <div class="stat-card-enhanced" data-type="total">
                            <div class="card-body text-center p-4">
                                <div class="stat-icon-wrapper">
                                    <i class="bi bi-clipboard-check"></i>
                                </div>
                                <div class="stat-number"><?php echo $totalRequests; ?></div>
                                <p class="stat-label mb-0">Total Requests</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="request-history.php?filter=pending" class="text-decoration-none">
                        <div class="stat-card-enhanced" data-type="pending">
                            <div class="card-body text-center p-4">
                                <div class="stat-icon-wrapper">
                                    <i class="bi bi-hourglass-split"></i>
                                </div>
                                <div class="stat-number"><?php echo $pendingRequests; ?></div>
                                <p class="stat-label mb-0">Pending Requests</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="request-history.php?filter=approved" class="text-decoration-none">
                        <div class="stat-card-enhanced" data-type="approved">
                            <div class="card-body text-center p-4">
                                <div class="stat-icon-wrapper">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div class="stat-number"><?php echo $approvedRequests; ?></div>
                                <p class="stat-label mb-0">Approved Requests</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Quick Actions and Recent Requests -->
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card quick-actions-card h-100">
                        <div class="card-header">
                            <h4 class="card-title mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-3">
                                <a href="request-blood.php" class="btn btn-danger action-btn">
                                    <span><i class="bi bi-droplet me-2"></i>Request Blood</span>
                                </a>
                                <a href="blood-availability.php" class="btn btn-outline-danger action-btn">
                                    <span><i class="bi bi-hospital me-2"></i>Find Blood Banks</span>
                                </a>
                            </div>

                            
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card table-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Recent Blood Requests</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($recentRequests && count($recentRequests) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Blood Type</th>
                                                <th>Units</th>
                                                <th>Hospital</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentRequests as $request): ?>
                                                <tr>
                                                    <td><strong><?php echo date('M d, Y', strtotime($request['request_date'])); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-danger status-badge"><?php echo $request['blood_type']; ?></span>
                                                    </td>
                                                    <td><strong><?php echo $request['units_requested']; ?></strong></td>
                                                    <td><?php echo $request['hospital'] && $request['hospital'] !== '0' ? $request['hospital'] : 'Not specified'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-4">
                                    <a href="request-history.php" class="btn btn-sm btn-outline-danger action-btn">
                                        <span>View All Requests <i class="bi bi-arrow-right ms-2"></i></span>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="empty-state text-center">
                                    <div class="empty-state-icon">
                                        <i class="bi bi-clipboard"></i>
                                    </div>
                                    <h5 class="fw-bold mb-2">No Requests Yet</h5>
                                    <p class="text-muted mb-4">You haven't made any blood requests yet. Start by making your first request!</p>
                                    <a href="request-blood.php" class="btn btn-danger action-btn">
                                        <span><i class="bi bi-plus-circle me-2"></i>Make Your First Request</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                            </body>
                            </html>

<?php include_once '../../includes/footer.php'; ?>

<!-- Print Utilities -->
<script src="../../assets/js/print-utils.js"></script>

<!-- Enhanced Dashboard JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate numbers on stat cards
    const animateValue = (element, start, end, duration) => {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            element.textContent = Math.floor(easeOutQuart * (end - start) + start);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    };

    // Get all stat numbers and animate them
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        if (!isNaN(finalValue) && finalValue > 0) {
            stat.textContent = '0';
            setTimeout(() => {
                animateValue(stat, 0, finalValue, 1000);
            }, 200);
        }
    });

    // Add parallax effect to floating background elements
    const dashboardContent = document.querySelector('.dashboard-content');
    if (dashboardContent) {
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallaxElements = dashboardContent.querySelectorAll('::before, ::after');
            // Note: CSS pseudo-elements can't be directly manipulated, but we can add classes
        });
    }

    // Enhanced hover effects for cards
    const statCards = document.querySelectorAll('.stat-card-enhanced');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        });
    });

    // Add ripple effect to action buttons
    const actionButtons = document.querySelectorAll('.action-btn');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Add smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// Add ripple effect styles
const style = document.createElement('style');
style.textContent = `
    .action-btn {
        position: relative;
        overflow: hidden;
    }
    
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>
