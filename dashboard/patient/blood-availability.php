<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../login.php?role=patient");
    exit;
}

// Set page title
$pageTitle = "Blood Availability - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get blood inventory status
$bloodInventory = executeQuery("
    SELECT blood_type, SUM(units) as available_units, organization_type, organization_id
    FROM blood_inventory
    WHERE status = 'Available'
    GROUP BY blood_type, organization_type, organization_id
    ORDER BY blood_type
");

// Get blood banks
$bloodBanks = executeQuery("
    SELECT * FROM blood_banks
");

if (!is_array($bloodInventory)) {
    $bloodInventory = [];
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <!-- Universal Print Functions -->
    <script src="../../assets/js/universal-print.js"></script>

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
    
    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
    
    <style>
    /* Red Theme for Blood Availability Page */
    :root {
        --patient-primary: #DC2626; /* Red */
        --patient-primary-dark: #B91C1C;
        --patient-primary-light: #EF4444;
        --patient-accent: #F87171;
        --patient-accent-dark: #DC2626;
        --patient-accent-light: #FEE2E2;
        --patient-cream: #FEF2F2;
        --patient-cream-light: #FEE2E2;
    }
    
    /* Patient Dashboard Header Styles */
    .dashboard-content {
        margin-left: 280px;
        padding-top: 100px; /* Space for fixed header */
        position: relative;
        background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 50%, #FECACA 100%);
        overflow: hidden;
    }

    .dashboard-content::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(220, 38, 38, 0.1) 0%, transparent 70%);
        border-radius: 50%;
        animation: float 20s ease-in-out infinite;
        z-index: 0;
    }

    .dashboard-content::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -5%;
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(185, 28, 28, 0.08) 0%, transparent 70%);
        border-radius: 50%;
        animation: float 25s ease-in-out infinite reverse;
        z-index: 0;
    }

    @keyframes float {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50% { transform: translate(30px, -30px) rotate(180deg); }
    }

    .dashboard-main {
        position: relative;
        z-index: 1;
    }

    .dashboard-header {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%); /* Red gradient */
        color: white;
        border-bottom: none;
        position: fixed;
        top: 0;
        left: 280px; /* Position after sidebar */
        right: 0;
        z-index: 1021;
        height: 100px;
        box-shadow: 0 4px 20px rgba(220, 38, 38, 0.3);
        padding: 0 2rem;
        overflow: visible;
        display: flex;
        align-items: center;
    }

    .dashboard-header .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
    
    .dashboard-header .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        position: relative;
    }
    
    .dashboard-header .page-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: white;
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
        min-width: 200px;
    }

    .dashboard-header .btn-outline-secondary {
        border-color: rgba(255, 255, 255, 0.3) !important;
        color: white !important;
        background: rgba(255, 255, 255, 0.1) !important;
        padding: 0.625rem 1rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
    }

    .dashboard-header .btn-outline-secondary:hover {
        background: rgba(255, 255, 255, 0.2) !important;
        border-color: rgba(255, 255, 255, 0.4) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .dashboard-header .btn-outline-secondary span {
        color: white !important;
    }

    .dashboard-header .avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.5rem;
    }

    .dashboard-header .avatar i {
        color: white;
        font-size: 1.25rem;
    }

    /* Notification Bell in Header */
    .dashboard-header .notification-bell .btn {
        border-color: rgba(255, 255, 255, 0.3) !important;
        color: white !important;
        background: rgba(255, 255, 255, 0.1) !important;
        padding: 0.625rem 1rem;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .dashboard-header .notification-bell .btn:hover {
        background: rgba(255, 255, 255, 0.2) !important;
        border-color: rgba(255, 255, 255, 0.4) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .dashboard-header .notification-bell .badge {
        background: #EF4444 !important;
        color: white;
    }

    .dashboard-header .notification-bell .btn i {
        color: white !important;
    }
    
    /* Text Colors - Red Theme */
    .text-danger {
        color: #DC2626 !important;
    }
    
    .text-success {
        color: #DC2626 !important;
    }
    
    .text-warning {
        color: #F59E0B !important;
    }
    
    .text-primary {
        color: #DC2626 !important;
    }
    
    .page-title {
        color: white !important;
    }
    
    .card h3, .card h4, .card h5 {
        color: #1F2937;
    }
    
    .card p {
        color: #4B5563;
    }
    
    .table {
        color: #1F2937;
    }
    
    .table th {
        color: #374151;
        font-weight: 600;
    }

    /* White text and borders in header */
.dashboard-header .btn-outline-secondary {
    border-color: white !important;
    color: white !important;
}

.dashboard-header .btn-outline-secondary:hover {
    border-color: white !important;
    color: white !important;
    background: rgba(255, 255, 255, 0.2) !important;
}

.dashboard-header .btn-outline-secondary span {
    color: white !important;
}

.dashboard-header .btn-outline-secondary i {
    color: white !important;
}

.dashboard-header #notificationDropdown {
    border-color: white !important;
    color: white !important;
}

.dashboard-header #notificationDropdown i {
    color: white !important;
}

.dashboard-header #userDropdown {
    border-color: white !important;
    color: white !important;
}

.dashboard-header #userDropdown span {
    color: white !important;
}

.dashboard-header #userDropdown i {
    color: white !important;
}

.dashboard-header .avatar i {
    color: white !important;
}
    
    /* Critical blood status in availability tables should remain red */
    .table .badge.bg-danger,
    table .badge.bg-danger {
        background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%) !important;
        color: white;
    }
    
    /* Other danger badges use red */
    .badge.bg-danger:not(.table .badge.bg-danger):not(table .badge.bg-danger) {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
        color: white;
    }
    
    .badge.bg-success {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
        color: white;
    }
    
    .badge.bg-warning {
        background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%) !important;
        color: white;
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
        border: none;
        color: white;
    }
    
    .btn-danger:hover {
        background: linear-gradient(135deg, #B91C1C 0%, #991B1B 100%) !important;
        color: white;
    }
    
    .btn-outline-danger {
        border: 2px solid #DC2626 !important;
        color: #DC2626 !important;
    }
    
    .btn-outline-danger:hover {
        background: #DC2626 !important;
        color: white !important;
    }

    /* Enhanced Card Styles */
    .card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
    }

    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #DC2626, #B91C1C, #991B1B);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.4s ease;
    }

    .card:hover::before {
        transform: scaleX(1);
    }

    .card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 16px 40px rgba(220, 38, 38, 0.2);
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

    /* Responsive adjustments */
    @media (max-width: 991.98px) {
        .dashboard-content {
            margin-left: 0;
            padding-top: 100px;
        }
        
        .dashboard-header {
            left: 0;
            padding: 1rem;
            height: auto;
        }
    }

    @media (max-width: 767.98px) {
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
</head>
<body>

<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <div class="header-content">
                <h2 class="page-title">Blood Availability</h2>
                <div class="header-actions">
                    <?php include_once '../../includes/notification_bell.php'; ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar me-2">
                                <i class="bi bi-person-circle fs-4"></i>
                            </div>
                            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Patient'); ?></span>
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
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Blood Inventory</h4>
                    <div>
                       
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h5 class="mb-3">Red Cross Blood Bank</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Blood Type</th>
                                            <th>Available units</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                        $inventoryMap = [];

                                        // Create a map of blood type to units
                                        foreach ($bloodInventory as $item) {
                                            if ($item['organization_type'] === 'redcross') {
                                                $inventoryMap[$item['blood_type']] = $item['available_units'];
                                            }
                                        }

                                        foreach ($bloodTypes as $bloodType):
                                            $units = isset($inventoryMap[$bloodType]) ? $inventoryMap[$bloodType] : 0;
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $bloodType; ?></strong></td>
                                                <td><?php echo $units; ?> units</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <h5 class="mb-3">Negros First Blood Bank</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Blood Type</th>
                                            <th>Available units</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $inventoryMap = [];

                                        // Create a map of blood type to units
                                        foreach ($bloodInventory as $item) {
                                            if ($item['organization_type'] === 'negrosfirst') {
                                                $inventoryMap[$item['blood_type']] = $item['available_units'];
                                            }
                                        }

                                        foreach ($bloodTypes as $bloodType):
                                            $units = isset($inventoryMap[$bloodType]) ? $inventoryMap[$bloodType] : 0;
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $bloodType; ?></strong></td>
                                                <td><?php echo $units; ?> units</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Need blood?</strong> If you need blood, please <a href="request-blood.php" class="alert-link">submit a blood request</a> or contact the blood bank directly.
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="card-title mb-0">Blood Type Compatibility</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr class="table-primary">
                                            <th>Blood Type</th>
                                            <th>Can Donate To</th>
                                            <th>Can Receive From</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>A+</strong></td>
                                            <td>A+, AB+</td>
                                            <td>A+, A-, O+, O-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>A-</strong></td>
                                            <td>A+, A-, AB+, AB-</td>
                                            <td>A-, O-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>B+</strong></td>
                                            <td>B+, AB+</td>
                                            <td>B+, B-, O+, O-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>B-</strong></td>
                                            <td>B+, B-, AB+, AB-</td>
                                            <td>B-, O-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>AB+</strong></td>
                                            <td>AB+</td>
                                            <td>All Blood Types</td>
                                        </tr>
                                        <tr>
                                            <td><strong>AB-</strong></td>
                                            <td>AB+, AB-</td>
                                            <td>A-, B-, AB-, O-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>O+</strong></td>
                                            <td>A+, B+, AB+, O+</td>
                                            <td>O+, O-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>O-</strong></td>
                                            <td>All Blood Types</td>
                                            <td>O-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="card-title mb-0">Blood Bank Contact Information</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex mb-4">
                                <div class="me-3 text-danger">
                                    <i class="bi bi-hospital-fill fs-3"></i>
                                </div>
                                <div>
                                    <h5>Philippine Red Cross - Bacolod Chapter</h5>
                                    <p class="mb-1"><i class="bi bi-geo-alt me-2"></i>10th St, Bacolod CIty, Philippines</p>
                                    <p class="mb-1"><i class="bi bi-telephone me-2"></i><strong>Blood Services:</strong> (034- 458-9798) or 09683292625</p>
                                    <p class="mb-1"><i class="bi bi-telephone me-2"></i><strong>Admin:</strong> (034-458-4930)</p>
                                    <p class="mb-1"><i class="bi bi-envelope me-2"></i>negros.occidental@redcross.org.ph</p>
                                    <p class="mb-0"><i class="bi bi-clock me-2"></i>Always Open</p>
                                </div>
                            </div>

                            <div class="d-flex">
                                <div class="me-3 text-danger">
                                    <i class="bi bi-building-fill fs-3"></i>
                                </div>
                                <div>
                                    <h5>Negros First Provincial Blood Center</h5>
                                    <p class="mb-1"><i class="bi bi-geo-alt me-2"></i>Abad Santos Street, Bacolod, 6100 Negros Occidental, Philippines</p>
                                    <p class="mb-1"><i class="bi bi-telephone me-2"></i>(034) 433 0313</p>
                                    
                                    <p class="mb-0"><i class="bi bi-clock me-2"></i>Always Open</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Print Utilities -->
<script src="../../assets/js/print-utils.js"></script>


</body>
</html>
