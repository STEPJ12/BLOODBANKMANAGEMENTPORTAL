<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

$pageTitle = "Notifications - Barangay";
$isDashboard = true;
require_once '../../config/db.php';

// Handle mark as read action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notification_id = (int)$_POST['notification_id'];
    if ($notification_id > 0) {
        updateRow("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_role = 'barangay'", [$notification_id]);
        $_SESSION['success_message'] = 'Notification marked as read';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    updateRow("UPDATE notifications SET is_read = 1 WHERE user_role = 'barangay' AND is_read = 0", []);
    $_SESSION['success_message'] = 'All notifications marked as read';
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notification_id = (int)$_POST['notification_id'];
    if ($notification_id > 0) {
        deleteRow("DELETE FROM notifications WHERE id = ? AND user_role = 'barangay'", [$notification_id]);
        $_SESSION['success_message'] = 'Notification deleted';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get notifications for barangay
$notifications = executeQuery("
    SELECT id, title, message, created_at, is_read 
    FROM notifications 
    WHERE user_role = 'barangay' 
    ORDER BY created_at DESC
");

if (!is_array($notifications)) {
    $notifications = [];
}

// Get counts
$total_notifications = count($notifications);
$unread_count = count(array_filter($notifications, function($n) { return !$n['is_read']; }));

// Get barangay information for header
$barangayId = $_SESSION['user_id'];
$barangayRow = getRow("SELECT * FROM barangay_users WHERE id = ?", [$barangayId]);
$barangayName = $barangayRow['name'] ?? 'Barangay';

// Determine base path
$basePath = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>
    
    <link rel="stylesheet" href="../../css/barangay-portal.css">
    <link rel="icon" type="image/png" href="<?php echo $basePath; ?>assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/bootstrap-icons-offline.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    
    <?php include_once 'shared-styles.php'; ?>
    
    <style>
    /* Header Layout Improvements */
    .dashboard-header {
        padding: 1.5rem 2rem !important;
    }

    .header-content h1 {
        font-size: 1.75rem !important;
        font-weight: 700 !important;
        color: #ffffff !important;
        margin-bottom: 0.5rem !important;
        line-height: 1.2 !important;
    }

    .header-content p {
        color: rgba(255, 255, 255, 0.9) !important;
        font-size: 0.95rem !important;
        margin-bottom: 0 !important;
        line-height: 1.5 !important;
        padding-left: 0 !important;
    }

    .header-content .badge {
        font-size: 0.85rem !important;
        padding: 0.5rem 0.75rem !important;
        font-weight: 600 !important;
    }

    /* User Dropdown Styling */
    .user-dropdown {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(20px) saturate(180%);
        border: 2px solid rgba(255, 255, 255, 0.5) !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
        position: relative !important;
        z-index: 1021 !important;
        padding: 0.5rem 1rem !important;
        border-radius: 10px !important;
    }

    .user-dropdown:hover {
        background: rgba(255, 255, 255, 1) !important;
        transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2) !important;
    }

    .user-dropdown .avatar {
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .user-dropdown .avatar {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%) !important;
        border-radius: 10px !important;
        padding: 0.4rem !important;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3) !important;
        transition: all 0.3s ease !important;
    }

    .user-dropdown:hover .avatar {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5) !important;
    }

    .user-dropdown .avatar i {
        font-size: 1.5rem;
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

    .user-dropdown .user-info {
        min-width: 120px;
    }

    .user-dropdown .user-info span {
        font-size: 0.9rem;
        color: #2a363b;
    }

    .user-dropdown .user-info small {
        font-size: 0.75rem;
        color: #64748b;
    }

    /* Notification Bell Button Styling */
    #notificationDropdown {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(20px) saturate(180%);
        border: 2px solid rgba(255, 255, 255, 0.5) !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
        color: #2a363b !important;
        padding: 0.5rem 0.75rem !important;
        border-radius: 10px !important;
        transition: all 0.3s ease !important;
    }

    #notificationDropdown:hover {
        background: rgba(255, 255, 255, 1) !important;
        transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2) !important;
        color: #3b82f6 !important;
    }

    #notificationDropdown {
        position: relative;
        overflow: visible;
    }

    #notificationDropdown i {
        font-size: 1.25rem;
        background: linear-gradient(135deg, #3b82f6 0%, #eab308 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        transition: all 0.3s ease !important;
        filter: drop-shadow(0 2px 4px rgba(59, 130, 246, 0.3));
    }

    #notificationDropdown:hover i {
        transform: scale(1.15) rotate(-10deg);
        filter: drop-shadow(0 4px 8px rgba(234, 179, 8, 0.5));
    }

    /* Notification badge animation */
    #notificationBadge {
        animation: pulse-badge 2s infinite;
    }

    @keyframes pulse-badge {
        0%, 100% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.1);
            opacity: 0.9;
        }
    }

    /* Mark All Read Button Styling */
    .btn-outline-success {
        border: 2px solid #10b981 !important;
        color: #10b981 !important;
        background: rgba(255, 255, 255, 0.9) !important;
        font-weight: 600 !important;
        padding: 0.5rem 1rem !important;
        border-radius: 8px !important;
        transition: all 0.3s ease !important;
    }

    .btn-outline-success:hover {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        color: #ffffff !important;
        border-color: #10b981 !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3) !important;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dashboard-header .row {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .dashboard-header .col-md-6 {
            width: 100%;
            margin-bottom: 1rem;
        }

        .dashboard-header .col-md-6:last-child {
            margin-bottom: 0;
        }
    }

    /* Notification-specific enhancements */
    .notification-list {
        max-height: none;
    }
    
    .list-group-item {
        padding: 1.25rem !important;
    }
    
    .list-group-item:last-child {
        margin-bottom: 0 !important;
    }
    
    /* Icon Styling in Notifications */
    .list-group-item i.bi-circle {
        color: #fbbf24 !important;
        font-size: 0.875rem !important;
    }
    
    .list-group-item i.bi-check-circle {
        color: #94a3b8 !important; /* Softer gray for read notifications */
        font-size: 0.875rem !important;
    }
    
    /* Empty State - Better visibility */
    .text-center .display-1 {
        color: #475569 !important; /* Softer gray for empty state icon */
    }
    
    .text-center h5 {
        color: #cbd5e1 !important; /* Lighter gray for headings */
    }
    
    .text-center p {
        color: #94a3b8 !important; /* Softer gray for paragraphs */
    }
    
    .list-group-item .text-muted.fw-bold {
        color: #f1f5f9 !important; /* Soft white for bold muted text */
        font-weight: 600 !important;
    }
    
    /* Notification Item Styling */
    .notification-item {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        transition: all 0.3s ease !important;
    }
    
    .notification-item:hover {
        background: #f8fafc !important;
        border-color: #3b82f6 !important;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1) !important;
    }
    
    .notification-unread {
        background: #fafbff !important;
        border-left: 4px solid #3b82f6 !important;
    }
    
    .notification-read {
        background: #fafafa !important;
        border-left: 4px solid #e2e8f0 !important;
    }
    
    /* Notification Title Styling */
    .notification-title {
        color: #1e293b !important;
        font-size: 1.1rem !important;
    }
    
    .notification-title.text-muted {
        color: #64748b !important;
    }
    
    .notification-title i {
        color: #3b82f6 !important;
    }
    
    .notification-title.text-muted i {
        color: #94a3b8 !important;
    }
    
    /* Notification Message Styling */
    .notification-message {
        color: #475569 !important;
        line-height: 1.6 !important;
        font-size: 0.95rem !important;
    }
    
    /* Notification Time Styling */
    .notification-time {
        color: #64748b !important;
        font-size: 0.875rem !important;
    }
    
    .notification-time i {
        color: #94a3b8 !important;
    }
    
    /* Badge Styling */
    .notification-item .badge {
        font-weight: 600 !important;
        padding: 0.35rem 0.65rem !important;
        font-size: 0.75rem !important;
    }
    
    /* Dropdown Button Styling */
    .notification-item .btn-outline-secondary {
        border-color: #cbd5e1 !important;
        color: #64748b !important;
    }
    
    .notification-item .btn-outline-secondary:hover {
        background: #3b82f6 !important;
        border-color: #3b82f6 !important;
        color: #ffffff !important;
    }
    
    /* Card Header Styling */
    .card-header {
        background: #ffffff !important;
        border-bottom: 2px solid #e2e8f0 !important;
    }
    
    .card-header .card-title {
        color: #1e293b !important;
        font-weight: 600 !important;
    }
    
    .card-header .card-title i {
        color: #3b82f6 !important;
    }
    
    /* Empty State Styling */
    .text-center .bi-bell-slash {
        color: #cbd5e1 !important;
    }
    
    .text-center h5 {
        color: #1e293b !important;
    }
    
    .text-center p.text-muted {
        color: #64748b !important;
    }
    </style>
    
    <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
</head>
<body>
<div class="dashboard-container">
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
                            <h1 class="mb-1">Notifications</h1>
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
                        
                        <?php include_once '../../includes/notification_bell.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <div class="dashboard-main p-4">
            <!-- Session Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Notifications List -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header p-4">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bell me-2"></i>Blood Request Notifications
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!empty($notifications)): ?>
                                <div class="notification-list">
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="list-group-item notification-item <?php echo $notification['is_read'] ? 'notification-read' : 'notification-unread'; ?> p-4 mb-3">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
                                                        <h6 class="mb-0 notification-title <?php echo $notification['is_read'] ? 'text-muted' : 'fw-bold'; ?>">
                                                            <i class="bi bi-<?php echo $notification['is_read'] ? 'check-circle' : 'circle'; ?> me-2"></i>
                                                            <?php echo htmlspecialchars($notification['title']); ?>
                                                        </h6>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-danger">New</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="mb-3 notification-message">
                                                        <?php echo htmlspecialchars($notification['message']); ?>
                                                    </p>
                                                    <small class="notification-time d-flex align-items-center">
                                                        <i class="bi bi-clock me-2"></i>
                                                        <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <?php if (!$notification['is_read']): ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                                <button type="submit" name="mark_read" class="dropdown-item">
                                                                    <i class="bi bi-check-circle me-2"></i>Mark as Read
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this notification?')">
                                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                                <button type="submit" name="delete_notification" class="dropdown-item text-danger">
                                                                    <i class="bi bi-trash me-2"></i>Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="mb-4">
                                        <i class="bi bi-bell-slash" style="font-size: 4rem; color: rgba(255, 255, 255, 0.3);"></i>
                                    </div>
                                    <h5 class="mt-3 mb-2">No Notifications</h5>
                                    <p class="text-muted">You don't have any blood request notifications yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts
    setTimeout(function() {
        document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
            try {
                new bootstrap.Alert(alert).close();
            } catch (e) {
                alert.style.display = 'none';
            }
        });
    }, 5000);
});
</script>

</body>
</html>