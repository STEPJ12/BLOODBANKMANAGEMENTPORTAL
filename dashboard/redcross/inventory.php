<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'redcross') {
    header("Location: ../../loginredcross.php?role=redcross");
    exit;
}

// Set page title
$pageTitle = "Blood Inventory";

// Include database connection
require_once '../../config/db.php';
echo '<script src="../../assets/js/universal-print.js"></script>';

// Get Red Cross information
$redcrossId = $_SESSION['user_id'];

// Get blood inventory with proper grouping and calculations
$bloodInventory = executeQuery("
    SELECT 
        blood_type,
        SUM(units) as available_units,
        MIN(expiry_date) AS next_expiry_date,
        DATEDIFF(MIN(expiry_date), CURDATE()) AS days_left
    FROM blood_inventory
    WHERE organization_type = 'redcross' 
      AND organization_id = ? 
      AND status = 'Available'
      AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    GROUP BY blood_type
", [$redcrossId]);

// Calculate totals
$totalAvailable = 0;

foreach ($bloodInventory as $item) {
    $totalAvailable += isset($item['available_units']) ? (int)$item['available_units'] : 0;
}

// Get inventory alerts: low stock, critical stock, expiring soon, and expired
$inventoryAlerts = [];

// Check for low stock (<= 10 units) and critical stock (<= 5 units or 0 units)
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$inventoryMap = [];
foreach ($bloodInventory as $item) {
    $inventoryMap[$item['blood_type']] = [
        'units' => (int)$item['available_units'],
        'days_left' => isset($item['days_left']) ? (int)$item['days_left'] : null,
        'next_expiry_date' => $item['next_expiry_date'] ?? null
    ];
}

foreach ($bloodTypes as $bloodType) {
    $units = isset($inventoryMap[$bloodType]) ? $inventoryMap[$bloodType]['units'] : 0;
    $daysLeft = isset($inventoryMap[$bloodType]) ? $inventoryMap[$bloodType]['days_left'] : null;
    
    if ($units == 0) {
        $inventoryAlerts[] = [
            'type' => 'critical',
            'blood_type' => $bloodType,
            'message' => "{$bloodType} blood is out of stock!",
            'units' => 0
        ];
    } elseif ($units <= 5) {
        $inventoryAlerts[] = [
            'type' => 'critical',
            'blood_type' => $bloodType,
            'message' => "{$bloodType} blood is critically low ({$units} units remaining)!",
            'units' => $units
        ];
    } elseif ($units <= 10) {
        $inventoryAlerts[] = [
            'type' => 'warning',
            'blood_type' => $bloodType,
            'message' => "{$bloodType} blood is running low ({$units} units remaining).",
            'units' => $units
        ];
    }
    
    // Check for expiring soon (within 7 days)
    if ($daysLeft !== null && $daysLeft > 0 && $daysLeft <= 7 && $units > 0) {
        $inventoryAlerts[] = [
            'type' => 'expiring',
            'blood_type' => $bloodType,
            'message' => "{$bloodType} blood will expire in {$daysLeft} day" . ($daysLeft == 1 ? '' : 's') . "!",
            'days_left' => $daysLeft,
            'units' => $units
        ];
    }
}

// Get expired blood inventory
$expiredInventory = executeQuery("
    SELECT 
        blood_type,
        SUM(units) as expired_units,
        COUNT(*) as expired_count
    FROM blood_inventory
    WHERE organization_type = 'redcross' 
      AND organization_id = ? 
      AND status = 'Available'
      AND expiry_date IS NOT NULL
      AND expiry_date < CURDATE()
    GROUP BY blood_type
", [$redcrossId]);

foreach ($expiredInventory as $expired) {
    $inventoryAlerts[] = [
        'type' => 'expired',
        'blood_type' => $expired['blood_type'],
        'message' => "{$expired['blood_type']} blood has expired ({$expired['expired_units']} units). Please remove from inventory.",
        'units' => (int)$expired['expired_units']
    ];
}

// Send inventory alerts as notifications and SMS (only send if there are alerts)
if (!empty($inventoryAlerts)) {
    // Group alerts by type
    $criticalAlerts = array_filter($inventoryAlerts, fn($a) => $a['type'] === 'critical');
    $warningAlerts = array_filter($inventoryAlerts, fn($a) => $a['type'] === 'warning');
    $expiringAlerts = array_filter($inventoryAlerts, fn($a) => $a['type'] === 'expiring');
    $expiredAlerts = array_filter($inventoryAlerts, fn($a) => $a['type'] === 'expired');
    
    // Get Red Cross admin contact info for SMS
    $redcrossAdmin = getRow("SELECT id, name, phone FROM redcross_users WHERE id = ?", [$redcrossId]);
    $adminPhone = $redcrossAdmin['phone'] ?? null;
    
    // Send notifications and SMS for critical alerts
    if (!empty($criticalAlerts)) {
        $criticalMessages = array_map(fn($a) => $a['message'], $criticalAlerts);
        $criticalText = "CRITICAL STOCK ALERT:\n" . implode("\n", $criticalMessages);
        
        // Send app notification (broadcast to all redcross users)
        executeQuery("
            INSERT INTO notifications (title, message, user_id, user_role, is_read, created_at) 
            VALUES (?, ?, 0, 'redcross', 0, NOW())
        ", [
            "Critical Blood Stock Alert",
            $criticalText
        ]);
        
        // Send SMS if phone number exists
        if (!empty($adminPhone)) {
            try {
                require_once '../../includes/sim800c_sms.php';
                if (function_exists('send_sms_sim800c')) {
                    send_sms_sim800c($adminPhone, $criticalText);
                }
            } catch (Exception $e) {
                error_log("Failed to send critical stock SMS: " . $e->getMessage());
            }
        }
    }
    
    // Send notifications and SMS for expired alerts
    if (!empty($expiredAlerts)) {
        $expiredMessages = array_map(fn($a) => $a['message'], $expiredAlerts);
        $expiredText = "EXPIRED BLOOD ALERT:\n" . implode("\n", $expiredMessages);
        
        // Send app notification
        executeQuery("
            INSERT INTO notifications (title, message, user_id, user_role, is_read, created_at) 
            VALUES (?, ?, 0, 'redcross', 0, NOW())
        ", [
            "Expired Blood Inventory Alert",
            $expiredText
        ]);
        
        // Send SMS if phone number exists
        if (!empty($adminPhone)) {
            try {
                require_once '../../includes/sim800c_sms.php';
                if (function_exists('send_sms_sim800c')) {
                    send_sms_sim800c($adminPhone, $expiredText);
                }
            } catch (Exception $e) {
                error_log("Failed to send expired blood SMS: " . $e->getMessage());
            }
        }
    }
    
    // Send notifications for expiring soon (within 7 days) - app notification only
    if (!empty($expiringAlerts)) {
        $expiringMessages = array_map(fn($a) => $a['message'], $expiringAlerts);
        $expiringText = "EXPIRING SOON ALERT:\n" . implode("\n", $expiringMessages);
        
        // Send app notification
        executeQuery("
            INSERT INTO notifications (title, message, user_id, user_role, is_read, created_at) 
            VALUES (?, ?, 0, 'redcross', 0, NOW())
        ", [
            "Blood Expiring Soon Alert",
            $expiringText
        ]);
    }
    
    // Send notifications for low stock warnings - app notification only
    if (!empty($warningAlerts)) {
        $warningMessages = array_map(fn($a) => $a['message'], $warningAlerts);
        $warningText = "LOW STOCK WARNING:\n" . implode("\n", $warningMessages);
        
        // Send app notification
        executeQuery("
            INSERT INTO notifications (title, message, user_id, user_role, is_read, created_at) 
            VALUES (?, ?, 0, 'redcross', 0, NOW())
        ", [
            "Low Stock Warning",
            $warningText
        ]);
    }
}

// Process form submission for adding inventory
$success = false;
$error = "";

// Success feedback via PRG pattern from session
// Note: Don't unset session variables here - they need to be displayed first
if (isset($_SESSION['inventory_message'])) {
    $success = ($_SESSION['inventory_message_type'] ?? '') === 'success';
    $error = ($_SESSION['inventory_message_type'] ?? '') === 'error' ? $_SESSION['inventory_message'] : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inventory']) && !isset($_GET['success'])) {
    // CSRF protection
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form token. Please refresh the page and try again.';
    } else {
    try {
        // Validate input
        $bloodType = sanitize($_POST['blood_type']);
        $units = (int)sanitize($_POST['units']);
        $status = sanitize($_POST['status']);
        // Normalize source to Title Case with single spacing
        $source = normalize_input($_POST['source'] ?? '', true);

        // Additional validation
        if (empty($bloodType) || $units <= 0 || empty($status)) {
            $error = "Please fill in all required fields and ensure units is greater than 0.";
        } else {
            // Insert new inventory with a 35-day expiry date
            $query = "INSERT INTO blood_inventory (organization_type, organization_id, blood_type, units, status, source, expiry_date, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 35 DAY), NOW())";
            $params = ['redcross', $redcrossId, $bloodType, $units, $status, $source];
            
            // Direct database insertion with PDO
            $conn = getConnection();
            $stmt = $conn->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                $_SESSION['inventory_message'] = "Successfully added {$units} units of {$bloodType} blood to inventory.";
                $_SESSION['inventory_message_type'] = 'success';
                header("Location: inventory.php?success=1");
                exit;
            } else {
                $_SESSION['inventory_message'] = "Failed to add inventory. Please try again.";
                $_SESSION['inventory_message_type'] = 'error';
                header("Location: inventory.php?success=1");
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("General error while processing inventory addition: " . $e->getMessage());
        $error = "An error occurred. Please try again.";
    }
    }
}

?>

<?php include_once 'header.php'; ?>

<style>
/* Inventory Page Specific Styles */
.inventory-header-section {
    margin-bottom: 3rem;
}

.inventory-hero {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
    border-radius: 20px;
    padding: 3rem 2.5rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px -12px rgba(220, 20, 60, 0.3);
}

.inventory-hero::before {
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

.hero-content {
    position: relative;
    z-index: 2;
}

.inventory-badge {
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

.inventory-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.2;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.inventory-subtitle {
    font-size: 1.1rem;
    line-height: 1.6;
    opacity: 0.9;
    margin-bottom: 0;
    max-width: 600px;
}

.inventory-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.btn-inventory {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    font-size: 0.95rem;
    justify-content: center;
}

.btn-inventory.btn-primary {
    background: var(--primary-color);
    color: white;
    box-shadow: 0 4px 15px rgba(220, 20, 60, 0.3);
}

.btn-inventory.btn-primary:hover {
    background: var(--accent-color);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(220, 20, 60, 0.4);
}

.btn-inventory.btn-secondary {
    background: transparent;
    color: white;
    border-color: rgba(255, 255, 255, 0.3);
}

.btn-inventory.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: white;
    transform: translateY(-2px);
}

/* Modern Blood Cards */
.blood-grid-section {
    margin-bottom: 3rem;
}

.modern-blood-card {
    background: white;
    border-radius: 16px;
    padding: 0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    border: 2px solid transparent;
    transition: all 0.3s ease;
    overflow: hidden;
    height: 100%;
}

.modern-blood-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.15);
}

.modern-blood-card.status-good {
    border-color: var(--success-color);
}

.modern-blood-card.status-low {
    border-color: var(--warning-color);
}

.modern-blood-card.status-critical {
    border-color: var(--danger-color);
}

.blood-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 1.5rem 0;
}

.blood-type-display {
    font-size: 2rem;
    font-weight: 800;
    color: var(--secondary-color);
}

.status-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.status-good .status-icon {
    background: linear-gradient(135deg, var(--success-color), #2ECC71);
    color: white;
}

.status-low .status-icon {
    background: linear-gradient(135deg, var(--warning-color), #F7DC6F);
    color: white;
}

.status-critical .status-icon {
    background: linear-gradient(135deg, var(--danger-color), #E74C3C);
    color: white;
}

.blood-card-body {
    padding: 1rem 1.5rem 1.5rem;
}

.units-display {
    text-align: center;
    margin-bottom: 1rem;
}

.units-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--primary-color);
    line-height: 1;
}

.units-label {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin-left: 0.5rem;
}

.progress-container {
    margin-bottom: 1rem;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-fill.status-good {
    background: linear-gradient(90deg, var(--success-color), #2ECC71);
}

.progress-fill.status-low {
    background: linear-gradient(90deg, var(--warning-color), #F7DC6F);
}

.progress-fill.status-critical {
    background: linear-gradient(90deg, var(--danger-color), #E74C3C);
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
    width: 100%;
}

.status-badge.status-good {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: var(--success-color);
}

.status-badge.status-low {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: var(--warning-color);
}

.status-badge.status-critical {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: var(--danger-color);
}

/* Summary Cards */
.summary-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    border: none;
    transition: all 0.3s ease;
    height: 100%;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
}

.summary-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.total-units .summary-icon {
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    color: white;
}

.blood-types .summary-icon {
    background: linear-gradient(135deg, var(--info-color), #5DADE2);
    color: white;
}

.critical-levels .summary-icon {
    background: linear-gradient(135deg, var(--warning-color), #F7DC6F);
    color: white;
}

.summary-content {
    flex: 1;
}

.summary-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--secondary-color);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.summary-label {
    font-size: 0.95rem;
    color: var(--gray-600);
    font-weight: 500;
}

/* Modern Table */
.inventory-table-section {
    margin-bottom: 2rem;
}

.table-header {
    margin-bottom: 1.5rem;
}

.table-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--secondary-color);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modern-table-container {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.modern-table thead {
    background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
}

.modern-table th {
    padding: 1.25rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: var(--secondary-color);
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.modern-table td {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.modern-table tbody tr:hover {
    background: var(--gray-50);
}

.modern-table tbody tr:last-child td {
    border-bottom: none;
}

.blood-type-cell {
    display: flex;
    align-items: center;
}

.blood-type-badge {
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
}

.units-cell {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
}

.units-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--secondary-color);
}

.units-text {
    font-size: 0.875rem;
    color: var(--gray-600);
}

.status-badge.badge-success {
    background: linear-gradient(135deg, var(--success-color), #2ECC71);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.badge-warning {
    background: linear-gradient(135deg, var(--warning-color), #F7DC6F);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.badge-danger {
    background: linear-gradient(135deg, var(--danger-color), #E74C3C);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.action-btn {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.action-btn:hover {
    background: var(--accent-color);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 20, 60, 0.3);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-content {
    color: var(--gray-500);
}

.empty-content i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-content p {
    margin: 0;
    font-size: 1rem;
}

/* Responsive Design */
@media (max-width: 992px) {
    .inventory-title {
        font-size: 2rem;
    }
    
    .inventory-subtitle {
        font-size: 1rem;
    }
    
    .inventory-hero {
        padding: 2rem 1.5rem;
    }
    
    .inventory-actions {
        margin-top: 2rem;
    }
}

@media (max-width: 768px) {
    .inventory-title {
        font-size: 1.75rem;
    }
    
    .inventory-actions {
        flex-direction: column;
    }
    
    .btn-inventory {
        justify-content: center;
    }
    
    .modern-table-container {
        overflow-x: auto;
    }
    
    .modern-table th,
    .modern-table td {
        padding: 1rem;
    }
    
    .summary-card {
        padding: 1.5rem;
    }
    
    .summary-number {
        font-size: 2rem;
    }
}
</style>

<!-- Print Table for Inventory (visible only when printing) -->
<div id="print-inventory-table" style="display:none;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
        <img src="../../assets/img/rclgo.png" alt="Red Cross Logo" style="height:50px;">
        <div>
            <div style="font-weight:bold;font-size:1.2rem;">Philippine Red Cross</div>
            <div style="font-size:0.95rem;">Blood Inventory Report &mdash; Generated: <?php echo date('M d, Y g:i A'); ?></div>
        </div>
    </div>
    <table class="table table-bordered" style="width:100%;">
        <thead>
            <tr>
                <th>Blood Type</th>
                <th>Available Units</th>
                <th>Status</th>
                <th>Next Expiry</th>
                <th>Days Left</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($bloodInventory) > 0): ?>
                <?php foreach ($bloodInventory as $item): 
                    $units = isset($item['available_units']) ? (int)$item['available_units'] : 0;
                    $statusText = 'Critical';
                    if ($units > 20) {
                        $statusText = 'Good';
                    } elseif ($units > 10) {
                        $statusText = 'Low';
                    }
                ?>
                <tr>
                    <td><?php echo $item['blood_type']; ?></td>
                    <td><?php echo (int)$units; ?></td>
                    <td><?php echo $statusText; ?></td>
                    <td><?php echo !empty($item['next_expiry_date']) ? $item['next_expiry_date'] : 'N/A'; ?></td>
                    <td><?php echo isset($item['days_left']) ? $item['days_left'] : 'N/A'; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;">No inventory data available.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Display success message if set -->
<?php 
$hasSuccessMessage = false;
$isSuccessType = false;
if (isset($_SESSION['inventory_message'])): 
    $hasSuccessMessage = true;
    $msgType = $_SESSION['inventory_message_type'] ?? 'success';
    $isSuccessType = ($msgType === 'success');
    $msgClass = $msgType === 'error' ? 'danger' : 'success';
    $iconClass = $msgType === 'error' ? 'exclamation-triangle-fill' : 'check-circle-fill';
?>
    <div class="alert alert-<?php echo $msgClass; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $iconClass; ?> me-2"></i>
        <?php echo htmlspecialchars($_SESSION['inventory_message'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['inventory_message'], $_SESSION['inventory_message_type']); ?>
<?php endif; ?>

<!-- Display error message if set -->
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<!-- Blood Inventory Overview -->
<div class="inventory-header-section mb-5">
    <div class="inventory-hero">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <div class="inventory-badge">
                        <i class="bi bi-droplet-fill"></i>
                        <span>Blood Inventory Management</span>
                    </div>
                    <h1 class="inventory-title">Current Blood Stock</h1>
                    <p class="inventory-subtitle">
                        Monitor and manage blood inventory levels to ensure adequate supply for all blood types.
                    </p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="inventory-actions">
                    <button class="btn-inventory btn-primary" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                        <i class="bi bi-plus-circle"></i>
                        Add Inventory
                    </button>
                    <button class="btn-inventory btn-secondary" onclick="printInventoryReport()">
                        <i class="bi bi-printer"></i>
                        Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Blood Type Cards Grid -->
<div class="blood-grid-section mb-5">
    <div class="row g-4">
        <?php
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $inventoryMap = [];

        // Create a map of blood type to units
        foreach ($bloodInventory as $item) {
            $inventoryMap[$item['blood_type']] = $item['available_units'];
        }

        foreach ($bloodTypes as $bloodType):
            $units = isset($inventoryMap[$bloodType]) ? $inventoryMap[$bloodType] : 0;
            $statusClass = 'critical';
            $statusText = 'Critical';
            $iconClass = 'bi-exclamation-triangle-fill';
            $progressWidth = 0;

            if ($units > 20) {
                $statusClass = 'good';
                $statusText = 'Good';
                $iconClass = 'bi-check-circle-fill';
                $progressWidth = 100;
            } elseif ($units > 10) {
                $statusClass = 'low';
                $statusText = 'Low';
                $iconClass = 'bi-exclamation-circle-fill';
                $progressWidth = 60;
            } else {
                $progressWidth = 20;
            }
        ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="modern-blood-card status-<?php echo $statusClass; ?>">
                    <div class="blood-card-header">
                        <div class="blood-type-display"><?php echo $bloodType; ?></div>
                        <div class="status-icon">
                            <i class="bi <?php echo $iconClass; ?>"></i>
                        </div>
                    </div>
                    <div class="blood-card-body">
                        <div class="units-display">
                            <span class="units-number"><?php echo (int)$units; ?></span>
                            <span class="units-label">units</span>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill status-<?php echo $statusClass; ?>" style="width: <?php echo $progressWidth; ?>%"></div>
                            </div>
                        </div>
                        <div class="status-badge status-<?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Inventory Summary Cards -->
<div class="row g-4 mb-5">
    <div class="col-lg-4">
        <div class="summary-card total-units">
            <div class="summary-icon">
                <i class="bi bi-droplet-fill"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number"><?php echo $totalAvailable; ?></div>
                <div class="summary-label">Total Units Available</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="summary-card blood-types">
            <div class="summary-icon">
                <i class="bi bi-collection-fill"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number"><?php echo count($bloodInventory); ?></div>
                <div class="summary-label">Blood Types in Stock</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="summary-card critical-levels">
            <div class="summary-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number">
                    <?php 
                    $criticalCount = 0;
                    $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                    $inventoryMap = [];
                    
                    // Create a map of blood type to units
                    foreach ($bloodInventory as $item) {
                        $inventoryMap[$item['blood_type']] = (int)$item['available_units'];
                    }
                    
                    // Check all blood types for critical levels (<= 10 units)
                    foreach ($bloodTypes as $bloodType) {
                        $units = isset($inventoryMap[$bloodType]) ? $inventoryMap[$bloodType] : 0;
                        if ($units <= 10) {
                            $criticalCount++;
                        }
                    }
                    echo $criticalCount;
                    ?>
                </div>
                <div class="summary-label">Critical Stock Levels</div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Inventory Table -->
<div class="inventory-table-section">
    <div class="table-header">
        <h3 class="table-title">
            <i class="bi bi-table"></i>
            Detailed Inventory Records
        </h3>
    </div>
    <div class="modern-table-container">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Blood Type</th>
                    <th>Available Units</th>
                    <th>Status</th>
                    <th>Next Expiry</th>
                    <th>Days Left</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($bloodInventory) > 0): ?>
                    <?php foreach ($bloodInventory as $item): 
                        $units = isset($item['available_units']) ? (int)$item['available_units'] : 0;
                        $statusClass = 'critical';
                        $statusText = 'Critical';
                        $badgeClass = 'badge-danger';
                        $nextExpiry = !empty($item['next_expiry_date']) ? $item['next_expiry_date'] : null;
                        $daysLeft = isset($item['days_left']) ? (int)$item['days_left'] : null;

                        if ($units > 20) {
                            $statusClass = 'good';
                            $statusText = 'Good';
                            $badgeClass = 'badge-success';
                        } elseif ($units > 10) {
                            $statusClass = 'low';
                            $statusText = 'Low';
                            $badgeClass = 'badge-warning';
                        }
                    ?>
                        <tr>
                            <td>
                                <div class="blood-type-cell">
                                    <span class="blood-type-badge"><?php echo htmlspecialchars($item['blood_type']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="units-cell">
                                    <span class="units-value"><?php echo (int)$units; ?></span>
                                    <span class="units-text">units</span>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                            </td>
                            <td>
                                <?php if ($nextExpiry): ?>
                                    <?php echo date('M d, Y', strtotime($nextExpiry)); ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($daysLeft !== null): ?>
                                    <?php 
                                        $daysBadge = 'badge-success';
                                        if ($daysLeft <= 7) { $daysBadge = 'badge-warning'; }
                                        if ($daysLeft <= 2) { $daysBadge = 'badge-danger'; }
                                    ?>
                                    <span class="status-badge <?php echo $daysBadge; ?>"><?php echo $daysLeft; ?> day<?php echo $daysLeft == 1 ? '' : 's'; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="action-btn" onclick="viewDetails('<?php echo $item['blood_type']; ?>')">
                                    <i class="bi bi-eye"></i>
                                    View Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="empty-state">
                            <div class="empty-content">
                                <i class="bi bi-droplet"></i>
                                <p>No inventory records found.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- Add Inventory Modal -->
<div class="modal fade" id="addInventoryModal" tabindex="-1" aria-labelledby="addInventoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addInventoryModalLabel">Add Blood Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="addInventoryForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                    <div class="mb-3">
                        <label for="blood_type" class="form-label">Blood Type <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lg" id="blood_type" name="blood_type" required>
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
                        <label for="units" class="form-label">Units <span class="text-danger">*</span></label>
                        <input type="number" step="1" class="form-control form-control-lg" id="units" name="units" min="1" required>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lg" id="status" name="status" required>
                            <option value="Available">Available</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="source" class="form-label">Source</label>
                        <input type="text" class="form-control form-control-lg" id="source" name="source" placeholder="e.g., Blood Drive, Direct Donation" data-titlecase="1">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="add_inventory" class="btn btn-danger btn-lg" style="background: var(--primary-color); border-color: var(--primary-color);">
                            <i class="bi bi-plus-circle me-2"></i>Add Inventory
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get the form element
    const form = document.querySelector('#addInventoryForm');

    // If there was a successful submission, close modal and refresh page
    const urlParams = new URLSearchParams(window.location.search);
    <?php if ($hasSuccessMessage && $isSuccessType): ?>
    // Close the modal if it's open
    const addInventoryModal = bootstrap.Modal.getInstance(document.getElementById('addInventoryModal'));
    if (addInventoryModal) {
        addInventoryModal.hide();
    }
    // Scroll to top to show the success message
    window.scrollTo({ top: 0, behavior: 'smooth' });
    // Reload the page after a short delay to refresh the inventory
    setTimeout(() => {
        // Remove success parameter from URL and reload
        window.location.href = window.location.pathname;
    }, 1500);
    <?php endif; ?>

    // Form validation
    form.addEventListener('submit', function(event) {
        let isValid = true;
        const bloodType = document.querySelector('#blood_type').value;
        const units = parseInt(document.querySelector('#units').value);
        const status = document.querySelector('#status').value;

        // Validate blood type
        if (!bloodType) {
            isValid = false;
            document.querySelector('#blood_type').classList.add('is-invalid');
        } else {
            document.querySelector('#blood_type').classList.remove('is-invalid');
        }

        // Validate units
        if (!units || units <= 0) {
            isValid = false;
            document.querySelector('#units').classList.add('is-invalid');
        } else {
            document.querySelector('#units').classList.remove('is-invalid');
        }

        // Validate status
        if (!status) {
            isValid = false;
            document.querySelector('#status').classList.add('is-invalid');
        } else {
            document.querySelector('#status').classList.remove('is-invalid');
        }

        if (!isValid) {
            event.preventDefault();
            event.stopPropagation();
        }

        form.classList.add('was-validated');
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    // Function to view blood type details
    window.viewDetails = function(bloodType) {
        // You can implement a modal or redirect to a details page
        window.location.href = `inventory-details.php?blood_type=${encodeURIComponent(bloodType)}`;
    }

    // Clean "Source" input: only letters/spaces, sentence case, but allow multiple spaces
    const sourceInput = document.getElementById('source');
    if (sourceInput) {
        sourceInput.addEventListener('input', function() {
            // Allow letters and spaces, sentence case, but allow multiple spaces
            let value = this.value.replace(/[^a-zA-Z\s]/g, '').replace(/\s{2,}/g, ' ').trimStart();
            if (value.length > 0) {
                value = value.charAt(0).toUpperCase() + value.slice(1);
            }
            this.value = value;
        });
    }

    // Clean "Units" input: only numbers
    const unitsInput = document.getElementById('units');
    if (unitsInput) {
        unitsInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    }
});

// Show print table only when printing
window.addEventListener('beforeprint', function() {
    document.getElementById('print-inventory-table').style.display = 'block';
});
window.addEventListener('afterprint', function() {
    document.getElementById('print-inventory-table').style.display = 'none';
});
</script>
<script>
  // Defensive: append CSRF token to any POST form without one
  document.addEventListener('DOMContentLoaded', function(){
    var csrf = '<?php echo htmlspecialchars(get_csrf_token()); ?>';
    document.querySelectorAll('form').forEach(function(f){
      if ((f.method || '').toUpperCase() === 'POST' && !f.querySelector('input[name="csrf_token"]')){
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'csrf_token';
        inp.value = csrf;
        f.appendChild(inp);
      }
    });
  });
</script>
<!-- Include print utilities -->
<script src="../../assets/js/print-utils.js"></script>
<style>
@media print {
    html, body {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    #print-inventory-table {
        display: block !important;
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        margin: 0;
        padding: 0;
        z-index: 9999;
    }
    body > *:not(#print-inventory-table) {
        display: none !important;
    }
    #print-inventory-table img {
        max-height: 50px;
    }
    #print-inventory-table table {
        font-size: 1rem;
        border-collapse: collapse;
        width: 100%;
    }
    #print-inventory-table th, #print-inventory-table td {
        border: 1px solid #333 !important;
        padding: 8px !important;
        text-align: left;
    }
}
</style>
<script src="../../assets/js/titlecase-formatter.js"></script>
</body>
</html>
