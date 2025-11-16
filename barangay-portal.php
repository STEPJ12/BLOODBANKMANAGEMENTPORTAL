<?php
// Include database connection
require_once 'config/db.php';

// Get blood inventory for both blood banks
$redcrossInventory = executeQuery("
    SELECT blood_type, SUM(units) as total_units
    FROM blood_inventory
    WHERE organization_type = 'redcross' AND status = 'Available' AND expiry_date >= CURDATE()
    GROUP BY blood_type
    ORDER BY blood_type
");

$negrosfirstInventory = executeQuery("
    SELECT blood_type, SUM(units) as total_units
    FROM blood_inventory
    WHERE organization_type = 'negrosfirst' AND status = 'Available' AND expiry_date >= CURDATE()
    GROUP BY blood_type
    ORDER BY blood_type
");


// Get upcoming blood drives
$upcomingDrives = executeQuery("
    SELECT bd.*, b.name as barangay_name,
           CASE WHEN bd.organization_type = 'redcross' THEN 'Red Cross' ELSE 'Negros First' END as organization_name
    FROM blood_drives bd
    JOIN barangay_users b ON bd.barangay_id = b.id
    WHERE bd.date >= CURDATE() AND bd.status = 'Scheduled'
    ORDER BY bd.date ASC
    LIMIT 6
");

// Get barangay statistics
$barangayStats = executeQuery("
    SELECT
        COUNT(DISTINCT d.id) as total_donors,
        COUNT(DISTINCT bd.id) as total_drives,
        SUM(CASE WHEN bd.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_drives
    FROM barangay_users b
    LEFT JOIN donors d ON d.barangay_id = b.id
    LEFT JOIN blood_drives bd ON bd.barangay_id = b.id
");

// Get latest announcements (add after other queries)
$announcements = executeQuery("
    SELECT a.*, 
           CASE 
               WHEN a.organization_type = 'redcross' THEN 'Red Cross'
               WHEN a.organization_type = 'negrosfirst' THEN 'Negros First'
               ELSE 'Barangay' 
           END as organization_name
    FROM announcements a
    WHERE a.status = 'Active' AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
    ORDER BY a.created_at DESC
    LIMIT 6
");
$announcements = $announcements ? $announcements : array();

// Page title
$pageTitle = "Barangay Portal - Blood Bank System";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons - CDN with fallback -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <style>
    :root {
        --primary-color: #3b82f6;
        --primary-dark: #2563eb;
        --primary-light: #dbeafe;
        --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%);
        --secondary-color: #2a363b;
        --secondary-light: #4a5568;
        --accent-color: #eab308;
        --accent-light: #fbbf24;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --info-color: #3b82f6;
        --light-bg: #f8f9fa;
        --card-bg: #ffffff;
        --glass-bg: rgba(255, 255, 255, 0.95);
        --gradient-primary: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%);
        --gradient-secondary: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        --gradient-hero: linear-gradient(135deg, #3b82f6 0%, #2563eb 25%, #eab308 50%, #fbbf24 100%);
        --gradient-accent: linear-gradient(135deg, #eab308 0%, #fbbf24 50%, #f59e0b 100%);
        --shadow-sm: 0 2px 4px rgba(59, 130, 246, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
        --shadow-md: 0 4px 8px rgba(59, 130, 246, 0.12), 0 2px 4px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 20px rgba(59, 130, 246, 0.15), 0 4px 8px rgba(0, 0, 0, 0.08);
        --shadow-xl: 0 20px 40px rgba(59, 130, 246, 0.2), 0 8px 16px rgba(0, 0, 0, 0.1);
        --border-radius: 10px;
        --border-radius-lg: 16px;
        --border-radius-xl: 20px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        color: #2a363b;
        background: #f8f9fa;
        min-height: 100vh;
        line-height: 1.6;
        font-weight: 400;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    /* Ensure Bootstrap Icons display properly */
    .bi {
        display: inline-block;
        font-family: "bootstrap-icons" !important;
        font-style: normal;
        font-weight: normal !important;
        font-variant: normal;
        text-transform: none;
        line-height: 1;
        vertical-align: -0.125em;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    .navbar {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 30%, #eab308 70%, #fbbf24 100%) !important;
        backdrop-filter: blur(20px);
        border-bottom: 2px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3), 0 2px 8px rgba(234, 179, 8, 0.2);
        padding: 1rem 0;
        transition: var(--transition);
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    
    .navbar-brand {
        font-weight: 700;
        font-size: 1.5rem;
        color: #ffffff !important;
        text-decoration: none;
        transition: var(--transition);
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    
    .navbar-brand:hover {
        color: #ffffff !important;
        transform: scale(1.05);
        text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }
    
    .navbar-brand i {
        color: #ffffff;
        font-size: 1.75rem;
        margin-right: 0.75rem;
        transition: var(--transition);
        display: inline-block;
        vertical-align: middle;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    }
    
    .navbar-nav .nav-link {
        font-weight: 500;
        color: rgba(255, 255, 255, 0.95) !important;
        padding: 0.75rem 1.25rem;
        border-radius: var(--border-radius);
        transition: var(--transition);
        margin: 0 0.25rem;
        position: relative;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }
    
    .navbar-nav .nav-link::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 0;
        height: 2px;
        background: #ffffff;
        transition: var(--transition);
        transform: translateX(-50%);
        box-shadow: 0 2px 4px rgba(255, 255, 255, 0.5);
    }
    
    .navbar-nav .nav-link.active {
        color: #ffffff !important;
        background: rgba(255, 255, 255, 0.2);
        font-weight: 600;
        backdrop-filter: blur(10px);
    }
    
    .navbar-nav .nav-link.active::before {
        width: 80%;
    }
    
    .navbar-nav .nav-link:hover {
        color: #ffffff !important;
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-1px);
        backdrop-filter: blur(10px);
    }
    
    .navbar-nav .nav-link:hover::before {
        width: 80%;
    }

    .hero-section {
        position: relative;
        background: url('assets/img/barangay.jpg') center/cover no-repeat;
        overflow: hidden;
        min-height: 500px;
        display: flex;
        align-items: center;
    }
    
    
    .hero-section::after {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0.4) 50%, rgba(234, 179, 8, 0.4) 100%);
        z-index: 1;
    }
    
    .hero-content {
        position: relative;
        z-index: 2;
        color: #fff;
        padding: 100px 0 80px;
        text-align: center;
    }
    
    .hero-content h1 {
        font-size: 4rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        text-shadow: 0 2px 8px rgba(0,0,0,0.5);
        margin-bottom: 1.5rem;
        line-height: 1.1;
        color: #ffffff !important;
    }
    
    .hero-content p {
        font-size: 1.25rem;
        color: #ffffff !important;
        margin-bottom: 2.5rem;
        text-shadow: 0 2px 6px rgba(0,0,0,0.5);
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.7;
        font-weight: 500;
    }
    
    .hero-cta {
        display: flex;
        gap: 1.5rem;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 2rem;
    }
    
    .hero-cta .btn {
        padding: 1rem 2.5rem;
        font-weight: 600;
        border-radius: var(--border-radius-lg);
        text-transform: none;
        letter-spacing: 0.025em;
        transition: var(--transition);
        font-size: 1.1rem;
        position: relative;
        overflow: hidden;
    }
    
    .hero-cta .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: var(--transition);
    }
    
    .hero-cta .btn:hover::before {
        left: 100%;
    }
    
    .hero-cta .btn-light {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #ffffff;
        border: 2px solid #3b82f6;
        box-shadow: var(--shadow-lg);
    }
    
    .hero-cta .btn-light:hover {
        background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%);
        border-color: #eab308;
        transform: translateY(-3px);
        box-shadow: var(--shadow-xl);
        color: #1e293b;
    }
    
    .hero-cta .btn-outline-light {
        border: 2px solid #eab308;
        color: #ffffff;
        background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%);
        backdrop-filter: blur(10px);
    }
    
    .hero-cta .btn-outline-light:hover {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-color: #3b82f6;
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
        color: #ffffff;
    }

    /* Statistics Section */
    .stats-section {
        background: #ffffff;
        padding: 100px 0 80px;
        position: relative;
    }
    
    .stats-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.3), transparent);
    }
    
    .section-title {
        text-align: center;
        margin-bottom: 4rem;
    }
    
    .section-title h2 {
        font-size: 3rem;
        font-weight: 800;
        color: var(--secondary-color);
        margin-bottom: 1.5rem;
        line-height: 1.2;
        letter-spacing: -0.025em;
    }
    
    .section-title p {
        font-size: 1.2rem;
        color: var(--secondary-light);
        max-width: 700px;
        margin: 0 auto;
        line-height: 1.6;
    }
    
    .stat-item {
        background: var(--card-bg);
        border-radius: var(--border-radius-xl);
        box-shadow: var(--shadow-md);
        padding: 3rem 2rem;
        margin-bottom: 2rem;
        transition: var(--transition);
        border: 1px solid rgba(59, 130, 246, 0.2);
        text-align: center;
        position: relative;
        overflow: hidden;
        height: 100%;
    }
    
    .stat-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
        border-radius: var(--border-radius-xl) var(--border-radius-xl) 0 0;
    }
    
    .stat-item::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.2) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        transition: var(--transition);
    }
    
    .stat-item:hover {
        transform: translateY(-12px);
        box-shadow: var(--shadow-xl);
    }
    
    .stat-item:hover::after {
        width: 200px;
        height: 200px;
    }
    
    .stat-icon {
        font-size: 3.5rem;
        color: #3b82f6;
        margin-bottom: 1.5rem;
        display: block;
        transition: var(--transition);
        position: relative;
        z-index: 2;
    }
    
    .stat-item:hover .stat-icon {
        transform: scale(1.1);
        color: #eab308;
    }
    
    .stat-number {
        font-size: 3rem;
        font-weight: 900;
        color: var(--primary-color);
        margin-bottom: 0.75rem;
        display: block;
        position: relative;
        z-index: 2;
        line-height: 1;
    }
    
    .stat-text {
        font-size: 1.1rem;
        color: var(--secondary-color);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        position: relative;
        z-index: 2;
    }

    /* Announcements Section */
    .announcement-card {
        border-radius: 18px;
        box-shadow: var(--shadow);
        transition: transform 0.25s, box-shadow 0.25s;
        min-height: 320px;
        background: var(--glass-bg) !important;
        border-left: 5px solid var(--primary-color);
        border: none;
    }
    .announcement-card:hover {
        transform: translateY(-8px) scale(1.03);
        box-shadow: 0 16px 40px rgba(59, 130, 246, 0.2), 0 2px 12px rgba(0,0,0,0.1);
        border-left-color: #eab308;
    }
    .announcement-card .card-title {
        font-size: 1.18rem;
        font-weight: 700;
        color: #eab308;
    }
    .announcement-content {
        font-size: 1.01rem;
        color: #e2e8f0;
        line-height: 1.7;
    }
    .announcement-card .badge {
        border-radius: 8px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    /* Blood Bank Cards */
    .blood-banks-section {
        padding: 100px 0;
        background: var(--light-bg);
        position: relative;
    }
    
    .blood-banks-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.3), transparent);
    }
    
    .blood-bank-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-xl);
        box-shadow: var(--shadow-md);
        transition: var(--transition);
        padding: 3rem 2.5rem;
        height: 100%;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .blood-bank-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
        border-radius: var(--border-radius-xl) var(--border-radius-xl) 0 0;
    }
    
    .blood-bank-card::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        transition: var(--transition);
    }
    
    .blood-bank-card:hover {
        transform: translateY(-12px);
        box-shadow: var(--shadow-xl);
    }
    
    .blood-bank-card:hover::after {
        width: 300px;
        height: 300px;
    }
    
    .blood-bank-header {
        text-align: center;
        margin-bottom: 2rem;
        position: relative;
        z-index: 2;
    }
    
    .blood-bank-header img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid #fff;
        box-shadow: var(--shadow-lg);
        margin-bottom: 1.5rem;
        transition: var(--transition);
    }
    
    .blood-bank-card:hover .blood-bank-header img {
        transform: scale(1.05);
        box-shadow: var(--shadow-xl);
    }
    
    .blood-bank-header h3 {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--secondary-color);
        margin-bottom: 0.75rem;
        line-height: 1.3;
    }
    
    .blood-bank-info {
        margin-bottom: 2rem;
        position: relative;
        z-index: 2;
    }
    
    .blood-bank-info p {
        margin-bottom: 1rem;
        color: var(--secondary-light);
        font-size: 1rem;
        display: flex;
        align-items: center;
        line-height: 1.5;
    }
    
    .blood-bank-info p i {
        color: #3b82f6;
        margin-right: 0.75rem;
        width: 20px;
        font-size: 1.1rem;
    }
    
    .blood-bank-card .btn {
        width: 100%;
        font-weight: 600;
        border-radius: var(--border-radius-lg);
        padding: 1rem 2rem;
        transition: var(--transition);
        font-size: 1.1rem;
        position: relative;
        z-index: 2;
        overflow: hidden;
    }
    
    .blood-bank-card .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: var(--transition);
    }
    
    .blood-bank-card .btn:hover::before {
        left: 100%;
    }
    
    .blood-bank-card .btn-outline-danger {
        border: 2px solid #3b82f6;
        color: #3b82f6;
        background: rgba(59, 130, 246, 0.1);
    }
    
    .blood-bank-card .btn-outline-danger:hover {
        background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%);
        border-color: #eab308;
        color: #1a2332;
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }

    /* Announcements Section */
    .announcements-section {
        padding: 100px 0;
        background: #ffffff;
        position: relative;
    }
    
    .announcements-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.3), transparent);
    }
    
    .announcement-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-xl);
        box-shadow: var(--shadow-md);
        transition: var(--transition);
        padding: 2.5rem;
        height: 100%;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .announcement-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
        border-radius: var(--border-radius-xl) var(--border-radius-xl) 0 0;
    }
    
    .announcement-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-xl);
        border-left-color: #eab308;
    }
    
    .announcement-card .card-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #eab308;
        margin-bottom: 1rem;
        line-height: 1.4;
    }
    
    .announcement-content {
        font-size: 1rem;
        color: var(--secondary-light);
        line-height: 1.7;
        margin-bottom: 1.5rem;
    }
    
    .announcement-card .badge {
        border-radius: var(--border-radius);
        font-weight: 600;
        letter-spacing: 0.025em;
        padding: 0.5rem 1rem;
    }

    /* Scroll to Top Button */
    #scrollTopBtn {
        display: none;
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 999;
        background: var(--primary-color);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 56px;
        height: 56px;
        font-size: 1.5rem;
        box-shadow: var(--shadow-lg);
        cursor: pointer;
        transition: var(--transition);
        backdrop-filter: blur(10px);
    }
    
    #scrollTopBtn:hover {
        background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%);
        color: #1a2332;
        transform: translateY(-3px);
        box-shadow: var(--shadow-xl);
    }

    /* Footer */
    .footer {
        background: var(--gradient-primary);
        color: #fff;
        position: relative;
        border-top: 4px solid rgba(234, 179, 8, 0.3);
        box-shadow: 0 -4px 20px rgba(59, 130, 246, 0.2);
    }
    
    .footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    }
    
    .footer-social {
        width: 48px;
        height: 48px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        color: #fff;
        font-size: 1.4rem;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .footer-social:hover {
        background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%);
        color: #1a2332;
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }
    
    .footer-links h5 {
        color: #fff;
        letter-spacing: 0.025em;
        font-weight: 700;
        margin-bottom: 1.5rem;
        font-size: 1.1rem;
    }
    
    .footer-links ul li {
        margin-bottom: 0.75rem;
    }
    
    .footer-links ul li a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: var(--transition);
        font-weight: 400;
        display: inline-flex;
        align-items: center;
    }
    
    .footer-links ul li a:hover {
        color: #fff;
        transform: translateX(5px);
    }
    
    .footer .bi {
        vertical-align: -0.15em;
        margin-right: 0.5rem;
    }
    /* Improved Container Spacing */
    .container {
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }
    
    /* Better Section Spacing */
    section {
        padding-left: 0;
        padding-right: 0;
    }
    
    /* Improved Card Spacing */
    .row.g-4 {
        margin-left: -0.75rem;
        margin-right: -0.75rem;
    }
    
    .row.g-4 > * {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    
    /* Navbar Improvements */
    .navbar .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .navbar-nav {
        gap: 0.25rem;
    }
    
    .navbar .d-flex {
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .navbar .btn {
        white-space: nowrap;
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
        transition: var(--transition);
    }
    
    .navbar .btn-outline-light {
        background: rgba(255, 255, 255, 0.2) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.5) !important;
        backdrop-filter: blur(10px) !important;
    }
    
    .navbar .btn-outline-light:hover {
        background: rgba(255, 255, 255, 0.3) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.7) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3) !important;
    }
    
    .navbar .btn-light {
        background: rgba(255, 255, 255, 0.95) !important;
        color: #3b82f6 !important;
        border-color: rgba(255, 255, 255, 0.5) !important;
        font-weight: 600 !important;
    }
    
    .navbar .btn-light:hover {
        background: #ffffff !important;
        color: #2563eb !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.4) !important;
    }
    
    .navbar .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    /* Responsive Design */
    @media (max-width: 1200px) {
        .hero-content h1 {
            font-size: 3.5rem;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
        }
        
        .stat-item {
            padding: 2.5rem 1.5rem;
        }
        
        .blood-bank-card {
            padding: 2.5rem 2rem;
        }
        
        .container {
            padding-left: 1.25rem;
            padding-right: 1.25rem;
        }
    }
    
    @media (max-width: 991.98px) {
        .hero-content h1 {
            font-size: 3rem;
        }
        
        .hero-content p {
            font-size: 1.1rem;
        }
        
        .section-title h2 {
            font-size: 2.25rem;
        }
        
        .section-title p {
            font-size: 1.1rem;
        }
        
        .stat-item {
            padding: 2rem 1.5rem;
        }
        
        .blood-bank-card {
            padding: 2rem 1.5rem;
        }
        
        .announcement-card {
            padding: 2rem;
        }
        
        .container {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        .navbar .d-flex {
            width: 100%;
            justify-content: center;
            margin-top: 0.5rem;
        }
    }
    
    @media (max-width: 767.98px) {
        .navbar {
            padding: 0.75rem 0;
        }
        
        .navbar-brand {
            font-size: 1.25rem;
        }
        
        .navbar-brand i {
            font-size: 1.5rem;
        }
        
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            font-size: 0.95rem;
        }
        
        .hero-content {
            padding: 60px 0 50px;
        }
        
        .hero-content h1 {
            font-size: 2.25rem;
            margin-bottom: 1rem;
        }
        
        .hero-content p {
            font-size: 1rem;
            margin-bottom: 2rem;
        }
        
        .hero-cta {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        .hero-cta .btn {
            width: 100%;
            max-width: 100%;
            padding: 0.875rem 1.5rem;
            font-size: 1rem;
        }
        
        .section-title {
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .section-title p {
            font-size: 0.95rem;
        }
        
        .stats-section,
        .blood-banks-section,
        .announcements-section {
            padding: 50px 0;
        }
        
        .stat-item {
            padding: 2rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1.25rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-text {
            font-size: 1rem;
        }
        
        .blood-bank-card {
            padding: 2rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .blood-bank-header img {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
        }
        
        .blood-bank-header h3 {
            font-size: 1.25rem;
        }
        
        .blood-bank-info p {
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
        }
        
        .announcement-card {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .announcement-card .card-title {
            font-size: 1.15rem;
            margin-bottom: 0.75rem;
        }
        
        .announcement-content {
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .footer .container {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        .footer .row > div {
            margin-bottom: 2rem;
        }
        
        #scrollTopBtn {
            bottom: 1rem;
            right: 1rem;
            width: 48px;
            height: 48px;
            font-size: 1.25rem;
        }
        
        .container {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
    }
    
    @media (max-width: 576px) {
        .hero-content {
            padding: 50px 0 40px;
        }
        
        .hero-content h1 {
            font-size: 1.875rem;
        }
        
        .hero-content p {
            font-size: 0.95rem;
        }
        
        .section-title h2 {
            font-size: 1.75rem;
        }
        
        .section-title p {
            font-size: 0.9rem;
        }
        
        .stats-section,
        .blood-banks-section,
        .announcements-section {
            padding: 40px 0;
        }
        
        .stat-item {
            padding: 1.5rem 1rem;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
        }
        
        .stat-text {
            font-size: 0.9rem;
        }
        
        .blood-bank-card {
            padding: 1.5rem 1rem;
        }
        
        .blood-bank-header img {
            width: 70px;
            height: 70px;
        }
        
        .announcement-card {
            padding: 1.25rem;
        }
        
        .container {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
    }
    
    @media (max-width: 400px) {
        .navbar-brand {
            font-size: 1.1rem;
        }
        
        .navbar .btn {
            font-size: 0.85rem;
            padding: 0.4rem 0.75rem;
        }
        
        .hero-content h1 {
            font-size: 1.5rem;
        }
        
        .section-title h2 {
            font-size: 1.5rem;
        }
    }
    
    /* General Text Visibility - Ensure all text is visible */
    h1, h2, h3, h4, h5, h6 {
        color: var(--secondary-color) !important;
    }
    
    p, span, div, label, small, li, td, th {
        color: var(--secondary-color) !important;
    }
    
    .text-muted {
        color: var(--secondary-light) !important;
    }
    
    .card {
        background: var(--card-bg) !important;
        border: 1px solid rgba(20, 184, 166, 0.2) !important;
        color: var(--secondary-color) !important;
    }
    
    .card-body {
        color: var(--secondary-color) !important;
    }
    
    .card-title, .card-title * {
        color: var(--secondary-color) !important;
    }
    
    .table {
        color: var(--secondary-color) !important;
    }
    
    .table thead th {
        color: var(--secondary-color) !important;
        border-color: rgba(59, 130, 246, 0.2) !important;
    }
    
    .table tbody td {
        color: var(--secondary-color) !important;
        border-color: rgba(59, 130, 246, 0.1) !important;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
        border-color: #3b82f6 !important;
        color: #ffffff !important;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%) !important;
        border-color: #eab308 !important;
        color: #1a2332 !important;
    }
    
    .btn-success {
        background: #10b981 !important;
        border-color: #10b981 !important;
        color: #ffffff !important;
    }
    
    .btn-success:hover {
        background: #f97316 !important;
        border-color: #eab308 !important;
        color: #1a2332 !important;
    }
    
    .badge {
        color: #ffffff !important;
    }
    
    i, [class*="bi-"] {
        color: inherit;
    }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm sticky-top" style="z-index: 1030;">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <i class="bi bi-droplet-fill me-2"></i>
                <span class="fw-bold">Barangay Portal</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="barangay-portal.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="barangay-blood-drives.php">Blood Drives</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="barangay-about.php">About Us</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="barangay-login.php" class="btn btn-outline-light me-2" role="button" aria-label="Login as BHW" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}" onkeyup="if(event.key===' '){event.preventDefault();}" tabindex="0" style="background: rgba(255, 255, 255, 0.2); color: #ffffff; border-color: rgba(255, 255, 255, 0.5); backdrop-filter: blur(10px);">
                        <i class="bi bi-box-arrow-in-right me-1"></i>BHW Login
                    </a>
                    <a href="bhw-register.php" class="btn btn-light" role="button" aria-label="Register as BHW" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}" onkeyup="if(event.key===' '){event.preventDefault();}" tabindex="0" style="background: rgba(255, 255, 255, 0.95); color: #3b82f6; border-color: rgba(255, 255, 255, 0.5); font-weight: 600;">
                        <i class="bi bi-person-plus me-1"></i>Register
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container hero-content">
            <h1>Barangay Health Worker Portal</h1>
            <p>Coordinate blood donation drives, monitor blood inventory, and help save lives in your community through efficient healthcare management.</p>
            <div class="hero-cta">
                <a href="barangay-login.php" class="btn btn-light" role="button" aria-label="Login as BHW">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login as BHW
                </a>
                <a href="bhw-register.php" class="btn btn-outline-light" role="button" aria-label="Register as BHW">
                    <i class="bi bi-person-plus me-2"></i>Register Now
                </a>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="container">
            <div class="section-title">
                <h2>Community Health Impact</h2>
                <p>Track our collective efforts in improving healthcare accessibility and blood donation coordination in our barangays</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="stat-item">
                        <i class="bi bi-people-fill stat-icon"></i>
                        <div class="stat-number"><?php echo $barangayStats[0]['total_donors'] ?? '0'; ?></div>
                        <div class="stat-text">Registered Donors</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <i class="bi bi-calendar-event stat-icon"></i>
                        <div class="stat-number"><?php echo $barangayStats[0]['total_drives'] ?? '0'; ?></div>
                        <div class="stat-text">Blood Drives Organized</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <i class="bi bi-activity stat-icon"></i>
                        <div class="stat-number"><?php echo $barangayStats[0]['recent_drives'] ?? '0'; ?></div>
                        <div class="stat-text">Recent Drives (30 days)</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blood Banks Section -->
    <section class="blood-banks-section">
        <div class="container">
            <div class="section-title">
                <h2>Partner Blood Banks</h2>
                <p>Connect with our trusted blood bank partners for donation drives and blood requests</p>
            </div>

            <div class="row g-4">
                <!-- Red Cross Card -->
                <div class="col-lg-6">
                    <div class="blood-bank-card">
                        <div class="blood-bank-header">
                            <img src="assets/img/rclogo.jpg" alt="Red Cross Logo">
                            <h3>Philippine Red Cross</h3>
                        </div>
                        <div class="blood-bank-info">
                            <p><i class="bi bi-geo-alt"></i>10th St, Bacolod, 6100 Negros Occidental</p>
                            <p><i class="bi bi-telephone"></i>(034) 434 8541</p>
                            <p><i class="bi bi-clock"></i>24/7 Emergency Services</p>
                        </div>
                        <a href="redcrossportal.php" class="btn btn-outline-danger">
                            <i class="bi bi-arrow-right me-2"></i>View Details
                        </a>
                    </div>
                </div>

                <!-- Negros First Card -->
                <div class="col-lg-6">
                    <div class="blood-bank-card">
                        <div class="blood-bank-header">
                            <img src="assets/img/nflogo.png" alt="Negros First Logo">
                            <h3>Negros First Provincial Blood Center</h3>
                        </div>
                        <div class="blood-bank-info">
                            <p><i class="bi bi-geo-alt"></i>Abad Santos Street, Bacolod, 6100 Negros Occidental</p>
                            <p><i class="bi bi-telephone"></i>(034) 433 0313</p>
                            <p><i class="bi bi-clock"></i>Mon-Fri: 8AM-5PM</p>
                        </div>
                        <a href="negrosfirstportal.php" class="btn btn-outline-danger">
                            <i class="bi bi-arrow-right me-2"></i>View Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Announcements Section -->
    <section class="announcements-section">
        <div class="container">
            <div class="section-title">
                <h2>Latest Announcements</h2>
                <p>Stay updated with the latest news and important information from our blood bank partners</p>
            </div>
            
            <div class="row g-4">
                <?php if (!empty($announcements)): ?>
                    <?php foreach (array_slice($announcements, 0, 3) as $announcement): ?>
                        <div class="col-lg-4">
                            <div class="announcement-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($announcement['organization_name']); ?></span>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></small>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                <div class="announcement-content">
                                    <?php echo htmlspecialchars(substr($announcement['content'], 0, 150)) . '...'; ?>
                                </div>
                                <a href="#" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-arrow-right me-1"></i>Read More
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="bi bi-megaphone display-1 text-muted"></i>
                            <h4 class="mt-3 text-muted">No announcements at the moment</h4>
                            <p class="text-muted">Check back later for updates and important information.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Scroll to Top Button -->
    <button id="scrollTopBtn" title="Back to top"><i class="bi bi-arrow-up"></i></button>

    <!-- Footer -->
    <footer class="footer mt-5" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%); color: #fff; position: relative; border-top: 4px solid rgba(234,179,8,0.3); box-shadow: 0 -4px 20px rgba(59,130,246,0.15);">
        <div class="container py-5">
            <div class="row g-4 align-items-start">
                <div class="col-md-4 mb-4 mb-md-0">
                    <a class="d-flex align-items-center text-white text-decoration-none mb-3" href="index.php">
                        <i class="bi bi-droplet-fill me-2 fs-2"></i>
                        <span class="fw-bold fs-3">Barangay Portal</span>
                    </a>
                    <p class="text-white-50 mb-3">Connecting blood donors with those in need. Our mission is to ensure a safe and adequate blood supply for the community.</p>
                    <div class="d-flex gap-2 mt-3">
                        <a href="#" class="footer-social d-flex align-items-center justify-content-center"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="footer-social d-flex align-items-center justify-content-center"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="footer-social d-flex align-items-center justify-content-center"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="footer-social d-flex align-items-center justify-content-center"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="footer-links">
                        <h5 class="fw-bold mb-3"><i class="bi bi-link-45deg me-2"></i>Quick Links</h5>
                        <ul class="list-unstyled">
                            <li><a href="barangay-portal.php">Home</a></li>
                            <li><a href="barangay-about.php">About Us</a></li>
                            <li><a href="barangay-blood-drives.php">Blood Drives</a></li>
                            <li><a href="barangay-contact.php">Contact Us</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="footer-links">
                        <h5 class="fw-bold mb-3"><i class="bi bi-hospital me-2"></i>Blood Banks</h5>
                        <ul class="list-unstyled">
                            <li><a href="redcross-details.php">Red Cross Blood Bank</a></li>
                            <li><a href="negrosfirst-details.php">Negros First Blood Center</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="footer-links">
                        <h5 class="fw-bold mb-3"><i class="bi bi-telephone me-2"></i>Contact Information</h5>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-geo-alt me-2"></i> Bacolod City, Negros Occidental</li>
                            <li><i class="bi bi-telephone me-2"></i> (034) 123-4567</li>
                            <li><i class="bi bi-envelope me-2"></i> brgybata@bloodbankportal.com</li>
                        </ul>
                    </div>
                </div>
            </div>
            <hr class="mt-5 mb-3 border-light-subtle">
            <div class="text-center text-white-50 small">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Barangay Portal. All rights reserved.</p>
            </div>
        </div>
        <style>
        .footer-social {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            color: #fff;
            font-size: 1.3rem;
            transition: background 0.3s, color 0.3s;
        }
        .footer-social:hover {
            background: #fff;
            color: #3b82f6;
            transform: translateY(-3px) scale(1.1);
        }
        .footer-links h5 {
            color: #fff;
            letter-spacing: 0.5px;
        }
        .footer-links ul li a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            transition: color 0.2s;
        }
        .footer-links ul li a:hover {
            color: #fff;
            text-decoration: underline;
        }
        .footer .bi {
            vertical-align: -0.15em;
        }
        @media (max-width: 767.98px) {
            .footer .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .footer .row > div {
                margin-bottom: 2rem;
            }
        }
        </style>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll to Top Button
        const scrollBtn = document.getElementById('scrollTopBtn');
        window.onscroll = function() {
            if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
                scrollBtn.style.display = "block";
            } else {
                scrollBtn.style.display = "none";
            }
        };
        scrollBtn.onclick = function() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        };

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

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all cards and sections
        document.querySelectorAll('.stat-item, .blood-bank-card, .announcement-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.backdropFilter = 'blur(20px)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.backdropFilter = 'blur(20px)';
            }
        });

        // Add loading animation
        window.addEventListener('load', () => {
            document.body.style.opacity = '1';
        });

        // Initial body opacity
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.5s ease';

        // Add keyboard event handlers for anchor tags with role="button"
        document.querySelectorAll('a[role="button"]').forEach(link => {
            // Handle Enter key (triggers on keydown)
            link.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.click();
                }
            });
            
            // Handle Space key (triggers on keyup to match button behavior)
            link.addEventListener('keyup', function(e) {
                if (e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
            
            // Prevent scrolling when Space is pressed
            link.addEventListener('keydown', function(e) {
                if (e.key === ' ') {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>