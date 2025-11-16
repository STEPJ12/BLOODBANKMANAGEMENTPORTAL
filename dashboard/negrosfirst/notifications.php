<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

$pageTitle = "Notifications - Negros First";

// Handle mark as read action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notification_id = (int)$_POST['notification_id'];
    if ($notification_id > 0) {
        updateRow("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_role = 'negrosfirst'", [$notification_id]);
        $_SESSION['success_message'] = 'Notification marked as read';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    updateRow("UPDATE notifications SET is_read = 1 WHERE user_role = 'negrosfirst' AND is_read = 0", []);
    $_SESSION['success_message'] = 'All notifications marked as read';
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notification_id = (int)$_POST['notification_id'];
    if ($notification_id > 0) {
        deleteRow("DELETE FROM notifications WHERE id = ? AND user_role = 'negrosfirst'", [$notification_id]);
        $_SESSION['success_message'] = 'Notification deleted';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get notifications
$notifications = executeQuery("
    SELECT id, title, message, created_at, is_read 
    FROM notifications 
    WHERE user_role = 'negrosfirst' 
    ORDER BY created_at DESC
");

if (!is_array($notifications)) {
    $notifications = [];
}

// Get counts
$total_notifications = count($notifications);
$unread_count = count(array_filter($notifications, function($n) { return !$n['is_read']; }));


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

<div class="dashboard-container">
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h4 mb-1 fw-bold">Notifications</h2>
                    <p class="text-muted mb-0">Manage your system notifications and alerts.</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-primary"><?php echo $total_notifications; ?> Total</span>
                    <span class="badge bg-danger"><?php echo $unread_count; ?> Unread</span>
                    <?php if ($unread_count > 0): ?>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="mark_all_read" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-check-all me-1"></i>Mark All Read
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dashboard-main p-4">
            <!-- Session Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Notifications List -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bell me-2"></i>All Notifications
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($notifications)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="list-group-item <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <h6 class="mb-0 <?php echo $notification['is_read'] ? 'text-muted' : 'fw-bold'; ?>">
                                                            <?php echo htmlspecialchars($notification['title']); ?>
                                                        </h6>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-primary ms-2">New</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock me-1"></i>
                                                        <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="bi bi-three-dots"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if (!$notification['is_read']): ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                                <button type="submit" name="mark_read" class="dropdown-item">
                                                                    <i class="bi bi-check me-2"></i>Mark as Read
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <?php endif; ?>
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
                                    <i class="bi bi-bell-slash display-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">No Notifications</h5>
                                    <p class="text-muted">You don't have any notifications yet.</p>
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
