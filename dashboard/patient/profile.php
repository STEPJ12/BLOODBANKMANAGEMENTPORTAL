<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../login.php?role=patient");
    exit;
}

// Set page title
$pageTitle = "Profile - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get patient information
$patientId = $_SESSION['user_id'];
$patient = getRow("SELECT * FROM patient_users WHERE id = ?", [$patientId]);

// Process form submission
$success = false;
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Function to format text properly (capitalize each word, single spacing)
    function formatText($text) {
        // Remove extra spaces and trim
        $text = trim(preg_replace('/\s+/', ' ', $text));
        // Capitalize each word
        return ucwords(strtolower($text));
    }
    
    // Validate and format input
    $name = formatText(sanitize($_POST['name'] ?? ''));
    $email = strtolower(trim(sanitize($_POST['email'] ?? '')));
    $contactNumber = sanitize($_POST['contact_number'] ?? '');
    $address = formatText(sanitize($_POST['address'] ?? ''));
    $city = formatText(sanitize($_POST['city'] ?? ''));
    
    // Handle date_of_birth, gender, and blood_type
    // If they already exist in database, keep them; otherwise use POST value
    $dateOfBirth = !empty($patient['date_of_birth']) ? $patient['date_of_birth'] : (!empty($_POST['date_of_birth']) ? sanitize($_POST['date_of_birth']) : null);
    $gender = !empty($patient['gender']) ? $patient['gender'] : (!empty($_POST['gender']) ? sanitize($_POST['gender']) : null);
    $bloodType = !empty($patient['blood_type']) ? $patient['blood_type'] : (!empty($_POST['blood_type']) ? sanitize($_POST['blood_type']) : null);
    
    // Convert empty strings to null for optional fields
    $dateOfBirth = empty($dateOfBirth) || $dateOfBirth === '0000-00-00' ? null : $dateOfBirth;
    $gender = empty($gender) ? null : $gender;
    $bloodType = empty($bloodType) ? null : $bloodType;
    $address = empty($address) ? null : $address;
    $city = empty($city) ? null : $city;

    // Validate data
    if (empty($name) || empty($email) || empty($contactNumber)) {
        $error = "Please fill in all required fields (Name, Email, and Contact Number).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email is already in use by another user
        $existingUser = getRow("SELECT id FROM patient_users WHERE email = ? AND id != ?", [$email, $patientId]);
        if ($existingUser) {
            $error = "Email is already in use by another user.";
        } else {
            // Use direct PDO connection for better error handling
            try {
                $conn = getConnection();
                
                $stmt = $conn->prepare("
                UPDATE patient_users
                SET
                    name = ?,
                    email = ?,
                    phone = ?,
                    address = ?,
                    city = ?,
                    date_of_birth = ?,
                    gender = ?,
                    blood_type = ?,
                    updated_at = NOW()
                WHERE id = ?
                ");
                
                $result = $stmt->execute([
                $name, $email, $contactNumber, $address, $city,
                $dateOfBirth, $gender, $bloodType, $patientId
            ]);

                if ($result && $stmt->rowCount() >= 0) {
                $success = true;
                // Refresh patient data
                $patient = getRow("SELECT * FROM patient_users WHERE id = ?", [$patientId]);
            } else {
                    $error = "Failed to update profile. No changes were made.";
                    error_log("Profile update failed - no rows affected for patient ID: " . $patientId);
                }
            } catch (PDOException $e) {
                $error = "Failed to update profile. Please check your input and try again.";
                error_log("Profile update PDO error for patient ID " . $patientId . ": " . $e->getMessage());
            } catch (Exception $e) {
                $error = "An unexpected error occurred. Please try again.";
                error_log("Profile update exception for patient ID " . $patientId . ": " . $e->getMessage());
            }
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
    
    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>

    <style>
          body {
              min-height: 100vh;
              overflow-x: hidden;
              margin: 0;
              padding: 0;
          }

          .dashboard-container {
              flex: 1;
              display: flex;
              min-height: 100vh;
              width: 100%;
              position: relative;
              overflow: hidden;
          }

          /* Red Theme for Profile Page */
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
          
          .dashboard-content {
              flex: 1;
              display: flex;
              flex-direction: column;
              min-width: 0;
              height: 100vh;
              overflow-y: auto;
              overflow-x: hidden;
              padding: 0;
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
              transform: none !important;
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

          .dashboard-main {
              flex: 1;
              padding: 1.5rem;
              overflow-y: auto;
              width: 100%;
              max-width: 100%;
              box-sizing: border-box;
          }

          /* Sidebar adjustment */
          .sidebar {
              position: fixed;
              left: 0;
              top: 0;
              height: 100vh;
              width: 250px;
              z-index: 1001; /* Higher than header */
          }

          /* Responsive adjustments */
          @media (max-width: 991.98px) {
              .dashboard-content {
                  margin-left: 0;
                  padding-top: 100px; /* Space for fixed header on mobile */
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

          .avatar-circle {
              width: 100px;
              height: 100px;
              background-color: #dc3545;
              border-radius: 50%;
              display: flex;
              justify-content: center;
              align-items: center;
              margin: 0 auto 1rem;
          }
          .dashboard-header .breadcrumb {
        margin-left: 45rem;
    }

          .avatar-initials {
              color: white;
              font-size: 48px;
              font-weight: bold;
              text-transform: uppercase;
          }

          .form-control {
              max-width: 100%;
          }

          .card {
              height: 100%;
              margin-bottom: 1rem;
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

          @media (max-width: 768px) {
              .dashboard-main {
                  padding: 1rem;
              }

              .dashboard-content {
                  height: calc(100vh - 56px);
              }

              .row > [class*='col-'] {
                  margin-bottom: 1rem;
              }
          }

          @media (max-width: 576px) {
              .dashboard-header {
                  flex-direction: column;
                  align-items: flex-start !important;
              }

              .breadcrumb {
                  margin-top: 0.5rem;
              }

              .avatar-circle {
                  width: 80px;
                  height: 80px;
              }

              .avatar-initials {
                  font-size: 36px;
              }
          }

          @media (min-width: 768px) {
              .row > [class*='col-'] {
                  margin-bottom: 0;
              }
          }

          .row.g-4 {
              margin-right: 0;
              margin-left: 0;
          }

          .form-control, .form-select {
              width: 100%;
              max-width: 100%;
          }

          .card-body {
              padding: 1.25rem;
          }
      
      /* Red Theme - Additional Component Styles */
      .btn-primary, .btn-primary-custom {
          background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
          border: none;
          color: white;
          font-weight: 600;
      }
      
      .btn-primary:hover, .btn-primary-custom:hover {
          background: linear-gradient(135deg, #B91C1C 0%, #991B1B 100%);
          color: white;
          transform: translateY(-2px);
          box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
      }
      
      .btn-outline-primary {
          border: 2px solid #DC2626;
          color: #DC2626;
          background: transparent;
      }
      
      .btn-outline-primary:hover {
          background: #DC2626;
          color: white;
      }
      
      .card-header.bg-gradient-light {
          background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
          color: white;
      }
      
      .card-header .card-title {
          color: white !important;
      }
      
      .badge.bg-primary, .badge.bg-danger {
          background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
          color: white;
      }
      
      .badge.bg-success {
          background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
          color: white;
      }
      
      .text-primary-custom {
          color: #DC2626 !important;
      }
      
      a.text-primary-custom:hover {
          color: #B91C1C !important;
      }
      
      .breadcrumb-item a {
          color: rgba(255, 255, 255, 0.8);
      }
      
      .breadcrumb-item.active {
          color: white;
      }
      
      .table-hover tbody tr:hover {
          background-color: rgba(220, 38, 38, 0.05);
      }
      
      .modal-header.bg-gradient-primary {
          background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
          color: white;
      }
      
      .dashboard-header .text-muted {
          color: rgba(255, 255, 255, 0.8) !important;
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
      
      .btn-outline-primary {
          border: 2px solid #DC2626;
          color: #DC2626;
      }
      
      .btn-outline-primary:hover {
          background: #DC2626;
          color: white;
      }
      
      .avatar-circle {
          background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
      }
      
      /* Card Text */
      .card h3, .card h4, .card h5 {
          color: #1F2937;
      }
      
      .card p {
          color: #4B5563;
      }
      
      .page-title {
          color: white !important;
      }
      </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <div class="header-content">
                <h2 class="page-title">My Profile</h2>
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

        <div class="dashboard-main">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" data-auto-dismiss="5000">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Your profile has been updated successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" data-auto-dismiss="5000">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Profile Card -->
                <div class="col-12 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">Profile Information</h4>
                            
                        </div>
                        <div class="card-body text-center">
                            <div class="avatar-circle">
                                <span class="avatar-initials"><?php echo substr($patient['name'], 0, 1); ?></span>
                            </div>
                            <h5 class="card-title"><?php echo $patient['name']; ?></h5>
                            <p class="card-text text-muted mb-1"><?php echo $patient['email']; ?></p>
                            <p class="card-text text-muted mb-3"><?php echo $patient['phone']; ?></p>

                            <div class="d-flex justify-content-center mb-3">
                                <span class="badge bg-danger px-3 py-2">
                                    <i class="bi bi-droplet-fill me-1"></i>
                                    <?php echo $patient['blood_type']; ?> Blood Type
                                </span>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="request-blood.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>        Request Blood
                                </a>
                                <a href="request-history.php" class="btn btn-outline-primary">
                                    <i class="bi bi-clipboard-check me-2"></i>View Request History
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile Form -->
                <div class="col-12 col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="card-title mb-0">Edit Profile</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($patient['name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="contact_number" class="form-label">Contact Number</label>
                                        <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <?php if (empty($patient['date_of_birth'])): ?>
                                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($patient['date_of_birth']); ?>" required>
                                            <div class="form-text">Please set your date of birth. This cannot be changed later.</div>
                                        <?php else: ?>
                                            <input type="date" class="form-control" id="date_of_birth_display" value="<?php echo htmlspecialchars($patient['date_of_birth']); ?>" disabled>
                                            <input type="hidden" name="date_of_birth" value="<?php echo htmlspecialchars($patient['date_of_birth']); ?>">
                                            <div class="form-text">Date of birth cannot be changed.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="gender" class="form-label">Gender</label>
                                        <?php if (empty($patient['gender'])): ?>
                                            <select class="form-select" id="gender" name="gender" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male" <?php echo ($patient['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo ($patient['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                                <option value="Other" <?php echo ($patient['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                            <div class="form-text">Please select your gender. This cannot be changed later.</div>
                                        <?php else: ?>
                                            <input type="text" class="form-control" id="gender_display" value="<?php echo htmlspecialchars($patient['gender']); ?>" disabled>
                                            <input type="hidden" name="gender" value="<?php echo htmlspecialchars($patient['gender']); ?>">
                                            <div class="form-text">Gender cannot be changed.</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="blood_type" class="form-label">Blood Type</label>
                                        <?php if (empty($patient['blood_type'])): ?>
                                            <select class="form-select" id="blood_type" name="blood_type" required>
                                                <option value="">Select Blood Type</option>
                                                <option value="A+" <?php echo ($patient['blood_type'] === 'A+') ? 'selected' : ''; ?>>A+</option>
                                                <option value="A-" <?php echo ($patient['blood_type'] === 'A-') ? 'selected' : ''; ?>>A-</option>
                                                <option value="B+" <?php echo ($patient['blood_type'] === 'B+') ? 'selected' : ''; ?>>B+</option>
                                                <option value="B-" <?php echo ($patient['blood_type'] === 'B-') ? 'selected' : ''; ?>>B-</option>
                                                <option value="AB+" <?php echo ($patient['blood_type'] === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                                <option value="AB-" <?php echo ($patient['blood_type'] === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                                <option value="O+" <?php echo ($patient['blood_type'] === 'O+') ? 'selected' : ''; ?>>O+</option>
                                                <option value="O-" <?php echo ($patient['blood_type'] === 'O-') ? 'selected' : ''; ?>>O-</option>
                                            </select>
                                            <div class="form-text">Please select your blood type. This cannot be changed later.</div>
                                        <?php else: ?>
                                            <input type="text" class="form-control" id="blood_type_display" value="<?php echo htmlspecialchars($patient['blood_type']); ?>" disabled>
                                            <input type="hidden" name="blood_type" value="<?php echo htmlspecialchars($patient['blood_type']); ?>">
                                            <div class="form-text">Blood type cannot be changed.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Home Address</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($patient['address']); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($patient['city'] ?? ''); ?>">
                                </div>

                                

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Print Utilities -->
<script src="../../assets/js/print-utils.js"></script>

<!-- Auto-dismiss alerts after 5 seconds -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('[data-auto-dismiss]');
    alerts.forEach(function(alert) {
        const dismissTime = parseInt(alert.getAttribute('data-auto-dismiss'));
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, dismissTime);
    });
});
</script>

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
    
    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .page-title {
        font-size: 1.25rem;
    }
    
    .row.g-4 {
        margin: 0;
    }
    
    .col-12.col-md-4,
    .col-12.col-md-8 {
        margin-bottom: 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .form-control {
        font-size: 16px; /* Prevents zoom on iOS */
    }
    
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}

@media (max-width: 576px) {
    .dashboard-header {
        padding: 0.75rem;
    }
    
    .page-title {
        font-size: 1.125rem;
    }
    
    .card {
        margin: 0 0.5rem;
    }
    
    .form-label {
        font-size: 0.875rem;
    }
    
    .btn {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
    
    .avatar-circle {
        width: 60px;
        height: 60px;
    }
    
    .avatar-initials {
        font-size: 1.5rem;
    }
}

/* Tablet Responsive */
@media (max-width: 992px) and (min-width: 769px) {
    .col-md-4,
    .col-md-8 {
        margin-bottom: 1.5rem;
    }
}
</style>

</body>
</html>
