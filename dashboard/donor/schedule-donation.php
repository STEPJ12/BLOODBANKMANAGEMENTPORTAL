<?php
// Start session
session_start();



// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../../login.php?role=donor");
    exit;
}

// Set page title
$pageTitle = "Schedule Donation - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get donor information
$donorId = $_SESSION['user_id'];
$donor = getRow("SELECT * FROM donor_users WHERE id = ?", [$donorId]);

// Add notification functions at the top after session_start()
function sendEmailNotification($to, $subject, $message) {
    // $headers = "MIME-Version: 1.0" . "\r\n";
    // $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    // $headers .= 'From: Blood Bank Portal <noreply@bloodbank.com>' . "\r\n";
    
    // return mail($to, $subject, $message, $headers);
    return true; // Always succeed for now
}

function sendSMSNotification($phone, $message) {
    // Use SIM800C SMS with notification templates format
    try {
        require_once __DIR__ . '/../../includes/sim800c_sms.php';
        $result = send_sms_sim800c($phone, $message);
        return $result['success'] ?? false;
    } catch (Exception $e) {
        // Use secure_log to prevent log injection
        secure_log('[DONOR_SMS_ERR] Exception in sendSMSNotification', [
            'error' => substr($e->getMessage(), 0, 500)
        ]);
        return false;
    }
}

// Get organization types for filter
$organizationTypes = [
    'all' => 'All Organizations',
    'redcross' => 'Red Cross',
    'negrosfirst' => 'Negros First'
];

// Get current filters
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
$locationFilter = isset($_GET['location']) ? $_GET['location'] : '';
$orgFilter = isset($_GET['org_filter']) ? $_GET['org_filter'] : 'all';

// Build query conditions and parameters
$conditions = [];
$queryParams = [];

if ($orgFilter !== 'all') {
    $conditions[] = "organization_type = :org_type";
    $queryParams['org_type'] = $orgFilter;
}
if ($dateFilter === 'week') {
    $conditions[] = "date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
}
if ($dateFilter === 'month') {
    $conditions[] = "date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}
if (!empty($locationFilter)) {
    $conditions[] = "location LIKE :location";
    $queryParams['location'] = "%$locationFilter%";
}

// Always hide past/finished drives for donors
// Note: redcross creates drives with status 'Scheduled'; include both for compatibility
$conditions[] = "status IN ('Scheduled','Active')";
// Use the actual time column name 'start_time' (as used in redcross/blood-drives.php)
$conditions[] = "(date > CURDATE() OR (date = CURDATE() AND start_time >= CURTIME()))";

$whereClause = '';
if (!empty($conditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$upcomingDrives = executeQuery(
    "SELECT * FROM blood_drives $whereClause ORDER BY date ASC",
    $queryParams
);

// Get blood banks
$bloodBanks = executeQuery("
    SELECT * FROM blood_banks 
    WHERE organization_type IN ('redcross', 'negrosfirst')
    ORDER BY name ASC
");

// Process form submission
$success = false;
$error = "";
// Handle flash success via PRG
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$flashSuccessMessage = '';
if (isset($_GET['ok']) && isset($_SESSION['flash_success_message'])) {
    $flashSuccessMessage = $_SESSION['flash_success_message'];
    unset($_SESSION['flash_success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input with proper security checks
    // First, check all required fields exist before sanitizing
    if (!isset($_POST['donation_date']) || !isset($_POST['blood_type'])) {
        throw new Exception("Missing required fields. Please fill in all required information.");
    }
    
    // Validate drive_id - must be integer if provided
    $driveId = null;
    if (isset($_POST['drive_id']) && !empty($_POST['drive_id'])) {
        $driveIdRaw = $_POST['drive_id'];
        // Validate as integer to prevent SQL injection
        if (!ctype_digit((string)$driveIdRaw) && !is_numeric($driveIdRaw)) {
            throw new Exception("Invalid blood drive ID. Please try again.");
        }
        $driveId = (int)$driveIdRaw;
        if ($driveId <= 0) {
            throw new Exception("Invalid blood drive ID. Please try again.");
        }
    }
    
    // Validate and sanitize donation_date - must be valid date format
    $donationDateRaw = $_POST['donation_date'];
    if (empty($donationDateRaw)) {
        throw new Exception("Donation date is required.");
    }
    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $donationDateRaw)) {
        throw new Exception("Invalid date format. Please use YYYY-MM-DD format.");
    }
    // Validate it's a real date
    $dateParts = explode('-', $donationDateRaw);
    if (count($dateParts) !== 3 || !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
        throw new Exception("Invalid date. Please select a valid date.");
    }
    $donationDate = sanitize($donationDateRaw);
    
    // Validate and sanitize blood_type - must be from whitelist
    $bloodTypeRaw = $_POST['blood_type'];
    $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
    if (!in_array($bloodTypeRaw, $validBloodTypes, true)) {
        throw new Exception("Invalid blood type selected. Please select a valid blood type.");
    }
    $bloodType = sanitize($bloodTypeRaw);
    
    // Validate and sanitize appointment_time - must be valid time format if provided
    $appointmentTime = null;
    if (isset($_POST['appointment_time']) && !empty(trim($_POST['appointment_time']))) {
        $appointmentTimeRaw = $_POST['appointment_time'];
        // Validate time format (HH:MM:SS or HH:MM)
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $appointmentTimeRaw)) {
            throw new Exception("Invalid time format. Please use HH:MM format.");
        }
        // Validate time values are within valid range
        $timeParts = explode(':', $appointmentTimeRaw);
        $hour = (int)$timeParts[0];
        $minute = (int)$timeParts[1];
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new Exception("Invalid time values. Hours must be 0-23 and minutes must be 0-59.");
        }
        $appointmentTime = sanitize($appointmentTimeRaw);
    }
    
    // Sanitize optional location field
    $location = null;
    if (isset($_POST['location']) && !empty(trim($_POST['location']))) {
        $location = sanitize($_POST['location']);
        // Limit length to prevent resource exhaustion
        if (strlen($location) > 255) {
            $location = substr($location, 0, 255);
        }
    }
    
    // Sanitize optional health_status field
    $healthStatus = null;
    if (isset($_POST['health_status']) && !empty(trim($_POST['health_status']))) {
        $healthStatus = sanitize($_POST['health_status']);
        // Limit length
        if (strlen($healthStatus) > 255) {
            $healthStatus = substr($healthStatus, 0, 255);
        }
    }
    
    // Sanitize notes field - required field
    $notes = '';
    if (isset($_POST['notes'])) {
    $notes = sanitize($_POST['notes']);
        // Limit length to prevent resource exhaustion
        if (strlen($notes) > 5000) {
            $notes = substr($notes, 0, 5000);
        }
    }

    // Initialize organization variables (will be set from blood drive)
    $organizationType = null;
    $organizationId = null;

    try {
        // ALWAYS get organization details from the blood drive - this is the source of truth
        if ($driveId) {
            $driveDetails = getRow("SELECT organization_type, organization_id, location FROM blood_drives WHERE id = ?", [$driveId]);
            if ($driveDetails) {
                // Always use the organization details from the blood drive - this overrides any POST data
                $organizationType = $driveDetails['organization_type'];
                $organizationId = $driveDetails['organization_id'];
                
                // Also use the drive's location if location is not provided
                if (empty($location) && !empty($driveDetails['location'])) {
                    $location = $driveDetails['location'];
                }
                
                // Validate that organization_id exists
                if (empty($organizationId)) {
                    throw new Exception("Blood drive is missing organization information. Please contact support.");
                }
                
                // Use secure_log to prevent log injection attacks
                secure_log('[DONOR_APPT] Retrieved from blood drive', [
                    'drive_id' => $driveId,
                    'organization_type' => $organizationType,
                    'organization_id' => $organizationId
                ]);
            } else {
                throw new Exception("Selected blood drive not found. Please try again.");
            }
        } else {
            // If no drive ID, fallback to POST data (should not happen in normal flow)
            // Validate organization_type and organization_id from POST
            if (!isset($_POST['organization_type']) || !isset($_POST['organization_id'])) {
                throw new Exception("Organization information is required. Please select a blood drive to register for.");
            }
            
            $organizationTypeRaw = $_POST['organization_type'];
            $organizationIdRaw = $_POST['organization_id'];
            
            // Sanitize organization_type
            $organizationType = sanitize($organizationTypeRaw);
            
            // Validate organization_id as integer
            if (!ctype_digit((string)$organizationIdRaw) && !is_numeric($organizationIdRaw)) {
                throw new Exception("Invalid organization ID format.");
            }
            $organizationId = (int)$organizationIdRaw;
            
            if (empty($organizationType) || $organizationId <= 0) {
                throw new Exception("Organization information is required. Please select a blood drive to register for.");
            }
            
            // Use secure_log to prevent log injection attacks
            secure_log('[DONOR_APPT_WARN] No drive ID provided, using POST data', [
                'organization_type' => $organizationType,
                'organization_id' => $organizationId
            ]);
        }

        // Ensure organization_id is an integer
        $organizationId = (int)$organizationId;
        if ($organizationId <= 0) {
            throw new Exception("Invalid organization ID. Please contact support.");
        }

        // Normalize organization_type to lowercase to ensure consistency
        $organizationType = strtolower(trim($organizationType ?? ''));
        if ($organizationType !== 'redcross' && $organizationType !== 'negrosfirst') {
            // Use secure_log to prevent log injection attacks
            secure_log('[DONOR_APPT_ERR] Invalid organization type', [
                'organization_type' => $organizationType ?: 'empty',
                'expected' => 'redcross or negrosfirst'
            ]);
            throw new Exception("Invalid organization type. Please contact support.");
        }

        // Debug information - use secure_log to prevent log injection
        secure_log('[DONOR_APPT_DEBUG] Appointment creation', [
            'donor_id' => $donorId,
            'appointment_date' => $donationDate,
            'organization_type' => $organizationType,
            'organization_id' => $organizationId,
            'blood_drive_id' => $driveId ?: null,
            'has_location' => !empty($location),
            'notes_length' => strlen($notes)
        ]);

        // Create the appointment using PDO directly for better error handling
        $conn = getConnection();
        $query = "INSERT INTO donor_appointments (
            donor_id,
            appointment_date,
            appointment_time,
            organization_type,
            organization_id,
            blood_drive_id,
            location,
            status,
            notes,
            blood_type,
            created_at,
            updated_at
        ) VALUES (
            :donor_id,
            :appointment_date,
            :appointment_time,
            :organization_type,
            :organization_id,
            :blood_drive_id,
            :location,
            'Pending',
            :notes,
            :blood_type,
            NOW(),
            NOW()
        )";
        $stmt = $conn->prepare($query);
        $params = [
            ':donor_id' => $donorId,
            ':appointment_date' => $donationDate,
            ':appointment_time' => $appointmentTime,
            ':organization_type' => $organizationType,
            ':organization_id' => $organizationId,
            ':blood_drive_id' => $driveId ?: null,
            ':location' => $location,
            ':notes' => $notes,
            ':blood_type' => $bloodType,
        ];
        
        // Final validation before insert
        if (empty($organizationType) || empty($organizationId) || $organizationId <= 0) {
            throw new Exception("Invalid organization information. Cannot create appointment.");
        }
        
        // Log query execution without sensitive data - use secure_log to prevent log injection
        secure_log('[DONOR_APPT] Executing appointment insert query', [
            'donor_id' => $donorId,
            'has_organization_type' => !empty($organizationType),
            'has_organization_id' => !empty($organizationId),
            'has_drive_id' => !empty($driveId)
        ]);
        $stmt->execute($params);
        // Consider success if no exception and at least one row affected
        $lastId = $conn->lastInsertId();
        if (!$lastId) {
            // Fallback: try to fetch the last inserted appointment for this donor
            // Use prepared statement instead of string concatenation to prevent SQL injection
            try {
                $fallbackStmt = $conn->prepare("SELECT id FROM donor_appointments WHERE donor_id = :donor_id ORDER BY id DESC LIMIT 1");
                $fallbackStmt->execute([':donor_id' => $donorId]);
                $lastId = (int)$fallbackStmt->fetchColumn();
            } catch (Exception $ie) {
                // Use secure_log to prevent log injection
                secure_log('[DONOR_APPT_ERR] Could not fetch last inserted appointment id', [
                    'error' => substr($ie->getMessage(), 0, 500)
                ]);
            }
        }
        if ($stmt->rowCount() >= 1) {
            // Optionally store or link interview data if provided
            // If an interview was previously saved via AJAX, link it to this appointment
            if (isset($_POST['interview_id']) && !empty($_POST['interview_id'])) {
                // Validate interview_id as integer
                $ivIdRaw = $_POST['interview_id'];
                if (!ctype_digit((string)$ivIdRaw) && !is_numeric($ivIdRaw)) {
                    // Use secure_log to prevent log injection
                    secure_log('[DONOR_APPT_ERR] Invalid interview_id format', [
                        'interview_id_raw' => is_string($ivIdRaw) ? substr($ivIdRaw, 0, 50) : gettype($ivIdRaw)
                    ]);
                    throw new Exception("Invalid interview ID format.");
                }
                $ivId = (int)$ivIdRaw;
                if ($ivId <= 0) {
                    throw new Exception("Invalid interview ID.");
                }
                
                // Sanitize and validate interview_status if provided
                $ivStatus = '';
                if (isset($_POST['interview_status']) && !empty(trim($_POST['interview_status']))) {
                    $ivStatus = sanitize($_POST['interview_status']);
                    // Limit length
                    if (strlen($ivStatus) > 255) {
                        $ivStatus = substr($ivStatus, 0, 255);
                    }
                }
                
                // Validate and sanitize JSON fields (interview responses, deferrals, reasons)
                $ivResponses = null;
                if (isset($_POST['interview_responses']) && !empty(trim($_POST['interview_responses']))) {
                    $ivResponsesRaw = $_POST['interview_responses'];
                    // Validate it's valid JSON
                    $decoded = json_decode($ivResponsesRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Use secure_log to prevent log injection
                        secure_log('[DONOR_APPT_ERR] Invalid JSON in interview_responses', [
                            'json_error' => json_last_error_msg()
                        ]);
                        throw new Exception("Invalid interview responses format.");
                    }
                    // Re-encode to ensure clean JSON
                    $ivResponses = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                    // Limit length to prevent resource exhaustion
                    if (strlen($ivResponses) > 10000) {
                        throw new Exception("Interview responses too large.");
                    }
                }
                
                $ivDeferrals = null;
                if (isset($_POST['interview_deferrals']) && !empty(trim($_POST['interview_deferrals']))) {
                    $ivDeferralsRaw = $_POST['interview_deferrals'];
                    $decoded = json_decode($ivDeferralsRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Use secure_log to prevent log injection
                        secure_log('[DONOR_APPT_ERR] Invalid JSON in interview_deferrals', [
                            'json_error' => json_last_error_msg()
                        ]);
                        throw new Exception("Invalid interview deferrals format.");
                    }
                    $ivDeferrals = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                    if (strlen($ivDeferrals) > 5000) {
                        throw new Exception("Interview deferrals too large.");
                    }
                }
                
                $ivReasons = null;
                if (isset($_POST['interview_reasons']) && !empty(trim($_POST['interview_reasons']))) {
                    $ivReasonsRaw = $_POST['interview_reasons'];
                    $decoded = json_decode($ivReasonsRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Use secure_log to prevent log injection
                        secure_log('[DONOR_APPT_ERR] Invalid JSON in interview_reasons', [
                            'json_error' => json_last_error_msg()
                        ]);
                        throw new Exception("Invalid interview reasons format.");
                    }
                    $ivReasons = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                    if (strlen($ivReasons) > 5000) {
                        throw new Exception("Interview reasons too large.");
                    }
                }

                try {
                    $up = $conn->prepare("UPDATE donor_interviews SET appointment_id = :appointment_id, status = :status, responses_json = :responses, deferrals_json = :deferrals, reasons_json = :reasons WHERE id = :id AND donor_id = :donor_id");
                    $up->execute([
                        ':appointment_id' => $lastId,
                        ':status' => $ivStatus,
                        ':responses' => $ivResponses,
                        ':deferrals' => $ivDeferrals,
                        ':reasons' => $ivReasons,
                        ':id' => $ivId,
                        ':donor_id' => $donorId
                    ]);

                    // Fetch the interview to build a preview
                    $saved = getRow("SELECT * FROM donor_interviews WHERE id = ?", [$ivId]);
                    if ($saved) {
                        $interviewPreview = "Status: " . ($saved['status'] ?? 'N/A');
                        if (!empty($saved['responses_json'])) {
                            $responsesText = is_string($saved['responses_json']) ? $saved['responses_json'] : json_encode($saved['responses_json']);
                            $responsesText = trim(strip_tags($responsesText));
                            if (strlen($responsesText) > 300) $responsesText = substr($responsesText, 0, 300) . '...';
                            $interviewPreview .= "; Responses: " . $responsesText;
                        }
                    }
                } catch (Exception $e) {
                    // Use secure_log to prevent log injection
                    secure_log('[DONOR_APPT_ERR] Failed to link interview to appointment', [
                        'interview_id' => $ivId,
                        'appointment_id' => $lastId,
                        'error' => substr($e->getMessage(), 0, 500)
                    ]);
                    $interviewPreview = '';
                }
            } elseif (isset($_POST['interview_status']) && !empty(trim($_POST['interview_status']))) {
                // Sanitize and validate interview_status
                $ivStatus = sanitize($_POST['interview_status']);
                if (strlen($ivStatus) > 255) {
                    $ivStatus = substr($ivStatus, 0, 255);
                }
                
                // Validate and sanitize JSON fields
                $ivResponses = null;
                if (isset($_POST['interview_responses']) && !empty(trim($_POST['interview_responses']))) {
                    $ivResponsesRaw = $_POST['interview_responses'];
                    $decoded = json_decode($ivResponsesRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Use secure_log to prevent log injection
                        secure_log('[DONOR_APPT_ERR] Invalid JSON in interview_responses', [
                            'json_error' => json_last_error_msg()
                        ]);
                        throw new Exception("Invalid interview responses format.");
                    }
                    $ivResponses = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                    if (strlen($ivResponses) > 10000) {
                        throw new Exception("Interview responses too large.");
                    }
                }
                
                $ivDeferrals = null;
                if (isset($_POST['interview_deferrals']) && !empty(trim($_POST['interview_deferrals']))) {
                    $ivDeferralsRaw = $_POST['interview_deferrals'];
                    $decoded = json_decode($ivDeferralsRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Use secure_log to prevent log injection
                        secure_log('[DONOR_APPT_ERR] Invalid JSON in interview_deferrals', [
                            'json_error' => json_last_error_msg()
                        ]);
                        throw new Exception("Invalid interview deferrals format.");
                    }
                    $ivDeferrals = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                    if (strlen($ivDeferrals) > 5000) {
                        throw new Exception("Interview deferrals too large.");
                    }
                }
                
                $ivReasons = null;
                if (isset($_POST['interview_reasons']) && !empty(trim($_POST['interview_reasons']))) {
                    $ivReasonsRaw = $_POST['interview_reasons'];
                    $decoded = json_decode($ivReasonsRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Use secure_log to prevent log injection
                        secure_log('[DONOR_APPT_ERR] Invalid JSON in interview_reasons', [
                            'json_error' => json_last_error_msg()
                        ]);
                        throw new Exception("Invalid interview reasons format.");
                    }
                    $ivReasons = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                    if (strlen($ivReasons) > 5000) {
                        throw new Exception("Interview reasons too large.");
                    }
                }

                try {
                    $ivStmt = $conn->prepare("INSERT INTO donor_interviews (donor_id, appointment_id, status, responses_json, deferrals_json, reasons_json, created_at) VALUES (:donor_id, :appointment_id, :status, :responses, :deferrals, :reasons, NOW())");
                    $ivStmt->execute([
                        ':donor_id' => $donorId,
                        ':appointment_id' => $lastId,
                        ':status' => $ivStatus,
                        ':responses' => $ivResponses,
                        ':deferrals' => $ivDeferrals,
                        ':reasons' => $ivReasons,
                    ]);
                    // Use secure_log to prevent log injection
                    secure_log('[DONOR_APPT] Interview saved for appointment', [
                        'appointment_id' => $lastId
                    ]);

                    // Build a short interview preview to include in organization notifications
                    $interviewPreview = "Status: " . ($ivStatus ?? 'N/A');
                    if (!empty($ivResponses)) {
                        // keep it short for notifications
                        $responsesText = is_string($ivResponses) ? $ivResponses : json_encode($ivResponses);
                        $responsesText = trim(strip_tags($responsesText));
                        if (strlen($responsesText) > 300) $responsesText = substr($responsesText, 0, 300) . '...';
                        $interviewPreview .= "; Responses: " . $responsesText;
                    }
                } catch (Exception $e) {
                    // Use secure_log to prevent log injection
                    secure_log('[DONOR_APPT_ERR] Failed to save interview', [
                        'appointment_id' => $lastId,
                        'error' => substr($e->getMessage(), 0, 500)
                    ]);
                    $interviewPreview = '';
                }
            }
            // Get donor's contact information
            $donorInfo = getRow("SELECT email, phone FROM donor_users WHERE id = ?", [$donorId]);
            // Prepare notification messages
            $emailSubject = "Blood Donation Appointment Confirmation";
            $emailMessage = "<h2>Thank you for scheduling your blood donation!</h2><p>Your appointment details:</p><ul><li>Date: " . date('F d, Y', strtotime($donationDate)) . "</li><li>Venue: " . ($driveDetails['venue_name'] ?? '') . "</li><li>Address: " . ($driveDetails['venue_address'] ?? '') . "</li></ul><p>Please remember to bring a valid ID and follow the pre-donation guidelines.</p>";
            // Send notifications to donor
            if (!empty($donorInfo['email'])) {
                sendEmailNotification($donorInfo['email'], $emailSubject, $emailMessage);
            }
            // Send professional SMS notification to donor using notification templates
            try {
                require_once '../../includes/sim800c_sms.php';
                require_once '../../includes/notification_templates.php';
                
                $donorPhone = $donorInfo['phone'] ?? '';
                $donorName = getRow("SELECT name FROM donor_users WHERE id = ?", [$donorId]);
                $donorName = $donorName['name'] ?? 'Donor';
                
                if (!empty($donorPhone)) {
                    // Try to decrypt phone number if encrypted, but keep original if decryption fails (plain text)
                    if (function_exists('decrypt_value')) {
                        $decryptedPhone = decrypt_value($donorPhone);
                        if (!empty($decryptedPhone)) {
                            $donorPhone = $decryptedPhone;
                        }
                    }
                    
                    if (!empty($donorPhone) && trim($donorPhone) !== '') {
                        // Use the organization type from the blood drive (already validated above)
                        $orgType = strtolower(trim($organizationType ?? ''));
                        
                        // Validate organization type
                        if (empty($orgType) || ($orgType !== 'redcross' && $orgType !== 'negrosfirst')) {
                            // Use secure_log to prevent log injection
                            secure_log('[DONOR_SMS_ERR] Invalid organization type for donor SMS', [
                                'organization_type' => $organizationType
                            ]);
                            $orgType = 'negrosfirst'; // Safe fallback - but should not happen if validation above worked
                        }
                        
                        // Get location/venue details
                        $venueName = $location ?: ($driveDetails['venue_name'] ?? 'the venue');
                        $venueAddress = $driveDetails['venue_address'] ?? '';
                        $fullLocation = $venueAddress ? $venueName . ', ' . $venueAddress : $venueName;
                        
                        // Use professional notification template
                        $smsMessage = get_notification_message('appointment', $donorName, $orgType, [
                            'status' => 'Pending',
                            'date' => $donationDate,
                            'time' => $appointmentTime ?? '',
                            'location' => $fullLocation
                        ]);
                        $smsMessage = format_notification_message($smsMessage);
                        
                        // Use secure_log to prevent log injection - redact phone number
                        secure_log('[DONOR_SMS] Sending appointment confirmation SMS', [
                            'donor_id' => $donorId,
                            'phone_prefix' => substr($donorPhone, 0, 4) . '****'
                        ]);
                        
                        $smsResult = send_sms_sim800c($donorPhone, $smsMessage);
                        
                        if ($smsResult['success']) {
                            // Use secure_log for consistency
                            secure_log('[DONOR_SMS] Appointment confirmation SMS sent successfully');
                        } else {
                            $smsError = $smsResult['error'] ?? 'Unknown error';
                            // Use secure_log to prevent log injection
                            secure_log('[DONOR_SMS] Failed to send appointment confirmation SMS', [
                                'error' => substr($smsError, 0, 500)
                            ]);
                        }
                    } else {
                        // Use secure_log to prevent log injection
                        secure_log('[DONOR_SMS] Donor phone missing or could not be decrypted', [
                            'donor_id' => $donorId
                        ]);
                    }
                }
            } catch (Exception $smsEx) {
                // Use secure_log to prevent log injection
                secure_log('[DONOR_SMS_ERR] Exception in donor SMS notification', [
                    'error' => substr($smsEx->getMessage(), 0, 500)
                ]);
                // Don't block appointment scheduling if SMS fails
            }
            // Send SMS notifications to blood bank users (Red Cross or Negros First) when donor registers for blood drive
            try {
                require_once '../../includes/sim800c_sms.php';
                require_once '../../includes/notification_templates.php';
                
                // Get blood drive details
                $driveTitle = '';
                $driveLocation = $location ?: ($driveDetails['venue_name'] ?? 'the venue');
                if (!empty($driveId)) {
                    $driveInfo = getRow("SELECT title, location FROM blood_drives WHERE id = ?", [$driveId]);
                    if ($driveInfo) {
                        $driveTitle = $driveInfo['title'] ?? '';
                        if (empty($driveLocation) && !empty($driveInfo['location'])) {
                            $driveLocation = $driveInfo['location'];
                        }
                    }
                }
                
                // Get donor name for the message
                $donorFullName = getRow("SELECT name FROM donor_users WHERE id = ?", [$donorId]);
                $donorFullName = $donorFullName['name'] ?? 'Donor';
                
                // Use the organization type from the blood drive (already validated above)
                $orgType = strtolower(trim($organizationType ?? ''));
                
                // Validate organization type - must be either redcross or negrosfirst
                if (empty($orgType) || ($orgType !== 'redcross' && $orgType !== 'negrosfirst')) {
                    // Use secure_log to prevent log injection
                    secure_log('[DONOR_SMS_ERR] Invalid organization type - cannot send notifications', [
                        'organization_type' => $organizationType
                    ]);
                    throw new Exception("Invalid organization type. Cannot send notifications.");
                }
                
                $institutionName = get_institution_name($orgType);
                $bloodBankUsers = [];
                
                // Get all users from the selected organization
                if ($orgType === 'redcross') {
                    $bloodBankUsers = executeQuery("SELECT id, phone, name FROM redcross_users WHERE phone IS NOT NULL AND phone != ''", []);
                    if (empty($bloodBankUsers)) {
                        // Fallback to generic users table
                        $bloodBankUsers = executeQuery("SELECT id, phone, name FROM users WHERE role = 'redcross' AND phone IS NOT NULL AND phone != ''", []);
                    }
                } elseif ($orgType === 'negrosfirst') {
                    $bloodBankUsers = executeQuery("SELECT id, phone, name FROM negrosfirst_users WHERE phone IS NOT NULL AND phone != ''", []);
                    if (empty($bloodBankUsers)) {
                        // Fallback to generic users table
                        $bloodBankUsers = executeQuery("SELECT id, phone, name FROM users WHERE role = 'negrosfirst' AND phone IS NOT NULL AND phone != ''", []);
                    }
                }
                
                $smsSentCount = 0;
                $smsErrorCount = 0;
                
                if (!empty($bloodBankUsers) && is_array($bloodBankUsers)) {
                    // Format date and time for SMS
                    $formattedDate = date('M d, Y', strtotime($donationDate));
                    $formattedTime = !empty($appointmentTime) ? date('h:i A', strtotime($appointmentTime)) : 'scheduled time';
                    
                    // Build professional SMS message for blood bank users
                    foreach ($bloodBankUsers as $bbUser) {
                        $bbUserPhone = $bbUser['phone'] ?? '';
                        $bbUserName = $bbUser['name'] ?? 'User';
                        $bbUserId = $bbUser['id'] ?? null;
                        
                        if (!empty($bbUserPhone)) {
                            // Try to decrypt phone number if encrypted
                            if (function_exists('decrypt_value')) {
                                $decryptedPhone = decrypt_value($bbUserPhone);
                                if (!empty($decryptedPhone)) {
                                    $bbUserPhone = $decryptedPhone;
                                }
                            }
                            
                            if (!empty($bbUserPhone) && trim($bbUserPhone) !== '') {
                                // Build professional SMS message
                                $smsMessage = "Hello, this is an automated alert from {$institutionName}. ";
                                $smsMessage .= "A new donor has registered for a blood drive. ";
                                $smsMessage .= "Donor: {$donorFullName}. ";
                                if (!empty($driveTitle)) {
                                    $smsMessage .= "Blood Drive: {$driveTitle}. ";
                                }
                                $smsMessage .= "Date: {$formattedDate} at {$formattedTime}. ";
                                $smsMessage .= "Location: {$driveLocation}. ";
                                $smsMessage .= "Appointment ID: {$lastId}. ";
                                $smsMessage .= "Please review and approve in the Appointments dashboard. Thank you!";
                                $smsMessage = format_notification_message($smsMessage);
                                
                                // Use secure_log to prevent log injection - redact phone number
                                secure_log('[DONOR_SMS] Sending blood drive registration SMS', [
                                    'org_type' => $orgType,
                                    'user_id' => $bbUserId,
                                    'phone_prefix' => substr($bbUserPhone, 0, 4) . '****'
                                ]);
                                
                                try {
                                    $smsResult = send_sms_sim800c($bbUserPhone, $smsMessage);
                                    if ($smsResult['success']) {
                                        $smsSentCount++;
                                        // Use secure_log to prevent log injection
                                        secure_log('[DONOR_SMS] Blood drive registration SMS sent successfully', [
                                            'user_name' => substr($bbUserName, 0, 100)
                                        ]);
                                    } else {
                                        $smsErrorCount++;
                                        $smsError = $smsResult['error'] ?? 'Unknown error';
                                        // Use secure_log to prevent log injection
                                        secure_log('[DONOR_SMS] Failed to send blood drive registration SMS', [
                                            'user_name' => substr($bbUserName, 0, 100),
                                            'error' => substr($smsError, 0, 500)
                                        ]);
                                    }
                                } catch (Exception $smsEx) {
                                    $smsErrorCount++;
                                    // Use secure_log to prevent log injection
                                    secure_log('[DONOR_SMS_ERR] Exception sending blood drive registration SMS', [
                                        'org_type' => $orgType,
                                        'user_id' => $bbUserId,
                                        'error' => substr($smsEx->getMessage(), 0, 500)
                                    ]);
                                }
                            }
                        }
                    }
                    
                    // Use secure_log for consistency
                    secure_log('[DONOR_SMS] Blood drive registration SMS summary', [
                        'sent' => $smsSentCount,
                        'failed' => $smsErrorCount,
                        'total' => count($bloodBankUsers)
                    ]);
                } else {
                    // Use secure_log to prevent log injection
                    secure_log('[DONOR_SMS] No users with phone numbers found to notify', [
                        'org_type' => $orgType
                    ]);
                }
            } catch (Exception $smsEx) {
                // Use secure_log to prevent log injection
                secure_log('[DONOR_SMS_ERR] Exception in blood bank SMS notification', [
                    'error' => substr($smsEx->getMessage(), 0, 500)
                ]);
                // Don't block appointment scheduling if SMS fails
            }
            $success = true;
            // Get organization name for success message
            $orgDisplayName = $institutionName ?? 'blood bank';
            $successMessage = "Thank you for donating your blood and for helping the " . htmlspecialchars($orgDisplayName) . " maintain a safe blood supply.";
            // Use secure_log for consistency
            secure_log('[DONOR_APPT] Successfully inserted appointment', [
                'appointment_id' => $lastId
            ]);

            // Create in-app notifications
            try {
                // Notify donor
                $notifSql = "INSERT INTO notifications (title, message, user_id, user_role, is_read, created_at) VALUES (:title, :message, :user_id, :user_role, 0, NOW())";
                $donorTitle = 'Blood Donation Appointment Scheduled';
                $donorMsg = 'Your blood donation appointment is scheduled for ' . date('M d, Y', strtotime($donationDate)) . ' at ' . ($appointmentTime ? date('h:i A', strtotime($appointmentTime)) : 'the scheduled time') . ' at ' . ($location ?: ($driveDetails['venue_name'] ?? 'the venue')) . '.';
                $stmtN = $conn->prepare($notifSql);
                $stmtN->execute([
                    ':title' => $donorTitle,
                    ':message' => $donorMsg,
                    ':user_id' => $donorId,
                    ':user_role' => 'donor'
                ]);

                // Notify organization (redcross/negrosfirst dashboard owner)
                if (!empty($organizationId) && !empty($organizationType)) {
                    // Ensure organization type is valid and normalized
                    $orgTypeForNotif = strtolower(trim($organizationType));
                    if ($orgTypeForNotif !== 'redcross' && $orgTypeForNotif !== 'negrosfirst') {
                        // Use secure_log to prevent log injection
                        secure_log('[DONOR_NOTIF_ERR] Invalid organization type for notification - skipping', [
                            'organization_type' => $organizationType
                        ]);
                        // Don't send notification if organization type is invalid
                    } else {
                    $orgTitle = 'New Donor Appointment';
                    $orgMsg = 'A donor has scheduled an appointment for ' . date('M d, Y', strtotime($donationDate)) . ' at ' . ($appointmentTime ? date('h:i A', strtotime($appointmentTime)) : 'the scheduled time') . '. Please review in the Appointments page.';
                    $stmtN2 = $conn->prepare($notifSql);
                    $stmtN2->execute([
                        ':title' => $orgTitle,
                        ':message' => $orgMsg,
                        ':user_id' => $organizationId,
                            ':user_role' => $orgTypeForNotif // e.g., 'redcross' or 'negrosfirst'
                    ]);
                        // Use secure_log to prevent log injection
                        secure_log('[DONOR_NOTIF] Sent in-app notification', [
                            'org_type' => $orgTypeForNotif,
                            'user_id' => $organizationId,
                            'organization_type' => $organizationType
                        ]);
                    }
                } else {
                    // Use secure_log to prevent log injection
                    secure_log('[DONOR_NOTIF_WARN] Cannot send organization notification', [
                        'organization_id' => $organizationId ?? null,
                        'organization_type' => $organizationType ?? null
                    ]);
                }

                // Send email notifications alongside in-app notifications
               // try {
                 //   require_once '../../includes/SMS/EmailNotificationService.php';
                   // $emailNotificationService = new EmailNotificationService();
                    
                    // Prepare appointment data for email notifications
                    $appointmentData = [
                        'donationDate' => $donationDate,
                        'appointmentTime' => $appointmentTime ?: 'TBD',
                        'location' => $location ?: ($driveDetails['venue_name'] ?? 'Blood Bank'),
                        'driveDetails' => $driveDetails['venue_address'] ?? 'Contact for address',
                        'contact_info' => $driveDetails['contact_info'] ?? 'Contact information not available'
                    ];
                    
                    // Send email notification to donor
                   // $emailNotificationService->sendDonationAppointmentNotification($appointmentData, $donorId, 'donor');
                    
                    // Send email notification to organization
                  //  if (!empty($organizationId) && !empty($organizationType)) {
                  //     $orgUsers = executeQuery("SELECT id FROM users WHERE role = ? AND organization_id = ?", [$organizationType, $organizationId]);
                    //    $orgRecipients = is_array($orgUsers) ? array_column($orgUsers, 'id') : [];
                        
                      //  $orgData = [
                        //    'title' => $orgTitle,
                          //  'message' => $orgMsg,
                            //'date' => $donationDate,
                           // 'donor_id' => $donorId
                       // ];
                        
                      //  $emailNotificationService->sendSystemAnnouncement($orgData, $orgRecipients);
                  //  }
                    
               // } catch (Exception $emailError) {
               //     error_log('Failed to send email notifications: ' . $emailError->getMessage());
               // }

            } catch (Exception $ne) {
                // Use secure_log to prevent log injection
                secure_log('[DONOR_NOTIF_ERR] Failed to insert notifications', [
                    'error' => substr($ne->getMessage(), 0, 500)
                ]);
            }

            // Post/Redirect/Get to avoid duplicate submissions on refresh
            $_SESSION['flash_success_message'] = $successMessage;
            header("Location: schedule-donation.php?ok=1");
            exit;
        } else {
            // If rowCount is 0, log and surface a soft error
            $errorInfo = $stmt->errorInfo();
            // Use secure_log to prevent log injection - sanitize PDO error info
            $safeErrorInfo = is_array($errorInfo) ? [
                'code' => $errorInfo[0] ?? null,
                'sqlstate' => $errorInfo[1] ?? null,
                'message' => isset($errorInfo[2]) ? substr($errorInfo[2], 0, 500) : null
            ] : [];
            secure_log('[DONOR_APPT_ERR] Insert returned rowCount=0', $safeErrorInfo);
            throw new Exception("Failed to insert appointment: " . ($errorInfo[2] ?? '')); 
        }
    } catch (Exception $e) {
        // Use secure_log to prevent log injection
        secure_log('[DONOR_APPT_ERR] Exception while scheduling donation', [
            'error' => substr($e->getMessage(), 0, 500),
            'trace' => substr($e->getTraceAsString(), 0, 1000)
        ]);
        $error = "Failed to schedule donation. Error: " . $e->getMessage();
    }
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

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <?php
    // Determine the correct path for CSS files
    $basePath = '';
    if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
        $basePath = '../../';
        echo '<link rel="stylesheet" href="' . $basePath . 'assets/css/dashboard.css">';
    }
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    
    <!-- Shared Donor Dashboard Styles -->
    <?php include_once 'shared-styles.php'; ?>
    
    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>

    <style>
    .dashboard-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0;
        margin-left: 300px; /* Sidebar width */
        padding-top: 100px; /* Space for fixed header */
        position: relative;
        background-color: #f8f9fa;
    }

    .dashboard-header {
        background-color: #fff;
        border-bottom: 1px solid #e9ecef;
        position: fixed;
        top: 0;
        left: 300px; /* Position after sidebar */
        right: 0;
        z-index: 1021;
        height: auto;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
        color: #2c3e50;
        margin: 0;
        font-weight: 600;
        font-size: 1.25rem;
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

    .dashboard-header .breadcrumb {
        margin: 0;
        padding-left: 650px;
        background: transparent;
        font-size: 0.9rem;
    }

    .dashboard-header .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        font-size: 1.2rem;
        line-height: 1;
        vertical-align: middle;
    }

    .dashboard-header .breadcrumb-item a {
        color: #dc3545;
        text-decoration: none;
    }

    .dashboard-header .breadcrumb-item a:hover {
        color: #b02a37;
        text-decoration: none;
    }

    .dashboard-header .breadcrumb-item.active {
        color: #6c757d;
    }

    @media (max-width: 991.98px) {
        .dashboard-content {
            margin-left: 0;
            padding-top: 100px; /* Space for fixed header on mobile */
        }
        
        .dashboard-header {
            left: 0;
            padding: 1rem;
        }
        
        .dashboard-header .page-title {
            font-size: 1.1rem;
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
    }

    .map-container {
        position: relative;
        height: 200px;
        background-color: #f8f9fa;
        border-radius: 0.375rem;
    }

    .map-container .map-fallback {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 1rem;
        background-color: #f8f9fa;
        border-radius: 0.375rem;
    }

    .map-container .map-fallback i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        color: #6c757d;
    }

    .map-error {
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 0.5rem;
    }

    .map-loading {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(255, 255, 255, 0.8);
        z-index: 1;
    }

    .map-loading .spinner-border {
        width: 2rem;
        height: 2rem;
    }

    /* Add this to your <style> section or CSS file */
    @media (min-width: 992px) {
        .guidelines-sticky {
            position: sticky;
            top: 2.5rem; /* Adjust to match your header height + spacing */
            z-index: 10;
            max-height: calc(100vh - 2.5rem - 24px); /* Prevent overflow below viewport */
            overflow-y: auto;
        }
        .guidelines-sticky .card {
            font-size: 1.02rem;
        }
        .guidelines-sticky .card-header h4 {
            font-size: 1.18rem;
        }
        .guidelines-sticky h5 {
            font-size: 1.05rem;
        }
        .guidelines-sticky ul {
            font-size: 1.01rem;
        }
    }

    /* Overall Page Layout Improvements */
    .container {
        max-width: 1200px;
    }
    
    .row {
        margin-left: -15px;
        margin-right: -15px;
    }
    
    .col-lg-8, .col-lg-4 {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    /* Donation Guidelines Styling */
    /* Default stack on small screens; sticky on large screens */
    .guidelines-sticky {
        position: static;
    }

    @media (min-width: 992px) {
        .guidelines-sticky {
            position: sticky;
            top: 1.25rem;
            max-height: calc(100vh - 2.5rem);
            overflow-y: auto;
        }
    }
    /* Scrollbar styling for sticky guidelines */
    .guidelines-sticky::-webkit-scrollbar { width: 8px; }
    .guidelines-sticky::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.15); border-radius: 8px; }
    .guidelines-sticky::-webkit-scrollbar-track { background-color: transparent; }
    
    .guideline-section {
        padding: 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .guideline-section:last-child {
        border-bottom: none;
    }
    
    .guideline-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        flex-shrink: 0;
    }
    
    .guideline-icon {
        background: rgba(220, 53, 69, 0.1);
    }
    
    .guideline-list {
        list-style: none;
        padding-left: 0;
        margin-bottom: 0;
        margin-left: 2.5rem;
    }
    
    .guideline-list li {
        position: relative;
        padding: 0.35rem 0;
        color: #5c6670;
        font-size: 0.95rem;
        line-height: 1.5;
    }
    
    .guideline-list li:before {
        content: "•";
        color: #dc3545;
        font-weight: bold;
        position: absolute;
        left: -0.8rem;
    }
    
    .guideline-section h6 {
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    
    /* Blood Drives Section Improvements */
    .card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }
    
    .card-header {
        border-radius: 12px 12px 0 0 !important;
        border-bottom: none;
        padding: 1.25rem 1.5rem;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    /* Responsive adjustments */
    @media (max-width: 1199px) {
        .guidelines-fixed {
            position: static;
            width: 100%;
            max-width: none;
        }
    }

    /* Blood Drives Styling */
    .blood-drive-item {
        transition: transform 0.2s ease;
    }
    
    .blood-drive-item:hover {
        transform: translateY(-2px);
    }
    
    .blood-drive-item .border {
        border-color: #e9ecef !important;
        transition: border-color 0.2s ease;
    }
    
    .blood-drive-item:hover .border {
        border-color: #dc3545 !important;
    }
    
    .blood-drive-item .shadow-sm {
        transition: box-shadow 0.2s ease;
    }
    
    .blood-drive-item:hover .shadow-sm {
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.15) !important;
    }
    
    .blood-drive-item .badge {
        font-size: 0.8rem;
        padding: 0.3em 0.6em;
        border-radius: 0.5em;
        font-weight: 600;
    }
    /* Softer badge styles for a calmer look */
    .blood-drive-item .badge.bg-danger {
        background-color: rgba(220, 53, 69, 0.12) !important;
        color: #b02a37 !important;
        border: 1px solid rgba(220, 53, 69, 0.25);
    }
    .blood-drive-item .badge.bg-primary {
        background-color: rgba(13, 110, 253, 0.12) !important;
        color: #0a58ca !important;
        border: 1px solid rgba(13, 110, 253, 0.25);
    }
    
    .blood-drive-item .btn-primary {
        background: linear-gradient(90deg, #dc3545 60%, #b02a37 100%);
        border: none;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    
    .blood-drive-item .btn-primary:hover {
        background: linear-gradient(90deg, #b02a37 60%, #dc3545 100%);
        transform: translateY(-1px);
    }

    .blood-drive-item h6 {
        font-weight: 600;
        line-height: 1.3;
    }

    /* Tighten meta spacing and size for clarity */
    .blood-drive-item p.small {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }

    .blood-drive-item .badge.small {
        font-size: 0.72rem;
        padding: 0.25em 0.5em;
    }

    /* Clamp long titles to avoid overflow */
    .blood-drive-item .card-title {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        max-height: 3rem;
        font-size: 0.95rem;
    }

    /* Harmonize card heights for neater grid */
    .blood-drive-item .p-3 { min-height: 200px; }


    </style>
    <style>
    /* Ensure modals appear above sticky headers/containers/sidebar */
    .modal-backdrop { z-index: 4990 !important; position: fixed !important; }
    .modal { z-index: 5000 !important; }
    </style>
</head>
<body<?php echo ($success || !empty($flashSuccessMessage)) ? ' data-success="1"' : ''; ?>>
<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <div class="header-content">
                <h2 class="page-title">Schedule Donation</h2>
                <div class="header-actions">
                    <?php include_once '../../includes/notification_bell.php'; ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar me-2">
                                <i class="bi bi-person-circle fs-4"></i>
                            </div>
                            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Post-Submit Thank You Modal -->
            <div class="modal fade" id="thankYouModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Thank You</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">
                                <?php 
                                  $msg = $flashSuccessMessage ?: ($successMessage ?? 'Thank you for donating your blood and helping maintain a safe blood supply.');
                                  echo htmlspecialchars($msg);
                                ?>
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Eligibility Reply Modal -->
            <div class="modal fade" id="eligibilityReplyModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="eligibilityReplyTitle">Eligibility Result</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="eligibilityReplyIcon" class="text-center mb-3"></div>
                            <div id="eligibilityReplyMessage"></div>
                        </div>
                        <div class="modal-footer" id="eligibilityReplyFooter">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-main p-3">

            <?php if ($success): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" data-auto-dismiss="5000">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo isset($successMessage) && $successMessage ? $successMessage : 'Your donation appointment has been scheduled successfully. Thank you for helping maintain a safe blood supply.'; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" data-auto-dismiss="5000">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Two-column layout: Form (left), Guidelines (right) -->
            <div class="row justify-content-center">
                <!-- Main Column - Full Width (Guidelines moved to modal) -->
                <div class="col-lg-12 mb-4">
                    <!-- Blood Drives List -->
                    <div class="mb-3">
                        <h4 class="text-center mb-4"><i class="bi bi-calendar-event me-2 text-info"></i>UPCOMING BLOOD DRIVES</h4>
                    </div>
                    <!-- Filters Toolbar -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-3">
                            <form method="GET" action="" class="row g-2 align-items-center">
                                <div class="col-12 col-md-4">
                                    <label for="orgFilter" class="form-label small text-muted mb-1">Organization</label>
                                    <select class="form-select form-select-sm" id="orgFilter" name="org_filter">
                                        <option value="all" <?php echo $orgFilter==='all'?'selected':''; ?>>All Organizations</option>
                                        <option value="redcross" <?php echo $orgFilter==='redcross'?'selected':''; ?>>Red Cross</option>
                                        <option value="negrosfirst" <?php echo $orgFilter==='negrosfirst'?'selected':''; ?>>Negros First</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="dateFilter" class="form-label small text-muted mb-1">Date</label>
                                    <select class="form-select form-select-sm" id="dateFilter" name="date_filter">
                                        <option value="all" <?php echo $dateFilter==='all'?'selected':''; ?>>All Upcoming</option>
                                        <option value="week" <?php echo $dateFilter==='week'?'selected':''; ?>>Next 7 Days</option>
                                        <option value="month" <?php echo $dateFilter==='month'?'selected':''; ?>>Next 30 Days</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-5">
                                    <label for="locationFilter" class="form-label small text-muted mb-1">Location</label>
                                    <input type="text" class="form-control form-control-sm" id="locationFilter" name="location" placeholder="Search by location" value="<?php echo htmlspecialchars($locationFilter); ?>">
                                </div>
                            </form>
                        </div>
                    </div>
                    <div>
                        <?php if (empty($upcomingDrives)): ?>
                            <div class="alert alert-info" role="alert">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                No blood drives found matching your criteria. Please try different filters or check back later.
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($upcomingDrives as $drive): ?>
                                    <div class="col-md-6 col-xl-4 mb-4">
                                        <div class="blood-drive-item" data-organization-type="<?php echo htmlspecialchars($drive['organization_type']); ?>" data-organization-id="<?php echo htmlspecialchars($drive['organization_id']); ?>">
                                            <div class="p-3 border rounded bg-white shadow-sm">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0 small card-title"><?php echo htmlspecialchars($drive['title']); ?></h6>
                                                    <span class="badge <?php echo $drive['organization_type'] === 'redcross' ? 'bg-danger' : 'bg-primary'; ?> small">
                                                        <?php
                                                            echo htmlspecialchars(
                                                                $drive['organization_type'] === 'redcross' ? 'Red Cross' :
                                                                ($drive['organization_type'] === 'negrosfirst' ? 'Negros First' :
                                                                ($drive['organization_type'] === 'barangay' ? 'Barangay' :
                                                                ucfirst($drive['organization_type'] ?? '')))
                                                            );
                                                        ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <p class="mb-1 small">
                                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                                        <?php echo htmlspecialchars($drive['location']); ?>
                                                    </p>
                                                    <p class="mb-1 small">
                                                        <i class="bi bi-calendar-event text-danger me-1"></i>
                                                        <?php echo date('M d, Y', strtotime($drive['date'])); ?>
                                                    </p>
                                                    <p class="mb-1 small">
                                                        <i class="bi bi-clock-fill text-info me-1"></i>
                                                        <?php echo date('h:i A', strtotime($drive['start_time'])); ?>
                                                    </p>
                                                </div>

                                                <!-- Register Button -->
                                                <button class="btn btn-primary btn-sm w-100 register-btn" 
                                                        data-drive-id="<?php echo $drive['id']; ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#guidelinesModal">
                                                    <i class="bi bi-calendar-check me-1"></i>Register
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- End Two-column layout -->

            <!-- Drive Preview Modal -->
            <div class="modal fade" id="previewModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Drive Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Drive Information</h6>
                                    <p class="mb-1"><strong>Title:</strong> <span id="previewTitle"></span></p>
                                    <p class="mb-1"><strong>Venue:</strong> <span id="previewVenue"></span></p>
                                    <p class="mb-1"><strong>Address:</strong> <span id="previewAddress"></span></p>
                                    <p class="mb-1"><strong>Date:</strong> <span id="previewDate"></span></p>
                                    <p class="mb-1"><strong>Time:</strong> <span id="previewTime"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-3">Requirements</h6>
                                    <div id="previewRequirements" class="small"></div>
                                </div>
                            </div>
                            
                            <!-- Map View -->
                            <div class="mt-3">
                                <h6 class="mb-2">Location</h6>
                                <div class="map-container">
                                    <div id="previewMap" style="height: 100%;"></div>
                                    <div class="map-loading d-none">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="map-error d-none"></div>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-primary w-100" id="proceedToRegister">
                                    <i class="bi bi-calendar-check me-2"></i>Proceed to Registration
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registration Modal -->
            <div class="modal fade" id="registrationModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Register for Blood Donation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning d-flex align-items-center py-2 px-3 small" role="alert">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Please make sure you meet the eligibility requirements. 
                                <a href="#" id="openGuidelinesFromRegistration" class="ms-2 fw-semibold">View Guidelines</a>
                            </div>
                            <form method="POST" action="" id="donationForm">
                                <input type="hidden" name="drive_id" id="selected_drive_id" value="">
                                <input type="hidden" name="donation_date" id="selected_date" value="">
                                <input type="hidden" name="appointment_time" id="selected_time" value="">
                                <input type="hidden" name="location" id="selected_location" value="">
                                <input type="hidden" name="drive_title" id="selected_drive_title" value="">
                                <input type="hidden" name="organization_type" id="selected_organization_type" value="">
                                <input type="hidden" name="organization_id" id="selected_organization_id" value="">
                                <!-- Hidden fields to carry interview data -->
                                <input type="hidden" name="interview_status" id="interview_status">
                                <input type="hidden" name="interview_responses" id="interview_responses">
                                <input type="hidden" name="interview_deferrals" id="interview_deferrals">
                                <input type="hidden" name="interview_reasons" id="interview_reasons">
                                <!-- Persisted interview id (created via AJAX) -->
                                <input type="hidden" name="interview_id" id="interview_id">

                                <div class="mb-3">
                                    <label for="blood_type" class="form-label">Blood Type</label>
                                    <select class="form-select" id="blood_type" name="blood_type" required>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                        <option value="Unknown">Unknown</option>
                                    </select>
                                </div>

                                <!-- III. DONOR'S DECLARATION -->
                                <div class="mb-3">
                                    <h6 class="form-label fw-semibold">DONOR'S DECLARATION</h6>
                                    <div class="border rounded p-3 small" style="max-height: 220px; overflow:auto; background:#fff;">
                                        <p class="mb-2">I certify that I am the person referred to above and that all the entries are read and well understood by me and to the best of my knowledge truthfully answered all the questions in the blood donation interview sheet.</p>
                                        <p class="mb-2">I understand that all questions are pertinent for my safety and for the benefit of the patient who will undergo blood transfusion.</p>
                                        <p class="mb-2">I am voluntarily giving my blood through the Philippine Red Cross without remuneration, for the use of persons in need of this vital fluid without regard to rank, race, color, creed, religion, or political persuasion.</p>
                                        <p class="mb-2">I understand that my blood will be screened for malaria, syphilis, hepatitis B, hepatitis C, and HIV. I am aware that the screening tests are not diagnostic and may yield false positive results. Should any of the screening tests give a reactive result, I authorize the Red Cross to advise me utilizing the information I have supplied, subject the results to confirmatory tests, offer counseling and to dispose of my donated blood in any way it may deem advisable for the safety of the majority of the populace.</p>
                                        <p class="mb-2">I confirm that I am over the age of 18 years.</p>
                                        <p class="mb-0">I understand that all information hereinto is treated confidential in compliance with the Data Privacy Act of 2012. I therefore authorize the Philippine Red Cross to utilize the information I supplied for purposes of research or studies for the benefit and safety of the community.</p>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="declaration_ack" required>
                                        <label class="form-check-label" for="declaration_ack">I have read, understood, and agree to the Donor’s Declaration.</label>
                                    </div>
                                </div>

        

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="consent" required>
                                    <label class="form-check-label" for="consent">
                                        I confirm that I am in good health and eligible to donate blood.
                                    </label>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-calendar-check me-2"></i>Confirm Registration
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirmation Modal -->
            <div class="modal fade" id="confirmationModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirm Your Registration</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center mb-4">
                                <i class="bi bi-check-circle-fill text-danger" style="font-size: 3rem;"></i>
                            </div>
                            <p>Please confirm your registration details:</p>
                            <div id="confirmationDetails"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmRegistration">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interview Modal -->
            <div class="modal fade" id="interviewModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Donor Interview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="interviewForm">
                                <div id="eligibilityResult" class="alert d-none" role="alert"></div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="border rounded p-3 h-100">
                                            <p class="small text-muted mb-2">MEDICAL HISTORY (Please read carefully and answer all relevant questions. Tick (✓) Yes or No.)</p>
                                            <fieldset class="mb-2">
                                                <legend class="form-label d-block"><strong>1.</strong> Do you feel well and healthy today?</legend>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q1" id="q1_yes" value="yes" required>
                                                    <label class="form-check-label" for="q1_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q1" id="q1_no" value="no">
                                                    <label class="form-check-label" for="q1_no">No</label>
                                                </div>
                                            </fieldset>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>2.</strong> Have you ever been refused as a blood donor or told not to donate blood for any reasons?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q2" id="q2_yes" value="yes" required>
                                                    <label class="form-check-label" for="q2_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q2" id="q2_no" value="no">
                                                    <label class="form-check-label" for="q2_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>3.</strong> Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q3" id="q3_yes" value="yes" required>
                                                    <label class="form-check-label" for="q3_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q3" id="q3_no" value="no">
                                                    <label class="form-check-label" for="q3_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>4.</strong> Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q4" id="q4_yes" value="yes" required>
                                                    <label class="form-check-label" for="q4_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q4" id="q4_no" value="no">
                                                    <label class="form-check-label" for="q4_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>5.</strong> Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q5" id="q5_yes" value="yes" required>
                                                    <label class="form-check-label" for="q5_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q5" id="q5_no" value="no">
                                                    <label class="form-check-label" for="q5_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>6.</strong> In the last 3 DAYS have you taken aspirin?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q6" id="q6_yes" value="yes" required>
                                                    <label class="form-check-label" for="q6_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q6" id="q6_no" value="no">
                                                    <label class="form-check-label" for="q6_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>7.</strong> In the past 3 MONTHS have you donated whole blood, platelets or plasma?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q7" id="q7_yes" value="yes" required>
                                                    <label class="form-check-label" for="q7_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q7" id="q7_no" value="no">
                                                    <label class="form-check-label" for="q7_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>8.</strong> In the past 4 WEEKS have you taken any medications and/or vaccinations?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q8" id="q8_yes" value="yes" required>
                                                    <label class="form-check-label" for="q8_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q8" id="q8_no" value="no">
                                                    <label class="form-check-label" for="q8_no">No</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="border rounded p-3 h-100">
                                            <div class="mb-2 fw-semibold">IN THE PAST 6 MONTHS HAVE YOU</div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>9.</strong> Been to any places in the Philippines or countries infected with ZIKA Virus?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q9" id="q9_yes" value="yes" required>
                                                    <label class="form-check-label" for="q9_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q9" id="q9_no" value="no">
                                                    <label class="form-check-label" for="q9_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>10.</strong> Had sexual contact with a person who was confirmed to have ZIKA Virus infection?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q10" id="q10_yes" value="yes" required>
                                                    <label class="form-check-label" for="q10_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q10" id="q10_no" value="no">
                                                    <label class="form-check-label" for="q10_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>11.</strong> Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q11" id="q11_yes" value="yes" required>
                                                    <label class="form-check-label" for="q11_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q11" id="q11_no" value="no">
                                                    <label class="form-check-label" for="q11_no">No</label>
                                                </div>
                                            </div>

                                            <div class="mb-2 fw-semibold mt-3">IN THE PAST 12 MONTHS HAVE YOU</div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>12.</strong> Received blood, blood products and/or had tissue/organ transplant or graft?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q12" id="q12_yes" value="yes" required>
                                                    <label class="form-check-label" for="q12_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q12" id="q12_no" value="no">
                                                    <label class="form-check-label" for="q12_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>13.</strong> Had surgical operation or dental extraction?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q13" id="q13_yes" value="yes" required>
                                                    <label class="form-check-label" for="q13_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q13" id="q13_no" value="no">
                                                    <label class="form-check-label" for="q13_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>14.</strong> Had a tattoo applied, ear and body piercing, acupuncture, needle stick injury or accidental contact with blood?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q14" id="q14_yes" value="yes" required>
                                                    <label class="form-check-label" for="q14_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q14" id="q14_no" value="no">
                                                    <label class="form-check-label" for="q14_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>15.</strong> Had sexual contact with high risks individuals or in exchange for material or monetary gain?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q15" id="q15_yes" value="yes" required>
                                                    <label class="form-check-label" for="q15_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q15" id="q15_no" value="no">
                                                    <label class="form-check-label" for="q15_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>16.</strong> Engaged in unprotected, unsafe or casual sex?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q16" id="q16_yes" value="yes" required>
                                                    <label class="form-check-label" for="q16_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q16" id="q16_no" value="no">
                                                    <label class="form-check-label" for="q16_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>17.</strong> Had jaundice/hepatitis/personal contact with person who had hepatitis?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q17" id="q17_yes" value="yes" required>
                                                    <label class="form-check-label" for="q17_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q17" id="q17_no" value="no">
                                                    <label class="form-check-label" for="q17_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>18.</strong> Been incarcerated, jailed or imprisoned?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q18" id="q18_yes" value="yes" required>
                                                    <label class="form-check-label" for="q18_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q18" id="q18_no" value="no">
                                                    <label class="form-check-label" for="q18_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>19.</strong> Spent time or have relatives in the United Kingdom or Europe?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q19" id="q19_yes" value="yes" required>
                                                    <label class="form-check-label" for="q19_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q19" id="q19_no" value="no">
                                                    <label class="form-check-label" for="q19_no">No</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="border rounded p-3">
                                            <div class="mb-2 fw-semibold">HAVE YOU EVER</div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>20.</strong> Travelled or lived outside of your place of residence or outside the Philippines?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q20" id="q20_yes" value="yes" required>
                                                    <label class="form-check-label" for="q20_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q20" id="q20_no" value="no">
                                                    <label class="form-check-label" for="q20_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>21.</strong> Taken prohibited drugs (orally, by nose, or by injection)?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q21" id="q21_yes" value="yes" required>
                                                    <label class="form-check-label" for="q21_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q21" id="q21_no" value="no">
                                                    <label class="form-check-label" for="q21_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>22.</strong> Used clotting factor concentrates?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q22" id="q22_yes" value="yes" required>
                                                    <label class="form-check-label" for="q22_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q22" id="q22_no" value="no">
                                                    <label class="form-check-label" for="q22_no">No</label>
                                                </div>
                                            </div>
                        

                                    <!-- Extend HAVE YOU EVER section with 23-25 -->
                                    <div class="col-12">
                                        <div class="border rounded p-3">
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>23.</strong> Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q23" id="q23_yes" value="yes" required>
                                                    <label class="form-check-label" for="q23_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q23" id="q23_no" value="no">
                                                    <label class="form-check-label" for="q23_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>24.</strong> Had Malaria or Hepatitis in the past?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q24" id="q24_yes" value="yes" required>
                                                    <label class="form-check-label" for="q24_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q24" id="q24_no" value="no">
                                                    <label class="form-check-label" for="q24_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>25.</strong> Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q25" id="q25_yes" value="yes" required>
                                                    <label class="form-check-label" for="q25_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q25" id="q25_no" value="no">
                                                    <label class="form-check-label" for="q25_no">No</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- HAD ANY OF THE FOLLOWING 27-33 -->
                                    <div class="col-12">
                                        <div class="border rounded p-3">
                                            <div class="mb-2 fw-semibold">HAD ANY OF THE FOLLOWING</div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>27.</strong> Cancer, blood disease or bleeding disorder (haemophilia)?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q27" id="q27_yes" value="yes" required>
                                                    <label class="form-check-label" for="q27_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q27" id="q27_no" value="no">
                                                    <label class="form-check-label" for="q27_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>28.</strong> Heart disease/surgery, rheumatic fever or chest pains?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q28" id="q28_yes" value="yes" required>
                                                    <label class="form-check-label" for="q28_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q28" id="q28_no" value="no">
                                                    <label class="form-check-label" for="q28_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>29.</strong> Lung disease, tuberculosis or asthma?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q29" id="q29_yes" value="yes" required>
                                                    <label class="form-check-label" for="q29_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q29" id="q29_no" value="no">
                                                    <label class="form-check-label" for="q29_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>30.</strong> Kidney disease, thyroid disease, diabetes, epilepsy?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q30" id="q30_yes" value="yes" required>
                                                    <label class="form-check-label" for="q30_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q30" id="q30_no" value="no">
                                                    <label class="form-check-label" for="q30_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>31.</strong> Chicken pox and/or cold sores?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q31" id="q31_yes" value="yes" required>
                                                    <label class="form-check-label" for="q31_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q31" id="q31_no" value="no">
                                                    <label class="form-check-label" for="q31_no">No</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block"><strong>32.</strong> Any other chronic medical condition or surgical operations?</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q32" id="q32_yes" value="yes" required>
                                                    <label class="form-check-label" for="q32_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q32" id="q32_no" value="no">
                                                    <label class="form-check-label" for="q32_no">No</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- FOR FEMALE DONORS ONLY 34-37 -->
                                    <div class="col-12">
                                        <div class="border rounded p-3">
                                            <div class="mb-2 fw-semibold">FOR FEMALE DONORS ONLY</div>
                                            <fieldset class="mb-2">
                                                <legend class="form-label d-block">Are you female?</legend>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="is_female" id="is_female_yes" value="yes">
                                                    <label class="form-check-label" for="is_female_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="is_female" id="is_female_no" value="no" checked>
                                                    <label class="form-check-label" for="is_female_no">No</label>
                                                </div>
                                            </fieldset>
                                            <fieldset class="mb-2 female-only d-none">
                                                <legend class="form-label d-block"><strong>34.</strong> Are you currently pregnant or have you ever been pregnant?</legend>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q34_current_pregnant" id="q34_yes" value="yes">
                                                    <label class="form-check-label" for="q34_yes">Currently pregnant</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q34_current_pregnant" id="q34_no" value="no">
                                                    <label class="form-check-label" for="q34_no">Not currently pregnant</label>
                                                </div>
                                            </fieldset>
                                            <div class="mb-2 female-only d-none">
                                                <label class="form-label d-block"><strong>35.</strong> When was your last childbirth?</label>
                                                <input type="text" class="form-control" name="q35_last_childbirth" placeholder="Enter date (if applicable)">
                                                <div class="form-text">In the past 1 YEAR, did you have a miscarriage or abortion?</div>
                                                <div class="mt-1">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="q35_miscarriage_1y" id="q35_miscarriage_yes" value="yes">
                                                        <label class="form-check-label" for="q35_miscarriage_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="q35_miscarriage_1y" id="q35_miscarriage_no" value="no">
                                                        <label class="form-check-label" for="q35_miscarriage_no">No</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <fieldset class="mb-2 female-only d-none">
                                                <legend class="form-label d-block"><strong>36.</strong> Are you currently breastfeeding?</legend>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q36_breastfeeding" id="q36_yes" value="yes">
                                                    <label class="form-check-label" for="q36_yes">Yes</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="q36_breastfeeding" id="q36_no" value="no">
                                                    <label class="form-check-label" for="q36_no">No</label>
                                                </div>
                                            </fieldset>
                                            <div class="mb-2 female-only d-none">
                                                <label class="form-label d-block"><strong>37.</strong> When was your last menstrual period? DATE:</label>
                                                <input type="date" class="form-control" name="q37_lmp_date">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid mt-3">
                                    <button type="submit" class="btn btn-danger">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guidelines Modal (shown before registration) -->
            <div class="modal fade" id="guidelinesModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Guidelines for Blood Donation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="container-fluid">
                                <div class="row">
                                    <div class="col-12">
                                        <!-- BEFORE DONATING -->
                                        <div class="guideline-section mb-3 pb-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <div class="guideline-icon me-2">
                                                    <i class="bi bi-clipboard-check-fill text-danger"></i>
                                                </div>
                                                <h6 class="mb-0 fw-bold text-dark">Before Donating</h6>
                                            </div>
                                            <ul class="guideline-list">
                                                <li>Get at least 6 hours of quality sleep.</li>
                                                <li>Drink plenty of water; avoid caffeine and carbonated drinks for 5 hours.</li>
                                                <li>Eat breakfast, but avoid eating for 2 hours before donation.</li>
                                                <li>Be in good health with no signs of illness (cough, fever, sore throat).</li>
                                                <li>Do not smoke or drink alcohol the day before.</li>
                                                <li>Wait at least 12 months after any piercings or tattoos.</li>
                                                <li>Wait at least 12 months after any tooth extraction or surgical operation.</li>
                                                <li>Avoid unsafe sexual practices.</li>
                                                <li>Women should not be pregnant or breastfeeding.</li>
                                                <li>Wait 4 weeks after any vaccination.</li>
                                                <li>Avoid taking aspirin for 3 days.</li>
                                            </ul>
                                        </div>

                                        <!-- ON THE DAY OF DONATION -->
                                        <div class="guideline-section mb-3 pb-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <div class="guideline-icon me-2">
                                                    <i class="bi bi-calendar2-check-fill text-danger"></i>
                                                </div>
                                                <h6 class="mb-0 fw-bold text-dark">On the Day of Donation</h6>
                                            </div>
                                            <ul class="guideline-list">
                                                <li>If taking anti-hypertensive medication, ensure your blood pressure is within normal limits.</li>
                                                <li>You cannot donate if you are currently taking antibiotics.</li>
                                                <li>Menstruating women can donate if they pass the hemoglobin test.</li>
                                                <li>16-year-olds can donate with written parental consent.</li>
                                                <li>Individuals who recently traveled outside the Philippines (e.g., US, UK) or to endemic areas within the Philippines (like Palawan) may have a temporary deferral of 6 months.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="ackGuidelines">
                                <label class="form-check-label small" for="ackGuidelines">I have read and understand the donation guidelines</label>
                            </div>
                            <div>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary" id="proceedFromGuidelines" disabled>Proceed to Registration</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Move modals to body to avoid stacking/overflow contexts within dashboard containers
    ['guidelinesModal','interviewModal','registrationModal','confirmationModal','previewModal','eligibilityReplyModal','thankYouModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
    });

    // If the server marked success, show the Thank You modal immediately
    if (document.body.dataset.success === '1') {
        setTimeout(() => {
            try { showModalById('thankYouModal'); } catch(_) {}
        }, 0);
    }
    let previewMap = null;
    let previewMarker = null;
    let mapErrorCount = 0;
    const MAX_MAP_ERRORS = 3;

    // Ensure Bootstrap is available; if not, load it dynamically
    function ensureBootstrap(cb){
        if (window.bootstrap && typeof window.bootstrap.Modal === 'function') { cb(); return; }
        const existing = document.querySelector('script[src*="bootstrap.bundle"]');
        if (existing) { existing.addEventListener('load', () => cb()); return; }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
        s.onload = () => cb();
        s.onerror = () => { console.error('Failed to load Bootstrap bundle'); };
        document.body.appendChild(s);
    }

    // Helper: robustly show a Bootstrap modal by ID
    function showModalById(id) {
        const el = document.getElementById(id);
        if (!el) { console.warn('Modal element not found:', id); return; }
        ensureBootstrap(() => {
            try {
                // Hide any open modals and clear backdrops to prevent stacking issues
                document.querySelectorAll('.modal.show').forEach(m => {
                    try { bootstrap.Modal.getInstance(m)?.hide(); } catch(_) {}
                });
                document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                const m = bootstrap.Modal.getOrCreateInstance(el);
                m.show();
                // Ensure viewport is at top for visibility
                window.scrollTo({ top: 0, behavior: 'smooth' });
                console.debug('Modal shown:', id);
            } catch (e) {
                console.error('Error showing modal', id, e);
            }
        });
    }

    // Initialize preview map
    function initPreviewMap(lat, lng, title) {
        const mapContainer = document.getElementById('previewMap');
        const loadingSpinner = mapContainer.parentElement.querySelector('.map-loading');
        const errorDiv = mapContainer.parentElement.querySelector('.map-error');
        
        // Show loading spinner
        loadingSpinner.classList.remove('d-none');
        errorDiv.classList.add('d-none');

        try {
            const location = { lat: parseFloat(lat), lng: parseFloat(lng) };
            
            if (!previewMap) {
                previewMap = new google.maps.Map(mapContainer, {
                    zoom: 15,
                    center: location,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false
                });
            } else {
                previewMap.setCenter(location);
            }

            if (previewMarker) {
                previewMarker.setMap(null);
            }

            previewMarker = new google.maps.Marker({
                position: location,
                map: previewMap,
                title: title,
                animation: google.maps.Animation.DROP
            });

            // Hide loading spinner
            loadingSpinner.classList.add('d-none');
        } catch (error) {
            console.error('Map initialization error:', error);
            mapErrorCount++;
            
            // Show error message
            errorDiv.textContent = 'Failed to load map. Please try again.';
            errorDiv.classList.remove('d-none');
            loadingSpinner.classList.add('d-none');

            // If too many errors, show fallback
            if (mapErrorCount >= MAX_MAP_ERRORS) {
                mapContainer.parentElement.querySelector('.map-fallback').classList.remove('d-none');
                mapContainer.style.display = 'none';
            }
        }
    }

    // Global maps initialization function
    window.initMaps = function() {
        // Reset error count when maps are successfully loaded
        mapErrorCount = 0;
        
        // Initialize any existing maps
        const previewBtn = document.querySelector('.preview-btn');
        if (previewBtn) {
            const data = previewBtn.dataset;
            if (data.latitude && data.longitude) {
                initPreviewMap(data.latitude, data.longitude, data.venue);
            }
        }
    };

    // Handle map loading errors
    window.gm_authFailure = function() {
        console.error('Google Maps authentication failed');
        const errorDiv = document.querySelector('.map-error');
        if (errorDiv) {
            errorDiv.textContent = 'Map authentication failed. Please contact support.';
            errorDiv.classList.remove('d-none');
        }
    };

    // Attach event listeners for preview buttons
    document.querySelectorAll('.preview-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            document.getElementById('previewTitle').textContent = data.driveTitle;
            document.getElementById('previewVenue').textContent = data.venue;
            document.getElementById('previewAddress').textContent = data.address;
            document.getElementById('previewDate').textContent = new Date(data.date).toLocaleDateString('en-US', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
            document.getElementById('previewTime').textContent = new Date('1970-01-01T' + data.time).toLocaleTimeString('en-US', {
                hour: 'numeric', minute: '2-digit'
            });
            document.getElementById('previewRequirements').innerHTML = data.requirements || 'No specific requirements';
            document.getElementById('proceedToRegister').setAttribute('data-drive-id', data.driveId);
        });
    });

    // Keep a reference to the last selected drive card
    let currentDriveCard = null;
    
    // Function to reset interview form (defined to prevent errors)
    function resetInterviewForm() {
        // Reset interview form fields if needed
        const interviewForm = document.getElementById('interviewForm');
        if (interviewForm) {
            interviewForm.reset();
        }
        // Clear interview-related hidden fields in donation form
        const interviewStatus = document.getElementById('interview_status');
        const interviewResponses = document.getElementById('interview_responses');
        const interviewDeferrals = document.getElementById('interview_deferrals');
        const interviewReasons = document.getElementById('interview_reasons');
        const interviewId = document.getElementById('interview_id');
        if (interviewStatus) interviewStatus.value = '';
        if (interviewResponses) interviewResponses.value = '';
        if (interviewDeferrals) interviewDeferrals.value = '';
        if (interviewReasons) interviewReasons.value = '';
        if (interviewId) interviewId.value = '';
    }
    // Helper to convert 'Aug 24, 2025' -> '2025-08-24' (no timezone shift)
    function parseDisplayDate(str) {
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const m = str.trim().match(/^(\w{3})\s+(\d{1,2}),\s*(\d{4})$/);
        if (!m) return '';
        const month = String(months.indexOf(m[1]) + 1).padStart(2, '0');
        const day = String(Number.parseInt(m[2], 10)).padStart(2, '0');
        const year = m[3];
        return `${year}-${month}-${day}`;
    }

    // Helper to convert '08:00 AM' -> '08:00:00' (24h)
    function to24HHMMSS(tStr) {
        const m = tStr.trim().match(/^(\d{1,2}):(\d{2})\s*([AP]M)$/i);
        if (!m) return '';
        let h = parseInt(m[1], 10);
        const min = m[2];
        const ampm = m[3].toUpperCase();
        if (ampm === 'PM' && h !== 12) h += 12;
        if (ampm === 'AM' && h === 12) h = 0;
        const hh = String(h).padStart(2, '0');
        return `${hh}:${min}:00`;
    }

    // Attach event listeners for register buttons
    const registerButtons = document.querySelectorAll('.register-btn');
    registerButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Reset interview state when starting a fresh registration
            resetInterviewForm();
            
            const driveId = this.getAttribute('data-drive-id');
            const driveCard = this.closest('.blood-drive-item');
            
            if (!driveId || !driveCard) {
                console.error('Invalid drive selection - missing driveId or driveCard');
                alert('Error: Cannot register. Please try again.');
                return false;
            }
            
            // Store the selected drive card for later use
            currentDriveCard = driveCard;
            
            // Get all drive information from the card
            const orgType = driveCard.getAttribute('data-organization-type') || '';
            const orgId = driveCard.getAttribute('data-organization-id') || '';
            const driveDate = driveCard.querySelector('.bi-calendar-event')?.nextSibling?.textContent?.trim() || '';
            const driveTime = driveCard.querySelector('.bi-clock-fill')?.nextSibling?.textContent?.trim() || '';
            const venueText = driveCard.querySelector('.bi-geo-alt-fill')?.nextSibling?.textContent?.trim() || '';
            const driveTitle = driveCard.querySelector('.card-title')?.textContent?.trim() || '';
            
            // Set all form fields immediately
            document.getElementById('selected_drive_id').value = driveId;
            document.getElementById('selected_organization_type').value = orgType;
            document.getElementById('selected_organization_id').value = orgId;
            document.getElementById('selected_date').value = parseDisplayDate(driveDate);
            document.getElementById('selected_time').value = to24HHMMSS(driveTime);
            document.getElementById('selected_location').value = venueText;
            document.getElementById('selected_drive_title').value = driveTitle;
            
            // Log for debugging
            console.log('Registered for drive:', {
                driveId: driveId,
                organizationType: orgType,
                organizationId: orgId,
                date: driveDate,
                title: driveTitle
            });
            
            // Update modal title
            const modalTitle = document.querySelector('#registrationModal .modal-title');
            if (modalTitle) {
                modalTitle.innerHTML = `Register for Blood Donation<br><small class="text-muted">${driveTitle}<br>${driveDate} at ${driveTime}</small>`;
            }
            
            // Slight delay to ensure DOM updates complete
            setTimeout(() => showModalById('guidelinesModal'), 0);
        });
    });

    // Also add event delegation as a safety net (in case buttons are re-rendered)
    // Note: This should not normally fire since we have direct listeners, but it's a backup
    document.addEventListener('click', function(e) {
        const t = e.target.closest('.register-btn');
        if (!t) return;
        
        // Prevent double-firing if the direct listener already handled it
        if (t.dataset.handled) return;
        t.dataset.handled = 'true';
        
        resetInterviewForm();
        const driveCard = t.closest('.blood-drive-item');
        if (driveCard) {
            currentDriveCard = driveCard;
            const driveId = t.getAttribute('data-drive-id');
            const orgType = driveCard.getAttribute('data-organization-type') || '';
            const orgId = driveCard.getAttribute('data-organization-id') || '';
            const driveDate = driveCard.querySelector('.bi-calendar-event')?.nextSibling?.textContent?.trim() || '';
            const driveTime = driveCard.querySelector('.bi-clock-fill')?.nextSibling?.textContent?.trim() || '';
            
            // Set all form fields
            document.getElementById('selected_organization_type').value = orgType;
            document.getElementById('selected_organization_id').value = orgId;
            document.getElementById('selected_drive_id').value = driveId;
            document.getElementById('selected_date').value = parseDisplayDate(driveDate);
            document.getElementById('selected_time').value = to24HHMMSS(driveTime);
            const venueText = driveCard.querySelector('.bi-geo-alt-fill')?.nextSibling?.textContent?.trim() || '';
            document.getElementById('selected_location').value = venueText;
            
            console.log('Event delegation - set drive info:', {
                driveId: driveId,
                organizationType: orgType,
                organizationId: orgId
            });
        }
        
        // Clear the flag after a short delay
        setTimeout(() => {
            if (t.dataset) delete t.dataset.handled;
        }, 100);
    });

    // Handle proceed to registration (from preview modal) - guard in case preview is not used
    const proceedFromPreview = document.getElementById('proceedToRegister');
    if (proceedFromPreview) {
        proceedFromPreview.addEventListener('click', function() {
            const driveId = this.getAttribute('data-drive-id');
            const driveCard = document.querySelector(`.register-btn[data-drive-id="${driveId}"]`).closest('.blood-drive-item');
            
            // Close preview modal if exists
            const previewModalEl = document.getElementById('previewModal');
            if (previewModalEl) {
                const pm = bootstrap.Modal.getInstance(previewModalEl);
                if (pm) pm.hide();
            }
            
            // Trigger guidelines modal via the same register button
            const registerBtn = driveCard.querySelector('.register-btn');
            if (registerBtn) registerBtn.click();
        });
    }

    // Handle form submission with confirmation
    const form = document.getElementById('donationForm');
    const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // CRITICAL: First, try to restore from currentDriveCard if available (most reliable source)
        if (currentDriveCard) {
            const cardDriveId = currentDriveCard.querySelector('.register-btn')?.getAttribute('data-drive-id') || '';
            const cardOrgType = currentDriveCard.getAttribute('data-organization-type') || '';
            const cardOrgId = currentDriveCard.getAttribute('data-organization-id') || '';
            
            if (cardDriveId && cardOrgType && cardOrgId) {
                // Restore all form fields from the card
                document.getElementById('selected_drive_id').value = cardDriveId;
                document.getElementById('selected_organization_type').value = cardOrgType;
                document.getElementById('selected_organization_id').value = cardOrgId;
                
                const driveDate = currentDriveCard.querySelector('.bi-calendar-event')?.nextSibling?.textContent?.trim();
                const driveTime = currentDriveCard.querySelector('.bi-clock-fill')?.nextSibling?.textContent?.trim();
                if (driveDate) {
                document.getElementById('selected_date').value = parseDisplayDate(driveDate);
                }
                if (driveTime) {
                document.getElementById('selected_time').value = to24HHMMSS(driveTime);
                }
                const venueText = currentDriveCard.querySelector('.bi-geo-alt-fill')?.nextSibling?.textContent?.trim();
                if (venueText) {
                document.getElementById('selected_location').value = venueText;
            }
                const driveTitle = currentDriveCard.querySelector('.card-title')?.textContent?.trim();
                if (driveTitle) {
                    document.getElementById('selected_drive_title').value = driveTitle;
                }
                
                console.log('Restored drive info from currentDriveCard:', {
                    driveId: cardDriveId,
                    organizationType: cardOrgType,
                    organizationId: cardOrgId
                });
            }
        }
        
        // Get form field values using proper form element access
        const formDriveId = this.querySelector('[name="drive_id"]')?.value || '';
        const formOrgType = this.querySelector('[name="organization_type"]')?.value || '';
        const formOrgId = this.querySelector('[name="organization_id"]')?.value || '';
        
        // Alternative: try by ID
        const idDriveId = document.getElementById('selected_drive_id')?.value || '';
        const idOrgType = document.getElementById('selected_organization_type')?.value || '';
        const idOrgId = document.getElementById('selected_organization_id')?.value || '';
        
        // Use whichever has a value
        const driveId = formDriveId || idDriveId;
        const orgType = formOrgType || idOrgType;
        const orgId = formOrgId || idOrgId;
        
        // If drive ID is missing, prevent submission and alert user
        if (!driveId || !orgType || !orgId) {
            alert('Error: Blood drive information is missing. Please close this form and click the "Register" button on the blood drive you want to register for again.');
            console.error('Form submission blocked - missing drive information:', {
                formDriveId: formDriveId,
                formOrgType: formOrgType,
                formOrgId: formOrgId,
                idDriveId: idDriveId,
                idOrgType: idOrgType,
                idOrgId: idOrgId,
                currentDriveCard: currentDriveCard ? 'exists' : 'null'
            });
            return false;
        }
        
        // Ensure form fields are set before submission
        this.querySelector('[name="drive_id"]').value = driveId;
        this.querySelector('[name="organization_type"]').value = orgType;
        this.querySelector('[name="organization_id"]').value = orgId;
        document.getElementById('selected_drive_id').value = driveId;
        document.getElementById('selected_organization_type').value = orgType;
        document.getElementById('selected_organization_id').value = orgId;
        
        console.log('Submitting form with:', {
            driveId: driveId,
            organizationType: orgType,
            organizationId: orgId,
            date: document.getElementById('selected_date')?.value || ''
        });
        // Ensure Donor’s Declaration is acknowledged
        const decl = document.getElementById('declaration_ack');
        if (decl && !decl.checked) {
            alert("Please acknowledge the Donor’s Declaration to proceed.");
            return;
        }

        // Populate confirmation details from hidden fields to avoid stale DOM reads
        const titleVal = document.getElementById('selected_drive_title').value || 'Selected Blood Drive';
        const dateVal = document.getElementById('selected_date').value;
        const timeVal24 = document.getElementById('selected_time').value;
        const venueVal = document.getElementById('selected_location').value;
        const timeVal12 = timeVal24 ? new Date(`1970-01-01T${timeVal24}`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : '';

        document.getElementById('confirmationDetails').innerHTML = `
            <div class="mb-3">
                <strong>Drive:</strong> ${titleVal}
            </div>
            <div class="mb-3">
                <strong>Date:</strong> ${dateVal ? new Date(dateVal + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : ''}
            </div>
            <div class="mb-3">
                <strong>Time:</strong> ${timeVal12}
            </div>
            <div class="mb-3">
                <strong>Venue:</strong> ${venueVal}
            </div>
            <div class="mb-3">
                <strong>Blood Type:</strong> ${this.blood_type.value}
            </div>
        `;
        
        confirmationModal.show();
    });

    // Handle final confirmation -> now directly submit main form
    document.getElementById('confirmRegistration').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';
        try {
            // Hide confirmation modal
            bootstrap.Modal.getInstance(document.getElementById('confirmationModal')).hide();
        } catch(_) {}
        // Submit main registration form
        const mainForm = document.getElementById('donationForm');
        if (mainForm) {
            // Final guard - check form fields properly
            const orgIdField = mainForm.querySelector('[name="organization_id"]');
            const orgTypeField = mainForm.querySelector('[name="organization_type"]');
            const driveIdField = mainForm.querySelector('[name="drive_id"]');
            
            const orgId = orgIdField?.value || document.getElementById('selected_organization_id')?.value || '';
            const orgType = orgTypeField?.value || document.getElementById('selected_organization_type')?.value || '';
            const driveId = driveIdField?.value || document.getElementById('selected_drive_id')?.value || '';
            
            // If still missing, try to restore from currentDriveCard
            if ((!orgId || !orgType || !driveId) && currentDriveCard) {
                const cardDriveId = currentDriveCard.querySelector('.register-btn')?.getAttribute('data-drive-id') || '';
                const cardOrgType = currentDriveCard.getAttribute('data-organization-type') || '';
                const cardOrgId = currentDriveCard.getAttribute('data-organization-id') || '';
                
                if (cardDriveId && cardOrgType && cardOrgId) {
                    if (orgIdField) orgIdField.value = cardOrgId;
                    if (orgTypeField) orgTypeField.value = cardOrgType;
                    if (driveIdField) driveIdField.value = cardDriveId;
                    document.getElementById('selected_organization_id').value = cardOrgId;
                    document.getElementById('selected_organization_type').value = cardOrgType;
                    document.getElementById('selected_drive_id').value = cardDriveId;
                    console.log('Restored drive info in confirmation button');
                }
            }
            
            // Final check
            const finalOrgId = mainForm.querySelector('[name="organization_id"]')?.value || document.getElementById('selected_organization_id')?.value || '';
            const finalOrgType = mainForm.querySelector('[name="organization_type"]')?.value || document.getElementById('selected_organization_type')?.value || '';
            const finalDriveId = mainForm.querySelector('[name="drive_id"]')?.value || document.getElementById('selected_drive_id')?.value || '';
            
            if (!finalOrgId || !finalOrgType || !finalDriveId) {
                alert('Missing organization info. Please close this form and click "Register" on the blood drive you want to join again.');
                btn.disabled = false; 
                btn.textContent = 'Confirm';
                console.error('Confirmation button - missing info:', {
                    orgId: finalOrgId,
                    orgType: finalOrgType,
                    driveId: finalDriveId
                });
                return;
            }
            
            console.log('Final submission from confirmation button:', {
                driveId: finalDriveId,
                organizationType: finalOrgType,
                organizationId: finalOrgId
            });
            
            mainForm.submit();
        }
    });

    // Interview form submission with eligibility determination
    const interviewForm = document.getElementById('interviewForm');
    if (interviewForm) {
        // Toggle female-only fields (radio buttons for is_female)
        const femaleRadios = document.querySelectorAll('input[name="is_female"]');
        const toggleFemale = () => {
            const selected = document.querySelector('input[name="is_female"]:checked');
            const isFemale = selected ? selected.value === 'yes' : false;
            document.querySelectorAll('.female-only').forEach(el => {
                el.classList.toggle('d-none', !isFemale);
            });
        };
        if (femaleRadios.length) {
            femaleRadios.forEach(r => r.addEventListener('change', toggleFemale));
            toggleFemale();
        }

        interviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const f = new FormData(interviewForm);
            const val = name => (f.get(name) || '').toString();
            const reasons = [];
            const deferrals = [];

            // Disqualifying today / immediate reasons
            if (val('q1') === 'no') reasons.push('Not feeling well today.');
            if (val('q2') === 'yes') reasons.push('Previously deferred or told not to donate.');
            if (val('q3') === 'yes') reasons.push('Donating solely for testing purposes is not allowed.');

            // Temporary deferrals based on timeframe
            if (val('q5') === 'yes') deferrals.push('Consumed alcohol within the last 12 hours. Please wait 12 hours.');
            if (val('q6') === 'yes') deferrals.push('Took aspirin within the last 3 days. Please wait 3 days.');
            if (val('q7') === 'yes') deferrals.push('Recent donation within 3 months.');
            if (val('q8') === 'yes') deferrals.push('Recent medications or vaccinations within 4 weeks.');
            if (val('q9') === 'yes' || val('q10') === 'yes' || val('q11') === 'yes') deferrals.push('ZIKA risk (travel/contact) within 6 months.');
            if (val('q12') === 'yes') deferrals.push('Received blood products/transplant within 12 months.');
            if (val('q13') === 'yes') deferrals.push('Surgical operation/dental extraction within 12 months.');
            if (val('q14') === 'yes') deferrals.push('Tattoo/piercing/needle-stick within 12 months.');
            if (val('q18') === 'yes') deferrals.push('Recent incarceration.');
            if (val('q19') === 'yes') deferrals.push('Extended stay in UK/Europe may require assessment.');
            if (val('q20') === 'yes') deferrals.push('Travel history may require temporary deferral; subject to assessment.');

            // Disqualifying conditions/risks
            if (val('q15') === 'yes' || val('q16') === 'yes') reasons.push('Recent high-risk or unprotected sexual activity.');
            if (val('q17') === 'yes') reasons.push('History/contact of hepatitis/jaundice.');
            if (val('q21') === 'yes') reasons.push('Use of prohibited drugs.');
            if (val('q22') === 'yes') reasons.push('Use of clotting factor concentrates.');
            if (val('q23') === 'yes') reasons.push('Positive test for HIV/Hepatitis/Syphilis/Malaria.');
            if (val('q24') === 'yes') reasons.push('History of malaria/hepatitis.');
            if (val('q25') === 'yes') reasons.push('History of STDs (e.g., syphilis, gonorrhea).');
            if (val('q27') === 'yes' || val('q28') === 'yes' || val('q29') === 'yes' || val('q30') === 'yes') reasons.push('Chronic or serious medical condition.');
            if (val('q31') === 'yes') reasons.push('Active or recent infection (chicken pox/cold sores).');
            if (val('q32') === 'yes') reasons.push('Other chronic medical condition or prior surgical operation.');
            if (val('q33') === 'yes') reasons.push('Recent rash and/or fever with possible associated symptoms.');

            // Female-specific
            if (val('is_female') === 'yes') {
                if (val('q34_current_pregnant') === 'yes') reasons.push('Currently pregnant.');
                if (val('q36_breastfeeding') === 'yes') reasons.push('Currently breastfeeding.');
                if (val('q35_miscarriage_1y') === 'yes') deferrals.push('Miscarriage/abortion within past 12 months.');
            }

            const resultEl = document.getElementById('eligibilityResult');

            // Helper to persist interview and show modal; donors are allowed to proceed regardless
            function persistInterviewAndShowModal(statusText, modalTitle, iconHtml, bodyHtml, proceedText) {
                // Update inline result
                if (statusText === 'Not Eligible') {
                    resultEl.className = 'alert alert-danger';
                } else if (statusText === 'Temporarily Deferred') {
                    resultEl.className = 'alert alert-warning';
                } else {
                    resultEl.className = 'alert alert-danger';
                }
                resultEl.innerHTML = bodyHtml;
                resultEl.classList.remove('d-none');

                // Prepare interview payload
                const responses = {};
                interviewForm.querySelectorAll('input[type="radio"]').forEach(r => { if (r.checked) { responses[r.name] = r.value; } });
                interviewForm.querySelectorAll('input[type="text"], input[type="date"]').forEach(inp => { if (inp.name) { responses[inp.name] = inp.value || ''; } });

                const status = statusText; // store descriptive status
                const deferralsOut = deferrals.slice();
                const reasonsOut = reasons.slice();

                const df = document.getElementById('donationForm');
                // set hidden fields immediately
                if (df) {
                    df.querySelector('#interview_status').value = status;
                    df.querySelector('#interview_responses').value = JSON.stringify(responses);
                    df.querySelector('#interview_deferrals').value = JSON.stringify(deferralsOut);
                    df.querySelector('#interview_reasons').value = JSON.stringify(reasonsOut);
                }

                // AJAX save (same as before)
                const csrfToken = '<?php echo htmlspecialchars(get_csrf_token()); ?>';
                const endpoint = '<?php echo $basePath; ?>api/save_interview.php';
                const payload = new FormData();
                payload.append('csrf_token', csrfToken);
                payload.append('interview_status', status);
                payload.append('interview_responses', JSON.stringify(responses));
                payload.append('interview_deferrals', JSON.stringify(deferralsOut));
                payload.append('interview_reasons', JSON.stringify(reasonsOut));

                function finalizeShow(data) {
                    if (df) {
                        if (data && data.success && data.interview_id) df.querySelector('#interview_id').value = data.interview_id;
                    }
                    const title = document.getElementById('eligibilityReplyTitle');
                    const icon = document.getElementById('eligibilityReplyIcon');
                    const msg = document.getElementById('eligibilityReplyMessage');
                    const footer = document.getElementById('eligibilityReplyFooter');
                    if (title && icon && msg && footer) {
                        title.textContent = modalTitle;
                        icon.innerHTML = iconHtml;
                        msg.innerHTML = bodyHtml;
                        footer.innerHTML = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>' +
                                           '<button type="button" class="btn btn-danger" id="eligibilityProceedBtn">' + (proceedText || 'Proceed to Registration') + '</button>';
                    }
                    showModalById('eligibilityReplyModal');
                    // Wire proceed
                    setTimeout(() => {
                        const proceedBtn = document.getElementById('eligibilityProceedBtn');
                        if (proceedBtn) {
                            proceedBtn.addEventListener('click', () => {
                                try { bootstrap.Modal.getInstance(document.getElementById('eligibilityReplyModal'))?.hide(); } catch(_) {}
                                try { bootstrap.Modal.getInstance(document.getElementById('interviewModal'))?.hide(); } catch(_) {}
                                showModalById('registrationModal');
                            }, { once: true });
                        }
                    }, 0);
                }

                if (!window.fetch) { finalizeShow(null); return; }
                fetch(endpoint, { method: 'POST', body: payload, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => finalizeShow(data))
                    .catch(err => { console.warn('Interview save failed', err); finalizeShow(null); });
            }

            // If there are disqualifying reasons, show them but allow donor to proceed; do not block
            if (reasons.length > 0) {
                const body = reasons.map(r => `• ${r}`).join('<br>') + '<br><br><strong>Next:</strong> Your interview will be forwarded to the organization (Red Cross / Negros First) for manual review. They will make the final eligibility decision and will notify you with the appointment outcome (approved/scheduled or rejected) including date, time and location if approved.';
                persistInterviewAndShowModal('Not Eligible', 'Not Eligible Today', '<i class="bi bi-x-circle-fill text-danger" style="font-size:3rem;"></i>', body, 'Proceed to Registration');
            }

            // If there are deferrals, show them but allow donor to proceed
            if (deferrals.length > 0) {
                const body = deferrals.map(r => `• ${r}`).join('<br>') + '<br><br><strong>Next:</strong> Your interview and deferral details will be sent to the organization for review. They will confirm whether you may be scheduled and will notify you of the final decision (including the appointment time and location if scheduled).';
                persistInterviewAndShowModal('Temporarily Deferred', 'Temporarily Deferred', '<i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>', body, 'Proceed to Registration');
            }

            // If neither reasons nor deferrals, treat as eligible but still persist and allow proceed
            if (reasons.length === 0 && deferrals.length === 0) {
                const body = 'Please proceed to registration to finalize your appointment. The organization will still review your interview and confirm the appointment details.';
                persistInterviewAndShowModal('Eligible', 'Eligible to Donate', '<i class="bi bi-check-circle-fill text-danger" style="font-size:3rem;"></i>', body, 'Proceed to Registration');
            }

            // Serialize interview responses
            const responses = {};
            interviewForm.querySelectorAll('input[type="radio"]').forEach(r => {
                if (r.checked) { responses[r.name] = r.value; }
            });
            interviewForm.querySelectorAll('input[type="text"], input[type="date"]').forEach(inp => {
                if (inp.name) { responses[inp.name] = inp.value || ''; }
            });

            const status = 'Eligible';
            const deferralsOut = [];
            const reasonsOut = [];

            // Try to persist the interview via AJAX so it is stored by donor even before appointment creation
            (function saveInterviewAndContinue() {
                const df = document.getElementById('donationForm');
                const csrfToken = '<?php echo htmlspecialchars(get_csrf_token()); ?>';
                const endpoint = '<?php echo $basePath; ?>api/save_interview.php';

                const payload = new FormData();
                payload.append('csrf_token', csrfToken);
                payload.append('interview_status', status);
                payload.append('interview_responses', JSON.stringify(responses));
                payload.append('interview_deferrals', JSON.stringify(deferralsOut));
                payload.append('interview_reasons', JSON.stringify(reasonsOut));

                // Fallback behavior: if fetch is not available or fails, continue without interview_id
                if (!window.fetch) {
                    if (df) {
                        df.querySelector('#interview_status').value = status;
                        df.querySelector('#interview_responses').value = JSON.stringify(responses);
                        df.querySelector('#interview_deferrals').value = JSON.stringify(deferralsOut);
                        df.querySelector('#interview_reasons').value = JSON.stringify(reasonsOut);
                    }
                    showEligibilityModalAndWireProceed();
                    return;
                }

                fetch(endpoint, { method: 'POST', body: payload, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        if (df) {
                            // set fields regardless
                            df.querySelector('#interview_status').value = status;
                            df.querySelector('#interview_responses').value = JSON.stringify(responses);
                            df.querySelector('#interview_deferrals').value = JSON.stringify(deferralsOut);
                            df.querySelector('#interview_reasons').value = JSON.stringify(reasonsOut);
                        }
                        if (data && data.success && data.interview_id) {
                            // persist id so server can link it to the appointment when the donor registers
                            if (df) df.querySelector('#interview_id').value = data.interview_id;
                        }
                        showEligibilityModalAndWireProceed();
                    })
                    .catch(err => {
                        console.warn('Could not save interview via AJAX, proceeding locally', err);
                        if (df) {
                            df.querySelector('#interview_status').value = status;
                            df.querySelector('#interview_responses').value = JSON.stringify(responses);
                            df.querySelector('#interview_deferrals').value = JSON.stringify(deferralsOut);
                            df.querySelector('#interview_reasons').value = JSON.stringify(reasonsOut);
                        }
                        showEligibilityModalAndWireProceed();
                    });

                function showEligibilityModalAndWireProceed() {
                    const title = document.getElementById('eligibilityReplyTitle');
                    const icon = document.getElementById('eligibilityReplyIcon');
                    const msg = document.getElementById('eligibilityReplyMessage');
                    const footer = document.getElementById('eligibilityReplyFooter');
                    if (title && icon && msg && footer) {
                        title.textContent = 'Proceed to Registration';
                        icon.innerHTML = '<i class="bi bi-check-circle-fill text-danger" style="font-size:3rem;"></i>';
                        msg.innerHTML = 'Please proceed to registration to finalize your appointment.';
                        footer.innerHTML = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>\
                                            <button type="button" class="btn btn-danger" id="eligibilityProceedBtn">Proceed to Registration</button>';
                    }
                    showModalById('eligibilityReplyModal');

                    // Wire proceed button to go to Registration
                    setTimeout(() => {
                        const proceedBtn = document.getElementById('eligibilityProceedBtn');
                        if (proceedBtn) {
                            proceedBtn.addEventListener('click', () => {
                                try { bootstrap.Modal.getInstance(document.getElementById('eligibilityReplyModal'))?.hide(); } catch(_) {}
                                try { bootstrap.Modal.getInstance(document.getElementById('interviewModal'))?.hide(); } catch(_) {}
                                showModalById('registrationModal');
                            }, { once: true });
                        }
                    }, 0);
                }
            })();
        });
    }

    // Proceed from Guidelines modal -> show Interview first
    const proceedFromGuidelinesBtn = document.getElementById('proceedFromGuidelines');
    if (proceedFromGuidelinesBtn) {
        proceedFromGuidelinesBtn.addEventListener('click', function() {
            const guidelinesModalEl = document.getElementById('guidelinesModal');
            const gm = bootstrap.Modal.getInstance(guidelinesModalEl);
            if (gm) gm.hide();
            const interviewModal = new bootstrap.Modal(document.getElementById('interviewModal'));
            interviewModal.show();
        });
    }

    // Enable proceed only when user acknowledges guidelines
    const ackGuidelines = document.getElementById('ackGuidelines');
    if (ackGuidelines && proceedFromGuidelinesBtn) {
        ackGuidelines.addEventListener('change', function() {
            proceedFromGuidelinesBtn.disabled = !this.checked;
        });
    }

    // Allow viewing guidelines from registration modal
    const openGuidelinesFromRegistration = document.getElementById('openGuidelinesFromRegistration');
    if (openGuidelinesFromRegistration) {
        openGuidelinesFromRegistration.addEventListener('click', function(e) {
            e.preventDefault();
            const regModalEl = document.getElementById('registrationModal');
            const rm = bootstrap.Modal.getInstance(regModalEl);
            if (rm) rm.hide();
            const gm = new bootstrap.Modal(document.getElementById('guidelinesModal'));
            gm.show();
        });
    }

    // Get filter elements
    const orgFilter = document.getElementById('orgFilter');
    const dateFilter = document.getElementById('dateFilter');
    const locationFilter = document.getElementById('locationFilter');

    // Find the closest form (or wrap filters in a form if not already)
    const filterForm = (orgFilter && orgFilter.form) || (dateFilter && dateFilter.form) || (locationFilter && locationFilter.form);

    // Auto-submit on change
    if (orgFilter) orgFilter.addEventListener('change', function() { filterForm.submit(); });
    if (dateFilter) dateFilter.addEventListener('change', function() { filterForm.submit(); });
    if (locationFilter) locationFilter.addEventListener('input', function() {
        clearTimeout(this._timeout);
        this._timeout = setTimeout(() => filterForm.submit(), 500);
    });

    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('[data-auto-dismiss]');
        alerts.forEach(function(alert) {
            const dismissTime = parseInt(alert.getAttribute('data-auto-dismiss'));
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, dismissTime);
        });
    });
});
</script>
</body>