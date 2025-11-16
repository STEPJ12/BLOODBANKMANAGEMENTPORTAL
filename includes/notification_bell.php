<?php
// Reusable notification bell component for all pages
?>
<!-- Notification Bell -->
<div class="dropdown notification-bell me-2" style="flex-shrink: 0; order: -1;">
    <button class="btn notification-bell-btn position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell-fill"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge" style="display: none;">
            0
        </span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" id="notificationDropdownMenu" aria-labelledby="notificationDropdown">
        <li class="dropdown-header">
            <div class="d-flex justify-content-between align-items-center">
                <span>Notifications</span>
                <a href="notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <div class="notification-list" id="notificationList">
                <div class="text-center py-2">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </li>
    </ul>
</div>

<style>
/* Notification bell wrapper */
.notification-bell {
    position: relative !important;
    z-index: 1050 !important;
}

/* Notification dropdown positioning - prevent content overlap */
.notification-dropdown,
ul.notification-dropdown,
#notificationDropdownMenu,
.dropdown-menu.notification-dropdown {
    position: absolute !important;
    z-index: 9999 !important;
    right: 0 !important;
    left: auto !important;
    top: 100% !important;
    margin-top: 0.5rem !important;
    width: 280px !important;
    max-height: 400px !important;
    overflow-y: auto !important;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    border: 1px solid rgba(0, 0, 0, 0.15) !important;
    transform: none !important;
}

.notification-item {
    cursor: pointer;
    transition: background-color 0.2s;
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
}
.notification-item:hover {
    background-color: #f8f9fa !important;
}
.notification-item:last-child {
    border-bottom: none !important;
}
.notification-item h6 {
    font-size: 0.9rem;
    margin-bottom: 4px;
}
.notification-item p {
    font-size: 0.8rem;
    margin-bottom: 4px;
}
.notification-item small {
    font-size: 0.75rem;
}

/* Patient Pages - Notification Dropdown Text Colors */
.patient-notification-dropdown {
    background: white !important;
}

.patient-notification-dropdown .dropdown-header {
    color: #1F2937 !important;
    font-weight: 600 !important;
}

.patient-notification-dropdown .dropdown-header span {
    color: #1F2937 !important;
}

.patient-notification-dropdown .btn-outline-primary {
    border-color: #DC2626 !important;
    color: #DC2626 !important;
}

.patient-notification-dropdown .btn-outline-primary:hover {
    background-color: #DC2626 !important;
    color: white !important;
}

.patient-notification-dropdown .notification-item h6 {
    color: #1F2937 !important;
}

.patient-notification-dropdown .notification-item h6.fw-bold {
    color: #DC2626 !important;
}

.patient-notification-dropdown .notification-item p {
    color: #4B5563 !important;
}

.patient-notification-dropdown .notification-item small {
    color: #6B7280 !important;
}

.patient-notification-dropdown .text-muted {
    color: #6B7280 !important;
}

/* Ensure dropdown button is properly positioned */
#notificationDropdown {
    position: relative !important;
    z-index: 1021 !important;
}

/* Prevent dropdown from affecting layout */
.dropdown {
    position: relative !important;
}

/* Enhanced Notification Bell Button - Visible and Styled */
.notification-bell-btn {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(10px) saturate(180%);
    border: 2px solid rgba(255, 255, 255, 0.5) !important;
    color: #3b82f6 !important;
    padding: 0.625rem 0.875rem !important;
    border-radius: 12px !important;
    font-size: 1.25rem !important;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3), 0 2px 6px rgba(0, 0, 0, 0.1) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    min-width: 44px !important;
    height: 44px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.notification-bell-btn:hover {
    background: rgba(255, 255, 255, 1) !important;
    color: #eab308 !important;
    transform: translateY(-2px) scale(1.05) !important;
    box-shadow: 0 6px 20px rgba(234, 179, 8, 0.4), 0 4px 12px rgba(0, 0, 0, 0.15) !important;
    border-color: rgba(234, 179, 8, 0.5) !important;
}

.notification-bell-btn:active {
    transform: translateY(0) scale(0.98) !important;
}

.notification-bell-btn i {
    filter: drop-shadow(0 2px 4px rgba(59, 130, 246, 0.3));
    transition: all 0.3s ease !important;
}

.notification-bell-btn:hover i {
    transform: rotate(-10deg) scale(1.1);
    filter: drop-shadow(0 4px 8px rgba(234, 179, 8, 0.5));
}

/* Patient Dashboard - Red Theme Override for Notification Bell */
.dashboard-header .notification-bell .btn,
.dashboard-header .notification-bell .notification-bell-btn,
.dashboard-header #notificationDropdown {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: white !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
    padding: 0.625rem 1rem !important;
    border-radius: 12px !important;
    transition: all 0.3s ease !important;
}

.dashboard-header .notification-bell .btn:hover,
.dashboard-header .notification-bell .notification-bell-btn:hover,
.dashboard-header #notificationDropdown:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.4) !important;
    color: white !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
}

.dashboard-header .notification-bell .btn i,
.dashboard-header .notification-bell .notification-bell-btn i,
.dashboard-header #notificationDropdown i {
    color: white !important;
    filter: none !important;
}

.dashboard-header .notification-bell .btn:hover i,
.dashboard-header .notification-bell .notification-bell-btn:hover i,
.dashboard-header #notificationDropdown:hover i {
    color: white !important;
    transform: none !important;
    filter: none !important;
}

.dashboard-header .notification-bell .badge,
.dashboard-header #notificationBadge {
    background: #EF4444 !important;
    color: white !important;
}
</style>

<script>
// Notification system - reusable across all pages
function loadNotifications() {
    // Detect current role and use appropriate fetch endpoint
    const currentPath = window.location.pathname;
    let fetchUrl = 'notifications_fetch.php';
    
    // Determine role based on path
    if (currentPath.includes('/patient/')) {
        fetchUrl = 'notifications_fetch.php'; // Patient notifications
    } else if (currentPath.includes('/donor/')) {
        fetchUrl = 'notifications_fetch.php'; // Donor notifications
    } else if (currentPath.includes('/barangay/')) {
        fetchUrl = 'notifications_fetch.php'; // Barangay notifications (same directory)
    }
    
    fetch(fetchUrl)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error loading notifications:', data.error);
                return;
            }
            
            // Update notification badge
            const badge = document.getElementById('notificationBadge');
            if (data.unread > 0) {
                badge.textContent = data.unread;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
            
            // Update notification list
            const notificationList = document.getElementById('notificationList');
            if (data.items && data.items.length > 0) {
                let html = '';
                data.items.slice(0, 4).forEach(item => {
                    const isRead = item.is_read == 1;
                    const timeAgo = getTimeAgo(item.created_at);
                    html += `
                        <div class="notification-item ${isRead ? '' : 'bg-light'}" onclick="markAsRead(${item.id}, this)">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 ${isRead ? 'text-muted' : 'fw-bold'}">${item.title}</h6>
                                    <p class="text-muted mb-1">${item.message.length > 50 ? item.message.substring(0, 50) + '...' : item.message}</p>
                                    <small class="text-muted">${timeAgo}</small>
                                </div>
                                ${!isRead ? '<span class="badge bg-danger">New</span>' : ''}
                            </div>
                        </div>
                    `;
                });
                notificationList.innerHTML = html;
            } else {
                notificationList.innerHTML = `
                    <div class="text-center py-3">
                        <i class="bi bi-bell-slash text-muted"></i>
                        <p class="text-muted mb-0 small">No notifications</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

function getTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
    return Math.floor(diffInSeconds / 86400) + 'd ago';
}

function markAsRead(notificationId, element) {
    // Immediately update the visual state
    if (element) {
        // Remove the "New" badge
        const badge = element.querySelector('.badge');
        if (badge) {
            badge.remove();
        }
        
        // Update styling to show as read
        element.classList.remove('bg-light');
        const title = element.querySelector('h6');
        if (title) {
            title.classList.remove('fw-bold');
            title.classList.add('text-muted');
        }
    }
    
    // Update the notification count
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        const currentCount = parseInt(badge.textContent) || 0;
        if (currentCount > 1) {
            badge.textContent = currentCount - 1;
        } else {
            badge.style.display = 'none';
        }
    }
    
    // Send request to server
    fetch('../../api/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            notification_id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Optionally reload notifications after a short delay
            setTimeout(() => {
                loadNotifications();
            }, 1000);
        } else {
            // If server request failed, revert the visual changes
            if (element) {
                element.classList.add('bg-light');
                const title = element.querySelector('h6');
                if (title) {
                    title.classList.remove('text-muted');
                    title.classList.add('fw-bold');
                }
            }
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
        // Revert visual changes on error
        if (element) {
            element.classList.add('bg-light');
            const title = element.querySelector('h6');
            if (title) {
                title.classList.remove('text-muted');
                title.classList.add('fw-bold');
            }
        }
    });
}

// Load notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add patient class to dropdown if on patient pages
    const currentPath = window.location.pathname;
    const dropdown = document.getElementById('notificationDropdownMenu');
    if (currentPath.includes('/patient/') && dropdown) {
        dropdown.classList.add('patient-notification-dropdown');
    }
    
    loadNotifications();
    
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
});
</script>
