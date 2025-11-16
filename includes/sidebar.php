<?php
// Determine active page
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Set role-specific variables
$roleColor = 'bg-danger';
$roleName = 'Donor';
$roleIcon = 'bi-droplet-fill';
$primaryColor = '#DC143C';
$secondaryColor = '#B22222';

if ($_SESSION['role'] === 'redcross') {
    $roleColor = 'bg-danger';
    $roleName = 'Red Cross';
    $roleIcon = 'bi-hospital';
    $primaryColor = '#DC143C';
    $secondaryColor = '#B22222';
} elseif ($_SESSION['role'] === 'negrosfirst') {
    $roleColor = 'bg-success';
    $roleName = 'Negros First';
    $roleIcon = 'bi-hospital';
    $primaryColor = '#28a745';
    $secondaryColor = '#218838';
} elseif ($_SESSION['role'] === 'patient') {
    $roleColor = 'bg-danger';
    $roleName = 'Patient';
    $roleIcon = 'bi-person';
    $primaryColor = '#DC2626'; // Red
    $secondaryColor = '#B91C1C'; // Darker Red
} elseif ($_SESSION['role'] === 'admin') {
    $roleColor = 'bg-dark';
    $roleName = 'Administrator';
    $roleIcon = 'bi-shield-lock';
    $primaryColor = '#212529';
    $secondaryColor = '#495057';
} elseif ($_SESSION['role'] === 'barangay') {
    $roleColor = 'bg-danger';
    $roleName = 'BHW';
    $roleIcon = 'bi-buildings';
    // Match body colors: Use dashboard theme colors
    $primaryColor = '#f8f9fa'; // Light gray background matching body
    $secondaryColor = '#ffffff'; // White for contrast
}
?>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 280px;
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    /* Blue sidebar with white text */
    background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
    border-right: none;
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    /* Red sidebar with white text */
    background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
    border-right: none;
    box-shadow: 4px 0 20px rgba(220, 38, 38, 0.3);
    <?php else: ?>
    background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%);
    <?php endif; ?>
    box-shadow: 4px 0 15px rgba(0,0,0,0.1);
    z-index: 1000;
    overflow-y: auto;
    transition: all 0.3s ease;
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.sidebar-header {
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
    border-bottom: 1px solid #e9ecef;
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
    border-bottom: 1px solid rgba(255,255,255,0.2);
    <?php else: ?>
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255,255,255,0.2);
    <?php endif; ?>
    padding: 1.5rem;
}

.sidebar-logo {
    width: 50px;
    height: 50px;
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    background: rgba(255,255,255,0.2);
    border: 2px solid rgba(255,255,255,0.3);
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    background: rgba(255,255,255,0.2);
    border: 2px solid rgba(255,255,255,0.3);
    <?php else: ?>
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    <?php endif; ?>
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sidebar-logo i {
    color: white;
    font-size: 1.5rem;
}

.sidebar-header h5 {
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
}

.sidebar-header small {
    <?php if ($_SESSION['role'] === 'barangay' || $_SESSION['role'] === 'patient'): ?>
    color: rgba(255,255,255,0.9);
    <?php else: ?>
    color: rgba(255,255,255,0.8);
    <?php endif; ?>
    font-weight: 500;
}

.sidebar-menu {
    padding: 1rem 0;
}

.sidebar .nav-link {
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    color: white;
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    color: white;
    <?php else: ?>
    color: rgba(255,255,255,0.9);
    <?php endif; ?>
    padding: 0.75rem 1.5rem;
    margin: 0.25rem 1rem;
    border-radius: 10px;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 14px;
    letter-spacing: 0.3px;
    border: none;
    background: transparent;
}

.sidebar .nav-link:hover {
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    background: #FCD34D;
    color: #1E3A8A;
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(252, 211, 77, 0.3);
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(255, 255, 255, 0.3);
    <?php else: ?>
    background: rgba(255,255,255,0.15);
    color: white;
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    <?php endif; ?>
}

.sidebar .nav-link.active {
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    background: rgba(252, 211, 77, 0.2);
    color: white !important;
    box-shadow: 0 2px 8px rgba(252, 211, 77, 0.3);
    border-left: 4px solid #FCD34D;
    font-weight: 600;
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    background: rgba(255, 255, 255, 0.2);
    color: white !important;
    box-shadow: 0 2px 8px rgba(255, 255, 255, 0.3);
    border-left: 4px solid rgba(255, 255, 255, 0.8);
    font-weight: 600;
    <?php else: ?>
    background: rgba(255,255,255,0.2);
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    border-left: 4px solid white;
    font-weight: 600;
    <?php endif; ?>
}

.sidebar .nav-link i {
    width: 20px;
    text-align: center;
    margin-right: 0.75rem;
    font-size: 1rem;
    <?php if ($_SESSION['role'] === 'barangay' || $_SESSION['role'] === 'patient'): ?>
    color: white;
    <?php endif; ?>
}

<?php if ($_SESSION['role'] === 'barangay'): ?>
.sidebar .nav-link:hover i {
    color: #1E3A8A;
}
.sidebar .nav-link.active i {
    color: white !important;
}
<?php elseif ($_SESSION['role'] === 'patient'): ?>
.sidebar .nav-link:hover i {
    color: white;
}
.sidebar .nav-link.active i {
    color: white !important;
}
<?php endif; ?>

.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1.5rem;
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    background: rgba(30, 64, 175, 0.3);
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    background: rgba(185, 28, 28, 0.3);
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    <?php else: ?>
    background: rgba(0,0,0,0.1);
    border-top: 1px solid rgba(255,255,255,0.2);
    <?php endif; ?>
}

.sidebar-footer .btn {
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
    border: 1px solid #1E40AF;
    color: white;
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    <?php else: ?>
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    <?php endif; ?>
    font-weight: 500;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.sidebar-footer .btn:hover {
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    background: #FCD34D;
    border-color: #FCD34D;
    color: #1E3A8A;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(252, 211, 77, 0.4);
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
    <?php else: ?>
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    <?php endif; ?>
}

/* Mobile responsiveness */
@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    background: rgba(30, 64, 175, 0.2);
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    background: rgba(185, 28, 28, 0.2);
    <?php else: ?>
    background: rgba(255,255,255,0.1);
    <?php endif; ?>
}

.sidebar::-webkit-scrollbar-thumb {
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    background: rgba(252, 211, 77, 0.5);
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    background: rgba(255, 255, 255, 0.3);
    <?php else: ?>
    background: rgba(255,255,255,0.3);
    <?php endif; ?>
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    <?php if ($_SESSION['role'] === 'barangay'): ?>
    background: rgba(252, 211, 77, 0.8);
    <?php elseif ($_SESSION['role'] === 'patient'): ?>
    background: rgba(255, 255, 255, 0.5);
    <?php else: ?>
    background: rgba(255,255,255,0.5);
    <?php endif; ?>
}
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="d-flex align-items-center">
            <div class="sidebar-logo me-3">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'negrosfirst'): ?>
                    <img src="/blood/assets/img/nflogo.png" alt="Negros First Logo" style="max-width:42px; max-height:42px; display:block;">
                <?php else: ?>
                    <i class="bi <?php echo $roleIcon; ?>"></i>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($_SESSION['role'] === 'barangay'): ?>
                    <h5 class="mb-0">Barangay Portal</h5>
                <?php elseif ($_SESSION['role'] === 'negrosfirst'): ?>
                    <h5 class="mb-0">Negros First Provincial Blood Center</h5>
                <?php elseif ($_SESSION['role'] === 'redcross'): ?>
                    <h5 class="mb-0">Red Cross Blood Center</h5>
                <?php elseif ($_SESSION['role'] === 'donor'): ?>
                    <h5 class="mb-0">Donor Portal</h5>
                <?php elseif ($_SESSION['role'] === 'patient'): ?>
                    <h5 class="mb-0">Patient Portal</h5>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <h5 class="mb-0">Admin Portal</h5>
                <?php else: ?>
                    <h5 class="mb-0">Blood Bank Portal</h5>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <?php if ($_SESSION['role'] === 'donor'): ?>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($currentPage === 'index.php' && $currentDir === 'donor') ? 'active' : ''; ?>" href="/blood/dashboard/donor/index.php">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo $currentPage === 'schedule-donation.php' ? 'active' : ''; ?>" href="/blood/dashboard/donor/schedule-donation.php">
                        <i class="bi bi-calendar-check me-2"></i> Donation Schedule
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo $currentPage === 'donation-history.php' ? 'active' : ''; ?>" href="/blood/dashboard/donor/donation-history.php">
                        <i class="bi bi-clock-history me-2"></i> Donation History
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo $currentPage === 'announcements.php' ? 'active' : ''; ?>" href="/blood/dashboard/donor/announcements.php">
                        <i class="bi bi-megaphone me-2"></i> Announcements
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>" href="/blood/dashboard/donor/profile.php">
                        <i class="bi bi-person me-2"></i> My Profile
                    </a>
                </li>
            <?php elseif ($_SESSION['role'] === 'redcross' || $_SESSION['role'] === 'negrosfirst'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'index.php' && ($currentDir === 'redcross' || $currentDir === 'negrosfirst')) ? 'active' : ''; ?>" href="/blood/dashboard/<?php echo $_SESSION['role']; ?>/index.php">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'inventory.php' || $currentPage === 'enhanced-inventory.php') ? 'active' : ''; ?>" href="/blood/dashboard/<?php echo $_SESSION['role']; ?>/enhanced-inventory.php">
                        <i class="bi bi-box me-2"></i> Blood Inventory
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'blood-drives.php' ? 'active' : ''; ?>" href="/blood/dashboard/<?php echo $_SESSION['role']; ?>/blood-drives.php">
                        <i class="bi bi-calendar-event me-2"></i> Blood Drives
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'appointments.php' ? 'active' : ''; ?>" href="../../dashboard/<?php echo $_SESSION['role']; ?>/appointments.php">
                        <i class="bi bi-calendar-check me-2"></i> Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'announcements.php' ? 'active' : ''; ?>" href="/blood/dashboard/<?php echo $_SESSION['role']; ?>/announcements.php">
                        <i class="bi bi-megaphone me-2"></i> Announcements
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'blood-requests.php' ? 'active' : ''; ?>" href="/blood/dashboard/<?php echo $_SESSION['role']; ?>/blood-requests.php">
                        <i class="bi bi-clipboard-check me-2"></i> Blood Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'donations.php' ? 'active' : ''; ?>" href="/blood/dashboard/<?php echo $_SESSION['role']; ?>/donations.php">
                        <i class="bi bi-clipboard-check me-2"></i> Blood Donation
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" href="/blood/dashboard/<?php echo $_SESSION['role']; ?>/reports.php">
                        <i class="bi bi-file-earmark-text me-2"></i> Reports
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'maintenance.php' ? 'active' : ''; ?>" href="/blood/dashboard/<?php echo $_SESSION['role']; ?>/maintenance.php">
                        <i class="bi bi-tools me-2"></i> Maintenance
                    </a>
                </li>
                
            <?php elseif ($_SESSION['role'] === 'patient'): ?>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($currentPage === 'index.php' && $currentDir === 'patient') ? 'active' : ''; ?>" href="/blood/dashboard/patient/index.php">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo $currentPage === 'request-blood.php' ? 'active' : ''; ?>" href="/blood/dashboard/patient/request-blood.php">
                        <i class="bi bi-droplet me-2"></i> Request Blood
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo $currentPage === 'request-history.php' ? 'active' : ''; ?>" href="/blood/dashboard/patient/request-history.php">
                        <i class="bi bi-clock-history me-2"></i> Request History
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo $currentPage === 'announcements.php' ? 'active' : ''; ?>" href="/blood/dashboard/patient/announcements.php">
                        <i class="bi bi-megaphone me-2"></i> Announcements
                    </a>
                </li>
              
               
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo $currentPage === 'blood-availability.php' ? 'active' : ''; ?>" href="/blood/dashboard/patient/blood-availability.php">
                        <i class="bi bi-hospital me-2"></i> Blood Banks
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if (basename($_SERVER['PHP_SELF']) == 'referrals.php') echo ' active'; ?>" href="/blood/dashboard/patient/referrals.php">
                        <i class="bi bi-file-earmark-text me-2"></i>
                        My Referrals
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>" href="/blood/dashboard/patient/profile.php">
                        <i class="bi bi-person me-2"></i> My Profile
                    </a>
                </li>
           
            <?php elseif ($_SESSION['role'] === 'barangay'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'index.php' && $currentDir === 'barangay') ? 'active' : ''; ?>" href="/blood/dashboard/barangay/index.php">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'blood-requests.php' ? 'active' : ''; ?>" href="/blood/dashboard/barangay/blood-requests.php">
                        <i class="bi bi-clipboard2-pulse me-2"></i> Blood Requests
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'referrals.php' ? 'active' : ''; ?>" href="/blood/dashboard/barangay/referrals.php">
                        <i class="bi bi-file-earmark-text me-2"></i> Referrals
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'announcements.php' ? 'active' : ''; ?>" href="/blood/dashboard/barangay/announcements.php">
                        <i class="bi bi-megaphone me-2"></i> Announcements
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>" href="/blood/dashboard/barangay/profile.php">
                        <i class="bi bi-person me-2"></i> Profile
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="sidebar-footer">
        <?php if ($_SESSION['role'] === 'redcross'): ?>
            <a href="../../logout.php?redirect=redcrossportal.php" class="btn w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Red Cross Portal
            </a>
        <?php elseif ($_SESSION['role'] === 'negrosfirst'): ?>
            <a href="../../logout.php?redirect=negrosfirstportal.php" class="btn w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Negros First Portal
            </a>
        <?php elseif ($_SESSION['role'] === 'barangay'): ?>
            <a href="../../logout.php?redirect=barangay-portal.php" class="btn w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Barangay Portal
            </a>
        <?php else: ?>
            <a href="../../logout.php" class="btn w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Logout
            </a>
        <?php endif; ?>
    </div>
</aside>

<style>
/* Mobile responsive sidebar */
@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1050;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-header {
        padding: 1rem;
    }
    
    .sidebar-brand {
        font-size: 1.1rem;
    }
    
    .sidebar-nav {
        padding: 0.5rem;
    }
    
    .nav-link {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
    
    .sidebar-footer {
        padding: 1rem;
    }
}

@media (max-width: 576px) {
    .sidebar {
        width: 280px;
    }
    
    .sidebar-header {
        padding: 0.75rem;
    }
    
    .sidebar-brand {
        font-size: 1rem;
    }
    
    .nav-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
    
    .sidebar-footer {
        padding: 0.75rem;
    }
}
</style>

