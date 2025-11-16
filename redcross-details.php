<?php
// Include database connection
require_once 'config/db.php';



// Get Red Cross information
$redcross = getRow("SELECT * FROM redcross_users WHERE id = 1"); // Assuming ID 1 is the main Red Cross account

// Get blood inventory
$bloodInventory = executeQuery("
    SELECT blood_type, 
           SUM(units) as total_units
    FROM blood_inventory
    WHERE organization_type = 'redcross' 
    AND status = 'Available'
    GROUP BY blood_type
    ORDER BY blood_type
");

// Initialize empty array if query fails
if ($bloodInventory === false) {
    $bloodInventory = [];
}

// Get upcoming blood drives (exclude completed ones based on date)
$upcomingDrives = executeQuery("
    SELECT bd.*, b.name as barangay_name
    FROM blood_drives bd
    JOIN barangay_users b ON bd.barangay_id = b.id
    WHERE bd.organization_type = 'redcross' 
    AND bd.date >= CURDATE() 
    AND bd.status = 'Scheduled'
    AND (bd.date > CURDATE() OR (bd.date = CURDATE() AND bd.end_time > CURTIME()))
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
    WHERE organization_type = 'redcross'
");

// Page title
$pageTitle = "Red Cross Blood Bank - Blood Bank Portal";



// Get blood donations
$status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$whereClause = "WHERE bd.organization_type = 'redcross'";

if ($status !== 'all') {
    $whereClause .= " AND bd.status = '{$status}'";
}

// Fetch donations from redcross with donor info
$bloodDonations = executeQuery("
    SELECT d.*, du.name AS donor_name, du.phone, du.blood_type AS donor_blood_type, du.city
    FROM donations d
    JOIN donor_users du ON d.donor_id = du.id
    WHERE d.organization_type = 'redcross'
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
$pendingCount = getCount("SELECT COUNT(*) FROM donations WHERE organization_type = 'redcross' AND status = 'Pending'");
$approvedCount = getCount("SELECT COUNT(*) FROM donations WHERE organization_type = 'redcross' AND status = 'Approved'");
$completedCount = getCount("SELECT COUNT(*) FROM donations WHERE organization_type = 'redcross' AND status = 'Completed'");
$rejectedCount = getCount("SELECT COUNT(*) FROM donations WHERE organization_type = 'redcross' AND status = 'Rejected'");

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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

    <title>Blood Bank</title>
</head>
    <style>
        :root {
            --primary-color: #ED1C24;
            --primary-dark: #C62828;
            --primary-light: #FF5252;
            --primary-gradient: linear-gradient(135deg, #ED1C24 0%, #C62828 50%, #B71C1C 100%);
            --secondary-color: #6c757d;
            --light-bg: #FFF5F5;
            --dark-bg: #1a1a1a;
            --border-radius: 15px;
            --box-shadow: 0 5px 15px rgba(237, 28, 36, 0.15);
            --box-shadow-lg: 0 10px 30px rgba(237, 28, 36, 0.25);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            overflow-x: hidden;
            padding-top: 80px;
            background: linear-gradient(135deg, #FFF5F5 0%, #FFEBEE 50%, #FFF5F5 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            min-height: 100vh;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

       /* Navbar Styles */
			#Nav2 {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				z-index: 1000;
				background: #ffffff;
				box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1), 0 2px 10px rgba(0, 0, 0, 0.05);
				font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
				border-bottom: 2px solid rgba(237, 28, 36, 0.2);
			}

			#Nav2 .navbar {
				padding: 1rem 0;
			}

			#Nav2 {
				color: #ED1C24 !important;
			}

			#Nav2 .navbar-brand,
			#Nav2 .navbar-brand span,
			#Nav2 .navbar-brand div,
			#Nav2 .navbar-brand p,
			#Nav2 .navbar-brand h1,
			#Nav2 .navbar-brand h2,
			#Nav2 .navbar-brand h3,
			#Nav2 .navbar-brand h4,
			#Nav2 .navbar-brand h5,
			#Nav2 .navbar-brand h6 {
				color: #ED1C24 !important;
			}

			#Nav2 .navbar-toggler {
				border-color: #ED1C24;
			}

			#Nav2 .navbar-toggler-icon {
				background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(237, 28, 36, 1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
			}

			#Nav2 .navbar-brand img {
				transition: all 0.3s ease;
			}

			#Nav2 .navbar-brand:hover img {
				transform: scale(1.05);
			}

			#Nav2 .nav-link {
				color: #ED1C24 !important;
				font-weight: 500;
				font-size: 15px;
				padding: 0.5rem 1rem;
				transition: all 0.3s ease;
				letter-spacing: 0.5px;
				position: relative;
			}
			
			#Nav2 .nav-link::after {
				content: '';
				position: absolute;
				bottom: 0;
				left: 50%;
				width: 0;
				height: 2px;
				background: #ED1C24;
				transition: all 0.3s ease;
				transform: translateX(-50%);
			}

			#Nav2 .nav-link:hover,
			#Nav2 .nav-link.selected {
				color: #ED1C24 !important;
				background: rgba(237, 28, 36, 0.1);
				border-radius: 8px;
			}
			
			#Nav2 .nav-link:hover::after,
			#Nav2 .nav-link.selected::after {
				width: 80%;
			}

			#Nav2 .btn {
				padding: 0.5rem 1.5rem;
				border-radius: 25px;
				font-weight: 500;
				font-size: 14px;
				transition: all 0.3s ease;
				letter-spacing: 0.5px;
			}

			#Nav2 .btn.signup {
				background-color: rgba(255, 255, 255, 0.95);
				color: #ED1C24;
				border: 2px solid rgba(255, 255, 255, 0.5);
				font-weight: 600;
			}

			#Nav2 .btn.signup:hover {
				background-color: #ffffff;
				color: #C62828;
				border-color: #ffffff;
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(255, 255, 255, 0.4);
			}

			#Nav2 .btn.login {
				background-color: #ED1C24;
				color: #ffffff;
				border: 2px solid #ED1C24;
			}

			#Nav2 .btn.login:hover {
				background-color: #C62828;
				color: #ffffff;
				border-color: #C62828;
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(237, 28, 36, 0.3);
			}

			#Nav2 .btn-outline-light {
				border-color: #ED1C24;
				color: #ED1C24;
				transition: all 0.3s ease;
				text-decoration: none;
			}

			#Nav2 .btn-outline-light:hover {
				background: linear-gradient(135deg, #ED1C24 0%, #C62828 50%, #B71C1C 100%);
				border-color: #ED1C24;
				color: white;
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(237, 28, 36, 0.3);
			}

			@media (max-width: 991.98px) {
				#Nav2 .navbar-collapse {
					background-color: #ffffff;
					padding: 1rem;
					border-radius: 8px;
					box-shadow: 0 4px 12px rgba(0,0,0,0.15);
					margin-top: 1rem;
					border: 1px solid rgba(237, 28, 36, 0.2);
				}
				
				#Nav2 .nav-link {
					color: #ED1C24 !important;
				}
				
				#Nav2 .nav-link:hover,
				#Nav2 .nav-link.selected {
					color: #C62828 !important;
					background: rgba(237, 28, 36, 0.1);
				}

				#Nav2 .nav-link {
					padding: 0.75rem 1rem;
					font-size: 14px;
				}

				#Nav2 .d-flex {
					margin-top: 1rem;
				}

				#Nav2 .btn {
					font-size: 13px;
					padding: 0.4rem 1.2rem;
				}
			}


        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, rgba(237, 28, 36, 0.85) 0%, rgba(198, 40, 40, 0.85) 50%, rgba(183, 28, 28, 0.85) 100%), url('assets/img/bg3.png');
            background-size: cover;
            background-position: center;
            background-blend-mode: overlay;
            color: white;
            padding: 120px 0;
            position: relative;
            min-height: 60vh;
            display: flex;
            align-items: center;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .hero-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .hero-content h1 {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .hero-content p {
            font-size: clamp(1rem, 2vw, 1.2rem);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        /* Blood Type Container */
        .blood-type-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .blood-type-item {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 245, 245, 0.95) 100%);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid rgba(237, 28, 36, 0.1);
        }

        .blood-type-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 10px 30px rgba(237, 28, 36, 0.3), 0 5px 15px rgba(0, 0, 0, 0.15);
            border-color: rgba(237, 28, 36, 0.3);
        }

        .blood-type-badge {
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: bold;
            font-size: 1.5rem;
            color: white;
            background: var(--primary-gradient);
            margin: 0 auto 15px;
            box-shadow: 0 4px 20px rgba(237, 28, 36, 0.4), 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
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
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: rotate(45deg);
            transition: all 0.5s;
        }
        
        .blood-type-item:hover .blood-type-badge::before {
            animation: shimmer 1.5s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .blood-type-item:hover .blood-type-badge {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 25px rgba(237, 28, 36, 0.5), 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        /* Blood Drive Cards */
        .blood-drive-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 245, 245, 0.95) 100%);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            box-shadow: var(--box-shadow);
            border: 2px solid rgba(237, 28, 36, 0.1);
        }

        .blood-drive-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 40px rgba(237, 28, 36, 0.3), 0 8px 20px rgba(0, 0, 0, 0.15);
            border-color: rgba(237, 28, 36, 0.3);
        }

        .blood-drive-date {
            width: 70px;
            height: 70px;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(237, 28, 36, 0.4), 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .blood-drive-card:hover .blood-drive-date {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 6px 25px rgba(237, 28, 36, 0.5), 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .blood-drive-date .day {
            font-size: 1.8rem;
            font-weight: bold;
            line-height: 1;
        }

        .blood-drive-date .month {
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        /* Statistics Section */
        .stat-item {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 245, 245, 0.95) 100%);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            border: 2px solid rgba(237, 28, 36, 0.1);
        }

        .stat-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 40px rgba(237, 28, 36, 0.3), 0 8px 20px rgba(0, 0, 0, 0.15);
            border-color: rgba(237, 28, 36, 0.3);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #ffffff;
            margin-bottom: 1rem;
            background: var(--primary-gradient);
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 20px rgba(237, 28, 36, 0.4), 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .stat-item:hover .stat-icon {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 6px 25px rgba(237, 28, 36, 0.5), 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .stat-number {
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-text {
            font-size: 1rem;
            color: var(--secondary-color);
        }

        /* Responsive Adjustments */
        @media (max-width: 991.98px) {
            .hero-section {
                padding: 80px 0;
                min-height: 40vh;
            }

            .blood-type-container {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }

            .stat-item {
                padding: 20px;
            }
        }

        @media (max-width: 767.98px) {
            .hero-section {
                padding: 60px 0;
            }

            .blood-type-badge {
                width: 60px;
                height: 60px;
                font-size: 1.3rem;
            }

            .blood-drive-date {
                width: 60px;
                height: 60px;
            }

            .stat-icon {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }
        }

        @media (max-width: 575.98px) {
            .hero-section {
                padding: 40px 0;
            }

            .blood-type-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                padding: 15px;
            }

            .blood-type-item {
                padding: 15px;
            }

            .blood-type-badge {
                width: 50px;
                height: 50px;
                font-size: 1.1rem;
            }

            .stat-item {
                padding: 15px;
            }
        }
        
        /* Card Hover Effects */
        .card[style*="border: 2px solid"]:hover {
            transform: translateY(-8px) scale(1.02) !important;
            box-shadow: 0 15px 40px rgba(237, 28, 36, 0.3), 0 8px 20px rgba(0, 0, 0, 0.15) !important;
            border-color: rgba(237, 28, 36, 0.4) !important;
        }
        
        /* Section Spacing */
        section {
            margin-bottom: 0 !important;
        }
        
        /* Clean up bg-light sections */
        .bg-light {
            background: linear-gradient(135deg, #FFF5F5 0%, #FFEBEE 50%, #FFF5F5 100%) !important;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1b1b 50%, #1a1a1a 100%);
            color: white;
            padding: 60px 0 20px;
            border-top: 4px solid var(--primary-color);
            box-shadow: 0 -4px 20px rgba(237, 28, 36, 0.2);
        }

        .footer-links h5 {
            color: white;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .footer-links h5::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 40px;
            height: 2px;
            background-color: var(--primary-color);
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
        }

        .footer-links ul li a:hover {
            color: white;
            padding-left: 5px;
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            margin-right: 10px;
            color: white;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            background: var(--primary-gradient);
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 4px 12px rgba(237, 28, 36, 0.4);
        }

        /* Improve mobile responsiveness */
        @media (max-width: 767.98px) {
            .blood-type-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat-item {
                margin-bottom: 1rem;
            }

            .blood-drive-card {
                margin-bottom: 1rem;
            }

            .footer {
                text-align: center;
            }

            .footer-links {
                margin-bottom: 2rem;
            }

            .social-icons {
                justify-content: center;
                margin-bottom: 2rem;
            }
        }

        /* Small screen adjustments */
        @media (max-width: 575.98px) {
            .blood-type-container {
                grid-template-columns: repeat(1, 1fr);
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }
        }
    </style>

<body>



    <!-- Navbar 2 Start -->
    <section id="Nav2">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container">
                <a class="navbar-brand" href="redcrossportal.php">
                    <img src="imgs/rc.png" alt="Red Cross Logo" style="max-width: 120px;">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item">
                            <a class="nav-link selected" href="redcross-details.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="aboutrc-details.php">About Us</a>
                        </li>
                       
                        <li class="nav-item">
                            <a class="nav-link" href="contactrc-details.php">Contact Us</a>
                        </li>
                    </ul>
                    <div class="d-flex ms-3" role="group" aria-label="Quick actions">
                        <a href="index.php" class="btn btn-outline-light" title="Back to Home">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </section>
    <!-- Navbar 2 End -->

    

    <!-- Header Start -->
    <section id="header">
        <div class="container">
            <!-- <h1>We are seeking for a better community health.</h1>
            <h4>Lorem ipsum dolor sit amet consectetur adipisicing elit. Tempora repellat inventore nemo repudiandae
                ipsum quos.</h4>
            <button class="btn more" onclick= "window.location.href = 'aboutrc.html';">More</button> -->
        </div>
    </section>
    <!-- Header End -->

    <!-- Sub Header Start -->
    <section id="sub-header" class="py-4" style="background: linear-gradient(135deg, rgba(237, 28, 36, 0.1) 0%, rgba(198, 40, 40, 0.1) 50%, rgba(183, 28, 28, 0.1) 100%); border-top: 2px solid rgba(237, 28, 36, 0.2); border-bottom: 2px solid rgba(237, 28, 36, 0.2);">
        <div class="container">
            <div class="text-center mb-3">
                <h2 class="mb-2 fw-bold" style="color: #ED1C24; font-size: 1.8rem; letter-spacing: 0.5px;">"Be a Hero. Donate Blood."</h2>
                <h4 class="mb-3 fw-bold" style="color: #ED1C24; font-size: 1.3rem; letter-spacing: 0.5px;">DUGONG BOMBO 2025</h4>
                <p class="mb-0 fw-bold" style="color: #C62828; font-size: 1.1rem; font-style: italic; letter-spacing: 0.3px;">BE PART OF SOMETHING BIGGER THAN YOURSELF.</p>
            </div>
            <h3 class="text-center mb-0 fw-bold" style="color: #ED1C24; font-size: 1.5rem; letter-spacing: 0.5px;">A SINGLE PINT CAN SAVE THREE LIVES, A SINGLE GESTURE CAN CREATE A MILLION SMILES.</h3>
        </div>
    </section>
    <!-- Sub Header End -->
    <!-- Blood Inventory Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="mb-4">
                        <h2 class="fw-bold mb-3" style="color: #ED1C24; font-size: 2.5rem;">Blood Inventory</h2>
                        <p class="text-muted fs-5">Check the availability of different blood types at our Red Cross Blood Bank. Our inventory is updated daily to provide accurate information.</p>
                    </div>

                    <div class="blood-type-container">
                        <?php
                        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        $inventoryMap = [];

                        // Create a map of blood type to units
                        if (is_array($bloodInventory)) {
                            foreach ($bloodInventory as $item) {
                                $inventoryMap[$item['blood_type']] = (int)$item['total_units'];
                            }
                        }

                        foreach ($bloodTypes as $bloodType):
                            $units = isset($inventoryMap[$bloodType]) ? $inventoryMap[$bloodType] : 0;
                            
                            $statusClass = 'danger';
                            $statusText = 'Critical';

                            if ($units > 20) {
                                $statusClass = 'success';
                                $statusText = 'Good';
                            } elseif ($units > 10) {
                                $statusClass = 'warning';
                                $statusText = 'Low';
                            }
                        ?>
                            <div class="blood-type-item">
                                <div class="blood-type-badge"><?php echo $bloodType; ?></div>
                                <h4 class="mb-1"><?php echo $units; ?> units</h4>
                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>


                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-lg" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 245, 245, 0.95) 100%); border: 2px solid rgba(237, 28, 36, 0.2) !important; transition: all 0.3s ease;">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <div style="width: 70px; height: 70px; background: var(--primary-gradient); border-radius: 15px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 4px 20px rgba(237, 28, 36, 0.4);">
                                    <i class="bi bi-telephone-fill fs-2 text-white"></i>
                                </div>
                                <h3 class="card-title fw-bold mb-0" style="color: #ED1C24;">Contact Information</h3>
                            </div>

                            <div class="d-flex mb-3 p-3" style="background: rgba(237, 28, 36, 0.05); border-radius: 10px; border-left: 4px solid #ED1C24;">
                                <div class="me-3" style="width: 50px; height: 50px; background: var(--primary-gradient); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(237, 28, 36, 0.3);">
                                    <i class="bi bi-geo-alt-fill text-white"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1 fw-bold" style="color: #ED1C24;">Address</h5>
                                    <p class="mb-0 text-muted">10th St, Bacolod City, Philippines</p>
                                </div>
                            </div>

                            <div class="d-flex mb-3 p-3" style="background: rgba(237, 28, 36, 0.05); border-radius: 10px; border-left: 4px solid #ED1C24;">
                                <div class="me-3" style="width: 50px; height: 50px; background: var(--primary-gradient); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(237, 28, 36, 0.3);">
                                    <i class="bi bi-telephone-fill text-white"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1 fw-bold" style="color: #ED1C24;">Phone</h5>
                                    <p class="mb-1 text-muted"><strong>Blood Services:</strong> (034- 458-9798) or 09683292625</p>
                                    <p class="mb-0 text-muted"><strong>Admin:</strong> (034-458-4930)</p>
                                </div>
                            </div>

                            <div class="d-flex mb-3 p-3" style="background: rgba(237, 28, 36, 0.05); border-radius: 10px; border-left: 4px solid #ED1C24;">
                                <div class="me-3" style="width: 50px; height: 50px; background: var(--primary-gradient); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(237, 28, 36, 0.3);">
                                    <i class="bi bi-envelope-fill text-white"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1 fw-bold" style="color: #ED1C24;">Email</h5>
                                    <p class="mb-0 text-muted">negros.occidental@redcross.org.ph</p>
                                </div>
                            </div>

                            <div class="d-flex mb-0 p-3" style="background: rgba(237, 28, 36, 0.05); border-radius: 10px; border-left: 4px solid #ED1C24;">
                                <div class="me-3" style="width: 50px; height: 50px; background: var(--primary-gradient); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(237, 28, 36, 0.3);">
                                    <i class="bi bi-clock-fill text-white"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1 fw-bold" style="color: #ED1C24;">Operating Hours</h5>
                                    <p class="mb-0 text-muted">Always open</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Upcoming Blood Drives Section -->
    <section class="py-5" style="background: linear-gradient(135deg, #FFF5F5 0%, #FFEBEE 50%, #FFF5F5 100%);">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3" style="color: #ED1C24; font-size: 2.5rem;">Upcoming Blood Drives</h2>
                <p class="text-muted fs-5">Join us in our upcoming blood donation events</p>
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
                                        <a href="login.php?role=donor" class="btn" style="border: 2px solid #ED1C24; color: #ED1C24; background: rgba(255, 255, 255, 0.9); padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease;">
                                            <i class="bi bi-heart-fill me-2"></i>Register to Donate
                                        </a>
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

            <div class="text-center mt-4">
                <a href="blood-drives.php" class="btn" style="background: var(--primary-gradient); color: white; border: none; padding: 0.75rem 2rem; border-radius: 10px; font-weight: 600; box-shadow: 0 4px 16px rgba(237, 28, 36, 0.35); transition: all 0.3s ease;">
                    <i class="bi bi-calendar-event me-2"></i>View All Blood Drives
                </a>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3" style="color: #ED1C24; font-size: 2.5rem;">Our Impact</h2>
                <p class="text-muted fs-5">Making a difference in our community</p>
            </div>

            <div class="row g-4">
                <div class="col-md-3">
                    <div class="stat-item h-100">
                        <div class="stat-icon">
                            <i class="bi bi-droplet-fill"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($donationStats['total_donations']); ?></div>
                        <div class="stat-text">Total Donations</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item h-100">
                        <div class="stat-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($donationStats['unique_donors']); ?></div>
                        <div class="stat-text">Unique Donors</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item h-100">
                        <div class="stat-icon">
                            <i class="bi bi-heart-pulse-fill"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($donationStats['total_units'] * 3); ?></div>
                        <div class="stat-text">Lives Saved</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item h-100">
                        <div class="stat-icon">
                            <i class="bi bi-hospital-fill"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($donationStats['total_units']); ?></div>
                        <div class="stat-text">units Collected</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

   

    

    <!-- Citizen's Charter Start -->
    <section id="articles" class="py-5" style="background: linear-gradient(135deg, #FFF5F5 0%, #FFEBEE 50%, #FFF5F5 100%);">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3" style="color: #ED1C24; font-size: 2.5rem;">Citizen's Charter</h2>
                <p class="text-muted fs-5">Our commitment to transparent and efficient service delivery</p>
            </div>
            
            <div class="row g-4">
                <!-- Blood Donation Service -->
                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-lg" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 245, 245, 0.95) 100%); border: 2px solid rgba(237, 28, 36, 0.2) !important; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden;">
                        <div class="card-header text-white" style="background: var(--primary-gradient); padding: 1.5rem; border: none;">
                            <div class="d-flex align-items-center">
                                <div style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                    <i class="bi bi-heart-pulse-fill fs-3"></i>
                        </div>
                                <h4 class="mb-0 fw-bold">Blood Donation Service</h4>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold mb-3" style="color: #ED1C24;">
                                <i class="bi bi-file-check me-2"></i>Requirements
                            </h5>
                            <ul class="mb-4" style="list-style: none; padding-left: 0;">
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Valid ID</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Age: 16-65 years old</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Weight: At least 50kg</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Good health condition</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>No recent major surgery</li>
                            </ul>

                            <h5 class="card-title fw-bold mb-3" style="color: #ED1C24;">
                                <i class="bi bi-list-ol me-2"></i>Procedure
                            </h5>
                            <ol class="mb-0" style="padding-left: 1.5rem;">
                                <li class="mb-2">Registration</li>
                                <li class="mb-2">Interview</li>
                                <li class="mb-2">Blood Testing</li>
                                <li class="mb-2">Blood Collection</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Blood Request Service -->
                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-lg" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 245, 245, 0.95) 100%); border: 2px solid rgba(237, 28, 36, 0.2) !important; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden;">
                        <div class="card-header text-white" style="background: var(--primary-gradient); padding: 1.5rem; border: none;">
                            <div class="d-flex align-items-center">
                                <div style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                    <i class="bi bi-file-earmark-medical-fill fs-3"></i>
                        </div>
                                <h4 class="mb-0 fw-bold">Blood Request Service</h4>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold mb-3" style="color: #ED1C24;">
                                <i class="bi bi-file-check me-2"></i>Requirements
                            </h5>
                            <ul class="mb-4" style="list-style: none; padding-left: 0;">
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Blood request form</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Referral letter from barangay (if don't have blood card)</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>STROBOX/COOLER with ICE</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Corresponding Processing fee</li>
                            </ul>

                            <h5 class="card-title fw-bold mb-3" style="color: #ED1C24;">
                                <i class="bi bi-list-ol me-2"></i>Procedure
                            </h5>
                            <ol class="mb-0" style="padding-left: 1.5rem;">
                                <li class="mb-2">Submit complete requirements</li>
                                <li class="mb-2">Verification of documents</li>
                                <li class="mb-2">Blood availability check</li>
                                <li class="mb-2">Processing of request</li>
                                <li class="mb-2">Release of blood products</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Blood Testing Service -->
                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-lg" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 245, 245, 0.95) 100%); border: 2px solid rgba(237, 28, 36, 0.2) !important; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden;">
                        <div class="card-header text-white" style="background: var(--primary-gradient); padding: 1.5rem; border: none;">
                            <div class="d-flex align-items-center">
                                <div style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                    <i class="bi bi-clipboard2-pulse-fill fs-3"></i>
                        </div>
                                <h4 class="mb-0 fw-bold">Blood Testing Service</h4>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold mb-3" style="color: #ED1C24;">
                                <i class="bi bi-file-check me-2"></i>Requirements
                            </h5>
                            <ul class="mb-4" style="list-style: none; padding-left: 0;">
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Valid ID</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Doctor's request form (if applicable)</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Previous test results (if any)</li>
                            </ul>

                            <h5 class="card-title fw-bold mb-3" style="color: #ED1C24;">
                                <i class="bi bi-list-ol me-2"></i>Procedure
                            </h5>
                            <ol class="mb-0" style="padding-left: 1.5rem;">
                                <li class="mb-2">Testing for Hepa B, Hepa C, HIV, Syphilis, and Malaria</li>
                                <li class="mb-2">Other Screening tests: Hgb, Blood typing, Vital signs</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Blood Drive Organization -->
                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-lg" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 245, 245, 0.95) 100%); border: 2px solid rgba(237, 28, 36, 0.2) !important; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden;">
                        <div class="card-header text-white" style="background: var(--primary-gradient); padding: 1.5rem; border: none;">
                            <div class="d-flex align-items-center">
                                <div style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                    <i class="bi bi-calendar-event-fill fs-3"></i>
                        </div>
                                <h4 class="mb-0 fw-bold">Blood Drive Organization</h4>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold mb-3" style="color: #ED1C24;">
                                <i class="bi bi-file-check me-2"></i>Requirements
                            </h5>
                            <ul class="mb-4" style="list-style: none; padding-left: 0;">
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Official letter of request</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Proposed venue details</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Expected number of donors</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Organization's registration documents</li>
                            </ul>

                            <h5 class="card-title fw-bold mb-3" style="color: #ED1C24;">
                                <i class="bi bi-list-ol me-2"></i>Procedure
                            </h5>
                            <ol class="mb-0" style="padding-left: 1.5rem;">
                                <li class="mb-2">Submit request letter</li>
                                <li class="mb-2">Site assessment</li>
                                <li class="mb-2">Schedule confirmation</li>
                                <li class="mb-2">MOA signing</li>
                                <li class="mb-2">Blood drive execution</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

           
    </section>
    <!-- Guidelines End -->


    

   

      <!-- Footer -->
      <footer class="footer py-5">
          <div class="container">
              <div class="row g-4">
                  <div class="col-lg-4 col-md-6">
                      <div class="footer-about">
                          <div class="footer-logo d-flex align-items-center mb-3">
                              <img src="imgs/rc.png" alt="Red Cross Logo" style="height: 50px; margin-right: 10px;">
                              <h4 class="text-white mb-0">Philippine Red Cross</h4>
                          </div>
                          <p class="text-white-50 mb-3">Born officially in 1947, the Philippine Red Cross has truly become the premier humanitarian organization in the country, committed to provide quality life-saving services that protect life and dignity of Filipinos.</p>
                          <div class="social-icons">
                              <a href="https://www.facebook.com/phredcross" target="_blank"><i class="fab fa-facebook-f"></i></a>
                              <a href="https://twitter.com/philredcross" target="_blank"><i class="fab fa-twitter"></i></a>
                              <a href="https://www.instagram.com/philredcross/" target="_blank"><i class="fab fa-instagram"></i></a>
                              <a href="http://www.linkedin.com/company/1089054" target="_blank"><i class="fab fa-linkedin-in"></i></a>
                          </div>
                      </div>
                  </div>

                  <div class="col-lg-2 col-md-6">
                      <div class="footer-links">
                          <h5>Quick Links</h5>
                          <ul>
                              <li><a href="redcrossportal.php">Home</a></li>
                              <li><a href="aboutrc.html">About Us</a></li>
                              <li><a href="blood-drives.php">Blood Drives</a></li>
                              <li><a href="requests.html">Donations</a></li>
                          </ul>
                      </div>
                  </div>

                  <div class="col-lg-4 col-md-6">
                      <div class="footer-links">
                          <h5>Contact Information</h5>
                          <ul>
                              <li>
                                  <i class="fas fa-map-marker-alt me-2"></i>
                                  10th St, Bacolod City, Philippines
                              </li>
                              <li>
                                  <i class="fas fa-phone me-2"></i>
                                  Emergency: 143
                              </li>
                              <li>
                                  <i class="fas fa-phone-alt me-2"></i>
                                  (034) 434 8541
                              </li>
                              <li>
                                  <i class="fas fa-envelope me-2"></i>
                                  negros.occidental@redcross.org.ph
                              </li>
                          </ul>
                      </div>
                  </div>

                  <div class="col-lg-3 col-md-6">
                      <div class="footer-links">
                          <h5>Operating Hours</h5>
                          <ul>
                              <li><i class="far fa-clock me-2"></i>Always Open</li>
                          </ul>
                      </div>
                  </div>
              </div>

              <hr class="mt-4 mb-3 border-secondary">

              <div class="row align-items-center">
                  <div class="col-md-6">
                      <p class="text-white-50 mb-md-0 text-center text-md-start">
                          &copy; <?php echo date('Y'); ?> Philippine Red Cross. All rights reserved.
                      </p>
                  </div>
                  <div class="col-md-6">
                      <div class="d-flex justify-content-center justify-content-md-end">
                          <a href="#" class="text-white-50 me-4">Privacy Policy</a>
                          <a href="#" class="text-white-50 me-4">Terms of Service</a>
                          <a href="#" class="text-white-50">Cookie Policy</a>
                      </div>
                  </div>
              </div>
          </div>
      </footer>

      <!-- Bootstrap 5 Bundle (includes Popper) -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- jQuery (required by some site scripts) -->
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <script type="text/javascript" src="js/swiper.min.js"></script>
      <script type="text/javascript" src="js/wow.min.js"></script>
      <script type="text/javascript" src="js/main.js"></script>

      <script>
        // Smooth scrolling for anchor links (vanilla JS)
        document.addEventListener('click', function(e) {
          const link = e.target.closest('a[href^="#"]');
          if (!link) return;
          const targetId = link.getAttribute('href');
          if (targetId.length > 1) {
            const el = document.querySelector(targetId);
            if (el) {
              e.preventDefault();
              const y = el.getBoundingClientRect().top + window.pageYOffset - 80;
              window.scrollTo({ top: y, behavior: 'smooth' });
            }
          }
        });

        // Active nav link on scroll
        const sections = document.querySelectorAll('.section');
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        function onScroll() {
          const scrollY = window.pageYOffset + 82;
          sections.forEach((sec, i) => {
            const top = sec.offsetTop;
            const bottom = top + sec.offsetHeight;
            if (scrollY >= top && scrollY < bottom) {
              navLinks.forEach(l => l.classList.remove('active'));
              if (navLinks[i]) navLinks[i].classList.add('active');
            }
          });
        }
        window.addEventListener('scroll', onScroll);
      </script>
  </body>

  </html>