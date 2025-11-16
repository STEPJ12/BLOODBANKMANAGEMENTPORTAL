<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

$isDashboard = true; // Enable notification dropdown

// Get database connection
$conn = getConnection();

// Get inventory summary grouped by blood type
// Note: Individual batches are ordered by expiry date (FIFO) in inventory-details.php
$sql = "SELECT 
    blood_type,
    SUM(units) as available_units,
    COUNT(*) as total_batches,
    MIN(expiry_date) AS next_expiry_date,
    DATEDIFF(MIN(expiry_date), CURDATE()) AS days_left,
    MAX(created_at) as last_updated
FROM blood_inventory 
WHERE organization_type = 'negrosfirst'
  AND status = 'Available'
  AND (expiry_date IS NULL OR expiry_date >= CURDATE())
GROUP BY blood_type
ORDER BY blood_type";

// Fetch inventory data using proper database connection
try {
    $result = $conn->query($sql);
    if ($result) {
        $inventory = $result->fetchAll(PDO::FETCH_ASSOC);
    } else {
        if (function_exists('secure_log')) {
            secure_log("Inventory query failed");
        }
        $inventory = [];
    }
} catch (Exception $e) {
    if (function_exists('secure_log')) {
        secure_log("Inventory query error", ['error' => substr($e->getMessage(), 0, 200)]);
    }
    $inventory = [];
}


// Handle form submission for inventory addition
// SECURITY: All POST data is validated and sanitized before use
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_inventory') {
    // Use secure_log to prevent log injection attacks
    if (function_exists('secure_log')) {
        secure_log('Inventory addition request received', ['action' => 'add_inventory']);
    }
    
    try {
        // Sanitize and validate all inputs
        $blood_type = sanitize($_POST['blood_type'] ?? '');
        $units = isset($_POST['units']) ? (int)$_POST['units'] : 0;
        $expiry_date = sanitize($_POST['expiry_date'] ?? '');
        $source = sanitize($_POST['source'] ?? 'Blood Drive');
        
        // Validate input
        if (empty($blood_type) || $units <= 0 || empty($expiry_date)) {
            throw new Exception('All fields are required');
        }
        
        // Validate blood type
        $valid_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        if (!in_array($blood_type, $valid_blood_types)) {
            throw new Exception('Invalid blood type');
        }
        
        // Validate expiry date
        $expiry_timestamp = strtotime($expiry_date);
        if ($expiry_timestamp === false || $expiry_timestamp < time()) {
            throw new Exception('Invalid expiry date');
        }
        
        // Log the data being inserted - use secure_log to prevent log injection
        if (function_exists('secure_log')) {
            secure_log("Inserting inventory data", [
                'blood_type' => $blood_type,
                'units' => $units,
                'expiry_date' => $expiry_date,
                'source' => $source
            ]);
        }
        
        // Check database connection
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        // Test connection
        if (!$conn) {
            throw new Exception('Database connection is null');
        }
        
        // Test if table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'blood_inventory'");
        if ($tableCheck && $tableCheck->rowCount() == 0) {
            throw new Exception('blood_inventory table does not exist');
        }
        
        // Insert into database
        $stmt = $conn->prepare("
            INSERT INTO blood_inventory (blood_type, units, expiry_date, status, organization_type, organization_id, source, created_at, updated_at) 
            VALUES (?, ?, ?, 'Available', 'negrosfirst', ?, ?, NOW(), NOW())
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare statement');
        }
        
        // Get the current user's organization ID (assuming it's stored in session)
        $organization_id = $_SESSION['user_id'] ?? 1; // Fallback to 1 if not set
        
        if ($stmt->execute([$blood_type, $units, $expiry_date, $organization_id, $source])) {
            // Set success message and redirect
            $_SESSION['success_message'] = 'Inventory added successfully!';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $errorInfo = $stmt->errorInfo();
            if (function_exists('secure_log')) {
                secure_log("Database insert failed", ['error_code' => $errorInfo[0] ?? 'unknown']);
            }
            throw new Exception('Failed to insert into database');
        }
        
    } catch (Exception $e) {
        // Set error message and redirect
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}


$pageTitle = "Enhanced Inventory Management - Negros First";
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
        --border-radius: 12px;
        --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --box-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .inventory-card {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--gray-200);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .inventory-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    }

    .inventory-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--box-shadow-lg);
    }

    .blood-type-card {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--gray-200);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .blood-type-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--box-shadow-lg);
    }

    .blood-type-indicator {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 0.5rem;
    }

    .status-critical { background: var(--accent-color); }
    .status-low { background: var(--warning-color); }
    .status-good { background: var(--success-color); }

    .alert-card {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border: 1px solid #fecaca;
        border-radius: var(--border-radius);
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .alert-critical {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-color: #fecaca;
    }

    .alert-warning {
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        border-color: #fde68a;
    }
    
    /* Toast Notifications */
    .toast-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 400px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transform: translateX(100%);
        opacity: 0;
        transition: all 0.3s ease;
    }
    
    .toast-notification.show {
        transform: translateX(0);
        opacity: 1;
    }
    
    .toast-content {
        display: flex;
        align-items: center;
        padding: 16px;
        gap: 12px;
    }
    
    .toast-icon {
        flex-shrink: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }
    
    .toast-success .toast-icon {
        background: #d4edda;
        color: #155724;
    }
    
    .toast-error .toast-icon {
        background: #f8d7da;
        color: #721c24;
    }
    
    .toast-info .toast-icon {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .toast-message {
        flex: 1;
        font-size: 14px;
        font-weight: 500;
        color: #333;
    }
    
    .toast-close {
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        padding: 4px;
        border-radius: 4px;
        transition: background-color 0.2s;
    }
    
    .toast-close:hover {
        background: #f8f9fa;
    }
    
    .toast-success {
        border-left: 4px solid #28a745;
    }
    
    .toast-error {
        border-left: 4px solid #dc3545;
    }
    
    .toast-info {
        border-left: 4px solid #17a2b8;
    }
</style>

<div class="dashboard-container">
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h4 mb-1 fw-bold">Inventory Management</h2>
                    <p class="text-muted mb-0">Monitor and manage blood inventory.</p>
                </div>
            </div>
        </div>
        
        <!-- Controls below header -->
        <div class="p-4 pb-0">
            <div class="d-flex gap-3 justify-content-end">
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                    <i class="bi bi-plus-circle me-2"></i>Add Inventory
                </button>
                <button class="btn btn-primary" onclick="exportInventoryData()">
                    <i class="bi bi-download me-2"></i>Export Report
                </button>
            </div>
        </div>

        <div class="dashboard-main p-4">
            <!-- Session Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Alerts Section -->
            <?php if (!empty($alerts)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert-card">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill text-warning me-3 fs-4"></i>
                            <div>
                                <h6 class="mb-1 fw-bold">Inventory Alerts</h6>
                                <p class="mb-0">Some blood types require immediate attention.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Inventory Overview -->
            <div class="row g-4 mb-4">
                <?php
                $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                $inventoryMap = [];
                foreach ($inventory ?? [] as $item) {
                    $inventoryMap[$item['blood_type']] = $item;
                }

                foreach ($bloodTypes as $bloodType):
                    $item = $inventoryMap[$bloodType] ?? null;
                    $units = $item ? (int)$item['available_units'] : 0;
                    $status = $units > 20 ? 'good' : ($units > 10 ? 'low' : 'critical');
                    $statusClass = $status === 'good' ? 'success' : ($status === 'low' ? 'warning' : 'danger');
                    $statusColor = $status === 'good' ? 'var(--success-color)' : ($status === 'low' ? 'var(--warning-color)' : 'var(--accent-color)');
                ?>
                <div class="col-lg-3 col-md-6">
                    <div class="blood-type-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1 fw-bold"><?php echo $bloodType; ?></h5>
                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                            </div>
                            <div class="blood-type-indicator status-<?php echo $status; ?>"></div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Available Units</span>
                                <span class="fw-bold fs-4" style="color: <?php echo $statusColor; ?>;"><?php echo $units; ?></span>
                            </div>
                        </div>

                        <?php if ($item && $item['next_expiry_date']): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Next Expiry</span>
                                <span class="fw-semibold"><?php echo date('M d, Y', strtotime($item['next_expiry_date'])); ?></span>
                            </div>
                            <div class="progress mt-1" style="height: 4px;">
                                <div class="progress-bar bg-<?php echo $statusClass; ?>" 
                                     style="width: <?php echo min(100, max(0, ($item['days_left'] / 35) * 100)); ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Detailed Inventory Table -->
            <div class="inventory-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-table me-2"></i>Detailed Inventory Report
                        </h5>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Inventory uses FIFO (First In First Out) ordering based on expiry date
                        </small>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="exportInventoryData()">
                            <i class="bi bi-download me-1"></i>Export
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Blood Type</th>
                                <th>Available Units</th>
                                <th>Total Batches</th>
                                <th>Next Expiry</th>
                                <th>Days Left</th>
                                <th>Last Updated</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory ?? [] as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="blood-type-indicator status-<?php echo $item['available_units'] > 20 ? 'good' : ($item['available_units'] > 10 ? 'low' : 'critical'); ?>"></div>
                                        <span class="fw-semibold"><?php echo $item['blood_type']; ?></span>
                                    </div>
                                </td>
                                <td><span class="fw-bold"><?php echo $item['available_units']; ?></span></td>
                                <td><?php echo isset($item['total_batches']) ? $item['total_batches'] : '0'; ?></td>
                                <td><?php echo $item['next_expiry_date'] ? date('M d, Y', strtotime($item['next_expiry_date'])) : 'N/A'; ?></td>
                                <td>
                                    <?php if ($item['days_left'] !== null): ?>
                                        <span class="badge bg-<?php echo $item['days_left'] < 7 ? 'danger' : ($item['days_left'] < 14 ? 'warning' : 'success'); ?>">
                                            <?php echo $item['days_left']; ?> days
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo isset($item['last_updated']) && $item['last_updated'] ? date('M d, Y H:i', strtotime($item['last_updated'])) : 'N/A'; ?></td>
                                <td>
                                    <?php
                                    $status = $item['available_units'] > 20 ? 'good' : ($item['available_units'] > 10 ? 'low' : 'critical');
                                    $statusClass = $status === 'good' ? 'success' : ($status === 'low' ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="inventory-details.php?blood_type=<?php echo urlencode($item['blood_type']); ?>" class="btn btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button class="btn btn-outline-success" title="Add Units" onclick="showAddUnitsModal('<?php echo $item['blood_type']; ?>')">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Inventory Modal -->
<div class="modal fade" id="addInventoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Blood Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="addInventoryForm">
                <input type="hidden" name="action" value="add_inventory">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="blood_type" class="form-label">Blood Type</label>
                        <select class="form-select" id="blood_type" name="blood_type" required>
                            <option value="">Select Blood Type</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="units" class="form-label">Number of Units</label>
                        <input type="number" class="form-control" id="units" name="units" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="expiry_date" class="form-label">Expiry Date</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" required readonly>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>Automatically set to 35 days from today (standard blood expiry period)
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="source" class="form-label">Source</label>
                        <select class="form-select" id="source" name="source" required>
                            <option value="Blood Drive">Blood Drive</option>
                            <option value="Walk-in/Voluntary">Walk-in/Voluntary</option>
                            <option value="Scheduled Donation">Scheduled Donation</option>
                            <option value="Emergency Collection">Emergency Collection</option>
                        </select>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>Select how this blood was obtained
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add to Inventory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing inventory functionality...');
        console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
        console.log('jQuery available:', typeof $ !== 'undefined');
        
        // Immediate test of form existence
        const form = document.getElementById('addInventoryForm');
        console.log('Add inventory form found:', !!form);
        if (form) {
            console.log('Form element:', form);
        } else {
            console.error('Add inventory form not found!');
        }
        
        // Immediate test of modal buttons
        setTimeout(function() {
            console.log('Testing modal buttons immediately...');
            const modal = document.getElementById('addInventoryModal');
            if (modal) {
                console.log('Modal found:', modal);
                
                const closeBtn = modal.querySelector('.btn-close');
                const cancelBtn = modal.querySelector('.btn-secondary');
                const submitBtn = modal.querySelector('button[type="submit"]');
                
                console.log('Close button:', closeBtn);
                console.log('Cancel button:', cancelBtn);
                console.log('Submit button:', submitBtn);
                
                // Add immediate click handlers
                if (closeBtn) {
                    closeBtn.onclick = function(e) {
                        e.preventDefault();
                        console.log('X button clicked - closing modal');
                        modal.style.display = 'none';
                        modal.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) backdrop.remove();
                    };
                }
                
                if (cancelBtn) {
                    cancelBtn.onclick = function(e) {
                        e.preventDefault();
                        console.log('Cancel button clicked - closing modal');
                        modal.style.display = 'none';
                        modal.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) backdrop.remove();
                    };
                }
                
            }
        }, 1000);
        
        // Set default expiry date to 35 days from now (blood expiry standard)
        const expiryDateInput = document.getElementById('expiry_date');
        if (expiryDateInput) {
            const today = new Date();
            const futureDate = new Date(today.getTime() + (35 * 24 * 60 * 60 * 1000));
            expiryDateInput.value = futureDate.toISOString().split('T')[0];
            
            // Add visual styling to show it's auto-calculated
            expiryDateInput.style.backgroundColor = '#f8f9fa';
            expiryDateInput.style.cursor = 'not-allowed';
            
            console.log('Expiry date automatically set to:', futureDate.toISOString().split('T')[0], '(35 days from today)');
        }

        // Add smooth animations
        const cards = document.querySelectorAll('.blood-type-card, .inventory-card');
        console.log('Found cards:', cards.length);
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });


        // Filter functionality - be more specific
        const filterBtn = document.querySelector('.btn-outline-secondary[title="Filter"]');
        console.log('Found filter button:', filterBtn);
        if (filterBtn) {
            filterBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Filter clicked');
                showFilterModal();
            });
        }

        // Export functionality - be more specific
        const exportBtn = document.querySelector('.btn-outline-primary[title="Export"]');
        console.log('Found export button:', exportBtn);
        if (exportBtn) {
            exportBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Export clicked');
                exportInventoryData();
            });
        }

        // Table row action buttons
        const tableBtns = document.querySelectorAll('tbody .btn-group .btn');
        console.log('Found table buttons:', tableBtns.length);
        tableBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Table button clicked:', this.title);
                const row = this.closest('tr');
                const bloodType = row.querySelector('td:first-child span').textContent;
                const action = this.title;
                
                if (action === 'View Details') {
                    window.location.href = `inventory-details.php?blood_type=${encodeURIComponent(bloodType)}`;
                } else if (action === 'Add Units') {
                    showAddUnitsModal(bloodType);
                }
            });
        });
        
        console.log('Inventory functionality initialized');
        
        
        
        // Handle form submission - let it submit normally to reload page
        const addInventoryForm = document.getElementById('addInventoryForm');
        if (addInventoryForm) {
            addInventoryForm.addEventListener('submit', function(e) {
                console.log('Form is submitting...');
                
                // Ensure expiry date is always 35 days from today
                const today = new Date();
                const expiryDate = new Date(today.getTime() + (35 * 24 * 60 * 60 * 1000));
                const formattedExpiryDate = expiryDate.toISOString().split('T')[0];
                
                // Set the expiry date field
                const expiryDateInput = this.querySelector('input[name="expiry_date"]');
                if (expiryDateInput) {
                    expiryDateInput.value = formattedExpiryDate;
                    console.log('Expiry date set to:', formattedExpiryDate);
                }
                
                // Don't prevent default - let the form submit normally
                // This will reload the page and show the updated inventory
                console.log('Allowing form to submit normally...');
            });
        } else {
            console.error('Add inventory form not found');
        }
        
        // Test button functionality
        console.log('Testing button functionality...');
        
        // Test Add Inventory button
        const addInventoryBtn = document.querySelector('[data-bs-target="#addInventoryModal"]');
        if (addInventoryBtn) {
            console.log('Add Inventory button found:', addInventoryBtn);
            addInventoryBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Add Inventory button clicked');
                
                // Reset form first
                const form = document.getElementById('addInventoryForm');
                if (form) {
                    form.reset();
                    console.log('Form reset for Add Inventory');
                }
                
                // Reset expiry date to 35 days from today
                const expiryDateInput = document.getElementById('expiry_date');
                if (expiryDateInput) {
                    const today = new Date();
                    const futureDate = new Date(today.getTime() + (35 * 24 * 60 * 60 * 1000));
                    expiryDateInput.value = futureDate.toISOString().split('T')[0];
                    console.log('Expiry date reset to:', futureDate.toISOString().split('T')[0]);
                }
                
                // Ensure Bootstrap is available
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const modal = new bootstrap.Modal(document.getElementById('addInventoryModal'));
                    modal.show();
                    console.log('Modal should be showing now');
                } else {
                    console.error('Bootstrap Modal not available');
                    // Fallback: show modal manually
                    const modalElement = document.getElementById('addInventoryModal');
                    if (modalElement) {
                        modalElement.style.display = 'block';
                        modalElement.classList.add('show');
                        document.body.classList.add('modal-open');
                        console.log('Modal shown manually');
                    }
                }
            });
        } else {
            console.error('Add Inventory button not found');
        }
        
        // Test Export Report button
        const exportReportBtn = document.querySelector('[onclick="exportInventoryData()"]');
        if (exportReportBtn) {
            console.log('Export Report button found:', exportReportBtn);
        } else {
            console.error('Export Report button not found');
        }
        
        
        // Test modal close functionality
        const modalCloseBtn = document.querySelector('#addInventoryModal .btn-close');
        if (modalCloseBtn) {
            console.log('Modal close button found:', modalCloseBtn);
            modalCloseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Modal close button clicked');
                closeModal();
            });
        } else {
            console.error('Modal close button not found');
        }
        
        // Add backdrop click handler
        const modal = document.getElementById('addInventoryModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    console.log('Modal backdrop clicked');
                    closeModal();
                }
            });
        }
        
        // Add event handlers for all modal buttons
        const cancelBtn = document.querySelector('#addInventoryModal .btn-secondary');
        if (cancelBtn) {
            console.log('Cancel button found:', cancelBtn);
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Cancel button clicked');
                closeModal();
            });
        } else {
            console.error('Cancel button not found');
        }
        
        const submitBtn = document.querySelector('#addInventoryModal button[type="submit"]');
        if (submitBtn) {
            console.log('Submit button found:', submitBtn);
            submitBtn.addEventListener('click', function(e) {
                console.log('Submit button clicked');
                // Let the form submission handler take care of this
            });
        } else {
            console.error('Submit button not found');
        }
    });

    // Close modal function
    window.closeModal = function() {
        const modal = document.getElementById('addInventoryModal');
        if (modal) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) {
                    bootstrapModal.hide();
                } else {
                    // Create new modal instance and hide it
                    const newModal = new bootstrap.Modal(modal);
                    newModal.hide();
                }
            } else {
                // Fallback: hide modal manually
                modal.style.display = 'none';
                modal.classList.remove('show');
                document.body.classList.remove('modal-open');
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
                console.log('Modal closed manually');
            }
        } else {
            console.error('Modal element not found for closing');
        }
    };
    
    // Test database connection function
    window.testDatabaseConnection = function() {
        console.log('Testing database connection...');
        
        const testData = new FormData();
        testData.append('blood_type', 'A+');
        testData.append('units', '1');
        testData.append('expiry_date', '2025-12-31');
        testData.append('source', 'Blood Drive');
        testData.append('action', 'add_inventory');
        
        fetch('', {
            method: 'POST',
            body: testData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed response:', data);
                if (data.success) {
                    showToast('Database test successful!', 'success');
                } else {
                    showToast('Database test failed: ' + data.message, 'error');
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                showToast('Server error - check console', 'error');
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            showToast('Network error - check console', 'error');
        });
    };
    
    // Toast notification function
    window.showToast = function(message, type = 'info') {
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.toast-notification');
        existingToasts.forEach(toast => toast.remove());
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">
                    <i class="bi bi-${type === 'success' ? 'check-circle-fill' : type === 'error' ? 'exclamation-triangle-fill' : 'info-circle-fill'}"></i>
                </div>
                <div class="toast-message">${message}</div>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
        
        // Add to page
        document.body.appendChild(toast);
        
        // Show toast with animation
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
            }
        }, 5000);
    };
    

    // Show blood type details modal
    window.showBloodTypeDetails = function(bloodType) {
        console.log('showBloodTypeDetails called with:', bloodType);
        
        // Remove existing modal if any
        const existingModal = document.getElementById('detailsModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="detailsModalLabel">${bloodType} Blood Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Current Status</h6>
                                    <p>Available Units: <strong id="currentUnits">Loading...</strong></p>
                                    <p>Status: <span id="currentStatus" class="badge">Loading...</span></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Expiry Information</h6>
                                    <p>Next Expiry: <strong id="nextExpiry">Loading...</strong></p>
                                    <p>Days Left: <span id="daysLeft" class="badge">Loading...</span></p>
                                </div>
                            </div>
                            <hr>
                            <h6>Recent Activity</h6>
                            <div id="recentActivity">
                                <p class="text-muted">Loading recent activity...</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeDetailsModal()">Close</button>
                            <button type="button" class="btn btn-primary" onclick="addUnitsFromDetails('${bloodType}')">Add Units</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        console.log('Modal HTML added to body');
        
        // Get the modal element
        const modalElement = document.getElementById('detailsModal');
        console.log('Modal element found:', modalElement);
        
        if (modalElement) {
            // Check if Bootstrap is available
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                console.log('Bootstrap Modal available, creating modal instance');
                const modal = new bootstrap.Modal(modalElement);
                
                // Add event handlers after modal is shown
                modalElement.addEventListener('shown.bs.modal', function() {
                    console.log('Modal shown, adding event handlers');
                    
                    const closeBtn = document.getElementById('closeDetailsModal');
                    const addUnitsBtn = document.getElementById('addUnitsFromDetails');
                    
                    if (closeBtn) {
                        closeBtn.addEventListener('click', function() {
                            console.log('Close button clicked');
                            modal.hide();
                        });
                    }
                    
                    if (addUnitsBtn) {
                        addUnitsBtn.addEventListener('click', function() {
                            console.log('Add Units button clicked from details modal');
                            modal.hide();
                            // Show add units modal after a short delay
                            setTimeout(() => {
                                showAddUnitsModal(bloodType);
                            }, 300);
                        });
                    }
                });
                
                modal.show();
                console.log('Modal show() called');
            } else {
                console.error('Bootstrap Modal not available');
                // Fallback: show modal manually
                modalElement.style.display = 'block';
                modalElement.classList.add('show');
                document.body.classList.add('modal-open');
                
                // Add event handlers for fallback
                const closeBtn = document.getElementById('closeDetailsModal');
                const addUnitsBtn = document.getElementById('addUnitsFromDetails');
                
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        console.log('Close button clicked (fallback)');
                        modalElement.style.display = 'none';
                        modalElement.classList.remove('show');
                        document.body.classList.remove('modal-open');
                    });
                }
                
                if (addUnitsBtn) {
                    addUnitsBtn.addEventListener('click', function() {
                        console.log('Add Units button clicked (fallback)');
                        modalElement.style.display = 'none';
                        modalElement.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        showAddUnitsModal(bloodType);
                    });
                }
            }
        } else {
            console.error('Modal element not found');
        }
        
        // Load real data after modal is shown
        setTimeout(() => {
            // Get real data from the blood type card
            let realUnits = 0;
            let realStatus = 'Unknown';
            let realExpiry = 'N/A';
            let realDaysLeft = 'N/A';
            
            // Find the card by blood type
            const allCards = document.querySelectorAll('.blood-type-card');
            let card = null;
            for (let i = 0; i < allCards.length; i++) {
                const h5 = allCards[i].querySelector('h5');
                if (h5 && h5.textContent.trim() === bloodType) {
                    card = allCards[i];
                    break;
                }
            }
            
            if (card) {
                // Get real units from the card
                const unitsElement = card.querySelector('.fw-bold.fs-4');
                if (unitsElement) {
                    realUnits = parseInt(unitsElement.textContent) || 0;
                }
                
                // Get real status from the card
                const statusBadge = card.querySelector('.badge');
                if (statusBadge) {
                    realStatus = statusBadge.textContent;
                }
                
                // Get real expiry info from the card
                const expiryElement = card.querySelector('.fw-semibold');
                if (expiryElement) {
                    realExpiry = expiryElement.textContent;
                }
                
                // Calculate days left based on status
                if (realStatus.toLowerCase() === 'critical') {
                    realDaysLeft = '0-2 days';
                } else if (realStatus.toLowerCase() === 'low') {
                    realDaysLeft = '3-7 days';
                } else {
                    realDaysLeft = '7+ days';
                }
            }
            
            // Update modal with real data
            const currentUnitsEl = document.getElementById('currentUnits');
            const currentStatusEl = document.getElementById('currentStatus');
            const nextExpiryEl = document.getElementById('nextExpiry');
            const daysLeftEl = document.getElementById('daysLeft');
            const recentActivityEl = document.getElementById('recentActivity');
            
            if (currentUnitsEl) currentUnitsEl.textContent = `${realUnits} units`;
            if (currentStatusEl) {
                currentStatusEl.textContent = realStatus;
                if (realStatus.toLowerCase() === 'good') {
                    currentStatusEl.className = 'badge bg-success';
                } else if (realStatus.toLowerCase() === 'low') {
                    currentStatusEl.className = 'badge bg-warning';
                } else {
                    currentStatusEl.className = 'badge bg-danger';
                }
            }
            if (nextExpiryEl) nextExpiryEl.textContent = realExpiry;
            if (daysLeftEl) {
                daysLeftEl.textContent = realDaysLeft;
                if (realStatus.toLowerCase() === 'good') {
                    daysLeftEl.className = 'badge bg-success';
                } else if (realStatus.toLowerCase() === 'low') {
                    daysLeftEl.className = 'badge bg-warning';
                } else {
                    daysLeftEl.className = 'badge bg-danger';
                }
            }
            if (recentActivityEl) {
                if (realUnits === 0) {
                    recentActivityEl.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>No available units!</strong> This blood type is currently out of stock.
                        </div>
                    `;
                } else {
                    recentActivityEl.innerHTML = `
                        <ul class="list-unstyled">
                            <li><i class="bi bi-plus-circle text-success me-2"></i>Current stock: ${realUnits} units available</li>
                            <li><i class="bi bi-info-circle text-info me-2"></i>Status: ${realStatus}</li>
                            <li><i class="bi bi-calendar text-primary me-2"></i>Next expiry: ${realExpiry}</li>
                        </ul>
                    `;
                }
            }
        }, 500);
    }

    // Show add units modal with pre-selected blood type
    window.showAddUnitsModal = function(bloodType) {
        console.log('showAddUnitsModal called with:', bloodType);
        const modal = document.getElementById('addInventoryModal');
        if (modal) {
            console.log('Modal element found:', modal);
            
            // Reset form first
            const form = document.getElementById('addInventoryForm');
            if (form) {
                form.reset();
                console.log('Form reset');
            }
            
            // Set blood type
            const bloodTypeSelect = document.getElementById('blood_type');
            if (bloodTypeSelect) {
                bloodTypeSelect.value = bloodType;
                console.log('Blood type set to:', bloodType);
            } else {
                console.error('Blood type select element not found');
            }
            
            // Reset expiry date to 35 days from today
            const expiryDateInput = document.getElementById('expiry_date');
            if (expiryDateInput) {
                const today = new Date();
                const futureDate = new Date(today.getTime() + (35 * 24 * 60 * 60 * 1000));
                expiryDateInput.value = futureDate.toISOString().split('T')[0];
                console.log('Expiry date reset to:', futureDate.toISOString().split('T')[0]);
            }
            
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                console.log('Bootstrap Modal available, showing modal...');
                const bootstrapModal = new bootstrap.Modal(modal);
                bootstrapModal.show();
                console.log('Add inventory modal shown via Bootstrap');
            } else {
                console.error('Bootstrap Modal not available for add inventory');
                // Fallback: show modal manually
                console.log('Showing modal manually...');
                modal.style.display = 'block';
                modal.classList.add('show');
                document.body.classList.add('modal-open');
                console.log('Modal shown manually');
            }
        } else {
            console.error('Add inventory modal not found');
        }
    }

    // Show filter modal
    window.showFilterModal = function() {
        const filterModalHtml = `
            <div class="modal fade" id="filterModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Filter Inventory</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Blood Type</label>
                                <select class="form-select" id="filterBloodType">
                                    <option value="">All Blood Types</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="filterStatus">
                                    <option value="">All Status</option>
                                    <option value="good">Good</option>
                                    <option value="low">Low</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">Clear All</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const existingModal = document.getElementById('filterModal');
        if (existingModal) existingModal.remove();
        
        document.body.insertAdjacentHTML('beforeend', filterModalHtml);
        const modal = new bootstrap.Modal(document.getElementById('filterModal'));
        modal.show();
    }

    // Apply filters
    window.applyFilters = function() {
        const bloodType = document.getElementById('filterBloodType').value;
        const status = document.getElementById('filterStatus').value;
        
        document.querySelectorAll('.blood-type-card').forEach(card => {
            let show = true;
            
            if (bloodType) {
                const cardBloodType = card.querySelector('h5').textContent;
                if (cardBloodType !== bloodType) show = false;
            }
            
            if (status) {
                const cardStatus = card.querySelector('.badge').textContent.toLowerCase();
                if (cardStatus !== status) show = false;
            }
            
            card.style.display = show ? 'block' : 'none';
        });
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
        modal.hide();
        showToast('Filters applied successfully!', 'success');
    }

    // Clear all filters
    window.clearFilters = function() {
        document.getElementById('filterBloodType').value = '';
        document.getElementById('filterStatus').value = '';
        
        document.querySelectorAll('.blood-type-card').forEach(card => {
            card.style.display = 'block';
        });
        
        showToast('All filters cleared!', 'info');
    }

    // Show edit modal
    window.showEditModal = function(bloodType, row) {
        const editModalHtml = `
            <div class="modal fade" id="editModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit ${bloodType} Inventory</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Available Units</label>
                                <input type="number" class="form-control" id="editUnits" min="0" value="15">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" id="editNotes" rows="3">Current inventory status</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="saveEdit('${bloodType}')">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const existingModal = document.getElementById('editModal');
        if (existingModal) existingModal.remove();
        
        document.body.insertAdjacentHTML('beforeend', editModalHtml);
        const modal = new bootstrap.Modal(document.getElementById('editModal'));
        modal.show();
    }

    // Save edit changes
    window.saveEdit = function(bloodType) {
        const units = document.getElementById('editUnits').value;
        const notes = document.getElementById('editNotes').value;
        
        showToast(`Updated ${bloodType} inventory: ${units} units`, 'success');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
        modal.hide();
    }

    // Export inventory data
    window.exportInventoryData = function() {
        const table = document.querySelector('.table');
        if (!table) return;
        
        const rows = Array.from(table.querySelectorAll('tr')).map(tr => {
            const cells = Array.from(tr.querySelectorAll('th,td'));
            return cells.map(td => '"' + (td.innerText || '').replace(/"/g, '""') + '"').join(',');
        });
        
        const csv = rows.join('\r\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'inventory_report.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        showToast('Inventory data exported successfully!', 'success');
    }

    // Close details modal
    window.closeDetailsModal = function() {
        console.log('closeDetailsModal called');
        const modal = document.getElementById('detailsModal');
        if (modal) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            } else {
                modal.style.display = 'none';
                modal.classList.remove('show');
                document.body.classList.remove('modal-open');
            }
        }
    };

    // Add units from details modal
    window.addUnitsFromDetails = function(bloodType) {
        console.log('addUnitsFromDetails called with:', bloodType);
        closeDetailsModal();
        setTimeout(() => {
            showAddUnitsModal(bloodType);
        }, 300);
    };

    // Show toast notifications
    window.showToast = function(message, type = 'info') {
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = toastContainer.lastElementChild;
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

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

    // Fix notification dropdown - simple and direct approach
    document.addEventListener('DOMContentLoaded', function() {
        const bellBtn = document.getElementById('nfBellBtn');
        const dropdown = document.getElementById('nfDropdown');
        
        if (bellBtn && dropdown) {
            console.log('Notification dropdown elements found');
            
            // Bell click handler
            bellBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Bell clicked');
                
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    console.log('Closing dropdown');
                } else {
                    dropdown.classList.add('show');
                    console.log('Opening dropdown');
                }
            });
            
            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!bellBtn.contains(e.target) && !dropdown.contains(e.target)) {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                        console.log('Closing dropdown - outside click');
                    }
                }
            });
            
            // Close on notification item click
            dropdown.addEventListener('click', function(e) {
                if (e.target.closest('.list-group-item') || e.target.closest('a')) {
                    dropdown.classList.remove('show');
                    console.log('Closing dropdown - notification click');
                }
            });
            
            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    console.log('Closing dropdown - Escape key');
                }
            });
        } else {
            console.log('Notification dropdown elements not found');
        }
    });
</script>


