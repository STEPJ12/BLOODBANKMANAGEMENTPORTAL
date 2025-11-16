<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

// Get blood type from URL parameter
$blood_type = isset($_GET['blood_type']) ? sanitize($_GET['blood_type']) : '';

// Validate blood type
$valid_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
if (empty($blood_type) || !in_array($blood_type, $valid_blood_types)) {
    // Redirect to inventory page if blood type is invalid
    header('Location: enhanced-inventory.php');
    exit;
}

// Get organization type and ID from session
$organization_type = 'negrosfirst';
$organization_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// Get inventory details for the selected blood type - ORDERED BY EXPIRY DATE (FIFO)
// This ensures First In First Out based on expiry date
$inventory_query = "SELECT id, units, status, source, expiry_date, created_at, updated_at
                   FROM blood_inventory
                   WHERE blood_type = ?
                   AND organization_type = ?
                   AND organization_id = ?
                   AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                   ORDER BY (expiry_date IS NULL) ASC, expiry_date ASC, created_at ASC";
$inventory_result = executeQuery($inventory_query, [$blood_type, $organization_type, $organization_id]);

// Calculate inventory summary
$total_units = 0;
$available_units = 0;
$used_units = 0;
$next_expiry_date = null;
$next_expiry_days_left = null;

foreach ($inventory_result as $item) {
    $total_units += $item['units'];

    if ($item['status'] === 'Available') {
        $available_units += $item['units'];
        // Track next expiry among available units
        if (!empty($item['expiry_date'])) {
            if ($next_expiry_date === null || strtotime($item['expiry_date']) < strtotime($next_expiry_date)) {
                $next_expiry_date = $item['expiry_date'];
            }
        }
    } elseif ($item['status'] === 'Used') {
        $used_units += $item['units'];
    }
}

// Compute days left for the next expiry
if ($next_expiry_date !== null) {
    $today = new DateTime(date('Y-m-d'));
    $expiryDt = new DateTime($next_expiry_date);
    $next_expiry_days_left = (int)$expiryDt->diff($today)->format('%r%a');
    $next_expiry_days_left = -$next_expiry_days_left; // convert to days until expiry
}

// Get recent donations of this blood type
$donations_query = "SELECT d.id, CONCAT(du.first_name, ' ', du.last_name) as donor_name,
                   d.donation_date, d.units, d.status
                   FROM donations d
                   JOIN donor_users du ON d.donor_id = du.id
                   WHERE d.blood_type = ?
                   AND d.organization_type = ?
                   ORDER BY d.donation_date DESC
                   LIMIT 5";
$recent_donations = executeQuery($donations_query, [$blood_type, $organization_type]);

// Get recent requests for this blood type
$requests_query = "SELECT br.id, br.requester_name, br.request_date,
                  br.units_requested, br.status, br.priority
                  FROM blood_requests br
                  WHERE br.blood_type = ?
                  AND br.organization_type = ?
                  ORDER BY br.request_date DESC
                  LIMIT 5";
$recent_requests = executeQuery($requests_query, [$blood_type, $organization_type]);

// Get monthly inventory history for the past 6 months
$history_query = "SELECT
                 DATE_FORMAT(created_at, '%Y-%m') as month,
                 SUM(CASE WHEN status = 'Available' OR status = 'Reserved' OR status = 'Used' THEN units ELSE 0 END) as units_added,
                 COUNT(*) as transactions
                 FROM blood_inventory
                 WHERE blood_type = ?
                 AND organization_type = ?
                 AND organization_id = ?
                 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                 ORDER BY month";
$inventory_history = executeQuery($history_query, [$blood_type, $organization_type, $organization_id]);

// Format history data for chart
$history_months = [];
$history_units = [];

foreach ($inventory_history as $history) {
    $month_date = new DateTime($history['month'] . '-01');
    $history_months[] = $month_date->format('M Y');
    $history_units[] = $history['units_added'];
}

$pageTitle = "$blood_type Blood Inventory Details - Negros First";
$isDashboard = true; // Enable notification dropdown
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
    .badge.Available {
        background-color: #28a745;
        color: white;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-weight: 600;
    }
    .badge.Reserved {
        background-color: #ffc107;
        color: black;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-weight: 600;
    }
    .badge.Used {
        background-color: #6c757d;
        color: white;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-weight: 600;
    }
    .badge.Expired {
        background-color: #dc3545;
        color: white;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-weight: 600;
    }
    .blood-type-badge {
        font-size: 2.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 700;
    }
    .stat-card {
        border-left: 4px solid #0d6efd;
        transition: transform 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border-radius: 12px;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
    }
    .stat-card.available {
        border-left-color: #28a745;
    }
    .stat-card.reserved {
        border-left-color: #ffc107;
    }
    .stat-card.expired {
        border-left-color: #dc3545;
    }
    .stat-card.used {
        border-left-color: #6c757d;
    }
    
    .fifo-indicator {
        background: linear-gradient(135deg, #1a365d 0%, #e53e3e 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .fifo-badge {
        background: #38a169;
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }
</style>

<div class="dashboard-container">
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h4 mb-1 fw-bold"><?php echo $blood_type; ?> Blood Inventory Details</h2>
                    <p class="text-muted mb-0">Comprehensive information about <?php echo $blood_type; ?> blood inventory with FIFO ordering.</p>
                </div>
                <div>
                    <a href="enhanced-inventory.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Inventory
                    </a>
                </div>
            </div>
        </div>

        <div class="dashboard-main p-4">
            <!-- FIFO Indicator -->
            <div class="fifo-indicator">
                <i class="bi bi-arrow-down-circle"></i>
                <span>Inventory displayed in FIFO order (First In, First Out based on expiry date)</span>
                <span class="fifo-badge">FIFO</span>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stat-card available">
                        <div class="card-body">
                            <h5 class="card-title">Available Units</h5>
                            <h2 class="display-4"><?php echo $available_units; ?></h2>
                            <p class="text-muted mb-0">Units ready for use</p>
                        </div>
                    </div>
                </div>
          
                <div class="col-md-4">
                    <div class="card stat-card used">
                        <div class="card-body">
                            <h5 class="card-title">Used Units</h5>
                            <h2 class="display-4"><?php echo $used_units; ?></h2>
                            <p class="text-muted mb-0">Units used for transfusions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card <?php echo ($next_expiry_days_left !== null && $next_expiry_days_left <= 7) ? 'expired' : 'available'; ?>">
                        <div class="card-body">
                            <h5 class="card-title">Next Expiry</h5>
                            <h4 class="mb-1"><?php echo $next_expiry_date ? date('M d, Y', strtotime($next_expiry_date)) : 'N/A'; ?></h4>
                            <p class="text-muted mb-0">
                                <?php if ($next_expiry_days_left !== null): ?>
                                    <?php
                                        $badgeClass = 'badge bg-success';
                                        if ($next_expiry_days_left <= 7) { $badgeClass = 'badge bg-warning text-dark'; }
                                        if ($next_expiry_days_left <= 2) { $badgeClass = 'badge bg-danger'; }
                                    ?>
                                    <span class="<?php echo $badgeClass; ?>"><?php echo $next_expiry_days_left; ?> day<?php echo $next_expiry_days_left == 1 ? '' : 's'; ?> left</span>
                                <?php else: ?>
                                    <span class="text-muted">No upcoming expiry</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory History Charts -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Inventory History (Past 6 Months)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="inventoryHistoryChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Inventory Status</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="inventoryStatusChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Inventory Table (FIFO Ordered) -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo $blood_type; ?> Blood Inventory Batches (FIFO Order)</h5>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Batches are ordered by earliest expiry date first
                            </small>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($inventory_result)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Batch ID</th>
                                                <th>Units</th>
                                                <th>Status</th>
                                                <th>Expiry Date</th>
                                                <th>Days Left</th>
                                                <th>Source</th>
                                                <th>Added On</th>
                                                <th>Last Updated</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inventory_result as $item): ?>
                                                <tr>
                                                    <td><?php echo $item['id']; ?></td>
                                                    <td><?php echo $item['units']; ?> units</td>
                                                    <td>
                                                        <span class="badge <?php echo $item['status']; ?>">
                                                            <?php echo $item['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($item['expiry_date'])): ?>
                                                            <?php echo date('M d, Y', strtotime($item['expiry_date'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($item['expiry_date'])): ?>
                                                            <?php 
                                                                $dleft = (int)((strtotime($item['expiry_date']) - strtotime(date('Y-m-d'))) / 86400);
                                                                $dBadge = 'badge bg-success';
                                                                if ($dleft <= 7) { $dBadge = 'badge bg-warning text-dark'; }
                                                                if ($dleft <= 2) { $dBadge = 'badge bg-danger'; }
                                                                if ($dleft < 0) { $dBadge = 'badge bg-danger'; $dleft = 'Expired'; }
                                                            ?>
                                                            <span class="<?php echo $dBadge; ?>">
                                                                <?php echo is_numeric($dleft) ? $dleft . ' day' . ($dleft == 1 ? '' : 's') : $dleft; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo !empty($item['source']) ? htmlspecialchars($item['source']) : 'N/A'; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($item['updated_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No inventory data available for <?php echo $blood_type; ?> blood.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Donations and Requests -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Donations</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_donations)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Donor</th>
                                                <th>Date</th>
                                                <th>Units</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_donations as $donation): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                                    <td><?php echo $donation['units']; ?></td>
                                                    <td>
                                                        <span class="badge bg-success"><?php echo $donation['status']; ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No recent donations of <?php echo $blood_type; ?> blood.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Requests</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_requests)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Requester</th>
                                                <th>Date</th>
                                                <th>Units</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_requests as $request): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                                    <td><?php echo $request['units_requested']; ?></td>
                                                    <td>
                                                        <?php if ($request['priority'] === 'High'): ?>
                                                            <span class="badge bg-danger">High</span>
                                                        <?php elseif ($request['priority'] === 'Medium'): ?>
                                                            <span class="badge bg-warning text-dark">Medium</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info">Normal</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($request['status'] === 'Approved'): ?>
                                                            <span class="badge bg-success">Approved</span>
                                                        <?php elseif ($request['status'] === 'Pending'): ?>
                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                        <?php elseif ($request['status'] === 'Fulfilled'): ?>
                                                            <span class="badge bg-primary">Fulfilled</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Rejected</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No recent requests for <?php echo $blood_type; ?> blood.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inventory History Chart
    const historyCtx = document.getElementById('inventoryHistoryChart');
    if (historyCtx) {
        const historyChart = new Chart(historyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($history_months); ?>,
                datasets: [{
                    label: 'Units Added',
                    data: <?php echo json_encode($history_units); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Inventory Status Chart
    const statusCtx = document.getElementById('inventoryStatusChart');
    if (statusCtx) {
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: ['Available', 'Used'],
                datasets: [{
                    data: [<?php echo $available_units; ?>, <?php echo $used_units; ?>],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(108, 117, 125, 0.7)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }
});
</script>

</body>
</html>

