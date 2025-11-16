<?php
session_start();
require_once '../../config/db.php';

// Get date range if provided
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Get organization type and ID from session (assuming it's stored there)
$organization_type = 'redcross'; // Default to redcross, can be changed based on user role
$organization_id = 1; // Default to ID 1, can be changed based on logged in user

// Get current inventory status
$current_inventory_query = "SELECT
                           blood_type,
                           SUM(CASE WHEN status = 'Available' THEN units ELSE 0 END) as available_units,
                           SUM(CASE WHEN status = 'Reserved' THEN units ELSE 0 END) as reserved_units,
                           SUM(units) as total_units
                           FROM blood_inventory
                           WHERE organization_type = ? AND organization_id = ?
                           GROUP BY blood_type
                           ORDER BY blood_type";
$current_inventory = executeQuery($current_inventory_query, [$organization_type, $organization_id]);

// Calculate totals
$total_available = 0;
$total_reserved = 0;
$total_units = 0;

foreach ($current_inventory as $item) {
    $total_available += $item['available_units'];
    $total_reserved += $item['reserved_units'];
    $total_units += $item['total_units'];
}

// Get inventory activity during the date range
$inventory_history_query = "SELECT
                          blood_type,
                          SUM(units) as units_added,
                          COUNT(*) as transactions
                          FROM blood_inventory
                          WHERE created_at BETWEEN ? AND ?
                          AND organization_type = ? AND organization_id = ?
                          GROUP BY blood_type
                          ORDER BY blood_type";
$inventory_history = executeQuery($inventory_history_query, [$start_date, $end_date, $organization_type, $organization_id]);

// Get blood requests during the date range
$blood_requests_query = "SELECT
                        blood_type,
                        COUNT(*) as request_count,
                        SUM(units_requested) as units_requested,
                        SUM(CASE WHEN status = 'Approved' OR status = 'Fulfilled' THEN units_requested ELSE 0 END) as units_approved
                        FROM blood_requests
                        WHERE request_date BETWEEN ? AND ?
                        AND organization_type = ?
                        GROUP BY blood_type
                        ORDER BY blood_type";
$blood_requests = executeQuery($blood_requests_query, [$start_date, $end_date, $organization_type]);

// Get expired inventory during the date range
$expired_inventory_query = "SELECT
                          blood_type,
                          SUM(units) as expired_units,
                          COUNT(*) as expired_count
                          FROM blood_inventory
                          WHERE (status = 'Expired' OR expiry_date < CURDATE())
                          AND (updated_at BETWEEN ? AND ? OR expiry_date BETWEEN ? AND ?)
                          AND organization_type = ? AND organization_id = ?
                          GROUP BY blood_type
                          ORDER BY blood_type";
$expired_inventory = executeQuery($expired_inventory_query, [$start_date, $end_date, $start_date, $end_date, $organization_type, $organization_id]);

// Get all blood types
$blood_types_query = "SELECT DISTINCT blood_type FROM blood_inventory ORDER BY blood_type";
$blood_types_result = executeQuery($blood_types_query);
$blood_types = [];
foreach ($blood_types_result as $row) {
    $blood_types[] = $row['blood_type'];
}

// Calculate totals for activity
$total_added = 0;
$total_requested = 0;
$total_approved = 0;
$total_expired = 0;
$total_net_change = 0;

foreach ($blood_types as $blood_type) {
    $units_added = 0;
    $units_requested = 0;
    $units_approved = 0;
    $units_expired = 0;

    // Find data for this blood type
    foreach ($inventory_history as $item) {
        if ($item['blood_type'] === $blood_type) {
            $units_added = $item['units_added'];
            break;
        }
    }

    foreach ($blood_requests as $item) {
        if ($item['blood_type'] === $blood_type) {
            $units_requested = $item['units_requested'];
            $units_approved = $item['units_approved'];
            break;
        }
    }

    foreach ($expired_inventory as $item) {
        if ($item['blood_type'] === $blood_type) {
            $units_expired = $item['expired_units'];
            break;
        }
    }

    $total_added += $units_added;
    $total_requested += $units_requested;
    $total_approved += $units_approved;
    $total_expired += $units_expired;

    $net_change = $units_added - $units_approved - $units_expired;
    $total_net_change += $net_change;
}

$pageTitle = "Blood Inventory Report";
include_once '../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">


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
<body>

<!-- Main content area -->
<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../../dashboard/index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="inventory.php">Blood Inventory</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Inventory Report</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Blood Inventory Report</h5>
                <div>
                    <button class="btn btn-sm btn-light me-2" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button class="btn btn-sm btn-light" id="exportCSV">
                        <i class="bi bi-file-earmark-excel"></i> Export CSV
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="" class="row mb-4">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date:</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date:</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </form>

                <div class="alert alert-info">
                    <strong>Report Period:</strong> <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Current Inventory Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="inventoryStatusChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Inventory Activity</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="activityChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="border-bottom pb-2">Current Inventory Status</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Blood Type</th>
                                        <th>Available Units</th>
                                        <th>Reserved Units</th>
                                        <th>Total Units</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($current_inventory as $item): ?>
                                        <tr>
                                            <td><strong><?php echo $item['blood_type']; ?></strong></td>
                                            <td><?php echo $item['available_units']; ?></td>
                                            <td><?php echo $item['reserved_units']; ?></td>
                                            <td><?php echo $item['total_units']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-primary">
                                        <td><strong>Total</strong></td>
                                        <td><strong><?php echo $total_available; ?></strong></td>
                                        <td><strong><?php echo $total_reserved; ?></strong></td>
                                        <td><strong><?php echo $total_units; ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <h5 class="border-bottom pb-2">Inventory Activity (<?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?>)</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Blood Type</th>
                                        <th>Units Added</th>
                                        <th>Units Requested</th>
                                        <th>Units Approved</th>
                                        <th>Units Expired</th>
                                        <th>Net Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($blood_types as $blood_type): ?>
                                        <?php
                                        // Find data for this blood type
                                        $units_added = 0;
                                        $units_requested = 0;
                                        $units_approved = 0;
                                        $units_expired = 0;

                                        foreach ($inventory_history as $item) {
                                            if ($item['blood_type'] === $blood_type) {
                                                $units_added = $item['units_added'];
                                                break;
                                            }
                                        }

                                        foreach ($blood_requests as $item) {
                                            if ($item['blood_type'] === $blood_type) {
                                                $units_requested = $item['units_requested'];
                                                $units_approved = $item['units_approved'];
                                                break;
                                            }
                                        }

                                        foreach ($expired_inventory as $item) {
                                            if ($item['blood_type'] === $blood_type) {
                                                $units_expired = $item['expired_units'];
                                                break;
                                            }
                                        }

                                        $net_change = $units_added - $units_approved - $units_expired;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo $blood_type; ?></strong></td>
                                            <td><?php echo $units_added; ?></td>
                                            <td><?php echo $units_requested; ?></td>
                                            <td><?php echo $units_approved; ?></td>
                                            <td><?php echo $units_expired; ?></td>
                                            <td class="<?php echo ($net_change >= 0) ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo ($net_change >= 0) ? '+' . $net_change : $net_change; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-primary">
                                        <td><strong>Total</strong></td>
                                        <td><strong><?php echo $total_added; ?></strong></td>
                                        <td><strong><?php echo $total_requested; ?></strong></td>
                                        <td><strong><?php echo $total_approved; ?></strong></td>
                                        <td><strong><?php echo $total_expired; ?></strong></td>
                                        <td class="<?php echo ($total_net_change >= 0) ? 'text-success' : 'text-danger'; ?>">
                                            <strong><?php echo ($total_net_change >= 0) ? '+' . $total_net_change : $total_net_change; ?></strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inventory Status Chart
        const statusCtx = document.getElementById('inventoryStatusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php
                    foreach ($current_inventory as $item) {
                        echo "'" . $item['blood_type'] . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Available Units',
                    data: [
                        <?php
                        foreach ($current_inventory as $item) {
                            echo $item['available_units'] . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }, {
                    label: 'Reserved Units',
                    data: [
                        <?php
                        foreach ($current_inventory as $item) {
                            echo $item['reserved_units'] . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(255, 159, 64, 0.5)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
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

        // Activity Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php
                    foreach ($blood_types as $blood_type) {
                        echo "'" . $blood_type . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Units Added',
                    data: [
                        <?php
                        foreach ($blood_types as $blood_type) {
                            $units_added = 0;
                            if (!empty($inventory_history)) {
                                foreach ($inventory_history as $item) {
                                    if ($item['blood_type'] === $blood_type) {
                                        $units_added = $item['units_added'];
                                        break;
                                    }
                                }
                            }
                            echo $units_added . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }, {
                    label: 'Units Approved',
                    data: [
                        <?php
                        foreach ($blood_types as $blood_type) {
                            $units_approved = 0;
                            if (!empty($blood_requests)) {
                                foreach ($blood_requests as $item) {
                                    if ($item['blood_type'] === $blood_type) {
                                        $units_approved = $item['units_approved'];
                                        break;
                                    }
                                }
                            }
                            echo $units_approved . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(153, 102, 255, 0.5)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }, {
                    label: 'Units Expired',
                    data: [
                        <?php
                        foreach ($blood_types as $blood_type) {
                            $units_expired = 0;
                            if (!empty($expired_inventory)) {
                                foreach ($expired_inventory as $item) {
                                    if ($item['blood_type'] === $blood_type) {
                                        $units_expired = $item['expired_units'];
                                        break;
                                    }
                                }
                            }
                            echo $units_expired . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(255
