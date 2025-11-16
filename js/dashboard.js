// Dashboard Interactions
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Animate counters
    const animateCounter = (element) => {
        const target = Number.parseInt(element.textContent);
        let count = 0;
        const duration = 1000; // 1 second
        const increment = target / (duration / 16); // 60fps

        const animation = setInterval(() => {
            count += increment;
            if (count >= target) {
                element.textContent = target;
                clearInterval(animation);
            } else {
                element.textContent = Math.floor(count);
            }
        }, 16);
    };

    // Intersection Observer for counter animation
    const observerOptions = {
        threshold: 0.5
    };

    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                counterObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.counter').forEach(counter => {
        counterObserver.observe(counter);
    });

    // Handle responsive table
    const adjustTable = () => {
        const tables = document.querySelectorAll('.table-responsive table');
        tables.forEach(table => {
            const headerHeight = table.querySelector('thead').offsetHeight;
            table.style.setProperty('--header-height', `${headerHeight}px`);
        });
    };

    // Call on load and resize
    adjustTable();
    window.addEventListener('resize', adjustTable);

    // Add loading state to buttons
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.getAttribute('data-loading-text')) {
                const originalText = this.innerHTML;
                this.innerHTML = this.getAttribute('data-loading-text');
                this.classList.add('disabled');
                
                // Reset after 2 seconds (adjust based on your actual loading time)
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.classList.remove('disabled');
                }, 2000);
            }
        });
    });

    // Sidebar toggle for mobile
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        // Remove legacy floating toggle creation; we use header toggle only
        const legacyBtn = document.querySelector('.sidebar-toggle');
        if (legacyBtn) legacyBtn.remove();

        // Also place a button inside the fixed header on mobile
        const header = document.querySelector('.dashboard-header');
        let headerBtn = null;
        if (header) {
            // Remove any existing header toggles
            const existingToggle = header.querySelector('.header-toggle');
            if (existingToggle) existingToggle.remove();
            
            // Create new toggle button (hamburger menu)
            headerBtn = document.createElement('button');
            headerBtn.className = 'header-toggle';
            headerBtn.setAttribute('type', 'button');
            headerBtn.setAttribute('aria-label', 'Toggle sidebar');
            headerBtn.setAttribute('aria-expanded', 'false');
            headerBtn.innerHTML = '<i class="bi bi-list"></i>';
            // Insert at the beginning of header-content if it exists, otherwise at the beginning of header
            const headerContent = header.querySelector('.header-content');
            if (headerContent) {
                headerContent.insertBefore(headerBtn, headerContent.firstChild);
            } else {
                header.insertBefore(headerBtn, header.firstChild);
            }
            
            headerBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (window.innerWidth < 992) {
                    toggleSidebar();
                    headerBtn.setAttribute('aria-expanded', sidebar.classList.contains('show') ? 'true' : 'false');
                }
            });
        }

        // Create overlay
        let overlay = document.querySelector('#sidebar-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sidebar-overlay';
            overlay.style.position = 'fixed';
            overlay.style.inset = '0';
            overlay.style.background = 'rgba(0,0,0,0.5)';
            overlay.style.zIndex = '1030';
            overlay.style.display = 'none';
            document.body.appendChild(overlay);
        }

        const openSidebar = () => {
            sidebar.classList.add('show');
            document.body.classList.add('sidebar-open');
            overlay.style.display = 'block';
        };
        const closeSidebar = () => {
            sidebar.classList.remove('show');
            document.body.classList.remove('sidebar-open');
            overlay.style.display = 'none';
        };
        const toggleSidebar = () => {
            if (sidebar.classList.contains('show')) closeSidebar(); else openSidebar();
            if (headerBtn) {
                headerBtn.setAttribute('aria-expanded', sidebar.classList.contains('show') ? 'true' : 'false');
            }
        };

        // header toggle wired above
        overlay.addEventListener('click', closeSidebar);
        document.addEventListener('keyup', (e) => {
            if (e.key === 'Escape') closeSidebar();
        });

        // Close after clicking a nav link on mobile
        sidebar.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) closeSidebar();
            });
        });

        // Ensure correct state on resize
        const handleResize = () => {
            if (window.innerWidth >= 992) {
                overlay.style.display = 'none';
                document.body.classList.remove('sidebar-open');
                // Keep sidebar visible on desktop
                sidebar.classList.remove('show');
                if (headerBtn) headerBtn.setAttribute('aria-expanded', 'false');
            }
        };
        window.addEventListener('resize', handleResize);
        
        // Initialize on page load
        if (window.innerWidth < 992 && headerBtn) {
            headerBtn.style.display = 'inline-flex';
        } else if (window.innerWidth >= 992 && headerBtn) {
            headerBtn.style.display = 'none';
        }
    }

    // Handle notification badge updates
    const updateNotificationBadge = (count) => {
        const badge = document.querySelector('#notificationsDropdown .badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
    };

    // Example: Update notification count (you would typically call this when receiving new notifications)
    // updateNotificationBadge(5);
}); 