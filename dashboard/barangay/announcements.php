<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

// Set page title
$pageTitle = "Manage Announcements - Blood Bank Portal";
$isDashboard = true;

// Include database connection
require_once '../../config/db.php';

// Get Barangay information
$barangayId = $_SESSION['user_id'];
$barangayRow = getRow("SELECT * FROM barangay_users WHERE id = ?", [$barangayId]);
$barangayName = $barangayRow['name'] ?? 'Barangay';

// Check for session messages (from redirect after POST)
$message = $_SESSION['announcement_message'] ?? '';
$alertType = $_SESSION['announcement_alert_type'] ?? '';
// Clear session messages after reading
unset($_SESSION['announcement_message']);
unset($_SESSION['announcement_alert_type']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['announcement_message'] = 'Invalid form submission. Please refresh and try again.';
        $_SESSION['announcement_alert_type'] = 'danger';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
    if (isset($_POST['create_announcement'])) {
        $title = normalize_input($_POST['title'] ?? '', true);
        $content = normalize_input($_POST['content'] ?? '', false);
        $link = sanitize($_POST['link']);
        $expiry_date = sanitize($_POST['expiry_date']);
        $expiryParam = ($expiry_date === '' || strtolower($expiry_date) === 'null') ? null : $expiry_date;

        // Validate inputs
        if (empty($title) || empty($content)) {
            $_SESSION['announcement_message'] = 'Title and content are required.';
            $_SESSION['announcement_alert_type'] = 'danger';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // Insert with 'barangay' as organization_type and created_by as barangay_id
        $result = insertRow("
            INSERT INTO announcements (
                title, content, organization_type, created_by, status, expiry_date, created_at, updated_at
            ) VALUES (?, ?, 'barangay', ?, 'Active', ?, NOW(), NOW())
        ", [$title, $content, $barangayId, $expiryParam]);

        if ($result !== false) {
            // Notify all donors in this barangay
            $donors = executeQuery("SELECT id, name, phone FROM donor_users WHERE barangay_id = ?", [$barangayId]);
            
            // Send SMS notifications
            try {
                require_once '../../includes/sim800c_sms.php';
                require_once '../../includes/notification_templates.php';
                
                $barangayInfo = getRow("SELECT name FROM barangay_users WHERE id = ?", [$barangayId]);
                $barangayName = $barangayInfo['name'] ?? 'your barangay';
                $smsSentCount = 0;
                $smsErrorCount = 0;
                
                foreach ($donors as $donor) {
                    $donorId = $donor['id'];
                    
                    // Create in-app notification
                    executeQuery("
                        INSERT INTO notifications (
                            title, message, user_id, user_role, is_read, created_at
                        ) VALUES (?, ?, ?, 'donor', 0, NOW())
                    ", [
                        "New Barangay Announcement",
                        "Your barangay has posted a new announcement: " . $title,
                        $donorId
                    ]);
                    
                    // Send SMS if phone number exists
                    $donorPhone = $donor['phone'] ?? '';
                    $donorName = $donor['name'] ?? 'Donor';
                    
                    if (!empty($donorPhone)) {
                        // Try to decrypt phone number if encrypted
                        if (function_exists('decrypt_value')) {
                            $decryptedPhone = decrypt_value($donorPhone);
                            if (!empty($decryptedPhone)) {
                                $donorPhone = $decryptedPhone;
                            }
                        }
                        
                        if (!empty($donorPhone) && trim($donorPhone) !== '') {
                            // Build professional SMS message
                            $smsMessage = "Hello {$donorName}, this is from {$barangayName}. ";
                            $smsMessage .= "A new announcement has been posted: {$title}. ";
                            $smsMessage .= "Please check your dashboard for details. Thank you!";
                            $smsMessage = format_notification_message($smsMessage);
                            
                            try {
                                $smsResult = send_sms_sim800c($donorPhone, $smsMessage);
                                if ($smsResult['success']) {
                                    $smsSentCount++;
                                } else {
                                    $smsErrorCount++;
                                }
                            } catch (Exception $smsEx) {
                                $smsErrorCount++;
                                error_log('[BARANGAY_SMS_ERR] Exception sending announcement SMS to donor ID ' . $donorId . ': ' . $smsEx->getMessage());
                            }
                        }
                    }
                }
                
                error_log('[BARANGAY_SMS] Announcement SMS summary - Sent: ' . $smsSentCount . ', Failed: ' . $smsErrorCount);
            } catch (Exception $smsEx) {
                error_log('[BARANGAY_SMS_ERR] Exception in announcement SMS: ' . $smsEx->getMessage());
                // Still create notifications even if SMS fails
            }

            $_SESSION['announcement_message'] = 'Announcement created successfully.';
            $_SESSION['announcement_alert_type'] = 'success';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $_SESSION['announcement_message'] = 'Failed to create announcement.';
            $_SESSION['announcement_alert_type'] = 'danger';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    } elseif (isset($_POST['update_announcement'])) {
        $id = sanitize($_POST['announcement_id']);
        $title = normalize_input($_POST['title'] ?? '', true);
        $content = normalize_input($_POST['content'] ?? '', false);
        $expiry_date = sanitize($_POST['expiry_date']);
        $status = sanitize($_POST['status']);
        $statusFormatted = ucfirst(strtolower($status));
        $expiryParam = ($expiry_date === '' || strtolower($expiry_date) === 'null') ? null : $expiry_date;

        if (empty($title) || empty($content)) {
            $_SESSION['announcement_message'] = 'Title and content are required.';
            $_SESSION['announcement_alert_type'] = 'danger';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $result = updateRow("
                UPDATE announcements
                SET title = ?, content = ?,
                    expiry_date = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND organization_type = 'barangay' AND (created_by = ? OR created_by IS NULL)
            ", [$title, $content, $expiryParam, $statusFormatted, $id, $barangayId]);

            if ($result !== false) {
                $_SESSION['announcement_message'] = 'Announcement updated successfully.';
                $_SESSION['announcement_alert_type'] = 'success';
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $_SESSION['announcement_message'] = 'Failed to update announcement.';
                $_SESSION['announcement_alert_type'] = 'danger';
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    } elseif (isset($_POST['delete_announcement'])) {
        $id = sanitize($_POST['announcement_id']);
        
        $result = deleteRow("
            DELETE FROM announcements
            WHERE id = ? AND organization_type = 'barangay' AND (created_by = ? OR created_by IS NULL)
        ", [$id, $barangayId]);

        if ($result !== false) {
            $_SESSION['announcement_message'] = 'Announcement deleted successfully.';
            $_SESSION['announcement_alert_type'] = 'success';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $_SESSION['announcement_message'] = 'Failed to delete announcement.';
            $_SESSION['announcement_alert_type'] = 'danger';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    }
}

// Get all announcements for Barangay organization type
$announcements = executeQuery("
    SELECT a.*,
           CASE 
               WHEN a.organization_type = 'redcross' THEN 'Red Cross'
               WHEN a.organization_type = 'negrosfirst' THEN 'Negros First'
               WHEN a.organization_type = 'barangay' THEN 'Barangay'
               WHEN a.organization_type = 'system' THEN 'System'
               WHEN a.organization_type = 'general' THEN 'General'
               ELSE 'Unknown'
           END as organization_name
    FROM announcements a
    WHERE a.organization_type = 'barangay' AND (a.created_by = ? OR a.created_by IS NULL)
    ORDER BY a.created_at DESC
", [$barangayId]);

if (!is_array($announcements)) {
    $announcements = [];
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

     <!-- Favicon -->
     <link rel="icon" type="image/png" href="<?php echo $basePath; ?>assets/images/favicon.png">

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons - CDN with fallback -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">

<!-- Custom Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    

    <!-- Custom Fonts - Using system fonts for offline -->
    <style>
        @import url('<?php echo $basePath; ?>assets/css/fonts.css');
    </style>
    <!-- Fallback to system fonts if font file not available -->
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
    </style>

    <!-- Chart.js - Offline -->
    <script src="<?php echo $basePath; ?>assets/js/chart.min.js"></script>
    <!-- Fallback message if Chart.js not found locally -->
    <script>
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not found locally. Please download Chart.js and place chart.min.js in assets/js/');
        }
    </script>

    <!-- Custom CSS -->
    <?php
    // Determine the correct path for CSS files - MUST be defined before use
    $basePath = '';
    if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
        $basePath = '../../';
    }
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    <link rel="stylesheet" href="../../css/barangay-portal.css">
    
    <?php include_once 'shared-styles.php'; ?>
    
    <style>
    /* Fix notification dropdown positioning - prevent overlap */
    .notification-dropdown,
    ul.notification-dropdown,
    #notificationDropdownMenu {
        position: absolute !important;
        z-index: 1060 !important;
        transform: translateX(0) !important;
        margin-top: 0.5rem !important;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        border: 1px solid rgba(0, 0, 0, 0.15) !important;
        max-height: 400px !important;
        overflow-y: auto !important;
    }
    
    /* Ensure dashboard content is positioned correctly */
    .dashboard-content {
        position: relative !important;
        z-index: 1 !important;
    }
    
    /* Fix dropdown button positioning */
    #notificationDropdown {
        position: relative !important;
        z-index: 1021 !important;
    }
    
    .dropdown-menu.show {
        display: block !important;
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
                            <h1 class="mb-1">Announcements Management</h1>
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
        <div class="dashboard-main p-3">
            <!-- Action Buttons Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
            
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                        <i class="bi bi-plus-circle me-2"></i>New Announcement
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (count($announcements) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    echo ($announcement['organization_type'] === 'negrosfirst' || $announcement['organization_type'] === NULL || $announcement['organization_type'] === '') ? 'warning' : 'primary';
                                                ?>">
                                                    <?php echo htmlspecialchars($announcement['organization_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    echo $announcement['status'] === 'Active' ? 'success' : 'secondary';
                                                ?>">
                                                    <?php echo ucfirst($announcement['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></td>
                                            <td>
                                                <?php
                                                    echo $announcement['expiry_date'] ?
                                                        date('M d, Y', strtotime($announcement['expiry_date'])) :
                                                        'No expiry';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($announcement['organization_type'] === 'barangay' && ($announcement['created_by'] == $barangayId || $announcement['created_by'] === null)): ?>
                                                    <button class="btn btn-sm btn-outline-primary me-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editAnnouncementModal"
                                                            data-announcement='<?php echo json_encode($announcement); ?>'>
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteAnnouncementModal"
                                                            data-announcement-id="<?php echo $announcement['id']; ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">Read Only</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-megaphone display-1 text-muted mb-3"></i>
                            <p class="text-muted mb-0">No announcements found. Create your first announcement!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="content">Content</label>
                        <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="expiry_date">Expiry Date</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                        </div>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_announcement" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                <input type="hidden" name="announcement_id" id="edit_announcement_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_title">Title</label>
                        <input type="text" class="form-control" name="title" id="edit_title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_content">Content</label>
                        <textarea class="form-control" name="content" id="edit_content" rows="5" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="edit_status">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="edit_expiry_date">Expiry Date</label>
                            <input type="date" class="form-control" name="expiry_date" id="edit_expiry_date">
                        </div>
                    </div>
                   
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_announcement" class="btn btn-primary">Update Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Announcement Modal -->
<div class="modal fade" id="deleteAnnouncementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                <input type="hidden" name="announcement_id" id="delete_announcement_id">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete this announcement? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_announcement" class="btn btn-danger">Delete Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for Bootstrap to be fully loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded');
        return;
    }
    
    // Handle edit announcement modal
    document.querySelectorAll('[data-bs-target="#editAnnouncementModal"]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const announcementData = this.getAttribute('data-announcement');
            if (!announcementData) {
                console.error('No announcement data found');
                alert('Error: No announcement data found');
                return;
            }
            
            try {
                const announcement = JSON.parse(announcementData);
                
                // Populate form fields
                const editIdField = document.getElementById('edit_announcement_id');
                const editTitleField = document.getElementById('edit_title');
                const editContentField = document.getElementById('edit_content');
                const editStatusField = document.getElementById('edit_status');
                const editLinkField = document.getElementById('edit_link');
                const editExpiryField = document.getElementById('edit_expiry_date');
                
                if (editIdField) editIdField.value = announcement.id || '';
                if (editTitleField) editTitleField.value = announcement.title || '';
                if (editContentField) editContentField.value = announcement.content || '';
                
                // Handle status - match database format (Active/Inactive)
                if (editStatusField) {
                    const status = announcement.status || 'Active';
                    editStatusField.value = status;
                }
                
                if (editLinkField) editLinkField.value = announcement.link || '';
                
                // Format expiry date for date input (YYYY-MM-DD)
                if (editExpiryField) {
                    if (announcement.expiry_date && announcement.expiry_date !== '0000-00-00' && announcement.expiry_date !== 'No expiry' && announcement.expiry_date !== null) {
                        try {
                            const expiryDate = new Date(announcement.expiry_date);
                            if (!isNaN(expiryDate.getTime())) {
                                editExpiryField.value = expiryDate.toISOString().split('T')[0];
                            } else {
                                editExpiryField.value = '';
                            }
                        } catch (dateError) {
                            editExpiryField.value = '';
                        }
                    } else {
                        editExpiryField.value = '';
                    }
                }
                
                // Show the modal using Bootstrap
                const modalElement = document.getElementById('editAnnouncementModal');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                } else {
                    console.error('Edit modal element not found');
                }
            } catch (error) {
                console.error('Error parsing announcement data:', error);
                alert('Error loading announcement data. Please try again.');
            }
        });
    });

    // Handle delete announcement modal
    document.querySelectorAll('[data-bs-target="#deleteAnnouncementModal"]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const announcementId = this.getAttribute('data-announcement-id');
            if (!announcementId) {
                console.error('No announcement ID found');
                alert('Error: No announcement ID found');
                return;
            }
            
            const deleteIdField = document.getElementById('delete_announcement_id');
            if (deleteIdField) {
                deleteIdField.value = announcementId;
            }
            
            // Show the modal using Bootstrap
            const modalElement = document.getElementById('deleteAnnouncementModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error('Delete modal element not found');
            }
        });
    });
    
    // Handle create announcement modal - reset form when opening
    const createBtn = document.querySelector('[data-bs-target="#createAnnouncementModal"]');
    if (createBtn) {
        createBtn.addEventListener('click', function(e) {
            // Reset form when opening create modal
            const form = document.querySelector('#createAnnouncementModal form');
            if (form) {
                form.reset();
            }
        });
    }
    
    // Ensure X and Cancel buttons work for all modals
    const modals = ['createAnnouncementModal', 'editAnnouncementModal', 'deleteAnnouncementModal'];
    
    modals.forEach(modalId => {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            // Initialize modal instance if it doesn't exist
            let modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (!modalInstance) {
                modalInstance = new bootstrap.Modal(modalElement, {
                    backdrop: true,
                    keyboard: true
                });
            }
            
            // Handle X button (btn-close) - ensure it closes the modal
            const closeButtons = modalElement.querySelectorAll('.btn-close');
            closeButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }, true); // Use capture phase to ensure it runs first
            });
            
            // Handle Cancel buttons - ensure they close the modal
            const cancelButtons = modalElement.querySelectorAll('.btn-secondary[data-bs-dismiss="modal"]');
            cancelButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }, true); // Use capture phase to ensure it runs first
            });
            
            // Reset form when modal is hidden
            modalElement.addEventListener('hidden.bs.modal', function() {
                const form = modalElement.querySelector('form');
                if (form) {
                    form.reset();
                }
            });
        }
    });
});
</script>
<?php include_once '../../includes/footer.php'; ?>