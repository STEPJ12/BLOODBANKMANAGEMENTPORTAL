<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../../login.php?role=donor");
    exit;
}

// Set page title
$pageTitle = "Donation History - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get donor information
$donorId = $_SESSION['user_id'];
$donor = getRow("SELECT * FROM donor_users WHERE id = ?", [$donorId]);

// Get donor's donation history
$donations = executeQuery("
    SELECT 
        d.*, 
        bd.title as blood_drive_title,
        CASE
            WHEN d.organization_type = 'redcross' THEN 'Red Cross'
            WHEN d.organization_type = 'negrosfirst' THEN 'Negros First'
            ELSE d.organization_type
        END as organization_name
    FROM donations d
    LEFT JOIN blood_drives bd ON d.blood_drive_id = bd.id
    WHERE d.donor_id = ?
    ORDER BY d.donation_date DESC
", [$donorId]);

// Ensure $donations is always an array
if ($donations === false) {
    $donations = [];
    error_log("Failed to fetch donations for donor ID: " . $donorId);
}

// Aggregates: yearly totals and last-12-months frequency
$yearly = executeQuery("
    SELECT 
        YEAR(donation_date) as yr, 
        COUNT(*) as donations_count, 
        COALESCE(SUM(units), 0) as total_units
    FROM donations
    WHERE donor_id = ?
    GROUP BY YEAR(donation_date)
    ORDER BY yr DESC
", [$donorId]);

// Ensure $yearly is always an array
if ($yearly === false) {
    $yearly = [];
}

// Compute average interval in days between donations
$avgIntervalDays = null;
if (is_array($donations) && count($donations) > 1) {
    $prev = null; $sum = 0; $cnt = 0;
    foreach ($donations as $d) {
        $dt = strtotime($d['donation_date']);
        if ($prev !== null) { $sum += abs($prev - $dt) / 86400; $cnt++; }
        $prev = $dt;
    }
    if ($cnt > 0) { $avgIntervalDays = round($sum / $cnt, 1); }
}

// Enhanced frequency tracking
$frequencyStats = [
    'total_donations' => count($donations),
    'total_units' => array_sum(array_column($donations ?: [], 'units')),
    'avg_interval_days' => $avgIntervalDays,
    'last_donation' => !empty($donations) ? $donations[0]['donation_date'] : null,
    'first_donation' => !empty($donations) ? end($donations)['donation_date'] : null,
    'donations_this_year' => 0,
    'donations_last_6_months' => 0,
    'donations_last_3_months' => 0,
    'donation_frequency_category' => 'New Donor'
];

// Calculate donations by time periods
$currentYear = date('Y');
$sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
$threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));

if (is_array($donations)) {
    foreach ($donations as $donation) {
        $donationDate = $donation['donation_date'];
        if (date('Y', strtotime($donationDate)) == $currentYear) {
            $frequencyStats['donations_this_year']++;
        }
        if ($donationDate >= $sixMonthsAgo) {
            $frequencyStats['donations_last_6_months']++;
        }
        if ($donationDate >= $threeMonthsAgo) {
            $frequencyStats['donations_last_3_months']++;
        }
    }
}

// Determine donation frequency category
if ($frequencyStats['total_donations'] == 0) {
    $frequencyStats['donation_frequency_category'] = 'No Donations';
} elseif ($frequencyStats['total_donations'] == 1) {
    $frequencyStats['donation_frequency_category'] = 'First Time Donor';
} elseif ($frequencyStats['donations_last_6_months'] >= 3) {
    $frequencyStats['donation_frequency_category'] = 'Frequent Donor';
} elseif ($frequencyStats['donations_last_6_months'] >= 1) {
    $frequencyStats['donation_frequency_category'] = 'Regular Donor';
} elseif ($frequencyStats['total_donations'] > 1) {
    $frequencyStats['donation_frequency_category'] = 'Occasional Donor';
} else {
    $frequencyStats['donation_frequency_category'] = 'New Donor';
}

// Monthly donation pattern
$monthlyDonations = executeQuery("
    SELECT 
        DATE_FORMAT(donation_date, '%Y-%m') as month,
        COUNT(*) as donation_count,
        SUM(units) as total_units
    FROM donations 
    WHERE donor_id = ? 
    AND donation_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(donation_date, '%Y-%m')
    ORDER BY month DESC
", [$donorId]);

// Ensure $monthlyDonations is always an array
if ($monthlyDonations === false) {
    $monthlyDonations = [];
}

// Debug information
error_log("Donor ID: " . $donorId);
error_log("Number of donations found: " . count($donations));

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
    
    <!-- Universal Print Functions -->
    <script src="../../assets/js/universal-print.js"></script>

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
                <h2 class="page-title">Donation History</h2>
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

        <div class="dashboard-main p-4">
            <!-- Donation Statistics -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-danger mb-2">
                                <i class="bi bi-droplet-fill"></i>
                            </div>
                            <h3 class="h2 fw-bold"><?php echo count($donations); ?></h3>
                            <p class="text-muted">Total Donations</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-success mb-2">
                                <i class="bi bi-calendar-check-fill"></i>
                            </div>
                            <?php
                            $totalunits = 0;
                            foreach ($donations as $donation) {
                                $totalunits += $donation['units'];
                            }
                            ?>
                            <h3 class="h2 fw-bold"><?php echo $totalunits; ?></h3>
                            <p class="text-muted">Total units</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-primary mb-2">
                                <i class="bi bi-calendar-date-fill"></i>
                            </div>
                            <?php
                            $lastDonationDate = !empty($donations) ? date('M d, Y', strtotime($donations[0]['donation_date'])) : 'N/A';
                            ?>
                            <h3 class="h5 fw-bold"><?php echo $lastDonationDate; ?></h3>
                            <p class="text-muted">Last Donation</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-warning mb-2">
                                <i class="bi bi-heart-pulse-fill"></i>
                            </div>
                            <?php
                            $livesSaved = $totalunits * 3; // Each unit can save up to 3 lives
                            ?>
                            <h3 class="h2 fw-bold"><?php echo $livesSaved; ?></h3>
                            <p class="text-muted">Lives Saved</p>
                        </div>
                    </div>
                </div>
                <?php if ($avgIntervalDays !== null): ?>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-info mb-2">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <h3 class="h2 fw-bold"><?php echo $avgIntervalDays; ?></h3>
                            <p class="text-muted">Avg. Days Between Donations</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Frequency Tracking Section -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="card-title mb-0">
                                <i class="bi bi-graph-up me-2"></i>Donation Frequency Analysis
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h5 class="text-primary mb-1"><?php echo $frequencyStats['donation_frequency_category']; ?></h5>
                                        <small class="text-muted">Donor Category</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h5 class="text-success mb-1"><?php echo $frequencyStats['donations_this_year']; ?></h5>
                                        <small class="text-muted">This Year</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h5 class="text-info mb-1"><?php echo $frequencyStats['donations_last_6_months']; ?></h5>
                                        <small class="text-muted">Last 6 Months</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h5 class="text-warning mb-1"><?php echo $frequencyStats['donations_last_3_months']; ?></h5>
                                        <small class="text-muted">Last 3 Months</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($frequencyStats['avg_interval_days'] !== null): ?>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <h6><i class="bi bi-clock me-2"></i>Average Interval</h6>
                                        <p class="mb-0">You donate blood every <strong><?php echo $frequencyStats['avg_interval_days']; ?> days</strong> on average.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-success">
                                        <h6><i class="bi bi-trophy me-2"></i>Donation Impact</h6>
                                        <p class="mb-0">You've donated <strong><?php echo $frequencyStats['total_units']; ?> units</strong> of blood, potentially saving <strong><?php echo $frequencyStats['total_units'] * 3; ?> lives</strong>.</p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Donation History Table -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Your Donation History</h4>
                    
                </div>
                <div class="card-body">
                    <?php if (count($donations) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="donationsTable">
                                <thead>
                                    <tr>
                                        <th>Donation Date</th>
                                        <th>Blood Drive</th>
                                        <th>Organization</th>
                                        <th>Blood Type</th>
                                        <th>Units</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($donations as $donation): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                            <td>
                                                <?php if ($donation['blood_drive_title']): ?>
                                                    <?php echo $donation['blood_drive_title']; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Direct Donation</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $donation['organization_name']; ?></td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $donation['blood_type']; ?></span>
                                            </td>
                                            <td><?php echo $donation['units']; ?> unit(s)</td>
                                            <td>
                                                <?php
                                                $statusClass = 'success';
                                                if ($donation['status'] === 'Rejected') {
                                                    $statusClass = 'danger';
                                                } elseif ($donation['status'] === 'Processing') {
                                                    $statusClass = 'warning';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $donation['status']; ?></span>
                                            </td>
                                            <td>
                                                <a href="donation-details.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-droplet fs-1 d-block mb-3"></i>
                                <p>No donation records found.</p>
                                <a href="schedule-donation.php" class="btn btn-sm btn-primary mt-2">Schedule Your First Donation</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Yearly Summary -->
            <?php if (!empty($yearly)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h4 class="card-title mb-0">Yearly Summary</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th class="text-end">Donations</th>
                                    <th class="text-end">Units</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($yearly as $row): ?>
                                <tr>
                                    <td><?php echo (int)$row['yr']; ?></td>
                                    <td class="text-end"><?php echo (int)$row['donations_count']; ?></td>
                                    <td class="text-end"><?php echo (int)$row['total_units']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Donation Timeline -->
            <?php if (count($donations) > 0): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h4 class="card-title mb-0">Donation Timeline</h4>
                    </div>
                    <div class="card-body">
                        <div class="donation-timeline">
                            <?php foreach ($donations as $index => $donation): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot bg-<?php echo $index === 0 ? 'primary' : 'secondary'; ?>">
                                        <i class="bi bi-droplet-fill"></i>
                                    </div>
                                    <div class="timeline-date">
                                        <?php echo date('M d, Y', strtotime($donation['donation_date'])); ?>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <?php if ($donation['blood_drive_title']): ?>
                                                        <?php echo $donation['blood_drive_title']; ?>
                                                    <?php else: ?>
                                                        Direct Donation
                                                    <?php endif; ?>
                                                </h5>
                                                <p class="card-text">
                                                    <span class="badge bg-danger me-2"><?php echo $donation['blood_type']; ?></span>
                                                    <span class="badge bg-success me-2"><?php echo $donation['units']; ?> unit(s)</span>
                                                    <span class="badge bg-info"><?php echo $donation['organization_name']; ?></span>
                                                </p>
                                                <?php if ($donation['notes']): ?>
                                                    <p class="card-text text-muted small"><?php echo $donation['notes']; ?></p>
                                                <?php endif; ?>
                                                <a href="donation-details.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>

<style>
    .donation-timeline {
        position: relative;
        padding-left: 40px;
    }

    .donation-timeline::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 15px;
        width: 2px;
        background-color: #dee2e6;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 30px;
    }

    .timeline-dot {
        position: absolute;
        left: -40px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        z-index: 1;
    }

    .timeline-date {
        font-weight: bold;
        margin-bottom: 10px;
    }

    .timeline-content {
        padding-bottom: 10px;
    }
    .dashboard-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0;
        margin-left: 300px; /* Sidebar width */
        padding-top: 100px; /* Space for fixed header */
        position: relative;
        background-color: #f8f9fa;
    }

    .dashboard-header {
        background-color: #fff;
        border-bottom: 1px solid #e9ecef;
        position: fixed;
        top: 0;
        left: 300px; /* Position after sidebar */
        right: 0;
        z-index: 1021;
        height: auto;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        padding: 1rem 1.5rem;
        overflow: visible;
    }
    
    .dashboard-header .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        position: relative;
    }

    .dashboard-header .page-title {
        color: #2c3e50;
        margin: 0;
        font-weight: 600;
        font-size: 1.25rem;
    }
    
    .dashboard-header .header-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        position: relative;
        z-index: 1021;
    }
    
    .dashboard-header .dropdown {
        position: relative;
        z-index: 1021;
    }
    
    .dashboard-header .dropdown-menu {
        position: absolute !important;
        right: 0 !important;
        left: auto !important;
        top: 100% !important;
        margin-top: 0.5rem !important;
        z-index: 1050 !important;
        transform: none !important;
    }

    .dashboard-header .breadcrumb {
        margin: 0;
        padding: 0;
        background: transparent;
        font-size: 0.875rem;
    }

    .dashboard-header .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        font-size: 1.1rem;
        line-height: 1;
        vertical-align: middle;
        color: #6c757d;
    }

    .dashboard-header .breadcrumb-item a {
        color: #dc3545;
        text-decoration: none;
    }

    .dashboard-header .breadcrumb-item a:hover {
        color: #b02a37;
    }

    .dashboard-header .breadcrumb-item.active {
        color: #6c757d;
    }

    .dashboard-header .btn {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
    }

    .dashboard-header .btn:hover {
        transform: translateY(-1px);
    }

    @media (max-width: 991.98px) {
        .dashboard-content {
            margin-left: 0;
            padding-top: 100px; /* Space for fixed header on mobile */
        }
        
        .dashboard-header {
            left: 0;
            padding: 1rem;
        }
        
        .dashboard-header .header-content {
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .dashboard-header .page-title {
            font-size: 1.1rem;
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .dashboard-header .header-actions {
            gap: 0.5rem;
        }
        
        .dashboard-header .header-actions .btn {
            padding: 0.5rem;
        }
        
        .dashboard-header .header-actions span:not(.badge) {
            display: none;
        }
    }
    
    @media (max-width: 575.98px) {
        .dashboard-header {
            padding: 0.75rem 1rem;
        }
        
        .dashboard-header .header-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<!-- Include print utilities -->
<script src="../../assets/js/print-utils.js"></script>