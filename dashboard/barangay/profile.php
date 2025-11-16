<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

// Set dashboard flag
$isDashboard = true;
$pageTitle = "Barangay Profile - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get barangay information
$barangayId = $_SESSION['user_id'];
$barangayRow = getRow("SELECT * FROM barangay_users WHERE id = ?", [$barangayId]);
$barangayName = $barangayRow['name'] ?? 'Barangay';
$barangay = $barangayRow; // Keep for backward compatibility


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>
    
    <?php
    // Determine the correct path for CSS files - MUST be defined before use
    $basePath = '';
    if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
        $basePath = '../../';
    }
    ?>
    
    <link rel="stylesheet" href="../../css/barangay-portal.css">

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

    <!-- Custom CSS -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/dashboard.css">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    
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
                            <h1 class="mb-1">Profile Management</h1>
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
        <div class="dashboard-main">
            <div class="row justify-content-center">
                <!-- Profile Information -->
                <div class="col-lg-6 col-xl-5 col-xxl-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body text-center">
                            <div class="mb-4">
                                <div class="avatar-lg mx-auto mb-3">
                                    <i class="bi bi-buildings fs-1"></i>
                                </div>
                                <h4 class="mb-1"><?php echo $barangay['name']; ?></h4>
                                <p class="text-muted mb-0">Barangay Official</p>
                            </div>
                            <div class="d-grid">
                                <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#updateProfileModal">
                                    <i class="bi bi-pencil me-2"></i>Edit Profile
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 pb-0">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person-lines-fill me-2"></i>Contact Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                        <div class="icon-wrapper me-3">
                                            <i class="bi bi-envelope"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Email Address</small>
                                            <span class="fw-medium"><?php echo $barangay['email']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                        <div class="icon-wrapper me-3">
                                            <i class="bi bi-telephone"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Phone Number</small>
                                            <span class="fw-medium"><?php echo $barangay['phone']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                        <div class="icon-wrapper me-3">
                                            <i class="bi bi-geo-alt"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Address</small>
                                            <span class="fw-medium"><?php echo $barangay['address']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Update Profile Modal -->
<div class="modal fade" id="updateProfileModal" tabindex="-1" aria-labelledby="updateProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateProfileModalLabel">
                    <i class="bi bi-person-gear me-2"></i>Update Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="update-profile.php" method="post">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name" class="form-label">
                                    <i class="bi bi-building me-1"></i>Barangay Name
                                </label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo $barangay['name']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone" class="form-label">
                                    <i class="bi bi-telephone me-1"></i>Phone Number
                                </label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $barangay['phone']; ?>" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope me-1"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $barangay['email']; ?>" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="address" class="form-label">
                                    <i class="bi bi-geo-alt me-1"></i>Address
                                </label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $barangay['address']; ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Profile Avatar - Aesthetic blue gradient with effects */
.avatar-lg {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%) !important;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 
        0 8px 32px rgba(59, 130, 246, 0.4),
        0 4px 16px rgba(0, 0, 0, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    border: 3px solid rgba(255, 255, 255, 0.3);
}

.avatar-lg::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transform: rotate(45deg);
    animation: avatarShimmer 3s infinite;
}

@keyframes avatarShimmer {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

.avatar-lg:hover {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 
        0 12px 40px rgba(234, 179, 8, 0.4),
        0 6px 20px rgba(0, 0, 0, 0.15),
        inset 0 1px 0 rgba(255, 255, 255, 0.4);
    background: linear-gradient(135deg, #eab308 0%, #fbbf24 50%, #f59e0b 100%) !important;
}

.avatar-lg i {
    color: #ffffff !important;
    font-size: 3rem !important;
    position: relative;
    z-index: 1;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.avatar-lg:hover i {
    transform: scale(1.1);
}

/* Contact Information - Remove gray background, make transparent with aesthetic styling */
.bg-light {
    background: transparent !important;
    border: none !important;
    border-bottom: 2px solid rgba(59, 130, 246, 0.1) !important;
    transition: all 0.3s ease !important;
    padding: 1rem !important;
    border-radius: 0 !important;
}

.bg-light:hover {
    background: rgba(59, 130, 246, 0.03) !important;
    border-bottom-color: rgba(59, 130, 246, 0.3) !important;
    transform: translateX(4px);
    padding-left: 1.25rem !important;
}

.bg-light:last-child {
    border-bottom: none !important;
}

/* Aesthetic icon wrappers with beautiful gradients */
.icon-wrapper {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%) !important;
    border-radius: 12px !important;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none !important;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3), 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.icon-wrapper::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.icon-wrapper:hover::before {
    width: 100px;
    height: 100px;
}

.icon-wrapper:hover {
    transform: translateY(-4px) scale(1.05);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4), 0 4px 12px rgba(0, 0, 0, 0.15);
    background: linear-gradient(135deg, #eab308 0%, #fbbf24 50%, #f59e0b 100%) !important;
}

.icon-wrapper i {
    color: #ffffff !important;
    font-size: 1.5rem !important;
    position: relative;
    z-index: 1;
    transition: all 0.3s ease;
}

.icon-wrapper:hover i {
    transform: scale(1.1) rotate(5deg);
}



/* Ensure all text is visible - no gray backgrounds */
.bg-light .fw-medium,
.bg-light span,
.bg-light * {
    background: transparent !important;
    color: #2a363b !important;
}

.bg-light .text-muted {
    background: transparent !important;
    color: #64748b !important;
}

/* Remove any background from contact information text */
.card-body .bg-light * {
    background: transparent !important;
}

</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once '../../includes/footer.php'; ?>