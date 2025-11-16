<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../login.php?role=patient");
    exit;
}

// Set page title
$pageTitle = "Combined History - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get patient information
$patientId = $_SESSION['user_id'];
$patient = getRow("SELECT * FROM patient_users WHERE id = ?", [$patientId]);

// Check if patient is also a donor
$isDonor = getRow("SELECT * FROM donor_users WHERE email = ?", [$patient['email']]);

// Get blood request history
$requestHistory = executeQuery("
    SELECT * FROM blood_requests
    WHERE patient_id = ?
    ORDER BY request_date DESC
", [$patientId]);

// Get donation history if user is also a donor
$donationHistory = [];
if ($isDonor) {
    $donationHistory = executeQuery("
        SELECT d.*, bd.title as blood_drive_title,
        CASE
            WHEN d.organization_type = 'redcross' THEN 'Red Cross'
            WHEN d.organization_type = 'negrosfirst' THEN 'Negros First'
            ELSE d.organization_type
        END as organization_name
        FROM donations d
        LEFT JOIN blood_drives bd ON d.blood_drive_id = bd.id
        WHERE d.donor_id = ?
        ORDER BY d.donation_date DESC
    ", [$isDonor['id']]);
}

// Combined frequency tracking
$combinedStats = [
    'is_donor' => !empty($isDonor),
    'total_requests' => count($requestHistory),
    'total_donations' => count($donationHistory),
    'total_units_requested' => array_sum(array_column($requestHistory ?: [], 'units_requested')),
    'total_units_approved' => array_sum(array_column($requestHistory ?: [], 'units_approved')),
    'total_units_donated' => array_sum(array_column($donationHistory ?: [], 'units')),
    'net_blood_impact' => 0,
    'user_category' => 'Patient Only',
    'requests_this_year' => 0,
    'donations_this_year' => 0,
    'last_request' => !empty($requestHistory) ? $requestHistory[0]['request_date'] : null,
    'last_donation' => !empty($donationHistory) ? $donationHistory[0]['donation_date'] : null
];

// Calculate net blood impact (donations - requests)
$combinedStats['net_blood_impact'] = $combinedStats['total_units_donated'] - $combinedStats['total_units_approved'];

// Calculate yearly activity
$currentYear = date('Y');
if (is_array($requestHistory)) {
    foreach ($requestHistory as $request) {
        if (date('Y', strtotime($request['request_date'])) == $currentYear) {
            $combinedStats['requests_this_year']++;
        }
    }
}

if (is_array($donationHistory)) {
    foreach ($donationHistory as $donation) {
        if (date('Y', strtotime($donation['donation_date'])) == $currentYear) {
            $combinedStats['donations_this_year']++;
        }
    }
}

// Determine user category
if ($combinedStats['is_donor'] && $combinedStats['total_donations'] > 0) {
    if ($combinedStats['net_blood_impact'] > 0) {
        $combinedStats['user_category'] = 'Net Donor';
    } elseif ($combinedStats['net_blood_impact'] < 0) {
        $combinedStats['user_category'] = 'Net Requester';
    } else {
        $combinedStats['user_category'] = 'Balanced User';
    }
} elseif ($combinedStats['is_donor'] && $combinedStats['total_donations'] == 0) {
    $combinedStats['user_category'] = 'Registered Donor (No Donations)';
} else {
    $combinedStats['user_category'] = 'Patient Only';
}

// Get combined timeline (requests and donations)
$combinedTimeline = [];

// Add requests to timeline
foreach ($requestHistory as $request) {
    $combinedTimeline[] = [
        'date' => $request['request_date'],
        'type' => 'request',
        'data' => $request,
        'description' => 'Blood Request - ' . $request['units_requested'] . ' units'
    ];
}

// Add donations to timeline
foreach ($donationHistory as $donation) {
    $combinedTimeline[] = [
        'date' => $donation['donation_date'],
        'type' => 'donation',
        'data' => $donation,
        'description' => 'Blood Donation - ' . $donation['units'] . ' units'
    ];
}

// Sort timeline by date (newest first)
usort($combinedTimeline, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

?>
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Blood Bank Portal - Combined History">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <!-- Universal Print Functions -->
    <script src="../../assets/js/universal-print.js"></script>

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

    <style>
        /* Sage Green & Cream Color Scheme for Patient Portal */
        :root {
            --patient-primary: #10B981; /* Sage Green */
            --patient-primary-dark: #059669;
            --patient-primary-light: #34D399;
            --patient-accent: #6EE7B7; /* Mint Green */
            --patient-accent-dark: #10B981;
            --patient-accent-light: #D1FAE5;
            --patient-cream: #FEFEFE;
            --patient-cream-light: #FFFEF9;
        }
        
        .dashboard-content {
            background-color: #FEFEFE; /* Cream background */
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%); /* Sage Green gradient */
            color: white;
        }
        
        .dashboard-header h2, .dashboard-header h4 {
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
        
        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .breadcrumb-item.active {
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            border: none;
            color: white;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
        }
        
        .text-primary {
            color: #10B981 !important;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: -1.5rem;
            width: 2px;
            background: #D1FAE5;
        }
        
        .timeline-item:last-child::before {
            bottom: 0;
        }
        
        .timeline-marker {
            position: absolute;
            left: 0;
            top: 0.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px;
        }
        
        .timeline-marker.request {
            background: #10B981;
            box-shadow: 0 0 0 2px #10B981;
        }
        
        .timeline-marker.donation {
            background: #34D399;
            box-shadow: 0 0 0 2px #34D399;
        }
        
        .impact-positive {
            color: #10B981;
        }
        
        .impact-negative {
            color: #EF4444;
        }
        
        .impact-neutral {
            color: #6c757d;
        }
        
        /* Text Colors - Sage Green Theme */
        .text-danger {
            color: #10B981 !important;
        }
        
        .text-success {
            color: #10B981 !important;
        }
        
        .text-warning {
            color: #F59E0B !important;
        }
        
        .text-primary {
            color: #10B981 !important;
        }
        
        .display-4.text-danger {
            color: #10B981 !important;
        }
        
        .display-4.text-success {
            color: #10B981 !important;
        }
        
        .display-4.text-warning {
            color: #F59E0B !important;
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
            background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important;
            color: white;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important;
            border: none;
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
            color: white;
        }
    </style>
</head>
<body class="h-100">

<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header p-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <h2 class="h4 mb-0">Combined Blood History</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Combined History</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="dashboard-main">
            <div class="container-fluid px-0">
                <!-- Combined Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="display-4 text-primary mb-2">
                                    <i class="bi bi-person-badge-fill"></i>
                                </div>
                                <h3 class="h5 fw-bold"><?php echo $combinedStats['user_category']; ?></h3>
                                <p class="text-muted">User Category</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="display-4 text-info mb-2">
                                    <i class="bi bi-arrow-up-circle-fill"></i>
                                </div>
                                <h3 class="h2 fw-bold <?php echo $combinedStats['net_blood_impact'] > 0 ? 'impact-positive' : ($combinedStats['net_blood_impact'] < 0 ? 'impact-negative' : 'impact-neutral'); ?>">
                                    <?php echo $combinedStats['net_blood_impact']; ?>
                                </h3>
                                <p class="text-muted">Net Blood Impact</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="display-4 text-success mb-2">
                                    <i class="bi bi-droplet-fill"></i>
                                </div>
                                <h3 class="h2 fw-bold"><?php echo $combinedStats['total_units_donated']; ?></h3>
                                <p class="text-muted">Units Donated</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="display-4 text-warning mb-2">
                                    <i class="bi bi-clipboard-data-fill"></i>
                                </div>
                                <h3 class="h2 fw-bold"><?php echo $combinedStats['total_units_approved']; ?></h3>
                                <p class="text-muted">Units Received</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Yearly Activity -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">This Year's Activity</h5>
                                <div class="row">
                                    <div class="col-6">
                                        <h3 class="text-success"><?php echo $combinedStats['donations_this_year']; ?></h3>
                                        <p class="text-muted">Donations</p>
                                    </div>
                                    <div class="col-6">
                                        <h3 class="text-warning"><?php echo $combinedStats['requests_this_year']; ?></h3>
                                        <p class="text-muted">Requests</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Blood Impact Summary</h5>
                                <p class="mb-2">
                                    <strong>Donated:</strong> <?php echo $combinedStats['total_units_donated']; ?> units
                                </p>
                                <p class="mb-2">
                                    <strong>Received:</strong> <?php echo $combinedStats['total_units_approved']; ?> units
                                </p>
                                <p class="mb-0">
                                    <strong>Net Impact:</strong> 
                                    <span class="<?php echo $combinedStats['net_blood_impact'] > 0 ? 'impact-positive' : ($combinedStats['net_blood_impact'] < 0 ? 'impact-negative' : 'impact-neutral'); ?>">
                                        <?php echo $combinedStats['net_blood_impact']; ?> units
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Combined Timeline -->
                <div class="row g-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-3">
                                <h4 class="card-title mb-0">
                                    <i class="bi bi-clock-history me-2"></i>Combined Timeline
                                </h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($combinedTimeline)): ?>
                                    <div class="timeline">
                                        <?php foreach ($combinedTimeline as $item): ?>
                                            <div class="timeline-item">
                                                <div class="timeline-marker <?php echo $item['type']; ?>"></div>
                                                <div class="card border-0 shadow-sm">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h6 class="mb-1">
                                                                    <?php if ($item['type'] === 'request'): ?>
                                                                        <i class="bi bi-clipboard-data text-warning me-2"></i>
                                                                        Blood Request
                                                                    <?php else: ?>
                                                                        <i class="bi bi-droplet text-success me-2"></i>
                                                                        Blood Donation
                                                                    <?php endif; ?>
                                                                </h6>
                                                                <p class="mb-1"><?php echo $item['description']; ?></p>
                                                                <small class="text-muted">
                                                                    <?php echo date('M d, Y', strtotime($item['date'])); ?>
                                                                    <?php if ($item['type'] === 'donation' && !empty($item['data']['blood_drive_title'])): ?>
                                                                        - <?php echo $item['data']['blood_drive_title']; ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </div>
                                                            <span class="badge bg-<?php echo $item['type'] === 'request' ? 'warning' : 'success'; ?>">
                                                                <?php echo ucfirst($item['type']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-inbox display-4 text-muted"></i>
                                        <h5 class="text-muted mt-3">No Activity Yet</h5>
                                        <p class="text-muted">You haven't made any blood requests or donations yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
