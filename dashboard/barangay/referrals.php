<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

// Set dashboard flag
$isDashboard = true;
$pageTitle = "Referrals - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get barangay information
$barangayId = $_SESSION['user_id'];
$barangayRow = getRow("SELECT * FROM barangay_users WHERE id = ?", [$barangayId]);
$barangayName = $barangayRow['name'] ?? 'Barangay';
$barangay = $barangayRow; // Keep for backward compatibility

// Get all referrals for the logged-in barangay only
$referrals = executeQuery("
    SELECT r.id, r.blood_request_id, r.barangay_id, 
           r.status AS referral_status,
           r.referral_date, r.referral_document_name, r.referral_document_type, 
           r.referral_document_data, r.created_at, r.updated_at,
           br.request_date, br.urgency, br.units_requested, br.status AS request_status,
           pu.name AS patient_name, pu.blood_type AS patient_blood_type
    FROM referrals r
    JOIN blood_requests br ON r.blood_request_id = br.id
    JOIN patient_users pu ON br.patient_id = pu.id
    WHERE r.barangay_id = ?
    ORDER BY r.referral_date DESC
", [$barangayId]);

if ($referrals === false) {
    die('Query failed. Check your PHP error log for details.');
}
if (empty($referrals)) {
    die('No referrals found for barangay_id: ' . $barangayId);
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>
    <?php
    // Determine the correct path for CSS files - MUST be defined before use
    $basePath = '';
    if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
        $basePath = '../../';
    }
    ?>
    
    <link rel="stylesheet" href="../../css/barangay-portal.css">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $basePath; ?>assets/images/favicon.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons - CDN with fallback -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
   
    <!-- Custom Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/dashboard.css">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    
    <?php include_once 'shared-styles.php'; ?>
    
    <style>
        /* Override table row backgrounds to white - Force all gray to white */
        .table tbody tr,
        .table tbody tr[style*="background"],
        .table tbody tr[style*="background-color"],
        .table tbody tr.bg-light,
        .table tbody tr.bg-gray,
        .table tbody tr.bg-secondary,
        .table tbody tr.bg-dark {
            background: #ffffff !important;
            color: #2a363b !important;
        }
        
        .table tbody tr:hover {
            background: rgba(234, 179, 8, 0.1) !important;
            color: #2a363b !important;
        }
        
        .table tbody td,
        .table tbody td[style*="background"],
        .table tbody td[style*="background-color"] {
            background: transparent !important;
            color: #2a363b !important;
        }
        
        .table tbody td * {
            color: #2a363b !important;
        }
        
        /* Force table body to have white background */
        .table tbody {
            background: #ffffff !important;
        }
        
        /* Override any table row styling that might be gray */
        table.table tbody tr,
        .table-responsive table tbody tr,
        .card-body .table tbody tr,
        .card .table tbody tr,
        .table-hover tbody tr {
            background: #ffffff !important;
            color: #2a363b !important;
        }
        
        /* Force white background on all table elements - highest priority */
        .table tbody tr:nth-child(1),
        .table tbody tr:nth-child(2),
        .table tbody tr:nth-child(3),
        .table tbody tr:nth-child(4),
        .table tbody tr:nth-child(5),
        .table tbody tr:nth-child(6),
        .table tbody tr:nth-child(7),
        .table tbody tr:nth-child(8),
        .table tbody tr:nth-child(9),
        .table tbody tr:nth-child(10),
        .table tbody tr:nth-child(n) {
            background: #ffffff !important;
            color: #2a363b !important;
        }
        
        /* Ensure card body has white background */
        .card-body {
            background: #ffffff !important;
        }
        
        /* Override any inline styles or other CSS that might set gray */
        .table tbody tr[class*="bg-"],
        .table tbody tr[style] {
            background: #ffffff !important;
        }
        
        /* Search bar styling - change from gray to white with blue border */
        #searchReferral {
            background: #ffffff !important;
            color: #2a363b !important;
            border: 2px solid rgba(59, 130, 246, 0.3) !important;
            border-radius: 8px 0 0 8px !important;
            padding: 0.75rem 1rem !important;
            transition: all 0.3s ease !important;
        }
        
        #searchReferral:focus {
            background: #ffffff !important;
            color: #2a363b !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
            outline: none !important;
        }
        
        #searchReferral::placeholder {
            color: #94a3b8 !important;
        }
        
        /* Search button styling - change from gray to blue */
        .input-group .btn-outline-secondary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            border: 2px solid #3b82f6 !important;
            border-left: none !important;
            color: #ffffff !important;
            border-radius: 0 8px 8px 0 !important;
            padding: 0.75rem 1rem !important;
            transition: all 0.3s ease !important;
        }
        
        .input-group .btn-outline-secondary:hover {
            background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%) !important;
            border-color: #eab308 !important;
            color: #1e293b !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(234, 179, 8, 0.4) !important;
        }
        
        .input-group .btn-outline-secondary i {
            color: inherit !important;
        }
        
        
        /* Table header styling */
        .table thead th {
            background: #f8f9fa !important;
            color: #2a363b !important;
            font-weight: 600 !important;
        }
        
        /* Empty state styling - make it visible */
        .table tbody td.text-center,
        .text-center.py-5 {
            background: #ffffff !important;
        }
        
        .text-center .text-muted {
            color: #64748b !important;
        }
        
        .text-center .text-muted i {
            color: #64748b !important;
        }
        
        .text-center h5 {
            color: #2a363b !important;
        }
        
        .text-center p.text-muted {
            color: #64748b !important;
        }
        
        /* Ensure document links are visible */
        .table tbody td a {
            color: #3b82f6 !important;
            text-decoration: none !important;
            font-weight: 500 !important;
        }
        
        .table tbody td a:hover {
            color: #eab308 !important;
            text-decoration: underline !important;
        }
        
        .table tbody td .text-muted {
            color: #64748b !important;
        }
        
        /* Override any gray status badges - change gray to green for fulfilled/completed */
        .table tbody td .badge.bg-secondary,
        .table tbody td .badge[class*="gray"],
        .table tbody td .badge[style*="gray"] {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: #ffffff !important;
            border: none !important;
        }
        
        /* Ensure all status badges are visible */
        .table tbody td .badge {
            font-weight: 500 !important;
            padding: 0.5rem 0.75rem !important;
            border-radius: 8px !important;
        }
        
        .table tbody td .badge.bg-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: #ffffff !important;
        }
        
        .table tbody td .badge.bg-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: #ffffff !important;
        }
        
        .table tbody td .badge.bg-info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            color: #ffffff !important;
        }
        
        .table tbody td .badge.bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            color: #ffffff !important;
        }
    
    /* Keep search and filter on same row */
    @media (max-width: 991.98px) {
        .d-flex.flex-nowrap {
            flex-wrap: wrap !important;
            gap: 0.5rem;
        }
        
        .d-flex.flex-nowrap .input-group {
            width: 100%;
            max-width: 100% !important;
            min-width: 100% !important;
        }
        
    }
    
    /* Print styles for referrals */
    @media print {
        /* Hide non-essential elements */
        .sidebar, .dashboard-header, .breadcrumb, .btn, .card-header .d-flex:first-child,
        .input-group, select, .notification-dropdown, header {
            display: none !important;
        }
        
        /* Show only the referrals table */
        body {
            margin: 0;
            padding: 20px;
            background: white;
            font-size: 12pt;
        }
        
        .dashboard-content {
            margin-left: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
            page-break-inside: avoid;
        }
        
        .card-body {
            padding: 0 !important;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
        }
        
        .table th,
        .table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .table thead {
            background-color: #f8f9fa !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .table thead th {
            font-weight: bold;
            background-color: #f8f9fa !important;
            color: #000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .badge {
            border: 1px solid #000;
            padding: 3px 6px;
            font-weight: normal;
        }
        
        /* Print header */
        .print-header {
            display: block !important;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .print-header h1 {
            margin: 0;
            font-size: 18pt;
            color: #000;
        }
        
        .print-header p {
            margin: 5px 0;
            font-size: 10pt;
            color: #666;
        }
        
        /* Avoid page breaks inside rows */
        .table tbody tr {
            page-break-inside: avoid;
        }
        
        /* Force print background colors */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        @page {
            margin: 1cm;
            size: A4;
        }
    }
    </style>

    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
</head>
<body>

<div class="dashboard-container">
    <?php include_once '../../includes/sidebar.php'; ?>
    <!-- Header positioned beside sidebar -->
    <div class="dashboard-header">
            <!-- Hamburger Menu Button (fallback if JS doesn't create it) -->
            <button class="header-toggle" type="button" aria-label="Toggle sidebar" aria-expanded="false" style="display: none;">
                <i class="bi bi-list"></i>
            </button>
            <div class="container-fluid">
                <div class="row align-items-center g-2">
                    <div class="col-lg-8 col-md-7 col-12">
                        <div class="header-content">
                            <h1 class="mb-1">Referrals Management</h1>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-5 col-12">
                        <div class="header-actions d-flex align-items-center justify-content-end gap-2">
                            <!-- Notification Bell - Moved to left of user dropdown -->
                            <?php include_once '../../includes/notification_bell.php'; ?>
                            
                            <!-- User Dropdown -->
                            <div class="dropdown">
                                <button class="btn user-dropdown dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar me-2">
                                        <i class="bi bi-person-badge-fill"></i>
                                    </div>
                                    <div class="user-info text-start d-none d-md-block">
                                        <span class="fw-medium d-block"><?php echo htmlspecialchars($barangayName); ?></span>
                                        <small class="text-muted">BHW Admin</small>
                                    </div>
                                    <span class="d-md-none fw-medium"><?php echo htmlspecialchars(substr($barangayName, 0, 15)) . (strlen($barangayName) > 15 ? '...' : ''); ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li class="dropdown-header">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-icon-wrapper me-2">
                                                <i class="bi bi-person-badge-fill"></i>
                                            </div>
                                            <div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($barangayName); ?></div>
                                                <small class="text-muted">Barangay Health Worker</small>
                                            </div>
                                        </div>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-gear-fill me-2"></i>Profile Settings</a></li>
                                   
                                    <li><a class="dropdown-item" href="notifications.php"><i class="bi bi-bell-fill me-2"></i>Notifications</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="../../logout.php"><i class="bi bi-power me-2"></i>Log Out</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <div class="dashboard-content">
        <div class="dashboard-main p-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-lg-6">
                            <h5 class="card-title mb-0">Issued Referrals</h5>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="d-flex align-items-center justify-content-lg-end gap-2 flex-nowrap">
                                <div class="input-group" style="flex: 1 1 auto; min-width: 200px; max-width: 280px;">
                                    <input type="text" class="form-control" id="searchReferral" placeholder="Search referrals...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($referrals && count($referrals) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Referral Date</th>
                                        <th>Patient</th>
                                        <th>Blood Type</th>
                                        <th>Units</th>
                                        <th>Document</th>
                                    </tr>
                                </thead>
                                <tbody id="referralsTableBody">
                                    <?php foreach ($referrals as $referral): ?>
                                        <tr data-patient-name="<?php echo strtolower(htmlspecialchars($referral['patient_name'])); ?>" 
                                            data-blood-type="<?php echo strtolower(htmlspecialchars($referral['patient_blood_type'])); ?>">
                                            <td><?php echo date('M d, Y', strtotime($referral['referral_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($referral['patient_name']); ?></td>
                                            <td><span class="badge bg-danger"><?php echo htmlspecialchars($referral['patient_blood_type']); ?></span></td>
                                            <td><?php echo htmlspecialchars($referral['units_requested']); ?></td>
                                            <td>
                                                <?php if (!empty($referral['referral_document_data']) && !empty($referral['referral_document_type'])): ?>
                                                    <?php if (strpos($referral['referral_document_type'], 'image/') === 0): ?>
                                                        <a href="#" class="view-image"
                                                           data-type="<?php echo htmlspecialchars($referral['referral_document_type']); ?>"
                                                           data-img="<?php echo base64_encode($referral['referral_document_data']); ?>"
                                                           data-name="<?php echo htmlspecialchars($referral['referral_document_name'] ?? 'Referral Image'); ?>">
                                                            <img src="data:<?php echo htmlspecialchars($referral['referral_document_type']); ?>;base64,<?php echo base64_encode($referral['referral_document_data']); ?>"
                                                                 alt="Referral Document" style="max-width: 80px; max-height: 80px; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="download_referral_document.php?id=<?php echo $referral['id']; ?>" target="_blank" rel="noopener">
                                                            <?php echo htmlspecialchars($referral['referral_document_name']); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No document</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                            <h5>No Referrals Found</h5>
                            <p class="text-muted">You haven't issued any referrals yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imagePreviewModalLabel">Referral Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewImage" src="" alt="Referral Document" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.15);" />
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Image preview modal
    const imageLinks = document.querySelectorAll('.view-image');
    const modalEl = document.getElementById('imagePreviewModal');
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    const previewImg = document.getElementById('previewImage');
    const titleEl = document.getElementById('imagePreviewModalLabel');

    imageLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            if (!modal || !previewImg) return;
            const mime = this.getAttribute('data-type') || 'image/jpeg';
            const b64 = this.getAttribute('data-img') || '';
            const name = this.getAttribute('data-name') || 'Referral Document';
            previewImg.src = `data:${mime};base64,${b64}`;
            if (titleEl) titleEl.textContent = name;
            modal.show();
        });
    });

    // Search functionality
    const searchInput = document.getElementById('searchReferral');
    const tableBody = document.getElementById('referralsTableBody');
    
    if (!tableBody) return;

    function filterReferrals() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        
        const rows = tableBody.querySelectorAll('tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const patientName = row.getAttribute('data-patient-name') || '';
            const bloodType = row.getAttribute('data-blood-type') || '';
            
            // Check search filter
            const matchesSearch = !searchTerm || 
                                 patientName.includes(searchTerm) || 
                                 bloodType.includes(searchTerm);
            
            // Show/hide row based on search
            if (matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Show message if no results
        let noResultsMsg = tableBody.querySelector('.no-results-message');
        if (visibleCount === 0 && rows.length > 0) {
            // Remove existing message first
            if (noResultsMsg) {
                noResultsMsg.remove();
            }
            // Create new message
            noResultsMsg = document.createElement('tr');
            noResultsMsg.className = 'no-results-message';
            noResultsMsg.innerHTML = `
                <td colspan="5" class="text-center py-4">
                    <i class="bi bi-search text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mb-0 mt-2">No referrals match your search criteria.</p>
                </td>
            `;
            tableBody.appendChild(noResultsMsg);
        } else {
            // Remove message if there are visible results
            if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }
    }

    // Add event listeners
    if (searchInput) {
        searchInput.addEventListener('input', filterReferrals);
        searchInput.addEventListener('keyup', filterReferrals);
    }

    // Clear search when clicking the search button (optional enhancement)
    const searchButton = searchInput ? searchInput.nextElementSibling : null;
    if (searchButton && searchButton.classList.contains('btn')) {
        searchButton.addEventListener('click', function() {
            if (searchInput && searchInput.value.trim()) {
                // Focus on input, don't clear
                searchInput.focus();
            }
        });
    }
});

</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once '../../includes/footer.php'; ?>