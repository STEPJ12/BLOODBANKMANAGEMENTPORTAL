<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../../login.php?role=donor");
    exit;
}

// Set page title
$pageTitle = "Blood Drives - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get donor information
$donorId = $_SESSION['user_id'];
$donor = getRow("SELECT * FROM donor_users WHERE id = ?", [$donorId]);

// Get upcoming blood drives
$upcomingDrives = executeQuery("
    SELECT bd.*, bu.name as barangay_name,
    CASE
        WHEN bd.organization_type = 'redcross' THEN 'Red Cross'
        WHEN bd.organization_type = 'negrosfirst' THEN 'Negros First'
        ELSE bd.organization_type
    END as organization_name
    FROM blood_drives bd
    JOIN barangay_users bu ON bd.barangay_id = bu.id
    WHERE bd.date >= CURDATE() AND bd.status = 'Scheduled'
    ORDER BY bd.date ASC
");

// Get past blood drives
$pastDrives = executeQuery("
    SELECT bd.*, bu.name as barangay_name,
    CASE
        WHEN bd.organization_type = 'redcross' THEN 'Red Cross'
        WHEN bd.organization_type = 'negrosfirst' THEN 'Negros First'
        ELSE bd.organization_type
    END as organization_name
    FROM blood_drives bd
    JOIN barangay_users bu ON bd.barangay_id = bu.id
    WHERE bd.date < CURDATE() OR bd.status = 'Completed'
    ORDER BY bd.date DESC
    LIMIT 10
");


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
    
    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
    <style>
    .dashboard-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0;
        margin-left: 300px;
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
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #2c3e50;
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
    
    @media (max-width: 991.98px) {
        .dashboard-content {
            margin-left: 0;
            padding-top: 100px; /* Space for fixed header on mobile */
        }
        .dashboard-header {
            left: 0;
            padding: 1rem;
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
                <h2 class="page-title">Blood Drives</h2>
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
            <!-- Search and Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form action="" method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Search by title or location...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="barangay" class="form-label">Barangay</label>
                            <select class="form-select" id="barangay" name="barangay">
                                <option value="">All Barangays</option>
                                <?php
                                $barangays = executeQuery("SELECT id, name FROM barangay_users ORDER BY name");
                                foreach ($barangays as $barangay) {
                                    echo "<option value='{$barangay['id']}'>{$barangay['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date" class="form-label">Date Range</label>
                            <select class="form-select" id="date" name="date">
                                <option value="">All Dates</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Upcoming Blood Drives -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h4 class="card-title mb-0">Upcoming Blood Drives</h4>
                </div>
                <div class="card-body">
                    <?php if (count($upcomingDrives) > 0): ?>
                        <div class="row g-4">
                            <?php foreach ($upcomingDrives as $drive): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <h5 class="card-title mb-0"><?php echo $drive['title']; ?></h5>
                                                <span class="badge bg-primary"><?php echo date('M d, Y', strtotime($drive['date'])); ?></span>
                                            </div>
                                            <p class="card-text text-muted mb-3">
                                                <i class="bi bi-geo-alt me-1"></i> <?php echo $drive['location']; ?>, <?php echo $drive['barangay_name']; ?><br>
                                                <i class="bi bi-clock me-1"></i> <?php echo date('g:i A', strtotime($drive['start_time'])); ?> - <?php echo date('g:i A', strtotime($drive['end_time'])); ?><br>
                                                <i class="bi bi-hospital me-1"></i> <?php echo $drive['organization_name']; ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <a href="blood-drive-details.php?id=<?php echo $drive['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-info-circle me-1"></i> Details
                                                </a>
                                                <a href="schedule-donation.php?drive_id=<?php echo $drive['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-calendar-plus me-1"></i> Schedule
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                                <p>No upcoming blood drives found.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Past Blood Drives -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h4 class="card-title mb-0">Past Blood Drives</h4>
                </div>
                <div class="card-body">
                    <?php if (count($pastDrives) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Title</th>
                                        <th>Location</th>
                                        <th>Barangay</th>
                                        <th>Organization</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pastDrives as $drive): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($drive['date'])); ?></td>
                                            <td><?php echo $drive['title']; ?></td>
                                            <td><?php echo $drive['location']; ?></td>
                                            <td><?php echo $drive['barangay_name']; ?></td>
                                            <td><?php echo $drive['organization_name']; ?></td>
                                            <td>
                                                <span class="badge bg-secondary">Completed</span>
                                            </td>
                                            <td>
                                                <a href="blood-drive-details.php?id=<?php echo $drive['id']; ?>" class="btn btn-sm btn-outline-primary">
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
                                <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                                <p>No past blood drives found.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>

<?php include_once '../../includes/footer.php'; ?>
