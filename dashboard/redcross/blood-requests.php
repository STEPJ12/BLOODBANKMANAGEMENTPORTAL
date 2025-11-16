<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'redcross') {
    header("Location: ../../loginredcross.php?role=redcross");
    exit;
}

// Set page title
$pageTitle = "Blood Requests";

// Include database connection
require_once '../../config/db.php';
echo '<script src="../../assets/js/universal-print.js"></script>';

// Get Red Cross information
$redcrossId = $_SESSION['user_id'];

// Process request action (approve/reject)
$message = '';
$alertType = '';

// Success feedback via PRG pattern from session
if (isset($_SESSION['blood_request_message'])) {
    $message = $_SESSION['blood_request_message'];
    $alertType = $_SESSION['blood_request_message_type'] ?? 'success';
    unset($_SESSION['blood_request_message']);
    unset($_SESSION['blood_request_message_type']);
}

// Also check URL parameter for backward compatibility
if (isset($_GET['msg']) && empty($message)) {
    switch ($_GET['msg']) {
        case 'approved':
            $message = 'Blood request has been approved successfully.';
            $alertType = 'success';
            break;
        case 'rejected':
            $message = 'Blood request has been rejected.';
            $alertType = 'success';
            break;
        case 'completed':
            $message = 'Blood request has been completed.';
            $alertType = 'success';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['success'])) {
    // CSRF protection
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid form token. Please refresh the page and try again.';
        $alertType = 'danger';
    } else {
    if (isset($_POST['approve_request'])) {
        $requestId = sanitize($_POST['request_id']);
        
        // Check if request exists and is in a state that allows approval (prevents duplicate processing)
        $request = getRow("SELECT id, patient_id, blood_type, units_requested, status 
            FROM blood_requests 
            WHERE id = ? AND organization_type = 'redcross'",
            [$requestId]
        );

        if (!$request) {
            $message = 'Invalid request.';
            $alertType = 'danger';
        } elseif (!empty($request['status']) && strtolower(trim($request['status'])) !== 'pending' && strtolower(trim($request['status'])) !== 'referred') {
            // Request already processed (not pending or referred)
            $message = 'This request has already been processed and cannot be approved again.';
            $alertType = 'warning';
        } else {
            // Double-check current status before updating (race condition protection)
            $currentStatus = getRow("SELECT status FROM blood_requests WHERE id = ? AND organization_type = 'redcross' FOR UPDATE", [$requestId]);
            
            if ($currentStatus && !empty($currentStatus['status']) && strtolower(trim($currentStatus['status'])) !== 'pending' && strtolower(trim($currentStatus['status'])) !== 'referred') {
                // Status changed between check and update
                $message = 'This request has already been processed. Please refresh the page.';
                $alertType = 'warning';
            } else {
                // Update the request status with condition to prevent duplicates
                $conn = getConnection();
                $stmt = $conn->prepare("
                    UPDATE blood_requests
                    SET status = 'Approved', processed_date = NOW(), notes = ?
                    WHERE id = ? AND organization_type = 'redcross'
                      AND (status IS NULL OR TRIM(LOWER(status)) = 'pending' OR TRIM(LOWER(status)) = 'referred')
                ");
                $updateResult = $stmt->execute([normalize_input($_POST['notes'] ?? ''), $requestId]);
                $affectedRows = $stmt->rowCount();
                
                if ($updateResult !== false && $affectedRows > 0) {
                    // Fetch updated request for notifications (including pickup date, time, and location)
                    $updatedRequest = getRow("SELECT id, patient_id, blood_type, units_requested, required_date, required_time, hospital FROM blood_requests WHERE id = ?", [$requestId]);
                    
                    if ($updatedRequest) {
                        // Get patient information including phone number for personalized message and SMS
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C_DEBUG] Fetching patient info', ['patient_id' => $updatedRequest['patient_id']]);
                        }
                        $patientInfo = getRow("SELECT name, phone FROM patient_users WHERE id = ?", [$updatedRequest['patient_id']]);
                        
                        if (empty($patientInfo)) {
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C_DEBUG] ERROR: Patient not found', ['patient_id' => $updatedRequest['patient_id']]);
                            }
                        } else {
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C_DEBUG] Patient found', [
                                    'has_name' => !empty($patientInfo['name']),
                                    'has_phone' => !empty($patientInfo['phone']),
                                    'phone_prefix' => !empty($patientInfo['phone']) ? substr($patientInfo['phone'], 0, 4) . '****' : 'EMPTY'
                                ]);
                            }
                        }
                        
                        $patientName = $patientInfo['name'] ?? '';
                        $patientPhone = $patientInfo['phone'] ?? '';
                        
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C_DEBUG] Phone before decryption', [
                                'phone_prefix' => !empty($patientPhone) ? substr($patientPhone, 0, 4) . '****' : 'EMPTY',
                                'phone_length' => !empty($patientPhone) ? strlen($patientPhone) : 0
                            ]);
                        }
                        
                        // Try to decrypt phone number if encrypted, but keep original if decryption fails (plain text)
                        if (!empty($patientPhone) && function_exists('decrypt_value')) {
                            $originalPhone = $patientPhone;
                            $decryptedPhone = decrypt_value($patientPhone);
                            // If decryption returns null/empty, it means the phone is NOT encrypted (plain text)
                            // In that case, use the original value
                            if (!empty($decryptedPhone)) {
                                $patientPhone = $decryptedPhone;
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C_DEBUG] Phone after decryption - was encrypted', [
                                        'phone_prefix' => substr($patientPhone, 0, 4) . '****',
                                        'phone_length' => strlen($patientPhone)
                                    ]);
                                }
                            } else {
                                // Decryption failed - phone is plain text, keep original
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C_DEBUG] Phone decryption returned empty - phone is plain text', [
                                        'phone_prefix' => substr($patientPhone, 0, 4) . '****'
                                    ]);
                                }
                            }
                        } elseif (!empty($patientPhone)) {
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C_DEBUG] Phone not encrypted (decrypt_value function not available)');
                            }
                        }
                        
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C_DEBUG] Final phone to send SMS', [
                                'phone_prefix' => !empty($patientPhone) ? substr($patientPhone, 0, 4) . '****' : 'EMPTY',
                                'phone_length' => !empty($patientPhone) ? strlen($patientPhone) : 0
                            ]);
                        }
                        
                        // Get Red Cross blood bank address for pickup location
                        $redcrossInfo = getRow("SELECT address, branch_name FROM redcross_users WHERE id = ?", [$redcrossId]);
                        $pickupLocation = !empty($redcrossInfo['address']) ? $redcrossInfo['address'] : 'Philippine Red Cross - Bacolod Chapter, Bacolod City Main St.';
                        if (!empty($redcrossInfo['branch_name'])) {
                            $pickupLocation = $redcrossInfo['branch_name'] . ', ' . $pickupLocation;
                        }
                        
                        // Use professional notification template with pickup details
                        require_once '../../includes/notification_templates.php';
                        $approvalMessage = get_notification_message('approved', $patientName, 'redcross', [
                            'units_requested' => $updatedRequest['units_requested'],
                            'blood_type' => $updatedRequest['blood_type'],
                            'request_id' => $updatedRequest['id'],
                            'date' => $updatedRequest['required_date'] ?? date('Y-m-d'),
                            'time' => $updatedRequest['required_time'] ?? '09:00:00',
                            'location' => $pickupLocation
                        ]);
                        $approvalMessage = format_notification_message($approvalMessage);
                        
                        executeQuery("
                            INSERT INTO notifications (
                                title, message, user_id, user_role, is_read, created_at
                            ) VALUES (?, ?, ?, 'patient', 0, NOW())
                        ", [
                            "Blood Request Approved",
                            $approvalMessage,
                            $updatedRequest['patient_id']
                        ]);

                        // Send SMS via SIM800C if phone number exists (skip enabled check for automated sends)
                        $smsSent = false;
                        $smsError = null;
                        try {
                            require_once '../../includes/sim800c_sms.php';
                            
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C] Approval SMS attempt', [
                                    'patient_id' => $updatedRequest['patient_id'],
                                    'phone_status' => !empty($patientPhone) ? 'EXISTS' : 'EMPTY or NULL',
                                    'phone_length' => !empty($patientPhone) ? strlen($patientPhone) : 0,
                                    'phone_prefix' => !empty($patientPhone) ? substr($patientPhone, 0, 4) . '****' : 'EMPTY'
                                ]);
                            }
                            
                            // Check if phone number exists
                            if (!empty($patientPhone) && trim($patientPhone) !== '') {
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C] Phone number validated, proceeding to send SMS');
                                }
                                // Try to send SMS directly - skip enabled check for automated sends
                                // (enabled check may fail due to DB connection issues, but script still works)
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C] Attempting to send approval SMS');
                                }
                                $smsResult = send_sms_sim800c($patientPhone, $approvalMessage);
                                
                                if ($smsResult['success']) {
                                    $smsSent = true;
                                    if (function_exists('secure_log')) {
                                        secure_log('[SIM800C] Approval SMS sent successfully', ['phone_prefix' => substr($patientPhone, 0, 4) . '****']);
                                    }
                                } else {
                                    $smsError = $smsResult['error'] ?? 'Unknown error';
                                    if (function_exists('secure_log')) {
                                        secure_log('[SIM800C] Failed to send approval SMS', [
                                            'phone_prefix' => substr($patientPhone, 0, 4) . '****',
                                            'error' => substr($smsError, 0, 500)
                                        ]);
                                    }
                                }
                            } else {
                                $smsError = 'Patient phone number not found';
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C] Cannot send approval SMS - patient phone number not found', ['patient_id' => $updatedRequest['patient_id']]);
                                }
                            }
                        } catch (Exception $ex) {
                            $smsError = $ex->getMessage();
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C_ERR] Exception in approval SMS', [
                                    'error' => substr($ex->getMessage(), 0, 500),
                                    'trace' => substr($ex->getTraceAsString(), 0, 1000)
                                ]);
                            }
                        }
                    }
                    
                    // Store success message in session and redirect (POST-redirect-GET pattern)
                    $successMsg = 'Blood request has been approved successfully.';
                    if ($smsSent) {
                        $successMsg .= ' SMS notification sent to patient.';
                    } elseif ($smsError && !empty($patientPhone)) {
                        $successMsg .= ' (Note: SMS notification could not be sent: ' . $smsError . ')';
                    }
                    $_SESSION['blood_request_message'] = $successMsg;
                    $_SESSION['blood_request_message_type'] = 'success';
                    header('Location: blood-requests.php?success=1');
                    exit;
                } else {
                    // No rows affected means request was already processed
                    $_SESSION['blood_request_message'] = 'This request has already been processed and cannot be approved again.';
                    $_SESSION['blood_request_message_type'] = 'warning';
                    header('Location: blood-requests.php?success=1');
                    exit;
                }
            }
        }
    }
        else if (isset($_POST['fulfill_request'])) {
            $requestId = sanitize($_POST['request_id']);
            $bloodType = sanitize($_POST['blood_type'] ?? '');
            $unitsRequested = validate_units($_POST['units'] ?? 0);
            $organizationId = $_SESSION['user_id'];

            // Check current status to prevent duplicate processing
            $request = getRow("SELECT id, patient_id, status FROM blood_requests WHERE id = ? AND organization_type = 'redcross'", [$requestId]);
            if (!$request) {
                $_SESSION['blood_request_message'] = 'Invalid request.';
                $_SESSION['blood_request_message_type'] = 'danger';
                header('Location: blood-requests.php?success=1');
                exit;
            } elseif (strtolower(trim($request['status'] ?? '')) !== 'approved') {
                // Request is not approved or already processed
                $_SESSION['blood_request_message'] = 'This request must be approved first or has already been processed.';
                $_SESSION['blood_request_message_type'] = 'warning';
                header('Location: blood-requests.php?success=1');
                exit;
            } else {
                // Begin transactional fulfillment with FIFO (First In, First Out based on expiry date)
                beginTransaction();
                try {
                    // Fetch available inventory batches ordered by earliest expiry (NULLs last)
                    // This ensures we use the oldest blood first (FIFO) based on expiry date
                    $batches = executeQuery(
                        "SELECT id, units, expiry_date FROM blood_inventory
                         WHERE organization_type = 'redcross' AND organization_id = ?
                           AND blood_type = ? AND status = 'Available'
                           AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                         ORDER BY (expiry_date IS NULL) ASC, expiry_date ASC",
                        [ $organizationId, $bloodType ]
                    );

                    $remaining = $unitsRequested;
                    if (!is_array($batches) || count($batches) === 0) {
                        throw new Exception('No available inventory for selected blood type.');
                    }

                    // Update inventory by using the oldest units first (earliest expiry first)
                    foreach ($batches as $batch) {
                        if ($remaining <= 0) break;
                        $use = min((int)$batch['units'], $remaining);
                        $newUnits = (int)$batch['units'] - $use;
                        
                        if ($newUnits > 0) {
                            // If using partial units, update the count
                            updateRow("UPDATE blood_inventory SET units = ?, updated_at = NOW() WHERE id = ?", [ $newUnits, $batch['id'] ]);
                        } else {
                            // If using all units in this batch, mark as Used
                            updateRow("UPDATE blood_inventory SET units = 0, status = 'Used', updated_at = NOW() WHERE id = ?", [ $batch['id'] ]);
                        }
                        $remaining -= $use;
                    }

                    if ($remaining > 0) {
                        // Not enough units to fulfill
                        throw new Exception('Insufficient inventory to fulfill this request.');
                    }

                    // Update request as completed (with status check to prevent duplicates)
                    $conn = getConnection();
                    $stmt = $conn->prepare("
                        UPDATE blood_requests 
                        SET status = 'Completed', processed_date = NOW(), notes = ? 
                        WHERE id = ? AND organization_type = 'redcross' AND TRIM(LOWER(status)) = 'approved'
                    ");
                    $res = $stmt->execute([normalize_input($_POST['notes'] ?? ''), $requestId]);
                    $affectedRows = $stmt->rowCount();
                    
                    if ($res === false) {
                        throw new Exception('Failed to update request status.');
                    }
                    
                    // Check if update actually affected a row
                    if ($affectedRows == 0) {
                        throw new Exception('Request has already been processed. Please refresh the page.');
                    }

                    commitTransaction();

                    // Get patient information including phone number for personalized message and SMS
                    $patientInfo = getRow("SELECT name, phone FROM patient_users WHERE id = ?", [$request['patient_id']]);
                    $patientName = $patientInfo['name'] ?? '';
                    $patientPhone = $patientInfo['phone'] ?? '';
                    
                    // Decrypt phone number if encrypted
                    // Try to decrypt phone number if encrypted, but keep original if decryption fails (plain text)
                    if (!empty($patientPhone) && function_exists('decrypt_value')) {
                        $decryptedPhone = decrypt_value($patientPhone);
                        // If decryption returns null/empty, it means the phone is NOT encrypted (plain text)
                        // In that case, use the original value
                        if (!empty($decryptedPhone)) {
                            $patientPhone = $decryptedPhone;
                        }
                        // If decryption fails, $patientPhone already contains the original (plain text) value
                    }
                    
                    // Use professional notification template
                    require_once '../../includes/notification_templates.php';
                    $completionMessage = get_notification_message('completed', $patientName, 'redcross', [
                        'type' => 'request',
                        'blood_type' => $request['blood_type'] ?? '',
                        'units_requested' => $request['units_requested'] ?? '',
                        'date' => date('Y-m-d'),
                        'pickup_completed' => true
                    ]);
                    $completionMessage = format_notification_message($completionMessage);
                    
                    executeQuery("INSERT INTO notifications (title, message, user_id, user_role, is_read, created_at) VALUES (?, ?, ?, 'patient', 0, NOW())",
                        [ 'Blood Request Completed', $completionMessage, $request['patient_id'] ]);
                    
                    // Send SMS via SIM800C if phone number exists (skip enabled check for automated sends)
                    $smsSent = false;
                    $smsError = null;
                    try {
                        require_once '../../includes/sim800c_sms.php';
                        
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C] Completion SMS attempt', [
                                'patient_id' => $request['patient_id'],
                                'phone_prefix' => !empty($patientPhone) ? substr($patientPhone, 0, 4) . '****' : 'EMPTY'
                            ]);
                        }
                        
                        // Check if phone number exists
                        if (!empty($patientPhone)) {
                            // Try to send SMS directly - skip enabled check for automated sends
                            // (enabled check may fail due to DB connection issues, but script still works)
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C] Attempting to send completion SMS');
                            }
                            $smsResult = send_sms_sim800c($patientPhone, $completionMessage);
                            
                            if ($smsResult['success']) {
                                $smsSent = true;
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C] Completion SMS sent successfully', ['phone_prefix' => substr($patientPhone, 0, 4) . '****']);
                                }
                            } else {
                                $smsError = $smsResult['error'] ?? 'Unknown error';
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C] Failed to send completion SMS', [
                                        'phone_prefix' => substr($patientPhone, 0, 4) . '****',
                                        'error' => substr($smsError, 0, 500)
                                    ]);
                                }
                            }
                        } else {
                            $smsError = 'Patient phone number not found';
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C] Cannot send completion SMS - patient phone number not found', ['patient_id' => $request['patient_id']]);
                            }
                        }
                    } catch (Exception $ex) {
                        $smsError = $ex->getMessage();
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C_ERR] Exception in completion SMS', [
                                'error' => substr($ex->getMessage(), 0, 500),
                                'trace' => substr($ex->getTraceAsString(), 0, 1000)
                            ]);
                        }
                    }
                    
                    // Store success message in session and redirect (POST-redirect-GET pattern)
                    $successMsg = 'Blood request has been completed successfully.';
                    if ($smsSent) {
                        $successMsg .= ' SMS notification sent to patient.';
                    } elseif ($smsError && !empty($patientPhone)) {
                        $successMsg .= ' (Note: SMS notification could not be sent: ' . $smsError . ')';
                    }
                    $_SESSION['blood_request_message'] = $successMsg;
                    $_SESSION['blood_request_message_type'] = 'success';
                    header('Location: blood-requests.php?success=1');
                    exit;
                } catch (Exception $e) {
                    rollbackTransaction();
                    $_SESSION['blood_request_message'] = 'Failed to fulfill blood request: ' . $e->getMessage();
                    $_SESSION['blood_request_message_type'] = 'danger';
                    header('Location: blood-requests.php?success=1');
                    exit;
                }
            }
        }
        
        // Handle return request
        if (isset($_POST['return_request'])) {
            $requestId = sanitize($_POST['request_id']);
            
            // Check current status to prevent duplicate processing
            $request = getRow("SELECT id, patient_id, status FROM blood_requests WHERE id = ? AND organization_type = 'redcross'", [$requestId]);
            
            if (!$request) {
                $_SESSION['blood_request_message'] = 'Invalid request.';
                $_SESSION['blood_request_message_type'] = 'danger';
                header('Location: blood-requests.php?success=1');
                exit;
            } elseif (strtolower(trim($request['status'] ?? '')) !== 'approved') {
                // Request is not approved or already processed
                $_SESSION['blood_request_message'] = 'This request must be approved first or has already been processed.';
                $_SESSION['blood_request_message_type'] = 'warning';
                header('Location: blood-requests.php?success=1');
                exit;
            } else {
                // Update with status check to prevent duplicates
                $conn = getConnection();
                $stmt = $conn->prepare("
                    UPDATE blood_requests
                    SET status = 'Returned', processed_date = NOW(), notes = ?
                    WHERE id = ? AND organization_type = 'redcross'
                      AND TRIM(LOWER(status)) = 'approved'
                ");
                $updateResult = $stmt->execute([normalize_input($_POST['notes'] ?? ''), $requestId]);
                $affectedRows = $stmt->rowCount();
                
                if ($updateResult !== false && $affectedRows > 0) {
                    // Get patient information for notification
                    $patientInfo = getRow("SELECT name, phone FROM patient_users WHERE id = ?", [$request['patient_id']]);
                    $patientName = $patientInfo['name'] ?? '';
                    $patientPhone = $patientInfo['phone'] ?? '';
                    
                    // Decrypt phone number if encrypted
                    if (!empty($patientPhone) && function_exists('decrypt_value')) {
                        $decryptedPhone = decrypt_value($patientPhone);
                        if (!empty($decryptedPhone)) {
                            $patientPhone = $decryptedPhone;
                        }
                    }
                    
                    // Create notification
                    $returnMessage = "Your blood request #" . str_pad($requestId, 4, '0', STR_PAD_LEFT) . " has been marked as returned. Please contact us for more information.";
                    
                    executeQuery("
                        INSERT INTO notifications (
                            title, message, user_id, user_role, is_read, created_at
                        ) VALUES (?, ?, ?, 'patient', 0, NOW())
                    ", [
                        "Blood Request Returned",
                        $returnMessage,
                        $request['patient_id']
                    ]);
                    
                    // Send SMS if phone number exists
                    $smsSent = false;
                    $smsError = null;
                    if (!empty($patientPhone)) {
                        try {
                            require_once '../../includes/sim800c_sms.php';
                            $smsResult = send_sms_sim800c($patientPhone, $returnMessage);
                            if ($smsResult['success']) {
                                $smsSent = true;
                            } else {
                                $smsError = $smsResult['error'] ?? 'Unknown error';
                            }
                        } catch (Exception $ex) {
                            $smsError = $ex->getMessage();
                        }
                    }
                    
                    // Store success message in session and redirect
                    $successMsg = 'Blood request has been marked as returned.';
                    if ($smsSent) {
                        $successMsg .= ' SMS notification sent to patient.';
                    } elseif ($smsError && !empty($patientPhone)) {
                        $successMsg .= ' (Note: SMS notification could not be sent: ' . $smsError . ')';
                    }
                    $_SESSION['blood_request_message'] = $successMsg;
                    $_SESSION['blood_request_message_type'] = 'success';
                    header('Location: blood-requests.php?success=1');
                    exit;
                } else {
                    $_SESSION['blood_request_message'] = 'Failed to mark request as returned. The request may have already been processed.';
                    $_SESSION['blood_request_message_type'] = 'warning';
                    header('Location: blood-requests.php?success=1');
                    exit;
                }
            }
        }
        
        // Handle reject request if it exists
        if (isset($_POST['reject_request'])) {
            $requestId = sanitize($_POST['request_id']);
            $rejectionReason = sanitize($_POST['rejection_reason'] ?? '');
            
            // Check current status to prevent duplicate processing
            $request = getRow("SELECT id, patient_id, status FROM blood_requests WHERE id = ? AND organization_type = 'redcross'", [$requestId]);
            
            if (!$request) {
                $_SESSION['blood_request_message'] = 'Invalid request.';
                $_SESSION['blood_request_message_type'] = 'danger';
                header('Location: blood-requests.php?success=1');
                exit;
            } elseif (!empty($request['status']) && strtolower(trim($request['status'])) !== 'pending' && strtolower(trim($request['status'])) !== 'referred') {
                // Already processed
                $_SESSION['blood_request_message'] = 'This request has already been processed.';
                $_SESSION['blood_request_message_type'] = 'warning';
                header('Location: blood-requests.php?success=1');
                exit;
            } else {
                // Update with status check to prevent duplicates
                $conn = getConnection();
                $stmt = $conn->prepare("
                    UPDATE blood_requests
                    SET status = 'Rejected', rejection_reason = ?, rejected_by = ?, rejected_at = NOW()
                    WHERE id = ? AND organization_type = 'redcross'
                      AND (status IS NULL OR TRIM(LOWER(status)) = 'pending' OR TRIM(LOWER(status)) = 'referred')
                ");
                $updateResult = $stmt->execute([$rejectionReason, $_SESSION['user_id'], $requestId]);
                $affectedRows = $stmt->rowCount();
                
                if ($updateResult !== false && $affectedRows > 0) {
                    // Get patient information including phone number for personalized message and SMS
                    $patientInfo = getRow("SELECT name, phone, blood_type FROM patient_users WHERE id = ?", [$request['patient_id']]);
                    $patientName = $patientInfo['name'] ?? '';
                    $patientPhone = $patientInfo['phone'] ?? '';
                    
                    // Decrypt phone number if encrypted
                    // Try to decrypt phone number if encrypted, but keep original if decryption fails (plain text)
                    if (!empty($patientPhone) && function_exists('decrypt_value')) {
                        $decryptedPhone = decrypt_value($patientPhone);
                        // If decryption returns null/empty, it means the phone is NOT encrypted (plain text)
                        // In that case, use the original value
                        if (!empty($decryptedPhone)) {
                            $patientPhone = $decryptedPhone;
                        }
                        // If decryption fails, $patientPhone already contains the original (plain text) value
                    }
                    
                    // Use professional notification template
                    require_once '../../includes/notification_templates.php';
                    $rejectionMessage = get_notification_message('rejected', $patientName, 'redcross', [
                        'type' => 'request',
                        'reason' => $rejectionReason,
                        'blood_type' => $patientInfo['blood_type'] ?? '',
                        'units_requested' => $request['units_requested'] ?? ''
                    ]);
                    $rejectionMessage = format_notification_message($rejectionMessage);
                    
                    executeQuery("
                        INSERT INTO notifications (title, message, user_id, user_role, is_read, created_at)
                        VALUES (?, ?, ?, 'patient', 0, NOW())
                    ", [
                        "Blood Request Rejected",
                        $rejectionMessage,
                        $request['patient_id']
                    ]);
                    
                    // Send SMS via SIM800C if phone number exists (skip enabled check for automated sends)
                    $smsSent = false;
                    $smsError = null;
                    try {
                        require_once '../../includes/sim800c_sms.php';
                        
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C] Rejection SMS attempt', [
                                'patient_id' => $request['patient_id'],
                                'phone_prefix' => !empty($patientPhone) ? substr($patientPhone, 0, 4) . '****' : 'EMPTY'
                            ]);
                        }
                        
                        // Check if phone number exists
                        if (!empty($patientPhone)) {
                            // Try to send SMS directly - skip enabled check for automated sends
                            // (enabled check may fail due to DB connection issues, but script still works)
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C] Attempting to send rejection SMS');
                            }
                            $smsResult = send_sms_sim800c($patientPhone, $rejectionMessage);
                            
                            if ($smsResult['success']) {
                                $smsSent = true;
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C] Rejection SMS sent successfully', ['phone_prefix' => substr($patientPhone, 0, 4) . '****']);
                                }
                            } else {
                                $smsError = $smsResult['error'] ?? 'Unknown error';
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C] Failed to send rejection SMS', [
                                        'phone_prefix' => substr($patientPhone, 0, 4) . '****',
                                        'error' => substr($smsError, 0, 500)
                                    ]);
                                }
                            }
                        } else {
                            $smsError = 'Patient phone number not found';
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C] Cannot send rejection SMS - patient phone number not found', ['patient_id' => $request['patient_id']]);
                            }
                        }
                    } catch (Exception $ex) {
                        $smsError = $ex->getMessage();
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C_ERR] Exception in rejection SMS', [
                                'error' => substr($ex->getMessage(), 0, 500),
                                'trace' => substr($ex->getTraceAsString(), 0, 1000)
                            ]);
                        }
                    }
                    
                    // Store success message in session and redirect (POST-redirect-GET pattern)
                    $successMsg = 'Blood request has been rejected.';
                    if ($smsSent) {
                        $successMsg .= ' SMS notification sent to patient.';
                    } elseif ($smsError && !empty($patientPhone)) {
                        $successMsg .= ' (Note: SMS notification could not be sent: ' . $smsError . ')';
                    }
                    $_SESSION['blood_request_message'] = $successMsg;
                    $_SESSION['blood_request_message_type'] = 'success';
                    header('Location: blood-requests.php?success=1');
                    exit;
                } else {
                    $_SESSION['blood_request_message'] = 'This request has already been processed.';
                    $_SESSION['blood_request_message_type'] = 'warning';
                    header('Location: blood-requests.php?success=1');
                    exit;
                }
            }
        }
    }
}

// Get blood requests
// SECURITY: Validate and whitelist status parameter to prevent SQL injection
$statusInput = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
// Whitelist of allowed status values - only allow specific values
$allowedStatuses = ['all', 'Pending', 'Approved', 'Completed', 'Returned', 'pending', 'approved', 'completed', 'returned'];
// Normalize to lowercase for comparison
$statusLower = strtolower(trim($statusInput));
// If not in whitelist, default to 'all'
if (!in_array($statusInput, $allowedStatuses, true) && !in_array($statusLower, ['all', 'pending', 'approved', 'completed', 'returned'], true)) {
    $statusInput = 'all';
    $statusLower = 'all';
}
// Normalize status for SQL (use capitalized version for consistency)
if ($statusLower === 'pending') {
    $status = 'Pending';
} elseif ($statusLower === 'approved') {
    $status = 'Approved';
} elseif ($statusLower === 'completed') {
    $status = 'Completed';
} elseif ($statusLower === 'returned') {
    $status = 'Returned';
} else {
    $status = 'all';
}

// SECURITY: Build query with parameterized placeholders - no string interpolation
// This prevents any possibility of SQL injection even if validation is bypassed
$queryParams = [];
$whereClause = "WHERE br.organization_type = 'redcross'";

if ($status !== 'all') {
    // Status is validated against whitelist, then used as parameter
    // SECURITY: This is a static query string - no user input is ever interpolated into it
    $whereClause .= " AND br.status = ?";
    $queryParams[] = $status;
}

// Static query string - no user input interpolation
$query = "
    SELECT br.*, pu.name as patient_name, pu.phone, pu.blood_type as patient_blood_type,
           b.name as barangay_name, br.has_blood_card, br.request_form_path, br.blood_card_path,
           r.referral_document_name, r.referral_document_type, r.referral_date, r.id as referral_id,
           TIME(br.created_at) as request_time, br.required_time,
           CASE 
               WHEN br.has_blood_card = 1 THEN 'Direct Request'
               WHEN r.id IS NULL AND br.barangay_id IS NOT NULL THEN 'Pending Barangay Referral'
               WHEN r.id IS NOT NULL THEN CONCAT('Referred by ', b.name)
               ELSE 'Direct Request'
           END as referral_status,
           COALESCE(br.status, 'Pending') as status
    FROM blood_requests br
    JOIN patient_users pu ON br.patient_id = pu.id
    LEFT JOIN barangay_users b ON br.barangay_id = b.id
    LEFT JOIN referrals r ON br.id = r.blood_request_id
    {$whereClause}
    ORDER BY
        br.request_date DESC,
        TIME(br.created_at) DESC
";

// SECURITY: Use secure_log to prevent log injection
if (function_exists('secure_log')) {
    secure_log("[BLOOD_REQUESTS] Executing query", [
        'has_status_filter' => $status !== 'all',
        'status' => substr($status, 0, 50),
        'has_query_params' => !empty($queryParams)
    ]);
}

$bloodRequests = executeQuery($query, $queryParams);

// Add debugging - use secure_log to prevent log injection
if ($bloodRequests === false) {
    if (function_exists('secure_log')) {
        secure_log("Database query failed. Check the error logs for details.");
    }
    $bloodRequests = [];
} else if (empty($bloodRequests)) {
    if (function_exists('secure_log')) {
        secure_log("Query returned no results", [
            'has_status_filter' => $status !== 'all',
            'status' => substr($status, 0, 50)
        ]);
    }
    
    // Check if the tables exist and have data
    $checkTables = executeQuery("
        SELECT 
            (SELECT COUNT(*) FROM blood_requests) as blood_requests_count,
            (SELECT COUNT(*) FROM patient_users) as patient_users_count,
            (SELECT COUNT(*) FROM blood_requests WHERE organization_type = 'redcross') as redcross_requests_count
    ");
    
    if ($checkTables) {
        if (function_exists('secure_log')) {
            secure_log("Table counts check", [
                'blood_requests_count' => $checkTables[0]['blood_requests_count'] ?? 0,
                'patient_users_count' => $checkTables[0]['patient_users_count'] ?? 0,
                'redcross_requests_count' => $checkTables[0]['redcross_requests_count'] ?? 0
            ]);
        }
    }
} else {
    // Debug the first request's status - use secure_log
    if (function_exists('secure_log') && isset($bloodRequests[0]['status'])) {
        secure_log("First request status", [
            'status' => substr($bloodRequests[0]['status'], 0, 50)
        ]);
    }
}

// Initialize $bloodRequests as an empty array if the query fails
if ($bloodRequests === false) {
    $bloodRequests = [];
}

// Function to fetch counts safely
function getCountValue($query) {
    $result = executeQuery($query);
    return isset($result[0]) ? (int)array_values($result[0])[0] : 0;
}

// REDCROSS organization counts - must match the table query structure (with JOIN to patient_users)
$pendingCountRedcross = getCountValue("SELECT COUNT(*) FROM blood_requests br JOIN patient_users pu ON br.patient_id = pu.id WHERE br.organization_type = 'redcross' AND br.status = 'Pending'");
$approvedCountRedcross = getCountValue("SELECT COUNT(*) FROM blood_requests br JOIN patient_users pu ON br.patient_id = pu.id WHERE br.organization_type = 'redcross' AND br.status = 'Approved'");
$fulfilledCountRedcross = getCountValue("SELECT COUNT(*) FROM blood_requests br JOIN patient_users pu ON br.patient_id = pu.id WHERE br.organization_type = 'redcross' AND br.status = 'Completed'");
$returnedCountRedcross = getCountValue("SELECT COUNT(*) FROM blood_requests br JOIN patient_users pu ON br.patient_id = pu.id WHERE br.organization_type = 'redcross' AND br.status = 'Returned'");
$totalCountRedcross = $pendingCountRedcross + $approvedCountRedcross + $fulfilledCountRedcross + $returnedCountRedcross;

// NEGROSFIRST organization counts
$pendingCountNegros = getCountValue("SELECT COUNT(*) FROM blood_requests WHERE organization_type = 'negrosfirst' AND status = 'Pending'");
$approvedCountNegros = getCountValue("SELECT COUNT(*) FROM blood_requests WHERE organization_type = 'negrosfirst' AND status = 'Approved'");
$fulfilledCountNegros = getCountValue("SELECT COUNT(*) FROM blood_requests WHERE organization_type = 'negrosfirst' AND status = 'Completed'");
$rejectedCountNegros = getCountValue("SELECT COUNT(*) FROM blood_requests WHERE organization_type = 'negrosfirst' AND status = 'Rejected'");
$totalCountNegros = $pendingCountNegros + $approvedCountNegros + $fulfilledCountNegros + $rejectedCountNegros;

$totalCount = $totalCountRedcross;
$pendingCount = $pendingCountRedcross;
$approvedCount = $approvedCountRedcross;
$fulfilledCount = $fulfilledCountRedcross;
$returnedCount = $returnedCountRedcross;


?>

<?php include_once 'header.php'; ?>

<style>
/* Blood Requests Page Specific Styles */
.requests-header-section {
    margin-bottom: 3rem;
}

.requests-hero {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
    border-radius: 20px;
    padding: 3rem 2.5rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px -12px rgba(220, 20, 60, 0.3);
    margin-bottom: 2rem;
}

.requests-hero::before {
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

.requests-hero h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    position: relative;
    z-index: 2;
}

.requests-hero p {
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

/* Enhanced Statistics Cards (larger) */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    border-radius: 18px;
    padding: 2.5rem;
    box-shadow: 0 6px 30px rgba(0, 0, 0, 0.09);
    border: 1px solid rgba(220, 20, 60, 0.08);
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
    width: 70px;
    height: 70px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.75rem;
    font-size: 1.8rem;
    color: white;
}

.stat-icon-wrapper.primary { background: linear-gradient(135deg, #007bff, #0056b3); }
.stat-icon-wrapper.success { background: linear-gradient(135deg, #28a745, #1e7e34); }
.stat-icon-wrapper.info { background: linear-gradient(135deg, #17a2b8, #138496); }
.stat-icon-wrapper.danger { background: linear-gradient(135deg, #dc3545, #c82333); }
.stat-icon-wrapper.warning { background: linear-gradient(135deg, #ffc107, #e0a800); }

.stat-number {
    font-size: 3rem;
    font-weight: 900;
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
.requests-table-card {
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
.status-returned { background: #fff3cd; color: #856404; }

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
.btn-fulfill { background: #e1f5fe; color: #0277bd; }

@media (max-width: 768px) {
    .requests-hero h1 { font-size: 2rem; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .hero-actions { justify-content: center; }
    .table-actions { flex-direction: column; }
}
</style>

<script>
  // Inject CSRF token into all POST forms on the page
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

  document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        // Hide alerts but exclude those inside modals
        document.querySelectorAll('.alert, .feedback-message, .notification, .status-message').forEach(function(el) {
            // Don't hide alerts that are inside modals
            if (!el.closest('.modal')) {
            el.style.display = 'none';
            }
        });
    }, 5000);
});
</script>

<!-- Display messages -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $alertType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Hero Section -->
<div class="requests-header-section">
    <div class="requests-hero">
        <h1><i class="bi bi-clipboard2-pulse me-3"></i>Blood Request Management</h1>
        <p>Process and manage blood requests efficiently. Review patient requests, approve or reject applications, and fulfill approved requests with available blood inventory.</p>
        <div class="hero-actions">
            <a href="blood-request-history.php" class="hero-btn">
                <i class="bi bi-clock-history me-2"></i>View History
            </a>
            <button class="hero-btn" onclick="printRequestsReport()">
                <i class="bi bi-printer me-2"></i>Print Report
            </button>
            
        </div>
    </div>
</div>

<!-- Enhanced Statistics -->
<div class="stats-grid">
    <div class="stat-card clickable-stat-card" data-status="Pending" style="cursor: pointer;">
        <div class="stat-icon-wrapper primary">
            <i class="bi bi-clock-history"></i>
        </div>
        <div class="stat-number"><?php echo $pendingCount; ?></div>
        <div class="stat-label">Pending Requests</div>
    </div>
    <div class="stat-card clickable-stat-card" data-status="Approved" style="cursor: pointer;">
        <div class="stat-icon-wrapper success">
            <i class="bi bi-check-circle"></i>
        </div>
        <div class="stat-number"><?php echo $approvedCount; ?></div>
        <div class="stat-label">Approved Requests</div>
    </div>
    <div class="stat-card clickable-stat-card" data-status="Completed" style="cursor: pointer;">
        <div class="stat-icon-wrapper info">
            <i class="bi bi-check-circle"></i>
        </div>
        <div class="stat-number"><?php echo $fulfilledCount; ?></div>
        <div class="stat-label">Completed Requests</div>
    </div>
    <div class="stat-card clickable-stat-card" data-status="Returned" style="cursor: pointer;">
        <div class="stat-icon-wrapper warning">
            <i class="bi bi-arrow-return-left"></i>
        </div>
        <div class="stat-number"><?php echo $returnedCount; ?></div>
        <div class="stat-label">Returned Blood</div>
    </div>
</div>

<!-- Enhanced Requests Table -->
<div class="requests-table-card">
    <div class="table-header">
        <h3><i class="bi bi-clipboard2-pulse me-2"></i>Blood Request Records</h3>
        <div class="table-actions">
            <button class="table-btn" onclick="printRequestsReport()">
                <i class="bi bi-printer me-2"></i>Print Report
            </button>
           
        </div>
    </div>
    <div class="table-responsive">
        <table class="enhanced-table table">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Patient</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="requestsTableBody">
                <?php if (count($bloodRequests) > 0): ?>
                    <?php foreach ($bloodRequests as $request): ?>
                        <tr class="request-row" data-status="<?php echo htmlspecialchars(strtolower($request['status'] ?? 'pending')); ?>">
                            <td>
                                <span class="fw-bold text-primary">#<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </td>
                            <td>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($request['patient_name']); ?></div>
                                    <?php $__ph = isset($request['phone']) ? decrypt_value($request['phone']) : null; ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($__ph !== null ? $__ph : ($request['phone'] ?? 'N/A')); ?></small>
                                </div>
                            </td>
                            <!-- Blood type and units removed from row; details are in modal -->
                            <td>
                                <?php
                                $status = $request['status'] ?? 'Pending';
                                $statusClass = '';
                                switch (strtolower($status)) {
                                    case 'pending':
                                        $statusClass = 'status-pending';
                                        break;
                                    case 'approved':
                                        $statusClass = 'status-approved';
                                        break;
                                    case 'completed':
                                        $statusClass = 'status-completed';
                                        break;
                                    case 'returned':
                                        $statusClass = 'status-returned';
                                        break;
                                    default:
                                        $statusClass = 'status-pending';
                                }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>" data-status-filter="<?php echo htmlspecialchars(strtolower($status)); ?>"><?php echo ucfirst($status); ?></span>
                            </td>
                            <td>
                                <div>
                                    <div><?php echo date('M d, Y', strtotime($request['request_date'])); ?></div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <?php 
                                    $requestTime = $request['request_time'] ?? null;
                                    if ($requestTime) {
                                        // Format time from database (HH:MM:SS or HH:MM format)
                                        echo date('h:i A', strtotime($requestTime));
                                    } else {
                                        // Fallback to created_at if request_time not available
                                        $createdAt = $request['created_at'] ?? null;
                                        if ($createdAt) {
                                            echo date('h:i A', strtotime($createdAt));
                                        } else {
                                            echo 'N/A';
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="action-btn btn-view"
                                        data-request-id="<?php echo (int)$request['id']; ?>"
                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                        data-phone="<?php echo htmlspecialchars($request['phone'] ?? ''); ?>"
                                        data-blood-type="<?php echo htmlspecialchars($request['blood_type']); ?>"
                                        data-units="<?php echo (int)$request['units_requested']; ?>"
                                        data-required-date="<?php echo htmlspecialchars($request['required_date'] ?? ($request['request_date'] ?? '')); ?>"
                                        data-required-time="<?php echo htmlspecialchars($request['required_time'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($request['status'] ?? 'Pending'); ?>"
                                        data-hospital="<?php echo htmlspecialchars($request['hospital'] ?? ''); ?>"
                                        data-doctor="<?php echo htmlspecialchars($request['doctor_name'] ?? ''); ?>"
                                        data-reason="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>"
                                        data-request-form="<?php echo htmlspecialchars($request['request_form_path'] ?? ''); ?>"
                                        data-blood-card="<?php echo htmlspecialchars($request['blood_card_path'] ?? ''); ?>"
                                        data-referral-id="<?php echo htmlspecialchars($request['referral_id'] ?? ''); ?>"
                                        data-referral-document-name="<?php echo htmlspecialchars($request['referral_document_name'] ?? ''); ?>"
                                        data-referral-date="<?php echo htmlspecialchars($request['referral_date'] ?? ''); ?>"
                                        data-barangay-name="<?php echo htmlspecialchars($request['barangay_name'] ?? ''); ?>"
                                        onclick="viewDetails(this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <!-- Actions moved to detail modal; keep only view button -->
                                    <!-- ...existing code... -->
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-clipboard2-x text-muted" style="font-size: 3rem;"></i>
                            <p class="mb-0 mt-3 text-muted">No blood requests found.</p>
                            <small class="text-muted">Requests will appear here when patients submit blood requests.</small>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    /* Enhanced Blood Requests Page Styling */
    .dashboard-container {
        display: flex;
        width: 100%;
        position: relative;
    }

    .dashboard-content {
        flex: 1;
        min-width: 0;
        height: 100vh;
        overflow-y: auto;
        padding: 2rem;
        margin-left: 300px; /* Sidebar width */
        transition: margin-left 0.3s ease;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    /* Responsive sidebar */
    @media (max-width: 991.98px) {
        .dashboard-content {
            margin-left: 0;
        }
    }

    .requests-header-section {
        margin-bottom: 2rem;
    }

    .requests-hero {
        background: linear-gradient(135deg, #DC143C 0%, #B22222 50%, #8B0000 100%);
        color: white;
        padding: 3rem 2rem;
        border-radius: 20px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(220, 20, 60, 0.3);
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .requests-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        opacity: 0.3;
    }

    .requests-hero h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        position: relative;
        z-index: 2;
    }

    .requests-hero p {
        font-size: 1.1rem;
        margin-bottom: 2rem;
        opacity: 0.95;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        position: relative;
        z-index: 2;
    }

    .hero-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .hero-btn {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.3);
        padding: 12px 24px;
        border-radius: 50px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        cursor: pointer;
    }

    .hero-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }

    /* Enhanced Statistics Grid (larger) */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        padding: 2.5rem;
        border-radius: 18px;
        box-shadow: 0 6px 30px rgba(0, 0, 0, 0.09);
        text-align: center;
        transition: all 0.3s ease;
        border: 1px solid rgba(220, 20, 60, 0.08);
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
        background: linear-gradient(90deg, #DC143C, #FF6B6B);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(220, 20, 60, 0.15);
    }

    .stat-icon-wrapper {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.25rem;
        font-size: 1.8rem;
    }

    .stat-icon-wrapper.primary {
        background: linear-gradient(135deg, #DC143C, #FF6B6B);
        color: white;
    }

    .stat-icon-wrapper.success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .stat-icon-wrapper.info {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: white;
    }

    .stat-icon-wrapper.danger {
        background: linear-gradient(135deg, #dc3545, #fd7e14);
        color: white;
    }

    .stat-number {
        font-size: 3rem;
        font-weight: 800;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: #6c757d;
        font-weight: 500;
        font-size: 0.95rem;
    }

    /* Enhanced Table Styling */
    .requests-table-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .table-header {
        background: linear-gradient(135deg, #DC143C 0%, #B22222 100%);
        color: white;
        padding: 1.5rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .table-header h3 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 600;
    }

    .table-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .table-btn {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        padding: 8px 16px;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .table-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-1px);
        color: white;
    }

    .enhanced-table {
        margin: 0;
        border: none;
    }

    .enhanced-table thead th {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border: none;
        padding: 1rem;
        font-weight: 600;
        color: #495057;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    .enhanced-table tbody tr {
        border-bottom: 1px solid #f1f3f4;
        transition: all 0.2s ease;
    }

    .enhanced-table tbody tr:hover {
        background-color: #f8f9fa;
        transform: scale(1.01);
    }

    .enhanced-table tbody td {
        padding: 1rem;
        vertical-align: middle;
        border: none;
    }

    .blood-type-badge {
        background: linear-gradient(135deg, #DC143C, #FF6B6B);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-pending {
        background: linear-gradient(135deg, #ffc107, #ffeb3b);
        color: #856404;
    }

    .status-approved {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .status-completed {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: white;
    }

    .status-returned {
        background: linear-gradient(135deg, #ffc107, #ff9800);
        color: #856404;
    }

    .clickable-stat-card {
        transition: all 0.3s ease;
    }

    .clickable-stat-card:hover {
        transform: translateY(-8px) !important;
        box-shadow: 0 12px 40px rgba(220, 20, 60, 0.25) !important;
    }

    .clickable-stat-card.active {
        border: 2px solid var(--primary-color);
        box-shadow: 0 8px 30px rgba(220, 20, 60, 0.2);
    }

    .action-btn {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .btn-view {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
    }

    .btn-approve {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .btn-reject {
        background: linear-gradient(135deg, #dc3545, #fd7e14);
        color: white;
    }

    .btn-fulfill {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: white;
    }

    .action-btn:hover {
        transform: translateY(-2px) scale(1.1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* Alert Styling */
    .alert {
        border-radius: 12px;
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .alert-danger {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
        border-left: 4px solid #28a745;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
        border-left: 4px solid #dc3545;
    }

    /* Modal styling improvements */
    .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .modal-header {
        background: linear-gradient(135deg, #DC143C 0%, #B22222 100%);
        color: white;
        border-radius: 15px 15px 0 0;
        border-bottom: none;
    }
    .modal-header .btn-close {
        filter: invert(1);
    }
    .modal-body {
        padding: 2rem;
    }
    .modal-footer {
        border-top: 1px solid #dee2e6;
        padding: 1rem 2rem;
    }

    /* Modal behavior fixes */
    .modal {
        transition: all 0.3s ease-in-out;
        pointer-events: auto !important;
        z-index: 1055 !important;
    }
    .modal-backdrop {
        transition: all 0.3s ease-in-out;
        pointer-events: auto !important;
        z-index: 1050 !important;
    }
    .modal.show {
        display: block !important;
        pointer-events: auto !important;
        z-index: 1055 !important;
    }
    .modal-backdrop.show {
        opacity: 0.5;
        pointer-events: auto !important;
        z-index: 1050 !important;
    }
    body.modal-open {
        overflow: hidden;
        padding-right: 0 !important;
    }
    .modal-dialog {
        pointer-events: auto !important;
        z-index: 1055 !important;
    }
    
    /* Ensure nested modals have higher z-index */
    .modal:nth-of-type(2) {
        z-index: 1060 !important;
    }
    .modal:nth-of-type(2) .modal-backdrop {
        z-index: 1055 !important;
    }
    .modal:nth-of-type(3) {
        z-index: 1065 !important;
    }
    .modal:nth-of-type(3) .modal-backdrop {
        z-index: 1060 !important;
    }

        /* Approve modal overlay helper: force it to be topmost and not tied to underlying backdrop */
        .overlay-approve-modal {
            position: fixed !important;
            top: 10% !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            z-index: 9999 !important;
            box-shadow: 0 30px 60px rgba(0,0,0,0.4) !important;
        }

    /* Responsive Design */
    @media (max-width: 768px) {
        .dashboard-content {
            padding: 1rem;
        }

        .requests-hero {
            padding: 2rem 1rem;
        }

        .requests-hero h1 {
            font-size: 2rem;
        }

        .hero-actions {
            flex-direction: column;
            align-items: center;
        }

        .hero-btn {
            width: 100%;
            max-width: 250px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .table-header {
            flex-direction: column;
            text-align: center;
        }

        .enhanced-table {
            font-size: 0.9rem;
        }

        .enhanced-table thead th,
        .enhanced-table tbody td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>



<!-- Bootstrap JS (required for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function getFilePreviewHTML(url) {
    if (!url) return '<span class="text-muted">Not provided</span>';
    try {
        const cleaned = String(url).split('?')[0].split('#')[0];
        const ext = (cleaned.split('.').pop() || '').toLowerCase();
        const imgExts = ['png','jpg','jpeg','gif','webp','svg'];
        if (ext === 'pdf') {
            return `<iframe src="${url}" style="width:100%;height:420px;border:1px solid #e9ecef;border-radius:8px;" frameborder="0"></iframe>`;
        } else if (imgExts.indexOf(ext) !== -1) {
            return `<div style=\"text-align:center;\"><img src=\"${url}\" alt=\"document preview\" style=\"max-width:100%;height:auto;border:1px solid #e9ecef;border-radius:8px;\"></div>`;
        } else {
            return `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Open Document</a>`;
        }
    } catch (e) {
        return `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Open Document</a>`;
    }
}

// Render a lightweight preview (thumbnail / file row) and provide a button to open a lightbox
function renderDocumentPreview(url) {
    if (!url) return '<span class="text-muted">Not provided</span>';
    try {
        const cleaned = String(url).split('?')[0].split('#')[0];
        const parts = cleaned.split('/');
        const filename = parts[parts.length - 1] || cleaned;
        const ext = (cleaned.split('.').pop() || '').toLowerCase();
        const imgExts = ['png','jpg','jpeg','gif','webp','svg'];

        // If it's a PDF or dynamic viewer (like view-referral.php) show a file row with View button
        if (ext === 'pdf' || cleaned.indexOf('view-referral.php') !== -1) {
            return `
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-file-earmark-pdf" style="font-size:28px;color:#d9534f;"></i>
                    <div>
                        <div class="fw-semibold">${filename}</div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-primary" onclick="openDocumentLightbox('${url.replace(/'/g, "\\'")}')"><i class="bi bi-eye me-1"></i>View</button>
                           
                            <a href="${url}" download class="btn btn-sm btn-outline-success ms-1">Download</a>
                        </div>
                    </div>
                </div>
            `;
        } else if (imgExts.indexOf(ext) !== -1) {
            // Image thumbnail clickable to open lightbox
            return `
                <div>
                    <div style="max-width:200px;cursor:pointer;" onclick="openDocumentLightbox('${url.replace(/'/g, "\\'")}')">
                        <img src="${url}" alt="${filename}" style="max-width:100%;height:auto;border:1px solid #e9ecef;border-radius:8px;">
                    </div>
                    <div class="mt-2">
                   
                        <a href="${url}" download class="btn btn-sm btn-outline-success ms-1">Download</a>
                    </div>
                </div>
            `;
        } else {
            return `
                <div>
                    <div class="fw-semibold">${filename}</div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-primary" onclick="openDocumentLightbox('${url.replace(/'/g, "\\'")}')"><i class="bi bi-eye me-1"></i>View</button>
                       
                        <a href="${url}" download class="btn btn-sm btn-outline-success ms-1">Download</a>
                    </div>
                </div>
            `;
        }
    } catch (e) {
        return `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Open Document</a>`;
    }
}

// Open a large modal lightbox showing the document (PDF/image/other) in an iframe/img
function openDocumentLightbox(url) {
    if (!url) return;
    let lb = document.getElementById('documentLightboxModal');
    if (!lb) {
        const lbHTML = `
        <div class="modal fade" id="documentLightboxModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark me-2"></i>Document Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body" id="documentLightboxBody" style="min-height:60vh;padding:0;display:flex;align-items:center;justify-content:center;">
              </div>
              <div class="modal-footer" id="documentLightboxFooter">
               
                <a id="documentLightboxDownloadLink" href="#" download class="btn btn-outline-success ms-2">Download</a>
                <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', lbHTML);
        lb = document.getElementById('documentLightboxModal');
    }

    const body = document.getElementById('documentLightboxBody');
    const openLink = document.getElementById('documentLightboxOpenLink');
    const dlLink = document.getElementById('documentLightboxDownloadLink');
    // Clear previous content
    body.innerHTML = '';

    // Decide embed type by extension
    const cleaned = String(url).split('?')[0].split('#')[0];
    const ext = (cleaned.split('.').pop() || '').toLowerCase();
    const imgExts = ['png','jpg','jpeg','gif','webp','svg'];

    if (ext === 'pdf' || cleaned.indexOf('view-referral.php') !== -1) {
        const iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.style.width = '100%';
        iframe.style.height = '70vh';
        iframe.style.border = '0';
        body.appendChild(iframe);
    } else if (imgExts.indexOf(ext) !== -1) {
        const img = document.createElement('img');
        img.src = url;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '70vh';
        img.style.objectFit = 'contain';
        body.appendChild(img);
    } else {
        // Fallback to iframe for unknown types
        const iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.style.width = '100%';
        iframe.style.height = '70vh';
        iframe.style.border = '0';
        body.appendChild(iframe);
    }

    openLink.href = url;
    dlLink.href = url;

    try {
        const modal = new bootstrap.Modal(lb);
        lb.addEventListener('hidden.bs.modal', function onHidden(){
            // cleanup
            try { document.getElementById('documentLightboxBody').innerHTML = ''; } catch(e){}
            lb.removeEventListener('hidden.bs.modal', onHidden);
        });
        modal.show();
    } catch (e) {
        window.open(url, '_blank');
    }
}

function approveRequest(requestId) {
    // Close the details modal first
    const detailsModal = document.getElementById('viewDetailsModal');
    if (detailsModal) {
        const bsModal = bootstrap.Modal.getInstance(detailsModal);
        if (bsModal) {
            bsModal.hide();
        }
    }
    
    // Wait a bit for the modal to close, then show confirmation
    setTimeout(function() {
    // set the hidden field
    const reqInput = document.getElementById('approve_request_id');
    if (reqInput) reqInput.value = requestId;

    const approveModalEl = document.getElementById('approveModal');
    if (!approveModalEl) return;

    // Make this modal an overlay so it appears on top of everything
    if (!document.body.classList.contains('overlay-approve-active')) {
        document.body.classList.add('overlay-approve-active');
        approveModalEl.classList.add('overlay-approve-modal');

        // create a persistent backdrop element that stays until approve modal closes
        const persistent = document.createElement('div');
        persistent.className = 'modal-backdrop overlay-approve-backdrop';
        persistent.style.position = 'fixed';
        persistent.style.top = '0';
        persistent.style.left = '0';
        persistent.style.width = '100%';
        persistent.style.height = '100%';
        persistent.style.backgroundColor = 'rgba(0,0,0,0.5)';
        persistent.style.zIndex = '9998';
        document.body.appendChild(persistent);
    }

    // Show modal without Bootstrap backdrop
    const modal = new bootstrap.Modal(approveModalEl, { backdrop: false });

    // Ensure this modal stays on top: append to body and enforce z-index until closed
    try {
        document.body.appendChild(approveModalEl.closest('.modal'));
    } catch (e) {}

    const enforceKey = 'overlayKeepInterval';
    // clear any existing enforcement
    if (approveModalEl[enforceKey]) {
        clearInterval(approveModalEl[enforceKey]);
    }
    approveModalEl[enforceKey] = setInterval(function() {
        try {
            approveModalEl.style.zIndex = '99999';
            // also ensure backdrop stays below it
            const pb = document.querySelector('.overlay-approve-backdrop');
            if (pb) pb.style.zIndex = '99998';
            // move modal node to end of body to keep stacking
            const node = approveModalEl.closest('.modal');
            if (node && node.parentNode !== document.body) document.body.appendChild(node);
        } catch (e) {}
    }, 150);

    const cleanup = function() {
        try { modal.dispose(); } catch (e) {}
        document.body.classList.remove('overlay-approve-active');
        approveModalEl.classList.remove('overlay-approve-modal');
        // remove only the persistent approve backdrop
        const pb = document.querySelector('.overlay-approve-backdrop');
        if (pb && pb.parentNode) pb.parentNode.removeChild(pb);
        // clear enforcement
        if (approveModalEl[enforceKey]) { clearInterval(approveModalEl[enforceKey]); delete approveModalEl[enforceKey]; }
    };

    approveModalEl.addEventListener('hidden.bs.modal', function handler() {
        cleanup();
        approveModalEl.removeEventListener('hidden.bs.modal', handler);
    });

    modal.show();
    }, 300);
}



// (Removed simple alert version of viewDetails to use the advanced modal-based version below)

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', function() {
    // Delegate click for all current/future view buttons
    document.addEventListener('click', function(ev) {
        const btn = ev.target.closest('.btn-view');
        if (btn) {
            ev.preventDefault();
            try { viewDetails(btn); } catch (e) { console.error('viewDetails error', e); }
        }
    });
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
});

function refreshRequests() {
    location.reload();
}

function approveRequest(requestId) {
    // Close the details modal first
    const detailsModal = document.getElementById('viewDetailsModal');
    if (detailsModal) {
        const bsModal = bootstrap.Modal.getInstance(detailsModal);
        if (bsModal) {
            bsModal.hide();
        }
    }
    
    // Wait a bit for the modal to close, then show confirmation
    setTimeout(function() {
    // set the hidden field
    const reqInput = document.getElementById('approve_request_id');
    if (reqInput) reqInput.value = requestId;

    const approveModalEl = document.getElementById('approveModal');
    if (!approveModalEl) return;

    // Make this modal an overlay so it appears on top of everything
    if (!document.body.classList.contains('overlay-approve-active')) {
        document.body.classList.add('overlay-approve-active');
        approveModalEl.classList.add('overlay-approve-modal');

        // create a persistent backdrop element that stays until approve modal closes
        const persistent = document.createElement('div');
        persistent.className = 'modal-backdrop overlay-approve-backdrop';
        persistent.style.position = 'fixed';
        persistent.style.top = '0';
        persistent.style.left = '0';
        persistent.style.width = '100%';
        persistent.style.height = '100%';
        persistent.style.backgroundColor = 'rgba(0,0,0,0.5)';
        persistent.style.zIndex = '9998';
        document.body.appendChild(persistent);
    }

    // Show modal without Bootstrap backdrop
    const modal = new bootstrap.Modal(approveModalEl, { backdrop: false });

    // Ensure this modal stays on top: append to body and enforce z-index until closed
    try {
        document.body.appendChild(approveModalEl.closest('.modal'));
    } catch (e) {}

    const enforceKey = 'overlayKeepInterval';
    if (approveModalEl[enforceKey]) {
        clearInterval(approveModalEl[enforceKey]);
    }
    approveModalEl[enforceKey] = setInterval(function() {
        try {
            approveModalEl.style.zIndex = '99999';
            const pb = document.querySelector('.overlay-approve-backdrop');
            if (pb) pb.style.zIndex = '99998';
            const node = approveModalEl.closest('.modal');
            if (node && node.parentNode !== document.body) document.body.appendChild(node);
        } catch (e) {}
    }, 150);

    const cleanup = function() {
        try { modal.dispose(); } catch (e) {}
        document.body.classList.remove('overlay-approve-active');
        approveModalEl.classList.remove('overlay-approve-modal');
        const pb = document.querySelector('.overlay-approve-backdrop');
        if (pb && pb.parentNode) pb.parentNode.removeChild(pb);
        if (approveModalEl[enforceKey]) { clearInterval(approveModalEl[enforceKey]); delete approveModalEl[enforceKey]; }
    };

    approveModalEl.addEventListener('hidden.bs.modal', function handler() {
        cleanup();
        approveModalEl.removeEventListener('hidden.bs.modal', handler);
    });

    modal.show();
    }, 300);
}



function returnRequest(requestId) {
    if (confirm('Are you sure you want to mark this request as returned? This action will notify the patient.')) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        // Use global CSRF token variable
        if (typeof csrf !== 'undefined') {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrf;
            form.appendChild(csrfInput);
        } else {
            // Fallback: try to find CSRF token in page
            const csrfToken = document.querySelector('input[name="csrf_token"]');
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfToken.value;
                form.appendChild(csrfInput);
            }
        }
        
        const requestIdInput = document.createElement('input');
        requestIdInput.type = 'hidden';
        requestIdInput.name = 'request_id';
        requestIdInput.value = requestId;
        form.appendChild(requestIdInput);
        
        const returnInput = document.createElement('input');
        returnInput.type = 'hidden';
        returnInput.name = 'return_request';
        returnInput.value = '1';
        form.appendChild(returnInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function fulfillRequest(requestId, bloodType, units) {
    // Close the details modal first
    const detailsModal = document.getElementById('viewDetailsModal');
    if (detailsModal) {
        const bsModal = bootstrap.Modal.getInstance(detailsModal);
        if (bsModal) {
            bsModal.hide();
        }
    }
    
    // Wait a bit for the modal to close, then show confirmation
    setTimeout(function() {
    document.getElementById('fulfill_request_id').value = requestId;
    document.getElementById('fulfill_blood_type').value = bloodType;
    document.getElementById('fulfill_units').value = units;
    new bootstrap.Modal(document.getElementById('fulfillModal')).show();
    }, 300);
}

function viewDetails(buttonEl) {
    // Accept the clicked button element directly to avoid selector issues
    if (!buttonEl) { console.error('viewDetails called without button element'); return; }

    const viewButton = buttonEl;
    const requestId = viewButton.getAttribute('data-request-id');

    // Get all the data attributes
    const patientName = viewButton.getAttribute('data-patient-name');
    const phone = viewButton.getAttribute('data-phone');
    const bloodType = viewButton.getAttribute('data-blood-type');
    const units = viewButton.getAttribute('data-units');
    const requiredDate = viewButton.getAttribute('data-required-date');
    const requiredTime = viewButton.getAttribute('data-required-time');
    const status = viewButton.getAttribute('data-status');
    const hospital = viewButton.getAttribute('data-hospital');
    const doctor = viewButton.getAttribute('data-doctor');
    const reason = viewButton.getAttribute('data-reason');
    const requestForm = viewButton.getAttribute('data-request-form');
    const bloodCard = viewButton.getAttribute('data-blood-card');
    const referralId = viewButton.getAttribute('data-referral-id');
    const referralDocumentName = viewButton.getAttribute('data-referral-document-name');
    const referralDate = viewButton.getAttribute('data-referral-date');
    const barangayName = viewButton.getAttribute('data-barangay-name');

    // Build the modal content
    let modalContent = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary mb-3"><i class="bi bi-person-fill me-2"></i>Patient Information</h6>
                <div class="mb-2"><strong>Name:</strong> ${patientName || 'N/A'}</div>
                <div class="mb-2"><strong>Contact:</strong> ${phone || 'N/A'}</div>
                <div class="mb-2"><strong>Blood Type:</strong> <span class="badge bg-primary">${bloodType || 'N/A'}</span></div>
            </div>
            <div class="col-md-6">
                <h6 class="text-danger mb-3"><i class="bi bi-clipboard2-pulse me-2"></i>Request Details</h6>
                <div class="mb-2"><strong>Request ID:</strong> #${String(requestId).padStart(4, '0')}</div>
                <div class="mb-2"><strong>Units Required:</strong> ${units || 'N/A'}</div>
                <div class="mb-2"><strong>Required Date:</strong> ${requiredDate || 'N/A'}</div>
                <div class="mb-2"><strong>Required Time:</strong> ${formatTime(requiredTime) || 'N/A'}</div>
                <div class="mb-2"><strong>Status:</strong> 
                    <span class="badge ${getStatusBadgeClass(status)}">${status || 'Pending'}</span>
                </div>
            </div>
        </div>
        
        <hr class="my-4">
        
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-info mb-3"><i class="bi bi-hospital me-2"></i>Medical Information</h6>
                <div class="mb-2"><strong>Hospital/Clinic:</strong> ${hospital || 'N/A'}</div>
                <div class="mb-2"><strong>Reason:</strong> ${reason || 'N/A'}</div>
            </div>
            <div class="col-md-6">
                <h6 class="text-warning mb-3"><i class="bi bi-file-earmark-text me-2"></i>Documents</h6>
                <div class="mb-2">
                    <strong>Request Form:</strong>
                    ${requestForm ? `
                            <div class="mt-2">
                            <a href="view-request-form.php?request_id=${requestId}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            <a href="download-request-form.php?request_id=${requestId}" class="btn btn-sm btn-outline-success ms-1">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                    ` : '<span class="text-muted">Not provided</span>'}
                </div>
                <div class="mb-2">
                    <strong>Blood Card:</strong>
                    ${bloodCard ? `
                            <div class="mt-2">
                            <a href="view-blood-card.php?request_id=${requestId}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            <a href="download-blood-card.php?request_id=${requestId}" class="btn btn-sm btn-outline-success ms-1">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                    ` : '<span class="text-muted">Not provided</span>'}
                </div>
            </div>
        </div>`;

    // Add referral information if available (show if referral exists, even without document name)
    if (referralId) {
        modalContent += `
            <hr class="my-4">
            <div class="row">
                <div class="col-12">
                    <h6 class="text-secondary mb-3"><i class="bi bi-arrow-right-circle me-2"></i>Referral Information</h6>
                    <div class="mb-2"><strong>Referred by:</strong> ${barangayName || 'Barangay'}</div>
                    <div class="mb-2"><strong>Referral Date:</strong> ${referralDate || 'N/A'}</div>
                    <div class="mb-2">
                        <strong>Referral Document:</strong>
                        <div class="mt-2">
                            <a href="view-referral.php?request_id=${requestId}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            <a href="download-referral.php?request_id=${requestId}" class="btn btn-sm btn-outline-success ms-1">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>`;
    } else if (barangayName) {
        modalContent += `
            <hr class="my-4">
            <div class="row">
                <div class="col-12">
                    <h6 class="text-secondary mb-3"><i class="bi bi-arrow-right-circle me-2"></i>Referral Information</h6>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Pending referral from ${barangayName}
                    </div>
                </div>
            </div>`;
    }

    // Resolve modal parts safely
    let bodyEl = document.getElementById('viewDetailsModalBody');
    let footerEl = document.getElementById('viewDetailsModalFooter');
    let modalRoot = document.getElementById('viewDetailsModal');

    if (!bodyEl || !footerEl || !modalRoot) {
        // Build modal dynamically if not present
        const modalHTML = `
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="viewDetailsModalLabel">
          <i class="bi bi-clipboard2-pulse me-2"></i>Blood Request Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="viewDetailsModalBody"></div>
      <div class="modal-footer" id="viewDetailsModalFooter">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        // Re-resolve elements
        modalRoot = document.getElementById('viewDetailsModal');
        bodyEl = document.getElementById('viewDetailsModalBody');
        footerEl = document.getElementById('viewDetailsModalFooter');
        if (!bodyEl || !footerEl || !modalRoot) {
            console.error('Failed to create View Details modal elements');
            return;
        }
    }

    // Update modal content
    bodyEl.innerHTML = modalContent;

    // Update modal footer with action buttons based on status
    let footerContent = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
    
    const normalizedStatus = (status || '').trim().toLowerCase();
    if (!normalizedStatus || normalizedStatus === 'pending') {
        footerContent += `
            <button type="button" class="btn btn-danger ms-2" onclick="approveRequest(${requestId})">
                <i class="bi bi-check-circle me-1"></i>Approve
            </button>`;
    } else if (normalizedStatus === 'approved') {
        footerContent += `
            <button type="button" class="btn btn-warning ms-2" onclick="returnRequest(${requestId})">
                <i class="bi bi-arrow-return-left me-1"></i>Mark as Returned
            </button>
            <button type="button" class="btn btn-info ms-2" onclick="fulfillRequest(${requestId}, '${bloodType}', ${units})">
                <i class="bi bi-check-circle me-1"></i>Complete Request
            </button>`;
    }

    footerEl.innerHTML = footerContent;

    // Show the modal
    try {
        const modal = new bootstrap.Modal(modalRoot);
        // Ensure clean lifecycle to avoid stuck modals
        modalRoot.addEventListener('hidden.bs.modal', function onHidden(){
            try { 
                modal.dispose(); 
                // Ensure body scroll is restored
                setTimeout(function() {
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }, 50);
            } catch (ex) {}
            modalRoot.removeEventListener('hidden.bs.modal', onHidden);
        });
        modal.show();
    } catch (e) {
        console.error('Failed to open Bootstrap modal', e);
    }
}

function getStatusBadgeClass(status) {
    const normalizedStatus = (status || '').trim().toLowerCase();
    switch (normalizedStatus) {
        case 'pending': return 'bg-warning text-dark';
    case 'approved': return 'bg-danger';
        case 'completed': return 'bg-info';
        default: return 'bg-secondary';
    }
}

function formatTime(timeString) {
    if (!timeString) return null;
    // Handle time formats: HH:MM:SS or HH:MM
    const timeMatch = timeString.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
    if (!timeMatch) return timeString;
    
    let hours = parseInt(timeMatch[1], 10);
    const minutes = timeMatch[2];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // the hour '0' should be '12'
    return `${hours}:${minutes} ${ampm}`;
}

// Global modal cleanup: ensure body scroll is restored when modals close
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('hidden.bs.modal', function (e) {
        // allow Bootstrap to finish its cleanup then ensure no leftover modal-open/backdrop
        setTimeout(function () {
            try {
                const openModals = document.querySelectorAll('.modal.show');
                if (!openModals || openModals.length === 0) {
                    // remove class that prevents scrolling
                    document.body.classList.remove('modal-open');
                    // restore body overflow and padding
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    // remove any leftover backdrops except our persistent approve overlay
                    document.querySelectorAll('.modal-backdrop:not(.overlay-approve-backdrop)').forEach(function(b) {
                        if (b && b.parentNode) b.parentNode.removeChild(b);
                    });
                }
            } catch (e) {
                console.error('Modal cleanup error', e);
            }
        }, 100);
    });
    
    // Additional cleanup on page visibility change or window focus
    window.addEventListener('focus', function() {
        setTimeout(function() {
            const openModals = document.querySelectorAll('.modal.show');
            if (openModals.length === 0) {
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        }, 100);
    });

    // Filter table by status when clicking stat cards
    const statCards = document.querySelectorAll('.clickable-stat-card');
    const tableBody = document.getElementById('requestsTableBody');
    
    if (statCards && tableBody) {
        statCards.forEach(function(card) {
            card.addEventListener('click', function() {
                const status = this.getAttribute('data-status');
                
                // Remove active class from all cards
                statCards.forEach(function(c) {
                    c.classList.remove('active');
                });
                
                // Add active class to clicked card
                this.classList.add('active');
                
                // Filter table rows
                const rows = tableBody.querySelectorAll('.request-row');
                let visibleCount = 0;
                
                rows.forEach(function(row) {
                    const rowStatus = row.getAttribute('data-status');
                    const statusLower = status.toLowerCase();
                    
                    if (statusLower === 'all' || rowStatus === statusLower) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Show message if no rows visible
                let noResultsRow = tableBody.querySelector('.no-results-row');
                if (visibleCount === 0) {
                    if (!noResultsRow) {
                        noResultsRow = document.createElement('tr');
                        noResultsRow.className = 'no-results-row';
                        noResultsRow.innerHTML = '<td colspan="6" class="text-center py-5"><i class="bi bi-clipboard2-x text-muted" style="font-size: 3rem;"></i><p class="mb-0 mt-3 text-muted">No ' + status + ' requests found.</p></td>';
                        tableBody.appendChild(noResultsRow);
                    }
                } else {
                    if (noResultsRow) {
                        noResultsRow.remove();
                    }
                }
                
                // Scroll to table
                tableBody.closest('.requests-table-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
        
        // Check if URL has status parameter and filter accordingly
        const urlParams = new URLSearchParams(window.location.search);
        const statusParam = urlParams.get('status');
        if (statusParam) {
            const matchingCard = Array.from(statCards).find(function(card) {
                return card.getAttribute('data-status').toLowerCase() === statusParam.toLowerCase();
            });
            if (matchingCard) {
                matchingCard.click();
            }
        }
    }
});
</script>
<script src="../../assets/js/titlecase-formatter.js"></script>

<!-- Approve Request Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveModalLabel">Approve Blood Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="approveRequestForm">
                <div class="modal-body" style="padding: 1.5rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                    <input type="hidden" name="request_id" id="approve_request_id" value="">
                    <input type="hidden" name="notes" value="">
                    <p style="margin: 0; font-size: 1rem; line-height: 1.6;">
                        <strong>Are you sure you want to approve this request?</strong> This will approve the blood request and notify the patient.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="approve_request" class="btn btn-danger">
                        <i class="bi bi-check-circle me-1"></i>Approve Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- Fulfill Request Modal -->
<div class="modal fade" id="fulfillModal" tabindex="-1" aria-labelledby="fulfillModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fulfillModalLabel">Complete Blood Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="fulfillRequestForm">
                <div class="modal-body" style="padding: 1.5rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                    <input type="hidden" name="request_id" id="fulfill_request_id" value="">
                    <input type="hidden" name="blood_type" id="fulfill_blood_type" value="">
                    <input type="hidden" name="units" id="fulfill_units" value="">
                    <input type="hidden" name="notes" value="">
                    <p style="margin: 0; font-size: 1rem; line-height: 1.6;">
                        <strong>Are you sure you want to complete this request?</strong> This will mark the blood request as completed and update the inventory.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="fulfill_request" class="btn btn-info">
                        <i class="bi bi-check-circle me-1"></i>Complete Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Ensure page refreshes after form submission
document.addEventListener('DOMContentLoaded', function() {
    // Handle approve form submission
    const approveForm = document.getElementById('approveRequestForm');
    if (approveForm) {
        approveForm.addEventListener('submit', function(e) {
            // Form will submit normally, page will refresh via server redirect
            // The server redirects to blood-requests.php?success=1 which refreshes the page
        });
    }
    
    // Handle fulfill form submission
    const fulfillForm = document.getElementById('fulfillRequestForm');
    if (fulfillForm) {
        fulfillForm.addEventListener('submit', function(e) {
            // Form will submit normally, page will refresh via server redirect
            // The server redirects to blood-requests.php?success=1 which refreshes the page
        });
    }
    
    // If success parameter is in URL, ensure page is refreshed (in case redirect didn't work)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        // Page should already be refreshed by the redirect, but ensure it's clean
        // Remove the success parameter from URL without reloading
        if (window.history && window.history.replaceState) {
            const newUrl = window.location.pathname + (window.location.search.replace(/[?&]success=1/, '').replace(/^&/, '?') || '');
            window.history.replaceState({}, '', newUrl);
        }
    }
});
</script>

