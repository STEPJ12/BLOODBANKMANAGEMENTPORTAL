<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'redcross') {
    header('Location: ../../dashboard/index.php');
    exit;
}
require_once '../../config/db.php';

// Get blood type from URL parameter
$blood_type = isset($_GET['blood_type']) ? sanitize($_GET['blood_type']) : '';

// Validate blood type
$valid_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
if (empty($blood_type) || !in_array($blood_type, $valid_blood_types)) {
    // Redirect to inventory page if blood type is invalid
    header('Location: inventory.php');
    exit;
}

// Get organization type and ID from session
$organization_type = 'redcross';
$organization_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// Get inventory details for the selected blood type
$inventory_query = "SELECT id, units, status, source, expiry_date, created_at, updated_at
                   FROM blood_inventory
                   WHERE blood_type = ?
                   AND organization_type = ?
                   AND organization_id = ?
                   ORDER BY created_at DESC";
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
    $diff = $today->diff($expiryDt);
    // If expiry is in the past, days left could be negative
    $next_expiry_days_left = (int)$expiryDt->diff($today)->format('%r%a');
    // Using signed days: positive if future, negative if past
    $next_expiry_days_left = (int)$expiryDt->diff($today)->format('%r%a');
    $next_expiry_days_left = -$next_expiry_days_left; // convert to days until expiry
}


// Email validation
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Encrypt sensitive data
function encrypt_data($data) {
    $key = 'your-secret-key';
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}
// Format name: Capitalize each word, single space only
function format_name($name) {
    $name = preg_replace('/\s+/', ' ', trim($name));
    return ucwords(strtolower($name));
}

$pageTitle = "$blood_type Blood Inventory Details";
include_once 'header.php';
?>

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
    
</style>

    <div class="container-fluid">
        <div class="row mb-3">
            
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card" style="background: linear-gradient(135deg, #DC143C 0%, #B22222 100%); color: white; border-radius: 16px; box-shadow: 0 8px 20px rgba(220, 20, 60, 0.3);">
                    <div class="card-body" style="padding: 2rem;">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h2 class="mb-2" style="color: white; font-weight: 700;">
                                    <span class="badge bg-white text-danger blood-type-badge"><?php echo $blood_type; ?></span>
                                    <span style="margin-left: 1rem;">Blood Inventory Details</span>
                                </h2>
                                <p class="mb-0" style="color: rgba(255, 255, 255, 0.9); font-size: 1.1rem;">Comprehensive information about <?php echo $blood_type; ?> blood inventory</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-3 mt-md-0">
                                <a href="inventory.php" class="btn btn-light" aria-label="Back to Inventory">
                                    <i class="bi bi-arrow-left"></i> <span class="d-none d-md-inline">Back to Inventory</span>
                                </a>
                                <a href="update-inventory.php?blood_type=<?php echo $blood_type; ?>" class="btn btn-light" aria-label="Update Inventory" style="background: rgba(255, 255, 255, 0.9);">
                                    <i class="bi bi-pencil"></i> <span class="d-none d-md-inline">Update Inventory</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <?php if (!empty($expired_items)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">Expired Units</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Units</th>
                                            <th>Expiry Date</th>
                                            <th>Status</th>
                                            <th>Source</th>
                                            <th>Added On</th>
                                            <th>Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (
                                            $expired_items as $item): ?>
                                            <tr>
                                                <td><?php echo $item['id']; ?></td>
                                                <td><?php echo $item['units']; ?> units</td>
                                                <td><?php echo date('M d, Y', strtotime($item['expiry_date'])); ?></td>
                                                <td><span class="badge bg-danger">Expired</span></td>
                                                <td><?php echo !empty($item['source']) ? $item['source'] : 'N/A'; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($item['updated_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo $blood_type; ?> Blood Inventory Details</h5>
                        <button class="btn btn-sm btn-danger" onclick="printInventoryDetailsReport()">
                            <i class="bi bi-printer me-1"></i> Print Report
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($inventory_result)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
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
                                                        ?>
                                                        <span class="<?php echo $dBadge; ?>"><?php echo $dleft; ?> day<?php echo $dleft == 1 ? '' : 's'; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo !empty($item['source']) ? format_name($item['source']) : 'N/A'; ?></td>
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
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.alert, .feedback-message').forEach(function(el) {
            el.style.display = 'none';
        });
    }, 5000);
});
</script>
<script>
// Print inventory details report function
function printInventoryDetailsReport() {
    const table = document.querySelector('.table-striped');
    if (!table) {
        alert('No inventory data found to print.');
        return;
    }
    
    // Get page title
    const pageTitle = '<?php echo $blood_type; ?> Blood Inventory Details';
    
    // Extract headers (excluding Actions column if exists)
    const headers = Array.from(table.querySelectorAll('thead th'))
        .filter((th, index) => {
            const text = (th.textContent || '').trim().toLowerCase();
            return !text.includes('action');
        })
        .map(th => (th.textContent || '').trim());
    
    // Extract rows (excluding Actions column if exists)
    const rows = Array.from(table.querySelectorAll('tbody tr'))
        .filter(tr => tr.style.display !== 'none')
        .map(tr => {
            const cells = Array.from(tr.querySelectorAll('td'))
                .filter((td, index) => {
                    // Check if corresponding header is Actions
                    const headerRow = table.querySelector('thead tr');
                    if (headerRow) {
                        const headerCells = Array.from(headerRow.querySelectorAll('th'));
                        if (headerCells[index]) {
                            const headerText = (headerCells[index].textContent || '').trim().toLowerCase();
                            return !headerText.includes('action');
                        }
                    }
                    return true;
                })
                .map(td => {
                    // Extract text from badges
                    const badges = td.querySelectorAll('.badge');
                    if (badges.length > 0) {
                        return Array.from(badges).map(b => b.textContent.trim()).join(', ');
                    }
                    return (td.textContent || '').trim();
                });
            return cells;
        });
    
    // Build table HTML
    let tableHTML = '<table style="width:100%; border-collapse:collapse; margin:20px 0; font-size:12px;">';
    
    // Headers
    tableHTML += '<thead><tr>';
    headers.forEach(header => {
        tableHTML += `<th style="background-color:#f8f9fa; padding:12px 8px; border:1px solid #ddd; font-weight:bold; text-align:left;">${header}</th>`;
    });
    tableHTML += '</tr></thead>';
    
    // Rows
    tableHTML += '<tbody>';
    rows.forEach((row, rowIndex) => {
        tableHTML += '<tr>';
        row.forEach((cell, index) => {
            // Style based on column type
            let cellStyle = 'padding:12px 8px; border:1px solid #ddd;';
            
            if (headers[index] && headers[index].toLowerCase().includes('blood type')) {
                cell = `<span style="background-color:#dc3545; color:#fff; padding:4px 8px; border-radius:4px; font-weight:bold;">${cell}</span>`;
            } else if (headers[index] && headers[index].toLowerCase().includes('status')) {
                // Style status badges
                if (cell.toLowerCase().includes('available')) {
                    cell = `<span style="background-color:#28a745; color:#fff; padding:4px 8px; border-radius:4px; font-weight:bold;">${cell}</span>`;
                } else if (cell.toLowerCase().includes('used')) {
                    cell = `<span style="background-color:#6c757d; color:#fff; padding:4px 8px; border-radius:4px; font-weight:bold;">${cell}</span>`;
                }
            }
            
            tableHTML += `<td style="${cellStyle}">${cell || ''}</td>`;
        });
        tableHTML += '</tr>';
        
        // Add page break every 15 rows for better printing
        if ((rowIndex + 1) % 15 === 0 && rowIndex < rows.length - 1) {
            tableHTML += '<tr style="page-break-after:always;"><td colspan="' + headers.length + '"></td></tr>';
        }
    });
    tableHTML += '</tbody></table>';
    
    // Generate print document
    const content = `
        <div style="margin-bottom:20px;">
            <p><strong>Blood Type:</strong> <?php echo htmlspecialchars($blood_type); ?></p>
            <p><strong>Available Units:</strong> <?php echo $available_units; ?> units</p>
            <p><strong>Used Units:</strong> <?php echo $used_units; ?> units</p>
            <p><strong>Total Units:</strong> <?php echo $total_units; ?> units</p>
            <p><strong>Next Expiry:</strong> <?php echo $next_expiry_date ? date('M d, Y', strtotime($next_expiry_date)) : 'N/A'; ?></p>
            <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Total Records:</strong> ${rows.length}</p>
        </div>
        ${tableHTML}
    `;
    
    generatePrintDocument(pageTitle, content);
}

// Feedback message auto-hide
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.alert, .feedback-message').forEach(function(el) {
            el.style.display = 'none';
        });
    }, 5000);
});
</script>
<script src="../../assets/js/universal-print.js"></script>

</body>
</html>

