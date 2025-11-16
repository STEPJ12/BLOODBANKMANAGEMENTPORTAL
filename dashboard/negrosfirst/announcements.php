<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

// Set page title
$pageTitle = "Manage Announcements - Blood Bank Portal";
$isDashboard = true; // Enable notification dropdown

// Get Negros First information
$negrosFirstId = $_SESSION['user_id'];

// Handle session messages for feedback
$message = '';
$alertType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $alertType = $_SESSION['message_type'] ?? 'success';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Process form submission

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_announcement'])) {
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);

        // Validate inputs
        if (empty($title) || empty($content)) {
            $message = 'Title and content are required.';
            $alertType = 'danger';
        } else {
            try {
                $result = insertRow(
                    "INSERT INTO announcements (title, content, organization_type, status, created_at, updated_at) VALUES (?, ?, 'negrosfirst', 'Active', NOW(), NOW())",
                    [$title, $content]
                );

                // insertRow returns lastInsertId() on success (could be 0) or false on error
                if ($result !== false) {
                    // Try to send notifications, but don't fail the creation if notifications fail
                    try {
                        // Notify all donors and patients
                        $users = executeQuery("
                            SELECT id, 'donor' as role FROM donor_users
                            UNION ALL
                            SELECT id, 'patient' as role FROM patient_users
                        ");

                        // Include notification templates
                        require_once '../../includes/notification_templates.php';
                        
                        if (is_array($users) && count($users) > 0) {
                            foreach ($users as $user) {
                                try {
                                    // Get user name for personalized message
                                    $table = $user['role'] . '_users';
                                    $userInfo = getRow("SELECT name FROM {$table} WHERE id = ?", [$user['id']]);
                                    $userName = $userInfo['name'] ?? '';
                                    
                                    $notificationData = [
                                        'message' => "Negros First has posted a new announcement: " . $title
                                    ];
                                    
                                    // Send notification with SMS using template
                                    send_notification_with_sms(
                                        $user['id'],
                                        $user['role'],
                                        'announcement',
                                        'New Negros First Announcement',
                                        $notificationData,
                                        'negrosfirst'
                                    );
                                } catch (Exception $notifError) {
                                    // Log but continue with other users
                                    if (function_exists('secure_log')) {
                                        secure_log("Notification send error for user {$user['id']}", ['error' => substr($notifError->getMessage(), 0, 200)]);
                                    }
                                }
                            }
                        }
                    } catch (Exception $notifException) {
                        // Log notification error but don't fail the creation
                        if (function_exists('secure_log')) {
                            secure_log("Announcement notification error", ['error' => substr($notifException->getMessage(), 0, 200)]);
                        }
                    }

                    $_SESSION['message'] = 'Announcement created successfully.';
                    $_SESSION['message_type'] = 'success';
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                } else {
                    $message = 'Failed to create announcement. Please try again.';
                    $alertType = 'danger';
                }
            } catch (Exception $e) {
                $message = 'An error occurred while creating the announcement: ' . $e->getMessage();
                $alertType = 'danger';
                if (function_exists('secure_log')) {
                    secure_log("Announcement creation error", ['error' => substr($e->getMessage(), 0, 200)]);
                }
            }
        }
    } elseif (isset($_POST['update_announcement'])) {
        $id = sanitize($_POST['announcement_id']);
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $status = sanitize($_POST['status']);

        if (empty($title) || empty($content)) {
            $message = 'Title and content are required.';
            $alertType = 'danger';
        } else {
            // Normalize status to match database enum format ('Active' or 'Inactive')
            $status = ucfirst(strtolower($status));
            if ($status !== 'Active' && $status !== 'Inactive') {
                $status = 'Active'; // Default to Active if invalid
            }
            
            // Validate ID
            if (empty($id) || !is_numeric($id)) {
                $message = 'Invalid announcement ID.';
                $alertType = 'danger';
            } else {
                try {
                    // Verify announcement exists and belongs to this organization
                    $existing = getRow("SELECT id, organization_type FROM announcements WHERE id = ?", [$id]);
                    
                    if (!$existing) {
                        $message = 'Announcement not found.';
                        $alertType = 'danger';
                    } elseif ($existing['organization_type'] !== 'negrosfirst') {
                        $message = 'You do not have permission to update this announcement.';
                        $alertType = 'danger';
                    } else {
                        // Use direct connection to get better error handling
                        // Note: announcements table doesn't have 'link' column, so we don't update it
                        $conn = getConnection();
                        $stmt = $conn->prepare("
                            UPDATE announcements
                            SET title = ?, content = ?, status = ?, updated_at = NOW()
                            WHERE id = ? AND organization_type = 'negrosfirst'
                        ");
                        $stmt->execute([$title, $content, $status, $id]);
                        $affectedRows = $stmt->rowCount();
                        
                        if ($affectedRows > 0) {
                            $_SESSION['message'] = 'Announcement updated successfully.';
                            $_SESSION['message_type'] = 'success';
                            header("Location: " . $_SERVER['REQUEST_URI']);
                            exit;
                        } else {
                            $message = 'No changes were made or update failed.';
                            $alertType = 'danger';
                        }
                    }
                } catch (Exception $e) {
                    error_log("Announcement update error: " . $e->getMessage());
                    $message = 'Failed to update announcement: ' . htmlspecialchars($e->getMessage());
                    $alertType = 'danger';
                }
            }
        }
    } elseif (isset($_POST['delete_announcement'])) {
        $id = sanitize($_POST['announcement_id']);
        
        // Validate ID
        if (empty($id) || !is_numeric($id)) {
            $message = 'Invalid announcement ID.';
            $alertType = 'danger';
        } else {
            try {
                $conn = getConnection();
                $stmt = $conn->prepare("
                    DELETE FROM announcements
                    WHERE id = ? AND organization_type = 'negrosfirst'
                ");
                $stmt->execute([$id]);
                $affectedRows = $stmt->rowCount();
                
                if ($affectedRows > 0) {
                    $_SESSION['message'] = 'Successfully deleted the announcement.';
                    $_SESSION['message_type'] = 'success';
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                } else {
                    $message = 'Announcement not found or already deleted.';
                    $alertType = 'danger';
                }
            } catch (Exception $e) {
                $message = 'Failed to delete announcement. Please try again.';
                $alertType = 'danger';
                if (function_exists('secure_log')) {
                    secure_log("Announcement deletion error", ['error' => substr($e->getMessage(), 0, 200)]);
                }
            }
        }
    }
}

// Get all announcements for Negros First
$announcements = executeQuery("
    SELECT a.*
    FROM announcements a
    WHERE a.organization_type = 'negrosfirst'
    ORDER BY a.created_at DESC
");

// Initialize as empty array if query fails
if (!is_array($announcements)) {
    $announcements = [];
}

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

    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Custom Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
</head>
<body>

<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h4 mb-0">Manage Announcements</h2>
            </div>
        </div>
        
        <!-- Controls below header -->
        <div class="p-3 pb-0">
            <div class="d-flex gap-2 justify-content-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="bi bi-plus-circle me-2"></i>New Announcement
                </button>
            </div>
        </div>
        
        <!-- Notification Dropdown -->
        <?php
        // Load notification data
        $notifCount = 0; $notifList = [];
        try {
            $notifCount = getCount("SELECT COUNT(*) FROM notifications WHERE user_role = 'negrosfirst' AND is_read = 0");
            $notifList = executeQuery("SELECT id, title, message, created_at, is_read FROM notifications WHERE user_role='negrosfirst' ORDER BY created_at DESC LIMIT 10");
            if (!is_array($notifList)) { $notifList = []; }
        } catch (Exception $e) { /* ignore rendering errors */ }
        ?>
        <style>
            .nf-topbar { position: fixed; top: 10px; right: 16px; z-index: 1100; }
            .nf-dropdown { position: absolute; right: 0; top: 42px; width: 320px; display: none; }
            .nf-dropdown.show { display: block; }
            .nf-notif-item { white-space: normal; }
            @media (max-width: 991.98px) { .nf-topbar { top: 8px; right: 12px; } }
        </style>
        <div class="nf-topbar">
            <button id="nfBellBtn" class="btn btn-light position-relative shadow-sm">
                <i class="bi bi-bell"></i>
                <span id="nfBellBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: <?php echo ((int)$notifCount>0)?'inline':'none'; ?>;">
                    <?php echo (int)$notifCount; ?>
                </span>
            </button>
            <div id="nfDropdown" class="nf-dropdown card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Notifications</span>
                    <a class="small text-decoration-none" href="../../dashboard/negrosfirst/notifications.php">View All</a>
                </div>
                <div id="nfList" class="list-group list-group-flush" style="max-height: 360px; overflow:auto;">
                    <?php if (!empty($notifList)): foreach ($notifList as $n): ?>
                        <div class="list-group-item nf-notif-item">
                            <div class="d-flex justify-content-between">
                                <div class="fw-semibold small"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="text-muted small"><?php echo date('M d, g:i A', strtotime($n['created_at'])); ?></div>
                            </div>
                            <div class="small text-muted"><?php echo htmlspecialchars($n['message']); ?></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="list-group-item text-center text-muted py-3">No notifications</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dashboard-main p-3">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($alertType); ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php 
                        echo match($alertType) {
                            'success' => 'check-circle-fill',
                            'danger' => 'exclamation-triangle-fill',
                            'warning' => 'exclamation-circle-fill',
                            'info' => 'info-circle-fill',
                            default => 'check-circle-fill'
                        };
                    ?> me-2"></i>
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
                                                    echo $announcement['status'] === 'active' ? 'success' : 'secondary';
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
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
                    <div class="mb-3">
                        <label class="form-label" for="edit_status">Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
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

<script>
// Handle edit announcement modal
document.querySelectorAll('[data-bs-target="#editAnnouncementModal"]').forEach(button => {
    button.addEventListener('click', function() {
        const announcement = JSON.parse(this.getAttribute('data-announcement'));
        document.getElementById('edit_announcement_id').value = announcement.id;
        document.getElementById('edit_title').value = announcement.title || '';
        document.getElementById('edit_content').value = announcement.content || '';
        
        // Handle status - capitalize first letter to match form options
        const status = announcement.status || 'Active';
        const capitalizedStatus = status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
        document.getElementById('edit_status').value = capitalizedStatus;
    });
});

// Handle delete announcement modal
document.querySelectorAll('[data-bs-target="#deleteAnnouncementModal"]').forEach(button => {
    button.addEventListener('click', function() {
        const announcementId = this.getAttribute('data-announcement-id');
        document.getElementById('delete_announcement_id').value = announcementId;
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // Title Case for text fields (allow spaces, but only one space between words)
    function toTitleCase(str, trimSpaces) {
        if (!str) return '';
        
        const hasTrailingSpace = str.endsWith(' ') && trimSpaces === false;
        const hasLeadingSpace = str.startsWith(' ');
        
        // Replace multiple spaces (2 or more) with single space
        str = str.replace(/\s{2,}/g, ' ');
        
        if (trimSpaces !== false) {
            str = str.trim();
        }
        
        // Split into words, filter empty strings, and capitalize each word
        const words = str.split(' ').filter(function(word) {
            return word.length > 0;
        }).map(function(word) {
            if (/^\d/.test(word)) { // Allow numbers in words
                return word;
            }
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        });
        
        let result = words.join(' ');
        
        if (hasTrailingSpace && result.length > 0) {
            result += ' ';
        }
        
        if (hasLeadingSpace && trimSpaces === false && result.length > 0) {
            result = ' ' + result;
        }
        
        return result;
    }

    // Apply to all relevant text inputs and textareas (title and content fields)
    const textInputs = document.querySelectorAll('input[name="title"], textarea[name="content"], input[id="edit_title"], textarea[id="edit_content"]');
    textInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            // Allow letters, spaces, numbers, and common punctuation
            let cleaned = this.value
                .replace(/[^a-zA-Z0-9\s.,!?()-]/g, '')  // Allow letters, numbers, spaces, and common punctuation
                .replace(/\s{2,}/g, ' ');                // Replace multiple spaces with one
            
            // Apply Title Case, but preserve trailing space during typing
            this.value = toTitleCase(cleaned, false);
        });
        
        input.addEventListener('blur', function() {
            // Trim spaces on blur
            this.value = toTitleCase(this.value, true);
        });
    });

    // Numbers only for number fields
    const numberInputs = document.querySelectorAll('input[type="number"]');
    numberInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            // Remove any non-digit characters (spaces not allowed)
            this.value = this.value.replace(/[^\d]/g, '');
        });
    });

    // Fix notification dropdown - simple and direct approach
    const bellBtn = document.getElementById('nfBellBtn');
    const dropdown = document.getElementById('nfDropdown');
    
    if (bellBtn && dropdown) {
        console.log('Notification dropdown elements found');
        
        // Bell click handler
        bellBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Bell clicked');
            
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                console.log('Closing dropdown');
            } else {
                dropdown.classList.add('show');
                console.log('Opening dropdown');
            }
        });
        
        // Close on outside click
        document.addEventListener('click', function(e) {
            if (!bellBtn.contains(e.target) && !dropdown.contains(e.target)) {
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    console.log('Closing dropdown - outside click');
                }
            }
        });
        
        // Close on notification item click
        dropdown.addEventListener('click', function(e) {
            if (e.target.closest('.list-group-item') || e.target.closest('a')) {
                dropdown.classList.remove('show');
                console.log('Closing dropdown - notification click');
            }
        });
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                console.log('Closing dropdown - Escape key');
            }
        });
    } else {
        console.log('Notification dropdown elements not found');
    }
});

// Auto-hide feedback messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            if (alert && alert.parentNode) {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            }
        }, 5000);
    });
});
</script>
</body>

