/**
 * Print Utilities for Patient Dashboard
 * Provides comprehensive print functionality for all dashboard content
 */

// Global print functions that can be called directly
function printDashboard() {
    const content = getDashboardContent();
    printContent('Patient Dashboard', content);
}

function printStats() {
    const content = getStatsContent();
    printContent('Patient Statistics', content);
}

function printAnnouncements() {
    const content = getAnnouncementsContent();
    printContent('Announcements', content);
}

function printRequests() {
    console.log('printRequests function called');
    const content = getRequestsContent();
    console.log('Content for printing:', content);
    printContent('Blood Requests', content);
}

function printBloodAvailability() {
    const content = getBloodAvailabilityContent();
    printContent('Blood Availability Report', content);
}

function printProfile() {
    const content = getProfileContent();
    printContent('Patient Profile', content);
}

// Content extraction functions
function getDashboardContent() {
    const content = [];
    
    // Welcome section
    const welcomeCard = document.querySelector('.card.border-0.shadow-sm.mb-4');
    if (welcomeCard) {
        content.push(extractCardContent(welcomeCard));
    }

    // Stats cards
    const statsCards = document.querySelectorAll('.row.g-4 .card');
    if (statsCards.length > 0) {
        content.push('<h3>Statistics</h3>');
        statsCards.forEach(card => {
            content.push(extractCardContent(card));
        });
    }

    // Recent requests
    const requestsCard = document.querySelector('.col-md-8 .card');
    if (requestsCard) {
        content.push(extractCardContent(requestsCard));
    }

    return content.join('<hr>');
}

function getStatsContent() {
    const statsCards = document.querySelectorAll('.row.g-4 .card');
    if (statsCards.length === 0) return '';

    const content = ['<h2>Patient Statistics</h2>'];
    statsCards.forEach(card => {
        content.push(extractCardContent(card));
    });

    return content.join('<hr>');
}

function getAnnouncementsContent() {
    const announcements = document.querySelectorAll('.announcement-card');
    if (announcements.length === 0) return '';

    const content = ['<h2>Announcements</h2>'];
    announcements.forEach(announcement => {
        content.push(extractAnnouncementContent(announcement));
    });

    return content.join('<hr>');
}

function getRequestsContent() {
    console.log('getRequestsContent called');
    
    // Try multiple selectors to find the table
    let table = document.querySelector('.table-responsive table');
    if (!table) {
        table = document.querySelector('table.table');
        console.log('Found table with .table selector:', table);
    }
    
    if (!table) {
        table = document.querySelector('table');
        console.log('Found table with general selector:', table);
    }
    
    if (!table) {
        console.log('No table found, available elements:', document.querySelectorAll('table'));
        console.log('Available table-responsive elements:', document.querySelectorAll('.table-responsive'));
        return '<h2>Blood Requests</h2><p>No request data found to print.</p>';
    }

    console.log('Table found:', table);
    const content = '<h2>Blood Requests</h2>' + extractTableContent(table);
    console.log('Generated content:', content);
    return content;
}

function getBloodAvailabilityContent() {
    const inventoryTable = document.querySelector('.table-responsive table');
    const compatibilityTable = document.querySelector('.table-bordered');
    const contactInfo = document.querySelectorAll('.card-body .d-flex');
    
    let content = '<h2>Blood Inventory Status</h2>';
    
    if (inventoryTable) {
        content += extractTableContent(inventoryTable);
    }
    
    content += '<h2>Blood Type Compatibility</h2>';
    if (compatibilityTable) {
        content += extractTableContent(compatibilityTable);
    }
    
    content += '<h2>Blood Bank Contact Information</h2>';
    contactInfo.forEach(info => {
        const title = info.querySelector('h5')?.textContent || 'Blood Bank';
        const details = info.querySelectorAll('p');
        let detailsText = '';
        details.forEach(detail => {
            detailsText += detail.textContent + '<br>';
        });
        content += `<div class="announcement"><h4>${title}</h4><div>${detailsText}</div></div>`;
    });
    
    return content;
}

function getProfileContent() {
    const profileInfo = document.querySelector('.col-12.col-md-4 .card-body');
    const profileForm = document.querySelector('.col-12.col-md-8 .card-body');
    
    let content = '<h2>Patient Profile Information</h2>';
    
    if (profileInfo) {
        const name = profileInfo.querySelector('.card-title')?.textContent || 'N/A';
        const email = profileInfo.querySelector('.card-text')?.textContent || 'N/A';
        const phone = profileInfo.querySelectorAll('.card-text')[1]?.textContent || 'N/A';
        const bloodType = profileInfo.querySelector('.badge')?.textContent || 'N/A';
        
        content += `
            <div class="announcement">
                <h4>Personal Information</h4>
                <p><strong>Name:</strong> ${name}</p>
                <p><strong>Email:</strong> ${email}</p>
                <p><strong>Phone:</strong> ${phone}</p>
                <p><strong>Blood Type:</strong> ${bloodType}</p>
            </div>
        `;
    }
    
    if (profileForm) {
        const formFields = profileForm.querySelectorAll('.form-control, .form-select');
        content += '<h3>Detailed Profile Information</h3>';
        
        formFields.forEach(field => {
            const label = field.previousElementSibling?.textContent || 'Field';
            const value = field.value || 'N/A';
            if (label && value !== 'N/A') {
                content += `<p><strong>${label}:</strong> ${value}</p>`;
            }
        });
    }
    
    return content;
}

// Helper functions
function extractCardContent(card) {
    const title = card.querySelector('.card-title, h3, h4, h5')?.textContent || 'Card';
    const body = card.querySelector('.card-body')?.textContent || card.textContent;
    
    return `<h3>${title}</h3><div>${body}</div>`;
}

function extractAnnouncementContent(announcement) {
    const title = announcement.querySelector('.announcement-title')?.textContent || 'Announcement';
    const meta = announcement.querySelector('.announcement-meta')?.textContent || '';
    const content = announcement.querySelector('.announcement-content')?.textContent || '';
    const badges = announcement.querySelector('.announcement-badges')?.textContent || '';

    return `
        <div class="announcement">
            <h4>${title}</h4>
            <p><strong>Meta:</strong> ${meta}</p>
            <p><strong>Priority:</strong> ${badges}</p>
            <div>${content}</div>
        </div>
    `;
}

function extractTableContent(table) {
    const rows = Array.from(table.querySelectorAll('tr'));
    let html = '<table border="1" style="width: 100%; border-collapse: collapse;">';
    
    rows.forEach((row, index) => {
        const cells = Array.from(row.querySelectorAll('th, td'));
        const tag = index === 0 ? 'th' : 'td';
        
        html += '<tr>';
        cells.forEach(cell => {
            const cellContent = cell.textContent.trim();
            if (cellContent) {
                html += `<${tag} style="padding: 8px; border: 1px solid #ddd;">${cellContent}</${tag}>`;
            }
        });
        html += '</tr>';
    });
    
    html += '</table>';
    return html;
}

function getPatientName() {
    // Try to get patient name from various sources
    const welcomeText = document.querySelector('h3')?.textContent;
    if (welcomeText && welcomeText.includes('Welcome back,')) {
        return welcomeText.replace('Welcome back,', '').trim();
    }
    
    const profileName = document.querySelector('.patient-name')?.textContent;
    if (profileName) return profileName;
    
    return 'Patient';
}

// Main print function
function printContent(title, content) {
    console.log('printContent called with title:', title);
    console.log('Content length:', content ? content.length : 'undefined');
    
    if (!content || content.trim() === '') {
        console.error('No content to print');
        alert('No content found to print. Please try again.');
        return;
    }
    
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (!printWindow) {
        console.error('Failed to open print window');
        alert('Failed to open print window. Please check your popup blocker.');
        return;
    }
    
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                @media print {
                    body { margin: 0; padding: 20px; }
                    .no-print { display: none !important; }
                    table { page-break-inside: auto; }
                    tr { page-break-inside: avoid; page-break-after: auto; }
                    thead { display: table-header-group; }
                    tfoot { display: table-footer-group; }
                }
                
                /* Ensure content is visible */
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    max-width: 800px; 
                    margin: 0 auto; 
                    padding: 20px; 
                    background: white;
                }
                
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                
                h1, h2, h3, h4, h5, h6 {
                    color: #2c3e50;
                    margin-top: 20px;
                    margin-bottom: 10px;
                }
                
                h1 { font-size: 24px; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
                h2 { font-size: 20px; border-bottom: 1px solid #bdc3c7; padding-bottom: 5px; }
                h3 { font-size: 18px; }
                h4 { font-size: 16px; }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                
                th, td {
                    padding: 12px;
                    text-align: left;
                    border: 1px solid #ddd;
                }
                
                th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                    color: #2c3e50;
                }
                
                .badge {
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: bold;
                    color: white;
                }
                
                .bg-success { background-color: #28a745; }
                .bg-warning { background-color: #ffc107; color: #212529; }
                .bg-danger { background-color: #dc3545; }
                .bg-secondary { background-color: #6c757d; }
                
                hr {
                    border: none;
                    border-top: 1px solid #ddd;
                    margin: 30px 0;
                }
                
                .announcement {
                    margin-bottom: 30px;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    background-color: #f8f9fa;
                }
                
                .print-header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #3498db;
                }
                
                .print-header h1 {
                    margin: 0;
                    color: #2c3e50;
                }
                
                .print-meta {
                    color: #7f8c8d;
                    font-size: 14px;
                    margin-top: 10px;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin: 20px 0;
                }
                
                .stat-item {
                    text-align: center;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    background-color: #f8f9fa;
                }
                
                .stat-number {
                    font-size: 32px;
                    font-weight: bold;
                    color: #3498db;
                    margin-bottom: 10px;
                }
                
                .stat-label {
                    color: #7f8c8d;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>${title}</h1>
                <div class="print-meta">
                    <strong>Patient:</strong> ${getPatientName()}<br>
                    <strong>Date:</strong> ${new Date().toLocaleDateString()}<br>
                    <strong>Time:</strong> ${new Date().toLocaleTimeString()}
                </div>
            </div>
            
            ${content}
            
            <div class="no-print" style="margin-top: 40px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Print This Page
                </button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #95a5a6; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                    Close
                </button>
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Auto-print after content loads
    printWindow.onload = function() {
        printWindow.focus();
        // Try to print automatically
        setTimeout(() => {
            try {
                printWindow.print();
            } catch (e) {
                console.log('Auto-print failed, user can manually print');
            }
        }, 500);
    };
    
    // Fallback: if window doesn't load, show content in current window
    setTimeout(() => {
        if (printWindow.closed || !printWindow.document.body) {
            console.log('Print window failed, showing content in current window');
            document.body.innerHTML = printContent;
        }
    }, 2000);
}

// Test function for debugging
function testPrint() {
    console.log('=== TEST PRINT FUNCTION ===');
    console.log('Available tables:', document.querySelectorAll('table'));
    console.log('Table responsive elements:', document.querySelectorAll('.table-responsive'));
    console.log('All table elements:', document.querySelectorAll('table'));
    
    // Test the print function
    printRequests();
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Print utilities loaded successfully');
    console.log('Available print functions:', {
        printDashboard: typeof printDashboard,
        printStats: typeof printStats,
        printAnnouncements: typeof printAnnouncements,
        printRequests: typeof printRequests,
        printBloodAvailability: typeof printBloodAvailability,
        printProfile: typeof printProfile
    });
}); 