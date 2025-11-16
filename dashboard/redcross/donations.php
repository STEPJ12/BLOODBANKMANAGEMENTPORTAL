<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'redcross') {
    header("Location: ../../loginredcross.php?role=redcross");
    exit;
}

// Set page title
$pageTitle = "Donation History";

// Include database connection
require_once '../../config/db.php';
// Ensure universal print is available
echo '<script src="../../assets/js/universal-print.js"></script>';

// Get Red Cross information
$redcrossId = $_SESSION['user_id'];

// Process donation actions
$message = '';
$alertType = '';

// Success feedback via PRG pattern from session
if (isset($_SESSION['donation_message'])) {
    $message = $_SESSION['donation_message'];
    $alertType = $_SESSION['donation_message_type'] ?? 'success';
    unset($_SESSION['donation_message']);
    unset($_SESSION['donation_message_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['success'])) {
    // CSRF protection
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid form token. Please refresh the page and try again.';
        $alertType = 'danger';
    } else {
        if (isset($_POST['approve_donation'])) {
            $donationId = sanitize($_POST['donation_id']);
            
            // Check current status to prevent duplicate processing
            $donation = getRow("SELECT id, donor_id, status FROM blood_donations WHERE id = ? AND organization_type = 'redcross'", [$donationId]);
            
            if (!$donation) {
                $_SESSION['donation_message'] = 'Invalid donation.';
                $_SESSION['donation_message_type'] = 'danger';
                header('Location: donations.php?success=1');
                exit;
            } elseif (!empty($donation['status']) && strtolower(trim($donation['status'])) !== 'pending') {
                $_SESSION['donation_message'] = 'This donation has already been processed.';
                $_SESSION['donation_message_type'] = 'warning';
                header('Location: donations.php?success=1');
                exit;
            } else {
                $conn = getConnection();
                $stmt = $conn->prepare("
                    UPDATE blood_donations
                    SET status = 'Approved', processed_date = NOW(), notes = ?
                    WHERE id = ? AND organization_type = 'redcross'
                      AND (status IS NULL OR TRIM(LOWER(status)) = 'pending')
                ");
                $updateResult = $stmt->execute([normalize_input($_POST['notes'] ?? ''), $donationId]);
                $affectedRows = $stmt->rowCount();

                if ($updateResult !== false && $affectedRows > 0) {
                    // Get donor information for personalized message
                    $donorInfo = getRow("SELECT CONCAT(first_name, ' ', last_name) as name FROM donor_users WHERE id = ?", [$donation['donor_id']]);
                    $donorName = $donorInfo['name'] ?? '';
                    
                    // Use professional notification template
                    require_once '../../includes/notification_templates.php';
                    $approvalMessage = get_notification_message('appointment', $donorName, 'redcross', [
                        'status' => 'Scheduled'
                    ]);
                    $approvalMessage = format_notification_message($approvalMessage);
                    
                    executeQuery("
                        INSERT INTO notifications (
                            title, message, user_id, user_role, is_read, created_at
                        ) VALUES (?, ?, ?, 'donor', 0, NOW())
                    ", [
                        "Donation Appointment Approved",
                        $approvalMessage,
                        $donation['donor_id']
                    ]);

                    // Get donor phone number for SMS
                    $donorInfoPhone = getRow("SELECT phone FROM donor_users WHERE id = ?", [$donation['donor_id']]);
                    $donorPhone = $donorInfoPhone['phone'] ?? '';
                    
                    // Try to decrypt phone number if encrypted, but keep original if decryption fails (plain text)
                    if (!empty($donorPhone) && function_exists('decrypt_value')) {
                        $decryptedPhone = decrypt_value($donorPhone);
                        // If decryption returns null/empty, it means the phone is NOT encrypted (plain text)
                        // In that case, use the original value
                        if (!empty($decryptedPhone)) {
                            $donorPhone = $decryptedPhone;
                        }
                        // If decryption fails, $donorPhone already contains the original (plain text) value
                    }
                    
                    // Send SMS via SIM800C if phone number exists (skip enabled check for automated sends)
                    $smsSent = false;
                    $smsError = null;
                    try {
                        require_once '../../includes/sim800c_sms.php';
                        
                        error_log('[SIM800C] Approval SMS attempt - Donor ID: ' . $donation['donor_id'] . ', Phone: ' . (!empty($donorPhone) ? substr($donorPhone, 0, 4) . '****' : 'EMPTY'));
                        
                        // Check if phone number exists
                        if (!empty($donorPhone)) {
                            // Try to send SMS directly - skip enabled check for automated sends
                            // (enabled check may fail due to DB connection issues, but script still works)
                            error_log('[SIM800C] Attempting to send approval SMS (bypassing enabled check for automated action)...');
                            $smsResult = send_sms_sim800c($donorPhone, $approvalMessage);
                            
                            if ($smsResult['success']) {
                                $smsSent = true;
                                error_log('[SIM800C] Approval SMS sent successfully to ' . substr($donorPhone, 0, 4) . '****');
                            } else {
                                $smsError = $smsResult['error'] ?? 'Unknown error';
                                error_log('[SIM800C] Failed to send approval SMS to ' . substr($donorPhone, 0, 4) . '****: ' . $smsError);
                            }
                        } else {
                            $smsError = 'Donor phone number not found';
                            error_log('[SIM800C] Cannot send approval SMS - donor phone number not found for donor ID: ' . $donation['donor_id']);
                        }
                    } catch (Exception $ex) {
                        $smsError = $ex->getMessage();
                        error_log('[SIM800C_ERR] Exception in approval SMS: ' . $ex->getMessage() . ' | Trace: ' . $ex->getTraceAsString());
                    }

                    // Store success message in session and redirect (POST-redirect-GET pattern)
                    $successMsg = 'Donation appointment has been approved successfully.';
                    if ($smsSent) {
                        $successMsg .= ' SMS notification sent to donor.';
                    } elseif ($smsError && !empty($donorPhone)) {
                        $successMsg .= ' (Note: SMS notification could not be sent: ' . $smsError . ')';
                    }
                    $_SESSION['donation_message'] = $successMsg;
                    $_SESSION['donation_message_type'] = 'success';
                    header('Location: donations.php?success=1');
                    exit;
                } else {
                    $_SESSION['donation_message'] = 'This donation has already been processed or failed to approve.';
                    $_SESSION['donation_message_type'] = 'warning';
                    header('Location: donations.php?success=1');
                    exit;
                }
            }
        } elseif (isset($_POST['reject_donation'])) {
            $donationId = sanitize($_POST['donation_id']);
            
            // Check current status to prevent duplicate processing
            $donation = getRow("SELECT id, donor_id, status FROM blood_donations WHERE id = ? AND organization_type = 'redcross'", [$donationId]);
            
            if (!$donation) {
                $_SESSION['donation_message'] = 'Invalid donation.';
                $_SESSION['donation_message_type'] = 'danger';
                header('Location: donations.php?success=1');
                exit;
            } elseif (!empty($donation['status']) && strtolower(trim($donation['status'])) !== 'pending') {
                $_SESSION['donation_message'] = 'This donation has already been processed.';
                $_SESSION['donation_message_type'] = 'warning';
                header('Location: donations.php?success=1');
                exit;
            } else {
                $conn = getConnection();
                $stmt = $conn->prepare("
                    UPDATE blood_donations
                    SET status = 'Rejected', processed_date = NOW(), notes = ?
                    WHERE id = ? AND organization_type = 'redcross'
                      AND (status IS NULL OR TRIM(LOWER(status)) = 'pending')
                ");
                $updateResult = $stmt->execute([normalize_input($_POST['notes'] ?? ''), $donationId]);
                $affectedRows = $stmt->rowCount();

                if ($updateResult !== false && $affectedRows > 0) {
                    // Get donor information for personalized message
                    $donorInfo = getRow("SELECT CONCAT(first_name, ' ', last_name) as name FROM donor_users WHERE id = ?", [$donation['donor_id']]);
                    $donorName = $donorInfo['name'] ?? '';
                    
                    // Use professional notification template
                    require_once '../../includes/notification_templates.php';
                    $notesText = !empty($_POST['notes']) ? $_POST['notes'] : 'Please contact us for more information.';
                    $rejectionMessage = get_notification_message('rejected', $donorName, 'redcross', [
                        'type' => 'appointment',
                        'reason' => $notesText
                    ]);
                    $rejectionMessage = format_notification_message($rejectionMessage);
                    
                    executeQuery("
                        INSERT INTO notifications (
                            title, message, user_id, user_role, is_read, created_at
                        ) VALUES (?, ?, ?, 'donor', 0, NOW())
                    ", [
                        "Donation Appointment Rejected",
                        $rejectionMessage,
                        $donation['donor_id']
                    ]);

                    // Get donor phone number for SMS
                    $donorInfoPhone = getRow("SELECT phone FROM donor_users WHERE id = ?", [$donation['donor_id']]);
                    $donorPhone = $donorInfoPhone['phone'] ?? '';
                    
                    // Try to decrypt phone number if encrypted, but keep original if decryption fails (plain text)
                    if (!empty($donorPhone) && function_exists('decrypt_value')) {
                        $decryptedPhone = decrypt_value($donorPhone);
                        // If decryption returns null/empty, it means the phone is NOT encrypted (plain text)
                        // In that case, use the original value
                        if (!empty($decryptedPhone)) {
                            $donorPhone = $decryptedPhone;
                        }
                        // If decryption fails, $donorPhone already contains the original (plain text) value
                    }
                    
                    // Send SMS via SIM800C if phone number exists (skip enabled check for automated sends)
                    $smsSent = false;
                    $smsError = null;
                    try {
                        require_once '../../includes/sim800c_sms.php';
                        
                        error_log('[SIM800C] Rejection SMS attempt - Donor ID: ' . $donation['donor_id'] . ', Phone: ' . (!empty($donorPhone) ? substr($donorPhone, 0, 4) . '****' : 'EMPTY'));
                        
                        // Check if phone number exists
                        if (!empty($donorPhone)) {
                            // Try to send SMS directly - skip enabled check for automated sends
                            // (enabled check may fail due to DB connection issues, but script still works)
                            error_log('[SIM800C] Attempting to send rejection SMS (bypassing enabled check for automated action)...');
                            $smsResult = send_sms_sim800c($donorPhone, $rejectionMessage);
                            
                            if ($smsResult['success']) {
                                $smsSent = true;
                                error_log('[SIM800C] Rejection SMS sent successfully to ' . substr($donorPhone, 0, 4) . '****');
                            } else {
                                $smsError = $smsResult['error'] ?? 'Unknown error';
                                error_log('[SIM800C] Failed to send rejection SMS to ' . substr($donorPhone, 0, 4) . '****: ' . $smsError);
                            }
                        } else {
                            $smsError = 'Donor phone number not found';
                            error_log('[SIM800C] Cannot send rejection SMS - donor phone number not found for donor ID: ' . $donation['donor_id']);
                        }
                    } catch (Exception $ex) {
                        $smsError = $ex->getMessage();
                        error_log('[SIM800C_ERR] Exception in rejection SMS: ' . $ex->getMessage() . ' | Trace: ' . $ex->getTraceAsString());
                    }

                    // Store success message in session and redirect (POST-redirect-GET pattern)
                    $successMsg = 'Donation appointment has been rejected.';
                    if ($smsSent) {
                        $successMsg .= ' SMS notification sent to donor.';
                    } elseif ($smsError && !empty($donorPhone)) {
                        $successMsg .= ' (Note: SMS notification could not be sent: ' . $smsError . ')';
                    }
                    $_SESSION['donation_message'] = $successMsg;
                    $_SESSION['donation_message_type'] = 'success';
                    header('Location: donations.php?success=1');
                    exit;
                } else {
                    $_SESSION['donation_message'] = 'This donation has already been processed or failed to reject.';
                    $_SESSION['donation_message_type'] = 'warning';
                    header('Location: donations.php?success=1');
                    exit;
                }
            }
        } elseif (isset($_POST['complete_donation'])) {
            $donationId = sanitize($_POST['donation_id']);
            $bloodType = sanitize($_POST['blood_type']);
            $units = validate_units($_POST['units']);
            $hemoglobin = sanitize($_POST['hemoglobin']);
            $bloodPressure = sanitize($_POST['blood_pressure']);
            $pulse = sanitize($_POST['pulse']);
            $temperature = sanitize($_POST['temperature']);
            $weight = sanitize($_POST['weight']);

            // Check current status to prevent duplicate processing
            $donation = getRow("SELECT id, donor_id, status FROM blood_donations WHERE id = ? AND organization_type = 'redcross'", [$donationId]);
            
            if (!$donation) {
                $_SESSION['donation_message'] = 'Invalid donation.';
                $_SESSION['donation_message_type'] = 'danger';
                header('Location: donations.php?success=1');
                exit;
            } elseif (!empty($donation['status']) && strtolower(trim($donation['status'])) === 'completed') {
                $_SESSION['donation_message'] = 'This donation has already been completed.';
                $_SESSION['donation_message_type'] = 'warning';
                header('Location: donations.php?success=1');
                exit;
            } else {
                // Begin transaction for completing donation
                beginTransaction();
                try {
                    $conn = getConnection();
                    $stmt = $conn->prepare("
                        UPDATE blood_donations
                        SET
                            status = 'Completed',
                            completed_date = NOW(),
                            units_collected = ?,
                            hemoglobin = ?,
                            blood_pressure = ?,
                            pulse = ?,
                            temperature = ?,
                            weight = ?,
                            notes = ?
                        WHERE id = ? AND organization_type = 'redcross'
                          AND (status IS NULL OR TRIM(LOWER(status)) != 'completed')
                    ");
                    $updateResult = $stmt->execute([
                        $units, $hemoglobin, $bloodPressure, $pulse, $temperature, $weight,
                        normalize_input($_POST['notes'] ?? ''), $donationId
                    ]);
                    $affectedRows = $stmt->rowCount();

                    if ($updateResult === false || $affectedRows == 0) {
                        throw new Exception('Donation has already been completed or failed to update.');
                    }

                    // Add to blood inventory with 35-day expiry
                    executeQuery("
                        INSERT INTO blood_inventory (
                            blood_type, units, status,
                            organization_type, organization_id, source, expiry_date, created_at
                        ) VALUES (?, ?, 'Available', 'redcross', ?, 'Donation', DATE_ADD(NOW(), INTERVAL 35 DAY), NOW())
                    ", [
                        $bloodType, $units, $redcrossId
                    ]);

                    // Get donor information for personalized message
                    $donorInfo = getRow("SELECT CONCAT(first_name, ' ', last_name) as name FROM donor_users WHERE id = ?", [$donation['donor_id']]);
                    $donorName = $donorInfo['name'] ?? '';
                    
                    // Use professional notification template
                    require_once '../../includes/notification_templates.php';
                    $completionMessage = get_notification_message('completed', $donorName, 'redcross', [
                        'type' => 'donation',
                        'date' => date('Y-m-d'),
                        'blood_type' => $bloodType ?? '',
                        'units' => $units ?? ''
                    ]);
                    $completionMessage = format_notification_message($completionMessage);
                    
                    executeQuery("
                        INSERT INTO notifications (
                            title, message, user_id, user_role, is_read, created_at
                        ) VALUES (?, ?, ?, 'donor', 0, NOW())
                    ", [
                        "Donation Completed",
                        $completionMessage,
                        $donation['donor_id']
                    ]);

                    // Get donor phone number for SMS
                    $donorInfoPhone = getRow("SELECT phone FROM donor_users WHERE id = ?", [$donation['donor_id']]);
                    $donorPhone = $donorInfoPhone['phone'] ?? '';
                    
                    // Try to decrypt phone number if encrypted, but keep original if decryption fails (plain text)
                    if (!empty($donorPhone) && function_exists('decrypt_value')) {
                        $decryptedPhone = decrypt_value($donorPhone);
                        // If decryption returns null/empty, it means the phone is NOT encrypted (plain text)
                        // In that case, use the original value
                        if (!empty($decryptedPhone)) {
                            $donorPhone = $decryptedPhone;
                        }
                        // If decryption fails, $donorPhone already contains the original (plain text) value
                    }
                    
                    // Send SMS via SIM800C if phone number exists (skip enabled check for automated sends)
                    $smsSent = false;
                    $smsError = null;
                    try {
                        require_once '../../includes/sim800c_sms.php';
                        
                        error_log('[SIM800C] Completion SMS attempt - Donor ID: ' . $donation['donor_id'] . ', Phone: ' . (!empty($donorPhone) ? substr($donorPhone, 0, 4) . '****' : 'EMPTY'));
                        
                        // Check if phone number exists
                        if (!empty($donorPhone)) {
                            // Try to send SMS directly - skip enabled check for automated sends
                            // (enabled check may fail due to DB connection issues, but script still works)
                            error_log('[SIM800C] Attempting to send completion SMS (bypassing enabled check for automated action)...');
                            $smsResult = send_sms_sim800c($donorPhone, $completionMessage);
                            
                            if ($smsResult['success']) {
                                $smsSent = true;
                                error_log('[SIM800C] Completion SMS sent successfully to ' . substr($donorPhone, 0, 4) . '****');
                            } else {
                                $smsError = $smsResult['error'] ?? 'Unknown error';
                                error_log('[SIM800C] Failed to send completion SMS to ' . substr($donorPhone, 0, 4) . '****: ' . $smsError);
                            }
                        } else {
                            $smsError = 'Donor phone number not found';
                            error_log('[SIM800C] Cannot send completion SMS - donor phone number not found for donor ID: ' . $donation['donor_id']);
                        }
                    } catch (Exception $ex) {
                        $smsError = $ex->getMessage();
                        error_log('[SIM800C_ERR] Exception in completion SMS: ' . $ex->getMessage() . ' | Trace: ' . $ex->getTraceAsString());
                    }

                    commitTransaction();

                    // Store success message in session and redirect (POST-redirect-GET pattern)
                    $successMsg = 'Donation has been completed successfully and added to inventory.';
                    if ($smsSent) {
                        $successMsg .= ' SMS notification sent to donor.';
                    } elseif ($smsError && !empty($donorPhone)) {
                        $successMsg .= ' (Note: SMS notification could not be sent: ' . $smsError . ')';
                    }
                    $_SESSION['donation_message'] = $successMsg;
                    $_SESSION['donation_message_type'] = 'success';
                    header('Location: donations.php?success=1');
                    exit;
                } catch (Exception $e) {
                    rollbackTransaction();
                    $_SESSION['donation_message'] = 'Failed to complete donation: ' . $e->getMessage();
                    $_SESSION['donation_message_type'] = 'danger';
                    header('Location: donations.php?success=1');
                    exit;
                }
            }
        } elseif (isset($_POST['add_donation'])) {
            // Add new donation manually
            $donorId = sanitize($_POST['donor_id']);
            $donationDate = sanitize($_POST['donation_date']);
            $bloodType = sanitize($_POST['blood_type']);
            $units = validate_units($_POST['units']);
            $notes = normalize_input($_POST['notes'] ?? '');

            // Begin transaction for adding donation
            beginTransaction();
            try {
                // Insert donation
                $donationId = insertRow("
                    INSERT INTO blood_donations (
                        donor_id, donation_date, blood_type, organization_type,
                        organization_id, status, units, notes, created_at, completed_date
                    ) VALUES (?, ?, ?, 'redcross', ?, 'Completed', ?, ?, NOW(), NOW())
                ", [
                    $donorId, $donationDate, $bloodType, $redcrossId, $units, $notes
                ]);

                if (!$donationId) {
                    throw new Exception('Failed to insert donation record.');
                }

                // Add to blood inventory with 35-day expiry
                executeQuery("
                    INSERT INTO blood_inventory (
                        blood_type, units, status,
                        organization_type, organization_id, source, expiry_date, created_at
                    ) VALUES (?, ?, 'Available', 'redcross', ?, 'Manual Entry', DATE_ADD(NOW(), INTERVAL 35 DAY), NOW())
                ", [
                    $bloodType, $units, $redcrossId
                ]);

                commitTransaction();

                $_SESSION['donation_message'] = 'Donation has been added successfully and added to inventory.';
                $_SESSION['donation_message_type'] = 'success';
                header('Location: donations.php?success=1');
                exit;
            } catch (Exception $e) {
                rollbackTransaction();
                $_SESSION['donation_message'] = 'Failed to add donation: ' . $e->getMessage();
                $_SESSION['donation_message_type'] = 'danger';
                header('Location: donations.php?success=1');
                exit;
            }
        }
    }
}

// Get Completed, No Show, and Rejected appointments for Red Cross (donation history)
// Start from appointments table, then join with donations to get units
// First check if there are any completed/rejected/no show appointments at all
$directCheck = executeQuery("
    SELECT COUNT(*) as count
    FROM donor_appointments
    WHERE organization_type = 'redcross'
      AND status IN ('Completed', 'No Show', 'Rejected')
");

error_log("DONATIONS DEBUG: Direct check for completed/rejected/no show appointments - Found: " . (is_array($directCheck) && count($directCheck) > 0 ? $directCheck[0]['count'] : 0));

// Fetch only Completed, No Show, and Rejected appointments - use organization_type only, organization_id might be NULL or different
$donations = executeQuery("
    SELECT 
        da.id,
        da.donor_id,
        da.appointment_date,
        da.appointment_time,
        da.location,
        da.notes,
        da.status,
        da.created_at,
        du.name as donor_name,
        du.blood_type,
        COALESCE(d.units, 0) as units_donated
    FROM donor_appointments da
    JOIN donor_users du ON da.donor_id = du.id
    LEFT JOIN donations d ON d.donation_date = da.appointment_date 
        AND d.donor_id = da.donor_id 
        AND d.organization_type = 'redcross'
        AND d.status = 'Completed'
    WHERE da.organization_type = 'redcross'
      AND da.status IN ('Completed', 'No Show', 'Rejected')
    ORDER BY 
        FIELD(da.status, 'Completed', 'Rejected', 'No Show'),
        da.appointment_date DESC,
        da.appointment_time DESC,
        da.created_at DESC
");

error_log("DONATIONS DEBUG: Main query result - Found " . (is_array($donations) ? count($donations) : 0) . " completed/rejected/no show appointments");

// Fix: Ensure $donations is always an array
if (!is_array($donations)) {
    $donations = [];
}

// Get donor statistics (total donation count and total units for each donor)
$donorStats = [];
foreach ($donations as $donation) {
    $donorId = $donation['donor_id'];
    if (!isset($donorStats[$donorId])) {
        // Get total donation count from appointments and total units from donations table
        // Count only completed appointments for donation stats, but show all appointments
        $stats = getRow("
            SELECT 
                COUNT(DISTINCT CASE WHEN da.status = 'Completed' THEN da.id END) as total_donations,
                COALESCE(SUM(d.units), 0) as total_units_donated
            FROM donor_appointments da
            LEFT JOIN donations d ON d.donation_date = da.appointment_date 
                AND d.donor_id = da.donor_id 
                AND d.organization_type = 'redcross'
                AND d.status = 'Completed'
            WHERE da.donor_id = ?
            AND da.organization_type = 'redcross'
        ", [$donorId]);

        $donorStats[$donorId] = [
            'total_donations' => $stats['total_donations'] ?? 0,
            'total_units_donated' => $stats['total_units_donated'] ?? 0
        ];
    }
}

?>

<?php include_once 'header.php'; ?>

<style>
/* Donations Page Specific Styles */
.donations-header-section {
    margin-bottom: 3rem;
}

.donations-hero {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
    border-radius: 20px;
    padding: 3rem 2.5rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px -12px rgba(220, 20, 60, 0.3);
    margin-bottom: 2rem;
}

.donations-hero::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    border-radius: 50%;
    transform: translate(50%, -50%);
}

.donations-hero h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    position: relative;
    z-index: 2;
}

.donations-hero p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 2rem;
    position: relative;
    z-index: 2;
}

.hero-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    position: relative;
    z-index: 2;
}

.hero-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.hero-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    transform: translateY(-2px);
}

/* Enhanced Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(220, 20, 60, 0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(220, 20, 60, 0.15);
}

.stat-icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
    color: white;
}

.stat-icon-wrapper.primary { background: linear-gradient(135deg, #007bff, #0056b3); }
.stat-icon-wrapper.success { background: linear-gradient(135deg, #28a745, #1e7e34); }
.stat-icon-wrapper.warning { background: linear-gradient(135deg, #ffc107, #e0a800); }
.stat-icon-wrapper.danger { background: linear-gradient(135deg, #dc3545, #c82333); }

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: var(--primary-color);
}

.stat-label {
    font-size: 0.95rem;
    color: var(--gray-600);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Enhanced Table Styling */
.donations-table-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(220, 20, 60, 0.1);
    overflow: hidden;
}

.table-header {
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    color: white;
    padding: 2rem;
    border-radius: 20px 20px 0 0;
}

.table-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.table-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.table-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.table-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    color: white;
}

.enhanced-table {
    margin: 0;
}

.enhanced-table thead th {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border: none;
    padding: 1rem;
    font-weight: 600;
    color: var(--gray-700);
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.enhanced-table tbody tr {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.enhanced-table tbody tr:hover {
    background: rgba(220, 20, 60, 0.02);
}

.enhanced-table td {
    padding: 1.25rem 1rem;
    vertical-align: middle;
    border: none;
}

/* Blood Type Badges */
.blood-type-badge {
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
}

/* Status Badges */
.status-badge {
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-approved { background: #d1edff; color: #0c5460; }
.status-completed { background: #d4edda; color: #155724; }
.status-rejected { background: #f8d7da; color: #721c24; }
.status-cancelled { background: #e2e3e5; color: #383d41; }

/* Action Buttons */
.action-btn {
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    margin: 0 0.2rem;
}

.action-btn:hover {
    transform: translateY(-1px);
}

.btn-view { background: #e3f2fd; color: #1976d2; }
.btn-approve { background: #e8f5e8; color: #2e7d32; }
.btn-reject { background: #ffebee; color: #c62828; }
.btn-complete { background: #f3e5f5; color: #7b1fa2; }

@media (max-width: 768px) {
    .donations-hero h1 { font-size: 2rem; }
    .stats-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
    .hero-actions { justify-content: center; }
    .table-actions { flex-direction: column; }
}
</style>

<!-- Display messages -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $alertType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>




<style>
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.5rem;
        color: white;
    }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 0.875rem;
        color: var(--gray-600);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
            }
        }

        /* Responsive typography */
        @media (max-width: 768px) {
            h2 {
                font-size: 1.5rem;
            }
            h3 {
                font-size: 1.3rem;
            }
            h4 {
                font-size: 1.2rem;
            }
            .table {
                font-size: 0.9rem;
            }
        }

        /* Action buttons responsiveness */
        .dropdown-menu {
            min-width: 200px;
        }

        @media (max-width: 576px) {
            .dropdown-menu {
                min-width: 160px;
            }
        }

        /* Card header responsiveness */
        .card-header {
            flex-wrap: wrap;
            gap: 1rem;
        }

        @media (max-width: 576px) {
            .card-header .btn-group,
            .card-header .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        /* Stats cards responsiveness */
        .stats-card {
            padding: 1rem;
            height: 100%;
            min-height: 120px;
        }

        /* Form controls responsiveness */
        .form-control,
        .form-select {
            max-width: 100%;
        }

        /* Table cell responsiveness */
        @media (max-width: 768px) {
            .table td, 
            .table th {
                min-width: 100px;
            }
            .table td:first-child,
            .table th:first-child {
                position: sticky;
                left: 0;
                background: white;
                z-index: 1;
            }
        }

        /* Print styles */
        @media print {
            .dashboard-content {
                margin-left: 0;
            }
            .no-print {
                display: none !important;
            }
        }

        .dashboard-main {
            flex: 1;
            padding: 1.5rem;
            margin-top: 4rem; /* Ensure content starts below the header */
        }
        .dashboard-header .breadcrumb {
        margin-left: 35rem;
    }
 
     .dashboard-header .breadcrumb {
        margin-left: 35rem;
    }

    .modal-backdrop {
        z-index: 1050 !important;
    }
    .modal {
        z-index: 1060 !important;
    }

    .custom-modal-width {
        max-width: 450px; /* Adjust this value as needed */
        width: 90vw;      /* Responsive: 90% of viewport width */
    }

    .table-responsive .dropdown-menu {
        z-index: 2000 !important;
        position: absolute !important;
    }

    .modal-header {
    border-bottom: 1px solid #f0f0f0;
}
.modal-footer {
    border-top: 1px solid #f0f0f0;
}
.modal-content {
    border-radius: 12px;
}
    </style>
</head>
<body>

<div class="dashboard-container">

    <div class="dashboard-content">
        <!-- Display messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $alertType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <div class="donations-header-section">
            <div class="donations-hero">
                <h1><i class="bi bi-heart-pulse me-3"></i>Donation History</h1>
                <p>View completed, rejected, and no-show blood donation appointments and track donor participation statistics.</p>
                <div class="hero-actions">
                    <button class="hero-btn" onclick="printDonationsReport()">
                        <i class="bi bi-printer me-2"></i>Print Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Enhanced Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-wrapper success">
                    <i class="bi bi-heart-pulse"></i>
                </div>
                <div class="stat-number"><?php echo count($donations); ?></div>
                <div class="stat-label">Total Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper primary">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-number"><?php echo count($donorStats); ?></div>
                <div class="stat-label">Unique Donors</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper warning">
                    <i class="bi bi-droplet"></i>
                </div>
                <div class="stat-number"><?php echo array_sum(array_column($donations, 'units_donated')); ?></div>
                <div class="stat-label">Total Units Collected</div>
            </div>
        </div>

        <!-- Enhanced Donations Table -->
        <div class="donations-table-card">
            <div class="table-header">
                <h3><i class="bi bi-heart-fill me-2"></i>Donation History</h3>
                <div class="table-actions">
                    <button class="table-btn" onclick="printDonationsReport()">
                        <i class="bi bi-printer me-2"></i>Print Report
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="enhanced-table table" id="donationsTable">
                    <thead>
                        <tr>
                            <th>Donation Date</th>
                            <th>Donor Name</th>
                            <th>Blood Type</th>
                            <th>Status</th>
                            <th>Units</th>
                            <th>Location</th>
                            <th>Total Donations</th>
                            <th>Total Units Donated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($donations) > 0): ?>
                            <?php foreach ($donations as $donation): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($donation['appointment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
                                    <td><span class="badge bg-danger"><?php echo htmlspecialchars($donation['blood_type']); ?></span></td>
                                    <td>
                                        <?php 
                                        $status = strtolower(trim($donation['status'] ?? ''));
                                        $statusDisplay = htmlspecialchars(ucfirst($donation['status'] ?? 'Unknown'));
                                        if ($status === 'completed') {
                                            echo '<span class="status-badge status-completed">Completed</span>';
                                        } elseif ($status === 'rejected') {
                                            echo '<span class="status-badge status-rejected">Rejected</span>';
                                        } elseif ($status === 'pending') {
                                            echo '<span class="status-badge status-pending">Pending</span>';
                                        } elseif ($status === 'scheduled') {
                                            echo '<span class="status-badge status-approved">Scheduled</span>';
                                        } elseif ($status === 'no show' || $status === 'no-show') {
                                            echo '<span class="status-badge status-cancelled">No Show</span>';
                                        } else {
                                            echo '<span class="status-badge status-pending">' . $statusDisplay . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo (int)$donation['units_donated']; ?> unit(s)</td>
                                    <td><?php echo htmlspecialchars($donation['location']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo (int)($donorStats[$donation['donor_id']]['total_donations'] ?? 0); ?> donation(s)</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo (int)($donorStats[$donation['donor_id']]['total_units_donated'] ?? 0); ?> unit(s)</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-heart text-muted" style="font-size: 3rem;"></i>
                                    <p class="mb-0 mt-3 text-muted">No donation records found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Donation Modal -->
<div class="modal fade" id="addDonationModal" tabindex="-1" aria-labelledby="addDonationModalLabel" aria-hidden="true">
    <div class="modal-dialog custom-modal-width">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDonationModalLabel">Add Blood Donation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                    <div class="mb-3">
                        <label for="donor_id" class="form-label">Donor</label>
                        <select class="form-select" id="donor_id" name="donor_id" required>
                            <option value="">Select Donor</option>
                            <?php if (!empty($donors)): ?>
                                <?php foreach ($donors as $donor): ?>
                                    <option value="<?php echo $donor['id']; ?>"><?php echo $donor['name']; ?> (<?php echo $donor['blood_type']; ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="donation_date" class="form-label">Donation Date</label>
                        <input type="date" class="form-control" id="donation_date" name="donation_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="blood_type" class="form-label">Blood Type</label>
                        <select class="form-select" id="blood_type" name="blood_type" required>
                            <option value="">Select Blood Type</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="units" class="form-label">Units Collected</label>
                        <input type="number" step="1" class="form-control" id="units" name="units" value="1" min="1" max="2" required>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any notes about this donation"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_donation" class="btn btn-primary">Add Donation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Donation Modal -->
<div class="modal fade" id="viewDonationModal" tabindex="-1" aria-labelledby="viewDonationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewDonationModalLabel">Donation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Donor</dt>
                    <dd class="col-sm-8" id="vd-donor"></dd>
                    <dt class="col-sm-4">Blood Type</dt>
                    <dd class="col-sm-8" id="vd-btype"></dd>
                    <dt class="col-sm-4">Units</dt>
                    <dd class="col-sm-8" id="vd-units"></dd>
                    <dt class="col-sm-4">Date</dt>
                    <dd class="col-sm-8" id="vd-date"></dd>
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8" id="vd-status"></dd>
                    <dt class="col-sm-4">Notes</dt>
                    <dd class="col-sm-8" id="vd-notes"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
    </div>

<!-- Bootstrap JS and dependencies -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Include print utilities -->
<script src="../../assets/js/print-utils.js"></script>

<!-- Custom JavaScript for responsive behavior -->
<script>
    // Inject CSRF token into all POST forms on the page (defensive)
    document.addEventListener('DOMContentLoaded', function(){
        var csrf = '<?php echo htmlspecialchars(get_csrf_token()); ?>';
        document.querySelectorAll('form').forEach(function(f){
            if ((f.method || '').toUpperCase() === 'POST' && !f.querySelector('input[name="csrf_token"]')){
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'csrf_token';
                inp.value = csrf;
                f.appendChild(inp);
            }
        });
    });

    // Sidebar toggle for mobile
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
        }
    });

    function viewDonationDetails(donationId) {
        var btn = document.querySelector('button[data-donation-id="' + donationId + '"]');
        if (!btn) return;
        document.getElementById('vd-donor').textContent = btn.getAttribute('data-donor-name') || '';
        document.getElementById('vd-btype').textContent = btn.getAttribute('data-blood-type') || '';
        document.getElementById('vd-units').textContent = btn.getAttribute('data-units') + ' unit(s)';
        document.getElementById('vd-date').textContent = btn.getAttribute('data-date') || '';
        document.getElementById('vd-status').textContent = btn.getAttribute('data-status') || '';
        document.getElementById('vd-notes').textContent = btn.getAttribute('data-notes') || '—';
        var modal = new bootstrap.Modal(document.getElementById('viewDonationModal'));
        modal.show();
    }

    function approveDonation(donationId) {
        if (confirm('Are you sure you want to approve this donation?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="donation_id" value="${donationId}">
                <input type="hidden" name="approve_donation" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function cancelDonation(donationId) {
        if (confirm('Are you sure you want to cancel this donation?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="donation_id" value="${donationId}">
                <input type="hidden" name="cancel_donation" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function completeDonation(donationId) {
        if (confirm('Are you sure you want to mark this donation as completed?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="donation_id" value="${donationId}">
                <input type="hidden" name="complete_donation" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.alert, .feedback-message, .notification, .status-message').forEach(function(el) {
            el.style.display = 'none';
        });
    }, 5000);
});
</script>