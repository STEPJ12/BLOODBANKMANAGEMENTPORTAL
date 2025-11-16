<?php
/**
 * Shared Styles for Patient Dashboard
 * Common styles for all patient portal pages
 * Each page can override CSS variables for unique color themes
 */
?>
<style>
/* Base CSS Variables - Can be overridden per page */
:root {
    --patient-primary: #DC2626; /* Default: Red */
    --patient-primary-dark: #B91C1C;
    --patient-primary-light: #EF4444;
    --patient-accent: #F87171;
    --patient-accent-dark: #DC2626;
    --patient-accent-light: #FEE2E2;
    --patient-cream: #FEF2F2;
    --patient-cream-light: #FEE2E2;
    --patient-header-gradient: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
    --patient-bg-gradient: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
    --patient-text: #1F2937;
    --patient-text-muted: #4B5563;
}

/* Base Layout Styles */
body {
    min-height: 100vh;
    overflow-x: hidden;
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.dashboard-container {
    flex: 1;
    display: flex;
    min-height: 100vh;
    width: 100%;
    position: relative;
    overflow: hidden;
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
    margin-left: 300px;
    padding-top: 100px;
    position: relative;
    background: var(--patient-bg-gradient);
}

.dashboard-main {
    flex: 1;
    padding: 1.5rem;
    overflow-y: auto;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Dashboard Header - Structure (colors overridden per page) */
.dashboard-header {
    background: var(--patient-header-gradient) !important;
    color: white;
    border-bottom: none;
    position: fixed;
    top: 0;
    left: 300px;
    right: 0;
    z-index: 1021;
    height: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    padding: 1rem 1.5rem;
    overflow: visible;
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
    color: white !important;
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
}

/* Header Buttons - White theme */
.dashboard-header .btn-outline-secondary {
    border-color: white !important;
    color: white !important;
    background: rgba(255, 255, 255, 0.2) !important;
}

.dashboard-header .btn-outline-secondary:hover {
    border-color: white !important;
    color: white !important;
    background: rgba(255, 255, 255, 0.3) !important;
}

.dashboard-header .btn-outline-secondary span,
.dashboard-header .btn-outline-secondary i,
.dashboard-header .avatar i {
    color: white !important;
}

.dashboard-header .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Sidebar Styles */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    width: 300px;
    z-index: 1001;
}

/* Cards */
.card {
    background: white;
    border-radius: 12px;
    border: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

.card-header {
    background: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 12px 12px 0 0;
    padding: 1rem 1.5rem;
}

.card-title {
    color: var(--patient-text) !important;
    font-weight: 600;
    margin: 0;
}

.card-body {
    padding: 1.5rem;
}

.card h3, .card h4, .card h5 {
    color: var(--patient-text);
}

.card p {
    color: var(--patient-text-muted);
}

/* Buttons - Use page-specific primary color */
.btn-primary, .btn-primary-custom {
    background: linear-gradient(135deg, var(--patient-primary) 0%, var(--patient-primary-dark) 100%);
    border: none;
    color: white;
    font-weight: 600;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    transition: all 0.3s ease;
}

.btn-primary:hover, .btn-primary-custom:hover {
    background: linear-gradient(135deg, var(--patient-primary-dark) 0%, var(--patient-primary-dark) 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.btn-outline-primary {
    border: 2px solid var(--patient-primary);
    color: var(--patient-primary);
    background: transparent;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    transition: all 0.3s ease;
}

.btn-outline-primary:hover {
    background: var(--patient-primary);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, var(--patient-primary) 0%, var(--patient-primary-dark) 100%) !important;
    border: none;
    color: white;
    font-weight: 600;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    background: linear-gradient(135deg, var(--patient-primary-dark) 0%, var(--patient-primary-dark) 100%) !important;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.btn-outline-danger {
    border: 2px solid var(--patient-primary) !important;
    color: var(--patient-primary) !important;
    background: transparent;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    transition: all 0.3s ease;
}

.btn-outline-danger:hover {
    background: var(--patient-primary) !important;
    border-color: var(--patient-primary) !important;
    color: white !important;
}

/* Badges */
.badge.bg-primary,
.badge.bg-danger:not(.table .badge.bg-danger):not([data-critical="true"]) {
    background: linear-gradient(135deg, var(--patient-primary) 0%, var(--patient-primary-dark) 100%) !important;
    color: white;
}

.badge.bg-success {
    background: linear-gradient(135deg, var(--patient-primary) 0%, var(--patient-primary-dark) 100%);
    color: white;
}

.badge.bg-warning {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
    color: white;
}

/* Text Colors */
.text-primary {
    color: var(--patient-primary) !important;
}

.text-danger:not(table .text-danger):not(.table .text-danger):not([data-critical="true"]) {
    color: var(--patient-primary) !important;
}

.text-success {
    color: var(--patient-primary) !important;
}

.text-warning {
    color: #F59E0B !important;
}

.text-muted {
    color: var(--patient-text-muted) !important;
}

/* Tables */
.table {
    color: var(--patient-text);
    background: white;
}

.table th {
    color: var(--patient-text);
    font-weight: 600;
    border-bottom: 2px solid rgba(0, 0, 0, 0.1);
}

.table tbody tr {
    transition: all 0.2s ease;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.03);
}

/* Form Elements */
.form-control,
.form-select {
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--patient-primary);
    box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.1);
    outline: none;
}

.form-label {
    color: var(--patient-text);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

/* Alerts */
.alert-info {
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.05) 0%, rgba(0, 0, 0, 0.03) 100%);
    border-color: rgba(0, 0, 0, 0.1);
    color: var(--patient-text);
    border-radius: 8px;
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
    border-color: rgba(16, 185, 129, 0.3);
    color: #059669;
    border-radius: 8px;
}

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
    border-color: rgba(239, 68, 68, 0.3);
    color: #DC2626;
    border-radius: 8px;
}

/* Blood Type Badge */
.blood-type-badge {
    display: inline-block;
    padding: 1rem 2rem;
    background: var(--patient-header-gradient);
    color: white;
    border-radius: 12px;
    font-size: 1.5rem;
    font-weight: 700;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.blood-type-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

/* Hover Card Effect */
.hover-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

/* Stat Cards */
.stat-card {
    border-left: 4px solid var(--patient-accent);
    background: white;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    background: linear-gradient(135deg, var(--patient-accent) 0%, var(--patient-primary) 100%);
    color: white;
}

.stat-number {
    color: var(--patient-primary);
    font-weight: 700;
}

/* Modal */
.modal-header.bg-gradient-primary {
    background: var(--patient-header-gradient) !important;
    color: white;
}

/* Responsive Design */
@media (max-width: 991.98px) {
    .dashboard-content {
        margin-left: 0;
        padding-top: 100px;
    }
    
    .dashboard-header {
        left: 0;
        padding: 1rem;
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
    
    .card-body {
        padding: 1rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .card {
        margin: 0 0.25rem 1rem;
    }
    
    .blood-type-badge {
        padding: 0.75rem 1.5rem;
        font-size: 1.25rem;
    }
}
</style>

