<?php
/**
 * Shared Styles for Donor Dashboard
 * Blood Donation Theme - Red and White Color Scheme
 */
?>
<style>
:root {
    --donor-primary: #DC143C;
    --donor-primary-dark: #B22222;
    --donor-primary-light: #FF6B6B;
    --donor-gradient: linear-gradient(135deg, #DC143C 0%, #B22222 50%, #8B0000 100%);
    --donor-accent: #FFE5E5;
    --donor-text: #2c3e50;
    --donor-bg: #FFF5F5;
}

body {
    background: linear-gradient(135deg, #FFF5F5 0%, #FFEBEE 50%, #FFF5F5 100%) !important;
    background-size: 400% 400% !important;
    animation: gradientShift 20s ease infinite !important;
    min-height: 100vh !important;
    background-attachment: fixed !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
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
    margin-left: 280px !important; /* Sidebar width - override dashboard.css */
    padding-top: 100px;
    position: relative;
    background: transparent !important;
}

.dashboard-header {
    background: linear-gradient(135deg, #DC143C 0%, #B22222 30%, #8B0000 100%) !important;
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 3px solid rgba(255, 255, 255, 0.3);
    position: fixed !important;
    top: 0 !important;
    left: 280px !important; /* Position right beside sidebar - override dashboard.css */
    right: 0 !important;
    z-index: 1021 !important;
    height: auto !important;
    box-shadow: 0 4px 20px rgba(220, 20, 60, 0.4), 0 2px 10px rgba(0, 0, 0, 0.2);
    padding: 1rem 1.5rem;
    overflow: visible;
    position: relative;
}

.dashboard-header::before {
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

.dashboard-header .header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    position: relative;
    z-index: 1;
}

.dashboard-header .page-title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #ffffff !important;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3), 0 4px 12px rgba(0, 0, 0, 0.2);
    position: relative;
    z-index: 1;
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
    background: rgba(255, 255, 255, 0.98) !important;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(220, 20, 60, 0.2) !important;
    border-radius: 12px !important;
    box-shadow: 0 8px 24px rgba(220, 20, 60, 0.2) !important;
    position: absolute !important;
    right: 0 !important;
    left: auto !important;
    top: 100% !important;
    margin-top: 0.5rem !important;
    z-index: 1050 !important;
    transform: none !important;
}

.dashboard-header .btn-outline-secondary {
    background: rgba(255, 255, 255, 0.2) !important;
    border: 2px solid rgba(255, 255, 255, 0.5) !important;
    color: #ffffff !important;
    border-radius: 10px !important;
    transition: all 0.3s ease !important;
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 1;
}

.dashboard-header .btn-outline-secondary:hover {
    background: rgba(255, 255, 255, 0.95) !important;
    border-color: rgba(255, 255, 255, 0.8) !important;
    color: var(--donor-primary) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 255, 255, 0.4) !important;
}

.dashboard-header .btn-outline-secondary i {
    color: #ffffff;
    transition: all 0.3s ease;
}

.dashboard-header .btn-outline-secondary:hover i {
    color: var(--donor-primary);
}

.dashboard-header .badge {
    background: rgba(255, 255, 255, 0.95) !important;
    color: var(--donor-primary) !important;
    border: 2px solid rgba(255, 255, 255, 0.5);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

/* Cards */
.card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 245, 245, 0.95) 100%) !important;
    backdrop-filter: blur(15px) saturate(180%);
    border: 2px solid rgba(220, 20, 60, 0.2) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(220, 20, 60, 0.15), 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.card:hover {
    transform: translateY(-4px) scale(1.01) !important;
    box-shadow: 0 12px 40px rgba(220, 20, 60, 0.25), 0 4px 16px rgba(0, 0, 0, 0.15) !important;
    border-color: rgba(220, 20, 60, 0.4) !important;
}

.card h3, .card h4, .card h5 {
    color: var(--donor-primary) !important;
    font-weight: 700 !important;
}

.card-header {
    background: linear-gradient(135deg, rgba(220, 20, 60, 0.05) 0%, rgba(178, 34, 34, 0.05) 100%) !important;
    border-bottom: 2px solid rgba(220, 20, 60, 0.2) !important;
    border-radius: 16px 16px 0 0 !important;
}

.card-title {
    color: var(--donor-primary) !important;
    font-weight: 700 !important;
}

.card-title i {
    color: var(--donor-primary) !important;
}

/* Buttons */
.btn-danger {
    background: var(--donor-gradient) !important;
    border: none !important;
    color: white !important;
    border-radius: 12px !important;
    font-weight: 600 !important;
    padding: 0.75rem 1.5rem !important;
    box-shadow: 0 4px 16px rgba(220, 20, 60, 0.35), 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    position: relative;
    overflow: hidden;
}

.btn-danger::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
}

.btn-danger:hover::before {
    left: 100%;
}

.btn-danger:hover {
    transform: translateY(-3px) scale(1.02) !important;
    box-shadow: 0 8px 24px rgba(220, 20, 60, 0.45), 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.btn-outline-danger {
    border: 2px solid var(--donor-primary) !important;
    color: var(--donor-primary) !important;
    background: rgba(255, 255, 255, 0.9) !important;
    backdrop-filter: blur(10px);
    border-radius: 12px !important;
    font-weight: 600 !important;
    padding: 0.75rem 1.5rem !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.btn-outline-danger:hover {
    background: var(--donor-gradient) !important;
    color: #ffffff !important;
    border-color: var(--donor-primary) !important;
    transform: translateY(-3px) scale(1.02) !important;
    box-shadow: 0 8px 24px rgba(220, 20, 60, 0.4), 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.btn-outline-secondary {
    border: 2px solid #64748b !important;
    color: #64748b !important;
    background: rgba(255, 255, 255, 0.9) !important;
    border-radius: 12px !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
}

.btn-outline-secondary:hover {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%) !important;
    color: #ffffff !important;
    border-color: #64748b !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(100, 116, 139, 0.4) !important;
}

.btn-primary {
    background: var(--donor-gradient) !important;
    border: none !important;
    color: white !important;
    border-radius: 12px !important;
    font-weight: 600 !important;
    padding: 0.75rem 1.5rem !important;
    box-shadow: 0 4px 16px rgba(220, 20, 60, 0.35) !important;
    transition: all 0.3s ease !important;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(220, 20, 60, 0.45) !important;
}

/* Tables */
.table {
    background: transparent !important;
}

.table thead th {
    background: linear-gradient(135deg, rgba(220, 20, 60, 0.1) 0%, rgba(178, 34, 34, 0.1) 100%) !important;
    color: var(--donor-primary) !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(220, 20, 60, 0.2) !important;
}

.table tbody tr {
    background: #ffffff !important;
    transition: all 0.3s ease !important;
}

.table tbody tr:hover {
    background: rgba(220, 20, 60, 0.05) !important;
    transform: translateX(4px);
}

.table tbody td {
    color: var(--donor-text) !important;
    border-color: rgba(220, 20, 60, 0.1) !important;
}

/* Form Elements */
.form-control, .form-select {
    border: 2px solid rgba(220, 20, 60, 0.2) !important;
    border-radius: 8px !important;
    padding: 0.75rem 1rem !important;
    transition: all 0.3s ease !important;
    background: rgba(255, 255, 255, 0.9) !important;
}

.form-control:focus, .form-select:focus {
    border-color: var(--donor-primary) !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 20, 60, 0.25) !important;
    background: #ffffff !important;
}

.form-label {
    color: var(--donor-text) !important;
    font-weight: 600 !important;
}

/* Alerts */
.alert-info {
    background: linear-gradient(135deg, rgba(220, 20, 60, 0.1) 0%, rgba(178, 34, 34, 0.1) 100%) !important;
    border: 2px solid rgba(220, 20, 60, 0.3) !important;
    border-radius: 12px !important;
    color: var(--donor-text) !important;
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%) !important;
    border: 2px solid rgba(16, 185, 129, 0.3) !important;
    border-radius: 12px !important;
    color: #059669 !important;
}

.alert-danger {
    background: linear-gradient(135deg, rgba(220, 20, 60, 0.1) 0%, rgba(178, 34, 34, 0.1) 100%) !important;
    border: 2px solid rgba(220, 20, 60, 0.3) !important;
    border-radius: 12px !important;
    color: var(--donor-primary) !important;
}

/* Icons */
.display-4, .display-5 {
    background: var(--donor-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    filter: drop-shadow(0 2px 4px rgba(220, 20, 60, 0.3));
}

.text-danger {
    color: var(--donor-primary) !important;
}

/* Badges */
.badge.bg-danger {
    background: var(--donor-gradient) !important;
    color: #ffffff !important;
}

/* Blood Type Badge */
.blood-type-badge {
    width: 120px;
    height: 120px;
    background: var(--donor-gradient);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 8px 32px rgba(220, 20, 60, 0.4), 0 4px 16px rgba(0, 0, 0, 0.2);
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

.blood-type-badge:hover::before {
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

.blood-type-badge:hover {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 12px 40px rgba(220, 20, 60, 0.5), 0 6px 20px rgba(0, 0, 0, 0.3);
}

.blood-type-badge span {
    font-size: 2rem;
    font-weight: 700;
    color: #ffffff;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    position: relative;
    z-index: 1;
}

/* Responsive */
@media (max-width: 991.98px) {
    .dashboard-content {
        margin-left: 0 !important;
        padding-top: 100px;
    }
    
    .dashboard-header {
        left: 0 !important; /* Full width on mobile - override dashboard.css */
        padding: 1rem;
    }
}

@media (max-width: 767.98px) {
    .dashboard-header .header-content {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .dashboard-header .page-title {
        font-size: 1.2rem;
    }
    
    .dashboard-header .header-actions {
        gap: 0.5rem;
    }
    
    .blood-type-badge {
        width: 100px;
        height: 100px;
    }
    
    .blood-type-badge span {
        font-size: 1.5rem;
    }
}
</style>
