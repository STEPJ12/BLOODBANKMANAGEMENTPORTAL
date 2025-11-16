<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../../login.php?role=donor");
    exit;
}

// Set page title
$pageTitle = "Donor Profile - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get donor information
$donorId = $_SESSION['user_id'];
$donor = getRow("SELECT * FROM donor_users WHERE id = ?", [$donorId]);
// Decrypt PII where possible
if ($donor) {
    $decPhone = function_exists('decrypt_value') ? decrypt_value($donor['phone']) : null;
    $decAddress = function_exists('decrypt_value') ? decrypt_value($donor['address']) : null;
    if ($decPhone !== null) { $donor['phone'] = $decPhone; }
    if ($decAddress !== null) { $donor['address'] = $decAddress; }
}

// Process form submission
$success = false;
$error = "";
$passwordSuccess = false;
$passwordError = "";

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordError = "All password fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = "New password and confirm password do not match.";
    } elseif (strlen($newPassword) < 8) {
        $passwordError = "New password must be at least 8 characters long.";
    } else {
        // Get current password hash from database
        $donorData = getRow("SELECT password FROM donor_users WHERE id = ?", [$donorId]);
        
        if ($donorData && password_verify($currentPassword, $donorData['password'])) {
            // Current password is correct, update to new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $result = updateRow("UPDATE donor_users SET password = ? WHERE id = ?", [$hashedPassword, $donorId]);
            
            if ($result !== false) {
                $passwordSuccess = true;
                if (function_exists('audit_log')) {
                    audit_log($donorId, 'donor', 'password_change', 'Password changed');
                }
            } else {
                $passwordError = "Failed to update password. Please try again.";
            }
        } else {
            $passwordError = "Current password is incorrect.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Validate and sanitize input
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $bloodType = sanitize($_POST['blood_type'] ?? '');
    $dateOfBirth = sanitize($_POST['date_of_birth'] ?? '');

    // Basic validation
    if (empty($name) || empty($email)) {
        $error = "Name and email are required fields.";
    } else {
        // Convert empty strings to NULL for optional fields
        $dateOfBirth = empty($dateOfBirth) || $dateOfBirth === '0000-00-00' ? null : $dateOfBirth;
        
        // Validate blood_type - must be one of the valid enum values
        $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        if (!empty($bloodType) && !in_array($bloodType, $validBloodTypes)) {
            $error = "Invalid blood type selected.";
        } else {
            $bloodType = empty($bloodType) ? null : $bloodType;
        }
        
        $phone = empty($phone) ? null : $phone;
        $address = empty($address) ? null : $address;

        // Encrypt PII if encryption function exists
        $encPhone = $phone;
        $encAddress = $address;
        if (function_exists('encrypt_value')) {
            if ($phone !== null && $phone !== '') {
                $encResult = encrypt_value($phone);
                if ($encResult === false || $encResult === null) {
                    $error = "Failed to encrypt phone number. Please try again.";
                } else {
                    $encPhone = $encResult;
                }
            }
            if ($address !== null && $address !== '' && !$error) {
                $encResult = encrypt_value($address);
                if ($encResult === false || $encResult === null) {
                    $error = "Failed to encrypt address. Please try again.";
                } else {
                    $encAddress = $encResult;
                }
            }
        }

        if (!$error) {
            // Use direct PDO connection for better error handling
            try {
                $conn = getConnection();
                $stmt = $conn->prepare("
                    UPDATE donor_users
                    SET name = ?, email = ?, phone = ?, address = ?,
                        blood_type = ?, date_of_birth = ?
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $name, $email, $encPhone, $encAddress,
                    $bloodType, $dateOfBirth,
                    $donorId
                ]);

                if ($result && $stmt->rowCount() >= 0) {
                    $success = true;
                    if (function_exists('audit_log')) {
                        audit_log($donorId, 'donor', 'profile_update', 'Profile updated');
                    }
                    // Refresh donor data
                    $donor = getRow("SELECT * FROM donor_users WHERE id = ?", [$donorId]);
                    if ($donor) {
                        $decPhone = function_exists('decrypt_value') ? decrypt_value($donor['phone']) : null;
                        $decAddress = function_exists('decrypt_value') ? decrypt_value($donor['address']) : null;
                        if ($decPhone !== null && $decPhone !== false) { $donor['phone'] = $decPhone; }
                        if ($decAddress !== null && $decAddress !== false) { $donor['address'] = $decAddress; }
                    }
                } else {
                    $error = "Failed to update profile. No rows were affected.";
                    error_log("Profile update failed - no rows affected for donor ID: " . $donorId);
                }
            } catch (PDOException $e) {
                $error = "Failed to update profile. Please check your input and try again.";
                error_log("Profile update PDO error for donor ID " . $donorId . ": " . $e->getMessage());
                if (isset($stmt)) {
                    $errorInfo = $stmt->errorInfo();
                    if ($errorInfo) {
                        error_log("SQL Error Info: " . print_r($errorInfo, true));
                    }
                }
            } catch (Exception $e) {
                $error = "An unexpected error occurred. Please try again.";
                error_log("Profile update exception for donor ID " . $donorId . ": " . $e->getMessage());
            }
        }
    }
}



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
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
                <h2 class="page-title">Donor Profile</h2>
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
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" data-auto-dismiss="5000">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Your profile has been updated successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" data-auto-dismiss="5000">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($passwordSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" data-auto-dismiss="5000">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Your password has been changed successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($passwordError): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" data-auto-dismiss="5000">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $passwordError; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body text-center p-4">
                            <div class="avatar-container mb-3">
                                <div class="avatar-circle">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                            </div>
                            <h4><?php echo $donor['name']; ?></h4>
                            <p class="text-muted mb-3"><?php echo $donor['email']; ?></p>

                            <div class="d-flex justify-content-center mb-3">
                                <span class="badge bg-danger">
                                    <i class="bi bi-droplet me-1"></i> <?php echo $donor['blood_type'] ?? 'N/A'; ?>
                                </span>
                            </div>

                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                    <i class="bi bi-key me-2"></i>Change Password
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="card-title mb-0">Donation Summary</h4>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get donation statistics (Completed donations only)
                            $rowCount = getRow("SELECT COUNT(*) as count FROM donations WHERE donor_id = ? AND status = 'Completed'", [$donorId]);
                            $rowUnits = getRow("SELECT COALESCE(SUM(units),0) as total FROM donations WHERE donor_id = ? AND status = 'Completed'", [$donorId]);
                            $rowLast = getRow("SELECT donation_date FROM donations WHERE donor_id = ? AND status = 'Completed' ORDER BY donation_date DESC LIMIT 1", [$donorId]);
                            $totalDonations = $rowCount ? (int)$rowCount['count'] : 0;
                            $totalunits = $rowUnits ? (int)$rowUnits['total'] : 0;
                            $lastDonation = $rowLast['donation_date'] ?? null;
                            ?>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="text-muted">Total Donations</div>
                                <div class="fw-bold"><?php echo $totalDonations; ?></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="text-muted">Total units</div>
                                <div class="fw-bold"><?php echo $totalunits; ?></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="text-muted">Last Donation</div>
                                <div class="fw-bold">
                                    <?php echo $lastDonation ? date('M d, Y', strtotime($lastDonation)) : 'N/A'; ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">Lives Saved</div>
                                <div class="fw-bold"><?php echo $totalunits * 3; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="card-title mb-0">Edit Profile</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="update_profile" value="1">
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $donor['name']; ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $donor['email']; ?>" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $donor['phone']; ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo $donor['date_of_birth']; ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="blood_type" class="form-label">Blood Type</label>
                                    <select class="form-select" id="blood_type" name="blood_type" required>
                                        <option value="">-- Select Blood Type --</option>
                                        <option value="A+" <?php echo ($donor['blood_type'] === 'A+') ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo ($donor['blood_type'] === 'A-') ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo ($donor['blood_type'] === 'B+') ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo ($donor['blood_type'] === 'B-') ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo ($donor['blood_type'] === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo ($donor['blood_type'] === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo ($donor['blood_type'] === 'O+') ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo ($donor['blood_type'] === 'O-') ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2" required><?php echo $donor['address']; ?></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="changePasswordForm" method="POST" action="">
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="8">
                        <div class="form-text">Password must be at least 8 characters long.</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required minlength="8">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="changePasswordForm" name="change_password" value="1" class="btn btn-primary">Change Password</button>
            </div>
        </div>
    </div>
</div>

</body>

<style>
    .avatar-container {
        display: flex;
        justify-content: center;
    }

    .avatar-circle {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background-color: #dc3545;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
    }
</style>

<script>
    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('[data-auto-dismiss]');
        alerts.forEach(function(alert) {
            const dismissTime = parseInt(alert.getAttribute('data-auto-dismiss'));
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, dismissTime);
        });

        // Close password modal on successful password change
        <?php if ($passwordSuccess): ?>
            const passwordModal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
            if (passwordModal) {
                passwordModal.hide();
            }
            // Reset form
            document.getElementById('changePasswordForm').reset();
        <?php endif; ?>
    });
</script>