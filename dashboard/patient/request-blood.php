<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../login.php?role=patient");
    exit;
}
// Set page title
$pageTitle = "Request Blood - Blood Bank Portal";

// Include database connection
require_once '../../config/db.php';

// Get patient information
$patientId = $_SESSION['user_id'];
$patient = getRow("SELECT * FROM patient_users WHERE id = ?", [$patientId]);

// Get all barangays for selection from database
$barangaysFromDb = executeQuery("SELECT * FROM barangay_users");
if (!is_array($barangaysFromDb)) {
    $barangaysFromDb = [];
}

// Complete list of all 61 barangays in Bacolod City
$allBacolodBarangays = [
    'Alangilan', 'Alijis', 'Banago', 'Bata', 'Cabug', 'Estefania', 'Felisa', 
    'Granada', 'Handumanan', 'Mandalagan', 'Mansilingan', 'Montevista', 
    'Pahanocoy', 'Punta Taytay', 'Singcang-Airport', 'Sum-ag', 'Taculing', 
    'Tangub', 'Villamonte', 'Vista Alegre',
    'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5',
    'Barangay 6', 'Barangay 7', 'Barangay 8', 'Barangay 9', 'Barangay 10',
    'Barangay 11', 'Barangay 12', 'Barangay 13', 'Barangay 14', 'Barangay 15',
    'Barangay 16', 'Barangay 17', 'Barangay 18', 'Barangay 19', 'Barangay 20',
    'Barangay 21', 'Barangay 22', 'Barangay 23', 'Barangay 24', 'Barangay 25',
    'Barangay 26', 'Barangay 27', 'Barangay 28', 'Barangay 29', 'Barangay 30',
    'Barangay 31', 'Barangay 32', 'Barangay 33', 'Barangay 34', 'Barangay 35',
    'Barangay 36', 'Barangay 37', 'Barangay 38', 'Barangay 39', 'Barangay 40',
    'Barangay 41'
];

// Create a map of barangay names from database
$barangayMap = [];
foreach ($barangaysFromDb as $brgy) {
    $barangayName = $brgy['barangay_name'] ?? '';
    if (!empty($barangayName)) {
        $barangayMap[$barangayName] = $brgy;
    }
}

// Combine: use database entries if available, otherwise create entries for missing ones
$barangays = [];
$counter = 1000; // Start counter for barangays not in database
foreach ($allBacolodBarangays as $brgyName) {
    if (isset($barangayMap[$brgyName])) {
        // Use database entry
        $barangays[] = $barangayMap[$brgyName];
    } else {
        // Create entry for barangay not in database
        $barangays[] = [
            'id' => $counter++,
            'barangay_name' => $brgyName,
            'name' => 'Barangay ' . $brgyName
        ];
    }
}

// Sort barangays alphabetically by name for easier selection
usort($barangays, function($a, $b) {
    $nameA = $a['barangay_name'] ?? '';
    $nameB = $b['barangay_name'] ?? '';
    return strcmp($nameA, $nameB);
});

// Get patient's barangay with fallback
$patientBarangay = isset($patient['barangay']) ? $patient['barangay'] : 'Not Set';

// If barangay is not set, show error and prevent form submission
if ($patientBarangay === 'Not Set') {
    $error = "Your barangay information is not set. Please update your profile first.";
    // Optionally redirect to profile page
    // header("Location: profile.php");
    // exit;
}

// Get blood inventory status
$sql = "SELECT blood_type, organization_type, SUM(units) as units
        FROM blood_inventory
        WHERE status = 'Available'
        GROUP BY blood_type, organization_type
        ORDER BY blood_type";
$bloodInventory = executeQuery($sql);

// Debug information - use secure_log to prevent log injection
if (function_exists('secure_log')) {
    secure_log("Blood Inventory Query executed");
    secure_log("Blood Inventory Result", ['result_count' => is_array($bloodInventory) ? count($bloodInventory) : 0]);
}

// Initialize empty array if query fails
if (!is_array($bloodInventory)) {
    $bloodInventory = [];
    if (function_exists('secure_log')) {
        secure_log("Blood inventory query failed or returned no results");
    }
}

// Process form submission
$success = false;
$error = "";
$formSubmitted = false;
// Confirmation payload for receipt/QR
$requestId = null;
$confirmationPayload = '';

// Check if we have a success message from previous submission (POST-redirect-GET pattern)
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_SESSION['blood_request_success'])) {
    $successData = $_SESSION['blood_request_success'];
    $success = true;
    $formSubmitted = true;
    $requestId = $successData['request_id'];
    $bloodType = $successData['blood_type'];
    $units = $successData['units'];
    $organization = $successData['organization'];
    $requiredDate = $successData['required_date'];
    $required_time = $successData['required_time'] ?? '';
    $hospital = $successData['hospital'];
    $confirmationPayload = $successData['confirmation_payload'];
    // Clear the session data to prevent showing it again
    unset($_SESSION['blood_request_success']);
}

// Initialize variables with safe defaults to avoid undefined variable warnings when page is loaded via GET
$uploadDir = '../../uploads/';
$requestFormPath = '';
$bloodCardPath = '';
if (!isset($bloodType)) $bloodType = '';
if (!isset($units)) $units = 0;
if (!isset($organization)) $organization = '';
if (!isset($requiredDate)) $requiredDate = '';
if (!isset($required_time)) $required_time = '';
$reason = '';
if (!isset($hospital)) $hospital = '';
$hasBloodCard = 0;
$selectedBarangay = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['success'])) {
    // Mark form submitted and perform CSRF protection
    $formSubmitted = true;
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please refresh the page and try again.';
    } else {
        // Sanitize inputs (use null coalescing to avoid undefined index warnings)
        $bloodType = sanitize($_POST['blood_type'] ?? '');
        $units = validate_units($_POST['units'] ?? 0);
        $organization = sanitize($_POST['organization'] ?? '');
        $requiredDate = sanitize($_POST['required_date'] ?? '');
    // New: required time (optional) from a time picker
    $required_time = isset($_POST['required_time']) ? sanitize($_POST['required_time']) : null;
    // Enforce clean formatting: Title Case and single spacing for textual fields
    $reason = normalize_input($_POST['reason'] ?? '', true);
    $hospital = sanitize($_POST['hospital'] ?? '');
    // If "Others" is selected, use the hospital_others value
    if ($hospital === 'Others') {
        $hospitalOthers = normalize_input($_POST['hospital_others'] ?? '', true);
        if (!empty($hospitalOthers)) {
            $hospital = $hospitalOthers;
        }
    }
    $hasBloodCard = isset($_POST['has_blood_card']) ? 1 : 0;
    // Get barangay ID - convert to integer, not normalize (it's an ID, not text)
    if ($hasBloodCard) {
        $selectedBarangay = null;
    } else {
        $barangayInput = $_POST['barangay'] ?? '';
        $selectedBarangay = !empty($barangayInput) ? (int)$barangayInput : null;
    }
    // Handle file uploads
    $uploadDir = '../../uploads/';
    $requestFormPath = '';
    $bloodCardPath = '';
    }
}
    // Create uploads directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Handle hospital request form upload (max 5MB)
    if (isset($_FILES['request_form']) && $_FILES['request_form']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['request_form']['size'] > 5 * 1024 * 1024) {
            $error = "Hospital request form must be 5MB or smaller.";
        } else {
            $requestFormFile = $_FILES['request_form'];
            $requestFormExt = strtolower(pathinfo($requestFormFile['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if (in_array($requestFormExt, $allowedExts)) {
                $requestFormName = uniqid('request_form_') . '.' . $requestFormExt;
                $requestFormPath = $uploadDir . $requestFormName;
                
                if (move_uploaded_file($requestFormFile['tmp_name'], $requestFormPath)) {
                    $requestFormPath = 'uploads/' . $requestFormName;
                } else {
                    $error = "Failed to upload hospital request form.";
                }
            } else {
                $error = "Invalid file type for hospital request form. Allowed types: PDF, JPG, JPEG, PNG";
            }
        }
    }

    // Handle blood card upload if user has one
    if ($hasBloodCard && isset($_FILES['blood_card']) && $_FILES['blood_card']['error'] === UPLOAD_ERR_OK) {
        $bloodCardFile = $_FILES['blood_card'];
        if ($bloodCardFile['size'] > 5 * 1024 * 1024) {
            $error = "Blood card file must be 5MB or smaller.";
        } else {
            $bloodCardExt = strtolower(pathinfo($bloodCardFile['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
            if (in_array($bloodCardExt, $allowedExts)) {
                $bloodCardName = uniqid('blood_card_') . '.' . $bloodCardExt;
                $bloodCardPath = $uploadDir . $bloodCardName;

                if (move_uploaded_file($bloodCardFile['tmp_name'], $bloodCardPath)) {
                    $bloodCardPath = 'uploads/' . $bloodCardName;
                } else {
                    $error = "Failed to upload blood card.";
                }
            } else {
                $error = "Invalid file type for blood card. Allowed types: PDF, JPG, JPEG, PNG";
            }
        }
    }

    // Log input values using secure_log to prevent log injection
    if (function_exists('secure_log')) {
        secure_log("Blood Request Input Values", [
            'blood_type' => substr($bloodType, 0, 50),
            'units' => $units,
            'organization' => substr($organization, 0, 50),
            'required_date' => substr($requiredDate, 0, 50),
            'hospital' => substr($hospital, 0, 100),
            'has_blood_card' => $hasBloodCard,
            'selected_barangay' => $selectedBarangay ? substr($selectedBarangay, 0, 50) : null,
            'has_request_form' => !empty($requestFormPath),
            'has_blood_card_file' => !empty($bloodCardPath)
        ]);
    }

    // Validate inputs
    if (empty($bloodType) || empty($units) || empty($organization) || empty($requiredDate) || empty($reason) || empty($hospital)) {
        $error = "Please fill in all required fields.";
        if (function_exists('secure_log')) {
            secure_log("Validation failed: Empty required fields");
        }
    } else if ($hospital === 'Others' && empty($_POST['hospital_others'])) {
        $error = "Please specify the hospital name when selecting 'Others'.";
        if (function_exists('secure_log')) {
            secure_log("Validation failed: Others selected but hospital name not provided");
        }
    } else if (!$hasBloodCard && empty($selectedBarangay)) {
        $error = "Please select a barangay for referral.";
        if (function_exists('secure_log')) {
            secure_log("Validation failed: No barangay selected for non-blood card request");
        }
    } else if (empty($requestFormPath)) {
        $error = "Please upload the hospital request form.";
        if (function_exists('secure_log')) {
            secure_log("Validation failed: No request form uploaded");
        }
    } else if ($hasBloodCard && empty($bloodCardPath)) {
        $error = "Please upload your blood card.";
        if (function_exists('secure_log')) {
            secure_log("Validation failed: No blood card uploaded for blood card holder");
        }
    } else {
        // Check availability
        $available = getRow("SELECT SUM(units) as total_units
                     FROM blood_inventory
                     WHERE blood_type = ? AND organization_type = ? AND status = 'Available'",
                     [$bloodType, $organization]);

        if (function_exists('secure_log')) {
            secure_log("Blood availability check result", [
                'has_result' => !empty($available),
                'total_units' => $available['total_units'] ?? 0
            ]);
        }

        if ($available && $available['total_units'] >= $units) {
            // Validate barangay ID if provided
            if (!$hasBloodCard && !empty($selectedBarangay)) {
                // Check if it's a valid barangay ID from database
                $barangayExists = getRow("SELECT id FROM barangay_users WHERE id = ?", [$selectedBarangay]);
                
                // If not in database, check if it's a valid barangay name from our complete list
                if (!$barangayExists) {
                    // Get the barangay name from our complete list
                    $selectedBarangayName = null;
                    foreach ($barangays as $brgy) {
                        if (isset($brgy['id']) && $brgy['id'] == $selectedBarangay) {
                            $selectedBarangayName = $brgy['barangay_name'] ?? null;
                            break;
                        }
                    }
                    
                    // Validate it's in our complete list of Bacolod barangays
                    if (!$selectedBarangayName || !in_array($selectedBarangayName, $allBacolodBarangays)) {
                    $error = "Invalid barangay selected. Please select a valid barangay.";
                    }
                    // If it's in our list, it's valid even if not in database
                }
            }
            
            if (empty($error)) {
                try {
                    // Insert blood request
                    // Insert including optional required_time
                    $query = "INSERT INTO blood_requests (
                        patient_id, blood_type, units_requested,
                        organization_type, required_date, required_time, reason, hospital,
                        status, request_date, barangay_id, has_blood_card,
                        request_form_path, blood_card_path
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?, ?, ?, ?)";
                    
                    if (function_exists('secure_log')) {
                        secure_log("Executing blood request insert query", [
                            'patient_id' => $patientId,
                            'blood_type' => substr($bloodType, 0, 10),
                            'units' => $units,
                            'organization' => substr($organization, 0, 50),
                            'has_required_time' => !empty($required_time),
                            'selected_barangay_id' => $selectedBarangay,
                            'has_blood_card' => $hasBloodCard,
                            'has_request_form' => !empty($requestFormPath),
                            'has_blood_card_file' => !empty($bloodCardPath)
                        ]);
                    }

                    $requestId = insertRow($query, [
                        $patientId, $bloodType, $units, $organization,
                        $requiredDate, $required_time, $reason, $hospital,
                        $selectedBarangay, $hasBloodCard, $requestFormPath, $bloodCardPath
                    ]);

                    if ($requestId !== false) {
                        if (function_exists('secure_log')) {
                            secure_log("Blood request inserted successfully", ['request_id' => $requestId]);
                        }
                        // Build verification URL for QR with signed token
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $basePathForQr = '/blood';
                        $appSecret = getenv('APP_SECRET') ?: 'change_this_secret';
                        $token = hash_hmac('sha256', (string)$requestId, $appSecret);
                        $confirmationPayload = $scheme . '://' . $host . $basePathForQr . '/verify-request.php?id=' . urlencode((string)$requestId) . '&t=' . urlencode($token);

                    // Add notifications for both barangay and blood bank
                    try {
                        // Notification for barangay if referral is needed
                        if (!$hasBloodCard && $selectedBarangay) {
                            $barangayQuery = "INSERT INTO notifications (
                                title, message, user_id, user_role, is_read, created_at
                            ) VALUES (?, ?, ?, 'barangay', 0, NOW())";
                            
                            if (function_exists('secure_log')) {
                                secure_log("Executing barangay notification query", ['barangay_id' => $selectedBarangay]);
                            }
                            executeQuery($barangayQuery, [
                                "New Blood Request Referral",
                                "A new blood request needs referral approval. Hospital request form is attached to the blood request.",
                                $selectedBarangay
                            ]);
                        }

                        // Notification for blood bank - send to all users of the selected organization
                        $bloodBankRole = $organization === 'redcross' ? 'redcross' : 'negrosfirst';
                        
                        // Get all users of the selected blood bank organization
                        $bloodBankUsers = [];
                        if ($bloodBankRole === 'redcross') {
                            $bloodBankUsers = executeQuery("SELECT id FROM redcross_users", []);
                        } elseif ($bloodBankRole === 'negrosfirst') {
                            $bloodBankUsers = executeQuery("SELECT id FROM negrosfirst_users", []);
                        }
                        
                        // Send notification to each blood bank user
                        if (!empty($bloodBankUsers) && is_array($bloodBankUsers)) {
                            $notificationMessage = "A new blood request for {$bloodType} blood type has been submitted. " . 
                                ($hasBloodCard ? "Patient has a blood card." : "Waiting for barangay referral.") .
                                " Documents are attached to the blood request.";
                            
                            foreach ($bloodBankUsers as $bbUser) {
                                $bbUserId = $bbUser['id'] ?? null;
                                if ($bbUserId) {
                                    $bloodBankQuery = "INSERT INTO notifications (
                                        title, message, user_id, user_role, is_read, created_at
                                    ) VALUES (?, ?, ?, ?, 0, NOW())";
                                    
                                    if (function_exists('secure_log')) {
                                        secure_log("Executing blood bank notification query", ['user_id' => $bbUserId, 'org_type' => $bloodBankRole]);
                                    }
                                    executeQuery($bloodBankQuery, [
                                        "New Blood Request",
                                        $notificationMessage,
                                        $bbUserId,
                                        $bloodBankRole
                                    ]);
                                    if (function_exists('secure_log')) {
                                        secure_log("Notification sent to blood bank user", ['org_type' => $bloodBankRole, 'user_id' => $bbUserId]);
                                }
                            }
                            }
                            if (function_exists('secure_log')) {
                                secure_log("Total notifications sent to blood bank", ['org_type' => $bloodBankRole, 'count' => count($bloodBankUsers)]);
                            }
                        } else {
                            if (function_exists('secure_log')) {
                                secure_log("ERROR: No blood bank users found to send notification", ['org_type' => $bloodBankRole]);
                            }
                            // Fallback: Create notification with user_id = 0 (broadcast to all users of that role)
                            $bloodBankQuery = "INSERT INTO notifications (
                                title, message, user_id, user_role, is_read, created_at
                            ) VALUES (?, ?, 0, ?, 0, NOW())";
                            
                            executeQuery($bloodBankQuery, [
                                "New Blood Request",
                                "A new blood request for {$bloodType} blood type has been submitted. " . 
                                ($hasBloodCard ? "Patient has a blood card." : "Waiting for barangay referral.") .
                                " Documents are attached to the blood request.",
                                $bloodBankRole
                            ]);
                        }

                        // Send SMS notifications to blood bank users (Red Cross or Negros First) via SIM800C
                        try {
                            require_once '../../includes/sim800c_sms.php';
                            require_once '../../includes/notification_templates.php';
                            
                            // Get blood bank users with phone numbers from the selected organization
                            $bloodBankRecipients = [];
                            if ($bloodBankRole === 'redcross') {
                                $bloodBankRecipients = executeQuery("SELECT id, phone, name FROM redcross_users WHERE phone IS NOT NULL AND phone != ''", []);
                                if (empty($bloodBankRecipients)) {
                                    // Fallback to generic users table
                                    $bloodBankRecipients = executeQuery("SELECT id, phone, name FROM users WHERE role = 'redcross' AND phone IS NOT NULL AND phone != ''", []);
                                }
                            } elseif ($bloodBankRole === 'negrosfirst') {
                                $bloodBankRecipients = executeQuery("SELECT id, phone, name FROM negrosfirst_users WHERE phone IS NOT NULL AND phone != ''", []);
                                if (empty($bloodBankRecipients)) {
                                    // Fallback to generic users table
                                    $bloodBankRecipients = executeQuery("SELECT id, phone, name FROM users WHERE role = 'negrosfirst' AND phone IS NOT NULL AND phone != ''", []);
                                }
                            }
                            
                            if (!empty($bloodBankRecipients) && is_array($bloodBankRecipients)) {
                                // Build professional SMS message for blood bank users
                                $patientName = $patient['name'] ?? ('Patient#' . $patientId);
                                $institutionName = get_institution_name($bloodBankRole);
                                
                                $smsMessage = "Hello, this is an automated alert from {$institutionName}. ";
                                $smsMessage .= "A new blood request has been submitted. ";
                                $smsMessage .= "Patient: {$patientName}. ";
                                $smsMessage .= "Blood Type: {$bloodType}. ";
                                $smsMessage .= "Units: {$units}. ";
                                $smsMessage .= "Hospital: {$hospital}. ";
                                if (!empty($requiredDate)) {
                                    $formattedDate = date('M d, Y', strtotime($requiredDate));
                                    $smsMessage .= "Required by: {$formattedDate}. ";
                                }
                                $smsMessage .= "Request ID: {$requestId}. ";
                                if ($hasBloodCard) {
                                    $smsMessage .= "Patient has a blood card. ";
                                } else {
                                    $smsMessage .= "Waiting for barangay referral. ";
                                }
                                $smsMessage .= "Please review in the dashboard. Thank you!";
                                $smsMessage = format_notification_message($smsMessage);
                                
                                // Send SMS to each blood bank user
                                $smsSentCount = 0;
                                $smsErrorCount = 0;
                                
                                foreach ($bloodBankRecipients as $recipient) {
                                    $recipientPhone = $recipient['phone'] ?? '';
                                    $recipientName = $recipient['name'] ?? 'User';
                                    $recipientId = $recipient['id'] ?? null;
                                    
                                    if (empty($recipientPhone)) continue;
                                    
                                    // Try to decrypt phone number if encrypted, but keep original if decryption fails (plain text)
                                    if (function_exists('decrypt_value')) {
                                        $decryptedPhone = decrypt_value($recipientPhone);
                                        // If decryption returns null/empty, it means the phone is NOT encrypted (plain text)
                                        // In that case, use the original value
                                        if (!empty($decryptedPhone)) {
                                            $recipientPhone = $decryptedPhone;
                                        }
                                        // If decryption fails, $recipientPhone already contains the original (plain text) value
                                    }
                                    
                                    if (!empty($recipientPhone) && trim($recipientPhone) !== '') {
                                        if (function_exists('secure_log')) {
                                            secure_log('[BLOODBANK_SMS] Sending new request SMS', ['org_type' => $bloodBankRole, 'user_id' => $recipientId, 'phone_prefix' => substr($recipientPhone, 0, 4) . '****']);
                                        }
                                        
                                        try {
                                            // Skip enabled check for automated sends (same as Red Cross dashboard)
                                            $smsResult = send_sms_sim800c($recipientPhone, $smsMessage);
                                            
                                            if ($smsResult['success']) {
                                                $smsSentCount++;
                                                if (function_exists('secure_log')) {
                                                    secure_log('[BLOODBANK_SMS] SMS sent successfully', ['org_type' => $bloodBankRole, 'user_name' => substr($recipientName, 0, 100), 'phone_prefix' => substr($recipientPhone, 0, 4) . '****']);
                                                }
                                            } else {
                                                $smsErrorCount++;
                                                $smsError = $smsResult['error'] ?? 'Unknown error';
                                                if (function_exists('secure_log')) {
                                                    secure_log('[BLOODBANK_SMS] Failed to send SMS', ['org_type' => $bloodBankRole, 'user_name' => substr($recipientName, 0, 100), 'phone_prefix' => substr($recipientPhone, 0, 4) . '****', 'error' => substr($smsError, 0, 500)]);
                                                }
                                            }
                                        } catch (Exception $smsEx) {
                                            $smsErrorCount++;
                                            if (function_exists('secure_log')) {
                                                secure_log('[BLOODBANK_SMS_ERR] Exception sending SMS', ['org_type' => $bloodBankRole, 'user_id' => $recipientId, 'error' => substr($smsEx->getMessage(), 0, 500)]);
                                            }
                                        }
                                    } else {
                                        if (function_exists('secure_log')) {
                                            secure_log('[BLOODBANK_SMS] Cannot send SMS - phone number not found', ['org_type' => $bloodBankRole, 'user_id' => $recipientId]);
                                        }
                                    }
                                }
                                
                                if (function_exists('secure_log')) {
                                    secure_log('[BLOODBANK_SMS] Summary', ['org_type' => $bloodBankRole, 'sent' => $smsSentCount, 'failed' => $smsErrorCount, 'total' => count($bloodBankRecipients)]);
                                }
                            } else {
                                if (function_exists('secure_log')) {
                                    secure_log('[BLOODBANK_SMS] No users with phone numbers found to notify', ['org_type' => $bloodBankRole]);
                                }
                            }
                        } catch (Exception $e) {
                            if (function_exists('secure_log')) {
                                secure_log('[BLOODBANK_SMS_TOP_ERR] Exception in blood bank SMS notification', ['error' => substr($e->getMessage(), 0, 500)]);
                            }
                            // Don't block request submission if SMS fails
                        }

                        // Send email notifications alongside in-app notifications
                      //  try {
                      //      require_once '../../includes/SMS/EmailNotificationService.php';
                      //      $emailNotificationService = new EmailNotificationService(); 
                            
                            // Prepare request data for email notifications
                            $requestData = [
                                'bloodType' => $bloodType,
                                'units' => $units,
                                'hospital' => $hospital,
                              //  'urgency' => $urgency,
                              //  'contact' => $contact,
                              //  'notes' => $notes,
                                'created_at' => date('Y-m-d H:i:s'),
                                'status' => 'Pending',
                                'additional_info' => $hasBloodCard ? "Patient has a blood card." : "Waiting for barangay referral."
                            ];
                            
                            // Get blood bank users for email notifications
                            $bloodBankUsers = executeQuery("SELECT id FROM users WHERE role = ?", [$bloodBankRole]);
                            $recipients = is_array($bloodBankUsers) ? array_column($bloodBankUsers, 'id') : [];
                            
                            // Add barangay user if referral is needed
                            if (!$hasBloodCard && $selectedBarangay) {
                                $recipients[] = $selectedBarangay;
                            }
                            
                            // Add the patient who submitted the request to receive confirmation email
                            $recipients[] = $patientId;
                            
                            // Send email notifications
                       //     $emailNotificationService->sendBloodRequestNotification($requestData, $recipients);
                            
                      //  } catch (Exception $emailError) {
                     //       error_log("Failed to send email notifications: " . $emailError->getMessage());
                      //  }

                    } catch (Exception $e) {
                        if (function_exists('secure_log')) {
                            secure_log("Failed to create notifications", ['error' => substr($e->getMessage(), 0, 500)]);
                        }
                    }
                    
                    // Store success data in session to prevent duplicate submissions
                    $_SESSION['blood_request_success'] = [
                        'request_id' => $requestId,
                        'blood_type' => $bloodType,
                        'units' => $units,
                        'organization' => $organization,
                        'required_date' => $requiredDate,
                        'required_time' => $required_time ?? '',
                        'hospital' => $hospital,
                        'confirmation_payload' => $confirmationPayload
                    ];
                    
                    // Send SMS notification to patient confirming their request submission
                    try {
                        // Get patient phone number
                        $patientPhone = $patient['phone'] ?? '';
                        
                        if (!empty($patientPhone)) {
                            // Try to decrypt phone number if encrypted, but keep original if decryption fails (plain text)
                            if (function_exists('decrypt_value')) {
                                $decryptedPhone = decrypt_value($patientPhone);
                                // If decryption returns null/empty, it means the phone is NOT encrypted (plain text)
                                // In that case, use the original value
                                if (!empty($decryptedPhone)) {
                                    $patientPhone = $decryptedPhone;
                                }
                                // If decryption fails, $patientPhone already contains the original (plain text) value
                            }
                            
                            // Get organization type for proper institution name
                            $orgType = strtolower($organization);
                            if ($orgType === 'redcross' || strpos($orgType, 'red') !== false) {
                                $orgType = 'redcross';
                            } elseif ($orgType === 'negrosfirst' || strpos($orgType, 'negros') !== false) {
                                $orgType = 'negrosfirst';
                            }
                            
                            // Use professional notification template
                            require_once '../../includes/notification_templates.php';
                            $patientName = $patient['name'] ?? '';
                            $confirmationMessage = "Hello {$patientName}, this is from " . get_institution_name($orgType) . ". ";
                            $confirmationMessage .= "Your blood request has been successfully submitted. ";
                            $confirmationMessage .= "Requested: {$units} unit(s) of {$bloodType}. ";
                            $confirmationMessage .= "Hospital: {$hospital}. ";
                            if (!empty($requiredDate)) {
                                $formattedDate = date('M d, Y', strtotime($requiredDate));
                                $confirmationMessage .= "Required by: {$formattedDate}. ";
                            }
                            $confirmationMessage .= "Request ID: {$requestId}. ";
                            $confirmationMessage .= "We will review your request and notify you of the status. Thank you!";
                            $confirmationMessage = format_notification_message($confirmationMessage);
                            
                            // Send SMS via SIM800C
                            require_once '../../includes/sim800c_sms.php';
                            if (function_exists('secure_log')) {
                                secure_log('[PATIENT_SMS] Request submission SMS attempt', ['patient_id' => $patientId, 'phone_prefix' => !empty($patientPhone) ? substr($patientPhone, 0, 4) . '****' : 'EMPTY']);
                            }
                            
                            if (!empty($patientPhone) && trim($patientPhone) !== '') {
                                $smsResult = send_sms_sim800c($patientPhone, $confirmationMessage);
                                
                                if ($smsResult['success']) {
                                    if (function_exists('secure_log')) {
                                        secure_log('[PATIENT_SMS] Request submission SMS sent successfully', ['phone_prefix' => substr($patientPhone, 0, 4) . '****']);
                                    }
                                } else {
                                    $smsError = $smsResult['error'] ?? 'Unknown error';
                                    if (function_exists('secure_log')) {
                                        secure_log('[PATIENT_SMS] Failed to send request submission SMS', ['phone_prefix' => substr($patientPhone, 0, 4) . '****', 'error' => substr($smsError, 0, 500)]);
                                    }
                                }
                            } else {
                                if (function_exists('secure_log')) {
                                    secure_log('[PATIENT_SMS] Cannot send request submission SMS - patient phone number not found', ['patient_id' => $patientId]);
                                }
                            }
                        }
                    } catch (Exception $smsEx) {
                        if (function_exists('secure_log')) {
                            secure_log('[PATIENT_SMS_ERR] Exception in patient SMS notification', ['error' => substr($smsEx->getMessage(), 0, 500)]);
                        }
                        // Don't block request submission if SMS fails
                    }
                    
                    // Redirect to prevent duplicate submission on refresh (POST-redirect-GET pattern)
                    header("Location: request-blood.php?success=1");
                    exit;
                } else {
                    // Provide a clearer hint for common schema issues (e.g., missing required_time column)
                    $col = getRow("SHOW COLUMNS FROM blood_requests LIKE 'required_time'");
                    if (!$col) {
                        $error = "Failed to submit your request. Database schema may be missing the 'required_time' column. Please run the migration to add it.";
                        if (function_exists('secure_log')) {
                            secure_log("Blood request insertion failed - missing required_time column");
                        }
                    } else {
                        $error = "Failed to submit your request. Please try again.";
                        if (function_exists('secure_log')) {
                            secure_log("Blood request insertion failed - insertRow returned false");
                        }
                    }
                }
            } catch (Exception $e) {
                $error = "Failed to submit your request. Database error: " . $e->getMessage();
                if (function_exists('secure_log')) {
                    secure_log("Blood request insertion error", ['error' => substr($e->getMessage(), 0, 500), 'trace' => substr($e->getTraceAsString(), 0, 1000)]);
                }
            }
        } else {
            $error = "Sorry, the requested blood type or units are not available at the selected blood bank.";
            if (function_exists('secure_log')) {
                secure_log("Blood availability check failed - insufficient units available");
            }
        }
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

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
    
    <!-- Shared Patient Dashboard Styles -->
    <?php include_once 'shared-styles.php'; ?>
    
    <!-- Custom JavaScript -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
        <script defer src="<?php echo $basePath; ?>assets/js/dashboard.js"></script>
    <?php endif; ?>
    
    <!-- QR Code library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- PDF export libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
<div class="dashboard-container">
    <!-- Include sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <!-- Top Header -->
    <div class="dashboard-header">
        <div class="header-content">
            <h2 class="page-title">Request Blood</h2>
            <div class="header-actions">
                <?php include_once '../../includes/notification_bell.php'; ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar me-2">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Patient'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <div class="dashboard-main">
            <?php if ($formSubmitted): ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Your blood request has been submitted successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>

                <?php elseif ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h4 class="card-title mb-0">Blood Request Form</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="bloodRequestForm" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                                <div class="row g-3">
                                    <div class="col-12 col-sm-6">
                                        <label for="organization" class="form-label">Blood Bank</label>
                                        <select name="organization" id="organization" class="form-select" required>
                                            <option value="redcross">Red Cross</option>
                                            <option value="negrosfirst">Negros First</option>
                                        </select>
                                        <div class="form-check mt-3">
                                            <input class="form-check-input" type="checkbox" id="has_blood_card" name="has_blood_card">
                                            <label class="form-check-label" for="has_blood_card">
                                                I have a blood card
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-6">
                                        <label for="blood_type" class="form-label">Blood Type</label>
                                        <select class="form-select" id="blood_type" name="blood_type" required>
                                            <option value="">Select Blood Type</option>
                                            <?php
                                            $types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                            // Create availability map for both organizations
                                            $availabilityMap = [];
                                            foreach ($bloodInventory as $item) {
                                                $orgKey = strtolower(str_replace(' ', '', $item['organization_type']));
                                                $bloodType = $item['blood_type'];
                                                if (!isset($availabilityMap[$bloodType])) {
                                                    $availabilityMap[$bloodType] = ['redcross' => 0, 'negrosfirst' => 0];
                                                }
                                                $availabilityMap[$bloodType][$orgKey] = (int)$item['units'];
                                            }
                                            
                                            foreach ($types as $type) {
                                                $redcrossAvailable = $availabilityMap[$type]['redcross'] ?? 0;
                                                $negrosfirstAvailable = $availabilityMap[$type]['negrosfirst'] ?? 0;
                                                $maxAvailable = max($redcrossAvailable, $negrosfirstAvailable);
                                                
                                                if ($maxAvailable > 0) {
                                                    echo "<option value=\"$type\" data-redcross=\"$redcrossAvailable\" data-negrosfirst=\"$negrosfirstAvailable\">$type</option>";
                                                } else {
                                                    echo "<option value=\"$type\" disabled style=\"color: #999;\">$type (Not Available)</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                        <small class="form-text text-muted" id="bloodTypeHint"></small>
                                    </div>

                                    <div class="col-12 col-sm-6">
                                        <label for="barangay" class="form-label">Barangay for Referral</label>
                                        <select name="barangay" id="barangay" class="form-select" required>
                                            <option value="">Select Barangay</option>
                                            <?php foreach ($barangays as $brgy): ?>
                                                <option value="<?php echo htmlspecialchars($brgy['id']); ?>">
                                                    <?php echo htmlspecialchars($brgy['barangay_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">Select your barangay. They will review your request and provide a referral.</small>
                                    </div>

                                    <div class="col-12 col-sm-6">
                                        <label for="units" class="form-label">Units Required</label>
                                        <input type="number" step="1" name="units" id="units" class="form-control" min="1" max="10" required>
                                        <small class="form-text text-muted">1 bag = 450 cc</small>
                                    </div>

                                    <div class="col-12">
                                        <label for="required_date" class="form-label">Required By Date & Time</label>
                                        <div class="d-flex gap-2">
                                            <input type="date" name="required_date" id="required_date" class="form-control" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                                            <input type="time" name="required_time" id="required_time" class="form-control" placeholder="HH:MM">
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="hospital" class="form-label">Hospital</label>
                                        <select name="hospital" id="hospital" class="form-select" required>
                                            <option value="">Select Hospital</option>
                                            <?php
                                            $hospitals = [
                                                'Riverside Medical Center Inc.',
                                                'Bacolod Adventist Medical Center',
                                                'Corazon Locsin Montelibano Memorial Regional Hospital',
                                                'South Bacolod General Hospital',
                                                'Bacolod Queen of Mercy',
                                                'The Doctor\'s Hospital Inc.',
                                                'Metro Bacolod Hospital and Medical Center',
                                                'Terisita Lopez Jalandoni Provincial Hospital',
                                            ];
                                            $patientHospital = $patient['hospital'] && $patient['hospital'] !== '0' ? $patient['hospital'] : '';
                                            foreach ($hospitals as $hosp) {
                                                $selected = ($patientHospital === $hosp) ? 'selected' : '';
                                                echo "<option value=\"" . htmlspecialchars($hosp) . "\" $selected>" . htmlspecialchars($hosp) . "</option>";
                                            }
                                            ?>
                                            <option value="Others">Others</option>
                                        </select>
                                        <div id="hospitalOthersSection" style="display: none; margin-top: 0.5rem;">
                                            <input type="text" name="hospital_others" id="hospital_others" class="form-control" placeholder="Please specify hospital name">
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="reason" class="form-label">Reason</label>
                                        <textarea name="reason" id="reason" class="form-control" rows="3" required></textarea>
                                    </div>

                                    <div class="col-12">
                                        <label for="request_form" class="form-label">Hospital Request Form</label>
                                        <input type="file" name="request_form" id="request_form" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                        <small class="form-text text-muted">Upload the request form from your hospital (PDF, JPG, JPEG, or PNG) Max size: 5MB</small>
                                    </div>

                                    <div class="col-12" id="bloodCardUploadSection" style="display: none;">
                                        <label for="blood_card" class="form-label">Blood Card</label>
                                        <input type="file" name="blood_card" id="blood_card" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="form-text text-muted">Upload your blood card (PDF, JPG, JPEG, or PNG)</small>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" id="submitBtn" class="btn btn-primary w-100">Submit Request</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h4 class="card-title mb-0">Blood Availability</h4>
                        </div>
                        <div class="card-body">
                            <!-- Desktop Table View -->
                            <div class="table-responsive d-none d-md-block">
                                <table class="table table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th>Blood Type</th>
                                            <th>Red Cross</th>
                                            <th>Negros First</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                        $inventoryMap = [];

                                        // Debug the raw inventory data
                                        if (function_exists('secure_log')) {
                                            secure_log("Raw Blood Inventory Data", ['inventory_count' => is_array($bloodInventory) ? count($bloodInventory) : 0]);
                                        }

                                        foreach ($bloodInventory as $item) {
                                            // Normalize to lowercase and remove spaces for consistency
                                            $orgKey = strtolower(str_replace(' ', '', $item['organization_type']));
                                            $inventoryMap[$item['blood_type']][$orgKey] = $item['units'];
                                            // Debug each item being processed
                                            if (function_exists('secure_log')) {
                                                secure_log("Processing inventory item", ['blood_type' => substr($item['blood_type'], 0, 10), 'organization' => substr($orgKey, 0, 50), 'units' => $item['units']]);
                                            }
                                        }

                                        // Debug the mapped inventory
                                        if (function_exists('secure_log')) {
                                            secure_log("Mapped Inventory", ['blood_types_count' => count($inventoryMap)]);
                                        }

                                        foreach ($bloodTypes as $bloodType):
                                            $redcrossunits = $inventoryMap[$bloodType]['redcross'] ?? 0;
                                            $negrosfirstunits = $inventoryMap[$bloodType]['negrosfirst'] ?? 0;
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $bloodType; ?></strong></td>
                                                <td><?php echo (int)$redcrossunits; ?> units</td>
                                                <td><?php echo (int)$negrosfirstunits; ?> units</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Mobile Card View -->
                            <div class="d-md-none">
                                <?php
                                foreach ($bloodTypes as $bloodType):
                                    $redcrossunits = $inventoryMap[$bloodType]['redcross'] ?? 0;
                                    $negrosfirstunits = $inventoryMap[$bloodType]['negrosfirst'] ?? 0;
                                ?>
                                    <div class="card mb-2 border-0 shadow-sm">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong class="text-dark"><?php echo $bloodType; ?></strong>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <div class="d-flex align-items-center">
                                                        <small class="text-muted">Red Cross:</small>
                                                    </div>
                                                    <div class="fw-bold"><?php echo (int)$redcrossunits; ?> units</div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="d-flex align-items-center">
                                                        <small class="text-muted">Negros First:</small>
                                                    </div>
                                                    <div class="fw-bold"><?php echo (int)$negrosfirstunits; ?> units</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h4 class="card-title mb-0">Request Guidelines</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column flex-sm-row mb-3 gap-3">
                                <div class="text-primary">
                                   
                                </div>
                                
                            </div>

                            <div class="d-flex flex-column flex-sm-row mb-3 gap-3">
                                <div class="text-primary">
                                    <i class="bi bi-clipboard-check-fill fs-4"></i>
                                </div>
                                <div>
                                    <h5>Requirements</h5>
                                    <ul class="mb-0 ps-3">
                                        <li>Referral letter from barangay (if don't have a blood card</li>
                                        <li>Styrobox/Cooler with Ice</li>
                                        <li>Corresponding Processing fee</li>
                                    </ul>
                                </div>
                            </div>

                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Handle organization and blood card interaction
        const bloodCardCheckbox = document.getElementById('has_blood_card');
        const organizationSelect = document.getElementById('organization');
        const barangaySelect = document.getElementById('barangay');
        const bloodTypeSelect = document.getElementById('blood_type');
        const bloodTypeHint = document.getElementById('bloodTypeHint');

        // Function to update blood type options based on selected organization
        function updateBloodTypeOptions() {
            const selectedOrg = organizationSelect.value;
            if (!selectedOrg) {
                // If no organization selected, show all options but disable unavailable ones
                Array.from(bloodTypeSelect.options).forEach(option => {
                    if (option.value && !option.disabled) {
                        const redcross = parseInt(option.getAttribute('data-redcross') || '0');
                        const negrosfirst = parseInt(option.getAttribute('data-negrosfirst') || '0');
                        if (redcross === 0 && negrosfirst === 0) {
                            option.disabled = true;
                            option.textContent = option.value + ' (Not Available)';
                            option.style.color = '#999';
                        }
                    }
                });
                bloodTypeHint.textContent = '';
                return;
            }

            const orgKey = selectedOrg === 'redcross' ? 'redcross' : 'negrosfirst';
            const currentSelection = bloodTypeSelect.value;
            let selectedOptionAvailable = false;

            Array.from(bloodTypeSelect.options).forEach(option => {
                if (option.value === '') {
                    // Keep the "Select Blood Type" option enabled
                    return;
                }

                const available = parseInt(option.getAttribute('data-' + orgKey) || '0');
                
                if (available > 0) {
                    option.disabled = false;
                    option.style.color = '';
                    option.textContent = option.value;
                    if (option.value === currentSelection) {
                        selectedOptionAvailable = true;
                    }
                } else {
                    option.disabled = true;
                    option.style.color = '#999';
                    option.textContent = option.value + ' (Not Available)';
                }
            });

            // If current selection is not available, clear it
            if (currentSelection && !selectedOptionAvailable) {
                bloodTypeSelect.value = '';
                bloodTypeHint.textContent = 'Please select a blood type available at ' + (selectedOrg === 'redcross' ? 'Red Cross' : 'Negros First');
                bloodTypeHint.style.color = '#dc2626';
            } else {
                updateBloodTypeHint();
            }
        }

        // Function to update blood type availability hint
        function updateBloodTypeHint() {
            const selectedType = bloodTypeSelect.value;
            const selectedOrg = organizationSelect.value;
            
            if (!selectedType || !selectedOrg) {
                bloodTypeHint.textContent = '';
                return;
            }

            const selectedOption = bloodTypeSelect.options[bloodTypeSelect.selectedIndex];
            const orgKey = selectedOrg === 'redcross' ? 'redcross' : 'negrosfirst';
            const available = parseInt(selectedOption.getAttribute('data-' + orgKey) || '0');
            const orgName = selectedOrg === 'redcross' ? 'Red Cross' : 'Negros First';

            if (available > 0) {
                bloodTypeHint.textContent = available + ' unit(s) available at ' + orgName;
                bloodTypeHint.style.color = '#059669';
            } else {
                bloodTypeHint.textContent = 'Not available at ' + orgName;
                bloodTypeHint.style.color = '#dc2626';
            }
        }

        // Function to update form state based on organization and blood card
        function updateFormState() {
            // Update blood type options first
            updateBloodTypeOptions();
            
            if (organizationSelect.value === 'redcross') {
                // Enable blood card checkbox for Red Cross only if no barangay is selected
                if (barangaySelect.value === '') {
                    bloodCardCheckbox.disabled = false;
                    bloodCardCheckbox.style.pointerEvents = 'auto';
                    bloodCardCheckbox.style.backgroundColor = '';
                } else {
                    bloodCardCheckbox.checked = false;
                    bloodCardCheckbox.disabled = true;
                    bloodCardCheckbox.style.pointerEvents = 'none';
                    bloodCardCheckbox.style.backgroundColor = '#e9ecef';
                }
                
                if (bloodCardCheckbox.checked) {
                    // If blood card is checked, disable and clear barangay selection
                    barangaySelect.disabled = true;
                    barangaySelect.required = false;
                    barangaySelect.value = ''; // Clear the selection
                    barangaySelect.style.pointerEvents = 'none'; // Prevent clicking
                    barangaySelect.style.backgroundColor = '#e9ecef'; // Visual feedback
                } else {
                    // If no blood card, enable barangay selection
                    barangaySelect.disabled = false;
                    barangaySelect.required = true;
                    barangaySelect.style.pointerEvents = 'auto';
                    barangaySelect.style.backgroundColor = '';
                }
            } else if (organizationSelect.value === 'negrosfirst') {
                // For Negros First, disable blood card and require barangay
                bloodCardCheckbox.checked = false;
                bloodCardCheckbox.disabled = true;
                bloodCardCheckbox.style.pointerEvents = 'none';
                bloodCardCheckbox.style.backgroundColor = '#e9ecef';
                barangaySelect.disabled = false;
                barangaySelect.required = true;
                barangaySelect.style.pointerEvents = 'auto';
                barangaySelect.style.backgroundColor = '';
            }
        }

        // Listen for organization changes
        organizationSelect.addEventListener('change', function() {
            if (this.value === 'redcross') {
                // Enable blood card checkbox for Red Cross only if no barangay is selected
                if (barangaySelect.value === '') {
                    bloodCardCheckbox.disabled = false;
                    bloodCardCheckbox.style.pointerEvents = 'auto';
                    bloodCardCheckbox.style.backgroundColor = '';
                } else {
                    bloodCardCheckbox.checked = false;
                    bloodCardCheckbox.disabled = true;
                    bloodCardCheckbox.style.pointerEvents = 'none';
                    bloodCardCheckbox.style.backgroundColor = '#e9ecef';
                }
            } else if (this.value === 'negrosfirst') {
                // For Negros First, disable blood card and require barangay
                bloodCardCheckbox.checked = false;
                bloodCardCheckbox.disabled = true;
                bloodCardCheckbox.style.pointerEvents = 'none';
                bloodCardCheckbox.style.backgroundColor = '#e9ecef';
                barangaySelect.disabled = false;
                barangaySelect.required = true;
                barangaySelect.style.pointerEvents = 'auto';
                barangaySelect.style.backgroundColor = '';
            }
            updateFormState();
        });

        // Listen for blood type selection changes
        bloodTypeSelect.addEventListener('change', function() {
            updateBloodTypeHint();
        });

        // Listen for blood card checkbox changes
        bloodCardCheckbox.addEventListener('change', function() {
            if (this.checked) {
                // If blood card is checked, disable and clear barangay selection
                barangaySelect.disabled = true;
                barangaySelect.required = false;
                barangaySelect.value = ''; // Clear the selection
                barangaySelect.style.pointerEvents = 'none'; // Prevent clicking
                barangaySelect.style.backgroundColor = '#e9ecef'; // Visual feedback
            } else {
                // If blood card is unchecked, enable barangay selection
                barangaySelect.disabled = false;
                barangaySelect.required = true;
                barangaySelect.style.pointerEvents = 'auto';
                barangaySelect.style.backgroundColor = '';
            }
        });

        // Listen for barangay selection changes
        barangaySelect.addEventListener('change', function() {
            if (this.value !== '') {
                // If barangay is selected, uncheck and disable blood card
                bloodCardCheckbox.checked = false;
                bloodCardCheckbox.disabled = true;
                bloodCardCheckbox.style.pointerEvents = 'none';
                bloodCardCheckbox.style.backgroundColor = '#e9ecef';
            } else {
                // If no barangay is selected, enable blood card checkbox for Red Cross
                if (organizationSelect.value === 'redcross') {
                    bloodCardCheckbox.disabled = false;
                    bloodCardCheckbox.style.pointerEvents = 'auto';
                    bloodCardCheckbox.style.backgroundColor = '';
                }
            }
            updateFormState();
        });

        // Initial state setup
        updateFormState();
        updateBloodTypeOptions();

        // Form submission feedback
        const form = document.getElementById('bloodRequestForm');
        let isConfirmed = false;
        
        const handleFormSubmit = function(e) {
            // If already confirmed, allow submission
            if (isConfirmed) {
                isConfirmed = false; // Reset for next time
                return true;
            }
            
            // Prevent default submission
            e.preventDefault();
            e.stopPropagation();
            
            // Client-side file size validation (max 5MB)
            const reqForm = document.getElementById('request_form');
            const bloodCard = document.getElementById('blood_card');
            const maxBytes = 5 * 1024 * 1024;
            if (reqForm && reqForm.files && reqForm.files[0] && reqForm.files[0].size > maxBytes) {
                alert('Hospital request form must be 5MB or smaller.');
                return false;
            }
            if (bloodCard && bloodCard.files && bloodCard.files[0] && bloodCard.files[0].size > maxBytes) {
                alert('Blood card file must be 5MB or smaller.');
                return false;
            }

            // Show confirmation modal
            const confirmModalEl = document.getElementById('confirmSubmissionModal');
            if (confirmModalEl) {
                // Ensure modal is in the body (Bootstrap should do this, but ensure it)
                if (confirmModalEl.parentElement !== document.body) {
                    document.body.appendChild(confirmModalEl);
                }
                
                // Get existing modal instance or create new one
                let confirmModal = bootstrap.Modal.getInstance(confirmModalEl);
                if (!confirmModal) {
                    confirmModal = new bootstrap.Modal(confirmModalEl, {
                        backdrop: 'static',
                        keyboard: false
                    });
                }
                
                // Show the modal
                confirmModal.show();
                
                // Force modal and backdrop to be on top with proper z-index
                setTimeout(function() {
                    confirmModalEl.style.zIndex = '9999';
                    confirmModalEl.style.position = 'fixed';
                    
                    // Find the backdrop (should be the last one if multiple modals)
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    const backdrop = backdrops[backdrops.length - 1];
                    if (backdrop) {
                        backdrop.style.zIndex = '9998';
                        backdrop.style.position = 'fixed';
                        backdrop.style.pointerEvents = 'auto';
                    }
                    
                    // Ensure all buttons are clickable
                    const buttons = confirmModalEl.querySelectorAll('button, .btn');
                    buttons.forEach(function(btn) {
                        btn.style.pointerEvents = 'auto';
                        btn.style.cursor = 'pointer';
                        btn.style.zIndex = '10003';
                    });
                }, 50);
            }
        };
        
        form.addEventListener('submit', handleFormSubmit);

        // Handle confirmation modal buttons
        const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
        const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');
        
        if (confirmSubmitBtn) {
            confirmSubmitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Close the modal first
                const modalEl = document.getElementById('confirmSubmissionModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }
                
                // Wait for modal to fully close, then submit
                setTimeout(function() {
                    // Update submit button
                    const submitBtn = document.getElementById('submitBtn');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                        submitBtn.disabled = true;
                    }
                    
                    // Remove the event listener to prevent the confirmation modal from showing again
                    form.removeEventListener('submit', handleFormSubmit);
                    
                    // Set the confirmed flag
                    isConfirmed = true;
                    
                    // Validate form first
                    if (form.checkValidity()) {
                        // Submit the form directly
                        form.submit();
                    } else {
                        // If validation fails, show validation messages
                        form.reportValidity();
                        // Re-enable submit button
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = 'Submit Request';
                        }
                        // Re-add event listener
                        form.addEventListener('submit', handleFormSubmit);
                    }
                }, 300);
            });
        }
        
        if (cancelSubmitBtn) {
            cancelSubmitBtn.addEventListener('click', function() {
                // Reset submit button if needed
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Submit Request';
                }
            });
        }

        // Handle blood card upload visibility
        const bloodCardUploadSection = document.getElementById('bloodCardUploadSection');

        // Render QR code in modal if we have a confirmation payload and show modal
        const modalQrContainer = document.getElementById('modalQrContainer');
        const confirmationPayload = <?php echo $confirmationPayload ? ('"' . addslashes($confirmationPayload) . '"') : 'null'; ?>;
        if (modalQrContainer && confirmationPayload) {
            const qr = new QRCode(modalQrContainer, {
                text: confirmationPayload,
                width: 160,
                height: 160,
                correctLevel: QRCode.CorrectLevel.M
            });
            // Show modal
            const confModalEl = document.getElementById('confirmationModal');
            if (confModalEl) {
                // Ensure modal is in the body (Bootstrap should do this, but ensure it)
                if (confModalEl.parentElement !== document.body) {
                    document.body.appendChild(confModalEl);
                }
                
                const confModal = new bootstrap.Modal(confModalEl);
                confModal.show();
                
                // Force modal and backdrop to be on top with proper z-index
                setTimeout(function() {
                    confModalEl.style.zIndex = '9999';
                    confModalEl.style.position = 'fixed';
                    
                    // Find the backdrop (should be the last one if multiple modals)
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    const backdrop = backdrops[backdrops.length - 1];
                    if (backdrop) {
                        backdrop.style.zIndex = '9998';
                        backdrop.style.position = 'fixed';
                        backdrop.style.pointerEvents = 'auto';
                    }
                    
                    // Ensure all buttons are clickable
                    const buttons = confModalEl.querySelectorAll('button, .btn');
                    buttons.forEach(function(btn) {
                        btn.style.pointerEvents = 'auto';
                        btn.style.cursor = 'pointer';
                        btn.style.zIndex = '10003';
                    });
                }, 50);
                
                // Auto-refresh page when modal is closed
                confModalEl.addEventListener('hidden.bs.modal', function() {
                    // Refresh the page to reset the form and clear success state
                    window.location.href = 'request-blood.php';
                });
            }
        }

        // Print receipt handler (print only the receipt as a clean document)
        const printBtn = document.getElementById('printReceiptBtn');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                const modalBody = document.getElementById('modalReceipt');
                if (!modalBody) return;

                const basePath = '../../';
                const w = window.open('', '_blank');
                if (!w) { return; }

                // Basic document shell
                w.document.open();
                w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Blood Request Receipt</title>');
                w.document.write('<style>@page{size:80mm auto;margin:8mm;}body{font-family:Arial,sans-serif;color:#000;} .header{display:flex;align-items:center;gap:10px;margin-bottom:10px;} .header img{height:36px;} .title{font-weight:700;font-size:14px;} .sub{font-size:11px;color:#444;} .hr{border-top:1px dashed #888;margin:10px 0;} .row{display:flex;justify-content:space-between;margin:6px 0;font-size:12px;} .label{color:#555;}</style>');
                w.document.write('</head><body>');

                // Header
                const header = w.document.createElement('div');
                header.className = 'header';
                const logo = document.createElement('img');
                logo.src = basePath + 'assets/img/rclogo.jpg';
                logo.onerror = function(){ this.onerror=null; this.src = basePath + 'assets/img/rclgo.png'; };
                const titleWrap = document.createElement('div');
                const t = document.createElement('div'); t.className='title'; t.textContent='Philippine Red Cross';
                const s = document.createElement('div'); s.className='sub'; s.textContent='Blood Request Receipt';
                titleWrap.appendChild(t); titleWrap.appendChild(s);
                header.appendChild(logo); header.appendChild(titleWrap);
                w.document.body.appendChild(header);

                const hr = document.createElement('div'); hr.className='hr'; w.document.body.appendChild(hr);

                // Body: clone modal content only
                const bodySource = modalBody.cloneNode(true);
                w.document.body.appendChild(bodySource);

                w.document.write('<script>setTimeout(function(){window.print();setTimeout(function(){window.close();},300);},200);<\/script>');
                w.document.write('</body></html>');
                w.document.close();
            });
        }

        // Download QR as PNG
        const downloadQrBtn = document.getElementById('downloadQrBtn');
        if (downloadQrBtn && modalQrContainer) {
            downloadQrBtn.addEventListener('click', function() {
                const img = modalQrContainer.querySelector('img');
                const canvas = modalQrContainer.querySelector('canvas');
                let dataUrl = '';
                if (img && img.src) {
                    dataUrl = img.src;
                } else if (canvas) {
                    dataUrl = canvas.toDataURL('image/png');
                }
                if (dataUrl) {
                    const a = document.createElement('a');
                    a.href = dataUrl;
                    a.download = `blood_request_qr_<?php echo htmlspecialchars((string)($requestId ?? '')); ?>.png`;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                }
            });
        }

        // Download receipt as PDF
        const pdfBtn = document.getElementById('downloadPdfBtn');
        if (pdfBtn) {
            pdfBtn.addEventListener('click', async function() {
                const card = document.getElementById('receiptCard');
                if (!card) return;
                const canvas = await html2canvas(card, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
                const imgData = canvas.toDataURL('image/png');
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const imgProps = pdf.getImageProperties(imgData);
                const imgWidth = pageWidth - 20; // 10mm margins left/right
                const imgHeight = (imgProps.height * imgWidth) / imgProps.width;
                let y = 10;
                if (imgHeight > pageHeight - 20) {
                    const ratio = (pageHeight - 20) / imgHeight;
                    const adjWidth = imgWidth * ratio;
                    const adjHeight = imgHeight * ratio;
                    const x = (pageWidth - adjWidth) / 2;
                    pdf.addImage(imgData, 'PNG', x, y, adjWidth, adjHeight);
                } else {
                    const x = 10;
                    pdf.addImage(imgData, 'PNG', x, y, imgWidth, imgHeight);
                }
                pdf.save(`blood_request_<?php echo htmlspecialchars((string)($requestId ?? '')); ?>.pdf`);
            });
        }

        const bloodCardInput = document.getElementById('blood_card');

        bloodCardCheckbox.addEventListener('change', function() {
            if (this.checked) {
                bloodCardUploadSection.style.display = 'block';
                bloodCardInput.required = true;
            } else {
                bloodCardUploadSection.style.display = 'none';
                bloodCardInput.required = false;
                bloodCardInput.value = ''; // Clear the file input
            }
        });

        // Handle hospital "Others" option
        const hospitalSelect = document.getElementById('hospital');
        const hospitalOthersSection = document.getElementById('hospitalOthersSection');
        const hospitalOthersInput = document.getElementById('hospital_others');

        if (hospitalSelect && hospitalOthersSection && hospitalOthersInput) {
            hospitalSelect.addEventListener('change', function() {
                if (this.value === 'Others') {
                    hospitalOthersSection.style.display = 'block';
                    hospitalOthersInput.required = true;
                } else {
                    hospitalOthersSection.style.display = 'none';
                    hospitalOthersInput.required = false;
                    hospitalOthersInput.value = ''; // Clear the input
                }
            });

            // Check on page load if "Others" is already selected
            if (hospitalSelect.value === 'Others') {
                hospitalOthersSection.style.display = 'block';
                hospitalOthersInput.required = true;
            }
        }

        // Title case formatting function (Title Case with single spacing)
        const formatToTitleCase = (str) => {
            if (!str) return '';
            // Collapse multiple spaces to single space and trim
            let s = str.replace(/\s+/g, ' ').trim();
            // Title case words consisting of letters; preserve single spaces
            return s.split(' ').map(w => {
                if (!w) return ''; // Handle empty strings from split
                const m = w.match(/^([A-Za-z])(.*)$/);
                if (!m) return w;
                return m[1].toUpperCase() + m[2].toLowerCase();
            }).filter(w => w.length > 0).join(' '); // Filter empty and join with single space
        };

        // Attach title case formatting to text inputs and textareas
        const attachTitlecase = (el) => {
            if (!el || el.dataset.titlecaseBound === '1') return;
            
            let lastValue = el.value;
            
            el.addEventListener('input', function(e) {
                const cursorPos = this.selectionStart;
                let value = this.value;
                
                // Prevent multiple consecutive spaces
                value = value.replace(/\s{2,}/g, ' ');
                
                // Apply title case formatting while typing
                // Format each word as user types (when space is pressed or word completes)
                const words = value.split(' ');
                const formattedWords = words.map((word, index) => {
                    if (!word) return '';
                    // Only format if word has at least one letter
                    const match = word.match(/^([A-Za-z])(.*)$/);
                    if (match) {
                        return match[1].toUpperCase() + match[2].toLowerCase();
                    }
                    return word;
                });
                
                value = formattedWords.join(' ');
                
                // Update value if changed
                if (value !== this.value) {
                    const oldValue = this.value;
                    this.value = value;
                    // Try to maintain cursor position as much as possible
                    const diff = value.length - oldValue.length;
                    const newPos = Math.min(Math.max(0, cursorPos + diff), this.value.length);
                    this.setSelectionRange(newPos, newPos);
                }
                
                lastValue = this.value;
            });
            
            el.addEventListener('blur', function() {
                const cursorPos = this.selectionStart;
                if (this.value.trim().length > 0) {
                    this.value = formatToTitleCase(this.value);
                    // Move cursor to end after formatting
                    this.setSelectionRange(cursorPos, cursorPos);
                }
            });
            
            el.dataset.titlecaseBound = '1';
            // Initial normalize if prefilled
            if (el.value) el.value = formatToTitleCase(el.value);
        };

        // Apply formatting to all text inputs and textareas
        // Fields that should have title case: reason, hospital_others
        const textInputs = document.querySelectorAll('input[type="text"], textarea');
        textInputs.forEach(function(input) {
            // Skip number, date, time, and file inputs
            if (input.type === 'number' || input.type === 'date' || input.type === 'time' || input.type === 'file') {
                return;
            }
            attachTitlecase(input);
        });

        // Numbers only for number fields
        const numberInputs = document.querySelectorAll('input[type="number"]');
        numberInputs.forEach(function(input) {
            input.addEventListener('input', function() {
                // Remove any non-digit characters (spaces not allowed)
                this.value = this.value.replace(/[^\d]/g, '');
            });
        });
    });
</script>

<style>
/* Red Theme Override for Request Blood Page */
:root {
    --patient-primary: #DC2626;
    --patient-primary-dark: #B91C1C;
    --patient-primary-light: #EF4444;
    --patient-accent: #F87171;
    --patient-accent-dark: #DC2626;
    --patient-accent-light: #FEE2E2;
    --patient-cream: #FEF2F2;
    --patient-cream-light: #FEE2E2;
    --patient-header-gradient: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
    --patient-bg-gradient: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
}

/* Override Sidebar for Patient Dashboard - Red Theme */
.sidebar {
    background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%) !important;
    border-right: none;
    box-shadow: 4px 0 20px rgba(220, 38, 38, 0.3);
}

.sidebar-header {
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.2) 0%, rgba(185, 28, 28, 0.2) 100%) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
}

.sidebar-logo {
    background: rgba(255, 255, 255, 0.2) !important;
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
}

.sidebar .nav-link {
    color: white !important;
}

.sidebar .nav-link:hover {
    background: rgba(255, 255, 255, 0.15) !important;
    color: white !important;
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(255, 255, 255, 0.2);
}

.sidebar .nav-link.active {
    background: rgba(255, 255, 255, 0.25) !important;
    color: white !important;
    box-shadow: 0 2px 8px rgba(255, 255, 255, 0.3);
    border-left: 4px solid #F87171 !important;
    font-weight: 600;
}

.sidebar .nav-link i {
    color: white !important;
}

.sidebar .nav-link:hover i,
.sidebar .nav-link.active i {
    color: white !important;
}

.sidebar-footer {
    background: rgba(185, 28, 28, 0.3) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.sidebar-footer .btn {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    color: white !important;
}

.sidebar-footer .btn:hover {
    background: rgba(255, 255, 255, 0.3) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
    color: white !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(185, 28, 28, 0.2) !important;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3) !important;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5) !important;
}

/* Enhanced Dashboard Styles */
.dashboard-content {
    background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 50%, #FECACA 100%);
    position: relative;
    overflow: hidden;
    margin-left: 280px; /* Only sidebar */
    padding-top: 100px; /* Space for top header */
}


.dashboard-content::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(220, 38, 38, 0.1) 0%, transparent 70%);
    border-radius: 50%;
    animation: float 20s ease-in-out infinite;
    z-index: 0;
}

.dashboard-content::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(185, 28, 28, 0.08) 0%, transparent 70%);
    border-radius: 50%;
    animation: float 25s ease-in-out infinite reverse;
    z-index: 0;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    50% { transform: translate(30px, -30px) rotate(180deg); }
}

.dashboard-main {
    position: relative;
    z-index: 1;
    padding: 1.5rem;
    padding-top: 0;
    margin-top: 0;
}

.dashboard-main > *:first-child {
    margin-top: 0;
}

.dashboard-header {
    position: fixed;
    top: 0;
    left: 280px; /* After sidebar */
    right: 0;
    height: 100px;
    background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
    color: white;
    z-index: 1020;
    box-shadow: 0 4px 20px rgba(220, 38, 38, 0.3);
    display: flex;
    align-items: center;
    padding: 0 2rem;
    overflow: visible;
}

.dashboard-header .page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: white !important;
    margin: 0;
    letter-spacing: 0.5px;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.dashboard-header .header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    z-index: 1021;
}

/* Header User Dropdown */
.dashboard-header .dropdown {
    position: relative;
    z-index: 1021;
}

.dashboard-header .btn-outline-secondary {
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: white !important;
    background: rgba(255, 255, 255, 0.1) !important;
    padding: 0.625rem 1rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}

.dashboard-header .btn-outline-secondary:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.4) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.dashboard-header .btn-outline-secondary span {
    color: white !important;
}

.dashboard-header .avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.5rem;
}

.dashboard-header .avatar i {
    color: white;
    font-size: 1.25rem;
}

.dashboard-header .dropdown-menu {
    position: absolute !important;
    right: 0 !important;
    left: auto !important;
    top: 100% !important;
    margin-top: 0.5rem !important;
    z-index: 1050 !important;
    min-width: 200px;
}

/* Notification Bell in Header */
.dashboard-header .notification-bell .btn {
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: white !important;
    background: rgba(255, 255, 255, 0.1) !important;
    padding: 0.625rem 1rem;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.dashboard-header .notification-bell .btn:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.4) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.dashboard-header .notification-bell .badge {
    background: #EF4444 !important;
    color: white;
}

.dashboard-header .notification-bell .btn i {
    color: white !important;
}

/* Critical blood status should remain red - in availability tables */
table .badge.bg-danger,
.table .badge.bg-danger {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%) !important;
    color: white;
}

/* Critical availability legend badge */
.critical-legend .badge.bg-danger,
.critical-badge.bg-danger {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%) !important;
    color: white;
}

/* Mobile card view critical badges */
.card .badge.bg-danger[data-critical="true"],
.badge.bg-danger[data-critical="true"] {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%) !important;
    color: white;
}

/* Critical status text should remain red */
table .text-danger,
.table .text-danger,
.text-danger[data-critical="true"],
td.text-danger {
    color: #EF4444 !important;
}

/* Good availability should be green */
table .text-success,
.table .text-success,
td.text-success,
.text-success {
    color: #10B981 !important;
}

/* Good availability badge should be green */
.badge.bg-success,
.badge.bg-success.me-2,
.mt-3 .badge.bg-success {
    background-color: #10B981 !important;
    background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important;
    color: white !important;
}

/* Modal z-index fix - Must be higher than fixed header (1020) and sidebar (1001-1030) */
.modal#confirmSubmissionModal {
    z-index: 9999 !important;
    position: fixed !important;
}

.modal-backdrop.show {
    z-index: 9998 !important;
    background-color: rgba(0, 0, 0, 0.5) !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
}

#confirmSubmissionModal .modal-dialog {
    z-index: 10000 !important;
    position: relative;
    margin: 1.75rem auto;
}

#confirmSubmissionModal .modal-content {
    position: relative;
    z-index: 10001 !important;
    pointer-events: auto !important;
}

#confirmSubmissionModal .modal-header,
#confirmSubmissionModal .modal-body,
#confirmSubmissionModal .modal-footer {
    position: relative;
    z-index: 10002 !important;
    pointer-events: auto !important;
}

#confirmSubmissionModal .modal-footer .btn,
#confirmSubmissionModal button,
#confirmSubmissionModal .btn {
    position: relative;
    z-index: 10003 !important;
    pointer-events: auto !important;
    cursor: pointer !important;
}

/* Ensure modal is always on top of fixed elements */
#confirmSubmissionModal.show {
    z-index: 9999 !important;
    display: block !important;
}

/* Request Details Modal (After Submission) - Same z-index fix */
.modal#confirmationModal {
    z-index: 9999 !important;
    position: fixed !important;
}

#confirmationModal .modal-dialog {
    z-index: 10000 !important;
    position: relative;
    margin: 1.75rem auto;
}

#confirmationModal .modal-content {
    position: relative;
    z-index: 10001 !important;
    pointer-events: auto !important;
}

#confirmationModal .modal-header,
#confirmationModal .modal-body,
#confirmationModal .modal-footer {
    position: relative;
    z-index: 10002 !important;
    pointer-events: auto !important;
}

#confirmationModal .modal-footer .btn,
#confirmationModal button,
#confirmationModal .btn {
    position: relative;
    z-index: 10003 !important;
    pointer-events: auto !important;
    cursor: pointer !important;
}

#confirmationModal.show {
    z-index: 9999 !important;
    display: block !important;
}

/* Prevent any fixed elements from blocking the modal */
.dashboard-header,
.sidebar,
.dashboard-container > div:first-child,
header.fixed-top,
.fixed-left {
    z-index: 1030 !important; /* Lower than modal */
}

/* Responsive adjustments */
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
    
    .dashboard-header h2 {
        font-size: 1.25rem;
    }
    
    .form-control {
        font-size: 16px; /* Prevents zoom on iOS */
    }
    
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .card {
        margin: 0 0.5rem 1rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .col-12.col-sm-6 {
        margin-bottom: 1rem;
    }
    
    /* Blood Availability Mobile Styles */
    .col-12.col-lg-4 {
        margin-top: 1rem;
    }
    
    /* Ensure blood availability cards stack properly on mobile */
    .col-12.col-lg-4 .card {
        margin-left: 0;
        margin-right: 0;
    }
}

@media (max-width: 576px) {
    .dashboard-header {
        padding: 0.75rem;
    }
    
    .dashboard-header h2 {
        font-size: 1.125rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .form-label {
        font-size: 0.875rem;
    }
    
    .btn {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
    
    .table th,
    .table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.75rem;
    }
}

/* Tablet Responsive */
@media (max-width: 992px) and (min-width: 769px) {
    .card {
        margin-bottom: 1.5rem;
    }
    
    .col-sm-6 {
        margin-bottom: 1rem;
    }
    
}
</style>

<!-- Confirmation Modal Before Submission - Placed outside dashboard-content for proper z-index -->
<div class="modal fade" id="confirmSubmissionModal" tabindex="-1" aria-labelledby="confirmSubmissionModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%); color: white;">
                <h5 class="modal-title" id="confirmSubmissionModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Submission
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><strong>Are you sure that all the information provided is accurate?</strong></p>
                <p class="text-muted small mb-0">Please review your blood request details before submitting. Once submitted, you will receive a confirmation with a QR code.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelSubmitBtn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmSubmitBtn" style="background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); border: none;">
                    <i class="bi bi-check-circle me-2"></i>Yes, Submit Request
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Request Details Modal (After Submission) - Placed outside dashboard-content for proper z-index -->
<?php if ($formSubmitted && $success): ?>
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-white">
                <h5 class="modal-title" id="confirmationModalLabel">Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalReceipt">
                <div class="row g-3">
                    <div class="col-md-8">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Request ID</dt>
                            <dd class="col-sm-8">#<?php echo htmlspecialchars($requestId ?? ''); ?></dd>
                            <dt class="col-sm-4">Patient</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($patient['name'] ?? ('Patient#'.$patientId)); ?></dd>
                            <dt class="col-sm-4">Blood Type</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($bloodType ?? ''); ?></dd>
                            <dt class="col-sm-4">Units</dt>
                            <dd class="col-sm-8"><?php echo (int)($units ?? 0); ?></dd>
                            <dt class="col-sm-4">Blood Bank</dt>
                            <dd class="col-sm-8 text-capitalize"><?php echo htmlspecialchars($organization ?? ''); ?></dd>
                            <dt class="col-sm-4">Required By</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($requiredDate ?? ''); ?> <?php echo !empty($required_time) ? htmlspecialchars($required_time) : ''; ?></dd>
                            <dt class="col-sm-4">Hospital</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($hospital ?? ''); ?></dd>
                        </dl>
                    </div>
                    <div class="col-md-4 text-center">
                        <div id="modalQrContainer" class="d-inline-block border p-2 rounded"></div>
                        <div class="small text-muted mt-2">Show this QR at the blood bank</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" id="printReceiptBtn"><i class="bi bi-printer me-1"></i> Print</button>
                <button class="btn btn-outline-primary" id="downloadQrBtn"><i class="bi bi-qr-code me-1"></i> Download QR</button>
                <button class="btn btn-outline-success" id="downloadPdfBtn"><i class="bi bi-file-earmark-pdf me-1"></i> Download PDF</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


</body>
</html>