<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

// Set page title
$pageTitle = "Donations Management - Negros First Blood Bank";
$isDashboard = true; // Enable notification dropdown

// Set organization ID for Negros First
$negrosFirstId = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_donation':
                $donor_id = $_POST['donor_id'];
                $blood_type = $_POST['blood_type'];
                $units = $_POST['units'];
                $donation_date = $_POST['donation_date'];
                $notes = $_POST['notes'];

                $query = "INSERT INTO donations (donor_id, blood_type, units, donation_date, notes, center_type) 
                         VALUES (?, ?, ?, ?, ?, 'negrosfirst')";
                executeQuery($query, [$donor_id, $blood_type, $units, $donation_date, $notes]);

                // Update blood inventory
                $inventory_query = "INSERT INTO blood_inventory (blood_type, units, center_type) 
                                  VALUES (?, ?, 'negrosfirst')
                                  ON DUPLICATE KEY UPDATE units = units + ?";
                executeQuery($inventory_query, [$blood_type, $units, $units]);
                break;

            case 'update_donation':
                $donation_id = $_POST['donation_id'];
                $status = $_POST['status'];
                $notes = $_POST['notes'];

                // Update donor_appointments, not donations
                $query = "UPDATE donor_appointments SET status = ?, notes = ? WHERE id = ? AND organization_type = 'negrosfirst'";
                executeQuery($query, [$status, $notes, $donation_id]);
                break;
        }
    }
}

// Get all completed appointments (donation history)
$donations_query = "
    SELECT 
        da.id,
        da.donor_id,
        da.appointment_date,
        da.appointment_time,
        da.location,
        da.notes,
        da.status,
        da.created_at,
        du.name as donor_name,
        du.blood_type,
        COALESCE(d.units, 0) as units_donated
    FROM donor_appointments da
    JOIN donor_users du ON da.donor_id = du.id
    LEFT JOIN donations d ON d.donation_date = da.appointment_date 
        AND d.donor_id = da.donor_id 
        AND d.organization_type = 'negrosfirst' 
        AND d.organization_id = ?
    WHERE da.organization_type = 'negrosfirst'
      AND da.organization_id = ?
      AND da.status = 'Completed'
    ORDER BY da.appointment_date DESC, da.appointment_time DESC
";

$donations = executeQuery($donations_query, [$negrosFirstId, $negrosFirstId]);

// Fix: Ensure $donations is always an array
if (!is_array($donations)) {
    $donations = [];
}

// Get donor statistics (total donation count and total units for each donor)
$donorStats = [];
foreach ($donations as $donation) {
    $donorId = $donation['donor_id'];
    if (!isset($donorStats[$donorId])) {
        // Get total donation count and total units for this donor from donations table
        $stats = getRow("
            SELECT 
                COUNT(*) as total_donations,
                COALESCE(SUM(units), 0) as total_units_donated
            FROM donations
            WHERE donor_id = ? 
            AND organization_type = 'negrosfirst'
            AND organization_id = ?
        ", [$donorId, $negrosFirstId]);
        
        $donorStats[$donorId] = [
            'total_donations' => $stats['total_donations'] ?? 0,
            'total_units_donated' => $stats['total_units_donated'] ?? 0
        ];
    }
}

// Get all donors for dropdown
$donors = executeQuery("
    SELECT id, name, blood_type, phone
    FROM donor_users
    WHERE status = 'active'
    ORDER BY name ASC
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
    }

    .card-header {
        border-bottom: 1px solid var(--gray-200);
        background: var(--white);
    }

    .table thead th {
        background: var(--gray-100);
        color: var(--gray-800);
        border-bottom: 2px solid var(--gray-200);
    }

    .badge {
        border-radius: 20px;
        padding: 0.35rem 0.6rem;
    }

    .modal-content { border-radius: var(--border-radius); box-shadow: var(--box-shadow-lg); }
    .modal-header, .modal-footer { background: var(--white); border-color: var(--gray-200); }
</style>

<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h4 mb-0">Donation History</h2>
            </div>
        </div>
        <!-- Notification Bell -->
        <?php
        // Get notification count
        $notifCount = getCount("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_role = 'negrosfirst' AND is_read = 0", [$negrosFirstId]);
        ?>
        <style>
            .nf-topbar {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1050;
            }
            .nf-dropdown {
                position: absolute;
                top: 100%;
                right: 0;
                width: 350px;
                max-height: 400px;
                display: none;
                margin-top: 8px;
                border-radius: 8px;
            }
            .nf-dropdown.show { display: block; }
            .nf-notif-item { white-space: normal; }
            @media (max-width: 991.98px) { .nf-topbar { top: 12px; right: 15px; } }
        </style>
        <div class="nf-topbar">
            <button id="nfBellBtn" class="btn btn-light position-relative shadow-sm">
                <i class="bi bi-bell"></i>
                <span id="nfBellBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: <?php echo ((int)$notifCount>0)?'inline':'none'; ?>;">
                    <?php echo (int)$notifCount; ?>
                </span>
            </button>
            <div id="nfDropdown" class="nf-dropdown card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Notifications</span>
                    <a class="small text-decoration-none" href="notifications.php">View All</a>
                </div>
                <div id="nfList" class="list-group list-group-flush" style="max-height: 360px; overflow:auto;">
                    <?php
                    $notifications = executeQuery("
                        SELECT * FROM notifications 
                        WHERE user_id = ? AND user_role = 'negrosfirst' 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ", [$negrosFirstId]);
                    
                    if ($notifications && count($notifications) > 0):
                        foreach ($notifications as $notif):
                    ?>
                        <a href="notifications.php" class="list-group-item list-group-item-action nf-notif-item <?php echo !$notif['is_read'] ? 'fw-bold' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($notif['title']); ?></h6>
                                <small><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></small>
                            </div>
                            <p class="mb-1 small"><?php echo htmlspecialchars(substr($notif['message'], 0, 100)) . (strlen($notif['message']) > 100 ? '...' : ''); ?></p>
                        </a>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <div class="list-group-item text-center text-muted">
                            <i class="bi bi-bell-slash fs-4 d-block mb-2"></i>
                            No notifications
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        
        <!-- Controls below header -->
        <div class="p-3 pb-0">
            <div class="d-flex gap-2 justify-content-end">
                <input type="text" id="donationsSearch" class="form-control" placeholder="Search donor..." style="max-width:220px;">
                <button type="button" class="btn btn-outline-secondary" id="exportDonationsCsv">
                    <i class="bi bi-filetype-csv me-1"></i> Export CSV
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="printDonationsReport()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>

        <div class="dashboard-main p-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="donationsTable">
                            <thead>
                                <tr>
                                    <th>Donation Date</th>
                                    <th>Donor Name</th>
                                    <th>Blood Type</th>
                                    <th>Units</th>
                                    <th>Location</th>
                                    <th>Total Donations</th>
                                    <th>Total Units Donated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($donations) > 0): ?>
                                    <?php foreach ($donations as $donation): 
                                        $stats = $donorStats[$donation['donor_id']] ?? ['total_donations' => 0, 'total_units_donated' => 0];
                                    ?>
                                        <tr>
                                            <td>
                                                <div><?php echo date('M d, Y', strtotime($donation['appointment_date'])); ?></div>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($donation['appointment_time'])); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
                                            <td><span class="badge bg-danger"><?php echo htmlspecialchars($donation['blood_type'] ?? 'N/A'); ?></span></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo (int)$donation['units_donated']; ?> unit(s)</span>
                                            </td>
                                            <td><?php echo htmlspecialchars($donation['location'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo (int)$stats['total_donations']; ?> donation(s)</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo (int)$stats['total_units_donated']; ?> unit(s)</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            No donation history found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced search filter (searches across all columns)
    const searchInput = document.getElementById('donationsSearch');
    const table = document.getElementById('donationsTable');
    const tbody = table ? table.querySelector('tbody') : null;
    if (searchInput && tbody) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            tbody.querySelectorAll('tr').forEach(tr => {
                const firstCell = tr.querySelector('td');
                if (firstCell && firstCell.hasAttribute('colspan')) {
                    // Skip the "no records" row - show it only if search is empty
                    tr.style.display = (q === '') ? '' : 'none';
                    return;
                }
                const cells = tr.querySelectorAll('td');
                let found = false;
                cells.forEach(cell => {
                    const txt = cell.textContent.toLowerCase();
                    if (txt.includes(q)) {
                        found = true;
                    }
                });
                tr.style.display = found ? '' : 'none';
            });
        });
    }

    // Export CSV
    const exportBtn = document.getElementById('exportDonationsCsv');
    if (exportBtn && table) {
        exportBtn.addEventListener('click', function() {
            const headers = Array.from(table.querySelectorAll('thead th'))
                .map(th => '"' + (th.innerText || '').replace(/"/g, '""') + '"');
            
            const rows = Array.from(table.querySelectorAll('tbody tr'))
                .filter(tr => tr.style.display !== 'none')
                .map(tr => Array.from(tr.querySelectorAll('td'))
                .map(td => '"' + (td.innerText || '').replace(/"/g, '""') + '"')
                .join(','));
            
            const csv = [headers.join(','), ...rows].join('\r\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'donation_history_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }


    // Fix notification dropdown - simple and direct approach
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

<!-- Universal Print Script -->
<script src="../../assets/js/universal-print.js"></script>

<?php include_once '../../includes/footer.php'; ?>