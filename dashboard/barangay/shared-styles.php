<?php
// Shared minimal & aesthetic blue dashboard theme styles for all barangay pages
// This file contains the common styling that should be included in all barangay dashboard pages
?>
<style>
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

/* Minimal & Aesthetic Light Dashboard Theme with Blue & Yellow Backgrounds */
body {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 20%, #fef3c7 40%, #fde68a 60%, #dbeafe 80%, #e0f2fe 100%) !important;
    color: #2a363b !important; /* Dark text for readability */
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height: 100vh !important;
    background-attachment: fixed !important; /* Keep background fixed on scroll */
}

.dashboard-container {
    background: transparent !important;
    color: #2a363b !important; /* Dark text */
    min-height: 100vh;
    position: relative;
}

.dashboard-container::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 30%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(234, 179, 8, 0.06) 0%, transparent 50%),
        radial-gradient(circle at 50% 50%, rgba(59, 130, 246, 0.04) 0%, transparent 50%);
    pointer-events: none;
    z-index: 0;
}

.dashboard-content {
    background: linear-gradient(135deg, rgba(239, 246, 255, 0.95) 0%, rgba(254, 243, 199, 0.9) 100%) !important;
    backdrop-filter: blur(10px) !important;
    color: #2a363b !important; /* Dark text */
    margin-left: 280px !important;
    padding-top: 120px !important; /* Space for fixed header */
    min-height: 100vh !important;
    position: relative;
    z-index: 1;
}

.dashboard-main {
    background: transparent !important;
    color: #2a363b !important; /* Dark text */
    position: relative;
    z-index: 1;
}

/* Minimal Sidebar Styling - Blue & Yellow Theme with Background */
.sidebar {
    background: linear-gradient(180deg, #3b82f6 0%, #2563eb 15%, #ffffff 20%, #ffffff 85%, #eab308 90%, #fbbf24 100%) !important;
    border-right: 2px solid rgba(59, 130, 246, 0.3) !important;
    box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15) !important;
    position: relative;
    overflow: hidden;
}

.sidebar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, transparent 50%),
        linear-gradient(225deg, rgba(234, 179, 8, 0.05) 0%, transparent 50%);
    pointer-events: none;
    z-index: 0;
}

.sidebar > * {
    position: relative;
    z-index: 1;
}

.sidebar-header {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important; /* Blue header */
    border-bottom: 1px solid rgba(59, 130, 246, 0.3) !important;
    padding: 1.5rem !important;
}

.sidebar-header h5 {
    color: #ffffff !important; /* White text in sidebar header */
    font-weight: 600 !important;
    font-size: 1.1rem !important;
    letter-spacing: 0.5px !important;
}

.sidebar-header small {
    color: rgba(255, 255, 255, 0.9) !important; /* Light white text in sidebar header */
}

.sidebar-logo {
    background: rgba(255, 255, 255, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

.sidebar-logo i {
    color: #ffffff !important; /* White icons in sidebar header */
}

/* Ensure all sidebar text is dark */
.sidebar,
.sidebar * {
    color: #2a363b !important; /* Dark text in sidebar */
}

.sidebar .nav-link i {
    color: inherit !important; /* Inherit dark from parent */
}

.sidebar-menu {
    padding: 1rem 0 !important;
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(10px) !important;
    margin: 0 0.5rem !important;
    border-radius: 12px !important;
}

.sidebar .nav-link {
    color: #2a363b !important; /* Dark text in sidebar */
    padding: 0.875rem 1.5rem !important;
    margin: 0.25rem 0.5rem !important;
    border-radius: 8px !important;
    transition: none !important;
    font-weight: 500 !important;
    font-size: 0.9rem !important;
    border: none !important;
    background: rgba(255, 255, 255, 0.6) !important;
    backdrop-filter: blur(5px) !important;
}

.sidebar .nav-link:hover {
    background: #eab308 !important; /* Yellow hover */
    color: #1e293b !important; /* Dark text on yellow */
    transform: translateX(4px) !important;
    box-shadow: 0 2px 8px rgba(234, 179, 8, 0.3) !important;
}

.sidebar .nav-link.active {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important; /* Blue for active */
    color: #ffffff !important; /* White text */
    font-weight: 600 !important;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4) !important;
}

.sidebar .nav-link i {
    color: inherit !important;
    width: 20px !important;
    margin-right: 0.75rem !important;
}

.sidebar-footer {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(234, 179, 8, 0.1) 100%) !important;
    backdrop-filter: blur(10px) !important;
    border-top: 2px solid rgba(59, 130, 246, 0.3) !important;
    padding: 1rem 1.5rem !important;
    margin: 0.5rem !important;
    border-radius: 12px !important;
}

.sidebar-footer .btn {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
    border: 1px solid rgba(59, 130, 246, 0.3) !important;
    color: #ffffff !important; /* White text in sidebar */
    font-weight: 500 !important;
    border-radius: 8px !important;
    padding: 0.75rem 1rem !important;
    transition: none !important;
    width: 100% !important;
}

.sidebar-footer .btn:hover {
    background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%) !important;
    border-color: #eab308 !important;
    color: #1e293b !important; /* Dark text on yellow */
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(234, 179, 8, 0.4) !important;
}

/* Sidebar Scrollbar */
.sidebar::-webkit-scrollbar {
    width: 4px !important;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05) !important;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(59, 130, 246, 0.5) !important;
    border-radius: 2px !important;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(251, 191, 36, 0.7) !important;
}

/* Enhanced Colorful Dashboard Header - Blue & Yellow Theme - Positioned beside sidebar */
.dashboard-header {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 30%, #eab308 70%, #fbbf24 100%) !important;
    backdrop-filter: blur(20px) saturate(180%) !important;
    border: none !important;
    border-radius: 0 20px 20px 0 !important;
    box-shadow: 
        0 12px 48px rgba(59, 130, 246, 0.3),
        0 4px 16px rgba(234, 179, 8, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
    position: fixed !important;
    top: 0 !important;
    left: 280px !important;
    right: 0 !important;
    height: auto !important;
    min-height: 100px !important;
    z-index: 1010 !important;
    overflow: hidden !important;
    padding: 1.5rem 2rem !important;
    margin: 0 !important;
}

.dashboard-header::before {
    content: '' !important;
    position: absolute !important;
    top: -50% !important;
    right: -50% !important;
    width: 200% !important;
    height: 200% !important;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%) !important;
}

.dashboard-header::after {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    background: 
        radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.1) 0%, transparent 50%) !important;
    pointer-events: none !important;
}

.header-content {
    position: relative !important;
    z-index: 2 !important;
}

.dashboard-header h1,
.dashboard-header .header-content h1,
.dashboard-header h2,
.dashboard-header h4 {
    color: white !important;
    font-weight: 800 !important;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2) !important;
    letter-spacing: -0.5px !important;
}

.dashboard-header .text-muted,
.dashboard-header small,
.dashboard-header .header-content .text-muted,
.dashboard-header .header-content small {
    color: rgba(255, 255, 255, 0.9) !important;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.2) !important;
    font-weight: 500 !important;
}

.status-indicator {
    background: rgba(255, 255, 255, 0.2) !important;
    backdrop-filter: blur(10px) !important;
    padding: 0.5rem 1rem !important;
    border-radius: 12px !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.status-indicator span {
    color: white !important;
    font-weight: 600 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
}

.status-dot {
    background: #10b981 !important;
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.6) !important;
    width: 8px !important;
    height: 8px !important;
}

.header-actions {
    position: relative !important;
    z-index: 1020 !important;
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
    gap: 0.75rem !important;
}

.user-dropdown {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(20px) saturate(180%) !important;
    border: 2px solid rgba(255, 255, 255, 0.5) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
}

.user-dropdown:hover {
    background: rgba(255, 255, 255, 1) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2) !important;
}

/* Breadcrumb styling in header */
.dashboard-header .breadcrumb {
    background: rgba(255, 255, 255, 0.2) !important;
    backdrop-filter: blur(10px) !important;
    padding: 0.5rem 1rem !important;
    border-radius: 12px !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    margin: 0 !important;
}

.dashboard-header .breadcrumb-item a {
    color: white !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
    font-weight: 500 !important;
}

.dashboard-header .breadcrumb-item.active {
    color: rgba(255, 255, 255, 0.8) !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
}

.dashboard-header .breadcrumb-item + .breadcrumb-item::before {
    color: rgba(255, 255, 255, 0.6) !important;
}

/* Card Styling */
.card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%) !important;
    backdrop-filter: blur(15px) saturate(180%) !important;
    color: #2a363b !important; /* Dark text */
    border: 2px solid rgba(59, 130, 246, 0.3) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(59, 130, 246, 0.15), 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    transition: none !important;
}

.card:hover {
    transform: translateY(-4px) scale(1.01) !important;
    box-shadow: 0 12px 40px rgba(234, 179, 8, 0.25), 0 4px 16px rgba(0, 0, 0, 0.15) !important;
    border-color: rgba(234, 179, 8, 0.6) !important;
    background: linear-gradient(135deg, rgba(255, 255, 255, 1) 0%, rgba(254, 243, 199, 0.95) 100%) !important;
}

.card-header {
    background: #f8f9fa !important; /* Light gray header */
    color: #2a363b !important; /* Dark text for visibility */
    border-bottom: 1px solid rgba(20, 184, 166, 0.2) !important;
    border-radius: 12px 12px 0 0 !important;
}

.card-header h4,
.card-header h5,
.card-header .card-title {
    color: #2a363b !important; /* Dark text for visibility */
}

.card-header p,
.card-header .text-muted {
    color: #64748b !important; /* Gray for muted text */
}

.card-body {
    background: transparent !important;
    color: #2a363b !important; /* Dark text */
}

.card-body *:not(.btn):not(.badge):not(.text-muted):not(.text-center):not(.empty-state-icon):not(.empty-state-icon *):not(.bg-white *):not(.card.bg-white *) {
    color: #2a363b !important; /* Dark text for all text */
    font-weight: 500 !important; /* Slightly bolder */
}

/* Special handling for text on white card backgrounds */
.card-body.bg-white *,
.card-body .bg-white *,
.card.bg-white .card-body * {
    color: #2a363b !important; /* Dark text on white backgrounds */
}

.card-body.bg-white .text-muted,
.card-body .bg-white .text-muted,
.card.bg-white .card-body .text-muted {
    color: #64748b !important; /* Gray for muted text on white */
}

.welcome-card {
    background: #ffffff !important; /* White welcome card */
    backdrop-filter: blur(10px) !important;
    color: #2a363b !important; /* Dark text */
    border: 1px solid rgba(59, 130, 246, 0.2) !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
}

.welcome-card h3 {
    color: #2a363b !important; /* Dark text for headings */
}

.welcome-card p {
    color: #64748b !important; /* Gray for paragraphs */
}

/* Stat Cards */
.stat-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%) !important;
    backdrop-filter: blur(20px) saturate(180%) !important;
    color: #2a363b !important; /* Dark text */
    border: 2px solid rgba(59, 130, 246, 0.3) !important;
    border-radius: 20px !important;
    box-shadow: 0 10px 40px rgba(59, 130, 246, 0.15), 0 2px 12px rgba(0, 0, 0, 0.1) !important;
    transition: none !important;
    position: relative;
    overflow: hidden;
}

.stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    transition: none !important;
}

.stat-card:hover::after {
    left: 100%;
}

.stat-card:hover {
    background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%) !important;
    color: #1e293b !important; /* Dark text on yellow */
    transform: translateY(-8px) scale(1.03) !important;
    box-shadow: 0 20px 60px rgba(234, 179, 8, 0.35), 0 8px 24px rgba(0, 0, 0, 0.15) !important;
    border-color: #eab308 !important;
}

.stat-number {
    color: #2a363b !important; /* Dark text for numbers */
    font-weight: 700 !important;
}

.stat-label {
    color: #64748b !important; /* Gray for labels */
    font-weight: 600 !important;
}

.stat-description {
    color: #94a3b8 !important; /* Lighter gray for descriptions */
}

.stat-card:hover .stat-number,
.stat-card:hover .stat-label,
.stat-card:hover .stat-description {
    color: #ffffff !important; /* White text on orange hover */
}

/* Table Styling */
.table {
    background: transparent !important;
    color: #2a363b !important; /* Dark text */
    border-radius: 12px !important;
    overflow: hidden !important;
}

.table thead th {
    background: #f8f9fa !important; /* Light gray header */
    color: #2a363b !important; /* Dark text for visibility */
    border-bottom: 1px solid rgba(20, 184, 166, 0.3) !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    font-size: 0.75rem !important;
    letter-spacing: 0.5px !important;
    padding: 1rem !important;
}

.table tbody tr {
    background: #ffffff !important; /* White background */
    color: #2a363b !important; /* Dark text for visibility */
    transition: none !important;
}

.table tbody tr.hover-row {
    background: #ffffff !important; /* White background */
}

.table tbody tr:nth-child(even) {
    background: #ffffff !important; /* White for all rows */
}

.table tbody tr:nth-child(odd) {
    background: #ffffff !important; /* White background for all rows */
}

/* Override any white backgrounds */
.table tbody tr[style*="background"],
.table tbody tr[style*="background-color"] {
    background: #ffffff !important; /* Force white background */
}

.table tbody tr:hover {
    background: rgba(234, 179, 8, 0.1) !important; /* Light yellow hover */
    color: #2a363b !important; /* Dark text */
    transform: scale(1.01) !important;
}

.table tbody td {
    background: transparent !important; /* Inherit from row */
    color: #2a363b !important; /* Dark text for visibility */
    border-color: rgba(20, 184, 166, 0.2) !important;
    font-weight: 500 !important; /* Slightly bolder for better visibility */
    padding: 1rem !important; /* Better spacing */
}

/* Override any white backgrounds on table cells */
.table tbody td[style*="background"],
.table tbody td[style*="background-color"] {
    background: transparent !important; /* Force transparent to inherit from row */
}

/* Force all table rows to be white - override any gray */
.table tbody tr[style*="background"],
.table tbody tr[style*="background-color"],
.table tbody tr.bg-light,
.table tbody tr.bg-gray,
.table tbody tr.bg-secondary {
    background: #ffffff !important;
    color: #2a363b !important;
}

.table tbody td * {
    color: #2a363b !important; /* Dark text for all text in table cells */
    font-weight: 500 !important; /* Slightly bolder */
}

.table tbody tr:hover td,
.table tbody tr:hover td * {
    color: #2a363b !important; /* Dark text on hover */
    font-weight: 600 !important; /* Bolder on hover */
}

/* Button Styling */
.btn {
    border-radius: 8px !important;
    font-weight: 500 !important;
    transition: none !important;
}

.btn-primary,
.btn-primary-custom {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%) !important;
    color: white !important;
    border: none !important;
    border-radius: 12px !important;
    font-weight: 600 !important;
    padding: 0.75rem 1.5rem !important;
    transition: none !important;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.35), 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    position: relative;
    overflow: hidden;
}

.btn-primary::before,
.btn-primary-custom::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: none !important;
}

.btn-primary:hover::before,
.btn-primary-custom:hover::before {
    width: 300px;
    height: 300px;
}

.btn-primary:hover,
.btn-primary-custom:hover {
    background: linear-gradient(135deg, #eab308 0%, #fbbf24 50%, #f59e0b 100%) !important;
    color: #1e293b !important;
    border: none !important;
    transform: translateY(-3px) scale(1.02) !important;
    box-shadow: 0 8px 24px rgba(234, 179, 8, 0.45), 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.btn-outline-primary {
    border: 2px solid #3b82f6 !important;
    color: #3b82f6 !important;
    background: rgba(255, 255, 255, 0.8) !important;
    backdrop-filter: blur(10px) !important;
    border-radius: 12px !important;
    font-weight: 600 !important;
    padding: 0.75rem 1.5rem !important;
    transition: none !important;
    position: relative;
    overflow: hidden;
}

.btn-outline-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.2), transparent);
    transition: none !important;
}

.btn-outline-primary:hover::before {
    left: 100%;
}

.btn-outline-primary:hover {
    background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%) !important;
    color: #1e293b !important;
    border-color: #eab308 !important;
    transform: translateY(-3px) scale(1.02) !important;
    box-shadow: 0 8px 24px rgba(234, 179, 8, 0.4), 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.btn-outline-success {
    border: 1.5px solid #10b981 !important;
    color: white !important;
    background: transparent !important;
}

.btn-outline-success:hover {
    background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%) !important;
    border-color: #eab308 !important;
    color: #1e293b !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 15px rgba(234, 179, 8, 0.4) !important;
}

.btn-outline-secondary {
    border: 1.5px solid rgba(59, 130, 246, 0.5) !important;
    color: #2a363b !important;
    background: transparent !important;
}

.btn-outline-secondary:hover {
    background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%) !important;
    border-color: #eab308 !important;
    color: #1e293b !important;
}

.btn-outline-danger {
    border: 1.5px solid #ef4444 !important;
    color: #ef4444 !important;
    background: transparent !important;
}

.btn-outline-danger:hover {
    background: #f97316 !important;
    border-color: #eab308 !important;
    color: white !important;
}

/* Navigation Links */
.nav-link {
    color: #2a363b !important;
    border-radius: 8px !important;
    transition: none !important;
}

.nav-link:hover {
    background: rgba(249, 115, 22, 0.1) !important;
    color: #2a363b !important;
}

.nav-link.active {
    background: rgba(20, 184, 166, 0.2) !important;
    color: #2a363b !important;
}

/* Dropdown Styling */
.dropdown {
    position: relative !important;
    z-index: 1020 !important;
}

.dropdown-menu {
    background: #ffffff !important; /* White dropdown */
    backdrop-filter: blur(10px) !important;
    color: #2a363b !important; /* Dark text */
    border: 1px solid rgba(59, 130, 246, 0.2) !important;
    border-radius: 12px !important;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15) !important;
    padding: 0.5rem 0 !important;
    z-index: 1050 !important;
    position: absolute !important;
    display: none !important; /* Hidden by default */
    visibility: hidden !important;
    opacity: 0 !important;
    min-width: 250px !important;
    transition: none !important;
}

/* Show dropdown menu only when it has the .show class (when clicked) */
.dropdown-menu.show {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.dropdown-menu * {
    color: #2a363b !important; /* Dark text for all dropdown text */
}

.dropdown-menu h5,
.dropdown-menu h6,
.dropdown-menu .dropdown-header {
    color: #2a363b !important; /* Dark text for headers */
}

.dropdown-item {
    color: #2a363b !important; /* Dark text */
    padding: 0.75rem 1.25rem !important;
    border-radius: 8px !important;
    margin: 0.25rem 0.5rem !important;
    transition: none !important;
}

.dropdown-item:hover {
    background: rgba(249, 115, 22, 0.1) !important;
    color: #2a363b !important; /* Dark text on light orange */
}

/* Notification dropdown specific styling */
.dropdown-menu .btn {
    color: #2a363b !important; /* Dark text on light buttons */
    background: #f8fafc !important; /* Light background for buttons */
    border: 1px solid rgba(59, 130, 246, 0.3) !important;
}

.dropdown-menu .btn:hover {
    background: #fbbf24 !important; /* Yellow on hover */
    color: #2a363b !important; /* Dark text */
}

.dropdown-menu .text-muted {
    color: #64748b !important; /* Gray for muted text in dark dropdowns */
}

.dropdown-item.text-danger {
    color: #ef4444 !important;
}

.dropdown-item.text-danger:hover {
    background: rgba(239, 68, 68, 0.3) !important;
    color: white !important;
}

.dropdown-divider {
    border-color: rgba(59, 130, 246, 0.3) !important;
    margin: 0.5rem 0 !important;
}

/* Badge Styling */
.badge {
    border-radius: 6px !important;
    padding: 0.375rem 0.75rem !important;
    font-weight: 500 !important;
    font-size: 0.75rem !important;
    color: white !important;
}

.badge.bg-primary {
    background: #2563eb !important;
}

.badge.bg-danger {
    background: #ef4444 !important;
}

.badge.bg-success {
    background: #10b981 !important;
}

.badge.bg-warning {
    background: #f59e0b !important;
}

/* Alert Styling */
.alert {
    background: #ffffff !important; /* White background */
    backdrop-filter: blur(10px) !important;
    color: #2a363b !important; /* Dark text for text */
    border: 1px solid rgba(59, 130, 246, 0.4) !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
}

.alert * {
    color: #2a363b !important; /* Ensure all text in alerts is visible */
}

.alert-success {
    border-left: 4px solid #10b981 !important;
    background: rgba(16, 185, 129, 0.2) !important; /* Green tint */
}

.alert-danger {
    border-left: 4px solid #ef4444 !important;
    background: rgba(239, 68, 68, 0.2) !important; /* Red tint */
}

.alert-warning {
    border-left: 4px solid #f59e0b !important;
    background: rgba(245, 158, 11, 0.2) !important; /* Orange tint */
}

.alert-info {
    border-left: 4px solid #3b82f6 !important;
    background: rgba(59, 130, 246, 0.2) !important; /* Blue tint */
}

.alert .btn-close {
    filter: invert(1) brightness(1.5) !important; /* Brighter close button */
}

.alert-soft {
    background: #f8f9fa !important; /* Light gray */
    backdrop-filter: blur(10px) !important;
    color: #2a363b !important; /* Dark text */
    border: 1px solid rgba(20, 184, 166, 0.3) !important;
    border-radius: 12px !important;
}

/* Form Elements */
.form-control,
.form-select {
    background: #ffffff !important; /* White background */
    color: #2a363b !important; /* Dark text */
    border: 1px solid rgba(20, 184, 166, 0.3) !important;
    border-radius: 8px !important;
    padding: 0.75rem 1rem !important;
    transition: none !important;
}

.form-control:focus,
.form-select:focus {
    background: #ffffff !important; /* White background on focus */
    color: #2a363b !important; /* Dark text */
    border-color: #eab308 !important;
    box-shadow: 0 0 0 3px rgba(234, 179, 8, 0.2) !important;
    outline: none !important;
}

.form-control::placeholder {
    color: #94a3b8 !important; /* Gray for placeholders */
}

.form-label {
    color: #2a363b !important; /* Dark text for labels */
    font-weight: 500 !important;
    margin-bottom: 0.5rem !important;
}

/* Modal Styling */
.modal-content {
    background: #ffffff !important; /* White modal */
    backdrop-filter: blur(20px) !important;
    color: #2a363b !important; /* Dark text */
    border: 1px solid rgba(20, 184, 166, 0.3) !important;
    border-radius: 16px !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2) !important;
}

.modal-header {
    background: #f8f9fa !important; /* Light gray header */
    color: #2a363b !important; /* Dark text */
    border-bottom: 1px solid rgba(20, 184, 166, 0.3) !important;
    border-radius: 16px 16px 0 0 !important;
}

.modal-header h5,
.modal-header .modal-title {
    color: #2a363b !important; /* Dark text for titles */
}

.modal-body {
    background: transparent !important;
    color: #2a363b !important; /* Dark text */
}

.modal-body * {
    color: #2a363b !important; /* Ensure all text is visible */
}

.modal-body h1,
.modal-body h2,
.modal-body h3,
.modal-body h4,
.modal-body h5,
.modal-body h6 {
    color: #2a363b !important; /* Dark text for headings */
}

.modal-body label {
    color: #2a363b !important; /* Dark text for labels */
}

.modal-footer {
    border-top: 1px solid rgba(59, 130, 246, 0.3) !important;
}

/* User Dropdown */
.user-dropdown {
    background: #ffffff !important; /* White dropdown */
    backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(20, 184, 166, 0.3) !important;
    border-radius: 12px !important;
    color: #2a363b !important; /* Dark text */
    transition: none !important;
}

.user-dropdown:hover {
    background: #f8f9fa !important; /* Light gray on hover */
    border-color: rgba(234, 179, 8, 0.5) !important;
}

.user-info span {
    color: #2a363b !important; /* Dark text for names */
}

.user-info small {
    color: #64748b !important; /* Gray for roles */
}

/* Status Indicator */
.status-indicator {
    color: #2a363b !important;
}

.status-indicator span {
    color: #2a363b !important; /* Dark text */
    font-weight: 500 !important; /* Bolder */
    text-transform: uppercase !important; /* Uppercase to match design */
    letter-spacing: 0.5px !important; /* Slight letter spacing */
}

.status-dot {
    background: #10b981 !important;
    width: 8px !important;
    height: 8px !important;
    border-radius: 50% !important;
    display: inline-block !important;
    margin-right: 0.5rem !important;
}

/* Text Colors - Better Visibility */
.text-muted {
    color: #94a3b8 !important; /* Softer gray for muted text */
}

.text-primary-custom {
    color: #2a363b !important; /* Dark text for primary text */
}

/* Body text - Make all text visible in main content area (not sidebar) */
.dashboard-content h1,
.dashboard-content h2,
.dashboard-content h3,
.dashboard-content h4,
.dashboard-content h5,
.dashboard-content h6 {
    color: #2a363b !important; /* Dark text for headings in body */
    font-weight: 600 !important; /* Bolder for headings */
}

.dashboard-content p,
.dashboard-content span:not(.badge):not(.btn *):not(.sidebar *),
.dashboard-content td,
.dashboard-content th,
.dashboard-content li,
.dashboard-content label,
.dashboard-content div:not(.badge):not(.btn):not(.sidebar *),
.dashboard-content a:not(.btn):not(.badge):not(.sidebar *) {
    color: #2a363b !important; /* Dark text for maximum visibility */
    font-weight: 500 !important; /* Slightly bolder for better readability */
}

.dashboard-content small {
    color: #cbd5e1 !important; /* Lighter gray for small text */
}

/* Ensure all text elements in body are visible (excluding sidebar) */
.dashboard-main h1,
.dashboard-main h2,
.dashboard-main h3,
.dashboard-main h4,
.dashboard-main h5,
.dashboard-main h6 {
    color: #2a363b !important; /* Dark text for headings */
    font-weight: 600 !important; /* Bolder for headings */
}

.dashboard-main p,
.dashboard-main span:not(.badge):not(.btn *),
.dashboard-main td,
.dashboard-main th,
.dashboard-main li,
.dashboard-main label,
.dashboard-main div:not(.badge):not(.btn),
.dashboard-main a:not(.btn):not(.badge) {
    color: #2a363b !important; /* Dark text for maximum visibility */
    font-weight: 500 !important; /* Slightly bolder for better readability */
}

.dashboard-main small {
    color: #cbd5e1 !important; /* Lighter gray for small text */
}

/* Global text colors for body (not sidebar) */
body:not(.sidebar) h1,
body:not(.sidebar) h2,
body:not(.sidebar) h3,
body:not(.sidebar) h4,
body:not(.sidebar) h5,
body:not(.sidebar) h6 {
    color: #2a363b !important; /* Dark text for headings */
    font-weight: 600 !important; /* Bolder for headings */
}

body:not(.sidebar) p,
body:not(.sidebar) span:not(.badge):not(.btn *):not(.sidebar *),
body:not(.sidebar) td,
body:not(.sidebar) th,
body:not(.sidebar) li,
body:not(.sidebar) label {
    color: #2a363b !important; /* Dark text for maximum visibility */
    font-weight: 500 !important; /* Slightly bolder for better readability */
}

/* Ensure table cells are visible in body */
.dashboard-content .table td,
.dashboard-content .table th,
.dashboard-main .table td,
.dashboard-main .table th {
    color: #2a363b !important; /* Dark text for maximum visibility */
    font-weight: 500 !important; /* Slightly bolder */
}

.dashboard-content .table tbody td,
.dashboard-content .table tbody td *,
.dashboard-main .table tbody td,
.dashboard-main .table tbody td * {
    color: #2a363b !important; /* Dark text for all table body text */
    font-weight: 500 !important; /* Slightly bolder */
}

.dashboard-content .table thead th,
.dashboard-main .table thead th {
    color: #2a363b !important; /* Dark text for headers */
}

/* Ensure links are visible in body (not sidebar) */
.dashboard-content a:not(.btn):not(.badge):not(.dropdown-item):not(.sidebar *),
.dashboard-main a:not(.btn):not(.badge):not(.dropdown-item):not(.sidebar *) {
    color: #60a5fa !important; /* Light blue for links - very visible */
    text-decoration: none !important;
}

.dashboard-content a:not(.btn):not(.badge):not(.dropdown-item):not(.sidebar *):hover,
.dashboard-main a:not(.btn):not(.badge):not(.dropdown-item):not(.sidebar *):hover {
    color: #fbbf24 !important; /* Yellow on hover */
    text-decoration: underline !important;
}

/* Ensure button text is visible */
.btn {
    color: #ffffff !important; /* White text on buttons */
}

.btn-outline-primary,
.btn-outline-success,
.btn-outline-secondary,
.btn-outline-danger {
    color: #2a363b !important; /* Dark text for outline buttons */
}

/* Ensure all text in cards is visible in body */
.dashboard-content .card-body *:not(.btn):not(.badge):not(h1):not(h2):not(h3):not(h4):not(h5):not(h6):not(.sidebar *):not(.text-center):not(.empty-state-icon):not(.empty-state-icon *),
.dashboard-main .card-body *:not(.btn):not(.badge):not(h1):not(h2):not(h3):not(h4):not(h5):not(h6):not(.sidebar *):not(.text-center):not(.empty-state-icon):not(.empty-state-icon *) {
    color: #2a363b !important; /* Dark text for maximum visibility */
    font-weight: 500 !important; /* Slightly bolder */
}

.dashboard-content .card-body h1,
.dashboard-content .card-body h2,
.dashboard-content .card-body h3,
.dashboard-content .card-body h4,
.dashboard-content .card-body h5,
.dashboard-content .card-body h6,
.dashboard-main .card-body h1,
.dashboard-main .card-body h2,
.dashboard-main .card-body h3,
.dashboard-main .card-body h4,
.dashboard-main .card-body h5,
.dashboard-main .card-body h6 {
    color: #2a363b !important; /* Dark text for headings */
    font-weight: 600 !important; /* Bolder for headings */
}

/* List Group Items */
.list-group-item {
    background: #f8f9fa !important; /* Light gray */
    border: 1px solid rgba(59, 130, 246, 0.3) !important;
    color: #2a363b !important; /* Dark text for maximum visibility */
    transition: none !important;
    margin-bottom: 0.5rem !important;
    border-radius: 8px !important;
    font-weight: 500 !important; /* Slightly bolder */
}

.list-group-item * {
    color: #2a363b !important; /* Dark text for all text */
    font-weight: 500 !important; /* Slightly bolder */
}

.list-group-item:hover {
    background: rgba(251, 191, 36, 0.3) !important; /* Yellow hover */
    border-color: rgba(251, 191, 36, 0.5) !important;
    color: #2a363b !important; /* Dark text on yellow */
    transform: translateX(4px) !important;
}

.list-group-item:hover * {
    color: #2a363b !important; /* Dark text on yellow */
}

.list-group-item.bg-light {
    background: rgba(45, 74, 107, 0.5) !important; /* Cooler blue */
}

.list-group-item h6 {
    color: #2a363b !important; /* Dark text for headings */
    font-weight: 600 !important; /* Bolder for headings */
}

.list-group-item p {
    color: #2a363b !important; /* Dark text for paragraphs */
    font-weight: 500 !important; /* Slightly bolder */
}

.list-group-item small {
    color: #2a363b !important; /* Dark text for small text */
    font-weight: 500 !important; /* Slightly bolder */
}

/* Pagination */
.pagination .page-link {
    background: rgba(30, 58, 138, 0.4) !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
    color: white !important;
}

.pagination .page-link:hover {
    background: rgba(251, 191, 36, 0.3) !important;
    border-color: #fbbf24 !important;
    color: white !important;
}

.pagination .page-item.active .page-link {
    background: #2563eb !important;
    border-color: #2563eb !important;
    color: white !important;
}

/* Responsive Design */
@media (max-width: 991.98px) {
    .dashboard-content {
        margin-left: 0 !important;
        width: 100% !important;
        padding-top: 100px !important;
    }
    
    .sidebar {
        transform: translateX(-100%) !important;
        transition: none !important;
    }
    
    .sidebar.show {
        transform: translateX(0) !important;
    }
    
    .dashboard-header {
        left: 0 !important;
        right: 0 !important;
        border-radius: 0 !important;
        padding: 1rem 1.5rem !important;
        min-height: 80px !important;
    }
    
    .dashboard-main {
        padding: 0.5rem !important;
    }
    
    .card {
        margin-bottom: 1rem !important;
    }
    
    .table-responsive {
        overflow-x: auto !important;
    }
}

@media (max-width: 768px) {
    .dashboard-content {
        padding-top: 90px !important;
    }
    
    .dashboard-header {
        left: 0 !important;
        right: 0 !important;
        padding: 1rem !important;
        min-height: 70px !important;
    }
    
    .dashboard-header h1,
    .dashboard-header h2 {
        font-size: 1.25rem !important;
    }
    
    .stat-card {
        margin-bottom: 1rem !important;
    }
    
    .btn {
        padding: 0.5rem 1rem !important;
        font-size: 0.875rem !important;
    }
    
    .table {
        font-size: 0.875rem !important;
    }
    
    .table thead th {
        padding: 0.75rem 0.5rem !important;
        font-size: 0.7rem !important;
    }
    
    .table tbody td {
        padding: 0.75rem 0.5rem !important;
    }
}

@media (max-width: 576px) {
    .dashboard-header {
        padding: 0.75rem !important;
    }
    
    .card-body {
        padding: 1rem !important;
    }
    
    .btn-sm {
        padding: 0.375rem 0.75rem !important;
        font-size: 0.8rem !important;
    }
}

/* No Transitions */
* {
    transition: none !important;
}

/* Remove default shadows for cleaner look */
.card,
.stat-card,
.welcome-card {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
}

/* Footer Styling - Ensure proper alignment and visibility */
footer {
    background: #1a2332 !important; /* Match dashboard background */
    color: #2a363b !important; /* Dark text */
    margin-top: auto !important;
    padding: 2rem 0 !important;
    border-top: 1px solid rgba(59, 130, 246, 0.3) !important;
}

footer h5 {
    color: #2a363b !important; /* Dark text for headings */
}

footer p,
footer li,
footer a:not(.btn) {
    color: #64748b !important; /* Gray for text */
}

footer a:not(.btn):hover {
    color: #fbbf24 !important; /* Yellow on hover */
}

footer .text-white-50 {
    color: #94a3b8 !important; /* Softer gray */
}

footer .text-white {
    color: #2a363b !important; /* Dark text */
}

/* Dashboard layout - Ensure footer doesn't affect alignment */
.dashboard-container {
    display: flex !important;
    flex-direction: column !important;
    min-height: 100vh !important;
}

.dashboard-content {
    flex: 1 !important;
    display: flex !important;
    flex-direction: column !important;
}

.dashboard-main {
    flex: 1 !important;
    padding-bottom: 2rem !important; /* Add bottom padding */
}

/* Ensure proper spacing at bottom of pages */
.dashboard-content > *:last-child {
    margin-bottom: 2rem !important;
}

/* Fix any alignment issues with content */
.container-fluid,
.container {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}

/* Ensure text in empty states is visible */
.empty-state-icon {
    color: #64748b !important; /* Darker gray for icons on white background */
}

.empty-state-icon i {
    color: #64748b !important; /* Darker gray for icons on white background */
    font-size: 4rem !important;
}

/* Empty state text on white/card backgrounds */
.card .text-center p,
.card .text-center h5,
.card .text-center h6 {
    color: #475569 !important; /* Dark gray for text on white/card backgrounds */
}

.card .text-muted {
    color: #64748b !important; /* Darker gray for muted text on white backgrounds */
}

/* Empty state in white cards */
.card-body .text-center .display-1,
.card-body .text-center h5,
.card-body .text-center p {
    color: #475569 !important; /* Dark gray for visibility on white */
}

/* Ensure all form text is visible */
.form-control option {
    background: #243447 !important;
    color: #2a363b !important; /* Dark text for options */
}

.form-select option {
    background: #243447 !important;
    color: #2a363b !important; /* Dark text for options */
}

/* Ensure all icons are visible */
i.bi,
[class*="bi-"] {
    color: inherit !important; /* Inherit color from parent */
}

/* Icons in body content */
.dashboard-content i.bi,
.dashboard-content [class*="bi-"],
.dashboard-main i.bi,
.dashboard-main [class*="bi-"] {
    color: #2a363b !important; /* Dark text for icons */
}

/* Icons in cards */
.card i.bi,
.card [class*="bi-"] {
    color: #2a363b !important; /* Dark text for icons in cards */
}

/* Icons in buttons */
.btn i.bi,
.btn [class*="bi-"] {
    color: inherit !important; /* Inherit button text color */
}

/* Icons in table */
.table i.bi,
.table [class*="bi-"] {
    color: #2a363b !important; /* Dark text for table icons */
}

/* Icons in empty states on white backgrounds */
.card-body .text-center i.bi,
.card-body .text-center [class*="bi-"] {
    color: #64748b !important; /* Darker gray for icons on white */
}

/* Ensure tooltip text is visible */
.tooltip .tooltip-inner {
    background: rgba(36, 52, 71, 0.95) !important;
    color: #f1f5f9 !important;
    border: 1px solid rgba(59, 130, 246, 0.3) !important;
}

/* Ensure popover text is visible */
.popover {
    background: rgba(36, 52, 71, 0.95) !important;
    color: #e2e8f0 !important;
    border: 1px solid rgba(59, 130, 246, 0.3) !important;
}

.popover-header {
    background: rgba(45, 74, 107, 0.8) !important;
    color: #f1f5f9 !important;
    border-bottom: 1px solid rgba(59, 130, 246, 0.3) !important;
}

.popover-body {
    color: #e2e8f0 !important;
}

/* FINAL OVERRIDE - Ensure ALL text in body is pure white and bold */
.dashboard-content *:not(.btn):not(.badge):not(.sidebar *):not(.text-center):not(.empty-state-icon):not(.empty-state-icon *):not(.bg-white *):not(.card.bg-white *):not(option) {
    color: #2a363b !important; /* Dark text for everything */
}

.dashboard-content td,
.dashboard-content th,
.dashboard-content p,
.dashboard-content span:not(.badge):not(.btn *),
.dashboard-content div:not(.badge):not(.btn):not(.sidebar *),
.dashboard-content li,
.dashboard-content label {
    color: #2a363b !important; /* Dark text */
    font-weight: 500 !important; /* Bolder */
}

.dashboard-content h1,
.dashboard-content h2,
.dashboard-content h3,
.dashboard-content h4,
.dashboard-content h5,
.dashboard-content h6 {
    color: #2a363b !important; /* Dark text */
    font-weight: 600 !important; /* Bolder */
}

/* Table text - maximum visibility */
.table tbody td,
.table tbody td *,
.table tbody th,
.table tbody th * {
    color: #2a363b !important; /* Dark text */
    font-weight: 500 !important; /* Bolder */
}

/* Force all nested elements in table cells to be visible */
.table tbody td h6,
.table tbody td h5,
.table tbody td h4,
.table tbody td h3,
.table tbody td h2,
.table tbody td h1,
.table tbody td div,
.table tbody td span,
.table tbody td small,
.table tbody td p,
.table tbody td strong,
.table tbody td b,
.table tbody td .fw-bold,
.table tbody td .text-muted,
.table tbody td .text-primary-custom,
.table tbody td .badge-blood-type,
.table tbody td .avatar-circle {
    color: #2a363b !important; /* Dark text */
    font-weight: 500 !important; /* Bolder */
}

/* Avatar circle text */
.table tbody td .avatar-circle {
    color: #2a363b !important; /* Dark text */
    font-weight: 600 !important; /* Bolder */
}

/* Badge blood type */
.table tbody td .badge-blood-type {
    color: #2a363b !important; /* Dark text */
    font-weight: 600 !important; /* Bolder */
    background: rgba(239, 68, 68, 0.3) !important; /* Red tint for visibility */
    border: 1px solid #ef4444 !important;
    padding: 0.25rem 0.5rem !important;
    border-radius: 4px !important;
}

/* Card text - maximum visibility */
.card-body *:not(.btn):not(.badge):not(.text-center):not(.empty-state-icon):not(.empty-state-icon *):not(.bg-white *):not(.card.bg-white *) {
    color: #2a363b !important; /* Dark text */
    font-weight: 500 !important; /* Bolder */
}

/* All icons in body - pure white */
.dashboard-content i,
.dashboard-content [class*="bi-"],
.dashboard-content svg {
    color: #2a363b !important; /* Dark text */
}

/* Small text - also make it visible */
.dashboard-content small,
.dashboard-content .text-muted:not(.bg-white *):not(.card.bg-white *) {
    color: #2a363b !important; /* Dark text */
    font-weight: 500 !important; /* Bolder */
    opacity: 1 !important; /* Fully opaque for maximum visibility */
}

/* Text primary custom - make it visible */
.dashboard-content .text-primary-custom,
.dashboard-content .text-primary-custom * {
    color: #2a363b !important; /* Dark text */
    font-weight: 600 !important; /* Bolder */
}

/* All bold text in tables */
.table tbody td .fw-bold,
.table tbody td .fw-semibold,
.table tbody td strong,
.table tbody td b {
    color: #2a363b !important; /* Dark text */
    font-weight: 600 !important; /* Bolder */
}

/* All headings in tables */
.table tbody td h1,
.table tbody td h2,
.table tbody td h3,
.table tbody td h4,
.table tbody td h5,
.table tbody td h6 {
    color: #2a363b !important; /* Dark text */
    font-weight: 600 !important; /* Bolder */
}

/* COMPREHENSIVE TABLE CELL OVERRIDE - Force all text to be visible */
.table tbody td,
.table tbody td *:not(.btn):not(.badge):not(.bg-warning):not(.bg-success):not(.bg-info):not(.bg-danger):not(.bg-secondary) {
    color: #2a363b !important; /* Dark text */
}

/* Override text-muted in table cells */
.table tbody td .text-muted,
.table tbody td small.text-muted,
.table tbody td .text-muted * {
    color: #2a363b !important; /* Dark text instead of muted */
    font-weight: 500 !important; /* Bolder */
    opacity: 1 !important; /* Fully opaque */
}

/* Override text-primary-custom in table cells */
.table tbody td .text-primary-custom,
.table tbody td .text-primary-custom * {
    color: #2a363b !important; /* Dark text */
    font-weight: 600 !important; /* Bolder */
}

/* All divs and spans in table cells */
.table tbody td div,
.table tbody td span:not(.badge):not(.btn) {
    color: #2a363b !important; /* Dark text */
    font-weight: 500 !important; /* Bolder */
}

/* Icons in table cells */
.table tbody td i,
.table tbody td .bi,
.table tbody td [class*="bi-"] {
    color: #2a363b !important; /* Dark text */
}

/* Avatar circle styling */
.table tbody td .avatar-circle {
    background: rgba(59, 130, 246, 0.3) !important;
    color: #2a363b !important; /* Dark text */
    font-weight: 700 !important; /* Very bold */
    border: 2px solid rgba(59, 130, 246, 0.5) !important;
}

/* CRITICAL: Override Bootstrap table-hover and any white backgrounds */
.table.table-hover tbody tr {
    background: rgba(36, 52, 71, 0.7) !important; /* Dark background always */
}

.table.table-hover tbody tr:hover {
    background: rgba(251, 191, 36, 0.3) !important; /* Yellow on hover */
}

/* Force table to have dark background always */
.table tbody {
    background: transparent !important;
}

/* Override any Bootstrap default white backgrounds */
.table tbody tr.bg-white,
.table tbody tr[class*="bg-"]:not([class*="bg-warning"]):not([class*="bg-success"]):not([class*="bg-info"]):not([class*="bg-danger"]):not([class*="bg-secondary"]) {
    background: rgba(36, 52, 71, 0.7) !important; /* Dark background */
}

/* Ensure table has dark background overall */
.table {
    background: transparent !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
}

/* Make sure table rows are always visible */
.table tbody tr:not(:hover) {
    background: rgba(36, 52, 71, 0.7) !important; /* Dark background when not hovering */
    color: #ffffff !important; /* White text */
}

.table tbody tr:not(:hover) td {
    color: #ffffff !important; /* White text in cells */
}

.table tbody tr:not(:hover) td * {
    color: #ffffff !important; /* White text in all nested elements */
}

/* Profile Page - Contact Information and Recent Activity */
/* Override bg-light to have dark background with visible text */
.bg-light,
.bg-light *,
[class*="bg-light"] {
    background: rgba(36, 52, 71, 0.6) !important; /* Dark background */
    color: #2a363b !important; /* Dark text text */
}

.bg-light .text-muted,
.bg-light small.text-muted,
.bg-light .text-muted * {
    color: #64748b !important; /* Gray for labels but still visible */
    font-weight: 500 !important; /* Bolder */
}

.bg-light .fw-medium,
.bg-light span.fw-medium,
.bg-light .fw-bold {
    color: #2a363b !important; /* Dark text for values */
    font-weight: 600 !important; /* Bolder */
}

/* Timeline items */
.timeline-item,
.timeline-item * {
    color: #2a363b !important; /* Dark text */
}

.timeline-item h6 {
    color: #2a363b !important; /* Dark text */
    font-weight: 600 !important; /* Bolder */
}

.timeline-item p {
    color: #2a363b !important; /* Dark text */
    font-weight: 500 !important; /* Bolder */
}

.timeline-item .text-muted,
.timeline-item small.text-muted {
    color: #64748b !important; /* Gray but visible */
    font-weight: 500 !important; /* Bolder */
}

.timeline-icon {
    background: rgba(36, 52, 71, 0.8) !important; /* Dark background */
    border: 2px solid rgba(59, 130, 246, 0.5) !important; /* Blue border */
}

.timeline-item {
    border-left-color: rgba(59, 130, 246, 0.5) !important; /* Blue border */
}

/* Form inputs visibility */
.form-control,
.form-control:focus {
    background: rgba(36, 52, 71, 0.6) !important; /* Dark background */
    color: #2a363b !important; /* Dark text text */
    border-color: rgba(59, 130, 246, 0.4) !important;
}

.form-control::placeholder {
    color: #94a3b8 !important; /* Light gray for placeholders */
    opacity: 1 !important; /* Fully visible */
}

textarea.form-control {
    background: rgba(36, 52, 71, 0.6) !important; /* Dark background */
    color: #2a363b !important; /* Dark text text */
}

/* Account Statistics */
.bg-primary.bg-opacity-10,
.bg-success.bg-opacity-10,
.bg-warning.bg-opacity-10 {
    background: rgba(36, 52, 71, 0.6) !important; /* Dark background */
}

.bg-primary.bg-opacity-10 *,
.bg-success.bg-opacity-10 *,
.bg-warning.bg-opacity-10 * {
    color: #2a363b !important; /* Dark text */
}

.bg-primary.bg-opacity-10 .text-muted,
.bg-success.bg-opacity-10 .text-muted,
.bg-warning.bg-opacity-10 .text-muted {
    color: #64748b !important; /* Gray but visible */
    font-weight: 500 !important; /* Bolder */
}

.bg-primary.bg-opacity-10 h3,
.bg-success.bg-opacity-10 h3,
.bg-warning.bg-opacity-10 h3 {
    color: #2a363b !important; /* Dark text */
    font-weight: 700 !important; /* Very bold */
}

/* Stat icons */
.stat-icon {
    color: #2a363b !important; /* Dark text */
}

.stat-icon i {
    color: #2a363b !important; /* Dark text */
    font-size: 2rem !important;
}

/* Avatar */
.avatar-lg {
    background: rgba(36, 52, 71, 0.6) !important; /* Dark background */
    border: 2px solid rgba(59, 130, 246, 0.5) !important; /* Blue border */
}

.avatar-lg i {
    color: #2a363b !important; /* Dark text */
}

/* Breadcrumb */
.breadcrumb,
.breadcrumb * {
    color: #2a363b !important; /* Dark text */
}

.breadcrumb-item.active {
    color: #64748b !important; /* Gray for active */
}

.breadcrumb-item a {
    color: #60a5fa !important; /* Light blue for links */
}

.breadcrumb-item a:hover {
    color: #fbbf24 !important; /* Yellow on hover */
}

/* Contact Information Icons */
.icon-wrapper i,
.bg-light .icon-wrapper i {
    color: #60a5fa !important; /* Light blue for icons */
    font-size: 1.25rem !important;
}

/* Profile card text */
.card-body.text-center h4,
.card-body.text-center p {
    color: #2a363b !important; /* Dark text */
}

.card-body.text-center .text-muted {
    color: #64748b !important; /* Gray but visible */
    font-weight: 500 !important; /* Bolder */
}

/* Ensure all card titles are visible */
.card-title,
.card-title * {
    color: #2a363b !important; /* Dark text */
    font-weight: 600 !important; /* Bolder */
}

/* Profile name and role */
.card-body h4,
.card-body p.text-muted {
    color: #2a363b !important; /* Dark text */
}

/* All spans and divs in cards */
.card-body span,
.card-body div:not(.btn):not(.badge) {
    color: #2a363b !important; /* Dark text */
}

/* Ensure all text in profile sections is visible */
.dashboard-content .card .card-body *:not(.btn):not(.badge):not(.text-center):not(.empty-state-icon):not(.empty-state-icon *) {
    color: #2a363b !important; /* Dark text */
    font-weight: 500 !important; /* Bolder */
}
</style>
