/**
 * Universal Print Functions for Blood Bank Portal
 * Provides proper printable documents for all dashboard content
 */

// Universal print functions for different content types
function printDonationsReport() {
    const table = document.querySelector('.table-responsive table, table.table, table');
    if (!table) {
        alert('No donation data found to print.');
        return;
    }
    
    const title = 'Donations Report';
    const content = extractTableWithContext(table, 'Donation History');
    generatePrintDocument(title, content);
}

function printRequestsReport() {
    const table = document.querySelector('.table-responsive table, table.table, table');
    if (!table) {
        alert('No request data found to print.');
        return;
    }
    
    const title = 'Blood Requests Report';
    const content = extractTableWithContext(table, 'Blood Requests');
    generatePrintDocument(title, content);
}

function printAppointmentsReport() {
    const table = document.querySelector('.table-responsive table, table.table, table');
    if (!table) {
        alert('No appointment data found to print.');
        return;
    }
    
    const title = 'Appointments Report';
    const content = extractTableWithContext(table, 'Appointments Schedule');
    generatePrintDocument(title, content);
}

function printInventoryReport() {
    const tables = Array.from(document.querySelectorAll('.table-responsive table, table.table, table'));
    if (tables.length === 0) {
        alert('No inventory data found to print.');
        return;
    }
    
    const seen = new Set();
    let content = '<h2>Blood Inventory Report</h2>';
    tables.forEach((table) => {
        const headers = Array.from(table.querySelectorAll('thead th, tr th'))
            .map(th => (th.textContent||'').trim().toLowerCase())
            .filter(h => h && !h.includes('action'))
            .join('|');
        if (seen.has(headers)) return;
        seen.add(headers);
        content += extractTableContent(table);
        content += '<br>';
    });
    
    const title = 'Blood Inventory Report';
    generatePrintDocument(title, content);
}

function printBloodAvailability() {
    const tables = document.querySelectorAll('.table-responsive table, table.table, table');
    if (tables.length === 0) {
        alert('No blood availability data found to print.');
        return;
    }
    
    let content = '<h2>Blood Availability Report</h2>';
    tables.forEach((table, index) => {
        const tableTitle = table.closest('.card')?.querySelector('.card-title, h4, h5')?.textContent || `Blood Bank ${index + 1}`;
        content += `<h3>${tableTitle}</h3>`;
        content += extractTableContent(table);
        content += '<br>';
    });
    
    const title = 'Blood Availability Report';
    generatePrintDocument(title, content);
}

function printProfile() {
    const profileCards = document.querySelectorAll('.card');
    if (profileCards.length === 0) {
        alert('No profile data found to print.');
        return;
    }
    
    let content = '<h2>Profile Information</h2>';
    profileCards.forEach(card => {
        const cardTitle = card.querySelector('.card-title, h4, h5')?.textContent || 'Profile Section';
        const cardBody = card.querySelector('.card-body');
        
        if (cardBody) {
            content += `<h3>${cardTitle}</h3>`;
            content += extractProfileContent(cardBody);
            content += '<br>';
        }
    });
    
    const title = 'User Profile';
    generatePrintDocument(title, content);
}

function printReport() {
    const reportContent = document.querySelector('.card-body, .report-content, .main-content');
    if (!reportContent) {
        alert('No report data found to print.');
        return;
    }
    
    const title = document.querySelector('h1, h2, .card-title')?.textContent || 'Report';
    const content = extractReportContent(reportContent);
    generatePrintDocument(title, content);
}

// Helper functions for content extraction
function extractTableWithContext(table, contextTitle) {
    let content = `<h2>${contextTitle}</h2>`;
    
    // Add any summary info above the table
    const summaryCard = table.closest('.card')?.querySelector('.card-header');
    if (summaryCard) {
        const summaryText = summaryCard.textContent.trim();
        if (summaryText) {
            content += `<p><strong>Summary:</strong> ${summaryText}</p>`;
        }
    }
    
    content += extractTableContent(table);
    return content;
}

function extractTableContent(table) {
    const rows = Array.from(table.querySelectorAll('tr'));
    if (rows.length === 0) return '';

    // Determine which column(s) are actions by header label
    const header = rows.find(r => r.querySelector('th')) || rows[0];
    const headerCells = Array.from(header.querySelectorAll('th, td'));
    const actionIdx = new Set();
    headerCells.forEach((c, i) => {
        const t = (c.textContent || '').trim().toLowerCase();
        if (t.includes('action')) actionIdx.add(i);
    });

    let html = '<table border="1" style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
    rows.forEach((row) => {
        const cells = Array.from(row.querySelectorAll('th, td'));
        if (cells.length === 0) return;
        const isHeader = !!row.querySelector('th');
        const tag = isHeader ? 'th' : 'td';
        html += '<tr>';
        cells.forEach((cell, idx) => {
            if (actionIdx.has(idx)) return; // skip actions column
            if (cell.querySelector('.btn, .no-print, .dropdown')) return; // skip buttons
            let cellContent = (cell.textContent || '').trim();
            const badge = cell.querySelector('.badge');
            if (badge) cellContent = badge.textContent.trim();
            if (!cellContent) return;
            const style = isHeader
                ? 'padding: 12px; border: 1px solid #ddd; background-color: #f8f9fa; font-weight: bold;'
                : 'padding: 12px; border: 1px solid #ddd;';
            html += `<${tag} style="${style}">${cellContent}</${tag}>`;
        });
        html += '</tr>';
    });
    html += '</table>';
    return html;
}

function extractProfileContent(cardBody) {
    let content = '';
    
    // Extract form fields
    const formGroups = cardBody.querySelectorAll('.mb-3, .form-group, .row');
    formGroups.forEach(group => {
        const label = group.querySelector('label')?.textContent?.replace('*', '').trim();
        const input = group.querySelector('input, select, textarea');
        
        if (label && input) {
            const value = input.value || input.textContent || 'N/A';
            if (value && value !== 'N/A') {
                content += `<p><strong>${label}:</strong> ${value}</p>`;
            }
        }
    });
    
    // Extract direct text content
    const textElements = cardBody.querySelectorAll('p, .card-text');
    textElements.forEach(element => {
        const text = element.textContent.trim();
        if (text && !text.includes('Edit') && !text.includes('Update')) {
            content += `<p>${text}</p>`;
        }
    });
    
    return content;
}

function extractReportContent(reportElement) {
    let content = '';
    
    // Extract tables
    const tables = reportElement.querySelectorAll('table');
    tables.forEach(table => {
        content += extractTableContent(table);
    });
    
    // Extract charts (convert to text description)
    const charts = reportElement.querySelectorAll('canvas, .chart-container');
    charts.forEach((chart, index) => {
        const chartTitle = chart.closest('.card')?.querySelector('.card-title, h3, h4, h5')?.textContent || `Chart ${index + 1}`;
        content += `<h3>${chartTitle}</h3>`;
        content += '<p><em>Chart data would be displayed here in the original dashboard.</em></p>';
    });
    
    // Extract text content
    const textElements = reportElement.querySelectorAll('p, .alert, .card-text');
    textElements.forEach(element => {
        const text = element.textContent.trim();
        if (text && !element.querySelector('.btn')) {
            content += `<p>${text}</p>`;
        }
    });
    
    return content;
}

// Main print document generator
function generatePrintDocument(title, content) {
    if (!content || content.trim() === '') {
        alert('No content found to print.');
        return;
    }
    
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (!printWindow) {
        alert('Failed to open print window. Please check your popup blocker.');
        return;
    }
    
    const currentUser = getCurrentUser();
    const currentDate = new Date().toLocaleDateString();
    const currentTime = new Date().toLocaleTimeString();
    
    // Compute base to assets (handles /blood subfolder)
    const origin = window.location.origin || '';
    const parts = (window.location.pathname || '/').split('/').filter(Boolean);
    const basePrefix = parts.length ? '/' + parts[0] : '';
    const assetsBase = origin + basePrefix + '/assets/img';
    // Logos per organization
    const rcJpg = assetsBase + '/rclogo.jpg';
    const rcPng = assetsBase + '/rclgo.png';
    const nfPng = assetsBase + '/nflogo.png';
    const isNegrosFirst = (window.location.pathname || '').toLowerCase().includes('/negrosfirst/');
    const brandName = isNegrosFirst ? 'Negros First' : 'Philippine Red Cross';
    const printHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <meta charset="UTF-8">
            <style>
                @page {
                    size: A4;
                    margin: 1in;
                }
                
                @media print {
                    body { margin: 0; padding: 0; }
                    .no-print { display: none !important; }
                    .page-break { page-break-before: always; }
                    table { page-break-inside: auto; }
                    tr { page-break-inside: avoid; page-break-after: auto; }
                    thead { display: table-header-group; }
                    tfoot { display: table-footer-group; }
                }
                
                body {
                    font-family: 'Arial', sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background: white;
                    margin: 0;
                    padding: 20px;
                }
                
                .print-header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 3px solid #dc3545;
                }
                .print-brand {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 12px;
                    margin-bottom: 10px;
                }
                .print-brand img { height: 40px; }
                .print-brand span { font-weight: bold; color: #dc3545; font-size: 18px; }
                
                .print-header h1 {
                    margin: 0 0 10px 0;
                    color: #dc3545;
                    font-size: 28px;
                    font-weight: bold;
                }
                
                .print-meta {
                    color: #666;
                    font-size: 14px;
                    margin-top: 10px;
                }
                
                .print-footer {
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    text-align: center;
                    color: #666;
                    font-size: 12px;
                }
                
                h1, h2, h3, h4, h5, h6 {
                    color: #2c3e50;
                    margin-top: 25px;
                    margin-bottom: 15px;
                }
                
                h1 { font-size: 24px; }
                h2 { font-size: 20px; border-bottom: 2px solid #dc3545; padding-bottom: 5px; }
                h3 { font-size: 18px; color: #dc3545; }
                h4 { font-size: 16px; }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                    font-size: 14px;
                }
                
                th, td {
                    padding: 12px 8px;
                    text-align: left;
                    border: 1px solid #ddd;
                    vertical-align: top;
                }
                
                th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                    color: #2c3e50;
                }
                
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                
                p {
                    margin-bottom: 10px;
                }
                
                .badge {
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: bold;
                    color: white;
                    background-color: #6c757d;
                }
                
                .no-print {
                    margin-top: 40px;
                    text-align: center;
                    padding: 20px;
                    background-color: #f8f9fa;
                    border-radius: 8px;
                }
                
                .print-btn {
                    padding: 12px 24px;
                    margin: 0 10px;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: bold;
                }
                
                .btn-primary {
                    background-color: #dc3545;
                    color: white;
                }
                
                .btn-secondary {
                    background-color: #6c757d;
                    color: white;
                }
                
                .btn-primary:hover {
                    background-color: #c82333;
                }
                
                .btn-secondary:hover {
                    background-color: #5a6268;
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <div class="print-brand">
                    ${isNegrosFirst
                        ? `<img src="${nfPng}" alt="Negros First Logo" />`
                        : `<img src="${rcJpg}" alt="Red Cross Logo" onerror="this.onerror=null; this.src='${rcPng}'" />`
                    }
                    <span>${brandName}</span>
                </div>
                <h1>${title}</h1>
                <div class="print-meta">
                    <strong>Generated by:</strong> ${currentUser}<br>
                    <strong>Date:</strong> ${currentDate}<br>
                    <strong>Time:</strong> ${currentTime}<br>
                    <strong>System:</strong> Blood Bank Portal
                </div>
            </div>
            
            <div class="print-content">
                ${content}
            </div>
            
            <div class="print-footer">
                <p>This document was generated from the Blood Bank Portal system.</p>
                <p>© ${new Date().getFullYear()} Blood Bank Portal. All rights reserved.</p>
            </div>
            
            <div class="no-print">
                <button class="print-btn btn-primary" onclick="window.print()">
                    🖨️ Print This Document
                </button>
                <button class="print-btn btn-secondary" onclick="window.close()">
                    ❌ Close Window
                </button>
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.write(printHTML);
    printWindow.document.close();
    
    // Focus and attempt to print
    printWindow.onload = function() {
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
}

// Utility function to get current user
function getCurrentUser() {
    // Try to get user name from various sources
    const userElements = [
        document.querySelector('.user-name'),
        document.querySelector('.navbar-text'),
        document.querySelector('[data-user-name]'),
        document.querySelector('.welcome-text')
    ];
    
    for (let element of userElements) {
        if (element && element.textContent.trim()) {
            return element.textContent.trim();
        }
    }
    
    // Fallback to session storage or default
    return sessionStorage.getItem('userName') || 'System User';
}

// Initialize print functions when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Universal print functions loaded successfully');
    
    // Make functions globally available
    window.printDonationsReport = printDonationsReport;
    window.printRequestsReport = printRequestsReport;
    window.printAppointmentsReport = printAppointmentsReport;
    window.printInventoryReport = printInventoryReport;
    window.printBloodAvailability = printBloodAvailability;
    window.printProfile = printProfile;
    window.printReport = printReport;
    // Intercept window.print to route to smarter generator when a table is present
    const originalPrint = window.print.bind(window);
    window.print = function() {
        const table = document.querySelector('.table-responsive table, table.table, table');
        if (table) {
            // Try to infer the context from heading
            const heading = document.querySelector('h1, h2, .card-title');
            const title = heading ? heading.textContent.trim() : 'Report';
            const content = extractTableContent(table);
            return generatePrintDocument(title, content);
        }
        return originalPrint();
    }
});

// Export functions for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        printDonationsReport,
        printRequestsReport,
        printAppointmentsReport,
        printInventoryReport,
        printBloodAvailability,
        printProfile,
        printReport
    };
}
