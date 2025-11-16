<?php
if (!isset($_SESSION)) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../../config/db.php';

// Get notifications from database
$notifications = [];
$notification_count = 0;

// Check if notifications table exists
$table_exists = false;
try {
    $check_table = executeQuery("SHOW TABLES LIKE 'notifications'");
    $table_exists = !empty($check_table);
} catch (Exception $e) {
    $table_exists = false;
}

if ($table_exists) {
    try {
        // Get Red Cross user ID from session
        $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        
        // Get recent notifications for redcross users (either specific user_id OR all redcross role notifications OR user_id = 0 for broadcasts)
        // Show notifications that:
        // 1. Are for this specific user (user_id matches)
        // 2. Are broadcasts to all redcross users (user_id = 0 and user_role = 'redcross')
        // 3. Are for redcross role in general (user_role = 'redcross' - for backward compatibility)
        $notification_query = "
            SELECT 
                n.id,
                n.title,
                n.message,
                n.user_role,
                n.is_read,
                n.created_at,
                CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, n.created_at, NOW()) < 60 
                    THEN CONCAT(TIMESTAMPDIFF(MINUTE, n.created_at, NOW()), ' minutes ago')
                    WHEN TIMESTAMPDIFF(HOUR, n.created_at, NOW()) < 24 
                    THEN CONCAT(TIMESTAMPDIFF(HOUR, n.created_at, NOW()), ' hours ago')
                    ELSE CONCAT(TIMESTAMPDIFF(DAY, n.created_at, NOW()), ' days ago')
                END as time_ago
            FROM notifications n 
            WHERE n.user_role = 'redcross' 
            AND (n.user_id = ? OR n.user_id = 0 OR n.user_id IS NULL)
            AND n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY n.created_at DESC 
            LIMIT 10
        ";
        
        $notifications = executeQuery($notification_query, [$currentUserId]) ?: [];
        
        // Count unread notifications for redcross users
        $unread_query = "SELECT COUNT(*) as count FROM notifications 
                         WHERE user_role = 'redcross' 
                         AND (user_id = ? OR user_id = 0 OR user_id IS NULL)
                         AND is_read = 0 
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $unread_result = executeQuery($unread_query, [$currentUserId]);
        $notification_count = $unread_result[0]['count'] ?? 0;
        
    } catch (Exception $e) {
        $table_exists = false;
    }
}

// If no notifications found, set empty arrays
if (!$table_exists) {
    $notifications = [];
    $notification_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Blood Donation System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #DC143C;
            --secondary-color: #2C3E50;
            --accent-color: #E74C3C;
            --success-color: #27AE60;
            --warning-color: #F39C12;
            --danger-color: #E74C3C;
            --info-color: #3498DB;
            --light-bg: #F8F9FA;
            --white: #FFFFFF;
            --gray-100: #F8F9FA;
            --gray-200: #E9ECEF;
            --gray-300: #DEE2E6;
            --gray-400: #CED4DA;
            --gray-500: #ADB5BD;
            --gray-600: #6C757D;
            --gray-700: #495057;
            --gray-800: #343A40;
            --gray-900: #212529;
            --border-radius: 12px;
            --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --box-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light-bg);
            color: var(--gray-800);
            line-height: 1.6;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            box-shadow: var(--box-shadow-lg);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-logo {
            width: 60px;
            height: 60px;
            background: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: var(--box-shadow);
            position: relative;
        }

        /* Red Cross Symbol */
        .red-cross-symbol {
            position: relative;
            width: 30px;
            height: 30px;
        }

        .red-cross-symbol::before {
            content: '';
            position: absolute;
            width: 6px;
            height: 30px;
            background: var(--primary-color);
            top: 0;
            left: 50%;
            transform: translateX(-50%);
        }

        .red-cross-symbol::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 6px;
            background: var(--primary-color);
            top: 50%;
            left: 0;
            transform: translateY(-50%);
        }

        .sidebar-title {
            color: var(--white);
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .sidebar-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            margin: 0;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 0;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            transform: translateX(4px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        /* Red Cross icon for navigation */
        .nav-red-cross {
            position: relative;
            width: 16px;
            height: 16px;
            display: inline-block;
            margin-right: 0.75rem;
        }

        .nav-red-cross::before {
            content: '';
            position: absolute;
            width: 3px;
            height: 16px;
            background: rgba(255, 255, 255, 0.9);
            top: 0;
            left: 50%;
            transform: translateX(-50%);
        }

        .nav-red-cross::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 3px;
            background: rgba(255, 255, 255, 0.9);
            top: 50%;
            left: 0;
            transform: translateY(-50%);
        }

        .sidebar-footer {
            flex-shrink: 0;
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        }

        .user-info {
            display: flex;
            align-items: center;
            color: var(--white);
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
        }

        .logout-btn {
            width: 100%;
            padding: 0.5rem 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--white);
            border-radius: var(--border-radius);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            background: var(--light-bg);
        }

        /* Top Header */
        .top-header {
            background: var(--white);
            padding: 1rem 2rem;
            box-shadow: var(--box-shadow);
            border-bottom: 1px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin: 0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: var(--gray-600);
            font-size: 1.2rem;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .notification-btn:hover {
            background: var(--gray-100);
            color: var(--primary-color);
        }

        .notification-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.2);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger-color);
            color: var(--white);
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: var(--warning-color);
            border-left: 4px solid var(--warning-color);
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: var(--info-color);
            border-left: 4px solid var(--info-color);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-footer {
                flex-shrink: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .top-header {
                padding: 1rem;
            }

            .content-area {
                padding: 1rem;
            }
        }

        /* Notification Modal */
        .notification-modal {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 350px;
            max-height: 400px;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-lg);
            border: 1px solid var(--gray-200);
            z-index: 1001;
            display: none;
            overflow: hidden;
        }

        .notification-modal.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: var(--white);
        }

        .notification-header h6 {
            margin: 0;
            font-weight: 600;
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: var(--gray-50);
            transform: translateX(2px);
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item.unread {
            background-color: rgba(220, 20, 60, 0.05);
            border-left: 3px solid var(--primary-color);
        }

        .notification-title {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }

        .notification-message {
            color: var(--gray-600);
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .notification-time {
            color: var(--gray-500);
            font-size: 0.75rem;
        }

        .notification-footer {
            padding: 0.75rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            text-align: center;
        }

        .notification-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .notification-footer a:hover {
            text-decoration: underline;
        }

        .empty-notifications {
            padding: 2rem 1.5rem;
            text-align: center;
            color: var(--gray-500);
        }

        .empty-notifications i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--gray-400);
        }

        /* Mobile Toggle Button */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--gray-600);
            font-size: 1.5rem;
            padding: 0.5rem;
        }

        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }

            .notification-modal {
                width: 300px;
                right: -50px;
                max-width: calc(100vw - 40px);
            }
        }

        @media (max-width: 480px) {
            .notification-modal {
                width: 280px;
                right: -80px;
                max-width: calc(100vw - 20px);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="../../assets/img/rclgo.png" alt="Red Cross Logo" style="width: 40px; height: 40px; object-fit: contain;">
            </div>
            <h3 class="sidebar-title">Red Cross</h3>
            <p class="sidebar-subtitle">Blood Bank Portal</p>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="bi bi-house-door"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="inventory.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
                    <i class="bi bi-droplet"></i>
                    Blood Inventory
                </a>
            </div>
            <div class="nav-item">
                <a href="donations.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'donations.php' ? 'active' : ''; ?>">
                    <i class="bi bi-heart-pulse"></i>
                    Donations
                </a>
            </div>
            <div class="nav-item">
                <a href="blood-requests.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'blood-requests.php' ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard2-pulse"></i>
                    Blood Requests
                </a>
            </div>
            <div class="nav-item">
                <a href="blood-drives.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'blood-drives.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-event"></i>
                    Blood Drives
                </a>
            </div>
            <div class="nav-item">
                <a href="announcements.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>">
                    <i class="bi bi-megaphone"></i>
                    Announcements
                </a>
            </div>
            
            <div class="nav-item">
                <a href="appointments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-check"></i>
                    Appointments
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    Reports
                </a>
            </div>
            
            <div class="nav-item">
                <a href="maintenance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'maintenance.php' ? 'active' : ''; ?>">
                    <i class="bi bi-tools"></i>
                    Maintenance
                </a>
            </div>
            
        </nav>
        
        <div class="sidebar-footer">
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-info">
                <div class="user-avatar">
                    <i class="bi bi-person"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 0.875rem;">
                        <?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Red Cross Admin'; ?>
                    </div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">Administrator</div>
                </div>
            </div>
            <a href="../../logout.php?redirect=redcrossportal.php" class="logout-btn">
                <i class="bi bi-box-arrow-right me-2"></i>
                Logout
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <div class="header-content">
                <div class="header-left">
                    <button class="mobile-toggle" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="page-title">
                        <?php 
                        $page_titles = [
                            'index.php' => 'Dashboard Overview',
                            'inventory.php' => 'Blood Inventory Management',
                            'donations.php' => 'Donation Records',
                            'blood-requests.php' => 'Blood Request Management',
                            'blood-request-history.php' => 'Blood Request History',
                            'blood-drives.php' => 'Blood Drive Events',
                            'announcements.php' => 'Announcements Management',
                            'appointments.php' => 'Appointment Scheduling',
                            'reports.php' => 'Reports',
                            'maintenance.php' => 'System Maintenance'
                        ];
                        $current_page = basename($_SERVER['PHP_SELF']);
                        echo isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'Red Cross Portal';
                        ?>
                    </h1>
                </div>
                <div class="header-actions">
                    <div style="position: relative;">
                        <button class="notification-btn" onclick="toggleNotificationModal()">
                            <i class="bi bi-bell"></i>
                            <?php if ($notification_count > 0): ?>
                            <span class="notification-badge"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="notification-modal" id="notificationModal">
                            <div class="notification-header">
                                <h6><i class="bi bi-bell me-2"></i>Notifications</h6>
                            </div>
                            <div class="notification-list">
                                <?php if (!empty($notifications)): ?>
                                    <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>" 
                                         role="button"
                                         tabindex="0"
                                         onclick="markAsRead(<?php echo $notification['id']; ?>)"
                                         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();markAsRead(<?php echo $notification['id']; ?>);}">
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                        <div class="notification-time"><?php echo htmlspecialchars($notification['time_ago']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-notifications">
                                        <i class="bi bi-bell-slash"></i>
                                        <div>No notifications</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="notification-footer">
                                <a href="notifications.php">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (isset($_SESSION['message'])): ?>
                <?php 
                    $msgType = htmlspecialchars($_SESSION['message_type'] ?? 'info', ENT_QUOTES, 'UTF-8');
                    $icon = $msgType == 'success' ? 'check-circle' : ($msgType == 'danger' ? 'exclamation-triangle' : 'info-circle');
                ?>
                <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $icon; ?> me-2"></i>
                    <?php
                        echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8');
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        function toggleNotificationModal() {
            const modal = document.getElementById('notificationModal');
            modal.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            const notificationBtn = document.querySelector('.notification-btn');
            const notificationModal = document.getElementById('notificationModal');
            
            // Close sidebar on mobile
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }

            // Close notification modal when clicking outside
            if (!notificationBtn.contains(event.target) && 
                !notificationModal.contains(event.target) && 
                notificationModal.classList.contains('show')) {
                notificationModal.classList.remove('show');
            }
        });

        // Close notification modal when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const notificationModal = document.getElementById('notificationModal');
                if (notificationModal.classList.contains('show')) {
                    notificationModal.classList.remove('show');
                }
            }
        });

        // Enhanced notification click handling with event delegation
        document.addEventListener('click', function(event) {
            const notificationItem = event.target.closest('.notification-item');
            if (notificationItem && notificationItem.onclick) {
                // Extract notification ID from onclick attribute
                const onclickAttr = notificationItem.getAttribute('onclick');
                if (onclickAttr && onclickAttr.includes('markAsRead(')) {
                    const match = onclickAttr.match(/markAsRead\((\d+)\)/);
                    if (match) {
                        const notificationId = parseInt(match[1]);
                        console.log('Notification clicked via event delegation:', notificationId);
                        markAsRead(notificationId);
                    }
                }
            }
        });

        // Mark notification as read
        function markAsRead(notificationId) {
            console.log('Marking notification as read:', notificationId);
            
            // Prevent multiple clicks
            if (window.markingAsRead) {
                console.log('Already processing a notification, please wait...');
                return;
            }
            window.markingAsRead = true;
            
            // Immediately update the UI to show it's being processed
            const notificationItem = document.querySelector(`[onclick="markAsRead(${notificationId})"]`);
            if (notificationItem) {
                notificationItem.style.opacity = '0.7';
                notificationItem.style.pointerEvents = 'none';
                notificationItem.style.cursor = 'wait';
            }
            
            fetch('../../api/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo htmlspecialchars(get_csrf_token()); ?>'
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Remove unread class and update badge count
                    if (notificationItem) {
                        notificationItem.classList.remove('unread');
                        notificationItem.style.opacity = '1';
                        notificationItem.style.pointerEvents = 'auto';
                        
                        // Add a visual indicator that it was marked as read
                        notificationItem.style.backgroundColor = '#d4edda';
                        setTimeout(() => {
                            notificationItem.style.backgroundColor = '';
                        }, 2000);
                    }
                    
                    // Update notification badge
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent);
                        const newCount = currentCount - 1;
                        if (newCount > 0) {
                            badge.textContent = newCount;
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                    
                    console.log('Notification marked as read successfully');
                } else {
                    console.error('Failed to mark notification as read:', data.message);
                    // Revert UI changes if failed
                    if (notificationItem) {
                        notificationItem.style.opacity = '1';
                        notificationItem.style.pointerEvents = 'auto';
                        notificationItem.style.cursor = 'pointer';
                    }
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
                // Revert UI changes if failed
                if (notificationItem) {
                    notificationItem.style.opacity = '1';
                    notificationItem.style.pointerEvents = 'auto';
                    notificationItem.style.cursor = 'pointer';
                }
            })
            .finally(() => {
                // Always reset the flag
                window.markingAsRead = false;
            });
        }
    </script>
