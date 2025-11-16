<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'redcross') {
header("Location: ../../loginredcross.php?role=redcross");
    exit;
}

// Set page title
$pageTitle = "Announcements";

// Include database connection
require_once '../../config/db.php';
echo '<script src="../../assets/js/universal-print.js"></script>';

// PHP formatting functions
function formatToTitleCase($text) {
    // Convert to lowercase first, then to title case
    $text = strtolower($text);
    // Remove multiple consecutive spaces and replace with single space (preserves single spaces)
    $text = preg_replace('/\s+/', ' ', trim($text));
    // Convert to title case (each word capitalized)
    $text = ucwords($text);
    return $text;
}

function formatToSingleSpaces($text) {
    // Remove multiple consecutive spaces and replace with single space (preserves single spaces)
    $text = preg_replace('/\s+/', ' ', $text);
    // Trim leading and trailing spaces only
    $text = trim($text);
    return $text;
}

// Get Red Cross information
$redcrossId = $_SESSION['user_id'];

// Process form submission
$message = '';
$alertType = '';

// PRG flash: read once on GET and clear
if (isset($_GET['ok']) && isset($_SESSION['flash_msg']) && isset($_SESSION['flash_type'])) {
    $message = $_SESSION['flash_msg'];
    $alertType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['success'])) {
    if (isset($_POST['create_announcement'])) {
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $expiry_date = sanitize($_POST['expiry_date'] ?? '');

        // Format title to title case and single spaces
        $title = formatToTitleCase($title);
        $content = formatToSingleSpaces($content);

        // Validate inputs
        if (empty($title) || empty($content)) {
            $message = 'Title and content are required.';
            $alertType = 'danger';
        } else {
            // Create announcement for this organization type per current schema
            try {
                $conn = getConnection();
                // First try inserting with expiry_date if the column exists
                $sql1 = "INSERT INTO announcements (title, content, organization_type, status, expiry_date, created_at, updated_at)
                         VALUES (:title, :content, 'redcross', 'Active', :expiry_date, NOW(), NOW())";
                $stmt = $conn->prepare($sql1);
                $ok = $stmt->execute([
                    ':title' => $title,
                    ':content' => $content,
                    ':expiry_date' => !empty($expiry_date) ? $expiry_date : null,
                ]);
                if ($ok) {
                    $announcementId = $conn->lastInsertId();
                    
                    // Send SMS notifications to patients and donors
                    try {
                        require_once '../../includes/sim800c_sms.php';
                        require_once '../../includes/notification_templates.php';
                        
                        $institutionName = get_institution_name('redcross');
                        $smsSentCount = 0;
                        $smsErrorCount = 0;
                        
                        // Get all patients with phone numbers
                        $patients = executeQuery("SELECT id, name, phone FROM patient_users WHERE phone IS NOT NULL AND phone != ''", []);
                        
                        if (!empty($patients) && is_array($patients)) {
                            foreach ($patients as $patient) {
                                $patientPhone = $patient['phone'] ?? '';
                                $patientName = $patient['name'] ?? 'Patient';
                                $patientId = $patient['id'] ?? null;
                                
                                if (!empty($patientPhone)) {
                                    // Try to decrypt phone number if encrypted
                                    if (function_exists('decrypt_value')) {
                                        $decryptedPhone = decrypt_value($patientPhone);
                                        if (!empty($decryptedPhone)) {
                                            $patientPhone = $decryptedPhone;
                                        }
                                    }
                                    
                                    if (!empty($patientPhone) && trim($patientPhone) !== '') {
                                        // Build professional SMS message
                                        $smsMessage = "Hello {$patientName}, this is from {$institutionName}. ";
                                        $smsMessage .= "A new announcement has been posted: {$title}. ";
                                        $smsMessage .= "Please check your dashboard for details. Thank you!";
                                        $smsMessage = format_notification_message($smsMessage);
                                        
                                        try {
                                            $smsResult = send_sms_sim800c($patientPhone, $smsMessage);
                                            if ($smsResult['success']) {
                                                $smsSentCount++;
                                            } else {
                                                $smsErrorCount++;
                                            }
                                        } catch (Exception $smsEx) {
                                            $smsErrorCount++;
                                            error_log('[REDCROSS_SMS_ERR] Exception sending announcement SMS to patient ID ' . $patientId . ': ' . $smsEx->getMessage());
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Get all donors with phone numbers
                        $donors = executeQuery("SELECT id, name, phone FROM donor_users WHERE phone IS NOT NULL AND phone != ''", []);
                        
                        if (!empty($donors) && is_array($donors)) {
                            foreach ($donors as $donor) {
                                $donorPhone = $donor['phone'] ?? '';
                                $donorName = $donor['name'] ?? 'Donor';
                                $donorId = $donor['id'] ?? null;
                                
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
                                        $smsMessage = "Hello {$donorName}, this is from {$institutionName}. ";
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
                                            error_log('[REDCROSS_SMS_ERR] Exception sending announcement SMS to donor ID ' . $donorId . ': ' . $smsEx->getMessage());
                                        }
                                    }
                                }
                            }
                        }
                        
                        error_log('[REDCROSS_SMS] Announcement SMS summary - Sent: ' . $smsSentCount . ', Failed: ' . $smsErrorCount);
                    } catch (Exception $smsEx) {
                        error_log('[REDCROSS_SMS_ERR] Exception in announcement SMS: ' . $smsEx->getMessage());
                        // Don't block announcement creation if SMS fails
                    }
                    
                    // PRG: redirect with flash to avoid duplicate on refresh
                    $_SESSION['flash_msg'] = 'Announcement created successfully.';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: announcements.php?ok=1');
                    exit;
                } else {
                    // If failed, try without expiry_date (column may not exist)
                    $err = $stmt->errorInfo();
                    error_log('Announcement insert (with expiry) failed: ' . json_encode($err));
                    $sql2 = "INSERT INTO announcements (title, content, organization_type, status, created_at, updated_at)
                             VALUES (:title, :content, 'redcross', 'Active', NOW(), NOW())";
                    $stmt2 = $conn->prepare($sql2);
                    $ok2 = $stmt2->execute([
                        ':title' => $title,
                        ':content' => $content,
                    ]);
                    if ($ok2) {
                        $announcementId = $conn->lastInsertId();
                        
                        // Send SMS notifications to patients and donors (same as above)
                        try {
                            require_once '../../includes/sim800c_sms.php';
                            require_once '../../includes/notification_templates.php';
                            
                            $institutionName = get_institution_name('redcross');
                            $smsSentCount = 0;
                            $smsErrorCount = 0;
                            
                            // Get all patients and donors with phone numbers
                            $patients = executeQuery("SELECT id, name, phone FROM patient_users WHERE phone IS NOT NULL AND phone != ''", []);
                            $donors = executeQuery("SELECT id, name, phone FROM donor_users WHERE phone IS NOT NULL AND phone != ''", []);
                            
                            $recipients = array_merge($patients ?: [], $donors ?: []);
                            
                            if (!empty($recipients) && is_array($recipients)) {
                                foreach ($recipients as $recipient) {
                                    $recipientPhone = $recipient['phone'] ?? '';
                                    $recipientName = $recipient['name'] ?? 'User';
                                    
                                    if (!empty($recipientPhone)) {
                                        // Try to decrypt phone number if encrypted
                                        if (function_exists('decrypt_value')) {
                                            $decryptedPhone = decrypt_value($recipientPhone);
                                            if (!empty($decryptedPhone)) {
                                                $recipientPhone = $decryptedPhone;
                                            }
                                        }
                                        
                                        if (!empty($recipientPhone) && trim($recipientPhone) !== '') {
                                            // Build professional SMS message
                                            $smsMessage = "Hello {$recipientName}, this is from {$institutionName}. ";
                                            $smsMessage .= "A new announcement has been posted: {$title}. ";
                                            $smsMessage .= "Please check your dashboard for details. Thank you!";
                                            $smsMessage = format_notification_message($smsMessage);
                                            
                                            try {
                                                $smsResult = send_sms_sim800c($recipientPhone, $smsMessage);
                                                if ($smsResult['success']) {
                                                    $smsSentCount++;
                                                } else {
                                                    $smsErrorCount++;
                                                }
                                            } catch (Exception $smsEx) {
                                                $smsErrorCount++;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            error_log('[REDCROSS_SMS] Announcement SMS summary - Sent: ' . $smsSentCount . ', Failed: ' . $smsErrorCount);
                        } catch (Exception $smsEx) {
                            error_log('[REDCROSS_SMS_ERR] Exception in announcement SMS: ' . $smsEx->getMessage());
                        }
                        
                        $_SESSION['flash_msg'] = 'Announcement created successfully.';
                        $_SESSION['flash_type'] = 'success';
                        header('Location: announcements.php?ok=1');
                        exit;
                    } else {
                        $err2 = $stmt2->errorInfo();
                        error_log('Announcement insert (fallback) failed: ' . json_encode($err2));
                        $_SESSION['flash_msg'] = 'Failed to create announcement.' . (!empty($err2[2]) ? ' Error: ' . $err2[2] : '');
                        $_SESSION['flash_type'] = 'danger';
                        header('Location: announcements.php?ok=1');
                        exit;
                    }
                }
            } catch (Exception $e) {
                error_log('Exception creating announcement: ' . $e->getMessage());
                $_SESSION['flash_msg'] = 'Failed to create announcement. Error: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
                header('Location: announcements.php?ok=1');
                exit;
            }
        }
    } elseif (isset($_POST['update_announcement'])) {
        $id = sanitize($_POST['announcement_id']);
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $status = sanitize($_POST['status']);

        // Format title to title case and single spaces
        $title = formatToTitleCase($title);
        $content = formatToSingleSpaces($content);

        if (empty($title) || empty($content)) {
            $message = 'Title and content are required.';
            $alertType = 'danger';
        } else {
            try {
                $conn = getConnection();
                // Try update with expiry_date if present and the column exists
                $sql1 = "UPDATE announcements
                         SET title = :title, content = :content, status = :status, expiry_date = :expiry_date, updated_at = NOW()
                         WHERE id = :id AND organization_type = 'redcross'";
                $stmt = $conn->prepare($sql1);
                $stmt->execute([
                    ':title' => $title,
                    ':content' => $content,
                    ':status' => $status,
                    ':expiry_date' => !empty($expiry_date) ? $expiry_date : null,
                    ':id' => $id,
                ]);
                if ($stmt->rowCount() >= 1) {
                    $_SESSION['flash_msg'] = 'Announcement updated successfully.';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: announcements.php?ok=1');
                    exit;
                } else {
                    // If zero rows affected, try fallback without expiry_date (column may not exist)
                    $sql2 = "UPDATE announcements
                             SET title = :title, content = :content, status = :status, updated_at = NOW()
                             WHERE id = :id AND organization_type = 'redcross'";
                    $stmt2 = $conn->prepare($sql2);
                    $stmt2->execute([
                        ':title' => $title,
                        ':content' => $content,
                        ':status' => $status,
                        ':id' => $id,
                    ]);
                    if ($stmt2->rowCount() >= 1) {
                        $_SESSION['flash_msg'] = 'Announcement updated successfully.';
                        $_SESSION['flash_type'] = 'success';
                        header('Location: announcements.php?ok=1');
                        exit;
                    } else {
                        // No rows changed (same data) or ID not found
                        $message = 'No changes applied (already up to date or not found).';
                        $alertType = 'info';
                    }
                }
            } catch (Exception $e) {
                error_log('Announcement update error: ' . $e->getMessage());
                $message = 'Failed to update announcement. Error: ' . $e->getMessage();
                $alertType = 'danger';
            }
        }
    } elseif (isset($_POST['delete_announcement'])) {
        $id = sanitize($_POST['announcement_id']);
        try {
            $conn = getConnection();
            $sql = "DELETE FROM announcements WHERE id = :id AND organization_type = 'redcross'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() >= 1) {
                $_SESSION['flash_msg'] = 'Announcement deleted successfully.';
                $_SESSION['flash_type'] = 'success';
                header('Location: announcements.php?ok=1');
                exit;
            } else {
                // Already deleted or not found should not be treated as an error for user feedback
                $message = 'Announcement was already removed or not found.';
                $alertType = 'info';
            }
        } catch (Exception $e) {
            error_log('Announcement delete error: ' . $e->getMessage());
            $message = 'Failed to delete announcement. Error: ' . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Get all announcements for this organization type
$announcements = executeQuery("
    SELECT * FROM announcements
    WHERE organization_type = 'redcross'
    ORDER BY created_at DESC
", []);

if (!is_array($announcements)) {
    $announcements = [];
}
?>

<?php include_once 'header.php'; ?>

<div class="dashboard-content">
    <!-- Display messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $alertType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const alertEl = document.querySelector('.alert.alert-dismissible');
        if (alertEl) {
            setTimeout(() => {
                try {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                    bsAlert.close();
                } catch (e) {
                    // Fallback if Bootstrap object not present
                    alertEl.classList.remove('show');
                    alertEl.remove();
                }
            }, 4000); // auto-dismiss after 4 seconds
        }
    });
    </script>

    <!-- Hero Section -->
    <div class="announcements-header-section">
        <div class="announcements-hero">
            <h1><i class="bi bi-megaphone me-3"></i>Announcement Management</h1>
            <p>Create, manage, and broadcast important announcements to the community. Keep stakeholders informed about blood drives, urgent needs, and organizational updates.</p>
            <div class="hero-actions">
                <button class="hero-btn" onclick="openCreateAnnouncementModal()">
                    <i class="bi bi-plus-circle me-2"></i>Create Announcement
                </button>
                <button class="hero-btn" onclick="printReport()">
                    <i class="bi bi-printer me-2"></i>Print Report
                </button>
            </div>
        </div>
    </div>

    <!-- Enhanced Statistics -->
    <div class="stats-grid">
        <div class="stat-card clickable-stat-card" data-filter="all" style="cursor: pointer;">
            <div class="stat-icon-wrapper primary">
                <i class="bi bi-megaphone"></i>
            </div>
            <div class="stat-number"><?php echo count($announcements); ?></div>
            <div class="stat-label">Total Announcements</div>
        </div>
        <div class="stat-card clickable-stat-card" data-filter="active" style="cursor: pointer;">
            <div class="stat-icon-wrapper success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-number"><?php echo count(array_filter($announcements, function($a) { return strtolower($a['status']) === 'active'; })); ?></div>
            <div class="stat-label">Active Announcements</div>
        </div>
        <div class="stat-card clickable-stat-card" data-filter="expired" style="cursor: pointer;">
            <div class="stat-icon-wrapper danger">
                <i class="bi bi-calendar-x"></i>
            </div>
            <div class="stat-number">
                <?php 
                $expiredCount = 0;
                foreach ($announcements as $a) {
                    if (isset($a['expiry_date']) && $a['expiry_date'] && strtotime($a['expiry_date']) < time()) {
                        $expiredCount++;
                    }
                }
                echo $expiredCount;
                ?>
            </div>
            <div class="stat-label">Expired</div>
        </div>
    </div>

    <!-- Enhanced Announcements Table -->
    <div class="announcements-table-card">
        <div class="table-header">
            <h3><i class="bi bi-megaphone me-2"></i>Announcement Records</h3>
            <div class="table-actions">
                <button class="table-btn" onclick="printReport()">
                    <i class="bi bi-printer me-2"></i>Print Report
                </button>
                <button class="table-btn" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="bi bi-plus-circle me-2"></i>Create Announcement
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="enhanced-table table">
                <thead>
                    <tr>
                        <th>Announcement Details</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="announcementsTableBody">
                    <?php if (count($announcements) > 0): ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <?php
                            // Determine row filter attributes
                            $rowStatus = strtolower($announcement['status'] ?? 'active');
                            $isExpired = false;
                            if (isset($announcement['expiry_date']) && $announcement['expiry_date'] && strtotime($announcement['expiry_date']) < time()) {
                                $isExpired = true;
                            }
                            $rowFilter = $isExpired ? 'expired' : $rowStatus;
                            ?>
                            <tr class="announcement-row" data-status="<?php echo htmlspecialchars($rowStatus); ?>" data-filter="<?php echo htmlspecialchars($rowFilter); ?>">
                                <td>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($announcement['content'], 0, 80)) . '...'; ?></small>
                                    </div>
                                </td>
                                
                                
                                <td>
                                    <div>
                                        <div><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($announcement['created_at'])); ?></small>
                                    </div>
                                </td>
                               
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="action-btn btn-edit" 
                                                data-bs-toggle="modal"
                                                data-bs-target="#editAnnouncementModal"
                                                data-announcement='<?php echo json_encode($announcement); ?>'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="action-btn btn-delete"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteAnnouncementModal"
                                                data-announcement-id="<?php echo $announcement['id']; ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-megaphone text-muted" style="font-size: 3rem;"></i>
                                <p class="mb-0 mt-3 text-muted">No announcements found yet.</p>
                                <small class="text-muted">Create your first announcement to start communicating with the community.</small>
                                <div class="mt-3">
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                        <i class="bi bi-plus-circle me-1"></i>Create Your First Announcement
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="create_title">Title</label>
                        <input type="text" class="form-control" id="create_title" name="title" data-titlecase="1" required>
                    
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="create_content">Content</label>
                        <textarea class="form-control" id="create_content" name="content" rows="5" required></textarea>
                       
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="create_expiry_date">Expiry Date (Optional)</label>
                            <input type="date" class="form-control" id="create_expiry_date" name="expiry_date">
                        <small class="form-text text-muted">Leave empty if the announcement should remain active indefinitely.</small>
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
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="announcement_id" id="edit_announcement_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_title">Title</label>
                        <input type="text" class="form-control" name="title" id="edit_title" data-titlecase="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_content">Content</label>
                        <textarea class="form-control" name="content" id="edit_content" rows="5" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="edit_status">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="edit_expiry_date">Expiry Date (Optional)</label>
                            <input type="date" class="form-control" name="expiry_date" id="edit_expiry_date">
                            <small class="form-text text-muted">Leave empty if the announcement should remain active indefinitely.</small>
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
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
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

<!-- Bootstrap JS (required for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Modal handling for edit and delete
document.addEventListener('DOMContentLoaded', function() {
    // Edit modal
    document.querySelectorAll('[data-bs-target="#editAnnouncementModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const announcement = JSON.parse(this.getAttribute('data-announcement'));
            document.getElementById('edit_announcement_id').value = announcement.id;
            document.getElementById('edit_title').value = announcement.title;
            document.getElementById('edit_content').value = announcement.content;
            document.getElementById('edit_status').value = announcement.status || 'active';
            document.getElementById('edit_expiry_date').value = announcement.expiry_date || '';
            
            // Apply restrictions to edit fields
            setTimeout(() => {
                addEditRestrictions();
            }, 100);
        });
    });

    // Delete modal
    document.querySelectorAll('[data-bs-target="#deleteAnnouncementModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const announcementId = this.getAttribute('data-announcement-id');
            document.getElementById('delete_announcement_id').value = announcementId;
        });
    });

    // Sidebar toggle for mobile
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
    
    // Filter table by status when clicking stat cards
    const statCards = document.querySelectorAll('.clickable-stat-card');
    const tableBody = document.getElementById('announcementsTableBody');
    
    if (statCards && tableBody) {
        statCards.forEach(function(card) {
            card.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                
                // Remove active class from all cards
                statCards.forEach(function(c) {
                    c.classList.remove('active');
                });
                
                // Add active class to clicked card
                this.classList.add('active');
                
                // Filter table rows
                const rows = tableBody.querySelectorAll('.announcement-row');
                let visibleCount = 0;
                
                rows.forEach(function(row) {
                    const rowFilter = row.getAttribute('data-filter');
                    const filterLower = filter.toLowerCase();
                    
                    if (filterLower === 'all' || rowFilter === filterLower) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Show message if no rows visible
                let noResultsRow = tableBody.querySelector('.no-results-row');
                if (visibleCount === 0) {
                    if (!noResultsRow) {
                        noResultsRow = document.createElement('tr');
                        noResultsRow.className = 'no-results-row';
                        const filterLabel = filter === 'all' ? 'all' : (filter === 'active' ? 'active' : 'expired');
                        noResultsRow.innerHTML = '<td colspan="3" class="text-center py-5"><i class="bi bi-megaphone text-muted" style="font-size: 3rem;"></i><p class="mb-0 mt-3 text-muted">No ' + filterLabel + ' announcements found.</p></td>';
                        tableBody.appendChild(noResultsRow);
                    }
                } else {
                    if (noResultsRow) {
                        noResultsRow.remove();
                    }
                }
                
                // Scroll to table
                tableBody.closest('.announcements-table-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }
    
    // Handle create announcement form submission to ensure page refresh after successful post
    const createForm = document.querySelector('#createAnnouncementModal form');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            // Allow form to submit normally via POST
            // PHP will handle the redirect which will refresh the page
            // Close modal immediately to show submission is in progress
            const modal = bootstrap.Modal.getInstance(document.getElementById('createAnnouncementModal'));
            if (modal) {
                modal.hide();
            }
            
            // Show loading indicator
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            }
            
            // The page will refresh after PHP processes and redirects
            // PHP redirect will automatically refresh the page
            // This ensures normal form submission behavior
        });
    }
    
    // Handle edit announcement form submission
    const editForm = document.querySelector('#editAnnouncementModal form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            // Close modal immediately to show submission is in progress
            const modal = bootstrap.Modal.getInstance(document.getElementById('editAnnouncementModal'));
            if (modal) {
                modal.hide();
            }
            
            // Show loading indicator
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
            }
        });
    }
    
    // Handle delete announcement form submission
    const deleteForm = document.querySelector('#deleteAnnouncementModal form');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            // Close modal immediately to show submission is in progress
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteAnnouncementModal'));
            if (modal) {
                modal.hide();
            }
            
            // Show loading indicator
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';
            }
        });
    }
    
});

// Input formatting and restrictions
function addInputRestrictions() {
    // Apply to title field
    const titleField = document.getElementById('create_title');
    if (titleField) {
        titleField.addEventListener('keydown', function(e) {
            // Allow backspace, delete, arrow keys, etc.
            if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'Home' || e.key === 'End') {
                return true;
            }
            
            // Block multiple consecutive spaces
            if (e.key === ' ') {
                const cursorPos = this.selectionStart;
                const beforeCursor = this.value.substring(0, cursorPos);
                
                // Check if there's already a space before cursor
                if (beforeCursor.endsWith(' ')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        titleField.addEventListener('input', function() {
            let value = this.value;
            const originalValue = value;
            
            // Remove multiple spaces and convert to title case
            value = value.replace(/\s+/g, ' ');
            value = value.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
            
            if (value !== originalValue) {
                this.value = value;
            }
        });
    }
    
    // Apply to content field
    const contentField = document.getElementById('create_content');
    if (contentField) {
        contentField.addEventListener('keydown', function(e) {
            // Allow backspace, delete, arrow keys, etc.
            if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'Home' || e.key === 'End') {
                return true;
            }
            
            // Block multiple consecutive spaces
            if (e.key === ' ') {
                const cursorPos = this.selectionStart;
                const beforeCursor = this.value.substring(0, cursorPos);
                
                // Check if there's already a space before cursor
                if (beforeCursor.endsWith(' ')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        contentField.addEventListener('input', function() {
            let value = this.value;
            const originalValue = value;
            
            // Remove multiple spaces
            value = value.replace(/\s+/g, ' ');
            
            if (value !== originalValue) {
                this.value = value;
            }
        });
    }
}

// Input restrictions for edit modal
function addEditRestrictions() {
    // Apply to edit title field
    const editTitleField = document.getElementById('edit_title');
    if (editTitleField) {
        editTitleField.addEventListener('keydown', function(e) {
            // Allow backspace, delete, arrow keys, etc.
            if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'Home' || e.key === 'End') {
                return true;
            }
            
            // Block multiple consecutive spaces
            if (e.key === ' ') {
                const cursorPos = this.selectionStart;
                const beforeCursor = this.value.substring(0, cursorPos);
                
                // Check if there's already a space before cursor
                if (beforeCursor.endsWith(' ')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        editTitleField.addEventListener('input', function() {
            let value = this.value;
            const originalValue = value;
            
            // Remove multiple spaces and convert to title case
            value = value.replace(/\s+/g, ' ');
            value = value.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
            
            if (value !== originalValue) {
                this.value = value;
            }
        });
    }
    
    // Apply to edit content field
    const editContentField = document.getElementById('edit_content');
    if (editContentField) {
        editContentField.addEventListener('keydown', function(e) {
            // Allow backspace, delete, arrow keys, etc.
            if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'Home' || e.key === 'End') {
                return true;
            }
            
            // Block multiple consecutive spaces
            if (e.key === ' ') {
                const cursorPos = this.selectionStart;
                const beforeCursor = this.value.substring(0, cursorPos);
                
                // Check if there's already a space before cursor
                if (beforeCursor.endsWith(' ')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        editContentField.addEventListener('input', function() {
            let value = this.value;
            const originalValue = value;
            
            // Remove multiple spaces
            value = value.replace(/\s+/g, ' ');
            
            if (value !== originalValue) {
                this.value = value;
            }
        });
    }
}

// Global function for opening create announcement modal
function openCreateAnnouncementModal() {
    console.log('Opening create announcement modal...');
    const modal = document.getElementById('createAnnouncementModal');
    if (modal) {
        try {
            // Try Bootstrap 5 method first
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
                console.log('Modal opened using Bootstrap 5');
            } else {
                throw new Error('Bootstrap not available');
            }
        } catch (error) {
            console.error('Error opening modal with Bootstrap:', error);
            // Fallback: show modal manually
            console.log('Using manual modal display...');
            modal.style.display = 'block';
            modal.classList.add('show');
            document.body.classList.add('modal-open');
            
            // Add backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'manual-backdrop';
            document.body.appendChild(backdrop);
            
            // Add close functionality
            const closeModal = () => {
                modal.style.display = 'none';
                modal.classList.remove('show');
                document.body.classList.remove('modal-open');
                const backdropEl = document.getElementById('manual-backdrop');
                if (backdropEl) backdropEl.remove();
            };
            
            // Close on backdrop click
            backdrop.addEventListener('click', closeModal);
            
            // Close on X button click
            const closeBtn = modal.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }
        }
        
        // Apply input restrictions when modal opens
        setTimeout(() => {
            addInputRestrictions();
        }, 100);
    } else {
        console.error('Create announcement modal not found!');
        alert('Error: Create announcement modal not found. Please refresh the page and try again.');
    }
}

</script>

<style>
    /* Hero Section Styling */
    .announcements-header-section {
        margin-bottom: 2rem;
    }
    
    .announcements-hero {
        background: linear-gradient(135deg, #DC143C 0%, #B22222 50%, #8B0000 100%);
        color: white;
        padding: 3rem 2rem;
        border-radius: 20px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(220, 20, 60, 0.3);
        position: relative;
        overflow: hidden;
    }
    
    .announcements-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        opacity: 0.1;
    }
    
    .announcements-hero h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        position: relative;
        z-index: 1;
    }
    
    .announcements-hero p {
        font-size: 1.1rem;
        margin-bottom: 2rem;
        opacity: 0.9;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        position: relative;
        z-index: 1;
    }
    
    .hero-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    
    .hero-btn {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.3);
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }
    
    .hero-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        color: white;
    }
    
    /* Enhanced Statistics Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .stat-card {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        text-align: center;
        transition: all 0.3s ease;
        border: 1px solid rgba(220, 20, 60, 0.1);
        position: relative;
        overflow: hidden;
    }
    
    .clickable-stat-card {
        cursor: pointer;
        user-select: none;
    }
    
    .clickable-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(220, 20, 60, 0.2);
        border-color: rgba(220, 20, 60, 0.3);
    }
    
    .clickable-stat-card.active {
        border: 2px solid #DC143C;
        box-shadow: 0 8px 30px rgba(220, 20, 60, 0.3);
        transform: translateY(-3px);
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #DC143C, #FF6B6B);
    }
    
    
    .stat-icon-wrapper {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.8rem;
        color: white;
        position: relative;
    }
    
    .stat-icon-wrapper.primary {
        background: linear-gradient(135deg, #DC143C, #FF6B6B);
    }
    
    .stat-icon-wrapper.success {
        background: linear-gradient(135deg, #28a745, #20c997);
    }
    
    .stat-icon-wrapper.warning {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
    }
    
    .stat-icon-wrapper.danger {
        background: linear-gradient(135deg, #dc3545, #e74c3c);
    }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        color: #6c757d;
        font-size: 0.95rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Enhanced Table Styling */
    .announcements-table-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        border: 1px solid rgba(220, 20, 60, 0.1);
    }
    
    .table-header {
        background: linear-gradient(135deg, #DC143C, #B22222);
        color: white;
        padding: 1.5rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .table-header h3 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 600;
    }
    
    .table-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .table-btn {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .table-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        color: white;
        transform: translateY(-1px);
    }
    
    .enhanced-table {
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .enhanced-table thead th {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        color: #2c3e50;
        font-weight: 600;
        padding: 1rem;
        border: none;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .enhanced-table tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid #f1f3f4;
    }
    
    .enhanced-table tbody tr:hover {
        background: linear-gradient(135deg, #fff5f5, #fef7f7);
        transform: scale(1.01);
        box-shadow: 0 4px 15px rgba(220, 20, 60, 0.1);
    }
    
    .enhanced-table tbody td {
        padding: 1rem;
        vertical-align: middle;
        border: none;
    }
    
    /* Badge Styling */
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-active {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }
    
    .status-inactive {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: white;
    }
    
    /* Action Buttons */
    .action-btn {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .btn-edit {
        background: linear-gradient(135deg, #17a2b8, #138496);
        color: white;
    }
    
    .btn-edit:hover {
        background: linear-gradient(135deg, #138496, #117a8b);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
    }
    
    .btn-delete {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }
    
    .btn-delete:hover {
        background: linear-gradient(135deg, #c82333, #a71e2a);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    }
    
    /* Alert Styling */
    .alert {
        border-radius: 12px;
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .alert-success {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
        border-left: 4px solid #28a745;
    }
    
    .alert-danger {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
        border-left: 4px solid #dc3545;
    }
    
    /* Modal Enhancements */
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
        overflow: hidden;
    }
    
    .modal-header {
        background: linear-gradient(135deg, #DC143C, #B22222);
        color: white;
        border-bottom: none;
        padding: 1.5rem 2rem;
    }
    
    .modal-title {
        font-weight: 600;
        margin: 0;
    }
    
    .btn-close {
        filter: invert(1);
        opacity: 0.8;
    }
    
    .modal-body {
        padding: 2rem;
    }
    
    /* Input formatting visual feedback */
    .form-control.formatting {
        background-color: #e3f2fd !important;
        border-color: #2196f3 !important;
        transition: all 0.3s ease;
    }
    
    .form-control, .form-select {
        border-radius: 10px;
        border: 2px solid #e9ecef;
        padding: 12px 15px;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #DC143C;
        box-shadow: 0 0 0 0.2rem rgba(220, 20, 60, 0.25);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #DC143C, #B22222);
        border: none;
        border-radius: 10px;
        padding: 12px 24px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #B22222, #8B0000);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(220, 20, 60, 0.3);
    }
    
    .btn-secondary {
        background: #6c757d;
        border: none;
        border-radius: 10px;
        padding: 12px 24px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .announcements-hero {
            padding: 2rem 1rem;
        }
        
        .announcements-hero h1 {
            font-size: 2rem;
        }
        
        .hero-actions {
            flex-direction: column;
            align-items: center;
        }
        
        .hero-btn {
            width: 100%;
            max-width: 250px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .table-header {
            flex-direction: column;
            text-align: center;
        }
        
        .enhanced-table {
            font-size: 0.9rem;
        }
        
        .enhanced-table thead th,
        .enhanced-table tbody td {
            padding: 0.75rem 0.5rem;
        }
    }
    
    @media (max-width: 576px) {
        .announcements-hero h1 {
            font-size: 1.75rem;
        }
        
        .stat-card {
            padding: 1.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
            font-size: 0.8rem;
        }
    }
</style>
<script src="../../assets/js/titlecase-formatter.js"></script>



