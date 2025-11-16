<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

// Set page title
$pageTitle = "Reports - Negros First Blood Bank";

// Include universal print script for proper formatted printing
echo '<script src="../../assets/js/universal-print.js"></script>';

// Use helpers from config/db.php; only define sanitize if it's not already defined
if (!function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

// Get Negros First organization ID
$negrosFirstId = $_SESSION['user_id'];

// Define available report types
$report_types = [
    'donations_by_date' => 'Donations by Date',
    'donations_by_blood_type' => 'Donations by Blood Type',
    'requests_by_date' => 'Blood Requests by Date',
    'requests_by_blood_type' => 'Blood Requests by Blood Type',
    'inventory_status' => 'Current Inventory Status',
    'inventory_trends' => 'Inventory Trends Over Time',
    'donor_statistics' => 'Donor Statistics',
    'appointments_report' => 'Appointments Report',
    'blood_drives_report' => 'Blood Drives Report',
    'fulfillment_rate' => 'Request Completion Rate',
    'expiry_tracking' => 'Blood Expiry Tracking',
    'top_donors' => 'Top Donors'
];

// Get selected report type
$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : '';

// Get date range if provided
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Normalize start/end to full timestamp range for inclusive queries
$start_ts = $start_date . ' 00:00:00';
$end_ts = $end_date . ' 23:59:59';

// Initialize report data
$report_data = [];
$chart_data = [];
$report_title = '';

// Generate report based on type
if (!empty($report_type) && array_key_exists($report_type, $report_types)) {
    $report_title = $report_types[$report_type];

    switch ($report_type) {
        case 'donations_by_date':
            // Get donations grouped by date from actual donations table
            $sql = "SELECT DATE(COALESCE(donation_date, created_at)) as date, COUNT(*) as count, SUM(units) as total_units
                FROM donations
                WHERE organization_type = 'negrosfirst' AND organization_id = ?
                    AND COALESCE(donation_date, created_at) BETWEEN ? AND ?
                    AND status = 'Completed'
                GROUP BY DATE(COALESCE(donation_date, created_at))
                ORDER BY date";
            $report_data = executeQuery($sql, [$negrosFirstId, $start_ts, $end_ts]);
            if (!is_array($report_data)) $report_data = [];

            foreach ($report_data as $row) {
                $chart_data[] = [
                    'date' => date('M d', strtotime($row['date'])),
                    'count' => (int)$row['count'],
                    'units' => (float)$row['total_units']
                ];
            }
            break;

        case 'donations_by_blood_type':
            // Get donations grouped by blood type from actual donations table
            $sql = "SELECT blood_type, COUNT(*) as count, SUM(units) as total_units
                FROM donations
                WHERE organization_type = 'negrosfirst' AND organization_id = ?
                    AND COALESCE(donation_date, created_at) BETWEEN ? AND ?
                    AND status = 'Completed'
                GROUP BY blood_type
                ORDER BY blood_type";
            $report_data = executeQuery($sql, [$negrosFirstId, $start_ts, $end_ts]);
            if (!is_array($report_data)) $report_data = [];

            foreach ($report_data as $row) {
                $chart_data[] = [
                    'blood_type' => $row['blood_type'],
                    'count' => (int)$row['count'],
                    'units' => (float)$row['total_units']
                ];
            }
            break;

        case 'requests_by_date':
            // Get blood requests grouped by date with Negros First filtering
            $sql = "SELECT DATE(COALESCE(request_date, created_at)) as date, COUNT(*) as count, SUM(units_requested) as total_units,
                SUM(CASE WHEN LOWER(TRIM(status)) = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN LOWER(TRIM(status)) = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN LOWER(TRIM(status)) = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN LOWER(TRIM(status)) = 'pending' THEN 1 ELSE 0 END) as pending
                FROM blood_requests
                WHERE organization_type = 'negrosfirst'
                    AND COALESCE(request_date, created_at) BETWEEN ? AND ?
                GROUP BY DATE(COALESCE(request_date, created_at))
                ORDER BY date";
            $report_data = executeQuery($sql, [$start_ts, $end_ts]);
            if (!is_array($report_data)) $report_data = [];

            foreach ($report_data as $row) {
                $chart_data[] = [
                    'date' => date('M d', strtotime($row['date'])),
                    'count' => (int)$row['count'],
                    'units' => (int)$row['total_units'],
                    'approved' => (int)$row['approved'],
                    'rejected' => (int)$row['rejected'],
                    'completed' => (int)$row['completed'],
                    'pending' => (int)$row['pending']
                ];
            }
            break;

        case 'requests_by_blood_type':
            // Get blood requests grouped by blood type with Negros First filtering
            $sql = "SELECT blood_type, COUNT(*) as count, SUM(units_requested) as total_units,
                SUM(CASE WHEN LOWER(TRIM(status)) = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN LOWER(TRIM(status)) = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN LOWER(TRIM(status)) = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN LOWER(TRIM(status)) = 'pending' THEN 1 ELSE 0 END) as pending
                FROM blood_requests
                WHERE organization_type = 'negrosfirst'
                    AND COALESCE(request_date, created_at) BETWEEN ? AND ?
                GROUP BY blood_type
                ORDER BY blood_type";
            $report_data = executeQuery($sql, [$start_ts, $end_ts]);
            if (!is_array($report_data)) $report_data = [];

            foreach ($report_data as $row) {
                $chart_data[] = [
                    'blood_type' => $row['blood_type'],
                    'count' => (int)$row['count'],
                    'units' => (int)$row['total_units'],
                    'approved' => (int)$row['approved'],
                    'rejected' => (int)$row['rejected'],
                    'completed' => (int)$row['completed'],
                    'pending' => (int)$row['pending']
                ];
            }
            break;

        case 'inventory_status':
            // Get current inventory status with Negros First filtering
            $sql = "SELECT blood_type, 
                    SUM(CASE WHEN status = 'Available' THEN units ELSE 0 END) as available_units,
                    SUM(CASE WHEN status = 'Used' THEN units ELSE 0 END) as used_units,
                    SUM(CASE WHEN status = 'Expired' THEN units ELSE 0 END) as expired_units,
                    MAX(updated_at) as last_updated
                FROM blood_inventory
                WHERE organization_type = 'negrosfirst' AND organization_id = ?
                GROUP BY blood_type
                ORDER BY blood_type";
            $report_data = executeQuery($sql, [$negrosFirstId]);
            if (!is_array($report_data)) $report_data = [];

            foreach ($report_data as $row) {
                $chart_data[] = [
                    'blood_type' => $row['blood_type'],
                    'units' => (int)$row['available_units']
                ];
            }
            break;

        case 'inventory_trends':
            // Get inventory trends over time
            $sql = "SELECT DATE(created_at) as date, blood_type, SUM(units) as units, status
                FROM blood_inventory
                WHERE organization_type = 'negrosfirst' AND organization_id = ?
                    AND created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at), blood_type, status
                ORDER BY date, blood_type";
            $report_data = executeQuery($sql, [$negrosFirstId, $start_ts, $end_ts]);
            if (!is_array($report_data)) $report_data = [];

            $grouped_data = [];
            foreach ($report_data as $row) {
                $date = $row['date'];
                if (!isset($grouped_data[$date])) {
                    $grouped_data[$date] = [];
                }
                $grouped_data[$date][$row['blood_type']] = (int)$row['units'];
            }

            foreach ($grouped_data as $date => $blood_types) {
                $chart_data[] = array_merge(['date' => date('M d', strtotime($date))], $blood_types);
            }
            break;

        case 'donor_statistics':
            // Get accurate donor statistics from donor_users table
            $sql = "SELECT 
                    COUNT(*) as total_donors,
                    COUNT(DISTINCT blood_type) as blood_type_count,
                    SUM(CASE WHEN is_eligible = 1 THEN 1 ELSE 0 END) as active_donors,
                    SUM(CASE WHEN is_eligible = 0 THEN 1 ELSE 0 END) as inactive_donors,
                    AVG(donation_count) as avg_donations,
                    MAX(donation_count) as max_donations
                FROM donor_users";
            $donor_stats = getRow($sql);
            if (!is_array($donor_stats)) {
                $donor_stats = ['total_donors' => 0, 'blood_type_count' => 0, 'active_donors' => 0, 'inactive_donors' => 0, 'avg_donations' => 0, 'max_donations' => 0];
            }

            // Get donors by blood type
            $sql = "SELECT blood_type, COUNT(*) as count 
                FROM donor_users 
                WHERE blood_type IS NOT NULL 
                GROUP BY blood_type 
                ORDER BY blood_type";
            $blood_type_data = executeQuery($sql);
            if (!is_array($blood_type_data)) { $blood_type_data = []; }

            foreach ($blood_type_data as $row) {
                $chart_data[] = [
                    'blood_type' => $row['blood_type'] ?? '',
                    'count' => isset($row['count']) ? (int)$row['count'] : 0
                ];
            }

            $report_data = [
                'stats' => [
                    'total_donors' => (int)($donor_stats['total_donors'] ?? 0),
                    'blood_type_count' => (int)($donor_stats['blood_type_count'] ?? 0),
                    'active_donors' => (int)($donor_stats['active_donors'] ?? 0),
                    'inactive_donors' => (int)($donor_stats['inactive_donors'] ?? 0),
                    'avg_donations' => round((float)($donor_stats['avg_donations'] ?? 0), 2),
                    'max_donations' => (int)($donor_stats['max_donations'] ?? 0)
                ],
                'blood_types' => $blood_type_data
            ];
            break;

        case 'appointments_report':
            // Get appointments statistics
            $sql = "SELECT 
                    DATE(appointment_date) as date,
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'No Show' THEN 1 ELSE 0 END) as no_show
                FROM donor_appointments
                WHERE organization_type = 'negrosfirst' AND organization_id = ?
                    AND appointment_date BETWEEN ? AND ?
                GROUP BY DATE(appointment_date)
                ORDER BY date";
            $report_data = executeQuery($sql, [$negrosFirstId, $start_date, $end_date]);
            if (!is_array($report_data)) $report_data = [];

            foreach ($report_data as $row) {
                $chart_data[] = [
                    'date' => date('M d', strtotime($row['date'])),
                    'total' => (int)$row['total_appointments'],
                    'scheduled' => (int)$row['scheduled'],
                    'completed' => (int)$row['completed'],
                    'pending' => (int)$row['pending'],
                    'rejected' => (int)$row['rejected'],
                    'no_show' => (int)$row['no_show']
                ];
            }
            break;

        case 'blood_drives_report':
            // Get blood drives statistics
            $sql = "SELECT 
                    DATE(date) as date,
                    COUNT(*) as total_drives,
                    SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM((SELECT COUNT(*) FROM donor_appointments WHERE blood_drive_id = bd.id)) as total_registrations
                FROM blood_drives bd
                WHERE organization_type = 'negrosfirst' AND organization_id = ?
                    AND date BETWEEN ? AND ?
                GROUP BY DATE(date)
                ORDER BY date";
            $report_data = executeQuery($sql, [$negrosFirstId, $start_date, $end_date]);
            if (!is_array($report_data)) $report_data = [];

            foreach ($report_data as $row) {
                $chart_data[] = [
                    'date' => date('M d', strtotime($row['date'])),
                    'total' => (int)$row['total_drives'],
                    'scheduled' => (int)$row['scheduled'],
                    'completed' => (int)$row['completed'],
                    'cancelled' => (int)$row['cancelled'],
                    'registrations' => (int)$row['total_registrations']
                ];
            }
            break;

        case 'fulfillment_rate':
            // Calculate request completion rate
            $sql = "SELECT 
                    blood_type,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as fulfilled,
                    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(units_requested) as total_units_requested,
                    SUM(CASE WHEN status = 'Completed' THEN units_requested ELSE 0 END) as units_fulfilled
                FROM blood_requests
                WHERE organization_type = 'negrosfirst'
                    AND request_date BETWEEN ? AND ?
                GROUP BY blood_type
                ORDER BY blood_type";
            $report_data = executeQuery($sql, [$start_date, $end_date]);
            if (!is_array($report_data)) $report_data = [];

            foreach ($report_data as $row) {
                $fulfillment_rate = $row['total_requests'] > 0 
                    ? round(($row['fulfilled'] / $row['total_requests']) * 100, 2) 
                    : 0;
                $chart_data[] = [
                    'blood_type' => $row['blood_type'],
                    'total_requests' => (int)$row['total_requests'],
                    'fulfilled' => (int)$row['fulfilled'],
                    'rejected' => (int)$row['rejected'],
                    'fulfillment_rate' => $fulfillment_rate
                ];
            }
            break;

        case 'expiry_tracking':
            // Track blood units expiring soon
            $sql = "SELECT 
                    blood_type,
                    expiry_date,
                    SUM(units) as units,
                    DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
                FROM blood_inventory
                WHERE organization_type = 'negrosfirst' AND organization_id = ?
                    AND status = 'Available'
                    AND expiry_date IS NOT NULL
                    AND expiry_date >= CURDATE()
                GROUP BY blood_type, expiry_date
                HAVING days_until_expiry <= 30
                ORDER BY expiry_date, blood_type";
            $report_data = executeQuery($sql, [$negrosFirstId]);
            if (!is_array($report_data)) $report_data = [];

            foreach ($report_data as $row) {
                $chart_data[] = [
                    'blood_type' => $row['blood_type'],
                    'expiry_date' => $row['expiry_date'],
                    'units' => (int)$row['units'],
                    'days_until_expiry' => (int)$row['days_until_expiry']
                ];
            }
            break;

        case 'top_donors':
            // Get top donors by donation count - FIXED to use name column
            $sql = "SELECT 
                    d.donor_id,
                    du.name as donor_name,
                    du.blood_type,
                    COUNT(*) as donation_count,
                    SUM(d.units) as total_units_donated,
                    MAX(d.donation_date) as last_donation
                FROM donations d
                JOIN donor_users du ON d.donor_id = du.id
                WHERE d.organization_type = 'negrosfirst' AND d.organization_id = ?
                    AND d.status = 'Completed'
                    AND d.donation_date BETWEEN ? AND ?
                GROUP BY d.donor_id, du.name, du.blood_type
                ORDER BY donation_count DESC, total_units_donated DESC
                LIMIT 20";
            $report_data = executeQuery($sql, [$negrosFirstId, $start_date, $end_date]);
            if (!is_array($report_data)) $report_data = [];

            foreach ($report_data as $row) {
                $chart_data[] = [
                    'donor_name' => $row['donor_name'],
                    'blood_type' => $row['blood_type'],
                    'donation_count' => (int)$row['donation_count'],
                    'total_units' => (float)$row['total_units_donated'],
                    'last_donation' => $row['last_donation']
                ];
            }
            break;
    }
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
    
    <!-- Universal Print Script for formatted printing -->
    <script src="../../assets/js/universal-print.js"></script>
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

    /* Reports Header Section */
    .reports-header-section {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        color: white;
        padding: 4rem 0;
        margin-bottom: 3rem;
        position: relative;
        overflow: hidden;
    }

    .reports-hero {
        text-align: center;
        position: relative;
        z-index: 2;
    }

    .reports-hero::before {
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

    .reports-hero h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        position: relative;
        z-index: 2;
    }

    .reports-hero p {
        font-size: 1.1rem;
        opacity: 0.9;
        margin-bottom: 2rem;
        position: relative;
        z-index: 2;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .hero-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .hero-btn {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        cursor: pointer;
    }

    .hero-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        color: white;
        transform: translateY(-2px);
    }

    /* Enhanced Card Styling */
    .reports-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(26, 54, 93, 0.1);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .card-header-custom {
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        color: white;
        padding: 2rem;
        border-radius: 20px 20px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .card-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Report Selection Cards */
    .report-card {
        transition: all 0.3s ease;
        border: 2px solid transparent;
        cursor: pointer;
    }

    .report-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary-color);
        box-shadow: 0 15px 40px rgba(26, 54, 93, 0.15);
    }

    .report-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        font-size: 2rem;
        color: white;
    }

    /* Enhanced Table Styling */
    .enhanced-table {
        margin: 0;
    }

    .enhanced-table thead th {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border: none;
        padding: 1rem;
        font-weight: 600;
        color: var(--gray-700);
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    .enhanced-table tbody tr {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }

    .enhanced-table tbody tr:hover {
        background: rgba(26, 54, 93, 0.02);
    }

    .enhanced-table td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        border: none;
    }

    /* Status Badges */
    .status-badge {
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-danger { background: #f8d7da; color: #721c24; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-success { background: #d4edda; color: #155724; }

    /* Statistics Cards */
    .dashboard-card {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(26, 54, 93, 0.1);
        transition: all 0.3s ease;
        height: 100%;
    }

    .dashboard-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 35px rgba(26, 54, 93, 0.15);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: var(--gray-600);
        font-weight: 500;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    /* Button Enhancements */
    .btn {
        border-radius: 10px;
        font-weight: 600;
        padding: 0.6rem 1.2rem;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-1px);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        border: none;
    }

    .btn-outline-primary {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
    }

    .btn-outline-primary:hover {
        background: var(--primary-color);
        border-color: var(--primary-color);
    }

    /* Print styles */
    @media print {
        .hero-actions,
        .card-header-custom .btn,
        .card-header-custom .d-flex:has(button),
        .reports-header-section .hero-actions,
        .nav, .navbar, .breadcrumb, .sidebar,
        .dashboard-card .stat-icon,
        .table-actions,
        .report-icon,
        .stat-icon,
        .bi,
        canvas,
        .reports-overview,
        .btn,
        .no-print,
        .report-filter,
        form,
        .alert,
        .modal { display: none !important; }

        .card-header-custom {
            display: flex !important;
            background: white !important;
            color: black !important;
            page-break-after: avoid;
        }
        .card-header-custom h3 {
            color: black !important;
        }

        .print-header { 
            display: flex !important;
            page-break-after: avoid;
        }

        body { 
            background: #fff !important; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
            margin: 0;
            padding: 10px;
        }
        
        .reports-card { 
            display: block !important;
            box-shadow: none !important; 
            border: 1px solid #ddd !important; 
            background: #fff !important;
            page-break-inside: avoid;
            width: 100% !important;
            margin: 0 !important;
            padding: 10px !important;
        }
        
        .container-fluid, .p-4, .dashboard-card { 
            box-shadow: none !important; 
            border: 1px solid #ddd !important; 
            background: #fff !important;
            display: block !important;
        }
        
        .table-responsive {
            display: block !important;
            width: 100% !important;
            overflow: visible !important;
        }
        
        .table-responsive table {
            display: table !important;
            width: 100% !important;
        }

        table { 
            border-collapse: collapse !important; 
            width: 100% !important;
            page-break-inside: auto;
            display: table !important;
            font-size: 11px !important;
        }
        
        thead { 
            display: table-header-group !important;
            background: #f9f9f9 !important;
        }
        
        thead th { 
            border: 1px solid #000 !important; 
            background: #f9f9f9 !important; 
            color: #000 !important;
            font-weight: bold !important;
            padding: 8px !important;
        }
        
        tbody {
            display: table-row-group !important;
        }
        
        tbody tr {
            display: table-row !important;
            page-break-inside: avoid;
        }
        
        tbody td { 
            border: 1px solid #000 !important; 
            color: #000 !important;
            padding: 6px !important;
            display: table-cell !important;
        }

        .p-4 { 
            padding: 10px !important; 
            display: block !important;
        }
        
        .mb-4 { 
            margin-bottom: 8px !important; 
        }
        
        .card-title { 
            color: black !important;
            font-size: 18px !important;
            display: block !important;
        }
        
        canvas {
            display: none !important;
        }
    }
    .print-header { display: none; align-items: center; gap: 12px; margin: 0 0 16px 0; }
    .print-header img { height: 48px; width: auto; }
    .print-header .meta { font-size: 12px; color: #666; }
</style>

<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header p-3">
            <h2 class="h4 mb-0">Reports & Analytics</h2>
        </div>

        <div class="dashboard-main p-3">
            <!-- Hero Section -->
            <div class="reports-header-section">
                <div class="reports-hero">
                    <h1><i class="bi bi-graph-up me-3"></i>Reports & Analytics</h1>
                    <p>Generate comprehensive reports and analyze blood donation trends, inventory status, and donor statistics to make informed decisions.</p>
                    <div class="hero-actions">
                        <button class="hero-btn" onclick="window.printReport ? window.printReport() : printReportData()">
                            <i class="bi bi-printer me-2"></i>Print Report
                        </button>
                        <button class="hero-btn" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- Reports Overview Statistics -->
            <div class="row g-4 mb-4 reports-overview">
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card text-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); color: white;">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="stat-number" style="color: var(--primary-color);"><?php echo count($report_types); ?></div>
                        <div class="stat-label">Available Reports</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card text-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                            <i class="bi bi-calendar-range"></i>
                        </div>
                        <div class="stat-number" style="color: #28a745;"><?php echo date('M d'); ?></div>
                        <div class="stat-label">Report Date</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card text-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #6f42c1); color: white;">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                        </div>
                        <div class="stat-number" style="color: #17a2b8;">
                            <?php echo !empty($report_type) ? '1' : '0'; ?>
                        </div>
                        <div class="stat-label">Active Report</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card text-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14); color: white;">
                            <i class="bi bi-download"></i>
                        </div>
                        <div class="stat-number" style="color: #ffc107;">PDF</div>
                        <div class="stat-label">Export Format</div>
                    </div>
                </div>
            </div>

            <?php if (empty($report_type)): ?>
                <!-- Report Selection -->
                <div class="reports-card">
                    <div class="card-header-custom">
                        <h3 class="card-title">
                            <i class="bi bi-graph-up"></i>
                            Select Report Type
                        </h3>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-light" onclick="printReportData()">
                                <i class="bi bi-printer me-1"></i>Print Page
                            </button>
                        </div>
                    </div>
                    <div class="row g-4 p-4">
                        <?php foreach ($report_types as $type => $title): ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="dashboard-card h-100 report-card">
                                    <div class="text-center mb-3">
                                        <div class="report-icon">
                                            <i class="bi bi-<?php 
                                                switch ($type) {
                                                    case 'donations_by_date': echo 'calendar-event'; break;
                                                    case 'donations_by_blood_type': echo 'droplet'; break;
                                                    case 'requests_by_date': echo 'calendar-check'; break;
                                                    case 'requests_by_blood_type': echo 'heart-pulse'; break;
                                                    case 'inventory_status': echo 'box-seam'; break;
                                                    case 'inventory_trends': echo 'graph-up-arrow'; break;
                                                    case 'donor_statistics': echo 'people'; break;
                                                    case 'appointments_report': echo 'calendar-plus'; break;
                                                    case 'blood_drives_report': echo 'truck'; break;
                                                    case 'fulfillment_rate': echo 'percent'; break;
                                                    case 'expiry_tracking': echo 'clock-history'; break;
                                                    case 'top_donors': echo 'trophy'; break;
                                                    default: echo 'graph-up';
                                                }
                                            ?>"></i>
                                        </div>
                                    </div>
                                    <h5 class="card-title text-center mb-3"><?php echo $title; ?></h5>
                                    <p class="text-muted text-center mb-4">
                                        <?php
                                        switch ($type) {
                                            case 'donations_by_date':
                                                echo 'Track donation trends over time with detailed date-based analysis.';
                                                break;
                                            case 'donations_by_blood_type':
                                                echo 'Analyze donation patterns by blood type distribution.';
                                                break;
                                            case 'requests_by_date':
                                                echo 'Monitor blood request patterns and approval rates over time.';
                                                break;
                                            case 'requests_by_blood_type':
                                                echo 'View blood request statistics grouped by blood type.';
                                                break;
                                            case 'inventory_status':
                                                echo 'Current blood inventory levels with status indicators.';
                                                break;
                                            case 'inventory_trends':
                                                echo 'Track inventory changes and trends over time.';
                                                break;
                                            case 'donor_statistics':
                                                echo 'Comprehensive donor demographics and activity statistics.';
                                                break;
                                            case 'appointments_report':
                                                echo 'Analyze appointment scheduling, completion rates, and trends.';
                                                break;
                                            case 'blood_drives_report':
                                                echo 'Monitor blood drive events, registrations, and success rates.';
                                                break;
                                            case 'fulfillment_rate':
                                                echo 'Track how many blood requests are successfully completed.';
                                                break;
                                            case 'expiry_tracking':
                                                echo 'Identify blood units expiring soon to prevent waste.';
                                                break;
                                            case 'top_donors':
                                                echo 'Recognize top donors by donation count and contribution.';
                                                break;
                                        }
                                        ?>
                                    </p>
                                    <div class="text-center">
                                        <a href="reports.php?type=<?php echo $type; ?>" class="btn btn-primary">
                                            <i class="bi bi-play-circle me-1"></i>Generate Report
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Report Display -->
                <div class="reports-card">
                    <div class="card-header-custom">
                        <h3 class="card-title">
                            <i class="bi bi-graph-up"></i>
                            <?php echo $report_title; ?>
                        </h3>
                        <div class="d-flex gap-2">
                            <a href="reports.php" class="btn btn-outline-light">
                                <i class="bi bi-arrow-left me-1"></i>Back to Reports
                            </a>
                            <button class="btn btn-outline-light" onclick="printReportData()">
                                <i class="bi bi-printer me-1"></i>Print Report
                            </button>
                            <button class="btn btn-outline-light" onclick="exportToPDF()">
                                <i class="bi bi-filetype-pdf me-1"></i>Export PDF
                            </button>
                            <button class="btn btn-outline-light" onclick="exportToCSV()">
                                <i class="bi bi-filetype-csv me-1"></i>Export CSV
                            </button>
                        </div>
                    </div>
                    <!-- Print Header (visible only when printing) -->
                    <div class="p-4">
                        <div class="print-header" style="display:none;">
                            <img src="../../assets/img/nflogo.png" alt="Negros First Logo">
                            <div>
                                <div><strong>Negros First Blood Bank</strong></div>
                                <div class="meta">Report: <?php echo htmlspecialchars($report_title); ?> | Generated: <?php echo date('M d, Y g:i A'); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (in_array($report_type, ['donations_by_date', 'donations_by_blood_type', 'requests_by_date', 'requests_by_blood_type', 'appointments_report', 'blood_drives_report'])): ?>
                        <!-- Date Range Filter -->
                        <div class="p-4 border-bottom">
                            <form method="get" action="" class="row g-3 align-items-end">
                                <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-funnel me-1"></i>Apply Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Report Content -->
                    <div class="p-4">
                        <?php if (!empty($report_data)): ?>
                            <?php if ($report_type === 'donations_by_date'): ?>
                                <div class="mb-4">
                                    <canvas id="reportChart_donations_by_date" width="400" height="200"></canvas>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Number of Donations</th>
                                                <th>Total Units</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                                    <td><?php echo $row['count']; ?></td>
                                                    <td><?php echo (int)$row['total_units']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($report_type === 'donations_by_blood_type'): ?>
                                <div class="mb-4">
                                    <canvas id="reportChart_donations_by_blood_type" width="400" height="200"></canvas>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Blood Type</th>
                                                <th>Number of Donations</th>
                                                <th>Total Units</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td><?php echo $row['blood_type']; ?></td>
                                                    <td><?php echo $row['count']; ?></td>
                                                    <td><?php echo (int)$row['total_units']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($report_type === 'requests_by_date'): ?>
                                <div class="mb-4">
                                    <canvas id="reportChart_requests_by_date" width="400" height="200"></canvas>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Total Requests</th>
                                                <th>Units Requested</th>
                                                <th>Approved</th>
                                                <th>Completed</th>
                                                <th>Rejected</th>
                                                <th>Pending</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                                    <td><?php echo $row['count']; ?></td>
                                                    <td><?php echo (int)$row['total_units']; ?></td>
                                                    <td><?php echo $row['approved']; ?></td>
                                                    <td><?php echo $row['completed']; ?></td>
                                                    <td><?php echo $row['rejected']; ?></td>
                                                    <td><?php echo $row['pending']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($report_type === 'requests_by_blood_type'): ?>
                                <div class="mb-4">
                                    <canvas id="reportChart_requests_by_blood_type" width="400" height="200"></canvas>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Blood Type</th>
                                                <th>Total Requests</th>
                                                <th>Units Requested</th>
                                                <th>Approved</th>
                                                <th>Rejected</th>
                                                <th>Pending</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td><?php echo $row['blood_type']; ?></td>
                                                    <td><?php echo $row['count']; ?></td>
                                                    <td><?php echo (int)$row['total_units']; ?></td>
                                                    <td><?php echo $row['approved']; ?></td>
                                                    <td><?php echo $row['rejected']; ?></td>
                                                    <td><?php echo $row['pending']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($report_type === 'inventory_status'): ?>
                                <div class="mb-4">
                                    <canvas id="reportChart_inventory_status" width="400" height="200"></canvas>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Blood Type</th>
                                                <th>Available Units</th>
                                                <th>Used Units</th>
                                                <th>Expired Units</th>
                                                <th>Last Updated</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): 
                                                $available = (int)($row['available_units'] ?? 0);
                                            ?>
                                                <tr>
                                                    <td><?php echo $row['blood_type']; ?></td>
                                                    <td><strong><?php echo $available; ?></strong></td>
                                                    <td><?php echo (int)($row['used_units'] ?? 0); ?></td>
                                                    <td><?php echo (int)($row['expired_units'] ?? 0); ?></td>
                                                    <td><?php echo $row['last_updated'] ? date('M d, Y H:i', strtotime($row['last_updated'])) : 'N/A'; ?></td>
                                                    <td>
                                                        <?php
                                                        if ($available < 5) {
                                                            echo '<span class="status-badge badge-danger">Critical</span>';
                                                        } elseif ($available < 10) {
                                                            echo '<span class="status-badge badge-warning">Low</span>';
                                                        } else {
                                                            echo '<span class="status-badge badge-success">Adequate</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($report_type === 'donor_statistics'): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-header bg-light">
                                                <h5 class="mb-0">Donor Summary</h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table">
                                                    <tr>
                                                        <th>Total Donors</th>
                                                        <td><?php echo $report_data['stats']['total_donors']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Active Donors</th>
                                                        <td><?php echo $report_data['stats']['active_donors']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Inactive Donors</th>
                                                        <td><?php echo $report_data['stats']['inactive_donors']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Average Donations per Donor</th>
                                                        <td><?php echo number_format($report_data['stats']['avg_donations'], 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Maximum Donations by a Donor</th>
                                                        <td><?php echo $report_data['stats']['max_donations']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Number of Blood Types</th>
                                                        <td><?php echo $report_data['stats']['blood_type_count']; ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-header bg-light">
                                                <h5 class="mb-0">Donors by Blood Type</h5>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="reportChart_donor_statistics" width="400" height="300"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Donors by Blood Type</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="enhanced-table table">
                                                <thead>
                                                    <tr>
                                                        <th>Blood Type</th>
                                                        <th>Number of Donors</th>
                                                        <th>Percentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $total_donors = $report_data['stats']['total_donors'];
                                                    foreach ($report_data['blood_types'] as $row):
                                                        $percentage = ($total_donors > 0) ? ($row['count'] / $total_donors) * 100 : 0;
                                                    ?>
                                                        <tr>
                                                            <td><?php echo $row['blood_type']; ?></td>
                                                            <td><?php echo $row['count']; ?></td>
                                                            <td><?php echo number_format($percentage, 2); ?>%</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($report_type === 'appointments_report'): ?>
                                <div class="mb-4">
                                    <canvas id="reportChart_appointments_report" width="400" height="200"></canvas>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Total</th>
                                                <th>Scheduled</th>
                                                <th>Completed</th>
                                                <th>Pending</th>
                                                <th>Rejected</th>
                                                <th>No Show</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                                    <td><?php echo $row['total_appointments']; ?></td>
                                                    <td><?php echo $row['scheduled']; ?></td>
                                                    <td><?php echo $row['completed']; ?></td>
                                                    <td><?php echo $row['pending']; ?></td>
                                                    <td><?php echo $row['rejected']; ?></td>
                                                    <td><?php echo $row['no_show']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($report_type === 'blood_drives_report'): ?>
                                <div class="mb-4">
                                    <canvas id="reportChart_blood_drives_report" width="400" height="200"></canvas>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Total Drives</th>
                                                <th>Scheduled</th>
                                                <th>Completed</th>
                                                <th>Cancelled</th>
                                                <th>Registrations</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                                    <td><?php echo $row['total_drives']; ?></td>
                                                    <td><?php echo $row['scheduled']; ?></td>
                                                    <td><?php echo $row['completed']; ?></td>
                                                    <td><?php echo $row['cancelled']; ?></td>
                                                    <td><?php echo $row['total_registrations']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($report_type === 'fulfillment_rate'): ?>
                                <div class="mb-4">
                                    <canvas id="reportChart_fulfillment_rate" width="400" height="200"></canvas>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Blood Type</th>
                                                <th>Total Requests</th>
                                                <th>Completed</th>
                                                <th>Rejected</th>
                                                <th>Completion Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): 
                                                $fulfillment_rate = isset($row['fulfillment_rate']) ? $row['fulfillment_rate'] : (isset($row['total_requests']) && $row['total_requests'] > 0 ? round(($row['fulfilled'] / $row['total_requests']) * 100, 2) : 0);
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['blood_type'] ?? 'N/A'); ?></td>
                                                    <td><?php echo (int)($row['total_requests'] ?? 0); ?></td>
                                                    <td><?php echo (int)($row['fulfilled'] ?? 0); ?></td>
                                                    <td><?php echo (int)($row['rejected'] ?? 0); ?></td>
                                                    <td><strong><?php echo number_format($fulfillment_rate, 2); ?>%</strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($report_type === 'expiry_tracking'): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> Blood units listed below will expire within 30 days. Please prioritize using these units.
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Blood Type</th>
                                                <th>Expiry Date</th>
                                                <th>Units</th>
                                                <th>Days Until Expiry</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr class="<?php echo $row['days_until_expiry'] <= 7 ? 'table-danger' : ($row['days_until_expiry'] <= 14 ? 'table-warning' : ''); ?>">
                                                    <td><?php echo $row['blood_type']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($row['expiry_date'])); ?></td>
                                                    <td><?php echo $row['units']; ?></td>
                                                    <td><strong><?php echo $row['days_until_expiry']; ?> days</strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($report_type === 'top_donors'): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Donor Name</th>
                                                <th>Blood Type</th>
                                                <th>Donation Count</th>
                                                <th>Total Units</th>
                                                <th>Last Donation</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $rank = 1;
                                            foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td><strong>#<?php echo $rank++; ?></strong></td>
                                                    <td><?php echo htmlspecialchars($row['donor_name']); ?></td>
                                                    <td><span class="badge bg-danger"><?php echo $row['blood_type']; ?></span></td>
                                                    <td><?php echo $row['donation_count']; ?></td>
                                                    <td><?php echo number_format($row['total_units'], 2); ?></td>
                                                    <td><?php echo $row['last_donation'] ? date('M d, Y', strtotime($row['last_donation'])) : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($report_type === 'inventory_trends'): ?>
                                <div class="mb-4">
                                    <canvas id="reportChart_inventory_trends" width="400" height="200"></canvas>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <?php 
                                                $blood_types = [];
                                                foreach ($report_data as $row) {
                                                    foreach ($row as $key => $value) {
                                                        if ($key !== 'date' && !in_array($key, $blood_types)) {
                                                            $blood_types[] = $key;
                                                        }
                                                    }
                                                }
                                                foreach ($blood_types as $bt): ?>
                                                    <th><?php echo $bt; ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td><?php echo $row['date']; ?></td>
                                                    <?php foreach ($blood_types as $bt): ?>
                                                        <td><?php echo isset($row[$bt]) ? $row[$bt] : 0; ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <div class="mt-4">
                                <a href="reports.php" class="btn btn-secondary">Back to Reports</a>
                                <button class="btn btn-primary ml-2" onclick="printReportData()">
                                    <i class="bi bi-printer me-1"></i>Print Report
                                </button>
                                <button class="btn btn-success ml-2" onclick="exportToCSV()">
                                    <i class="bi bi-filetype-csv me-1"></i>Export to CSV
                                </button>
                                <button class="btn btn-danger ml-2" onclick="exportToPDF()">
                                    <i class="bi bi-filetype-pdf me-1"></i>Export to PDF
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h5>No Data Available</h5>
                                <p>No data found for the selected report type and date range. Please try different parameters or check if data exists in the system.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
// Print report functionality with formatted data
function printReportData() {
    const reportType = <?php echo json_encode($report_type ?? ''); ?>;
    const reportTitle = <?php echo json_encode($report_title ?? 'Reports & Analytics'); ?>;
    
    if (!reportType) {
        // Print report selection page
        const title = 'Negros First Blood Bank - Reports & Analytics';
        let content = '<h2>Reports & Analytics</h2>';
        content += '<p><strong>Generated:</strong> ' + new Date().toLocaleString() + '</p>';
        content += '<p><strong>Available Report Types:</strong> ' + <?php echo count($report_types); ?> + '</p>';
        
        if (typeof generatePrintDocument === 'function') {
            generatePrintDocument(title, content);
        } else {
            window.print();
        }
        return;
    }
    
    // Print specific report
    const table = document.querySelector('.table-responsive table, table.table-striped, table');
    if (!table) {
        alert('No report data found to print.');
        return;
    }
    
    const title = 'Negros First Blood Bank - ' + reportTitle;
    
    // Extract headers (excluding Actions if any)
    const headers = Array.from(table.querySelectorAll('thead th'))
        .filter(th => {
            const text = (th.textContent || '').trim().toLowerCase();
            return !text.includes('action');
        })
        .map(th => (th.textContent || '').trim());
    
    // Extract rows
    const rows = Array.from(table.querySelectorAll('tbody tr'))
        .map(tr => {
            const cells = Array.from(tr.querySelectorAll('td'))
                .filter((td, index) => {
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
            let cellStyle = 'padding:12px 8px; border:1px solid #ddd;';
            
            if (headers[index] && headers[index].toLowerCase().includes('blood type')) {
                cell = `<span style="background-color:#dc3545; color:#fff; padding:4px 8px; border-radius:4px; font-weight:bold;">${cell}</span>`;
            } else if (headers[index] && headers[index].toLowerCase().includes('total')) {
                cellStyle += 'font-weight:bold;';
            }
            
            tableHTML += `<td style="${cellStyle}">${cell || ''}</td>`;
        });
        tableHTML += '</tr>';
        
        // Add page break every 15 rows
        if ((rowIndex + 1) % 15 === 0 && rowIndex < rows.length - 1) {
            tableHTML += '<tr style="page-break-after:always;"><td colspan="' + headers.length + '"></td></tr>';
        }
    });
    tableHTML += '</tbody></table>';
    
    const content = `
        <div style="margin-bottom:20px;">
            <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Report Period:</strong> ${document.querySelector('input[name="start_date"]')?.value || ''} to ${document.querySelector('input[name="end_date"]')?.value || ''}</p>
            <p><strong>Total Records:</strong> ${rows.length}</p>
        </div>
        ${tableHTML}
    `;
    
    if (typeof generatePrintDocument === 'function') {
        generatePrintDocument(title, content);
    } else {
        window.print();
    }
}

// Export to CSV functionality
function exportToCSV() {
    const reportType = <?php echo json_encode($report_type ?? ''); ?>;
    const reportTitle = <?php echo json_encode($report_title ?? 'Report'); ?>;
    const reportData = <?php echo json_encode($report_data ?? []); ?>;
    
    if (!reportData || reportData.length === 0) {
        alert('No data available to export.');
        return;
    }
    
    let csvContent = '';
    let filename = 'negrosfirst_report.csv';
    
    // Generate CSV based on report type
    if (reportType === 'donations_by_date' || reportType === 'donations_by_blood_type') {
        csvContent = 'Report Type,Date/Blood Type,Count,Total Units\n';
        reportData.forEach(row => {
            const dateOrType = reportType === 'donations_by_date' ? row['date'] : row['blood_type'];
            csvContent += `"${reportTitle}","${dateOrType}","${row['count']}","${row['total_units']}"\n`;
        });
        filename = 'negrosfirst_donations_report.csv';
    } else if (reportType === 'requests_by_date' || reportType === 'requests_by_blood_type') {
        csvContent = 'Report Type,Date/Blood Type,Total Requests,Units Requested,Approved,Completed,Rejected,Pending\n';
        reportData.forEach(row => {
            const dateOrType = reportType === 'requests_by_date' ? row['date'] : row['blood_type'];
            csvContent += `"${reportTitle}","${dateOrType}","${row['count']}","${row['total_units']}","${row['approved'] || 0}","${row['completed'] || 0}","${row['rejected']}","${row['pending']}"\n`;
        });
        filename = 'negrosfirst_requests_report.csv';
    } else if (reportType === 'inventory_status') {
        csvContent = 'Blood Type,Available Units,Used Units,Expired Units,Last Updated\n';
        reportData.forEach(row => {
            csvContent += `"${row['blood_type']}","${row['available_units'] || 0}","${row['used_units'] || 0}","${row['expired_units'] || 0}","${row['last_updated'] || 'N/A'}"\n`;
        });
        filename = 'negrosfirst_inventory_report.csv';
    } else if (reportType === 'appointments_report') {
        csvContent = 'Date,Total,Scheduled,Completed,Pending,Rejected,No Show\n';
        reportData.forEach(row => {
            csvContent += `"${row['date']}","${row['total_appointments']}","${row['scheduled']}","${row['completed']}","${row['pending']}","${row['rejected']}","${row['no_show']}"\n`;
        });
        filename = 'negrosfirst_appointments_report.csv';
    } else if (reportType === 'blood_drives_report') {
        csvContent = 'Date,Total Drives,Scheduled,Completed,Cancelled,Registrations\n';
        reportData.forEach(row => {
            csvContent += `"${row['date']}","${row['total_drives']}","${row['scheduled']}","${row['completed']}","${row['cancelled']}","${row['total_registrations']}"\n`;
        });
        filename = 'negrosfirst_blood_drives_report.csv';
    } else if (reportType === 'fulfillment_rate') {
        csvContent = 'Blood Type,Total Requests,Completed,Rejected,Completion Rate\n';
        reportData.forEach(row => {
            csvContent += `"${row['blood_type']}","${row['total_requests']}","${row['fulfilled']}","${row['rejected']}","${row['fulfillment_rate']}%"\n`;
        });
        filename = 'negrosfirst_completion_rate_report.csv';
    } else if (reportType === 'expiry_tracking') {
        csvContent = 'Blood Type,Expiry Date,Units,Days Until Expiry\n';
        reportData.forEach(row => {
            csvContent += `"${row['blood_type']}","${row['expiry_date']}","${row['units']}","${row['days_until_expiry']}"\n`;
        });
        filename = 'negrosfirst_expiry_tracking_report.csv';
    } else if (reportType === 'top_donors') {
        csvContent = 'Rank,Donor Name,Blood Type,Donation Count,Total Units,Last Donation\n';
        reportData.forEach((row, index) => {
            csvContent += `"${index + 1}","${row['donor_name']}","${row['blood_type']}","${row['donation_count']}","${row['total_units']}","${row['last_donation'] || 'N/A'}"\n`;
        });
        filename = 'negrosfirst_top_donors_report.csv';
    } else if (reportType === 'donor_statistics') {
        csvContent = 'Blood Type,Number of Donors,Percentage\n';
        const totalDonors = reportData.stats.total_donors || 1;
        reportData.blood_types.forEach(row => {
            const percentage = ((row['count'] / totalDonors) * 100).toFixed(2);
            csvContent += `"${row['blood_type']}","${row['count']}","${percentage}%"\n`;
        });
        filename = 'negrosfirst_donor_statistics_report.csv';
    } else {
        // Generic CSV export from table
        const table = document.querySelector('.table-responsive table, table.table-striped, table');
        if (table) {
            const rows = Array.from(table.querySelectorAll('tr'));
            csvContent = rows.map(row => {
                const cells = Array.from(row.querySelectorAll('th, td'));
                return cells.map(cell => {
                    let text = cell.innerText || cell.textContent || '';
                    text = text.replace(/"/g, '""');
                    return `"${text}"`;
                }).join(',');
            }).join('\n');
        } else {
            alert('No data available to export.');
            return;
        }
    }
    
    // Download CSV
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

// Export to PDF functionality
function exportToPDF() {
    if (typeof window.jspdf === 'undefined' || !window.jspdf.jsPDF) {
        alert('PDF export library not loaded. Please refresh the page and try again.');
        return;
    }
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape');
    const reportType = <?php echo json_encode($report_type ?? ''); ?>;
    const reportTitle = <?php echo json_encode($report_title ?? 'Report'); ?>;
    const reportData = <?php echo json_encode($report_data ?? []); ?>;
    
    if (!reportData || reportData.length === 0) {
        alert('No data available to export.');
        return;
    }
    
    // Add header
    doc.setFontSize(18);
    doc.setTextColor(26, 54, 93);
    doc.text('Negros First Blood Bank', 14, 15);
    doc.setFontSize(14);
    doc.setTextColor(0, 0, 0);
    doc.text(reportTitle, 14, 25);
    doc.setFontSize(10);
    doc.setTextColor(100, 100, 100);
    doc.text('Generated: ' + new Date().toLocaleString(), 14, 32);
    
    let startY = 40;
    let columns = [];
    let rows = [];
    
    // Prepare data based on report type
    if (reportType === 'donations_by_date' || reportType === 'donations_by_blood_type') {
        columns = ['Date/Blood Type', 'Count', 'Total Units'];
        rows = reportData.map(row => {
            const dateOrType = reportType === 'donations_by_date' ? row['date'] : row['blood_type'];
            return [dateOrType, row['count'].toString(), row['total_units'].toString()];
        });
    } else if (reportType === 'requests_by_date' || reportType === 'requests_by_blood_type') {
        columns = ['Date/Blood Type', 'Total', 'Units', 'Approved', 'Completed', 'Rejected', 'Pending'];
        rows = reportData.map(row => {
            const dateOrType = reportType === 'requests_by_date' ? row['date'] : row['blood_type'];
            return [
                dateOrType,
                row['count'].toString(),
                row['total_units'].toString(),
                (row['approved'] || 0).toString(),
                (row['completed'] || 0).toString(),
                row['rejected'].toString(),
                row['pending'].toString()
            ];
        });
    } else if (reportType === 'fulfillment_rate') {
        columns = ['Blood Type', 'Total Requests', 'Completed', 'Rejected', 'Completion Rate'];
        rows = reportData.map(row => [
            row['blood_type'],
            row['total_requests'].toString(),
            row['fulfilled'].toString(),
            row['rejected'].toString(),
            (row['fulfillment_rate'] || 0).toFixed(2) + '%'
        ]);
    } else if (reportType === 'inventory_status') {
        columns = ['Blood Type', 'Available', 'Used', 'Expired', 'Last Updated'];
        rows = reportData.map(row => [
            row['blood_type'],
            (row['available_units'] || 0).toString(),
            (row['used_units'] || 0).toString(),
            (row['expired_units'] || 0).toString(),
            row['last_updated'] ? new Date(row['last_updated']).toLocaleDateString() : 'N/A'
        ]);
    } else if (reportType === 'appointments_report') {
        columns = ['Date', 'Total', 'Scheduled', 'Completed', 'Pending', 'Rejected', 'No Show'];
        rows = reportData.map(row => [
            row['date'],
            row['total_appointments'].toString(),
            row['scheduled'].toString(),
            row['completed'].toString(),
            row['pending'].toString(),
            row['rejected'].toString(),
            row['no_show'].toString()
        ]);
    } else if (reportType === 'top_donors') {
        columns = ['Rank', 'Donor Name', 'Blood Type', 'Donations', 'Total Units', 'Last Donation'];
        rows = reportData.map((row, index) => [
            (index + 1).toString(),
            row['donor_name'],
            row['blood_type'],
            row['donation_count'].toString(),
            row['total_units'].toString(),
            row['last_donation'] || 'N/A'
        ]);
    } else {
        // Try to extract from table
        const table = document.querySelector('.table-responsive table, table.table-striped, table');
        if (table) {
            const ths = Array.from(table.querySelectorAll('thead th, thead td'));
            columns = ths.map(th => th.innerText.trim());
            const trs = Array.from(table.querySelectorAll('tbody tr'));
            rows = trs.map(tr => {
                const tds = Array.from(tr.querySelectorAll('td'));
                return tds.map(td => td.innerText.trim());
            });
        } else {
            alert('No data available to export.');
            return;
        }
    }
    
    // Generate table
    doc.autoTable({
        head: [columns],
        body: rows,
        startY: startY,
        styles: { fontSize: 8 },
        headStyles: { fillColor: [26, 54, 93], textColor: 255 },
        margin: { top: startY }
    });
    
    // Save PDF
    const filename = 'negrosfirst_' + (reportType || 'report') + '_' + new Date().toISOString().split('T')[0] + '.pdf';
    doc.save(filename);
}

// Chart rendering
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Wait for Chart.js to load
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not loaded, skipping chart rendering');
            return;
        }

        var chartData = <?php echo json_encode($chart_data ?? []); ?>;
        var reportType = <?php echo json_encode($report_type ?? ''); ?>;
        var canvasId = reportType ? 'reportChart_' + reportType : 'reportChart';
        var ctx = document.getElementById(canvasId);
        
        if (!ctx || !chartData || chartData.length === 0) {
            console.log('No chart data or canvas element found');
            return;
        }

        var labels = [];
        var datasets = [];

        if (reportType === 'donations_by_date' || reportType === 'requests_by_date' || reportType === 'appointments_report' || reportType === 'blood_drives_report') {
            labels = chartData.map(function(d){ return d.date; });
            // primary dataset: counts or totals
            datasets.push({
                label: reportType === 'appointments_report' ? 'Total Appointments' : (reportType === 'blood_drives_report' ? 'Total Drives' : 'Count'),
                data: chartData.map(function(d){ return d.count || d.total || d.total_appointments || d.total_drives || 0; }),
                backgroundColor: 'rgba(26,54,93,0.6)'
            });
            
            if (reportType === 'donations_by_date' || reportType === 'requests_by_date') {
                // units dataset (secondary)
                datasets.push({
                    label: 'Units',
                    data: chartData.map(function(d){ return d.units || 0; }),
                    backgroundColor: 'rgba(38,161,105,0.6)'
                });
            }

            if (reportType === 'requests_by_date') {
                if (chartData[0] && 'approved' in chartData[0]) {
                    datasets.push({ label: 'Approved', data: chartData.map(function(d){ return d.approved || 0; }), backgroundColor: 'rgba(23,162,184,0.6)'});
                }
                if (chartData[0] && 'completed' in chartData[0]) {
                    datasets.push({ label: 'Completed', data: chartData.map(function(d){ return d.completed || 0; }), backgroundColor: 'rgba(40,167,69,0.6)'});
                }
                if (chartData[0] && 'rejected' in chartData[0]) {
                    datasets.push({ label: 'Rejected', data: chartData.map(function(d){ return d.rejected || 0; }), backgroundColor: 'rgba(255,193,7,0.6)'});
                }
                if (chartData[0] && 'pending' in chartData[0]) {
                    datasets.push({ label: 'Pending', data: chartData.map(function(d){ return d.pending || 0; }), backgroundColor: 'rgba(108,117,125,0.6)'});
                }
            }
            
            if (reportType === 'appointments_report') {
                if (chartData[0] && 'scheduled' in chartData[0]) {
                    datasets.push({ label: 'Scheduled', data: chartData.map(function(d){ return d.scheduled || 0; }), backgroundColor: 'rgba(23,162,184,0.6)'});
                }
                if (chartData[0] && 'completed' in chartData[0]) {
                    datasets.push({ label: 'Completed', data: chartData.map(function(d){ return d.completed || 0; }), backgroundColor: 'rgba(40,167,69,0.6)'});
                }
                if (chartData[0] && 'pending' in chartData[0]) {
                    datasets.push({ label: 'Pending', data: chartData.map(function(d){ return d.pending || 0; }), backgroundColor: 'rgba(255,193,7,0.6)'});
                }
            }
        } else if (reportType === 'donations_by_blood_type' || reportType === 'requests_by_blood_type' || reportType === 'donor_statistics' || reportType === 'fulfillment_rate' || reportType === 'inventory_status') {
            var key = 'blood_type';
            labels = chartData.map(function(d){ return d[key] || ''; });
            
            if (reportType === 'fulfillment_rate') {
                datasets.push({ 
                    label: 'Completion Rate (%)', 
                    data: chartData.map(function(d){ return d.fulfillment_rate || 0; }), 
                    backgroundColor: 'rgba(40,167,69,0.6)'
                });
            } else {
                datasets.push({ 
                    label: 'Count', 
                    data: chartData.map(function(d){ return d.count || d.units || 0; }), 
                    backgroundColor: chartData.map(function(_,i){ 
                        const colors = [
                            'rgba(26,54,93,0.6)',
                            'rgba(38,161,105,0.6)',
                            'rgba(23,162,184,0.6)',
                            'rgba(255,193,7,0.6)',
                            'rgba(108,117,125,0.6)',
                            'rgba(0,123,255,0.6)',
                            'rgba(111,66,193,0.6)',
                            'rgba(214,51,132,0.6)'
                        ];
                        return colors[i % colors.length];
                    })
                });
            }
        } else if (reportType === 'top_donors') {
            labels = chartData.map(function(d){ return d.donor_name || ''; });
            datasets.push({ 
                label: 'Donation Count', 
                data: chartData.map(function(d){ return d.donation_count || 0; }), 
                backgroundColor: 'rgba(26,54,93,0.6)'
            });
        } else if (reportType === 'expiry_tracking') {
            labels = chartData.map(function(d){ return d.blood_type + ' (' + d.days_until_expiry + ' days)'; });
            datasets.push({ 
                label: 'Units', 
                data: chartData.map(function(d){ return d.units || 0; }), 
                backgroundColor: chartData.map(function(d){ 
                    return d.days_until_expiry <= 7 ? 'rgba(229,62,62,0.6)' : (d.days_until_expiry <= 14 ? 'rgba(255,193,7,0.6)' : 'rgba(40,167,69,0.6)');
                })
            });
        }

        if (labels.length === 0 || datasets.length === 0) {
            console.log('No data to chart');
            return;
        }

        // Build chart
        var config = {
            type: reportType === 'donations_by_blood_type' || reportType === 'inventory_status' || reportType === 'donor_statistics' ? 'bar' : 'bar',
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { 
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    } 
                },
                plugins: {
                    legend: {
                        display: datasets.length > 1,
                        position: 'bottom'
                    }
                }
            }
        };

        new Chart(ctx, config);
    } catch (e) {
        console.error('Failed to render report chart', e);
    }
});
</script>

</body>
</html>
