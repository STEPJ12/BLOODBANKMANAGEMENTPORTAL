<?php
// Include database connection
require_once 'config/db.php';



// Get Negros First information
$negrosfirst = getRow("SELECT * FROM negrosfirst_users WHERE id = 1"); // Assuming ID 1 is the main Negros First account

// Get blood inventory
$bloodInventory = executeQuery("
    SELECT blood_type, SUM(units) as available_units
    FROM blood_inventory
    WHERE organization_type = 'negrosfirst' AND status = 'Available'
    GROUP BY blood_type
    ORDER BY blood_type
");
if (!is_array($bloodInventory)) {
    $bloodInventory = [];
}

// Get upcoming blood drives
$upcomingDrives = executeQuery("
    SELECT bd.*, b.name as barangay_name
    FROM blood_drives bd
    JOIN barangay_users b ON bd.barangay_id = b.id
    WHERE bd.organization_type = 'negrosfirst' AND bd.date >= CURDATE() AND bd.status = 'Scheduled'
    ORDER BY bd.date ASC
    LIMIT 5
");


// Get donation statistics
$donationStats = getRow("
    SELECT
        COUNT(*) as total_donations,
        SUM(units) as total_units,
        COUNT(DISTINCT donor_id) as unique_donors
    FROM donations
    WHERE organization_type = 'negrosfirst'
");

// Page title
$pageTitle = "Negros First Blood Center - Blood Bank Portal";


// Get blood donations
$status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$whereClause = "WHERE bd.organization_type = 'negrosfirst'";

if ($status !== 'all') {
    $whereClause .= " AND bd.status = '{$status}'";
}

// Fetch donations from redcross with donor info
$bloodDonations = executeQuery("
    SELECT d.*, du.name AS donor_name, du.phone, du.blood_type AS donor_blood_type, du.city
    FROM donations d
    JOIN donor_users du ON d.donor_id = du.id
    WHERE d.organization_type = 'negrosfirst'
    ORDER BY
        CASE
            WHEN d.status = 'Pending' THEN 1
            WHEN d.status = 'Approved' THEN 2
            WHEN d.status = 'Completed' THEN 3
            WHEN d.status = 'Rejected' THEN 4
            WHEN d.status = 'Cancelled' THEN 5
        END,
        d.donation_date DESC
");
// Check if blood donations were fetched correctly
if ($bloodDonations === false) {
    echo "Error in fetching donations.";
} else {
    // Proceed with your logic
}

// Get counts for each status
$pendingCount = getCount("SELECT COUNT(*) FROM donations WHERE organization_type = 'negrosfirst' AND status = 'Pending'");
$approvedCount = getCount("SELECT COUNT(*) FROM donations WHERE organization_type = 'negrosfirst' AND status = 'Approved'");
$completedCount = getCount("SELECT COUNT(*) FROM donations WHERE organization_type = 'negrosfirst' AND status = 'Completed'");
$rejectedCount = getCount("SELECT COUNT(*) FROM donations WHERE organization_type = 'negrosfirst' AND status = 'Rejected'");

$totalCount = $pendingCount + $approvedCount + $completedCount + $rejectedCount;

// Get donors for manual entry
$donors = executeQuery("SELECT id, name, blood_type FROM donor_users ORDER BY name");

// Check if donors were fetched correctly
if ($donors === false) {
    echo "Error in fetching donors.";
} else {
    // Process donor data
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="css/swiper.min.css">
    <link rel="stylesheet" type="text/css" href="css/animate.css" />
    <link rel="stylesheet" type="text/css" href="css/style.css" />
<style>
    :root {
        /* Negros First Brand Colors - Rich & Professional */
        --nf-primary: #b31217;        /* Deep Red - Primary brand color */
        --nf-primary-dark: #8a0e12;  /* Darker red for depth */
        --nf-primary-light: #d41e24; /* Lighter red for highlights */
        --nf-secondary: #198754;     /* Green - Health & Life */
        --nf-secondary-dark: #0f5132; /* Dark green */
        --nf-secondary-light: #20c997; /* Light green */
        --nf-accent: #ffc107;        /* Gold/Amber - Premium accent */
        --nf-accent-dark: #ff9800;  /* Darker gold */
        --nf-navy: #0d47a1;         /* Deep Navy - Trust & Professionalism */
        --nf-navy-light: #1565c0;   /* Lighter navy */
        
        /* Neutral Colors */
        --white: #ffffff;
        --gray-50: #f8f9fa;
        --gray-100: #f1f3f5;
        --gray-200: #e9ecef;
        --gray-300: #dee2e6;
        --gray-400: #ced4da;
        --gray-500: #adb5bd;
        --gray-600: #6c757d;
        --gray-700: #495057;
        --gray-800: #343a40;
        --gray-900: #212529;
        
        /* Legacy compatibility */
        --primary-color: var(--nf-primary);
        --secondary-color: var(--nf-secondary);
        --accent-color: var(--nf-primary);
        --success-color: var(--nf-secondary);
        --warning-color: var(--nf-accent);
        --info-color: var(--nf-navy);
        --light-bg: #fafbfc;
        
        /* Design Tokens */
        --border-radius: 16px;
        --border-radius-sm: 12px;
        --border-radius-lg: 24px;
        --box-shadow: 0 4px 12px rgba(179, 18, 23, 0.08), 0 2px 4px rgba(179, 18, 23, 0.04);
        --box-shadow-lg: 0 12px 24px rgba(179, 18, 23, 0.12), 0 4px 8px rgba(179, 18, 23, 0.08);
        --box-shadow-xl: 0 20px 40px rgba(179, 18, 23, 0.15);
        --gradient-primary: linear-gradient(135deg, var(--nf-primary) 0%, var(--nf-primary-dark) 100%);
        --gradient-secondary: linear-gradient(135deg, var(--nf-secondary) 0%, var(--nf-secondary-dark) 100%);
        --gradient-hero: linear-gradient(135deg, rgba(179, 18, 23, 0.95) 0%, rgba(13, 71, 161, 0.9) 50%, rgba(25, 135, 84, 0.85) 100%);
        --gradient-accent: linear-gradient(135deg, var(--nf-primary) 0%, var(--nf-navy) 50%, var(--nf-secondary) 100%);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: var(--gray-800);
        line-height: 1.7;
        background: linear-gradient(180deg, #ffffff 0%, var(--gray-50) 100%);
        padding-top: 0;
        position: relative;
    }
    
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 20% 50%, rgba(179, 18, 23, 0.03) 0%, transparent 50%),
            radial-gradient(circle at 80% 80%, rgba(25, 135, 84, 0.03) 0%, transparent 50%),
            radial-gradient(circle at 40% 20%, rgba(13, 71, 161, 0.02) 0%, transparent 50%);
        pointer-events: none;
        z-index: 0;
    }
    
    body > * {
        position: relative;
        z-index: 1;
    }
    
    /* Enhanced Section Spacing */
    section {
        margin-bottom: 0;
    }
    
    section h2 {
        margin-bottom: 1.5rem;
        font-size: 2.5rem;
        font-weight: 800;
        background: var(--gradient-accent);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        position: relative;
        display: inline-block;
    }
    
    section h2::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 60px;
        height: 4px;
        background: var(--gradient-primary);
        border-radius: 2px;
    }
    
    .lead {
        margin-bottom: 2rem;
        font-size: 1.1rem;
    }
    
    .card {
        margin-bottom: 1.5rem;
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        overflow: hidden;
    }
    
    .card:hover {
        box-shadow: var(--box-shadow-lg);
        transform: translateY(-2px);
    }
    
    .card-title {
        color: var(--nf-primary);
        font-weight: 700;
    }
    
    .card-body {
        padding: 2rem;
    }

    /* Enhanced Navbar */
    .navbar {
        background: rgba(255, 255, 255, 0.98) !important;
        backdrop-filter: blur(20px);
        box-shadow: 0 2px 20px rgba(179, 18, 23, 0.08);
        transition: var(--transition);
    }
    
    .navbar.scrolled {
        box-shadow: 0 4px 30px rgba(179, 18, 23, 0.15);
    }
    
    .navbar-dark {
        background: var(--white) !important;
        box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
    }
    
    .navbar-dark .nav-link {
        color: var(--gray-800) !important;
        font-weight: 500;
        transition: var(--transition);
        position: relative;
    }
    
    .navbar-dark .nav-link:hover,
    .navbar-dark .nav-link.selected {
        color: var(--nf-primary) !important;
    }
    
    .navbar-dark .nav-link.selected::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 60%;
        height: 3px;
        background: var(--gradient-primary);
        border-radius: 2px;
    }
    
    .navbar-dark .btn-outline-light {
        border-color: var(--nf-primary);
        color: var(--nf-primary);
        transition: var(--transition);
    }
    
    .navbar-dark .btn-outline-light:hover {
        background: var(--gradient-primary);
        border-color: var(--nf-primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(179, 18, 23, 0.3);
    }
    
    .navbar-dark .navbar-toggler {
        border-color: var(--gray-300);
    }
    
    .navbar-dark .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2833, 37, 41, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }

    .navbar-brand i {
        color: var(--nf-accent);
        font-size: 1.5rem;
    }
    
    .navbar-brand img {
        transition: var(--transition);
        filter: drop-shadow(0 2px 8px rgba(179, 18, 23, 0.2));
    }
    
    .navbar-brand:hover img {
        transform: scale(1.05);
        filter: drop-shadow(0 4px 12px rgba(179, 18, 23, 0.3));
    }

    .hero-section {
        background: var(--gradient-hero), url('assets/img/bgnf.jpg');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        color: white;
        padding: 140px 0;
        position: relative;
        overflow: hidden;
    }
    
    .hero-section::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 30% 30%, rgba(255, 193, 7, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 70% 70%, rgba(25, 135, 84, 0.1) 0%, transparent 50%);
        pointer-events: none;
    }

    .hero-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.15) 50%, transparent 70%);
        animation: shimmer 4s infinite;
    }

    @keyframes shimmer {
        0% { transform: translateX(-100%) translateY(0); }
        50% { transform: translateX(100%) translateY(20px); }
        100% { transform: translateX(-100%) translateY(0); }
    }

    .hero-content {
        position: relative;
        z-index: 2;
    }

    .hero-title {
        font-size: 3.5rem;
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, #ffffff 0%, var(--nf-accent) 50%, #ffffff 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-shadow: 0 0 30px rgba(255, 193, 7, 0.3);
        animation: titleGlow 3s ease-in-out infinite;
    }
    
    @keyframes titleGlow {
        0%, 100% { filter: brightness(1); }
        50% { filter: brightness(1.1); }
    }

    .hero-subtitle {
        font-size: 1.25rem;
        font-weight: 400;
        opacity: 0.9;
        margin-bottom: 2rem;
        line-height: 1.6;
    }

    .hero-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .btn-hero {
        padding: 0.875rem 2rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        text-decoration: none;
        transition: var(--transition);
        border: 2px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-hero-primary {
        background: var(--gradient-primary);
        color: white;
        border-color: var(--nf-primary);
        box-shadow: 0 4px 15px rgba(179, 18, 23, 0.4);
    }

    .btn-hero-primary:hover {
        background: linear-gradient(135deg, var(--nf-primary-light), var(--nf-primary));
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(179, 18, 23, 0.5);
        color: white;
    }

    .btn-hero-outline {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border-color: rgba(255, 255, 255, 0.3);
        backdrop-filter: blur(10px);
    }

    .btn-hero-outline:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
        color: white;
    }

    .blood-type-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
        padding: 1rem 0;
    }

    .blood-type-item {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 2rem 1.5rem;
        text-align: center;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--gray-200);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .blood-type-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: var(--gradient-accent);
    }

    .blood-type-item:hover {
        transform: translateY(-4px);
        box-shadow: var(--box-shadow-lg);
    }

    .blood-type-badge {
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: 700;
        font-size: 1.25rem;
        color: white;
        background: var(--gradient-primary);
        margin: 0 auto 1rem;
        box-shadow: 0 4px 15px rgba(179, 18, 23, 0.3);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }
    
    .blood-type-badge::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
        opacity: 0;
        transition: var(--transition);
    }
    
    .blood-type-item:hover .blood-type-badge::before {
        opacity: 1;
    }

    .blood-type-item:hover .blood-type-badge {
        transform: scale(1.1);
    }

    .blood-type-units {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-800);
        margin-bottom: 0.5rem;
    }

    .blood-type-status {
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .blood-drive-card {
        border-radius: var(--border-radius);
        overflow: hidden;
        transition: var(--transition);
        height: 100%;
        background: var(--white);
        border: 1px solid var(--gray-200);
        box-shadow: var(--box-shadow);
        margin-bottom: 2rem;
    }
    
    .blood-drive-card .card-body {
        padding: 2rem;
    }

    .blood-drive-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--box-shadow-lg);
    }

    .blood-drive-date {
        width: 80px;
        height: 80px;
        background: var(--gradient-primary);
        color: white;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-radius: var(--border-radius);
        box-shadow: 0 4px 15px rgba(179, 18, 23, 0.3);
        transition: var(--transition);
    }

    .blood-drive-card:hover .blood-drive-date {
        transform: scale(1.05);
    }

    .blood-drive-date .day {
        font-size: 1.75rem;
        font-weight: 800;
        line-height: 1;
    }

    .blood-drive-date .month {
        font-size: 0.875rem;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .stat-item {
        text-align: center;
        padding: 3rem 2rem;
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        border: 1px solid var(--gray-200);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .stat-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: var(--gradient-accent);
    }

    .stat-item:hover {
        transform: translateY(-4px);
        box-shadow: var(--box-shadow-lg);
    }

    .stat-item .stat-icon {
        font-size: 3rem;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 1rem;
        transition: var(--transition);
    }

    .stat-item:hover .stat-icon {
        transform: scale(1.1);
    }

    .stat-item .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        background: var(--gradient-accent);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-item .stat-text {
        font-size: 1rem;
        color: var(--gray-600);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .footer {
        background: linear-gradient(135deg, var(--nf-primary-dark) 0%, var(--nf-navy) 50%, var(--nf-secondary-dark) 100%);
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    
    .footer::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 20% 30%, rgba(255, 193, 7, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%, rgba(25, 135, 84, 0.1) 0%, transparent 50%);
        pointer-events: none;
    }

    .footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: var(--gradient-accent);
        z-index: 2;
    }

    .footer-logo {
        height: 60px;
        width: 60px;
        object-fit: contain;
        transition: transform 0.3s ease;
        margin-right: 0.5rem;
    }

    .footer-logo:hover {
        transform: scale(1.05);
    }

    .footer-title {
        display: inline-block;
        vertical-align: middle;
    }

    .footer-description {
        color: rgba(255, 255, 255, 0.8);
        line-height: 1.6;
        font-size: 0.95rem;
        margin-left: 0.1rem;
    }

    .footer-links h5 {
        color: #fff;
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        position: relative;
        padding-bottom: 0.5rem;
    }

    .footer-links h5::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: 0;
        width: 50px;
        height: 3px;
        background: var(--nf-accent);
        border-radius: 2px;
    }

    .footer-links ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer-links ul li {
        margin-bottom: 0.75rem;
    }

    .footer-links ul li a {
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .footer-links ul li a i {
        font-size: 0.8rem;
        transition: transform 0.3s ease;
    }

    .footer-links ul li a:hover {
        color: #fff;
        padding-left: 5px;
    }

    .footer-links ul li a:hover i {
        transform: translateX(3px);
    }

    .social-icons {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .social-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        color: white;
        font-size: 1.2rem;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .social-icon:hover {
        background: var(--gradient-primary);
        color: #fff;
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(179, 18, 23, 0.4);
    }

    .contact-info li {
        display: flex;
        align-items: flex-start;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    
    .contact-info li:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateX(5px);
    }

    .contact-info .icon {
        width: 50px;
        height: 50px;
        background: var(--gradient-primary);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 15px rgba(179, 18, 23, 0.3);
        transition: var(--transition);
    }
    
    .contact-info li:hover .icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 6px 20px rgba(179, 18, 23, 0.4);
    }

    .contact-info .text {
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.6;
        font-size: 1rem;
        flex: 1;
    }

    .footer-divider {
        margin: 2rem 0;
        border-color: rgba(255, 255, 255, 0.1);
    }

    .copyright {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
        margin: 0;
    }

    @media (max-width: 991.98px) {
        .footer .row > div {
            margin-bottom: 2rem;
        }
        .foot-info {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 0.5rem !important;
        }
        .footer-description {
            max-width: 100%;
        }
    }

    @media (max-width: 767.98px) {
        .footer {
            text-align: center;
        }
        .footer-links h5::after {
            left: 50%;
            transform: translateX(-50%);
        }
        .social-icons {
            justify-content: center;
        }
        .contact-info li {
            justify-content: center;
        }
        .footer-links ul li a {
            justify-content: center;
        }
        .foot-info {
            flex-direction: column !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }
        .footer-description {
            margin-left: 0;
            max-width: 100%;
        }
    }
    
    /* Enhanced Button Styles */
    .btn-danger,
    .btn-outline-danger {
        background: var(--gradient-primary);
        border: none;
        color: white;
        font-weight: 600;
        padding: 0.75rem 2rem;
        border-radius: var(--border-radius);
        transition: var(--transition);
        box-shadow: 0 4px 15px rgba(179, 18, 23, 0.3);
    }
    
    .btn-danger:hover,
    .btn-outline-danger:hover {
        background: linear-gradient(135deg, var(--nf-primary-light), var(--nf-primary));
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(179, 18, 23, 0.4);
        color: white;
    }
    
    .btn-outline-danger {
        background: transparent;
        border: 2px solid var(--nf-primary);
        color: var(--nf-primary);
    }
    
    .btn-outline-danger:hover {
        background: var(--gradient-primary);
        color: white;
    }
    
    /* Contact Information Card Styles */
    .contact-info-clean {
        border: 1px solid var(--gray-200) !important;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 249, 250, 0.98) 100%);
    }
    
    .contact-header-icon {
        width: 70px;
        height: 70px;
        background: var(--gradient-primary);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        box-shadow: 0 4px 20px rgba(179, 18, 23, 0.3);
        font-size: 1.75rem;
        color: white;
    }
    
    .contact-info-unified {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    
    .contact-info-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1rem;
        background: rgba(179, 18, 23, 0.03);
        border-radius: var(--border-radius-sm);
        border-left: 3px solid var(--nf-primary);
        transition: var(--transition);
    }
    
    .contact-info-item:hover {
        background: rgba(179, 18, 23, 0.06);
        border-left-color: var(--nf-accent);
        transform: translateX(3px);
    }
    
    .contact-info-icon {
        font-size: 1.5rem;
        color: var(--nf-primary);
        margin-top: 0.25rem;
        flex-shrink: 0;
        width: 24px;
        text-align: center;
    }
    
    .contact-info-text {
        flex: 1;
        min-width: 0;
    }
    
    .contact-info-text strong {
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--nf-primary);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .contact-info-text p {
        font-size: 0.9rem;
        color: var(--gray-700);
        line-height: 1.6;
        margin: 0;
    }
    
    .contact-info-link {
        color: var(--nf-primary);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
    }
    
    .contact-info-link:hover {
        color: var(--nf-primary-dark);
        text-decoration: underline;
    }
    
    .btn-contact {
        background: var(--gradient-primary);
        color: white;
        border: none;
        padding: 0.875rem 1.5rem;
        border-radius: var(--border-radius-sm);
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(179, 18, 23, 0.3);
        transition: var(--transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-contact:hover {
        background: linear-gradient(135deg, var(--nf-primary-light), var(--nf-primary));
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(179, 18, 23, 0.4);
        color: white;
    }
    
    /* Alert Enhancement */
    .alert-info {
        background: linear-gradient(135deg, rgba(13, 71, 161, 0.1) 0%, rgba(25, 135, 84, 0.1) 100%);
        border-left: 4px solid var(--nf-navy);
        border-radius: var(--border-radius);
    }
    
    /* Section Backgrounds */
    section.bg-light {
        background: linear-gradient(180deg, var(--gray-50) 0%, var(--white) 100%) !important;
    }
    
    /* Enhanced Text Colors */
    .text-muted {
        color: var(--gray-600) !important;
    }
    
    /* Service Cards */
    .service-card {
        border: 1px solid var(--gray-200);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }
    
    .service-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-accent);
        transform: scaleX(0);
        transition: var(--transition);
    }
    
    .service-card:hover::before {
        transform: scaleX(1);
    }
    
    .service-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--box-shadow-lg);
    }
    
    .service-icon {
        width: 70px;
        height: 70px;
        background: var(--gradient-primary);
        border-radius: var(--border-radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(179, 18, 23, 0.3);
        transition: var(--transition);
    }
    
    .service-card:hover .service-icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 6px 20px rgba(179, 18, 23, 0.4);
    }
    
    @media (max-width: 991.98px) {
        .contact-info-clean {
            position: relative !important;
            margin-top: 2rem;
        }
        
        .contact-info-item {
            padding: 1rem;
        }
        
        .contact-info-icon {
            width: 44px;
            height: 44px;
            min-width: 44px;
            font-size: 1.1rem;
        }
    }
</style>

<body>

    
                
            </div>
        </nav>
    </section>
    <!-- Navbar 1 End -->

    <!-- Navbar 2 Start -->
    <section id="Nav2">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/blood/negrosfirst-details.php">
                    <img src="/blood/imgs/nflogo.png" alt="Negros First Logo" style="max-width: 120px;">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item"><a class="nav-link selected" href="/blood/negrosfirst-details.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="/blood/negrosfirst-details-about.php">About Us</a></li>
                        <li class="nav-item"><a class="nav-link" href="/blood/negrosfirst-details-contact.php">Contact Us</a></li>
                    </ul>
                    <div class="d-flex ms-3">
                        <a class="btn btn-outline-light" href="/blood/index.php" title="Back to Home">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </section>
    <!-- Navbar 2 End -->
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content" data-aos="fade-right" data-aos-duration="1000">
                        <h1 class="hero-title">Negros First Provincial Blood Center</h1>
                        <p class="hero-subtitle">Dedicated to serving the blood needs of Negros Occidental with quality blood products and exceptional service. Join us in saving lives through voluntary blood donation.</p>
                        
                    </div>
                </div>
                <div class="col-lg-6 text-center" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                    <img src="assets/img/nflogo.png" alt="Negros First Logo" class="img-fluid" style="max-width: 300px; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));">
                </div>
            </div>
        </div>
    </section>



    <!-- Header End -->


    <!-- Blood Inventory Section -->
    <section id="inventory" class="py-5" style="padding-top: 100px !important;">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div data-aos="fade-up" data-aos-duration="800" class="mb-5">
                        <h2 class="fw-bold mb-4">Blood Inventory</h2>
                        <p class="lead mb-4">Check the availability of different blood types at our Negros First Blood Center. Our inventory is updated daily to provide accurate information.</p>
                    </div>

                    <div class="blood-type-container" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                        <?php
                        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        $inventoryMap = [];

                        // Create a map of blood type to units
                        foreach ($bloodInventory as $item) {
                            $inventoryMap[$item['blood_type']] = $item['available_units'];
                        }

                        foreach ($bloodTypes as $bloodType):
                            $units = isset($inventoryMap[$bloodType]) ? $inventoryMap[$bloodType] : 0;
                            $statusClass = 'danger';
                            $statusText = 'Critical';
                            $statusColor = '#b31217';

                            if ($units > 20) {
                                $statusClass = 'success';
                                $statusText = 'Good';
                                $statusColor = '#198754';
                            } elseif ($units > 10) {
                                $statusClass = 'warning';
                                $statusText = 'Low';
                                $statusColor = '#ffc107';
                            }
                        ?>
                            <div class="blood-type-item" data-aos="zoom-in" data-aos-duration="600">
                                <div class="blood-type-badge"><?php echo $bloodType; ?></div>
                                <div class="blood-type-units"><?php echo $units; ?> units</div>
                                <span class="blood-type-status" style="background-color: <?php echo $statusColor; ?>20; color: <?php echo $statusColor; ?>;"><?php echo $statusText; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert alert-info mt-5" data-aos="fade-up" data-aos-duration="800" data-aos-delay="400" style="padding: 1.5rem; border-radius: 12px; border-left: 4px solid var(--info-color);">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Inventory Update:</strong> Our blood inventory is updated in real-time. For urgent blood requests, please contact us directly.
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-lg contact-info-clean" style="position: sticky; top: 100px;">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <div class="contact-header-icon">
                                    <i class="bi bi-telephone-fill"></i>
                                </div>
                                <h3 class="card-title fw-bold mb-2 mt-3">Contact Information</h3>
                            </div>

                            <div class="contact-info-unified">
                                <div class="contact-info-item">
                                    <i class="bi bi-geo-alt-fill contact-info-icon"></i>
                                    <div class="contact-info-text">
                                        <strong>Address</strong>
                                        <p class="mb-0">Abad Santos Street, Bacolod<br>6100 Negros Occidental, Philippines</p>
                                    </div>
                                </div>

                                <div class="contact-info-item">
                                    <i class="bi bi-telephone-fill contact-info-icon"></i>
                                    <div class="contact-info-text">
                                        <strong>Phone</strong>
                                        <p class="mb-0">
                                            <a href="tel:+63344330313" class="contact-info-link">(034) 433 0313</a>
                                        </p>
                                    </div>
                                </div>

                                <div class="contact-info-item">
                                    <i class="bi bi-envelope-fill contact-info-icon"></i>
                                    <div class="contact-info-text">
                                        <strong>Email</strong>
                                        <p class="mb-0">
                                            <a href="mailto:info@negrosfirst.gov.ph" class="contact-info-link">info@negrosfirst.gov.ph</a>
                                        </p>
                                    </div>
                                </div>

                                <div class="contact-info-item">
                                    <i class="bi bi-clock-fill contact-info-icon"></i>
                                    <div class="contact-info-text">
                                        <strong>Operating Hours</strong>
                                        <p class="mb-0">
                                            Always Open<br>
                                            
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <a href="contact.php" class="btn btn-contact">
                                    <i class="bi bi-envelope me-2"></i>Contact Us
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Upcoming Blood Drives Section -->
    <section class="py-5 bg-light" style="padding: 80px 0 !important;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">Upcoming Blood Drives</h2>
                <p class="lead text-muted">Join us in our upcoming blood donation events</p>
            </div>

            <div class="row g-4">
                <?php if (count($upcomingDrives) > 0): ?>
                    <?php foreach ($upcomingDrives as $drive): ?>
                        <div class="col-md-4">
                            <div class="card blood-drive-card h-100 border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <div class="d-flex mb-3">
                                        <div class="blood-drive-date me-3">
                                            <span class="day"><?php echo date('d', strtotime($drive['date'])); ?></span>
                                            <span class="month"><?php echo date('M', strtotime($drive['date'])); ?></span>
                                        </div>
                                        <div>
                                            <h5 class="card-title"><?php echo $drive['title']; ?></h5>
                                            <p class="card-text text-muted mb-0">
                                                <i class="bi bi-geo-alt me-1"></i> <?php echo $drive['location']; ?>, <?php echo $drive['barangay_name']; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <p class="card-text">
                                            <i class="bi bi-clock me-2"></i>
                                            <?php echo date('g:i A', strtotime($drive['start_time'])); ?> -
                                            <?php echo date('g:i A', strtotime($drive['end_time'])); ?>
                                        </p>
                                    </div>
                                    <div class="d-grid">
                                        <a href="login.php?role=donor" class="btn btn-outline-danger">Register to Donate</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-calendar-x display-1 text-muted"></i>
                        <p class="mt-3">No upcoming blood drives scheduled at the moment.</p>
                        <p>Please check back later or contact us directly.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center mt-5">
                <a href="blood-drives.php" class="btn btn-danger" style="padding: 0.75rem 2.5rem; border-radius: 50px; font-weight: 600; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3); transition: all 0.3s ease;">
                    <i class="bi bi-calendar-event me-2"></i>View All Blood Drives
                </a>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5" style="padding: 80px 0 !important; background: linear-gradient(180deg, var(--gray-50) 0%, var(--white) 100%);">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="800">
                <h2 class="fw-bold mb-3">Our Impact</h2>
                <p class="lead text-muted">Making a difference in the lives of people across Negros Occidental</p>
            </div>

            <div class="row g-4" style="margin-top: 2rem;">
                <div class="col-md-3" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                    <div class="stat-item h-100">
                        <div class="stat-icon">
                            <i class="bi bi-droplet-fill"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($donationStats['total_donations']); ?></div>
                        <div class="stat-text">Total Donations</div>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                    <div class="stat-item h-100">
                        <div class="stat-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($donationStats['unique_donors']); ?></div>
                        <div class="stat-text">Unique Donors</div>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
                    <div class="stat-item h-100">
                        <div class="stat-icon">
                            <i class="bi bi-heart-pulse-fill"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($donationStats['total_units'] * 3); ?></div>
                        <div class="stat-text">Lives Saved</div>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-duration="800" data-aos-delay="400">
                    <div class="stat-item h-100">
                        <div class="stat-icon">
                            <i class="bi bi-hospital-fill"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($donationStats['total_units']); ?></div>
                        <div class="stat-text">Units Collected</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-5 bg-light" style="padding: 80px 0 !important;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">Our Services</h2>
                <p class="lead text-muted">Comprehensive blood bank services for the community</p>
            </div>

            <div class="row g-4" style="margin-top: 2rem;">
                <div class="col-md-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                    <div class="card h-100 border-0 shadow-sm service-card">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="bi bi-droplet-half"></i>
                            </div>
                            <h4 class="card-title">Blood Collection</h4>
                            <p class="card-text">We collect whole blood donations from voluntary donors. Each donation can save up to three lives.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                    <div class="card h-100 border-0 shadow-sm service-card">
                        <div class="card-body p-4">
                            <div class="service-icon" style="background: var(--gradient-secondary);">
                                <i class="bi bi-clipboard2-pulse"></i>
                            </div>
                            <h4 class="card-title">Blood Testing</h4>
                            <p class="card-text">All donated blood undergoes rigorous testing for infectious diseases to ensure safety for recipients.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
                    <div class="card h-100 border-0 shadow-sm service-card">
                        <div class="card-body p-4">
                            <div class="service-icon" style="background: linear-gradient(135deg, var(--nf-navy) 0%, var(--nf-navy-light) 100%);">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <h4 class="card-title">Blood Storage</h4>
                            <p class="card-text">We maintain proper storage facilities to preserve blood components at optimal conditions.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="400">
                    <div class="card h-100 border-0 shadow-sm service-card">
                        <div class="card-body p-4">
                            <div class="service-icon" style="background: linear-gradient(135deg, var(--nf-accent) 0%, var(--nf-accent-dark) 100%);">
                                <i class="bi bi-truck"></i>
                            </div>
                            <h4 class="card-title">Blood Distribution</h4>
                            <p class="card-text">We distribute blood products to hospitals and healthcare facilities as needed for patient care.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="500">
                    <div class="card h-100 border-0 shadow-sm service-card">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <h4 class="card-title">Blood Drives</h4>
                            <p class="card-text">We organize regular blood donation drives in communities, schools, and workplaces.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="600">
                    <div class="card h-100 border-0 shadow-sm service-card">
                        <div class="card-body p-4">
                            <div class="service-icon" style="background: var(--gradient-secondary);">
                                <i class="bi bi-megaphone"></i>
                            </div>
                            <h4 class="card-title">Awareness Programs</h4>
                            <p class="card-text">We conduct educational programs to raise awareness about the importance of blood donation.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>



    <!-- Guidelines Start -->
  <section id="articles">
      <div class="container">
          <h2 style="display: inline-block;">Blood Bank Guidelines</h2>
          <div class="swiper-container">
              <div class="button-area" style="display: inline-block; margin-left: 850px;">
                  <div class="swiper-button-next"></div>
                  <div class="swiper-button-prev"></div>
              </div>
              <div class="swiper-wrapper">
                  <div class="swiper-slide">
                      <div class="card">
                          <div class="card-img-top" style="position: relative;">
                              <img src="imgs/p3.jpg" alt="Know Your Blood Type">
                              <button class="like"><i class="fas fa-heart icon-large"></i></button>
                          </div>
                          <div class="card-body">
                              <h4 class="card-title">Know Your Blood Type</h4>
                              <p class="card-text">Identify your blood type before donation. This ensures compatibility for recipients and maintains a balanced blood supply across all blood types.</p>
                              <div class="btn-cont">
                                  <button class="card-btn" onclick="window.location.href = 'article.html';">Details</button>
                              </div>
                          </div>
                      </div>
                  </div>
                  <div class="swiper-slide">
                      <div class="card">
                          <div class="card-img-top" style="position: relative;">
                              <img src="imgs/p4.jpg" alt="Benefits of Donating Blood">
                              <button class="like"><i class="fas fa-heart icon-large"></i></button>
                          </div>
                          <div class="card-body">
                              <h4 class="card-title">Benefits of Donating Blood</h4>
                              <p class="card-text">Donating blood improves heart health, stimulates blood cell production, and provides life-saving support to those in need during emergencies or surgeries.</p>
                              <div class="btn-cont">
                                  <button class="card-btn" onclick="window.location.href = 'article.html';">Details</button>
                              </div>
                          </div>
                      </div>
                  </div>
                  <div class="swiper-slide">
                      <div class="card">
                          <div class="card-img-top" style="position: relative;">
                              <img src="imgs/p1.jpg" alt="Health Screening Before Donation">
                              <button class="like"><i class="fas fa-heart icon-large"></i></button>
                          </div>
                          <div class="card-body">
                              <h4 class="card-title">Health Screening Before Donation</h4>
                              <p class="card-text">Every donor must undergo a health screening to ensure safety for both the donor and the recipient. This includes checking hemoglobin levels, blood pressure, and more.</p>
                              <div class="btn-cont">
                                  <button class="card-btn" onclick="window.location.href = 'article.html';">Details</button>
                              </div>
                          </div>
                      </div>
                  </div>
                  <div class="swiper-slide">
                      <div class="card">
                          <div class="card-img-top" style="position: relative;">
                              <img src="imgs/p5.jpg" alt="Steps to Donate Blood">
                              <button class="like"><i class="fas fa-heart icon-large"></i></button>
                          </div>
                          <div class="card-body">
                              <h4 class="card-title">Steps to Donate Blood</h4>
                              <p class="card-text">Register at a Red Cross blood bank, complete the donor questionnaire, undergo a mini health check, and proceed to donation followed by recovery and refreshments.</p>
                              <div class="btn-cont">
                                  <button class="card-btn" onclick="window.location.href = 'article.html';">Details</button>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </section>
  <!-- Guidelines End -->


 


    <!-- Footer Start -->
    <footer id="footer" class="footer" style="margin-top: 80px !important;">
        <div class="container py-5" style="padding-top: 60px !important; padding-bottom: 40px !important;">
            <div class="row g-4">
                <div class="col-lg-5 col-md-6">
                    <div class="foot-info mb-4 d-flex align-items-center gap-3 flex-wrap">
                        <img src="assets/img/nflogo.png" alt="Negros First Logo" class="footer-logo mb-0">
                        <span class="footer-title" style="font-weight:700; font-size:1.5rem; color:var(--nf-accent); letter-spacing:1px;">Negros First Provincial Blood Center</span>
                    </div>
                    <p class="mb-3 footer-description" style="max-width: 420px;">Negros First Provincial Blood Center is dedicated to providing safe and quality blood products to the people of Negros Occidental. Join us in saving lives through voluntary blood donation.</p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com/negrosfirst" target="_blank" aria-label="Facebook" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/negrosfirst" target="_blank" aria-label="Instagram" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="https://twitter.com/negrosfirst" target="_blank" aria-label="Twitter" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="https://api.whatsapp.com/send?phone=+63344330313" target="_blank" aria-label="WhatsApp" class="social-icon"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <div class="footer-links">
                        <h5>Quick Links</h5>
                        <ul>
                            <li><a href="negrosfirstportal.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                            <li><a href="aboutnf.php"><i class="fas fa-chevron-right"></i> About Us</a></li>
                            <li><a href="drives.php"><i class="fas fa-chevron-right"></i> Blood Drives</a></li>
                            <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="footer-links">
                        <h5>Contact Information</h5>
                        <ul class="contact-info" style="margin-top: 1.5rem; padding: 0;">
                            <li>
                                <div class="icon"><i class="bi bi-geo-alt-fill"></i></div>
                                <div class="text">Abad Santos Street, Bacolod, 6100 Negros Occidental, Philippines</div>
                            </li>
                            <li>
                                <div class="icon"><i class="bi bi-telephone-fill"></i></div>
                                <div class="text">(034) 433 0313</div>
                            </li>
                            <li>
                                <div class="icon"><i class="bi bi-envelope-fill"></i></div>
                                <div class="text">info@negrosfirst.gov.ph</div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <hr class="footer-divider">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="copyright">&copy; <?php echo date('Y'); ?> Negros First Provincial Blood Center. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script type="text/javascript" src="js/swiper.min.js"></script>
    <script type="text/javascript" src="js/wow.min.js"></script>
    <script type="text/javascript" src="js/main.js"></script>
    
    <script>
        // Initialize AOS (Animate On Scroll)
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Counter animation for statistics
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/,/g, ''));
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current).toLocaleString();
                }, 16);
            });
        }

        // Trigger counter animation when statistics section is visible
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    observer.unobserve(entry.target);
                }
            });
        });

        const statsSection = document.querySelector('.stat-item');
        if (statsSection) {
            observer.observe(statsSection);
        }

        // Enhanced navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
        });

        // Initialize Swiper for guidelines section
        if (document.querySelector('.swiper-container')) {
            const swiper = new Swiper('.swiper-container', {
                slidesPerView: 1,
                spaceBetween: 30,
                loop: true,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                breakpoints: {
                    768: {
                        slidesPerView: 2,
                    },
                    1024: {
                        slidesPerView: 3,
                    }
                }
            });
        }

        // Add loading animation
        window.addEventListener('load', () => {
            document.body.classList.add('loaded');
        });
    </script>
</body>

</html>