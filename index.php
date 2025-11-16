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

// Get latest announcements
$announcements = executeQuery("
    SELECT a.*, 
           CASE WHEN a.organization_type = 'redcross' THEN 'Red Cross' ELSE 'Negros First' END as organization_name
    FROM announcements a
    WHERE a.status = 'Active'
    ORDER BY a.created_at DESC
    LIMIT 6
");

// Convert to empty array if query failed
$announcements = $announcements ? $announcements : array();

// Page title
$pageTitle = "Blood Bank Portal - Home";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons - Local Version -->
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <!-- Fallback for offline use -->
    <link rel="stylesheet" href="assets/css/bootstrap-icons-offline.css">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->

    <!-- website font  -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css"
        integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">

    <!-- Removed Bootstrap 4 include to prevent conflicts; keep any custom CSS if needed -->
    <!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" /> -->
    <link rel="stylesheet" href="css/swiper.min.css">
    <link rel="stylesheet" type="text/css" href="css/animate.css" />
    <link rel="stylesheet" type="text/css" href="css/style.css" />
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #dc3545;
            --primary-dark: #a71d2a;
            --primary-light: #ff6b7a;
            --secondary-color: #6c757d;
            --light-bg: #f8f9fa;
            --dark-bg: #1a1a2e;
            --negrosfirst-color: #198754;
            --negrosfirst-dark: #0f5132;
            --negrosfirst-light: #d1e7dd;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-red: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%);
            --gradient-green: linear-gradient(135deg, #198754 0%, #0f5132 100%);
            --shadow-sm: 0 2px 10px rgba(0,0,0,0.08);
            --shadow-md: 0 5px 25px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.15);
            --shadow-xl: 0 20px 60px rgba(0,0,0,0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            overflow-x: hidden;
            line-height: 1.7;
            background: #ffffff;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
        }

        /* Navbar Styles */
        .navbar {
            padding: 1.2rem 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
        }

        .navbar.scrolled {
            padding: 0.8rem 0;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-size: 1.6rem;
            font-weight: 700;
            background: var(--gradient-red);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
        }

        .navbar-brand i {
            background: var(--gradient-red);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .nav-link {
            font-weight: 500;
            color: #333 !important;
            margin: 0 0.5rem;
            position: relative;
            transition: color 0.3s ease;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--gradient-red);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 80%;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--primary-color) !important;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.9) 0%, rgba(167, 29, 42, 0.9) 100%), 
                        url('assets/img/bgl.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white;
            padding: 180px 0;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: float 20s infinite ease-in-out;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .hero-content {
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            animation: fadeInUp 1s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-content h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 2px 4px 8px rgba(0, 0, 0, 0.3);
            line-height: 1.2;
            letter-spacing: -1px;
        }

        .hero-content p {
            font-size: clamp(1.1rem, 2.5vw, 1.4rem);
            line-height: 1.8;
            margin-bottom: 2.5rem;
            opacity: 0.95;
            font-weight: 300;
        }

        /* Enhanced Buttons */
        .btn {
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-danger {
            background: var(--gradient-red);
            border: none;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }

        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.5);
        }

        .btn-outline-danger {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline-danger:hover {
            background: var(--gradient-red);
            color: white;
            border-color: transparent;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }

        .btn-outline-light {
            border: 2px solid white;
            color: white;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .btn-outline-light:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
        }

        .btn-lg {
            padding: 1rem 2.5rem;
            font-size: 1rem;
        }

        /* Blood Bank Cards */
        .blood-bank-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-md);
            height: 100%;
            border: none;
            padding: 2.5rem;
            position: relative;
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
        }

        .blood-bank-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient-red);
            transform: scaleX(0);
            transition: transform 0.5s ease;
        }

        .blood-bank-card:hover::before {
            transform: scaleX(1);
        }

        .blood-bank-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: var(--shadow-xl);
        }

        /* Red Cross specific styling */
        .redcross-card {
            border-left: 5px solid var(--primary-color);
        }

        .redcross-card::before {
            background: var(--gradient-red);
        }

        .redcross-card .blood-bank-header h3 {
            color: var(--primary-color);
            background: var(--gradient-red);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .redcross-card .btn-outline-danger {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .redcross-card .btn-outline-danger:hover {
            background: var(--gradient-red);
            color: white;
        }

        /* Negros First specific styling */
        .negrosfirst-card {
            border-left: 5px solid var(--negrosfirst-color);
        }

        .negrosfirst-card::before {
            background: var(--gradient-green);
        }

        .negrosfirst-card .blood-bank-header h3 {
            color: var(--negrosfirst-color);
            background: var(--gradient-green);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .negrosfirst-card .btn-outline-danger {
            border-color: var(--negrosfirst-color);
            color: var(--negrosfirst-color);
        }

        .negrosfirst-card .btn-outline-danger:hover {
            background: var(--gradient-green);
            color: white;
        }

        .negrosfirst-card .text-danger {
            color: var(--negrosfirst-color) !important;
        }

        /* Blood inventory styling */
        .blood-inventory-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .blood-type-badge {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .blood-type-badge:hover {
            transform: scale(1.1);
        }

        .inventory-count {
            font-size: 1rem;
            font-weight: 700;
            color: #333;
        }

        /* Organization-specific inventory headers */
        .redcross-card .blood-inventory-section h6 {
            color: var(--primary-color);
        }

        .negrosfirst-card .blood-inventory-section h6 {
            color: var(--negrosfirst-color);
        }

        .blood-bank-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .blood-bank-header img {
            width: 140px;
            height: 140px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 1.5rem;
            padding: 8px;
            transition: transform 0.5s ease, box-shadow 0.5s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .blood-bank-card:hover .blood-bank-header img {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .redcross-card .blood-bank-header img {
            border: 4px solid var(--primary-color);
            background: linear-gradient(135deg, #fff 0%, #ffe6e6 100%);
        }

        .negrosfirst-card .blood-bank-header img {
            border: 4px solid var(--negrosfirst-color);
            background: linear-gradient(135deg, #fff 0%, #e6f5ed 100%);
        }

        .blood-bank-header h3 {
            font-size: 1.75rem;
            color: var(--dark-bg);
            margin-bottom: 0.5rem;
            transition: transform 0.3s ease;
        }

        .blood-bank-card:hover .blood-bank-header h3 {
            transform: scale(1.05);
        }

        /* Blood Drive Cards */
        .blood-drive-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            border: none;
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .blood-drive-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.05) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .blood-drive-card:hover::after {
            opacity: 1;
        }

        .blood-drive-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .blood-drive-date {
            width: 80px;
            height: 80px;
            background: var(--gradient-red);
            color: white;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
            transition: transform 0.3s ease;
        }

        .blood-drive-card:hover .blood-drive-date {
            transform: scale(1.1) rotate(-5deg);
        }

        .blood-drive-date .day {
            font-size: 2rem;
            line-height: 1;
            font-weight: 800;
        }

        .blood-drive-date .month {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* Section Headers */
        section > .container > .text-center > h2,
        section > .container > h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
            padding-bottom: 1rem;
        }

        section > .container > .text-center > h2::after,
        section > .container > h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--gradient-red);
            border-radius: 2px;
        }

        /* Enhanced text styling */
        .text-muted {
            color: #6c757d !important;
            font-weight: 400;
        }

        /* CTA Section */
        .cta-section {
            background: var(--gradient-red);
            color: white;
            padding: 100px 0;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .cta-section .container {
            position: relative;
            z-index: 1;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 80px 0 20px;
            position: relative;
            overflow: hidden;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
        }

        .footer-links h5 {
            color: white;
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            position: relative;
            font-weight: 700;
        }

        .footer-links h5::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 50px;
            height: 3px;
            background: var(--gradient-red);
            border-radius: 2px;
        }

        .footer-links ul {
            list-style: none;
            padding: 0;
        }

        .footer-links ul li {
            margin-bottom: 12px;
        }

        .footer-links ul li a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .footer-links ul li a:hover {
            color: white;
            padding-left: 8px;
            transform: translateX(5px);
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            margin-right: 12px;
            color: white;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }

        .social-icons a:hover {
            background: var(--gradient-red);
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 5px 20px rgba(220, 53, 69, 0.4);
        }

        /* Additional Enhancements */
        .blood-bank-card p {
            color: #555;
            line-height: 1.8;
            margin-bottom: 0.5rem;
        }

        .blood-bank-card p i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }

        .card-title {
            font-weight: 700;
            color: #333;
            margin-bottom: 0.75rem;
        }

        .card-text {
            color: #666;
            line-height: 1.7;
        }

        /* Loading animation */
        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }

        /* Scroll to top button (optional enhancement) */
        .scroll-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--gradient-red);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
            z-index: 1000;
        }

        .scroll-to-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.5);
        }

        /* Responsive Adjustments */
        @media (max-width: 991.98px) {
            .navbar {
                padding: 0.5rem 0;
            }

            .hero-section {
                padding: 120px 0;
            }

            .blood-bank-card {
                margin-bottom: 2rem;
            }

            .cta-section {
                padding: 80px 0;
            }
        }

        @media (max-width: 767.98px) {
            .hero-content {
                padding: 0 1rem;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .blood-bank-header img {
                width: 120px;
                height: 120px;
            }

            .footer {
                padding: 50px 0 20px;
            }

            .footer-links {
                margin-bottom: 2rem;
            }

            .btn-lg {
                padding: 0.875rem 2rem;
                font-size: 0.95rem;
            }
        }

        @media (max-width: 575.98px) {
            .blood-drive-date {
                width: 70px;
                height: 70px;
            }

            .blood-drive-date .day {
                font-size: 1.75rem;
            }

            .blood-drive-date .month {
                font-size: 0.75rem;
            }

            .btn {
                padding: 0.625rem 1.5rem;
                font-size: 0.85rem;
            }

            .hero-section {
                padding: 100px 0;
            }

            .blood-bank-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <i class="bi bi-droplet-fill me-2"></i>
                <span class="fw-bold">Donor & Patient Portal</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="redcross-details.php">Red Cross</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="negrosfirst-details.php">Negros First</a>
                    </li>
                    
                   
                </ul>
                <div class="d-flex">
                    <a href="login.php" class="btn btn-outline-danger me-2">Login</a>
                    <a href="register.php" class="btn btn-danger">Register</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center hero-content">
            <h1>Donate Blood, Save Lives</h1>
            <p class="mt-3 mb-4">Your donation can make a difference in someone's life. Join our mission to ensure blood supply for those in need.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="register.php?role=donor" class="btn btn-danger btn-lg">Become a Donor</a>
                <a href="login.php?role=patient" class="btn btn-outline-light btn-lg">Request Blood</a>
            </div>
        </div>
    </section>

    <!-- Blood Banks Section -->
  <section class="py-5" style="background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);">
    <div class="container">
      <div class="text-center mb-5">
        <h2 class="fw-bold">Blood Banks</h2>
        <p class="text-muted" style="font-size: 1.1rem; margin-top: 1rem;">Check blood availability and services offered by our partner blood banks</p>
      </div>

      <div class="row g-4 justify-content-center">
        <!-- Red Cross Card -->
        <div class="col-md-6 d-flex justify-content-center">
          <div class="blood-bank-card redcross-card">
            <div class="blood-bank-header">
              <img src="assets/img/rclogo.jpg" alt="Red Cross Logo" class="img-fluid mb-2 rounded-circle bg-white p-1">
              <h3>Philippine Red Cross</h3>
            </div>
            <div>
              <!-- Campaign Quotes -->
              <div class="mb-3 p-2" style="background: linear-gradient(135deg, rgba(220, 38, 38, 0.1) 0%, rgba(185, 28, 28, 0.05) 100%); border-left: 3px solid #DC2626; border-radius: 6px;">
                <div class="text-center">
                  <h6 class="mb-1" style="color: #DC2626; font-weight: 700; font-size: 0.95rem;">"Be a Hero. Donate Blood."</h6>
                  <p class="mb-0" style="color: #991B1B; font-style: italic; font-size: 0.85rem;">BE PART OF SOMETHING BIGGER THAN YOURSELF.</p>
                </div>
              </div>
              
              <p><i class="bi bi-geo-alt text-danger me-1"></i>10th St, Bacolod, 6100 Negros Occidental</p>
              <p><i class="bi bi-telephone text-danger me-1"></i>(034) 434 8541</p>
              
              <!-- Blood Inventory Display -->
              <div class="blood-inventory-section">
                <h6 class="mb-3"><i class="bi bi-droplet-fill me-1"></i>Available Blood Types</h6>
                <div class="row g-2">
                  <?php if (!empty($redcrossInventory)): ?>
                    <?php foreach ($redcrossInventory as $blood): ?>
                      <div class="col-6">
                        <div class="d-flex justify-content-between align-items-center">
                          <span class="badge bg-danger blood-type-badge"><?php echo $blood['blood_type']; ?></span>
                          <span class="inventory-count"><?php echo $blood['total_units']; ?> units</span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="col-12">
                      <p class="text-muted mb-0">No inventory data available</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              
              <a href="redcross-details.php" class="btn btn-outline-danger mt-3 w-100">View Details</a>
            </div>
          </div>
        </div>

        <!-- Negros First Card -->
        <div class="col-md-6 d-flex justify-content-center">
          <div class="blood-bank-card negrosfirst-card">
            <div class="blood-bank-header">
              <img src="assets/img/nflogo.png" alt="NF Logo" class="img-fluid mb-2 rounded-circle bg-white p-1">
              <h3>Negros First Provincial Blood Center</h3>
            </div>
            <div>
              <p><i class="bi bi-geo-alt text-danger me-1"></i>Abad Santos Street, Bacolod, 6100 Negros Occidental</p>
              <p><i class="bi bi-telephone text-danger me-1"></i>(034) 433 0313</p>
              
              <!-- Blood Inventory Display -->
              <div class="blood-inventory-section">
                <h6 class="mb-3"><i class="bi bi-droplet-fill me-1"></i>Available Blood Types</h6>
                <div class="row g-2">
                  <?php if (!empty($negrosfirstInventory)): ?>
                    <?php foreach ($negrosfirstInventory as $blood): ?>
                      <div class="col-6">
                        <div class="d-flex justify-content-between align-items-center">
                          <span class="badge blood-type-badge" style="background-color: var(--negrosfirst-color);"><?php echo $blood['blood_type']; ?></span>
                          <span class="inventory-count"><?php echo $blood['total_units']; ?> units</span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="col-12">
                      <p class="text-muted mb-0">No inventory data available</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              
              <a href="negrosfirst-details.php" class="btn btn-outline-danger mt-3 w-100">View Details</a>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>


    <!-- Upcoming Blood Drives Section -->
    <section class="py-5" style="background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Latest Announcements</h2>
                <p class="text-muted" style="font-size: 1.1rem; margin-top: 1rem;">Stay updated with the latest news and information from our blood banks</p>
            </div>

            <div class="row g-4">
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="col-md-4">
                            <div class="card blood-drive-card h-100 border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <div class="d-flex mb-3">
                                        <div class="blood-drive-date me-3">
                                            <span class="day"><?php echo date('d', strtotime($announcement['created_at'])); ?></span>
                                            <span class="month"><?php echo date('M', strtotime($announcement['created_at'])); ?></span>
                                        </div>
                                        <div>
                                            <h5 class="card-title"><?php echo $announcement['title']; ?></h5>
                                            <p class="card-text text-muted mb-0">
                                                <i class="bi bi-building me-1"></i> <?php echo $announcement['organization_name']; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <p class="card-text">
                                            <?php echo substr($announcement['content'], 0, 150) . '...'; ?>
                                        </p>
                                    </div>
                                    <div class="d-grid">
                                        <a href="announcement-details.php?id=<?php echo $announcement['id']; ?>" class="btn btn-outline-danger">Read More</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-megaphone display-1 text-muted"></i>
                        <p class="mt-3">No announcements available at the moment.</p>
                        <p>Please check back later for updates.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center mt-4">
                <a href="announcements.php" class="btn btn-danger">View All Announcements</a>
            </div>
        </div>
    </section>



    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container text-center">
            <h2 class="mb-4 fw-bold">Ready to Make a Difference?</h2>
            <p class="mb-4" style="font-size: 1.2rem; opacity: 0.95;">Join our community of blood donors and help save lives. Register today!</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="register.php?role=donor" class="btn btn-light btn-lg px-5 fw-semibold" style="color: var(--primary-color);">Register as Donor</a>
                <a href="login.php?role=patient" class="btn btn-outline-light btn-lg px-5 fw-semibold">Request Blood</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="mb-4">
                        <a class="d-flex align-items-center text-white text-decoration-none" href="index.php">
                            <i class="bi bi-droplet-fill me-2"></i>
                            <span class="fw-bold fs-4">Blood Bank Portal</span>
                        </a>
                    </div>
                    <p class="text-muted">Connecting blood donors with those in need. Our mission is to ensure a safe and adequate blood supply for the community.</p>
                    <div class="social-icons mt-3">
                        <a href="#"><i class="bi bi-facebook"></i></a>
                        <a href="#"><i class="bi bi-twitter"></i></a>
                        <a href="#"><i class="bi bi-instagram"></i></a>
                        <a href="#"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="footer-links">
                        <h5>Quick Links</h5>
                        <ul>
                            <li><a href="index.php">Home</a></li>
                            <li><a href="about.php">About Us</a></li>
                            <li><a href="blood-drives.php">Blood Drives</a></li>
                            <li><a href="contact.php">Contact Us</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="footer-links">
                        <h5>Blood Banks</h5>
                        <ul>
                            <li><a href="redcrossportal.php">Red Cross Blood Bank</a></li>
                            <li><a href="negrosfirstportal.php">Negros First Blood Center</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="footer-links">
                        <h5>Contact Information</h5>
                        <ul>
                            <li><i class="bi bi-geo-alt me-2"></i> Bacolod City, Negros Occidental</li>
                            <li><i class="bi bi-telephone me-2"></i> (034) 123-4567</li>
                            <li><i class="bi bi-envelope me-2"></i> info@bloodbankportal.com</li>
                        </ul>
                    </div>
                </div>
            </div>
            <hr class="mt-4 mb-3 border-secondary">
            <div class="text-center text-muted">
                <p>&copy; <?php echo date('Y'); ?> Blood Bank Portal. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Enhanced JavaScript -->
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scroll for anchor links
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

        // Intersection Observer for fade-in animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all cards and sections
        document.querySelectorAll('.blood-bank-card, .blood-drive-card, section').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Parallax effect for hero section
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero-section');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.5}px)`;
            }

            // Show/hide scroll to top button
            const scrollTop = document.querySelector('.scroll-to-top');
            if (scrollTop) {
                if (window.pageYOffset > 300) {
                    scrollTop.classList.add('visible');
                } else {
                    scrollTop.classList.remove('visible');
                }
            }
        });

        // Scroll to top functionality
        const scrollTopBtn = document.createElement('div');
        scrollTopBtn.className = 'scroll-to-top';
        scrollTopBtn.innerHTML = '<i class="bi bi-arrow-up"></i>';
        scrollTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        document.body.appendChild(scrollTopBtn);
    </script>
    
    <style>
        /* Ripple effect */
        .btn {
            position: relative;
            overflow: hidden;
        }
        
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s ease-out;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Additional animations */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Stagger animation for cards */
        .blood-bank-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .blood-bank-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .blood-drive-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .blood-drive-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .blood-drive-card:nth-child(3) {
            animation-delay: 0.3s;
        }
    </style>
</body>
</html>