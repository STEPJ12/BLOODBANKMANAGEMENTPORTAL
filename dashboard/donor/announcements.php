<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../../login.php?role=donor");
    exit;
}

// Set page title
$pageTitle = "Announcements - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get donor information
$donorId = $_SESSION['user_id'];
$donor = getRow("SELECT * FROM donor_users WHERE id = ?", [$donorId]);
$donorBarangayId = $donor['barangay_id'] ?? null;

// Get announcements: 
// - All active announcements from redcross and negrosfirst (general)
// - All barangay announcements (show all barangay announcements to donors)
$announcements = executeQuery("
    SELECT a.*,
           CASE 
               WHEN a.organization_type = 'redcross' THEN 'Red Cross'
               WHEN a.organization_type = 'negrosfirst' THEN 'Negros First'
               WHEN a.organization_type = 'barangay' THEN 
                   COALESCE((SELECT name FROM barangay_users WHERE id = a.created_by), 'Barangay')
               ELSE 'System'
           END as organization_name
    FROM announcements a
    WHERE a.status = 'Active'
      AND (
          a.organization_type IN ('redcross', 'negrosfirst')
          OR a.organization_type = 'barangay'
      )
      AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
    ORDER BY a.created_at DESC
    LIMIT 20
");

// Initialize as empty array if query failed
if ($announcements === false) {
    $announcements = [];
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
                <h2 class="page-title">Announcements</h2>
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

        <div class="dashboard-main">
            <div class="container-fluid">

                <?php if (count($announcements) > 0): ?>
                    <!-- Announcements Grid -->
                    <div class="announcements-grid">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-card">
                                <div class="card-header-section">
                                    <div class="priority-badge priority-<?php echo isset($announcement['priority']) ? $announcement['priority'] : 'none'; ?>">
                                        <i class="bi bi-flag-fill me-1"></i>
                                        <?php echo isset($announcement['priority']) ? ucfirst($announcement['priority']) : 'No Priority'; ?>
                                    </div>
                                    <div class="organization-badge organization-<?php echo $announcement['organization_type']; ?>">
                                        <i class="bi bi-building me-1"></i>
                                        <?php echo htmlspecialchars($announcement['organization_name']); ?>
                                    </div>
                                </div>
                                
                                <div class="card-content">
                                    <h3 class="announcement-title">
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </h3>
                                    
                                    <div class="announcement-text">
                                        <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                    </div>
                                    
                                    <?php if (!empty($announcement['link'])): ?>
                                        <div class="action-buttons">
                                            <a href="<?php echo htmlspecialchars($announcement['link']); ?>" 
                                               class="btn btn-primary btn-learn-more" 
                                               target="_blank">
                                                <i class="bi bi-arrow-right me-2"></i>Learn More
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer-section">
                                    <div class="timeline-info">
                                        <div class="timeline-item">
                                            <i class="bi bi-calendar-plus text-success"></i>
                                            <span>Posted: <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></span>
                                        </div>
                                       
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="bi bi-megaphone"></i>
                        </div>
                        <h3>No Announcements Available</h3>
                        <p>There are no active announcements at the moment. Check back later for updates!</p>
                        <div class="empty-state-actions">
                            <button class="btn btn-outline-primary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise me-2"></i>Refresh Page
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard Header Styling */
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
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.25rem;
    margin: 0;
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

/* Breadcrumb Styling */
.breadcrumb {
    background: transparent;
    padding: 0;
    margin: 0;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: "›";
    color: #6c757d;
    font-weight: bold;
}

.breadcrumb-item a {
    color: #dc3545;
    text-decoration: none;
}

.breadcrumb-item a:hover {
    color: #b02a37;
}

.breadcrumb-item.active {
    color: #6c757d;
}

/* Announcements Grid */
.announcements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.announcement-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.announcement-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.card-header-section {
    padding: 1rem 1.5rem;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.priority-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.priority-high {
    background-color: #dc3545;
    color: white;
}

.priority-medium {
    background-color: #ffc107;
    color: #212529;
}

.priority-low {
    background-color: #6c757d;
    color: white;
}

.priority-none {
    background-color: #e9ecef;
    color: #6c757d;
}

.organization-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.organization-redcross {
    background-color: #dc3545;
    color: white;
}

.organization-negrosfirst {
    background-color: #198754;
    color: white;
}

.organization-barangay {
    background-color: #0d6efd;
    color: white;
}

.card-content {
    padding: 1.5rem;
}

.announcement-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.75rem;
    line-height: 1.4;
}

.announcement-text {
    color: #6c757d;
    line-height: 1.6;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.action-buttons {
    margin-top: 1rem;
}

.btn-learn-more {
    background-color: #0d6efd;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.btn-learn-more:hover {
    background-color: #0b5ed7;
    transform: translateY(-1px);
}

.card-footer-section {
    padding: 1rem 1.5rem;
    background-color: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.timeline-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.timeline-item {
    display: flex;
    align-items: center;
    font-size: 0.8rem;
    color: #6c757d;
}

.timeline-item i {
    margin-right: 0.5rem;
    font-size: 0.8rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.empty-state-icon {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 1.5rem;
}

.empty-state h3 {
    color: #6c757d;
    margin-bottom: 1rem;
    font-weight: 600;
}

.empty-state p {
    color: #adb5bd;
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

.empty-state-actions .btn {
    padding: 0.75rem 2rem;
    border-radius: 25px;
    font-weight: 600;
}

/* Dashboard Layout Fixes */
.dashboard-content {
    position: relative;
    z-index: 1;
}

.dashboard-main {
    position: relative;
    z-index: 1;
}

/* Responsive Design */
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
    
    .announcements-header {
        padding: 2rem 0 1.5rem 0;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .announcements-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .card-header-section {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-stats {
        text-align: center;
        margin-top: 1rem;
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
    
    .announcement-card {
        margin: 0 1rem;
    }
    
    .card-header-section,
    .card-content,
    .card-footer-section {
        padding: 1rem;
    }
    
    .page-title {
        font-size: 1.75rem;
    }
}
</style>

</body>
</html>
