<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../../login.php?role=donor");
    exit;
}

// Set dashboard flag
$isDashboard = true;
$pageTitle = "Donor Dashboard - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

$donorId = $_SESSION['user_id']; // for donor user
$donor = getRow("SELECT * FROM donor_users WHERE id = ?", [$donorId]);


// Get donation statistics
$totalDonations = getRow("SELECT COUNT(*) as count FROM donations WHERE donor_id = ?", [$donorId]);
$totalDonations = $totalDonations ? $totalDonations['count'] : 0;

// Get recent notifications
$notifications = executeQuery("
    SELECT * FROM notifications 
    WHERE user_id = ? AND user_role = 'donor' 
    ORDER BY created_at DESC 
    LIMIT 5
", [$donorId]);

if ($notifications === false) {
    error_log("Failed to fetch notifications for donor ID $donorId");
}

// Count unread notifications
$unreadCount = getCount("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id = ? AND user_role = 'donor' AND is_read = 0
", [$donorId]);

$totalunits = getRow("SELECT SUM(units) as total FROM donations WHERE donor_id = ?", [$donorId]);
$totalunits = $totalunits && $totalunits['total'] ? $totalunits['total'] : 0;

$livesSaved = $totalunits * 3; // Assuming each unit saves 3 lives

// Get upcoming donation appointment
$upcomingAppointment = getRow("
    SELECT * FROM donor_appointments
    WHERE donor_id = ? AND appointment_date >= CURDATE()
    ORDER BY appointment_date ASC
    LIMIT 1
", [$donorId]);

$recentDonations = executeQuery("
    SELECT d.donation_date, d.units, du.name AS donor_name, du.blood_type, du.address
    FROM donations d
    JOIN donor_users du ON d.donor_id = du.id
    WHERE d.donor_id = ?
    ORDER BY d.donation_date DESC
    LIMIT 5
", [$donorId]);

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment_id'])) {
    $appointmentId = $_POST['cancel_appointment_id'];
    // Make sure the appointment belongs to this donor
    executeQuery("DELETE FROM donor_appointments WHERE id = ? AND donor_id = ?", [$appointmentId, $donorId]);
    // Get the latest donation date for this donor
    $latestDonation = getRow("SELECT MAX(donation_date) as last_date FROM donations WHERE donor_id = ?", [$donorId]);
    $lastDate = $latestDonation && $latestDonation['last_date'] ? $latestDonation['last_date'] : null;

    // Update the donor's last_donation_date (set to NULL if no donations left)
    executeQuery("UPDATE donor_users SET last_donation_date = ? WHERE id = ?", [$lastDate, $donorId]);
    // Optionally, redirect to avoid form resubmission
    header("Location: index.php");
    exit;
}


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
                <h2 class="page-title">Donor Dashboard</h2>
                <div class="header-actions">
                    <!-- Notification Bell - Consistent across all donor pages -->
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
            <!-- Welcome Banner -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3>Welcome back, <?php echo $_SESSION['user_name']; ?>!</h3>
                            <p class="mb-0">Thank you for being a blood donor. Your contributions help save lives every day.</p>

                            <?php if ($upcomingAppointment): ?>
                                <div class="alert alert-info mt-3 mb-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-calendar-check me-2"></i>
                                        You have an upcoming donation appointment on
                                        <strong><?php echo date('F j, Y', strtotime($upcomingAppointment['appointment_date'])); ?></strong>
                                        at <strong><?php echo date('g:i A', strtotime($upcomingAppointment['appointment_time'])); ?></strong>
                                    </div>
                                    <form method="post" style="margin:0;">
                                        <input type="hidden" name="cancel_appointment_id" value="<?php echo $upcomingAppointment['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger ms-3">Cancel</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="mt-3">
                                    <a href="/blood/dashboard/donor/schedule-donation.php" class="btn btn-danger">Schedule Your Next Donation</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="blood-type-badge">
                                <span><?php echo $donor['blood_type']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-danger mb-2">
                                <i class="bi bi-droplet-fill"></i>
                            </div>
                            <h3 class="counter h2 fw-bold"><?php echo $totalDonations; ?></h3>
                            <p class="text-muted">Total Donations</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-danger mb-2">
                                <i class="bi bi-water"></i>
                            </div>
                            <h3 class="counter h2 fw-bold"><?php echo $totalunits; ?></h3>
                            <p class="text-muted">units Donated</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-danger mb-2">
                                <i class="bi bi-heart-fill"></i>
                            </div>
                            <h3 class="counter h2 fw-bold"><?php echo $livesSaved; ?></h3>
                            <p class="text-muted">Lives Saved</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Donations and Quick Actions (side by side) -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="card-title mb-0">Recent Donations</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($recentDonations && count($recentDonations) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Location</th>
                                                <th>units</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentDonations as $donation): ?>
                                                <?php 
                                                // Decrypt address if encrypted
                                                $displayAddress = $donation['address'];
                                                if (function_exists('decrypt_value')) {
                                                    $decAddress = decrypt_value($donation['address']);
                                                    if ($decAddress !== null && $decAddress !== '') {
                                                        $displayAddress = $decAddress;
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($displayAddress); ?></td>
                                                    <td><?php echo $donation['units']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="/blood/dashboard/donor/donation-history.php" class="btn btn-sm btn-outline-danger">View All Donations</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="display-4 text-muted mb-3">
                                        <i class="bi bi-droplet"></i>
                                    </div>
                                    <p class="mb-3">You haven't made any donations yet.</p>
                                    <a href="dashboard/donor/schedule-donation.php" class="btn btn-danger">Schedule Your First Donation</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="card-title mb-0"><i class="bi bi-lightning-charge text-danger me-2"></i>Quick Actions</h4>
                        </div>
                        <div class="card-body d-flex flex-column gap-2">
                            <a href="schedule-donation.php" class="btn btn-danger w-100"><i class="bi bi-calendar-plus me-2"></i>Schedule Donation</a>
                            <a href="donation-history.php" class="btn btn-outline-danger w-100"><i class="bi bi-clock-history me-2"></i>Donation History</a>
                            <a href="profile.php" class="btn btn-outline-secondary w-100"><i class="bi bi-person-lines-fill me-2"></i>Update Profile</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                            </body>
                            </html>

<?php include_once '../../includes/footer.php'; ?>