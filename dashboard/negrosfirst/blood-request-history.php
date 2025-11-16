<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

// Set page title
$pageTitle = "Blood Request History - Negros First";
$isDashboard = true; // Enable notification dropdown

// Get Negros First information
$negrosFirstId = $_SESSION['user_id'];

// Get all completed blood requests with patient statistics
// Using LEFT JOINs to ensure records are not filtered out if related data is missing
// Explicitly listing columns to avoid selecting non-existent columns
$completedRequests = executeQuery("
    SELECT 
        br.id,
        br.patient_id,
        br.blood_type,
        br.units_requested,
        br.urgency,
        br.organization_type,
        br.organization_id,
        br.required_date,
        br.required_time,
        br.reason,
        br.hospital,
        br.status,
        br.request_date,
        br.processed_date,
        br.fulfilled_date,
        br.notes,
        br.rejection_reason,
        br.barangay_id,
        br.has_blood_card,
        br.request_form_path,
        br.blood_card_path,
        br.referral_id,
        br.referral_status,
        br.created_at,
        br.updated_at,
        COALESCE(pu.name, 'Unknown Patient') as patient_name,
        COALESCE(pu.phone, '') as phone,
        COALESCE(pu.blood_type, br.blood_type) as patient_blood_type,
        b.name as barangay_name,
        r.referral_document_name,
        r.referral_document_type,
        r.referral_date,
        r.id as referral_id,
        (SELECT COUNT(*) FROM blood_requests br2 WHERE br2.patient_id = br.patient_id AND br2.organization_type = 'negrosfirst') as patient_total_requests,
        (SELECT COALESCE(SUM(units_requested), 0) FROM blood_requests br3 WHERE br3.patient_id = br.patient_id AND br3.organization_type = 'negrosfirst') as patient_total_units_requested,
        (SELECT COALESCE(SUM(units_requested), 0) FROM blood_requests br4 WHERE br4.patient_id = br.patient_id AND br4.organization_type = 'negrosfirst' AND br4.status IN ('Approved', 'Completed')) as patient_total_units_approved
    FROM blood_requests br
    LEFT JOIN patient_users pu ON br.patient_id = pu.id
    LEFT JOIN barangay_users b ON br.barangay_id = b.id
    LEFT JOIN referrals r ON br.id = r.blood_request_id
    WHERE br.organization_type = ?
      AND (br.status = ? OR LOWER(TRIM(br.status)) = ?)
    ORDER BY COALESCE(br.processed_date, br.request_date) DESC, br.id DESC
", ['negrosfirst', 'Completed', 'completed']);

// Final safety check - ensure it's always an array
if (!is_array($completedRequests)) {
    $completedRequests = [];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Custom CSS -->
    <?php
    $basePath = '../../';
    echo '<link rel="stylesheet" href="' . $basePath . 'assets/css/dashboard.css">';
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
</head>

<style>
    :root {
        --primary-color: #1a365d;
        --secondary-color: #2d3748;
        --accent-color: #e53e3e;
        --success-color: #38a169;
        --warning-color: #d69e2e;
        --info-color: #3182ce;
        --light-bg: #f7fafc;
        --white: #ffffff;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-600: #4b5563;
        --gray-800: #1f2937;
        --border-radius: 12px;
        --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --box-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .card { border: 1px solid var(--gray-200); border-radius: var(--border-radius); box-shadow: var(--box-shadow); }
    .card-header { border-bottom: 1px solid var(--gray-200); background: var(--white); }
    .table thead th { background: var(--gray-100); color: var(--gray-800); border-bottom: 2px solid var(--gray-200); }
    .badge { border-radius: 20px; padding: 0.35rem 0.6rem; }
    .modal-content { border-radius: var(--border-radius); box-shadow: var(--box-shadow-lg); }
    .modal-header, .modal-footer { background: var(--white); border-color: var(--gray-200); }
</style>

<body>

<div class="dashboard-container">
    <?php include_once '../../includes/sidebar.php'; ?>
    <div class="dashboard-content">
        <div class="dashboard-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h4 mb-0">Blood Request History</h2>
            </div>
        </div>

        <div class="dashboard-main p-3">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'success'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Completed Requests</h4>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="printHistoryReport()">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                        <button class="btn btn-sm btn-primary" id="exportHistoryCsv">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($completedRequests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="historyTable">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Patient Name</th>
                                        <th>Blood Type</th>
                                        <th>Units</th>
                                        <th>Request Date</th>
                                        <th>Completed Date</th>
                                        <th>Patient Total Requests</th>
                                        <th>Patient Total Units</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completedRequests as $request): ?>
                                        <tr>
                                            <td>#<?php echo str_pad($request['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                            <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                            <td><span class="badge bg-danger"><?php echo htmlspecialchars($request['blood_type']); ?></span></td>
                                            <td><?php echo $request['units_requested']; ?> unit(s)</td>
                                            <td><?php echo date('M d, Y', strtotime($request['request_date'] ?? $request['required_date'] ?? '')); ?></td>
                                            <td>
                                                <?php 
                                                $completedDate = $request['processed_date'] ?? '';
                                                echo $completedDate ? date('M d, Y', strtotime($completedDate)) : 'N/A';
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo (int)$request['patient_total_requests']; ?> request(s)</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo (int)$request['patient_total_units_requested']; ?> unit(s) requested</span><br>
                                                <small class="text-muted"><?php echo (int)$request['patient_total_units_approved']; ?> unit(s) approved</small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary view-history-btn"
                                                        data-request-id="<?php echo $request['id']; ?>"
                                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                                        data-phone="<?php echo htmlspecialchars($request['phone']); ?>"
                                                        data-blood-type="<?php echo htmlspecialchars($request['blood_type']); ?>"
                                                        data-units="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                                        data-request-date="<?php echo htmlspecialchars(date('M d, Y', strtotime($request['request_date'] ?? $request['required_date'] ?? ''))); ?>"
                                                        data-completed-date="<?php echo htmlspecialchars(($request['processed_date'] ?? '') ? date('M d, Y', strtotime($request['processed_date'])) : 'N/A'); ?>"
                                                        data-status="<?php echo htmlspecialchars($request['status']); ?>"
                                                        data-hospital="<?php echo htmlspecialchars($request['hospital'] ?? ''); ?>"
                                                        data-doctor="<?php echo htmlspecialchars($request['doctor_name'] ?? ''); ?>"
                                                        data-reason="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>"
                                                        data-request-form="<?php echo htmlspecialchars($request['request_form_path'] ?? ''); ?>"
                                                        data-blood-card="<?php echo htmlspecialchars($request['blood_card_path'] ?? ''); ?>"
                                                        data-patient-total-requests="<?php echo (int)$request['patient_total_requests']; ?>"
                                                        data-patient-total-units="<?php echo (int)$request['patient_total_units_requested']; ?>"
                                                        data-patient-total-approved="<?php echo (int)$request['patient_total_units_approved']; ?>">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clipboard-x fs-1 d-block mb-3 text-muted"></i>
                            <p class="text-muted">No completed blood requests found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Request Modal -->
<div class="modal fade" id="viewHistoryModal" tabindex="-1" aria-labelledby="viewHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewHistoryModalLabel">Blood Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewHistoryModalBody">
                <!-- Content will be injected by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // View details handler
    document.querySelectorAll('.view-history-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const requestId = this.getAttribute('data-request-id');
            const patientName = this.getAttribute('data-patient-name');
            const phone = this.getAttribute('data-phone');
            const bloodType = this.getAttribute('data-blood-type');
            const units = this.getAttribute('data-units');
            const requestDate = this.getAttribute('data-request-date');
            const completedDate = this.getAttribute('data-completed-date');
            const status = this.getAttribute('data-status');
            const hospital = this.getAttribute('data-hospital');
            const doctor = this.getAttribute('data-doctor');
            const reason = this.getAttribute('data-reason');
            const requestForm = this.getAttribute('data-request-form');
            const bloodCard = this.getAttribute('data-blood-card');
            const patientTotalRequests = this.getAttribute('data-patient-total-requests');
            const patientTotalUnits = this.getAttribute('data-patient-total-units');
            const patientTotalApproved = this.getAttribute('data-patient-total-approved');
            
            let html = `
                <div class="row mb-3">
                    <div class="col-md-6 mb-2"><strong>Request ID:</strong> #${String(requestId).padStart(5, '0')}</div>
                    <div class="col-md-6 mb-2"><strong>Status:</strong> <span class="badge bg-success">${status}</span></div>
                    <div class="col-md-6 mb-2"><strong>Patient Name:</strong> <span class="text-primary">${patientName}</span></div>
                    <div class="col-md-6 mb-2"><strong>Contact:</strong> <span class="text-muted">${phone}</span></div>
                    <div class="col-md-6 mb-2"><strong>Blood Type:</strong> <span class="badge bg-danger fs-6">${bloodType}</span></div>
                    <div class="col-md-6 mb-2"><strong>Units:</strong> <span class="fw-bold">${units}</span></div>
                    <div class="col-md-6 mb-2"><strong>Request Date:</strong> <span>${requestDate}</span></div>
                    <div class="col-md-6 mb-2"><strong>Completed Date:</strong> <span>${completedDate}</span></div>
                    <div class="col-md-6 mb-2"><strong>Hospital/Clinic:</strong> <span>${hospital || 'N/A'}</span></div>
                    <div class="col-md-6 mb-2"><strong>Doctor:</strong> <span>${doctor || 'N/A'}</span></div>
                    <div class="col-12 mb-3"><strong>Reason:</strong> <span>${reason || 'N/A'}</span></div>
                </div>
                
                <hr>
                
                <div class="mb-3">
                    <h6 class="border-bottom pb-2">Patient Request Statistics</h6>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <strong>Total Requests:</strong><br>
                            <span class="badge bg-info fs-6">${patientTotalRequests} request(s)</span>
                        </div>
                        <div class="col-md-4 mb-2">
                            <strong>Total Units Requested:</strong><br>
                            <span class="badge bg-primary fs-6">${patientTotalUnits} unit(s)</span>
                        </div>
                        <div class="col-md-4 mb-2">
                            <strong>Total Units Approved:</strong><br>
                            <span class="badge bg-success fs-6">${patientTotalApproved} unit(s)</span>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="mt-3"><strong>Attached Documents:</strong></div>
                <ul class="list-group list-group-flush mb-2">
                    <li class="list-group-item">
                        <strong>Hospital Request Form:</strong>
                        ${requestForm ? `<a href="view-request-form.php?request_id=${requestId}" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-eye me-1"></i>View File</a> <a href="download-request-form.php?request_id=${requestId}" class="btn btn-sm btn-outline-success ms-1"><i class="bi bi-download me-1"></i>Download</a>` : '<span class="text-muted ms-2">No file attached</span>'}
                    </li>
                    <li class="list-group-item">
                        <strong>Blood Card:</strong>
                        ${bloodCard ? `<a href="view-blood-card.php?request_id=${requestId}" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-eye me-1"></i>View File</a> <a href="download-blood-card.php?request_id=${requestId}" class="btn btn-sm btn-outline-success ms-1"><i class="bi bi-download me-1"></i>Download</a>` : '<span class="text-muted ms-2">No file attached</span>'}
                    </li>
                </ul>
            `;
            
            document.getElementById('viewHistoryModalBody').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('viewHistoryModal'));
            modal.show();
        });
    });
    
    // Export CSV
    const exportBtn = document.getElementById('exportHistoryCsv');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const table = document.getElementById('historyTable');
            if (!table) return;
            
            const headers = Array.from(table.querySelectorAll('thead th'))
                .map(th => '"' + (th.innerText || '').replace(/"/g, '""') + '"');
            
            const rows = Array.from(table.querySelectorAll('tbody tr'))
                .filter(tr => tr.style.display !== 'none')
                .map(tr => Array.from(tr.querySelectorAll('td'))
                .slice(0, 8) // Exclude Actions column
                .map(td => '"' + (td.innerText || '').replace(/"/g, '""') + '"')
                .join(','));
            
            const csv = [headers.join(','), ...rows].join('\r\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'blood_request_history_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }
});

function printHistoryReport() {
    const table = document.getElementById('historyTable');
    if (!table) {
        alert('No blood request history data found to print.');
        return;
    }
    
    // Get page title
    const pageTitle = 'Blood Request History';
    
    // Extract headers (excluding Actions column)
    const headers = Array.from(table.querySelectorAll('thead th'))
        .filter((th, index) => {
            const text = (th.textContent || '').trim().toLowerCase();
            return !text.includes('action');
        })
        .map(th => (th.textContent || '').trim());
    
    // Extract rows (excluding Actions column)
    const rows = Array.from(table.querySelectorAll('tbody tr'))
        .filter(tr => tr.style.display !== 'none')
        .map(tr => {
            const cells = Array.from(tr.querySelectorAll('td'))
                .filter((td, index) => {
                    // Check if corresponding header is Actions
                    const headerRow = table.querySelector('thead tr');
                    if (headerRow) {
                        const headerCells = Array.from(headerRow.querySelectorAll('th'));
                        if (headerCells[index]) {
                            const headerText = (headerCells[index].textContent || '').trim().toLowerCase();
                            return !headerText.includes('action');
                        }
                    }
                    return true;
                })
                .map(td => {
                    // Extract text from badges
                    const badges = td.querySelectorAll('.badge');
                    if (badges.length > 0) {
                        return Array.from(badges).map(b => b.textContent.trim()).join(', ');
                    }
                    return (td.textContent || '').trim();
                });
            return cells;
        });
    
    // Build table HTML
    let tableHTML = '<table style="width:100%; border-collapse:collapse; margin:20px 0; font-size:12px;">';
    
    // Headers
    tableHTML += '<thead><tr>';
    headers.forEach(header => {
        tableHTML += `<th style="background-color:#f8f9fa; padding:12px 8px; border:1px solid #ddd; font-weight:bold; text-align:left;">${header}</th>`;
    });
    tableHTML += '</tr></thead>';
    
    // Rows
    tableHTML += '<tbody>';
    rows.forEach((row, rowIndex) => {
        tableHTML += '<tr>';
        row.forEach((cell, index) => {
            // Style based on column type
            let cellStyle = 'padding:12px 8px; border:1px solid #ddd;';
            
            if (headers[index] && headers[index].toLowerCase().includes('blood type')) {
                cell = `<span style="background-color:#dc3545; color:#fff; padding:4px 8px; border-radius:4px; font-weight:bold;">${cell}</span>`;
            } else if (headers[index] && headers[index].toLowerCase().includes('total')) {
                cellStyle += 'font-weight:bold;';
            }
            
            tableHTML += `<td style="${cellStyle}">${cell || ''}</td>`;
        });
        tableHTML += '</tr>';
        
        // Add page break every 15 rows for better printing
        if ((rowIndex + 1) % 15 === 0 && rowIndex < rows.length - 1) {
            tableHTML += '<tr style="page-break-after:always;"><td colspan="' + headers.length + '"></td></tr>';
        }
    });
    tableHTML += '</tbody></table>';
    
    // Generate print document
    const content = `
        <div style="margin-bottom:20px;">
            <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Total Completed Requests:</strong> ${rows.length}</p>
        </div>
        ${tableHTML}
    `;
    
    generatePrintDocument(pageTitle, content);
}
</script>

<!-- Universal Print Script -->
<script src="../../assets/js/universal-print.js"></script>

</body>
</html>

