<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../login.php?role=patient");
    exit;
}

// Set page title
$pageTitle = "Notifications - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get patient information
$patientId = $_SESSION['user_id'];

// Process mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $notificationId = sanitize($_POST['notification_id']);
        executeQuery("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE id = ? AND user_id = ? AND user_role = 'patient'
        ", [$notificationId, $patientId]);
    } elseif (isset($_POST['mark_all_read'])) {
        executeQuery("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE user_id = ? AND user_role = 'patient' AND is_read = 0
        ", [$patientId]);
    }
}

// Get notifications
$notifications = executeQuery("
    SELECT * FROM notifications
    WHERE user_id = ? AND user_role = 'patient'
    ORDER BY created_at DESC
", [$patientId]);

// Count unread notifications
$unreadCount = getCount("
    SELECT COUNT(*) FROM notifications
    WHERE user_id = ? AND user_role = 'patient' AND is_read = 0
", [$patientId]);

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
    
    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
    
    <!-- QR Code library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- PDF export libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
    <style>
    /* Red Theme for Notifications Page */
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
        margin-left: 280px;
        padding-top: 100px;
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
        left: 280px;
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
    
    .btn-outline-primary {
        border: 2px solid #DC2626;
        color: #DC2626;
    }
    
    .btn-outline-primary:hover {
        background: #DC2626;
        color: white;
    }
    
    .badge.bg-danger {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
        color: white;
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

    .list-group-item {
        transition: all 0.3s ease;
        border-radius: 12px;
        margin-bottom: 0.5rem;
    }

    .list-group-item:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
    }
    
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
    </style>
</head>


<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <div class="header-content">
                <h2 class="page-title">Notifications</h2>
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

        <div class="dashboard-main p-3">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Your Notifications</h4>
                    <div>
                        <?php if ($unreadCount > 0): ?>
                            <form method="POST" action="" class="d-inline">
                                <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-check-all me-1"></i> Mark All as Read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($notifications) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="list-group-item border-0 px-0 py-3 <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-danger rounded-circle p-2">&nbsp;</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary rounded-circle p-2">&nbsp;</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="mb-0"><?php echo $notification['title']; ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                                </small>
                                            </div>
                                            <p class="mb-1"><?php echo $notification['message']; ?></p>
                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" name="mark_read" class="btn btn-sm btn-link p-0">
                                                        Mark as Read
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
                                <p>No notifications found.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
