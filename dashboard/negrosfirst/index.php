<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

$isDashboard = true; // Enable notification dropdown
// Set organization ID for Negros First
$negrosFirstId = $_SESSION['user_id'];

// Fetch historical blood request data
$historicalRequests = executeQuery("
    SELECT 
        br.blood_type,
        DATE_FORMAT(br.request_date, '%Y-%m') as month,
        COUNT(*) as request_count,
        SUM(br.units_requested) as total_units_requested
    FROM blood_requests br
    WHERE br.organization_type = 'negrosfirst' 
    AND br.organization_id = ?
    AND br.request_date >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
    GROUP BY br.blood_type, DATE_FORMAT(br.request_date, '%Y-%m')
    ORDER BY br.blood_type, month
", [$negrosFirstId]);

// Fetch historical donation data
$historicalDonations = executeQuery("
    SELECT 
        d.blood_type,
        DATE_FORMAT(d.donation_date, '%Y-%m') as month,
        COUNT(*) as donation_count,
        SUM(d.units) as total_units_donated
    FROM donations d
    WHERE d.organization_type = 'negrosfirst' 
    AND d.organization_id = ?
    AND d.donation_date >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
    GROUP BY d.blood_type, DATE_FORMAT(d.donation_date, '%Y-%m')
    ORDER BY d.blood_type, month
", [$negrosFirstId]);

// Inventory snapshot (available units by blood type)
$inventory = executeQuery("
    SELECT blood_type, COALESCE(SUM(units),0) as units
    FROM blood_inventory
    WHERE organization_type = 'negrosfirst' AND status = 'Available'
    GROUP BY blood_type
    ORDER BY blood_type
");

// Recent blood requests (latest 8)
$recentRequests = executeQuery("
    SELECT br.id, br.patient_id, br.blood_type, br.units_requested, br.status, br.request_date
    FROM blood_requests br
    WHERE br.organization_type = 'negrosfirst'
    ORDER BY br.request_date DESC
    LIMIT 8
");

// Process the data for predictions
$months = [];
$chartData = [];
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Initialize the past 6 months and future 6 months
for ($i = -6; $i <= 6; $i++) {
    $month = date('Y-m', strtotime("$i months"));
    $months[] = $month;
}

// Initialize chartData with zeros
foreach ($bloodTypes as $bt) {
    $chartData[$bt] = array_fill_keys($months, 0);
}

// Process historical request data and calculate predictions
$requestTrends = [];
$monthlyAverages = [];
$seasonalFactors = [];

// Initialize arrays for each blood type
foreach ($bloodTypes as $bt) {
    $requestTrends[$bt] = [];
    $monthlyAverages[$bt] = [];
    $seasonalFactors[$bt] = [];
}

// Process historical data
foreach ($historicalRequests as $request) {
    $bloodType = $request['blood_type'];
    $month = $request['month'];
    
    // Store the actual request data
    $chartData[$bloodType][$month] = (int)$request['total_units_requested'];
    
    // Store for trend analysis
    if (!isset($requestTrends[$bloodType][$month])) {
        $requestTrends[$bloodType][$month] = 0;
    }
    $requestTrends[$bloodType][$month] += (int)$request['total_units_requested'];
}

// Calculate monthly averages and seasonal factors
foreach ($bloodTypes as $bt) {
    if (!empty($requestTrends[$bt])) {
        // Calculate monthly averages
        foreach ($months as $month) {
            if (isset($requestTrends[$bt][$month])) {
                $monthlyAverages[$bt][] = $requestTrends[$bt][$month];
            }
        }
        
        // Calculate seasonal factors (if we have enough historical data)
        if (count($monthlyAverages[$bt]) >= 12) {
            $avg = array_sum($monthlyAverages[$bt]) / count($monthlyAverages[$bt]);
            foreach ($monthlyAverages[$bt] as $value) {
                $seasonalFactors[$bt][] = $value / $avg;
            }
        }
    }
}

// Generate predictions for each blood type
foreach ($bloodTypes as $bt) {
    if (!empty($monthlyAverages[$bt])) {
        // Calculate baseline prediction using weighted moving average
        $weights = [0.1, 0.15, 0.2, 0.25, 0.3];
        $weightedSum = 0;
        $totalWeight = 0;
        
        $recentData = array_slice($monthlyAverages[$bt], -count($weights));
        for ($i = 0; $i < count($recentData); $i++) {
            $weightedSum += $recentData[$i] * $weights[$i];
            $totalWeight += $weights[$i];
        }
        
        $baselinePrediction = $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
        
        // Generate predictions for future months
        for ($i = 6; $i < count($months); $i++) {
            $monthsAhead = $i - 5;
            
            // Apply growth factor (5% annual growth rate)
            $growthFactor = 1 + (0.05 * $monthsAhead / 12);
            
            // Apply seasonal factor if available
            $seasonalFactor = 1.0;
            if (!empty($seasonalFactors[$bt])) {
                $seasonalIndex = ($i - 6) % count($seasonalFactors[$bt]);
                $seasonalFactor = $seasonalFactors[$bt][$seasonalIndex];
            }
            
            // Calculate final prediction
            $prediction = max(0, round($baselinePrediction * $seasonalFactor * $growthFactor));
            
            // Store prediction
            $chartData[$bt][$months[$i]] = $prediction;
        }
    }
}


// Set dashboard flag
$isDashboard = true;
$pageTitle = "Negros First Provincial Dashboard - Blood Bank Portal";

// Get total blood requests
$totalRequests = getRow("SELECT COUNT(*) as count FROM blood_requests WHERE organization_type = 'negrosfirst'");
$totalRequests = $totalRequests ? $totalRequests['count'] : 0;

// Get total donations
$totalDonations = getRow("SELECT COUNT(*) as count FROM donations WHERE organization_type = 'negrosfirst'");
$totalDonations = $totalDonations ? $totalDonations['count'] : 0;

// Requests by status
$pendingCount   = getCount("SELECT COUNT(*) FROM blood_requests WHERE organization_type='negrosfirst' AND status='Pending'");
$approvedCount  = getCount("SELECT COUNT(*) FROM blood_requests WHERE organization_type='negrosfirst' AND status='Approved'");
$completedCount = getCount("SELECT COUNT(*) FROM blood_requests WHERE organization_type='negrosfirst' AND status='Completed'");
$rejectedCount  = getCount("SELECT COUNT(*) FROM blood_requests WHERE organization_type='negrosfirst' AND status='Rejected'");


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
<style>
    :root {
        --primary-color: #1a365d;
        --secondary-color: #2d3748;
        --accent-color: #e53e3e;
        --success-color: #38a169;
        --warning-color: #d69e2e;
        --info-color: #3182ce;
        --light-bg: #f7fafc;
        --white: #ffffff;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --gray-900: #111827;
        --border-radius: 12px;
        --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --box-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .dashboard-container {
        background: var(--light-bg);
        min-height: 100vh;
    }

    .dashboard-content {
        margin-left: 280px;
        transition: var(--transition);
    }

    .dashboard-header {
        background: var(--white);
        border-bottom: 1px solid var(--gray-200);
        box-shadow: var(--box-shadow);
    }

    .kpi-card {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--gray-200);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    }

    .kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--box-shadow-lg);
    }

    .kpi-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin: 0 auto 1rem;
        transition: var(--transition);
    }

    .kpi-card:hover .kpi-icon {
        transform: scale(1.1);
    }

    .kpi-number {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .kpi-label {
        font-size: 0.875rem;
        color: var(--gray-600);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .chart-container {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--gray-200);
        margin-bottom: 2rem;
    }

    .chart-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--gray-800);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    @media (max-width: 768px) {
        .dashboard-content {
            margin-left: 0;
        }
    }
</style>
<body>

<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h4 mb-1 fw-bold">Negros First Provincial Dashboard</h2>
                    <p class="text-muted mb-0">Welcome back! Here's your blood bank overview.</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    
                </div>
            </div>
        </div>

        <!-- Notification Bell -->
        <?php
        // Get notification count
        $notifCount = getCount("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_role = 'negrosfirst' AND is_read = 0", [$negrosFirstId]);
        ?>
        <style>
            .nf-topbar {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1050;
            }
            .nf-dropdown {
                position: absolute;
                top: calc(100% + 8px);
                right: 0;
                width: 350px;
                max-height: 400px;
                margin-top: 0;
                border-radius: 8px;
                background: white;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
                z-index: 1060;
            }
            .nf-dropdown.show { 
                display: block !important; 
                animation: slideDown 0.2s ease-out;
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
            #nfBellBtn {
                cursor: pointer;
                transition: all 0.2s ease;
                border: none;
                padding: 8px 12px;
            }
            #nfBellBtn:hover {
                background-color: #f8f9fa !important;
                transform: scale(1.05);
            }
            #nfBellBtn:focus {
                outline: 2px solid #0d6efd;
                outline-offset: 2px;
            }
            .nf-notif-item { 
                white-space: normal; 
                cursor: pointer;
            }
            .nf-notif-item:hover {
                background-color: #f8f9fa;
            }
            @media (max-width: 991.98px) { 
                .nf-topbar { 
                    top: 12px; 
                    right: 15px; 
                }
                .nf-dropdown {
                    width: 300px;
                }
            }
        </style>
        <div class="nf-topbar">
            <button id="nfBellBtn" type="button" class="btn btn-light position-relative shadow-sm">
                <i class="bi bi-bell"></i>
                <span id="nfBellBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: <?php echo ((int)$notifCount>0)?'inline':'none'; ?>;">
                    <?php echo (int)$notifCount; ?>
                </span>
            </button>
            <div id="nfDropdown" class="nf-dropdown card shadow-sm" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Notifications</span>
                    <a class="small text-decoration-none" href="notifications.php">View All</a>
                </div>
                <div id="nfList" class="list-group list-group-flush" style="max-height: 360px; overflow:auto;">
                    <?php
                    $notifications = executeQuery("
                        SELECT * FROM notifications 
                        WHERE user_id = ? AND user_role = 'negrosfirst' 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ", [$negrosFirstId]);
                    
                    if ($notifications && count($notifications) > 0):
                        foreach ($notifications as $notif):
                    ?>
                        <a href="notifications.php" class="list-group-item list-group-item-action nf-notif-item <?php echo !$notif['is_read'] ? 'fw-bold' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($notif['title']); ?></h6>
                                <small><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></small>
                            </div>
                            <p class="mb-1 small"><?php echo htmlspecialchars(substr($notif['message'], 0, 100)) . (strlen($notif['message']) > 100 ? '...' : ''); ?></p>
                        </a>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <div class="list-group-item text-center text-muted">
                            <i class="bi bi-bell-slash fs-4 d-block mb-2"></i>
                            No notifications
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dashboard-main p-4">
            <!-- KPI Cards -->
            <div class="row g-4 mb-4">
                <div class="col-12 col-md-3">
                    <div class="kpi-card h-100 text-center">
                        <div class="kpi-icon" style="background: linear-gradient(135deg, var(--info-color), #2b6cb0); color: white;">
                            <i class="bi bi-clipboard-pulse"></i>
                        </div>
                        <div class="kpi-number"><?php echo $totalRequests; ?></div>
                        <div class="kpi-label">Total Requests</div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="kpi-card h-100 text-center">
                        <div class="kpi-icon" style="background: linear-gradient(135deg, var(--success-color), #2f855a); color: white;">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div class="kpi-number"><?php echo $approvedCount; ?></div>
                        <div class="kpi-label">Approved</div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="kpi-card h-100 text-center">
                        <div class="kpi-icon" style="background: linear-gradient(135deg, var(--warning-color), #b7791f); color: white;">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="kpi-number"><?php echo $pendingCount; ?></div>
                        <div class="kpi-label">Pending</div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="kpi-card h-100 text-center">
                        <div class="kpi-icon" style="background: linear-gradient(135deg, var(--accent-color), #c53030); color: white;">
                            <i class="bi bi-droplet"></i>
                        </div>
                        <div class="kpi-number"><?php echo $totalDonations; ?></div>
                        <div class="kpi-label">Total Donations</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Inventory Snapshot -->
                <div class="col-12 col-xl-6">
                    <div class="chart-container h-100">
                        <div class="chart-title">
                            <i class="bi bi-box-seam"></i>
                            Blood Inventory Status
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Blood Type</th>
                                        <th class="text-end">Available Units</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $invMap = [];
                                    foreach ($inventory ?? [] as $row) { $invMap[$row['blood_type']] = (int)$row['units']; }
                                    foreach ($bloodTypes as $bt):
                                        $u = $invMap[$bt] ?? 0;
                                        $badge = $u > 20 ? 'success' : ($u > 10 ? 'warning' : 'danger');
                                        $statusText = $u > 20 ? 'Good' : ($u > 10 ? 'Low' : 'Critical');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="blood-type-indicator me-3" style="width: 12px; height: 12px; border-radius: 50%; background: var(--<?php echo $badge; ?>-color);"></div>
                                                <span class="fw-semibold"><?php echo $bt; ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold"><?php echo $u; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $badge; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <a href="enhanced-inventory.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-right me-1"></i>View Full Inventory
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-12 col-xl-6">
                    <div class="chart-container h-100">
                        <div class="chart-title">
                            <i class="bi bi-lightning-charge"></i>
                            Quick Actions
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="blood-requests.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-4">
                                    <i class="bi bi-clipboard-pulse fs-2 mb-2"></i>
                                    <span class="fw-semibold">Blood Requests</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="donations.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center py-4">
                                    <i class="bi bi-droplet fs-2 mb-2"></i>
                                    <span class="fw-semibold">Donations</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="enhanced-inventory.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center py-4">
                                    <i class="bi bi-box-seam fs-2 mb-2"></i>
                                    <span class="fw-semibold">Inventory</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="reports.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center py-4">
                                    <i class="bi bi-graph-up fs-2 mb-2"></i>
                                    <span class="fw-semibold">Reports</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Requests -->
            <div class="chart-container mt-4">
                <div class="chart-title">
                    <i class="bi bi-list-ul"></i>
                    Recent Blood Requests
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentRequests)): foreach ($recentRequests as $r): ?>
                            <tr>
                                <td><span class="fw-bold text-primary">#<?php echo (int)$r['id']; ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2" style="width: 32px; height: 32px; background: var(--gray-200); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-person-fill text-muted"></i>
                                        </div>
                                        <span class="fw-semibold">Patient #<?php echo (int)$r['patient_id']; ?></span>
                                    </div>
                                </td>
                                <td><span class="text-muted"><?php echo date('M d, Y', strtotime($r['request_date'])); ?></span></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                    No recent requests found.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href="blood-requests.php" class="btn btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>View All Requests
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
                            </body>
                            </html>

<script>
    // Add smooth animations and interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Animate KPI cards on load
        const kpiCards = document.querySelectorAll('.kpi-card');
        kpiCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Add hover effects to quick action buttons
        const quickActions = document.querySelectorAll('.btn-outline-primary, .btn-outline-success, .btn-outline-info, .btn-outline-warning');
        quickActions.forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            });
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });


    // Notification dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        const bellBtn = document.getElementById('nfBellBtn');
        const dropdown = document.getElementById('nfDropdown');
        
        if (bellBtn && dropdown) {
            // Toggle dropdown on bell click
            bellBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Toggle show class
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    dropdown.style.display = 'none';
                } else {
                    dropdown.classList.add('show');
                    dropdown.style.display = 'block';
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                const topbar = document.querySelector('.nf-topbar');
                if (topbar && !topbar.contains(e.target)) {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                        dropdown.style.display = 'none';
                    }
                }
            });
            
            // Close dropdown on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    dropdown.style.display = 'none';
                }
            });
            
            // Prevent dropdown from closing when clicking inside it
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
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

<?php include_once '../../includes/footer.php'; ?>
