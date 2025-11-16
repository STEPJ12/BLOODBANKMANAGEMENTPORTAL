<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $basePath; ?>assets/images/favicon.png">

    <!-- Bootstrap CSS - Offline -->
    <link href="<?php echo $basePath; ?>assets/css/bootstrap.min.css" rel="stylesheet" onerror="console.error('Bootstrap CSS not found locally. Please download Bootstrap 5.3.0 and place bootstrap.min.css in assets/css/')">

    <!-- Bootstrap Icons CSS - Offline -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/bootstrap-icons-offline.css">

    <!-- Custom Fonts - Using system fonts for offline -->
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
    </style>

    <!-- Chart.js - Offline -->
    <script src="<?php echo $basePath; ?>assets/js/chart.min.js"></script>
    <script>
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not found locally. Please download Chart.js and place chart.min.js in assets/js/');
        }
    </script>

    <!-- Custom CSS -->
    <?php
// Security headers and cookie flags
require_once __DIR__ . '/security.php';
    // Determine the correct path for CSS files
    $basePath = '';
    if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
        $basePath = '../../';
        echo '<link rel="stylesheet" href="' . $basePath . 'assets/css/dashboard.css">';
    }
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">

    <style>
      /* Global responsive helpers */
      img { max-width: 100%; height: auto; }
      @media (max-width: 991.98px) {
        .table-responsive, .table { width: 100%; display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .btn-group, .hero-actions, .table-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
        .btn, .hero-btn, .table-btn { width: 100%; max-width: 100%; }
        .modal-dialog { max-width: 95vw; margin: .5rem auto; }
        .card-title { font-size: 1.05rem; }
        .breadcrumb { font-size: .9rem; }
      }
      @media (max-width: 575.98px) {
        h1 { font-size: 1.4rem; }
        h2 { font-size: 1.25rem; }
        h3 { font-size: 1.1rem; }
      }
    </style>

    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
</head>
<body>
    <?php if (!isset($isDashboard) || !$isDashboard): ?>
    <!-- Header for non-dashboard pages -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="<?php echo $basePath; ?>index.php">
                    <i class="bi bi-droplet-fill me-2"></i>
                    <span>Blood Bank Portal</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $basePath; ?>index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $basePath; ?>about-us.html">About Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $basePath; ?>crowdsourcing.php">Community Portal</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $basePath; ?>citizen-charter.php">Citizen Charter</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $basePath; ?>donations.php">Donations</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $basePath; ?>contact-us.html">Contact Us</a>
                        </li>
                    </ul>
                    <div class="d-flex">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo $basePath; ?>dashboard/<?php echo $_SESSION['role']; ?>/index.php" class="btn btn-outline-light me-2">Dashboard</a>
                            <a href="<?php echo $basePath; ?>logout.php" class="btn btn-light">Logout</a>
                        <?php else: ?>
                            <a href="<?php echo $basePath; ?>login.php" class="btn btn-outline-light me-2">Sign In</a>
                            <a href="<?php echo $basePath; ?>register.php" class="btn btn-light">Register</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <?php endif; ?>
    <!-- Main Content -->
    <main>
    <?php
    // Dashboard top-right notifications for Negros First
    if (isset($isDashboard) && $isDashboard && isset($_SESSION['role']) && $_SESSION['role'] === 'negrosfirst') {
        // Load DB helpers (safe to include multiple times)
        if (!function_exists('getCount')) {
            @require_once($basePath . 'config/db.php');
        }
        $notifCount = 0; $notifList = [];
        try {
            $notifCount = getCount("SELECT COUNT(*) FROM notifications WHERE user_role = 'negrosfirst' AND is_read = 0");
            $notifList = executeQuery("SELECT id, title, message, created_at, is_read FROM notifications WHERE user_role='negrosfirst' ORDER BY created_at DESC LIMIT 10");
            if (!is_array($notifList)) { $notifList = []; }
        } catch (Exception $e) { /* ignore rendering errors */ }
        ?>
        <style>
            .nf-topbar { position: fixed; top: 10px; right: 16px; z-index: 1040; }
            .nf-dropdown { position: absolute; right: 0; top: 42px; width: 320px; display: none !important; z-index: 1030; }
            .nf-dropdown.show { display: block !important; }
            /* Force hide dropdown when any modal is open - Bootstrap 5 adds 'modal-open' class to body */
            body.modal-open .nf-dropdown,
            body.modal-open .nf-dropdown.show,
            body.modal-open .nf-topbar .nf-dropdown,
            body.modal-open .nf-topbar .nf-dropdown.show {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }
            /* Hide when modal backdrop exists */
            .modal-backdrop ~ * .nf-dropdown,
            .modal-backdrop.show ~ * .nf-dropdown,
            .modal.show ~ * .nf-dropdown {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
            }
            /* Hide when modal element exists in DOM */
            body:has(.modal.show) .nf-dropdown,
            body:has(.modal.show) .nf-dropdown.show {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
            }
            .nf-notif-item { white-space: normal; }
            @media (max-width: 991.98px) { .nf-topbar { top: 8px; right: 12px; } }
        </style>
        <div class="nf-topbar">
            <button id="nfBellBtn" class="btn btn-light position-relative shadow-sm">
                <i class="bi bi-bell"></i>
                <span id="nfBellBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: <?php echo ((int)$notifCount>0)?'inline':'none'; ?>;">
                    <?php echo (int)$notifCount; ?>
                </span>
            </button>
            <div id="nfDropdown" class="nf-dropdown card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Notifications</span>
                    <a class="small text-decoration-none" href="<?php echo $basePath; ?>dashboard/negrosfirst/notifications.php">View All</a>
                </div>
                <div id="nfList" class="list-group list-group-flush" style="max-height: 360px; overflow:auto;">
                    <?php if (!empty($notifList)): foreach ($notifList as $n): ?>
                        <div class="list-group-item nf-notif-item">
                            <div class="d-flex justify-content-between">
                                <div class="fw-semibold small"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="text-muted small"><?php echo date('M d, g:i A', strtotime($n['created_at'])); ?></div>
                            </div>
                            <div class="small text-muted"><?php echo htmlspecialchars($n['message']); ?></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="list-group-item text-center text-muted py-3">No notifications</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
            // Simple and direct notification dropdown handler
            function initNotificationDropdown() {
                const btn = document.getElementById('nfBellBtn');
                const dd = document.getElementById('nfDropdown');
                
                if (!btn || !dd) {
                    console.log('Notification elements not found');
                    return;
                }
                
                console.log('Setting up notification dropdown');
                
                // Function to check if modal is open
                const isModalOpen = function() {
                    return document.body.classList.contains('modal-open') || 
                           document.querySelector('.modal.show') !== null ||
                           document.querySelector('.modal-backdrop') !== null;
                };
                
                // Bell click handler
                btn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Don't open dropdown if modal is open
                    if (isModalOpen()) {
                        dd.classList.remove('show');
                        return;
                    }
                    
                    if (dd.classList.contains('show')) {
                        dd.classList.remove('show');
                    } else {
                        dd.classList.add('show');
                    }
                };
                
                // Outside click handler
                document.onclick = function(e) {
                    if (!btn.contains(e.target) && !dd.contains(e.target)) {
                        if (dd.classList.contains('show')) {
                            dd.classList.remove('show');
                            console.log('Dropdown closed by outside click');
                        }
                    }
                };
                
                // Notification item click handler
                dd.onclick = function(e) {
                    if (e.target.closest('.list-group-item') || e.target.closest('a')) {
                        dd.classList.remove('show');
                        console.log('Dropdown closed by notification click');
                    }
                };
                
                // Escape key handler
                document.onkeydown = function(e) {
                    if (e.key === 'Escape' && dd.classList.contains('show')) {
                        dd.classList.remove('show');
                        console.log('Dropdown closed by Escape');
                    }
                };
                
                // Close dropdown when any modal is shown (Bootstrap 5)
                const handleModalShow = function() {
                    dd.classList.remove('show');
                    // Force hide with inline style as backup
                    dd.style.display = 'none';
                    dd.style.visibility = 'hidden';
                };
                
                // Listen for Bootstrap modal show events
                document.addEventListener('show.bs.modal', handleModalShow);
                document.addEventListener('shown.bs.modal', handleModalShow);
                document.addEventListener('hide.bs.modal', function() {
                    // Remove inline styles when modal closes
                    dd.style.display = '';
                    dd.style.visibility = '';
                });
                
                // Watch for modal-open class on body
                const bodyObserver = new MutationObserver(function(mutations) {
                    if (isModalOpen()) {
                        handleModalShow();
                    } else {
                        // Remove inline styles when modal closes
                        dd.style.display = '';
                        dd.style.visibility = '';
                    }
                });
                
                bodyObserver.observe(document.body, {
                    attributes: true,
                    attributeFilter: ['class']
                });
                
                // Also check when modals are added/removed from DOM
                const modalObserver = new MutationObserver(function(mutations) {
                    if (isModalOpen() && dd.classList.contains('show')) {
                        handleModalShow();
                    }
                });
                
                modalObserver.observe(document.body, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class']
                });
                
                // Periodic check as additional safety measure
                setInterval(function() {
                    if (isModalOpen() && dd.classList.contains('show')) {
                        handleModalShow();
                    }
                }, 100);
            }
            
            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initNotificationDropdown);
            } else {
                initNotificationDropdown();
            }

                // Live polling every 30s
                async function refreshNotifications() {
                    try {
                        const res = await fetch('<?php echo $basePath; ?>dashboard/negrosfirst/notifications_fetch.php', { credentials: 'same-origin' });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (badge) {
                            const count = parseInt(data.unread || 0, 10);
                            badge.textContent = count;
                            badge.style.display = count > 0 ? 'inline' : 'none';
                        }
                        if (list && Array.isArray(data.items)) {
                            if (data.items.length === 0) {
                                list.innerHTML = '<div class="list-group-item text-center text-muted py-3">No notifications</div>';
                            } else {
                                list.innerHTML = data.items.map(n => `
                                    <div class="list-group-item nf-notif-item">
                                        <div class="d-flex justify-content-between">
                                            <div class="fw-semibold small">${(n.title||'').toString().replaceAll('<','&lt;')}</div>
                                            <div class="text-muted small">${new Date(n.created_at).toLocaleString()}</div>
                                        </div>
                                        <div class="small text-muted">${(n.message||'').toString().replaceAll('<','&lt;')}</div>
                                    </div>
                                `).join('');
                            }
                        }
                    } catch (e) {
                        // silent fail
                    }
                }
                refreshNotifications();
                setInterval(refreshNotifications, 5000);
            });
        </script>
    <?php }
    ?>
    <script>
      // Global 30s auto-dismiss for Bootstrap alerts
      document.addEventListener('DOMContentLoaded', function(){
        // Prevent form resubmission on refresh/back by clearing POST state
        try { if (window.history && window.history.replaceState) { window.history.replaceState(null, '', window.location.href); } } catch (e) {}
        setTimeout(function(){
          document.querySelectorAll('.alert-dismissible, .alert').forEach(function(el){
            try { new bootstrap.Alert(el).close(); } catch(e) { try { el.style.display='none'; } catch(_) {} }
          });
        }, 5000);
      });
    </script>
