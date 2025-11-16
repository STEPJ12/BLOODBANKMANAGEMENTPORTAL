<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/redcross_auth.php';

// Set page title
$pageTitle = "Red Cross Dashboard - Blood Bank Portal";

// Include header
include_once 'header.php';


// Fetch Red Cross information
$redcrossId = $_SESSION['user_id'];
$redcross = getRow("SELECT * FROM blood_banks WHERE id = ?", [$redcrossId]);

// Fetch blood inventory
$bloodInventory = [];
try {
    // Debug the redcrossId
    error_log("Red Cross ID: " . $redcrossId);
    
    // Simplified query without expiry date check
    $bloodInventory = executeQuery("
        SELECT blood_type, COALESCE(SUM(units), 0) as total_units
        FROM blood_inventory
        WHERE organization_type = 'redcross' 
        AND organization_id = ?
        AND status = 'Available'
        GROUP BY blood_type
        ORDER BY blood_type
    ", [$redcrossId]);
    
    if ($bloodInventory === false) {
        error_log("Error fetching blood inventory: Query returned false");
        $bloodInventory = [];
    } else {
        error_log("Blood inventory query successful. Results: " . print_r($bloodInventory, true));
    }
} catch (Exception $e) {
    error_log("Error fetching blood inventory: " . $e->getMessage());
    $bloodInventory = [];
}

// Debug information
error_log("Final Blood Inventory Result: " . print_r($bloodInventory, true));



// Fetch recent donations
$recentDonations = executeQuery("
    SELECT d.*, du.name as donor_name
    FROM donations d
    JOIN donor_users du ON d.donor_id = du.id
    WHERE d.organization_type = 'redcross' AND d.organization_id = ?
    ORDER BY d.donation_date DESC
    LIMIT 5
", [$redcrossId]);

// Fetch pending blood requests (all Red Cross requests regardless of organization_id)
$pendingRequests = [];
try {
    $pendingRequests = executeQuery("
        SELECT br.*, pu.name as patient_name, pu.hospital
        FROM blood_requests br
        JOIN patient_users pu ON br.patient_id = pu.id
        WHERE br.organization_type = 'redcross' 
        AND br.status = 'Pending'
        ORDER BY br.request_date ASC
        LIMIT 10
    ", []);
    
    if ($pendingRequests === false) {
        error_log("Error fetching pending requests: Query returned false");
        $pendingRequests = [];
    }
} catch (Exception $e) {
    error_log("Error fetching pending requests: " . $e->getMessage());
    $pendingRequests = [];
}

// Fetch upcoming blood drives (all Red Cross drives regardless of organization_id)
$upcomingDrives = [];
try {
    $upcomingDrives = executeQuery("
        SELECT bd.*, b.name as barangay_name
        FROM blood_drives bd
        LEFT JOIN barangay_users b ON bd.barangay_id = b.id
        WHERE bd.organization_type = 'redcross' 
        AND bd.date >= CURDATE() 
        AND bd.status = 'Scheduled'
        ORDER BY bd.date ASC, bd.start_time ASC
        LIMIT 5
    ", []);
    
    if ($upcomingDrives === false) {
        error_log("Error fetching upcoming drives: Query returned false");
        $upcomingDrives = [];
    }
} catch (Exception $e) {
    error_log("Error fetching upcoming drives: " . $e->getMessage());
    $upcomingDrives = [];
}

?>

<!-- Enhanced Dashboard Header -->
<div class="dashboard-header-section">
    <div class="header-hero">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="welcome-content">
                    <div class="welcome-badge">
                        <i class="bi bi-heart-pulse"></i>
                        <span>Philippine Red Cross</span>
                    </div>
                    <h1 class="hero-title">Welcome Back!!</h1>
                    <p class="hero-subtitle">
                       Donate Blood Save lives.
                        <br>Together, we make a difference in our community.
                    </p>
                    <div class="hero-actions">
                        <a href="blood-requests.php" class="btn-hero btn-primary">
                            <i class="bi bi-clipboard2-pulse"></i>
                            Process Requests
                        </a>
                        <a href="blood-drives.php" class="btn-hero btn-secondary">
                            <i class="bi bi-calendar-event"></i>
                            Schedule Drive
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="bi bi-droplet-fill"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo array_sum(array_column($bloodInventory, 'total_units')); ?></div>
                            <div class="stat-label">Total Units Available</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="bi bi-heart"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo count($recentDonations); ?></div>
                            <div class="stat-label">Recent Donations</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Status Bar -->
    <div class="status-bar">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="status-card status-success">
                    <div class="status-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="status-content">
                        <div class="status-title">System Status</div>
                        <div class="status-value">All Systems Operational</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="status-card status-info">
                    <div class="status-icon">
                        <i class="bi bi-calendar-week"></i>
                    </div>
                    <div class="status-content">
                        <div class="status-title">This Week</div>
                        <div class="status-value"><?php echo count($upcomingDrives); ?> Blood Drives</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="status-card status-warning">
                    <div class="status-icon">
                        <i class="bi bi-clock-fill"></i>
                    </div>
                    <div class="status-content">
                        <div class="status-title">Last Updated</div>
                        <div class="status-value"><?php echo date('M j, Y g:i A'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="status-card status-primary">
                    <div class="status-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="status-content">
                        <div class="status-title">Active Users</div>
                        <div class="status-value">Online Now</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Enhanced Dashboard Header Styles */
    .dashboard-header-section {
        margin-bottom: 2rem;
    }

    .header-hero {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        border-radius: 20px;
        padding: 3rem 2.5rem;
        color: white;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px -12px rgba(220, 20, 60, 0.3);
    }

    .header-hero::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(50%, -50%);
    }

    .welcome-content {
        position: relative;
        z-index: 2;
    }

    .welcome-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.15);
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.875rem;
        font-weight: 500;
        margin-bottom: 1.5rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .hero-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        line-height: 1.2;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .hero-subtitle {
        font-size: 1.1rem;
        line-height: 1.6;
        opacity: 0.9;
        margin-bottom: 2rem;
        max-width: 600px;
    }

    .hero-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .btn-hero {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.875rem 1.5rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        font-size: 0.95rem;
    }

    .btn-hero.btn-primary {
        background: white;
        color: var(--primary-color);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .btn-hero.btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        color: var(--primary-color);
        text-decoration: none;
    }

    .btn-hero.btn-secondary {
        background: transparent;
        color: white;
        border-color: rgba(255, 255, 255, 0.3);
    }

    .btn-hero.btn-secondary:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: white;
        transform: translateY(-2px);
        color: white;
        text-decoration: none;
    }

    .hero-stats {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: rgba(255, 255, 255, 0.1);
        padding: 1.25rem;
        border-radius: 16px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
    }

    .stat-item:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateX(4px);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .stat-content .stat-number {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 0.25rem;
    }

    .stat-content .stat-label {
        font-size: 0.875rem;
        opacity: 0.8;
        line-height: 1;
    }

    /* Status Bar */
    .status-bar {
        margin-bottom: 2rem;
    }

    .status-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border: none;
        transition: all 0.3s ease;
        height: 100%;
    }

    .status-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
    }

    .status-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .status-success .status-icon {
        background: linear-gradient(135deg, var(--success-color), #2ECC71);
        color: white;
    }

    .status-info .status-icon {
        background: linear-gradient(135deg, var(--info-color), #5DADE2);
        color: white;
    }

    .status-warning .status-icon {
        background: linear-gradient(135deg, var(--warning-color), #F7DC6F);
        color: white;
    }

    .status-primary .status-icon {
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        color: white;
    }

    .status-content {
        flex: 1;
    }

    .status-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--gray-600);
        margin-bottom: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-value {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--secondary-color);
        line-height: 1.2;
    }

    /* Responsive Design for Header */
    @media (max-width: 992px) {
        .hero-title {
            font-size: 2rem;
        }
        
        .hero-subtitle {
            font-size: 1rem;
        }
        
        .header-hero {
            padding: 2rem 1.5rem;
        }
        
        .hero-stats {
            margin-top: 2rem;
        }
    }

    @media (max-width: 768px) {
        .hero-title {
            font-size: 1.75rem;
        }
        
        .hero-actions {
            flex-direction: column;
        }
        
        .btn-hero {
            justify-content: center;
        }
        
        .stat-item {
            padding: 1rem;
        }
        
        .status-card {
            padding: 1.25rem;
        }
    }

    /* Dashboard Cards */
    .dashboard-card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border: none;
        transition: all 0.3s ease;
        height: 100%;
    }

    .dashboard-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
    }

    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-100);
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--secondary-color);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Blood Type Cards */
    .blood-type-card {
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        border: 2px solid var(--gray-200);
        transition: all 0.3s ease;
        height: 100%;
    }

    .blood-type-card:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
    }

    .blood-type-label {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--secondary-color);
        margin-bottom: 0.5rem;
    }

    .blood-units {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--primary-color);
        margin-bottom: 0.5rem;
    }

    .units-text {
        color: var(--gray-600);
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }

    .status-indicator {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-good {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: var(--success-color);
    }

    .status-low {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: var(--warning-color);
    }

    .status-critical {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: var(--danger-color);
    }

    /* Quick Actions */
    .action-btn {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 12px;
        padding: 1rem 1.5rem;
        text-decoration: none;
        color: var(--secondary-color);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.3s ease;
        font-weight: 500;
        margin-bottom: 1rem;
    }

    .action-btn:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateX(4px);
        text-decoration: none;
    }

    .action-btn i {
        font-size: 1.25rem;
        width: 24px;
        text-align: center;
    }

    /* List Items */
    .list-item {
        background: white;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        margin-bottom: 0.75rem;
        border: 1px solid var(--gray-200);
        transition: all 0.3s ease;
    }

    .list-item:hover {
        border-color: var(--primary-color);
        transform: translateX(4px);
    }

    .list-item:last-child {
        margin-bottom: 0;
    }

    .item-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .item-title {
        font-weight: 600;
        color: var(--secondary-color);
        margin: 0;
    }

    .item-meta {
        color: var(--gray-600);
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Statistics Cards */
    .stat-card {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        color: white;
        border-radius: 16px;
        padding: 2rem;
        text-align: center;
        box-shadow: 0 10px 25px -3px rgba(220, 20, 60, 0.3);
    }

    .stat-number {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        font-size: 1rem;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--gray-500);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-state h5 {
        color: var(--gray-600);
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        margin: 0;
        font-size: 0.875rem;
    }
</style>

<!-- Dashboard Content -->
<!-- Blood Inventory Overview -->
<div class="dashboard-card mb-4">
    <div class="card-header-custom">
        <h3 class="card-title">
            <i class="bi bi-droplet-fill"></i>
            Blood Inventory Overview
        </h3>
        <a href="inventory.php" class="btn btn-outline-primary">
            <i class="bi bi-eye me-1"></i>View Full Inventory
        </a>
    </div>
    <div class="row g-3">
        <?php
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $inventoryMap = [];

        // Create a map of blood type to units
        foreach ($bloodInventory as $item) {
            $inventoryMap[$item['blood_type']] = $item['total_units'];
        }

        foreach ($bloodTypes as $bloodType):
            $units = isset($inventoryMap[$bloodType]) ? $inventoryMap[$bloodType] : 0;
            $statusClass = 'critical';
            $statusText = 'Critical';

            if ($units > 20) {
                $statusClass = 'good';
                $statusText = 'Good';
            } elseif ($units > 10) {
                $statusClass = 'low';
                $statusText = 'Low';
            }
        ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="blood-type-card">
                    <div class="blood-type-label"><?php echo $bloodType; ?></div>
                    <div class="blood-units"><?php echo $units; ?></div>
                    <div class="units-text">units available</div>
                    <div class="status-indicator status-<?php echo $statusClass; ?>">
                        <?php echo $statusText; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

       
            

<div class="row g-4 mb-4">
    <!-- Quick Actions -->
    <div class="col-lg-4">
        <div class="dashboard-card">
            <div class="card-header-custom">
                <h3 class="card-title">
                    <i class="bi bi-lightning-fill"></i>
                    Quick Actions
                </h3>
            </div>
            <div class="d-flex flex-column">
                <a href="blood-requests.php" class="action-btn">
                    <i class="bi bi-clipboard2-pulse"></i>
                    Process Blood Request
                </a>
                <a href="blood-drives.php" class="action-btn">
                    <i class="bi bi-calendar-event"></i>
                    Schedule Blood Drive
                </a>
                <a href="inventory.php" class="action-btn">
                    <i class="bi bi-box-seam"></i>
                    Update Inventory
                </a>
                <a href="reports.php" class="action-btn" style="margin-bottom: 0;">
                    <i class="bi bi-file-earmark-text"></i>
                    Generate Report
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Donations -->
    <div class="col-lg-8">
        <div class="dashboard-card">
            <div class="card-header-custom">
                <h3 class="card-title">
                    <i class="bi bi-heart-fill"></i>
                    Recent Donations
                </h3>
                <a href="donations.php" class="btn btn-outline-primary">
                    <i class="bi bi-eye me-1"></i>View All
                </a>
            </div>
            <?php if (count($recentDonations) > 0): ?>
                <div class="d-flex flex-column">
                    <?php foreach ($recentDonations as $donation): ?>
                        <div class="list-item">
                            <div class="item-header">
                                <h6 class="item-title"><?php echo htmlspecialchars($donation['donor_name']); ?></h6>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary">
                                        <?php echo number_format($donation['units'], 1); ?> units
                                    </span>
                                    <a href="donations.php?id=<?php echo $donation['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="item-meta">
                                <i class="bi bi-calendar3"></i>
                                <?php echo date('M d, Y', strtotime($donation['donation_date'])); ?>
                                <span>•</span>
                                <i class="bi bi-droplet"></i>
                                <?php echo htmlspecialchars($donation['blood_type'] ?? 'N/A'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-droplet-half"></i>
                    <h5>No Recent Donations</h5>
                    <p>Donation records will appear here once donors start contributing.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


       
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-hide feedback messages after 30 seconds
    setTimeout(function() {
        var successMsg = document.querySelector('.alert-success');
        var errorMsg = document.querySelector('.alert-danger');
        if (successMsg) successMsg.style.display = 'none';
        if (errorMsg) errorMsg.style.display = 'none';
    }, 5000);
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Add loading states for action buttons
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'bi bi-hourglass-split';
            
            setTimeout(() => {
                icon.className = originalClass;
            }, 1000);
        });
    });

    // Auto-refresh dashboard data every 5 minutes
    setInterval(function() {
        // You can add AJAX calls here to refresh data without page reload
        console.log('Dashboard data refresh check...');
    }, 5000);
</script>

</body>
</html>