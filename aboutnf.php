<?php
// Include database connection
require_once 'config/db.php';

// Page title
$pageTitle = "About Us - Negros First Provincial Blood Center";
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
        padding: 120px 0 80px;
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

    .card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        overflow: hidden;
        height: 100%;
    }
    
    .card:hover {
        box-shadow: var(--box-shadow-lg);
        transform: translateY(-4px);
    }
    
    .card-title {
        color: var(--nf-primary);
        font-weight: 700;
    }
    
    .card-body {
        padding: 2rem;
    }

    .icon-box {
        width: 80px;
        height: 80px;
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

    .card:hover .icon-box {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 6px 20px rgba(179, 18, 23, 0.4);
    }

    .mission-vision-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 249, 250, 0.98) 100%);
        border-left: 5px solid;
        border-image: var(--gradient-primary) 1;
    }

    .mission-vision-card.mission {
        border-image: var(--gradient-primary) 1;
    }

    .mission-vision-card.vision {
        border-image: var(--gradient-secondary) 1;
    }

    .values-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
    }

    .value-item {
        text-align: center;
        padding: 2rem;
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .value-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-accent);
    }

    .value-item:hover {
        transform: translateY(-6px);
        box-shadow: var(--box-shadow-lg);
    }

    .value-icon {
        width: 70px;
        height: 70px;
        background: var(--gradient-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        color: white;
        margin: 0 auto 1.5rem;
        box-shadow: 0 4px 15px rgba(179, 18, 23, 0.3);
        transition: var(--transition);
    }

    .value-item:hover .value-icon {
        transform: scale(1.15);
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

    .footer-links ul li a:hover {
        color: #fff;
        padding-left: 5px;
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

    .copyright {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
        margin: 0;
    }

    @media (max-width: 767.98px) {
        .hero-title {
            font-size: 2.5rem;
        }
        section h2 {
            font-size: 2rem;
        }
    }
</style>

<body>
    <!-- Navbar 2 Start -->
    <section id="Nav2">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/blood/negrosfirstportal.php">
                    <img src="/blood/imgs/nflogo.png" alt="Negros First Logo" style="max-width: 120px;">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item"><a class="nav-link" href="/blood/negrosfirstportal.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link selected" href="/blood/aboutnf.php">About Us</a></li>
                        <li class="nav-item"><a class="nav-link" href="/blood/contact.php">Contact Us</a></li>
                    </ul>
                    <div class="d-flex ms-3">
                        <a class="btn btn-outline-light" href="/blood/loginnegrosfirst.php">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
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
                <div class="col-lg-12 text-center">
                    <div class="hero-content" data-aos="fade-up" data-aos-duration="1000">
                        <h1 class="hero-title">About Negros First</h1>
                        <p class="lead text-white" style="font-size: 1.25rem; opacity: 0.95;">Dedicated to serving the blood needs of Negros Occidental with excellence, compassion, and commitment to saving lives.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="py-5" style="padding-top: 100px !important;">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-12 text-center" data-aos="fade-up" data-aos-duration="800">
                    <h2>Who We Are</h2>
                    <p class="lead text-muted" style="max-width: 800px; margin: 0 auto;">Negros First Provincial Blood Center is a premier blood banking facility committed to providing safe, quality blood products and services to the people of Negros Occidental. We operate under the highest standards of medical excellence and are dedicated to saving lives through voluntary blood donation.</p>
                </div>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-lg-6" data-aos="fade-right" data-aos-duration="800">
                    <div class="card mission-vision-card mission h-100">
                        <div class="card-body p-4">
                            <div class="icon-box">
                                <i class="bi bi-bullseye"></i>
                            </div>
                            <h3 class="card-title mb-3">Our Mission</h3>
                            <p class="card-text">To ensure a safe, adequate, and sustainable blood supply for the people of Negros Occidental through voluntary blood donation, while maintaining the highest standards of quality, safety, and service excellence in all our operations.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left" data-aos-duration="800">
                    <div class="card mission-vision-card vision h-100">
                        <div class="card-body p-4">
                            <div class="icon-box" style="background: var(--gradient-secondary);">
                                <i class="bi bi-eye"></i>
                            </div>
                            <h3 class="card-title mb-3">Our Vision</h3>
                            <p class="card-text">To be the leading blood center in Negros Occidental, recognized for excellence in blood banking services, community engagement, and commitment to saving lives. We envision a community where every patient in need has access to safe blood products.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Values Section -->
    <section class="py-5 bg-light" style="padding: 80px 0 !important; background: linear-gradient(180deg, var(--gray-50) 0%, var(--white) 100%) !important;">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="800">
                <h2>Our Core Values</h2>
                <p class="lead text-muted">The principles that guide everything we do</p>
            </div>

            <div class="values-grid">
                <div class="value-item" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                    <div class="value-icon">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Compassion</h4>
                    <p>We serve with empathy and care, understanding that every donation and every patient matters.</p>
                </div>

                <div class="value-item" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                    <div class="value-icon">
                        <i class="bi bi-shield-check-fill"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Safety</h4>
                    <p>We maintain the highest standards of safety and quality in all our blood banking processes.</p>
                </div>

                <div class="value-item" data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
                    <div class="value-icon">
                        <i class="bi bi-award-fill"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Excellence</h4>
                    <p>We strive for excellence in every aspect of our service delivery and operations.</p>
                </div>

                <div class="value-item" data-aos="fade-up" data-aos-duration="800" data-aos-delay="400">
                    <div class="value-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Community</h4>
                    <p>We are committed to building strong relationships with our community and stakeholders.</p>
                </div>

                <div class="value-item" data-aos="fade-up" data-aos-duration="800" data-aos-delay="500">
                    <div class="value-icon">
                        <i class="bi bi-lightbulb-fill"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Innovation</h4>
                    <p>We embrace new technologies and methods to improve our services and operations.</p>
                </div>

                <div class="value-item" data-aos="fade-up" data-aos-duration="800" data-aos-delay="600">
                    <div class="value-icon">
                        <i class="bi bi-handshake-fill"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Integrity</h4>
                    <p>We conduct our operations with honesty, transparency, and ethical practices.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Overview Section -->
    <section class="py-5" style="padding: 80px 0 !important;">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="800">
                <h2>What We Do</h2>
                <p class="lead text-muted">Comprehensive blood banking services for our community</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="icon-box mx-auto">
                                <i class="bi bi-droplet-half"></i>
                            </div>
                            <h4 class="card-title">Blood Collection</h4>
                            <p class="card-text">We collect whole blood donations from voluntary donors, ensuring each donation can save up to three lives.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="icon-box mx-auto" style="background: var(--gradient-secondary);">
                                <i class="bi bi-clipboard2-pulse"></i>
                            </div>
                            <h4 class="card-title">Blood Testing</h4>
                            <p class="card-text">All donated blood undergoes rigorous testing for infectious diseases to ensure safety for recipients.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="icon-box mx-auto" style="background: linear-gradient(135deg, var(--nf-navy) 0%, var(--nf-navy-light) 100%);">
                                <i class="bi bi-truck"></i>
                            </div>
                            <h4 class="card-title">Blood Distribution</h4>
                            <p class="card-text">We distribute blood products to hospitals and healthcare facilities as needed for patient care.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
                        <ul style="margin-top: 1.5rem; padding: 0; list-style: none;">
                            <li style="margin-bottom: 1rem; color: rgba(255, 255, 255, 0.9);">
                                <i class="bi bi-geo-alt-fill me-2"></i>Abad Santos Street, Bacolod, 6100 Negros Occidental, Philippines
                            </li>
                            <li style="margin-bottom: 1rem; color: rgba(255, 255, 255, 0.9);">
                                <i class="bi bi-telephone-fill me-2"></i>+63 34 433 0313
                            </li>
                            <li style="margin-bottom: 1rem; color: rgba(255, 255, 255, 0.9);">
                                <i class="bi bi-envelope-fill me-2"></i>info@negrosfirst.gov.ph
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <hr style="margin: 2rem 0; border-color: rgba(255, 255, 255, 0.1);">
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
    <script type="text/javascript" src="js/main.js"></script>
    
    <script>
        // Initialize AOS (Animate On Scroll)
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });

        // Enhanced navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
        });
    </script>
</body>
</html>
