<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../../login.php?role=donor");
    exit;
}

// Set page title
$pageTitle = "Notifications - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get donor information
$donorId = $_SESSION['user_id'];

// Get notifications
$notifications = executeQuery("
    SELECT * FROM notifications
    WHERE user_role = 'donor' AND user_id = ?
    ORDER BY created_at DESC
", [$donorId]);

// Mark notification as read if ID is provided
if (isset($_GET['id'])) {
    $notificationId = sanitize($_GET['id']);
    executeQuery("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ? AND user_role = 'donor' AND user_id = ?
    ", [$notificationId, $donorId]);

    // Refresh notifications
    $notifications = executeQuery("
        SELECT * FROM notifications
        WHERE user_role = 'donor' AND user_id = ?
        ORDER BY created_at DESC
    ", [$donorId]);
}

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    executeQuery("
        UPDATE notifications
        SET is_read = 1
        WHERE user_role = 'donor' AND user_id = ?
    ", [$donorId]);

    // Refresh notifications
    $notifications = executeQuery("
        SELECT * FROM notifications
        WHERE user_role = 'donor' AND user_id = ?
        ORDER BY created_at DESC
    ", [$donorId]);

    // Redirect to remove query parameter
    header("Location: notifications.php");
    exit;
}

// Include header
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <?php
    // Determine the correct path for CSS files
    $basePath = '';
    if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
        $basePath = '../../';
    }
    ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $basePath; ?>assets/images/favicon.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Custom Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/dashboard.css">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    
    <!-- Shared Donor Dashboard Styles -->
    <?php include_once 'shared-styles.php'; ?>
    
    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
</head>
<body>
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
                            <span><?php echo $_SESSION['user_name']; ?></span>
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
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Your Notifications</h4>
                    <a href="?mark_all_read=1" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-check-all me-1"></i> Mark All as Read
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($notifications) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <a href="?id=<?php echo $notification['id']; ?>" class="list-group-item list-group-item-action py-3 <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div class="d-flex">
                                            <?php
                                            $iconClass = 'info-circle-fill text-primary';

                                            if (strpos($notification['title'], 'Donation') !== false) {
                                                $iconClass = 'droplet-fill text-danger';
                                            } elseif (strpos($notification['title'], 'Appointment') !== false) {
                                                $iconClass = 'calendar-check-fill text-success';
                                            } elseif (strpos($notification['title'], 'Reward') !== false) {
                                                $iconClass = 'award-fill text-warning';
                                            } elseif (strpos($notification['title'], 'Blood Drive') !== false) {
                                                $iconClass = 'calendar-event-fill text-primary';
                                            }
                                            ?>
                                            <div class="me-3">
                                                <i class="bi bi-<?php echo $iconClass; ?> fs-4"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1"><?php echo $notification['title']; ?></h5>
                                                <p class="mb-1"><?php echo $notification['message']; ?></p>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-primary rounded-pill">New</span>
                                        <?php endif; ?>
                                    </div>
                                </a>
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

<style>
    .dashboard-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0;
        margin-left: 300px;
        padding-top: 100px; /* Space for fixed header */
        position: relative;
        background-color: #f8f9fa;
    }
    
    .dashboard-header {
        background-color: #fff;
        border-bottom: 1px solid #e9ecef;
        position: fixed;
        top: 0;
        left: 300px; /* Position after sidebar */
        right: 0;
        z-index: 1021;
        height: auto;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        padding: 1rem 1.5rem;
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
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #2c3e50;
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
    
    @media (max-width: 991.98px) {
        .dashboard-content {
            margin-left: 0;
            padding-top: 100px; /* Space for fixed header on mobile */
        }
        .dashboard-header {
            left: 0;
            padding: 1rem;
        }
    }
    
    @media (max-width: 767.98px) {
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
    
    .list-group-item.unread {
        background-color: rgba(13, 110, 253, 0.05);
        font-weight: 500;
    }
</style>

<?php include_once '../../includes/footer.php'; ?>