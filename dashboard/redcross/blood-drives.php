<?php
// Start session
session_start();

// Check if user is logged in and is Red Cross
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'redcross') {
    header("Location: ../../loginredcross.php?role=redcross");
    exit;
}

// Set page title
$pageTitle = "Blood Drives";

// Include database connection
require_once '../../config/db.php';
echo '<script src="../../assets/js/universal-print.js"></script>';

// Get Red Cross information
$redcrossId = $_SESSION['user_id'];
$redcross = getRow("SELECT * FROM blood_banks WHERE id = ? AND organization_type = 'redcross'", [$redcrossId]);

// Process form submission
$success = false;
$error = "";

// Success feedback via PRG pattern from session
if (isset($_SESSION['blood_drive_message'])) {
    $success = !empty($_SESSION['blood_drive_message']);
    $error = $_SESSION['blood_drive_message_type'] === 'error' ? $_SESSION['blood_drive_message'] : '';
    unset($_SESSION['blood_drive_message']);
    unset($_SESSION['blood_drive_message_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['success'])) {
    // CSRF protection
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form token. Please refresh the page and try again.';
    } else {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            // Validate input
            // Normalize inputs: Title, Location, Address -> Title Case + single spacing; Requirements -> single spacing only
            $title = normalize_input($_POST['title'] ?? '', true);
            $date = sanitize($_POST['date'] ?? '');
            $time = sanitize($_POST['time'] ?? '');
            $barangayId = (int)($_POST['barangay_id'] ?? 0);
            $location = normalize_input($_POST['location'] ?? '', true);
            $address = normalize_input($_POST['address'] ?? '', true);
            $requirements = normalize_input($_POST['requirements'] ?? '');

            try {
                // Calculate end_time (default to 6 hours after start_time if not provided)
                $endTime = sanitize($_POST['end_time'] ?? '');
                if (empty($endTime) && !empty($time)) {
                    // If end_time not provided, calculate 6 hours after start_time
                    $startTimeObj = DateTime::createFromFormat('H:i', $time);
                    if ($startTimeObj) {
                        $startTimeObj->modify('+6 hours');
                        $endTime = $startTimeObj->format('H:i');
                    } else {
                        $endTime = '17:00'; // Default to 5:00 PM if time parsing fails
                    }
                } elseif (empty($endTime)) {
                    $endTime = '17:00'; // Default to 5:00 PM if no start time
                }
                
                // Insert new blood drive
                $query = "INSERT INTO blood_drives (
                    title, date, start_time, end_time, barangay_id, organization_type, organization_id,
                    location, address, requirements, status, created_at
                ) VALUES (
                    :title, :date, :start_time, :end_time, :barangay_id, 'redcross', :organization_id,
                    :location, :address, :requirements, 'Scheduled', NOW()
                )";

                $params = [
                    ':title' => $title,
                    ':date' => $date,
                    ':start_time' => $time,
                    ':end_time' => $endTime,
                    ':barangay_id' => $barangayId,
                    ':organization_id' => $redcrossId,
                    ':location' => $location,
                    ':address' => $address,
                    ':requirements' => $requirements
                ];

                $conn = getConnection();
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                
                $driveId = $conn->lastInsertId();

                // Send SMS notifications to patients and donors about new blood drive
                try {
                    require_once '../../includes/sim800c_sms.php';
                    require_once '../../includes/notification_templates.php';
                    
                    $institutionName = get_institution_name('redcross');
                    $formattedDate = date('F j, Y', strtotime($date));
                    $formattedTime = '';
                    if (!empty($time)) {
                        $formattedTime = ' from ' . date('h:i A', strtotime($time));
                    }
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
                                    $smsMessage .= "A new blood drive has been scheduled on {$formattedDate}{$formattedTime} at {$location}. ";
                                    $smsMessage .= "Please check your dashboard for details and consider participating. Thank you!";
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
                                        error_log('[REDCROSS_SMS_ERR] Exception sending blood drive SMS to patient ID ' . $patientId . ': ' . $smsEx->getMessage());
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
                                    $smsMessage .= "A new blood drive has been scheduled on {$formattedDate}{$formattedTime} at {$location}. ";
                                    $smsMessage .= "Please consider donating! Your contribution helps save lives. Thank you!";
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
                                        error_log('[REDCROSS_SMS_ERR] Exception sending blood drive SMS to donor ID ' . $donorId . ': ' . $smsEx->getMessage());
                                    }
                                }
                            }
                        }
                    }
                    
                    error_log('[REDCROSS_SMS] Blood drive SMS summary - Sent: ' . $smsSentCount . ', Failed: ' . $smsErrorCount);
                } catch (Exception $smsEx) {
                    error_log('[REDCROSS_SMS_ERR] Exception in blood drive SMS: ' . $smsEx->getMessage());
                    // Don't block blood drive creation if SMS fails
                }

                $_SESSION['blood_drive_message'] = "Blood drive created successfully!";
                $_SESSION['blood_drive_message_type'] = 'success';
                header('Location: blood-drives.php?success=1');
                exit;
            } catch (Exception $e) {
                $_SESSION['blood_drive_message'] = "Failed to create blood drive: " . $e->getMessage();
                $_SESSION['blood_drive_message_type'] = 'error';
                header('Location: blood-drives.php?success=1');
                exit;
            }
        } elseif ($_POST['action'] === 'update') {
            // Handle update action
            $driveId = (int)($_POST['drive_id'] ?? 0);
            $status = sanitize($_POST['status'] ?? '');

            try {
                $query = "UPDATE blood_drives SET status = :status WHERE id = :id AND organization_type = 'redcross' AND organization_id = :org_id";
                $params = [
                    ':status' => $status,
                    ':id' => $driveId,
                    ':org_id' => $redcrossId
                ];

                $conn = getConnection();
                $stmt = $conn->prepare($query);
                $stmt->execute($params);

                $_SESSION['blood_drive_message'] = "Blood drive updated successfully!";
                $_SESSION['blood_drive_message_type'] = 'success';
                header('Location: blood-drives.php?success=1');
                exit;
            } catch (Exception $e) {
                $_SESSION['blood_drive_message'] = "Failed to update blood drive: " . $e->getMessage();
                $_SESSION['blood_drive_message_type'] = 'error';
                header('Location: blood-drives.php?success=1');
                exit;
            }
        }
    }
    }
}

// Automatically mark past blood drives as completed
try {
    $conn = getConnection();
    $stmt = $conn->prepare("
        UPDATE blood_drives 
        SET status = 'Completed' 
        WHERE organization_type = 'redcross' 
        AND organization_id = ? 
        AND status = 'Scheduled' 
        AND date < CURDATE()
    ");
    $stmt->execute([$redcrossId]);
} catch (Exception $e) {
    // Log error but don't block page load
    error_log('Error auto-completing blood drives: ' . $e->getMessage());
}

// Get all blood drives for this Red Cross
$bloodDrives = executeQuery("
    SELECT bd.*, bu.name as barangay_name,
    (SELECT COUNT(*) FROM donor_appointments WHERE blood_drive_id = bd.id) as registered_donors
    FROM blood_drives bd
    JOIN barangay_users bu ON bd.barangay_id = bu.id
    WHERE bd.organization_type = 'redcross'
    AND bd.organization_id = ?
    ORDER BY bd.date DESC
", [$redcrossId]);

// Get all barangays for the dropdown
$barangays = executeQuery("SELECT * FROM barangay_users ORDER BY name ASC");
?>

<?php include_once 'header.php'; ?>

<div class="dashboard-content">
    <!-- Display messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            Blood drive operation completed successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <div class="drives-header-section">
        <div class="drives-hero">
            <h1><i class="bi bi-calendar-event me-3"></i>Blood Drive Management</h1>
            <p>Organize and manage community blood drives efficiently. Schedule drives, coordinate with barangays, track registrations, and ensure successful blood collection events that save lives.</p>
            <div class="hero-actions">
                <button class="hero-btn" onclick="openCreateDriveModal()">
                    <i class="bi bi-plus-circle me-2"></i>Create Blood Drive
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
                <i class="bi bi-calendar-event"></i>
            </div>
            <div class="stat-number"><?php echo count($bloodDrives); ?></div>
            <div class="stat-label">Total Drives</div>
        </div>
        <div class="stat-card clickable-stat-card" data-filter="Scheduled" style="cursor: pointer;">
            <div class="stat-icon-wrapper success">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-number"><?php echo count(array_filter($bloodDrives, function($d) { return $d['status'] === 'Scheduled'; })); ?></div>
            <div class="stat-label">Scheduled Drives</div>
        </div>
        <div class="stat-card clickable-stat-card" data-filter="all" style="cursor: pointer;">
            <div class="stat-icon-wrapper info">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-number"><?php echo array_sum(array_column($bloodDrives, 'registered_donors')); ?></div>
            <div class="stat-label">Total Registrations</div>
        </div>
        <div class="stat-card clickable-stat-card" data-filter="Completed" style="cursor: pointer;">
            <div class="stat-icon-wrapper warning">
                <i class="bi bi-calendar-check-fill"></i>
            </div>
            <div class="stat-number"><?php echo count(array_filter($bloodDrives, function($d) { return $d['status'] === 'Completed'; })); ?></div>
            <div class="stat-label">Completed Drives</div>
        </div>
    </div>

    <!-- Enhanced Drives Table -->
    <div class="drives-table-card">
        <div class="table-header">
            <h3><i class="bi bi-calendar-event me-2"></i>Blood Drive Records</h3>
            <div class="table-actions">
                <button class="table-btn" onclick="printReport()">
                    <i class="bi bi-printer me-2"></i>Print Report
                </button>
                <button class="table-btn" onclick="openCreateDriveModal()">
                    <i class="bi bi-plus-circle me-2"></i>Create Drive
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="enhanced-table table">
                <thead>
                    <tr>
                        <th>Drive Details</th>
                        <th>Date & Time</th>
                        <th>Location</th>
                        <th>Barangay</th>
                        <th>Registrations</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="drivesTableBody">
                    <?php if (count($bloodDrives) > 0): ?>
                        <?php foreach ($bloodDrives as $drive): ?>
                            <tr class="drive-row" data-status="<?php echo htmlspecialchars(strtolower($drive['status'] ?? 'scheduled')); ?>">
                                <td>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($drive['title']); ?></div>
                                        <small class="text-muted">ID: #<?php echo str_pad($drive['id'], 4, '0', STR_PAD_LEFT); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div><?php echo date('M d, Y', strtotime($drive['date'])); ?></div>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($drive['start_time'] ?? '00:00:00')); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div><?php echo htmlspecialchars($drive['location'] ?? 'Not specified'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($drive['address'] ?? 'Address not specified'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="barangay-badge"><?php echo htmlspecialchars($drive['barangay_name']); ?></span>
                                </td>
                                <td>
                                    <span class="registration-badge">
                                        <i class="bi bi-people me-1"></i><?php echo $drive['registered_donors']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status = $drive['status'];
                                    $statusClass = '';
                                    switch (strtolower($status)) {
                                        case 'scheduled':
                                            $statusClass = 'status-scheduled';
                                            break;
                                        case 'completed':
                                            $statusClass = 'status-completed';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'status-cancelled';
                                            break;
                                        default:
                                            $statusClass = 'status-scheduled';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="action-btn btn-view" onclick="viewDriveDetails(<?php echo $drive['id']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                <p class="mb-0 mt-3 text-muted">No blood drives scheduled yet.</p>
                                <small class="text-muted">Create your first blood drive to start organizing community events.</small>
                                <div class="mt-3">
                                    <button class="btn btn-primary" onclick="openCreateDriveModal()">
                                        <i class="bi bi-plus-circle me-1"></i>Create Your First Drive
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

<!-- Create Drive Modal -->
<div class="modal fade" id="createDriveModal" tabindex="-1" aria-labelledby="createDriveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createDriveModalLabel">Create New Blood Drive</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="createDriveForm" onsubmit="return validateCreateDriveForm()">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="title" class="form-label">Drive Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   placeholder="e.g., Community Blood Drive 2024" 
                                   pattern="[A-Za-z0-9\s\-\.]{3,100}" 
                                   title="Title must be 3-100 characters, letters, numbers, spaces, hyphens, and periods only"
                                   required>
                           
                        </div>
                        <div class="col-md-6">
                            <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date" name="date" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>"
                                   required>
                            <div class="form-text">Select the blood drive date</div>
                        </div>
                        <div class="col-md-6">
                            <label for="time" class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="time" name="time" 
                                   min="06:00" max="18:00" step="900"
                                   placeholder="Select time" required>
                            <div class="form-text">Choose a time between 6:00 AM and 6:00 PM</div>
                        </div>
                        <div class="col-md-6">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" 
                                   min="06:00" max="23:59" step="900"
                                   placeholder="Select end time">
                            <div class="form-text">Optional: Defaults to 6 hours after start time</div>
                        </div>
                        <div class="col-12">
                            <label for="barangay_id" class="form-label">Partner Barangay <span class="text-danger">*</span></label>
                            <select class="form-select" id="barangay_id" name="barangay_id" required>
                                <option value="">Select Barangay</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['id']; ?>"><?php echo htmlspecialchars($barangay['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Choose the partnering barangay for this blood drive</div>
                        </div>
                        <div class="col-md-6">
                            <label for="location" class="form-label">Venue Name</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   placeholder="e.g., Community Center, Barangay Hall"
                                   pattern="[A-Za-z0-9\s\-\.]{0,100}"
                                   title="Venue name should be letters, numbers, spaces, hyphens, and periods only">
                            
                        </div>
                        <div class="col-md-6">
                            <label for="address" class="form-label">Full Address</label>
                            <input type="text" class="form-control" id="address" name="address" 
                                   placeholder="Complete address with street, barangay, city"
                                   pattern="[A-Za-z0-9\s\-\.\,]{0,200}"
                                   title="Address should contain letters, numbers, spaces, and common punctuation">
                            
                        </div>
                        <div class="col-12">
                            <label for="requirements" class="form-label">Requirements & Instructions</label>
                            <textarea class="form-control" id="requirements" name="requirements" rows="3" 
                                      placeholder="List any special requirements or instructions for donors..."
                                      maxlength="500"></textarea>
                           
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-calendar-plus me-1"></i>Create Blood Drive
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel">Update Drive Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="drive_id" id="update_drive_id">
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Drive Details Modal -->
<div class="modal fade" id="driveDetailsModal" tabindex="-1" aria-labelledby="driveDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="driveDetailsModalLabel">Blood Drive Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div><strong>Title:</strong> <span id="dd_title">N/A</span></div>
            <div><strong>Drive ID:</strong> <span id="dd_id">N/A</span></div>
            <div><strong>Date:</strong> <span id="dd_date">N/A</span></div>
            <div><strong>Time:</strong> <span id="dd_time">N/A</span></div>
            <div><strong>Status:</strong> <span id="dd_status">N/A</span></div>
          </div>
          <div class="col-md-6">
            <div><strong>Barangay:</strong> <span id="dd_barangay">N/A</span></div>
            <div><strong>Location:</strong> <span id="dd_location">N/A</span></div>
            <div><strong>Address:</strong> <span id="dd_address">N/A</span></div>
            <div><strong>Registered Donors:</strong> <span id="dd_registered">0</span></div>
          </div>
          <div class="col-12">
            <div><strong>Requirements:</strong></div>
            <div id="dd_requirements" class="text-muted">None</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS (required for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Expose CSRF token for JS form submissions
const CSRF_TOKEN = '<?php echo htmlspecialchars(get_csrf_token()); ?>';

// Debug modal functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking modal functionality...');
    
    // Check if Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded!');
        return;
    }
    
    // Check if modal element exists
    const modal = document.getElementById('createDriveModal');
    if (!modal) {
        console.error('Create drive modal not found!');
        return;
    }
    
    console.log('Modal found:', modal);
    
    // Test modal functionality
    const createButtons = document.querySelectorAll('[data-bs-target="#createDriveModal"]');
    console.log('Found create buttons:', createButtons.length);
    
    createButtons.forEach((button, index) => {
        console.log(`Button ${index}:`, button);
        button.addEventListener('click', function(e) {
            console.log('Create button clicked!');
            e.preventDefault();
            
            try {
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
                console.log('Modal should be showing now');
            } catch (error) {
                console.error('Error showing modal:', error);
            }
        });
    });
    
    // Enhance time picker functionality
    const timeInput = document.getElementById('time');
    const endTimeInput = document.getElementById('end_time');
    
    if (timeInput) {
        console.log('Time input found:', timeInput);
        
        // Set default time if empty
        if (!timeInput.value) {
            timeInput.value = '09:00'; // Default to 9:00 AM
        }
        
        // Add click event to ensure time picker opens
        timeInput.addEventListener('click', function() {
            console.log('Time input clicked');
            this.showPicker && this.showPicker();
        });
        
        // Add focus event
        timeInput.addEventListener('focus', function() {
            console.log('Time input focused');
            this.showPicker && this.showPicker();
        });
        
        // Add change event to auto-calculate end time
        timeInput.addEventListener('change', function() {
            console.log('Time changed to:', this.value);
            
            // Auto-calculate end time if not set (6 hours after start)
            if (endTimeInput && !endTimeInput.value && this.value) {
                const startTime = new Date('2000-01-01T' + this.value + ':00');
                startTime.setHours(startTime.getHours() + 6);
                const endTime = startTime.toTimeString().slice(0, 5);
                endTimeInput.value = endTime;
                console.log('Auto-calculated end time:', endTime);
            }
        });
    }
    
    if (endTimeInput) {
        // Add click event to ensure time picker opens
        endTimeInput.addEventListener('click', function() {
            this.showPicker && this.showPicker();
        });
        
        // Add focus event
        endTimeInput.addEventListener('focus', function() {
            this.showPicker && this.showPicker();
        });
    }
    
    // Add form validation and debugging
    const createForm = document.getElementById('createDriveForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            console.log('Create drive form submitted');
            
            // Validate required fields
            const title = document.getElementById('title').value.trim();
            const date = document.getElementById('date').value;
            const time = document.getElementById('time').value;
            const barangayId = document.getElementById('barangay_id').value;
            
            // Enhanced validation
            if (!title || !date || !time || !barangayId) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Validate title format
            if (title.length < 3 || title.length > 100) {
                e.preventDefault();
                alert('Title must be between 3 and 100 characters.');
                return false;
            }
            
            // Validate date is not in the past
            const selectedDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                e.preventDefault();
                alert('Blood drive date cannot be in the past.');
                return false;
            }
            
            // Validate time is within business hours
            const timeValue = time.split(':');
            const hours = parseInt(timeValue[0]);
            if (hours < 6 || hours > 18) {
                e.preventDefault();
                alert('Blood drive time must be between 6:00 AM and 6:00 PM.');
                return false;
            }
            
            // Auto-calculate end_time if not provided
            const endTime = document.getElementById('end_time').value;
            if (!endTime && time) {
                const startTimeObj = new Date('2000-01-01T' + time + ':00');
                startTimeObj.setHours(startTimeObj.getHours() + 6);
                const calculatedEndTime = startTimeObj.toTimeString().slice(0, 5);
                document.getElementById('end_time').value = calculatedEndTime;
            }
            
            console.log('Form validation passed, submitting...');
        });
    }
    
    // Add input formatting functions
    function formatTitleCase(input) {
        return input
            .toLowerCase()
            .split(' ')
            .filter(word => word.length > 0) // Remove empty strings
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }
    
    function formatSingleSpaces(input) {
        // Replace multiple consecutive spaces/tabs with single spaces
        // This preserves single spaces between words while removing extra spaces
        // Example: "word1    word2   word3" becomes "word1 word2 word3"
        return input.replace(/\s+/g, ' ').trim();
    }
    
    function formatInputField(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('keydown', function(e) {
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
            
            field.addEventListener('input', function(e) {
                const cursorPos = this.selectionStart;
                let value = this.value;
                
                // Prevent multiple consecutive spaces
                value = value.replace(/\s{2,}/g, ' ');
                
                // Apply title case formatting while typing
                const words = value.split(' ');
                const formattedWords = words.map((word) => {
                    if (!word) return '';
                    // Only format if word has at least one letter
                    const match = word.match(/^([A-Za-z])(.*)$/);
                    if (match) {
                        return match[1].toUpperCase() + match[2].toLowerCase();
                    }
                    return word;
                });
                
                value = formattedWords.join(' ');
                
                // Update value if changed
                if (value !== this.value) {
                    const oldValue = this.value;
                    this.value = value;
                    // Try to maintain cursor position
                    const diff = value.length - oldValue.length;
                    const newPos = Math.min(Math.max(0, cursorPos + diff), this.value.length);
                    this.setSelectionRange(newPos, newPos);
                    this.classList.add('formatting');
                    setTimeout(() => {
                        this.classList.remove('formatting');
                    }, 300);
                }
            });
            
            field.addEventListener('blur', function() {
                if (this.value.trim()) {
                    const cursorPos = this.value.length;
                    // Final format to ensure consistency
                    let value = this.value.replace(/\s+/g, ' ').trim();
                    value = value.split(' ').map((word) => {
                        if (!word) return '';
                        const match = word.match(/^([A-Za-z])(.*)$/);
                        if (match) {
                            return match[1].toUpperCase() + match[2].toLowerCase();
                        }
                        return word;
                    }).join(' ');
                    this.value = value;
                    this.setSelectionRange(cursorPos, cursorPos);
                }
            });
        }
    }
    
    // Apply formatting to text input fields
    formatInputField('title');
    formatInputField('location');
    formatInputField('address');
    
    // Apply single space formatting to textarea fields (but not title case)
    function formatTextareaField(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('keydown', function(e) {
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
            
            field.addEventListener('input', function() {
                let value = this.value;
                const originalValue = value;
                
                // Remove multiple spaces
                value = value.replace(/\s+/g, ' ');
                
                if (value !== originalValue) {
                    this.classList.add('formatting');
                    this.value = value;
                    setTimeout(() => {
                        this.classList.remove('formatting');
                    }, 500);
                }
            });
        }
    }
    
    // Apply to textarea fields
    formatTextareaField('requirements');
});

// Fallback function to manually open create drive modal
function openCreateDriveModal() {
    console.log('Manually opening create drive modal...');
    const modal = document.getElementById('createDriveModal');
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
    } else {
        console.error('Create drive modal not found!');
        alert('Error: Create drive modal not found. Please refresh the page and try again.');
    }
}

// Print report function
function printReport() {
    window.print();
}

async function viewDriveDetails(driveId) {
    try {
        const res = await fetch(`get-drive-details.php?id=${encodeURIComponent(driveId)}`, { credentials: 'same-origin' });
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        const d = await res.json();
        
        if (d.error) {
            alert('Error: ' + d.error);
            return;
        }
        
        // Populate modal fields
        const m = document.getElementById('driveDetailsModal');
        if (!m) {
            console.error('Drive details modal not found');
            alert('Drive details modal not found. Please refresh the page.');
            return;
        }
        
        // Format time display
        const formatTime = (timeStr) => {
            if (!timeStr) return 'N/A';
            try {
                const [hours, minutes] = timeStr.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                return `${displayHour}:${minutes} ${ampm}`;
            } catch (e) {
                return timeStr;
            }
        };
        
        m.querySelector('#dd_title').textContent = d.title || 'N/A';
        m.querySelector('#dd_id').textContent = `#${String(d.id).padStart(4,'0')}`;
        m.querySelector('#dd_date').textContent = d.date ? new Date(d.date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';
        m.querySelector('#dd_time').textContent = formatTime(d.start_time);
        m.querySelector('#dd_location').textContent = d.location || 'N/A';
        m.querySelector('#dd_address').textContent = d.address || 'N/A';
        m.querySelector('#dd_barangay').textContent = d.barangay_name || 'N/A';
        m.querySelector('#dd_requirements').textContent = d.requirements || 'None';
        m.querySelector('#dd_status').textContent = d.status || 'Scheduled';
        m.querySelector('#dd_registered').textContent = d.registered_donors != null ? d.registered_donors : '0';
        
        // Show modal
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = new bootstrap.Modal(m);
            // Ensure clean lifecycle to avoid stuck modals
            m.addEventListener('hidden.bs.modal', function onHidden(){
                try { modal.dispose(); } catch (ex) {}
                m.removeEventListener('hidden.bs.modal', onHidden);
                // Ensure body scroll is restored
                setTimeout(function() {
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }, 50);
            });
            modal.show();
        } else {
            // Fallback if Bootstrap not loaded
            m.style.display = 'block';
            m.classList.add('show');
            document.body.classList.add('modal-open');
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'drive-details-backdrop';
            document.body.appendChild(backdrop);
        }
    } catch (e) {
        console.error('Error loading drive details:', e);
        alert('Unable to load drive details: ' + e.message);
    }
}

function editDrive(driveId) {
    // Prefill the existing Update Status modal with the drive ID and open it
    const idInput = document.getElementById('update_drive_id');
    if (idInput) { idInput.value = driveId; }
    const modalEl = document.getElementById('updateStatusModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        // Ensure clean lifecycle
        modalEl.addEventListener('hidden.bs.modal', function onHidden(){
            try { modal.dispose(); } catch (ex) {}
            modalEl.removeEventListener('hidden.bs.modal', onHidden);
            // Ensure body scroll is restored
            setTimeout(function() {
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, 50);
        });
        modal.show();
    }
}

function validateCreateDriveForm() {
    const title = document.getElementById('title').value.trim();
    const date = document.getElementById('date').value;
    const time = document.getElementById('time').value;
    const barangayId = document.getElementById('barangay_id').value;
    
    if (!title || !date || !time || !barangayId) {
        alert('Please fill in all required fields.');
        return false;
    }
    
    return true;
}

function completeDrive(driveId) {
    if (confirm('Mark this blood drive as completed?')) {
        // Submit form to update status
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="drive_id" value="${driveId}">
            <input type="hidden" name="status" value="Completed">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    setTimeout(function() {
        document.querySelectorAll('.alert, .feedback-message, .notification, .status-message').forEach(function(el) {
            el.style.display = 'none';
        });
    }, 5000);

    // Filter table by status when clicking stat cards
    const statCards = document.querySelectorAll('.clickable-stat-card');
    const tableBody = document.getElementById('drivesTableBody');
    
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
                const rows = tableBody.querySelectorAll('.drive-row');
                let visibleCount = 0;
                
                rows.forEach(function(row) {
                    const rowStatus = row.getAttribute('data-status');
                    const filterLower = filter.toLowerCase();
                    
                    if (filterLower === 'all' || rowStatus === filterLower) {
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
                        noResultsRow.innerHTML = '<td colspan="7" class="text-center py-5"><i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i><p class="mb-0 mt-3 text-muted">No ' + filter + ' drives found.</p></td>';
                        tableBody.appendChild(noResultsRow);
                    }
                } else {
                    if (noResultsRow) {
                        noResultsRow.remove();
                    }
                }
                
                // Scroll to table
                tableBody.closest('.drives-table-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }
});
</script>

<style>
    /* Enhanced Blood Drives Page Styling */
    .dashboard-content {
        padding: 2rem;
    }
    
    /* Time input styling */
    input[type="time"] {
        cursor: pointer;
        background-color: #fff;
    }
    
    input[type="time"]:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    
    input[type="time"]:hover {
        border-color: #86b7fe;
    }
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        min-height: 100vh;
    }

    .drives-header-section {
        margin-bottom: 2rem;
    }

    .drives-hero {
        background: linear-gradient(135deg, #DC143C 0%, #B22222 50%, #8B0000 100%);
        color: white;
        padding: 3rem 2rem;
        border-radius: 20px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(220, 20, 60, 0.3);
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .drives-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        opacity: 0.3;
    }

    .drives-hero h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        position: relative;
        z-index: 2;
    }

    .drives-hero p {
        font-size: 1.1rem;
        margin-bottom: 2rem;
        opacity: 0.95;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        position: relative;
        z-index: 2;
    }

    .hero-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .hero-btn {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.3);
        padding: 12px 24px;
        border-radius: 50px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        cursor: pointer;
    }

    .hero-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }

    /* Enhanced Statistics Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
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

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #DC143C, #FF6B6B);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(220, 20, 60, 0.15);
    }

    .clickable-stat-card {
        transition: all 0.3s ease;
    }

    .clickable-stat-card:hover {
        transform: translateY(-8px) !important;
        box-shadow: 0 12px 40px rgba(220, 20, 60, 0.25) !important;
    }

    .clickable-stat-card.active {
        border: 2px solid #DC143C;
        box-shadow: 0 8px 30px rgba(220, 20, 60, 0.2);
    }

    .stat-icon-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.5rem;
    }

    .stat-icon-wrapper.primary {
        background: linear-gradient(135deg, #DC143C, #FF6B6B);
        color: white;
    }

    .stat-icon-wrapper.success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .stat-icon-wrapper.info {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: white;
    }

    .stat-icon-wrapper.warning {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: white;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: #6c757d;
        font-weight: 500;
        font-size: 0.95rem;
    }

    /* Enhanced Table Styling */
    .drives-table-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .table-header {
        background: linear-gradient(135deg, #DC143C 0%, #B22222 100%);
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
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .table-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-1px);
        color: white;
    }

    .enhanced-table {
        margin: 0;
        border: none;
    }

    .enhanced-table thead th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: none;
        padding: 1rem;
        font-weight: 600;
        color: #495057;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    .enhanced-table tbody tr {
        border-bottom: 1px solid #f1f3f4;
        transition: all 0.2s ease;
    }

    .enhanced-table tbody tr:hover {
        background-color: #f8f9fa;
        transform: scale(1.01);
    }

    .enhanced-table tbody td {
        padding: 1rem;
        vertical-align: middle;
        border: none;
    }

    .barangay-badge {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .registration-badge {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-scheduled {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .status-completed {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: white;
    }

    .status-cancelled {
        background: linear-gradient(135deg, #dc3545, #fd7e14);
        color: white;
    }

    .action-btn {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .btn-view {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
    }

    .btn-edit {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: white;
    }

    .btn-complete {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .action-btn:hover {
        transform: translateY(-2px) scale(1.1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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

    /* Modal styling improvements */
    .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .modal-header {
        background: linear-gradient(135deg, #DC143C 0%, #B22222 100%);
        color: white;
        border-radius: 15px 15px 0 0;
        border-bottom: none;
    }
    .modal-header .btn-close {
        filter: invert(1);
    }
    .modal-body {
        padding: 2rem;
    }
    .modal-footer {
        border-top: 1px solid #dee2e6;
        padding: 1rem 2rem;
    }
    
    /* Input formatting feedback */
    .form-control.formatting {
        border-color: #17a2b8;
        box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        transition: all 0.3s ease;
    }
    
    .form-text {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    
    .form-text.formatting-hint {
        color: #17a2b8;
        font-weight: 500;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .dashboard-content {
            padding: 1rem;
        }

        .drives-hero {
            padding: 2rem 1rem;
        }

        .drives-hero h1 {
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
            grid-template-columns: repeat(2, 1fr);
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
</style>
<script src="../../assets/js/titlecase-formatter.js"></script>
