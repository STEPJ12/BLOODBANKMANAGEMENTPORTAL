<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

$pageTitle = "System Maintenance - Negros First Blood Bank";
$isDashboard = true; // Enable notification dropdown

// Simple config file for maintenance flags
$configPath = realpath(__DIR__ . '/../../config');
if ($configPath === false) { $configPath = __DIR__ . '/../../config'; }
$flagsFile = $configPath . '/maintenance_flags.json';

// Ensure config directory exists
if (!is_dir($configPath)) {
    @mkdir($configPath, 0755, true);
}

// Load flags
$flags = [];
if (file_exists($flagsFile)) {
    $json = @file_get_contents($flagsFile);
    if ($json !== false) {
        $flags = json_decode($json, true);
        if (!is_array($flags)) { $flags = []; }
    }
}

// Defaults
if (!isset($flags['negrosfirst'])) { $flags['negrosfirst'] = false; }

$message = '';
$alert = '';

// Handle toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    $desired = ($_POST['toggle'] === 'enable');
    $flags['negrosfirst'] = $desired;
    
    // Ensure config directory is writable
    if (!is_writable($configPath)) {
        $message = 'Config directory is not writable. Please check file permissions for config/.';
        $alert = 'danger';
    } else {
        $result = @file_put_contents($flagsFile, json_encode($flags, JSON_PRETTY_PRINT));
        if ($result !== false) {
            $message = 'Maintenance mode ' . ($desired ? 'enabled' : 'disabled') . ' successfully.';
            $alert = 'success';
        } else {
            $message = 'Failed to update maintenance flag. Check file permissions for config/.';
            $alert = 'danger';
        }
    }
}

// Health checks
$dbOk = false; 
$dbMsg = '';
try {
    $testQuery = executeQuery("SELECT 1 as test");
    if (is_array($testQuery) && count($testQuery) > 0) {
        $dbOk = true;
        $dbMsg = 'Database connection OK';
    } else {
        $dbMsg = 'Database query returned unexpected result';
    }
} catch (Exception $e) {
    $dbMsg = 'Database error: ' . $e->getMessage();
} catch (Throwable $e) {
    $dbMsg = 'Database error: ' . $e->getMessage();
}

$diskFree = @disk_free_space(__DIR__);
$diskTotal = @disk_total_space(__DIR__);
$diskOk = ($diskFree !== false && $diskTotal !== false && $diskTotal > 0);
$diskPercent = $diskOk ? round(($diskFree / $diskTotal) * 100, 2) : null;
$diskMessage = $diskOk ? sprintf('%.2f GB free / %.2f GB total (%.2f%%)', 
    $diskFree / (1024*1024*1024), 
    $diskTotal / (1024*1024*1024), 
    $diskPercent
) : 'Unable to check disk space';


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
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-600: #4b5563;
        --gray-800: #1f2937;
        --border-radius: 12px;
        --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --box-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card { 
        border: 1px solid var(--gray-200); 
        border-radius: var(--border-radius); 
        box-shadow: var(--box-shadow); 
        transition: var(--transition);
    }
    
    .card:hover {
        box-shadow: var(--box-shadow-lg);
        transform: translateY(-2px);
    }
    
    .card-header { 
        border-bottom: 1px solid var(--gray-200); 
        background: var(--white); 
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }
    
    .list-group-item { 
        border-color: var(--gray-200); 
    }
    
    .badge { 
        border-radius: 20px; 
        padding: 0.35rem 0.6rem; 
        font-weight: 600;
    }
    
    .btn-action {
        width: 100%;
        margin-bottom: 10px;
        padding: 0.75rem 1rem;
        font-weight: 600;
        transition: var(--transition);
    }
    
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .status-badge {
        font-size: 0.9em;
        padding: 5px 10px;
    }
</style>

<div class="dashboard-container">
    <?php include_once '../../includes/sidebar.php'; ?>
    <div class="dashboard-content">
        <div class="dashboard-header p-3">
            <h2 class="h4 mb-0"><i class="bi bi-tools me-2"></i>System Maintenance</h2>
        </div>
        
        <!-- Controls below header -->
        <div class="p-3 pb-0">
            <div class="d-flex gap-2 justify-content-between align-items-center flex-wrap">
                <div>
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $alert; ?> alert-dismissible fade show mb-0" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="performAction('check_health')">
                        <i class="bi bi-heart-pulse me-1"></i> Health Check
                    </button>
                    <form method="POST" class="d-flex gap-2">
                        <?php if ($flags['negrosfirst']): ?>
                            <button type="submit" name="toggle" value="disable" class="btn btn-success">
                                <i class="bi bi-play-circle me-1"></i> Disable Maintenance
                            </button>
                        <?php else: ?>
                            <button type="submit" name="toggle" value="enable" class="btn btn-warning">
                                <i class="bi bi-pause-circle me-1"></i> Enable Maintenance
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="dashboard-main p-3">
            <div class="row g-4">
                <!-- Maintenance Mode Status -->
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-tools me-2"></i>Maintenance Mode</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="display-6 me-3 <?php echo $flags['negrosfirst'] ? 'text-warning' : 'text-success'; ?>">
                                    <i class="bi bi-<?php echo $flags['negrosfirst'] ? 'pause-circle-fill' : 'check-circle-fill'; ?>"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Status</h5>
                                    <span class="badge bg-<?php echo $flags['negrosfirst'] ? 'warning' : 'success'; ?> status-badge">
                                        <?php echo $flags['negrosfirst'] ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </div>
                            </div>
                            <p class="mb-0 text-muted">
                                <?php if ($flags['negrosfirst']): ?>
                                    <strong>System is in maintenance mode.</strong> Regular users may experience limited access. Only administrators can access the system during this time.
                                <?php else: ?>
                                    System is operational. Enable maintenance mode to temporarily prevent changes and inform users of scheduled downtime.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Health Checks -->
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>System Health</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-database-fill-check me-2"></i>Database</span>
                                    <span class="badge bg-<?php echo $dbOk ? 'success' : 'danger'; ?>">
                                        <?php echo $dbOk ? 'OK' : 'Error'; ?>
                                    </span>
                                </li>
                                <li class="list-group-item small text-muted"><?php echo htmlspecialchars($dbMsg); ?></li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-hdd-network me-2"></i>Disk Space</span>
                                    <span class="badge bg-<?php echo $diskOk && $diskPercent > 10 ? 'success' : ($diskOk && $diskPercent > 5 ? 'warning' : 'danger'); ?>">
                                        <?php echo $diskOk ? ($diskPercent . '%') : 'Unknown'; ?>
                                    </span>
                                </li>
                                <li class="list-group-item small text-muted"><?php echo htmlspecialchars($diskMessage); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Database Maintenance -->
                <div class="col-12 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-database me-2"></i>Database Maintenance</h5>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-primary btn-action" onclick="performAction('backup')">
                                <i class="bi bi-download me-2"></i>Backup Database
                            </button>
                            <button class="btn btn-info btn-action" onclick="performAction('optimize')">
                                <i class="bi bi-speedometer2 me-2"></i>Optimize Database
                            </button>
                            <button class="btn btn-outline-danger btn-action" onclick="performAction('expire_inventory')">
                                <i class="bi bi-exclamation-octagon me-2"></i>Expire Old Inventory
                            </button>
                            <button class="btn btn-warning btn-action" onclick="performAction('clear_records')">
                                <i class="bi bi-trash me-2"></i>Clear Old Records
                            </button>
                        </div>
                    </div>
                </div>

                <!-- System Maintenance -->
                <div class="col-12 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-gear me-2"></i>System Maintenance</h5>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-success btn-action" onclick="performAction('update_settings')">
                                <i class="bi bi-gear-fill me-2"></i>Update System Settings
                            </button>
                            <button class="btn btn-danger btn-action" onclick="performAction('clear_cache')">
                                <i class="bi bi-x-circle me-2"></i>Clear System Cache
                            </button>
                            <button class="btn btn-secondary btn-action" onclick="performAction('check_health')">
                                <i class="bi bi-heart-pulse me-2"></i>Check System Health
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Messages -->
            <div class="row mt-4">
                <div class="col-12">
                    <div id="status-message" class="alert" style="display: none;"></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body text-center">
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Notice:</strong> For urgent assistance, contact the system administrator or call (034) 765-4321.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Function to show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

// Function to handle AJAX requests
async function performAction(action) {
    try {
        const response = await fetch('maintenance_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=' + encodeURIComponent(action)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
        } else {
            showToast(data.message, 'danger');
        }
        
        if (action === 'check_health' && data.data) {
            displayHealthCheck(data.data);
        }
        
    } catch (error) {
        showToast('An error occurred: ' + error.message, 'danger');
    }
}

// Function to display health check results
function displayHealthCheck(health) {
    const healthModal = new bootstrap.Modal(document.getElementById('healthModal'));
    const healthList = document.getElementById('healthList');
    
    healthList.innerHTML = `
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong>Database Connection</strong>
                <small class="d-block text-muted">${health.database_message || ''}</small>
            </div>
            <span class="badge bg-${health.database ? 'success' : 'danger'} rounded-pill">
                ${health.database ? 'OK' : 'Failed'}
            </span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong>Disk Space</strong>
                <small class="d-block text-muted">${health.disk_message || ''}</small>
            </div>
            <span class="badge bg-${health.disk_space ? 'success' : 'danger'} rounded-pill">
                ${health.disk_space ? 'OK' : 'Low'}
            </span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong>PHP Version</strong>
                <small class="d-block text-muted">${health.php_message || ''}</small>
            </div>
            <span class="badge bg-${health.php_version ? 'success' : 'danger'} rounded-pill">
                ${health.php_version ? 'OK' : 'Update Required'}
            </span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong>MySQLi Extension</strong>
                <small class="d-block text-muted">${health.mysqli_message || ''}</small>
            </div>
            <span class="badge bg-${health.mysqli ? 'success' : 'warning'} rounded-pill">
                ${health.mysqli ? 'Available' : 'Not Available'}
            </span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong>Memory Limit</strong>
                <small class="d-block text-muted">${health.memory_limit || 'Not set'}</small>
            </div>
            <span class="badge bg-info rounded-pill">
                Info
            </span>
        </li>
    `;
    
    healthModal.show();
}

// Fix notification dropdown - simple and direct approach
document.addEventListener('DOMContentLoaded', function() {
    const bellBtn = document.getElementById('nfBellBtn');
    const dropdown = document.getElementById('nfDropdown');
    
    if (bellBtn && dropdown) {
        bellBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            } else {
                dropdown.classList.add('show');
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!bellBtn.contains(e.target) && !dropdown.contains(e.target)) {
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        });
        
        dropdown.addEventListener('click', function(e) {
            if (e.target.closest('.list-group-item') || e.target.closest('a')) {
                dropdown.classList.remove('show');
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        });
    }
});

// Auto-hide feedback messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert:not(.alert-info)');
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

<!-- Health Check Modal -->
<div class="modal fade" id="healthModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-heart-pulse me-2"></i>System Health Check Results</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group" id="healthList">
                    <!-- Health check results will be inserted here -->
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
