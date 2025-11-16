<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

// Include database connection
require_once '../../config/db.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $requestId = $_POST['request_id'] ?? null;
    $bloodBankId = $_POST['blood_bank_id'] ?? null;
    $referralDate = $_POST['referral_date'] ?? date('Y-m-d');
    $barangayId = $_SESSION['user_id'];

    // Validate required fields
    if (!$requestId || !$bloodBankId) {
        $_SESSION['error'] = "Missing required fields.";
        header("Location: index.php");
        exit;
    }

    // Get blood request details
    $bloodRequest = getRow("SELECT * FROM blood_requests WHERE id = ?", [$requestId]);
    if (!$bloodRequest) {
        $_SESSION['error'] = "Blood request not found.";
        header("Location: index.php");
        exit;
    }

    // Handle file upload
    if (!isset($_FILES['referral_document']) || $_FILES['referral_document']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Referral document is required.";
        header("Location: index.php");
        exit;
    }

    $fileTmpPath = $_FILES['referral_document']['tmp_name'];
    $fileName = $_FILES['referral_document']['name'];
    $fileType = $_FILES['referral_document']['type'];
    $fileSize = $_FILES['referral_document']['size'];
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed) || $fileSize > 5 * 1024 * 1024) {
        $_SESSION['error'] = "Invalid file type or size.";
        header("Location: index.php");
        exit;
    }
    $fileData = file_get_contents($fileTmpPath);

    // Begin transaction
    beginTransaction();

    try {
        // Insert into referrals table
        $sql = "INSERT INTO referrals 
            (blood_request_id, barangay_id, status, referral_date, referral_document_name, referral_document_type, referral_document_data, created_at, updated_at)
            VALUES (?, ?, 'pending', ?, ?, ?, ?, NOW(), NOW())";
        $params = [
            $requestId,
            $barangayId,
            $referralDate,
            $fileName,
            $fileType,
            $fileData
        ];

        // Before insert
        if (!$requestId || !$barangayId || !$referralDate || !$fileName || !$fileType || !$fileData) {
            throw new Exception("One or more required values are empty.");
        }

        global $pdo;
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($params)) {
            $errorInfo = implode(' | ', $stmt->errorInfo());
            throw new Exception("Failed to insert referral. SQL Error: " . $errorInfo);
        }

        // Update referral_status to track that this request has been referred
        // Note: We don't update the main 'status' field because 'Referred' is not a valid enum value
        // The status remains 'Pending' until the blood bank processes it
        $updateResult = updateRow(
            "UPDATE blood_requests SET referral_status = 'pending', updated_at = NOW() WHERE id = ?",
            [$requestId]
        );
        // updateRow returns false on failure, or number of affected rows on success
        if ($updateResult === false) {
            // Log the error but don't fail the transaction since referral was created successfully
            // Don't log user-controlled data (request_id) for security
            secure_log("Warning: Failed to update referral_status for blood request");
        }

        // Get patient_id from the blood request
        $patientId = $bloodRequest['patient_id'];

        // Insert notification for the patient
        $notifTitle = "New Referral Issued";
        $notifMsg = "A new referral has been issued for your blood request.";
        $notifLink = "../../dashboard/patient/referrals.php";
        $notifRole = "patient";
        executeQuery(
            "INSERT INTO notifications (title, message, user_id, user_role, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [$notifTitle, $notifMsg, $patientId, $notifRole, $notifLink]
        );

        // Send SMS notification to patient about referral
        try {
            require_once '../../includes/sim800c_sms.php';
            require_once '../../includes/notification_templates.php';
            
            // Get patient information
            $patientInfo = getRow("SELECT name, phone FROM patient_users WHERE id = ?", [$patientId]);
            if ($patientInfo && !empty($patientInfo['phone'])) {
                $patientPhone = $patientInfo['phone'];
                $patientName = $patientInfo['name'] ?? '';
                
                // Try to decrypt phone number if encrypted, but keep original if decryption fails (plain text)
                if (function_exists('decrypt_value')) {
                    $decryptedPhone = decrypt_value($patientPhone);
                    if (!empty($decryptedPhone)) {
                        $patientPhone = $decryptedPhone;
                    }
                }
                
                if (!empty($patientPhone) && trim($patientPhone) !== '') {
                    // Get organization type from blood request
                    $orgType = strtolower($bloodRequest['organization_type'] ?? 'redcross');
                    if ($orgType !== 'redcross' && $orgType !== 'negrosfirst') {
                        $orgType = 'redcross';
                    }
                    
                    // Build professional SMS message
                    $smsMessage = "Hello {$patientName}, this is from " . get_institution_name($orgType) . ". ";
                    $smsMessage .= "A new referral has been issued for your blood request (Request ID: {$requestId}). ";
                    $smsMessage .= "The referral has been submitted to the blood bank for review. ";
                    $smsMessage .= "You will be notified once your request is processed. Thank you!";
                    $smsMessage = format_notification_message($smsMessage);
                    
                    secure_log('[BARANGAY_SMS] Sending referral SMS to patient ID: ' . $patientId . ', Phone: ' . substr($patientPhone, 0, 4) . '****');
                    
                    $smsResult = send_sms_sim800c($patientPhone, $smsMessage);
                    
                    if ($smsResult['success']) {
                        secure_log('[BARANGAY_SMS] Referral SMS sent successfully to patient');
                    } else {
                        $smsError = $smsResult['error'] ?? 'Unknown error';
                        secure_log('[BARANGAY_SMS] Failed to send referral SMS: ' . $smsError);
                    }
                }
            }
        } catch (Exception $smsEx) {
            secure_log('[BARANGAY_SMS_ERR] Exception in referral SMS: ' . $smsEx->getMessage());
            // Don't block referral if SMS fails
        }

        // Commit transaction
        commitTransaction();

        $_SESSION['success'] = "Referral successfully issued.";
        header("Location: blood-requests.php");
        exit;

    } catch (Exception $e) {
        rollbackTransaction();
        $_SESSION['error'] = "Failed to issue referral: " . $e->getMessage();
        header("Location: blood-requests.php");
        exit;
    }
} else {
    header("Location: blood-requests.php");
    exit;
}
?>