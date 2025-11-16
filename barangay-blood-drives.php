<?php
// Include database connection
require_once 'config/db.php';

// Get upcoming blood drives
$upcomingDrives = executeQuery("
    SELECT bd.*, b.name as barangay_name,
           CASE WHEN bd.organization_type = 'redcross' THEN 'Red Cross' ELSE 'Negros First' END as organization_name
    FROM blood_drives bd
    JOIN barangay_users b ON bd.barangay_id = b.id
    WHERE bd.date >= CURDATE() AND bd.status = 'Scheduled'
    ORDER BY bd.date ASC
");

// Get past blood drives
$pastDrives = executeQuery("
    SELECT bd.*, b.name as barangay_name,
           CASE WHEN bd.organization_type = 'redcross' THEN 'Red Cross' ELSE 'Negros First' END as organization_name
    FROM blood_drives bd
    JOIN barangay_users b ON bd.barangay_id = b.id
    WHERE bd.date < CURDATE()
    ORDER BY bd.date DESC
    LIMIT 10
");

// Page title
$pageTitle = "Blood Drives - Barangay Portal";
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
    <!-- Fallback for offline use -->
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/bootstrap-icons-offline.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
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
        color: var(--secondary-color);
        background: var(--light-bg);
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

    .drives-section {
        padding: 100px 0;
        background: var(--light-bg);
        position: relative;
    }
    
    .drives-section::before {
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
        color: #6b7280;
        max-width: 700px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .drive-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-xl);
        box-shadow: var(--shadow-md);
        transition: var(--transition);
        border: 1px solid rgba(229, 231, 235, 0.8);
        padding: 2.5rem;
        height: 100%;
        position: relative;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    
    .drive-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
        border-radius: var(--border-radius-xl) var(--border-radius-xl) 0 0;
    }
    
    .drive-card::after {
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
    
    .drive-card:hover {
        transform: translateY(-12px);
        box-shadow: var(--shadow-xl);
    }
    
    .drive-card:hover::after {
        width: 300px;
        height: 300px;
    }
    
    .drive-header {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
        position: relative;
        z-index: 2;
    }
    
    .drive-icon {
        width: 70px;
        height: 70px;
        background: var(--gradient-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.75rem;
        margin-right: 1.5rem;
        box-shadow: var(--shadow-lg);
        transition: var(--transition);
    }
    
    .drive-card:hover .drive-icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: var(--shadow-xl);
    }
    
    .drive-info h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--secondary-color);
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }
    
    .drive-info p {
        color: #6b7280;
        margin: 0;
        font-size: 1rem;
    }
    
    .drive-details {
        margin-bottom: 2rem;
        position: relative;
        z-index: 2;
    }
    
    .drive-details .row {
        margin-bottom: 1rem;
    }
    
    .drive-details .col-md-6 {
        display: flex;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .drive-details i {
        color: var(--primary-color);
        margin-right: 0.75rem;
        width: 20px;
        font-size: 1.1rem;
    }
    
    .drive-details span {
        color: var(--secondary-light);
        font-weight: 500;
    }
    
    .drive-status {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 1.5rem;
        position: relative;
        z-index: 2;
    }
    
    .status-upcoming {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    
    .status-completed {
        background: rgba(107, 114, 128, 0.1);
        color: var(--secondary-light);
        border: 1px solid rgba(107, 114, 128, 0.2);
    }
    
    .drive-actions {
        position: relative;
        z-index: 2;
    }
    
    .btn {
        border-radius: var(--border-radius-lg);
        font-weight: 600;
        padding: 0.75rem 1.5rem;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }
    
    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: var(--transition);
    }
    
    .btn:hover::before {
        left: 100%;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-color: #3b82f6;
        color: #ffffff;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%);
        border-color: #eab308;
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        color: #1e293b;
    }
    
    .btn-outline-primary {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        background: rgba(59, 130, 246, 0.05);
    }
    
    .btn-outline-primary:hover {
        background: var(--gradient-primary);
        border-color: var(--primary-color);
        color: #ffffff;
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-outline-danger {
        border: 2px solid #3b82f6;
        color: #3b82f6;
        background: rgba(59, 130, 246, 0.1);
    }
    
    .btn-outline-danger:hover {
        background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%);
        border-color: #eab308;
        color: #1a2332;
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-color: #3b82f6;
        color: #ffffff;
    }
    
    .btn-danger:hover {
        background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%);
        border-color: #eab308;
        color: #1e293b;
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--card-bg);
        border-radius: var(--border-radius-xl);
        box-shadow: var(--shadow-md);
        border: 1px solid rgba(229, 231, 235, 0.8);
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #d1d5db;
        margin-bottom: 1.5rem;
    }
    
    .empty-state h4 {
        color: var(--secondary-color);
        margin-bottom: 1rem;
        font-weight: 600;
    }
    
    .empty-state p {
        color: #6b7280;
        margin-bottom: 0;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .hero-content h1 {
            font-size: 2.5rem;
        }
        
        .hero-content p {
            font-size: 1rem;
        }
        
        .section-title h2 {
            font-size: 2rem;
        }
        
        .drive-card {
            padding: 2rem 1.5rem;
        }
        
        .drive-header {
            flex-direction: column;
            text-align: center;
        }
        
        .drive-icon {
            margin-right: 0;
            margin-bottom: 1rem;
        }
    }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm sticky-top" style="z-index: 1030;">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="barangay-portal.php">
                <i class="bi bi-droplet-fill me-2"></i>
                <span class="fw-bold">Barangay Portal</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="barangay-portal.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="barangay-blood-drives.php">Blood Drives</a>
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
            <h1 data-aos="fade-up" data-aos-duration="1000">Blood Drives</h1>
            <p data-aos="fade-up" data-aos-duration="1000" data-aos-delay="200">Discover upcoming blood donation drives in your community and join us in saving lives through organized blood collection events.</p>
        </div>
    </section>

    <!-- Upcoming Drives Section -->
    <section class="drives-section">
        <div class="container">
            <div class="section-title" data-aos="fade-up" data-aos-duration="800">
                <h2>Upcoming Blood Drives</h2>
                <p>Join our upcoming blood donation drives and make a difference in your community</p>
            </div>
            
            <div class="row">
                <?php if (!empty($upcomingDrives)): ?>
                    <?php foreach ($upcomingDrives as $index => $drive): ?>
                        <div class="col-lg-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="<?php echo $index * 100; ?>">
                            <div class="drive-card">
                                <div class="drive-header">
                                    <div class="drive-icon">
                                        <i class="bi bi-heart-pulse"></i>
                                    </div>
                                    <div class="drive-info">
                                        <h3><?php echo htmlspecialchars($drive['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($drive['organization_name']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="drive-status status-upcoming">
                                    <i class="bi bi-calendar-check me-2"></i>
                                    Upcoming Drive
                                </div>
                                
                                <div class="drive-details">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <i class="bi bi-geo-alt"></i>
                                            <span><?php echo htmlspecialchars($drive['location']); ?></span>
                                        </div>
                                        <div class="col-md-6">
                                            <i class="bi bi-calendar-event"></i>
                                            <span><?php echo date('M d, Y', strtotime($drive['date'])); ?></span>
                                        </div>
                                        <div class="col-md-6">
                                            <i class="bi bi-clock"></i>
                                            <span><?php echo date('g:i A', strtotime($drive['start_time'])); ?> - <?php echo date('g:i A', strtotime($drive['end_time'])); ?></span>
                                        </div>
                                        <div class="col-md-6">
                                            <i class="bi bi-people"></i>
                                            <span><?php echo htmlspecialchars($drive['barangay_name']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="drive-actions">
                                    <a href="barangay-login.php" class="btn btn-primary">
                                        <i class="bi bi-person-plus me-2"></i>Register to Participate
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"></i>
                            <h4>No Upcoming Drives</h4>
                            <p>There are currently no scheduled blood drives. Check back later for updates or contact your local blood bank for more information.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Past Drives Section -->
    <section class="drives-section" style="background: #fff;">
        <div class="container">
            <div class="section-title" data-aos="fade-up" data-aos-duration="800">
                <h2>Recent Blood Drives</h2>
                <p>View our recent blood donation drives and their impact on the community</p>
            </div>
            
            <div class="row">
                <?php if (!empty($pastDrives)): ?>
                    <?php foreach ($pastDrives as $index => $drive): ?>
                        <div class="col-lg-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="<?php echo $index * 100; ?>">
                            <div class="drive-card">
                                <div class="drive-header">
                                    <div class="drive-icon">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div class="drive-info">
                                        <h3><?php echo htmlspecialchars($drive['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($drive['organization_name']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="drive-status status-completed">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Completed
                                </div>
                                
                                <div class="drive-details">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <i class="bi bi-geo-alt"></i>
                                            <span><?php echo htmlspecialchars($drive['location']); ?></span>
                                        </div>
                                        <div class="col-md-6">
                                            <i class="bi bi-calendar-event"></i>
                                            <span><?php echo date('M d, Y', strtotime($drive['date'])); ?></span>
                                        </div>
                                        <div class="col-md-6">
                                            <i class="bi bi-people"></i>
                                            <span><?php echo htmlspecialchars($drive['barangay_name']); ?></span>
                                        </div>
                                        <div class="col-md-6">
                                            <i class="bi bi-heart-pulse"></i>
                                            <span>Lives Saved</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="drive-actions">
                                    <a href="#" class="btn btn-outline-primary">
                                        <i class="bi bi-info-circle me-2"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="bi bi-clock-history"></i>
                            <h4>No Recent Drives</h4>
                            <p>No recent blood drives to display. Check back later for updates.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer mt-5" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%); color: #fff; position: relative; border-top: 4px solid rgba(234,179,8,0.3); box-shadow: 0 -4px 20px rgba(59,130,246,0.15);">
        <div class="container py-5">
            <div class="row g-4 align-items-start">
                <div class="col-md-4 mb-4 mb-md-0">
                    <a class="d-flex align-items-center text-white text-decoration-none mb-3" href="barangay-portal.php">
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
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });
    </script>
    <script>
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

        // Observe all cards
        document.querySelectorAll('.drive-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.boxShadow = '0 4px 20px rgba(59, 130, 246, 0.4), 0 2px 10px rgba(234, 179, 8, 0.3)';
            } else {
                navbar.style.boxShadow = '0 4px 16px rgba(59, 130, 246, 0.3), 0 2px 8px rgba(234, 179, 8, 0.2)';
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