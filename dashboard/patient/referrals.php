<?php
// filepath: c:\xamppp\htdocs\blood\dashboard\patient\referrals.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../login.php?role=patient");
    exit;
}
require_once '../../config/db.php';

$patientId = $_SESSION['user_id'];
$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : null;

// Fetch referrals for this specific patient
// Based on referrals table structure: id, blood_request_id, barangay_id, status, referral_date, etc.
// Using LEFT JOINs to handle cases where related records might not exist
if ($requestId) {
    $referrals = executeQuery("
        SELECT r.*, 
               br.request_date,
               br.urgency,
               br.units_requested,
               br.blood_type,
               br.status as request_status,
               br.organization_type,
               br.hospital,
               pu.name as patient_name,
               pu.blood_type as patient_blood_type,
               bu.name as barangay_user_name,
               bu.barangay_name,
               bu.email as barangay_email
        FROM referrals r
        INNER JOIN blood_requests br ON r.blood_request_id = br.id
        INNER JOIN patient_users pu ON br.patient_id = pu.id
        LEFT JOIN barangay_users bu ON r.barangay_id = bu.id
        WHERE r.blood_request_id = ? AND br.patient_id = ?
        ORDER BY r.referral_date DESC, r.created_at DESC
    ", [$requestId, $patientId]);
} else {
    // Fetch all referrals for this patient, issued by any barangay
    $referrals = executeQuery("
        SELECT r.*, 
               br.request_date,
               br.urgency,
               br.units_requested,
               br.blood_type,
               br.status as request_status,
               br.required_date,
               br.organization_type,
               br.hospital,
               pu.name as patient_name,
               pu.blood_type as patient_blood_type,
               pu.phone as patient_phone,
               bu.name as barangay_user_name,
               bu.barangay_name,
               bu.email as barangay_email
        FROM referrals r
        INNER JOIN blood_requests br ON r.blood_request_id = br.id
        INNER JOIN patient_users pu ON br.patient_id = pu.id
        LEFT JOIN barangay_users bu ON r.barangay_id = bu.id
        WHERE br.patient_id = ?
        ORDER BY r.referral_date DESC, r.created_at DESC
    ", [$patientId]);
}

// --- Notification logic ---
$newReferral = false;
$unseenIds = [];

// Ensure $referrals is an array to avoid foreach warning
if (!is_array($referrals)) {
    $referrals = [];
}

foreach ($referrals as $referral) {
    if (isset($referral['notified']) && $referral['notified'] == 0) {
        $newReferral = true;
        $unseenIds[] = $referral['id'];
    }
}
// Mark all as notified after displaying
if (!empty($unseenIds)) {
    $ids = implode(',', array_map('intval', $unseenIds));
    updateRow("UPDATE referrals SET notified = 1 WHERE id IN ($ids)");
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">



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
    
    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
    
    <!-- QR Code library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <style>
    /* Red Theme for Referrals Page */
    :root {
        --patient-primary: #DC2626; /* Red */
        --patient-primary-dark: #B91C1C;
        --patient-primary-light: #EF4444;
        --patient-accent: #F87171;
        --patient-accent-dark: #DC2626;
        --patient-accent-light: #FEE2E2;
        --patient-cream: #FEF2F2;
        --patient-cream-light: #FEE2E2;
    }
    
    /* Patient Dashboard Header Styles */
    .dashboard-content {
        margin-left: 280px;
        padding-top: 100px; /* Space for fixed header */
        position: relative;
        background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 50%, #FECACA 100%);
        overflow: hidden;
    }

    .dashboard-content::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(220, 38, 38, 0.1) 0%, transparent 70%);
        border-radius: 50%;
        animation: float 20s ease-in-out infinite;
        z-index: 0;
    }

    .dashboard-content::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -5%;
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(185, 28, 28, 0.08) 0%, transparent 70%);
        border-radius: 50%;
        animation: float 25s ease-in-out infinite reverse;
        z-index: 0;
    }

    @keyframes float {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50% { transform: translate(30px, -30px) rotate(180deg); }
    }

    .dashboard-main {
        position: relative;
        z-index: 1;
    }

    .dashboard-header {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%); /* Red gradient */
        color: white;
        border-bottom: none;
        position: fixed;
        top: 0;
        left: 280px; /* Position after sidebar */
        right: 0;
        z-index: 1021;
        height: 100px;
        box-shadow: 0 4px 20px rgba(220, 38, 38, 0.3);
        padding: 0 2rem;
        overflow: visible;
        display: flex;
        align-items: center;
    }

    .dashboard-header .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
    
    .dashboard-header .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        position: relative;
    }
    
    .dashboard-header .page-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: white;
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
        min-width: 200px;
    }

    .dashboard-header .btn-outline-secondary {
        border-color: rgba(255, 255, 255, 0.3) !important;
        color: white !important;
        background: rgba(255, 255, 255, 0.1) !important;
        padding: 0.625rem 1rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
    }

    .dashboard-header .btn-outline-secondary:hover {
        background: rgba(255, 255, 255, 0.2) !important;
        border-color: rgba(255, 255, 255, 0.4) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .dashboard-header .btn-outline-secondary span {
        color: white !important;
    }

    .dashboard-header .avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.5rem;
    }

    .dashboard-header .avatar i {
        color: white;
        font-size: 1.25rem;
    }

    /* Notification Bell in Header */
    .dashboard-header .notification-bell .btn {
        border-color: rgba(255, 255, 255, 0.3) !important;
        color: white !important;
        background: rgba(255, 255, 255, 0.1) !important;
        padding: 0.625rem 1rem;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .dashboard-header .notification-bell .btn:hover {
        background: rgba(255, 255, 255, 0.2) !important;
        border-color: rgba(255, 255, 255, 0.4) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .dashboard-header .notification-bell .badge {
        background: #EF4444 !important;
        color: white;
    }

    .dashboard-header .notification-bell .btn i {
        color: white !important;
    }
    
    /* Text Colors - Red Theme */
    .text-danger {
        color: #DC2626 !important;
    }
    
    .text-success {
        color: #DC2626 !important;
    }
    
    .text-primary {
        color: #DC2626 !important;
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
        border: none;
        color: white;
    }
    
    .btn-danger:hover {
        background: linear-gradient(135deg, #B91C1C 0%, #991B1B 100%) !important;
        color: white;
    }

    /* Enhanced Card Styles */
    .card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
    }

    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #DC2626, #B91C1C, #991B1B);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.4s ease;
    }

    .card:hover::before {
        transform: scaleX(1);
    }

    .card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 16px 40px rgba(220, 38, 38, 0.2);
    }

    .table {
        border-radius: 12px;
        overflow: hidden;
    }

    .table thead th {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
        color: white;
        font-weight: 600;
        border: none;
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
    }
    
    .page-title {
        color: white !important;
    }
    
    .card h3, .card h4, .card h5 {
        color: #1F2937;
    }
    
    .card p {
        color: #4B5563;
    }
    
    .table {
        color: #1F2937;
    }
    
    .table th {
        color: #374151;
        font-weight: 600;
    }
    
    .badge.bg-danger {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
        color: white;
    }

    .badge.bg-success {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
        color: white;
    }

    .btn-outline-primary {
        border: 2px solid #DC2626;
        color: #DC2626;
    }

    .btn-outline-primary:hover {
        background: #DC2626;
        color: white;
    }

    /* White text and borders in header */
.dashboard-header .btn-outline-secondary {
    border-color: white !important;
    color: white !important;
}

.dashboard-header .btn-outline-secondary:hover {
    border-color: white !important;
    color: white !important;
    background: rgba(255, 255, 255, 0.2) !important;
}

.dashboard-header .btn-outline-secondary span {
    color: white !important;
}

.dashboard-header .btn-outline-secondary i {
    color: white !important;
}

.dashboard-header #notificationDropdown {
    border-color: white !important;
    color: white !important;
}

.dashboard-header #notificationDropdown i {
    color: white !important;
}

.dashboard-header #userDropdown {
    border-color: white !important;
    color: white !important;
}

.dashboard-header #userDropdown span {
    color: white !important;
}

.dashboard-header #userDropdown i {
    color: white !important;
}

.dashboard-header .avatar i {
    color: white !important;
}
    
    .badge.bg-success {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important;
        color: white;
    }
    
    .btn-outline-primary {
        border: 2px solid #10B981;
        color: #10B981;
    }
    
    .btn-outline-primary:hover {
        background: #10B981;
        color: white;
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

    /* Responsive adjustments */
    @media (max-width: 991.98px) {
        .dashboard-content {
            margin-left: 0;
            padding-top: 100px;
        }
        
        .dashboard-header {
            left: 0;
            padding: 1rem;
            height: auto;
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
    }

    @media (max-width: 575.98px) {
        .dashboard-header {
            padding: 0.75rem 1rem;
        }
        
        .dashboard-header .header-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
    </style>
    <!-- PDF export libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>

<div class="dashboard-container">
    <?php include_once '../../includes/sidebar.php'; ?>
    <div class="dashboard-content">
        <div class="dashboard-header">
            <div class="header-content">
                <h2 class="page-title">My Referrals</h2>
                <div class="header-actions">
                    <?php include_once '../../includes/notification_bell.php'; ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar me-2">
                                <i class="bi bi-person-circle fs-4"></i>
                            </div>
                            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Patient'); ?></span>
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
        <div class="dashboard-main p-3">
            <?php if ($newReferral): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-bell-fill"></i>
                    You have a new referral issued by your barangay! <a href="#issued-referrals" class="alert-link">View now</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">Issued Referrals</h5>
                </div>
                <div class="card-body" id="issued-referrals">
                    <?php if ($referrals && count($referrals) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Referral Date</th>
                                        <th>Blood Bank</th>
                                        <th>Units</th>
                                        <th>Status</th>
                                        <th>Referral Document</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($referrals as $referral): ?>
                                        <tr>
                                            <td><?php echo !empty($referral['referral_date']) ? date('M d, Y', strtotime($referral['referral_date'])) : (!empty($referral['created_at']) ? date('M d, Y', strtotime($referral['created_at'])) : 'N/A'); ?></td>
                                            <td>
                                                <div class="fw-medium">
                                                    <?php 
                                                    $orgType = $referral['organization_type'] ?? '';
                                                    if ($orgType === 'redcross') {
                                                        echo 'Red Cross';
                                                    } elseif ($orgType === 'negrosfirst') {
                                                        echo 'Negros First';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </div>
                                                <small class="text-muted"><?php echo htmlspecialchars($referral['barangay_name'] ?? 'Barangay Referral'); ?></small>
                                            </td>
                                            <td><?php echo $referral['units_requested']; ?></td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                switch ($referral['status']) {
                                                    case 'pending': $statusClass = 'bg-warning'; break;
                                                    case 'approved': $statusClass = 'bg-info'; break;
                                                    case 'completed': $statusClass = 'bg-success'; break;
                                                    case 'rejected': $statusClass = 'bg-danger'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($referral['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($referral['referral_document_name'])): ?>
                                                    <a href="download-referral.php?id=<?php echo $referral['id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="bi bi-download"></i> Download
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Not available</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                            </div>
                            <h5>No Referrals Found</h5>
                            <p class="text-muted">You have no issued referrals yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .dashboard-content {
        margin-left: 0;
        padding: 0;
    }
    
    .dashboard-header {
        padding: 1rem;
    }
    
    .dashboard-header .breadcrumb {
        padding-left: 0;
        margin-top: 0.5rem;
    }
    
    .dashboard-header h2 {
        font-size: 1.25rem;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .card {
        margin: 0 0.5rem 1rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
}

@media (max-width: 576px) {
    .dashboard-header {
        padding: 0.75rem;
    }
    
    .dashboard-header h2 {
        font-size: 1.125rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .btn {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
    
    .table th,
    .table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.75rem;
    }
    
    .text-muted {
        font-size: 0.75rem;
    }
}

/* Tablet Responsive */
@media (max-width: 992px) and (min-width: 769px) {
    .card {
        margin-bottom: 1.5rem;
    }
}
</style>

                    </body>
                    </html>
