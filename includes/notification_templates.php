<?php
/**
 * Notification Message Templates for Blood Bank Management Portal
 * Professional, polite, and clear messages for SMS and in-app notifications
 */

/**
 * Get institution name based on organization type
 * 
 * @param string $organizationType 'redcross' or 'negrosfirst'
 * @return string Institution name
 */
function get_institution_name($organizationType) {
    switch (strtolower($organizationType)) {
        case 'redcross':
            return 'Philippine Red Cross - Bacolod Chapter';
        case 'negrosfirst':
            return 'Negros First Provincial Blood Center';
        default:
            return 'Blood Bank Portal';
    }
}

/**
 * Template 1: Approved Request Notification
 * 
 * @param string $name Patient/Donor name
 * @param string $organizationType Organization type
 * @param array $data Additional data (date, time, location, etc.)
 * @return string Formatted message
 */
function template_approved_request($name, $organizationType, $data = []) {
    $institution = get_institution_name($organizationType);
    $greeting = !empty($name) ? "Hello {$name}" : "Hello";
    
    $message = "{$greeting}, this is from {$institution}. ";
    $message .= "We want to inform you that your blood request has been approved. ";
    
    if (!empty($data['units_requested']) && !empty($data['blood_type'])) {
        $message .= "Requested: {$data['units_requested']} unit(s) of {$data['blood_type']}. ";
    }
    
    if (!empty($data['date']) && !empty($data['time'])) {
        $formattedDate = date('M d, Y', strtotime($data['date']));
        $formattedTime = date('h:i A', strtotime($data['time']));
        $message .= "Please come on {$formattedDate} at {$formattedTime}";
        
        if (!empty($data['location'])) {
            $message .= " to {$data['location']}";
        }
        $message .= ". ";
    } elseif (!empty($data['date'])) {
        $formattedDate = date('M d, Y', strtotime($data['date']));
        $message .= "Please coordinate pickup on {$formattedDate}. ";
    }
    
    if (!empty($data['request_id'])) {
        $message .= "Request ID: {$data['request_id']}. ";
    }
    
    $message .= "Thank you!";
    
    return $message;
}

/**
 * Template 2: Appointment Confirmation
 * 
 * @param string $name Donor name
 * @param string $organizationType Organization type
 * @param array $data Additional data (date, time, location, etc.)
 * @return string Formatted message
 */
function template_appointment_confirmation($name, $organizationType, $data = []) {
    $institution = get_institution_name($organizationType);
    $greeting = !empty($name) ? "Hello {$name}" : "Hello";
    
    $message = "{$greeting}, this is from {$institution}. ";
    
    if (isset($data['status']) && $data['status'] === 'Scheduled') {
        $message .= "Your blood donation appointment has been confirmed and scheduled";
    } else {
        $message .= "Your blood donation appointment request has been received and is pending approval";
    }
    
    if (!empty($data['date']) && !empty($data['time'])) {
        $formattedDate = date('M d, Y', strtotime($data['date']));
        $formattedTime = date('h:i A', strtotime($data['time']));
        $message .= " for {$formattedDate} at {$formattedTime}";
        
        if (!empty($data['location'])) {
            $message .= " at {$data['location']}";
        }
        $message .= ". ";
    } else {
        $message .= ". ";
    }
    
    if (isset($data['status']) && $data['status'] === 'Scheduled') {
        $message .= "Please arrive 15 minutes before your scheduled time. ";
        if (!empty($data['notes'])) {
            $message .= "Note: {$data['notes']}. ";
        }
    } else {
        $message .= "You will be notified once the appointment is confirmed. ";
    }
    
    $message .= "Thank you for your willingness to help save lives!";
    
    return $message;
}

/**
 * Template 3: Rejected/Declined Request
 * 
 * @param string $name Patient/Donor name
 * @param string $organizationType Organization type
 * @param array $data Additional data (reason, date, etc.)
 * @return string Formatted message
 */
function template_rejected_request($name, $organizationType, $data = []) {
    $institution = get_institution_name($organizationType);
    $greeting = !empty($name) ? "Hello {$name}" : "Hello";
    
    $message = "{$greeting}, this is from {$institution}. ";
    
    // Determine if it's a request or appointment
    $type = isset($data['type']) ? $data['type'] : 'request';
    
    if ($type === 'appointment') {
        $message .= "We regret to inform you that your blood donation appointment request has been declined";
    } else {
        $message .= "We regret to inform you that your blood request has been declined";
    }
    
    if (!empty($data['reason'])) {
        $message .= " due to the following reason: {$data['reason']}. ";
    } else {
        $message .= ". ";
    }
    
    if (!empty($data['blood_type']) && !empty($data['units_requested'])) {
        $message .= "Your request for {$data['units_requested']} unit(s) of {$data['blood_type']} cannot be fulfilled at this time. ";
    }
    
    $message .= "If you have any questions or concerns, please contact us. ";
    $message .= "We appreciate your understanding. Thank you.";
    
    return $message;
}

/**
 * Template 4: Donation Reminder
 * 
 * @param string $name Donor name
 * @param string $organizationType Organization type
 * @param array $data Additional data (date, time, location, etc.)
 * @return string Formatted message
 */
function template_donation_reminder($name, $organizationType, $data = []) {
    $institution = get_institution_name($organizationType);
    $greeting = !empty($name) ? "Hello {$name}" : "Hello";
    
    $message = "{$greeting}, this is from {$institution}. ";
    
    if (!empty($data['date']) && !empty($data['time'])) {
        $formattedDate = date('M d, Y', strtotime($data['date']));
        $formattedTime = date('h:i A', strtotime($data['time']));
        $message .= "This is a friendly reminder that you have a blood donation appointment scheduled for {$formattedDate} at {$formattedTime}";
        
        if (!empty($data['location'])) {
            $message .= " at {$data['location']}";
        }
        $message .= ". ";
    } else {
        $message .= "This is a friendly reminder about your upcoming blood donation appointment. ";
    }
    
    $message .= "Please arrive 15 minutes early and bring a valid ID. ";
    
    if (!empty($data['last_donation_date'])) {
        $daysSince = (time() - strtotime($data['last_donation_date'])) / (60 * 60 * 24);
        if ($daysSince < 56) {
            $daysLeft = 56 - floor($daysSince);
            $message .= "Reminder: You must wait {$daysLeft} more day(s) before you can donate again. ";
        }
    }
    
    $message .= "Your contribution helps save lives. Thank you!";
    
    return $message;
}

/**
 * Template 5: Completed Request/Appointments
 * 
 * @param string $name Patient/Donor name
 * @param string $organizationType Organization type
 * @param array $data Additional data (type, date, etc.)
 * @return string Formatted message
 */
function template_completed_request($name, $organizationType, $data = []) {
    $institution = get_institution_name($organizationType);
    $greeting = !empty($name) ? "Hello {$name}" : "Hello";
    
    $message = "{$greeting}, this is from {$institution}. ";
    
    // Determine completion type
    $type = isset($data['type']) ? $data['type'] : 'request';
    
    if ($type === 'donation') {
        $message .= "Thank you for your generous blood donation";
        if (!empty($data['date'])) {
            $formattedDate = date('M d, Y', strtotime($data['date']));
            $message .= " on {$formattedDate}";
        }
        $message .= "! Your contribution will help save lives. ";
        
        if (!empty($data['blood_type']) && !empty($data['units'])) {
            $message .= "You donated {$data['units']} unit(s) of {$data['blood_type']}. ";
        }
        
        $message .= "You will be eligible to donate again after 56 days. ";
        $message .= "We truly appreciate your kindness and support!";
        
    } elseif ($type === 'request') {
        $message .= "We want to inform you that your blood request process has been completed";
        if (!empty($data['date'])) {
            $formattedDate = date('M d, Y', strtotime($data['date']));
            $message .= " on {$formattedDate}";
        }
        $message .= ". ";
        
        if (!empty($data['blood_type']) && !empty($data['units_requested'])) {
            $message .= "Your request for {$data['units_requested']} unit(s) of {$data['blood_type']} ";
        }
        
        // Check if patient has already picked up the blood
        if (isset($data['pickup_completed']) && $data['pickup_completed'] === true) {
            $message .= "has been successfully processed and you have already picked up the blood. ";
            $message .= "Thank you for using our services.";
        } else {
            $message .= "is ready. Please coordinate pickup with the blood center at your earliest convenience. ";
            $message .= "Thank you for your patience.";
        }
        
    } else {
        // Generic completion
        $message .= "We want to inform you that your transaction has been completed successfully";
        if (!empty($data['date'])) {
            $formattedDate = date('M d, Y', strtotime($data['date']));
            $message .= " on {$formattedDate}";
        }
        $message .= ". ";
        
        if (!empty($data['notes'])) {
            $message .= "{$data['notes']}. ";
        }
        
        $message .= "Thank you!";
    }
    
    return $message;
}

/**
 * Template 6: Blood Drive Announcement
 * 
 * @param string $name Donor/Patient name
 * @param string $organizationType Organization type
 * @param array $data Additional data (title, date, time, location, address, etc.)
 * @return string Formatted message
 */
function template_blood_drive_announcement($name, $organizationType, $data = []) {
    $institution = get_institution_name($organizationType);
    $greeting = !empty($name) ? "Hello {$name}" : "Hello";
    
    $message = "{$greeting}, this is from {$institution}. ";
    $message .= "A new blood drive has been scheduled";
    
    if (!empty($data['title'])) {
        $message .= ": {$data['title']}";
    }
    
    if (!empty($data['date'])) {
        $formattedDate = date('M d, Y', strtotime($data['date']));
        $message .= " on {$formattedDate}";
        
        if (!empty($data['time'])) {
            $formattedTime = date('h:i A', strtotime($data['time']));
            $message .= " at {$formattedTime}";
        }
        
        $message .= ".";
    }
    
    if (!empty($data['location'])) {
        $message .= " Location: {$data['location']}";
        
        if (!empty($data['address'])) {
            $message .= ", {$data['address']}";
        }
        $message .= ".";
    }
    
    if (!empty($data['requirements'])) {
        $message .= " Requirements: {$data['requirements']}. ";
    }
    
    $message .= " Please check your dashboard for details and consider participating. ";
    $message .= "Your contribution helps save lives. Thank you!";
    
    return $message;
}

/**
 * Get notification message based on type and data
 * Main function to retrieve formatted notification messages
 * 
 * @param string $type Notification type (approved, appointment, rejected, reminder, completed)
 * @param string $name User name
 * @param string $organizationType Organization type (redcross or negrosfirst)
 * @param array $data Additional data for message customization
 * @return string Formatted notification message
 */
function get_notification_message($type, $name, $organizationType, $data = []) {
    // Normalize organization type
    $orgType = strtolower($organizationType);
    if (strpos($orgType, 'redcross') !== false || strpos($orgType, 'red_cross') !== false) {
        $orgType = 'redcross';
    } elseif (strpos($orgType, 'negros') !== false || strpos($orgType, 'negrosfirst') !== false) {
        $orgType = 'negrosfirst';
    }
    
    // Normalize notification type
    $notificationType = strtolower($type);
    
    switch ($notificationType) {
        case 'approved':
        case 'approve':
        case 'approval':
            return template_approved_request($name, $orgType, $data);
            
        case 'appointment':
        case 'confirm':
        case 'confirmed':
        case 'scheduled':
            return template_appointment_confirmation($name, $orgType, $data);
            
        case 'rejected':
        case 'reject':
        case 'declined':
        case 'decline':
            return template_rejected_request($name, $orgType, $data);
            
        case 'reminder':
        case 'remind':
            return template_donation_reminder($name, $orgType, $data);
            
        case 'completed':
        case 'complete':
        case 'done':
        case 'finished':
            return template_completed_request($name, $orgType, $data);
            
        case 'blood_drive':
        case 'blooddrive':
        case 'drive':
            return template_blood_drive_announcement($name, $orgType, $data);
            
        case 'announcement':
        case 'announce':
            // Generic announcement message
            $institution = get_institution_name($orgType);
            $greeting = !empty($name) ? "Hello {$name}" : "Hello";
            $message = "{$greeting}, this is from {$institution}. ";
            $message .= !empty($data['message']) ? $data['message'] : "You have a new announcement from the Blood Bank Portal.";
            $message .= " Please check your dashboard for details. Thank you!";
            return $message;
            
        default:
            // Fallback generic message
            $institution = get_institution_name($orgType);
            $greeting = !empty($name) ? "Hello {$name}" : "Hello";
            return "{$greeting}, this is from {$institution}. " . 
                   (!empty($data['message']) ? $data['message'] : "You have a new notification from the Blood Bank Portal. Thank you!");
    }
}

/**
 * Format notification for display
 * Removes extra spaces and ensures proper formatting
 * 
 * @param string $message Raw message
 * @return string Formatted message
 */
function format_notification_message($message) {
    // Remove multiple spaces
    $message = preg_replace('/\s+/', ' ', $message);
    // Trim whitespace
    $message = trim($message);
    // Ensure proper punctuation at the end
    if (!preg_match('/[.!?]$/', $message)) {
        $message .= '.';
    }
    return $message;
}

/**
 * Send notification with SMS (Centralized helper for Negros First)
 * Creates in-app notification and sends SMS via SIM800C
 * 
 * @param string $userId User ID (patient or donor)
 * @param string $userRole User role ('patient' or 'donor')
 * @param string $notificationType Type of notification (approved, appointment, rejected, completed, blood_drive, etc.)
 * @param string $notificationTitle Title for in-app notification
 * @param array $data Additional data for message customization
 * @param string $organizationType Organization type (default: 'negrosfirst')
 * @return array ['notification_success' => bool, 'sms_success' => bool, 'sms_error' => string|null]
 */
function send_notification_with_sms($userId, $userRole, $notificationType, $notificationTitle, $data = [], $organizationType = 'negrosfirst') {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/sim800c_sms.php';
    
    $result = [
        'notification_success' => false,
        'sms_success' => false,
        'sms_error' => null
    ];
    
    try {
        // Get user name and phone
        $table = $userRole . '_users';
        $user = getRow("SELECT name, phone FROM {$table} WHERE id = ?", [$userId]);
        
        if (!$user) {
            if (function_exists('secure_log')) {
                secure_log("User not found for notification", ['user_role' => $userRole, 'user_id' => (int)$userId]);
            }
            return $result;
        }
        
        $userName = $user['name'] ?? '';
        $userPhone = $user['phone'] ?? '';
        
        // Decrypt phone if encrypted
        if (!empty($userPhone) && function_exists('decrypt_value')) {
            $decryptedPhone = decrypt_value($userPhone);
            if (!empty($decryptedPhone)) {
                $userPhone = $decryptedPhone;
            }
        }
        
        // Get notification message using template
        $notificationMessage = get_notification_message($notificationType, $userName, $organizationType, $data);
        $notificationMessage = format_notification_message($notificationMessage);
        
        // Create in-app notification
        $notificationSql = "INSERT INTO notifications (title, message, user_id, user_role, is_read, created_at) 
                           VALUES (?, ?, ?, ?, 0, NOW())";
        
        $notificationResult = insertRow($notificationSql, [
            $notificationTitle,
            $notificationMessage,
            $userId,
            $userRole
        ]);
        
        $result['notification_success'] = $notificationResult !== false;
        
        if (!$notificationResult) {
            if (function_exists('secure_log')) {
                secure_log("Failed to create notification", ['user_role' => $userRole, 'user_id' => (int)$userId]);
            }
        }
        
        // Send SMS if phone number exists
        if (!empty($userPhone) && trim($userPhone) !== '') {
            try {
                $smsResult = send_sms_sim800c($userPhone, $notificationMessage);
                
                if ($smsResult['success']) {
                    $result['sms_success'] = true;
                    if (function_exists('secure_log')) {
                        secure_log("SMS sent successfully", [
                            'user_role' => $userRole,
                            'user_id' => (int)$userId
                        ]);
                    }
                } else {
                    $result['sms_error'] = $smsResult['error'] ?? 'Unknown error';
                    if (function_exists('secure_log')) {
                        secure_log("Failed to send SMS", [
                            'user_role' => $userRole,
                            'user_id' => (int)$userId,
                            'error' => substr($result['sms_error'], 0, 200)
                        ]);
                    }
                }
            } catch (Exception $smsEx) {
                $result['sms_error'] = $smsEx->getMessage();
                if (function_exists('secure_log')) {
                    secure_log("Exception sending SMS", [
                        'user_role' => $userRole,
                        'user_id' => (int)$userId,
                        'error' => substr($smsEx->getMessage(), 0, 200)
                    ]);
                }
            }
        } else {
            if (function_exists('secure_log')) {
                secure_log("No phone number found for SMS", ['user_role' => $userRole, 'user_id' => (int)$userId]);
            }
        }
        
    } catch (Exception $e) {
        if (function_exists('secure_log')) {
            secure_log("Exception in send_notification_with_sms", ['error' => substr($e->getMessage(), 0, 200)]);
        }
        $result['sms_error'] = $e->getMessage();
    }
    
    return $result;
}
?>

