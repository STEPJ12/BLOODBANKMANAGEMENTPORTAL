<?php
session_start();

// Check if user is logged in and has the 'redcross' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'redcross') {
    header("Location: ../../loginredcross.php?role=redcross");
    exit;
}

// Set page title
$pageTitle = "Appointment Management";

// Include database connection
require_once '../../config/db.php';
echo '<script src="../../assets/js/universal-print.js"></script>';

// Initialize variables
$success_message = '';
$errors = [];

// Success feedback via PRG pattern from session
if (isset($_SESSION['appointment_message'])) {
    $success_message = $_SESSION['appointment_message'];
    unset($_SESSION['appointment_message']);
}
if (isset($_SESSION['appointment_errors'])) {
    $errors = $_SESSION['appointment_errors'];
    unset($_SESSION['appointment_errors']);
}

// Handle form submission for creating appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_appointment']) && !isset($_GET['success'])) {
    // CSRF protection
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh the page and try again.';
    } else {
    $donor_id = (int)$_POST['donor_id'];
    $appointment_date = sanitize($_POST['appointment_date']);
    $appointment_time = sanitize($_POST['appointment_time']);
    // Normalize location (Title Case, single spaces) and notes (single spaces)
    // Note: Title case formatting is applied client-side via titlecase-formatter.js
    $location = normalize_input($_POST['location'] ?? '', true);
    $notes = normalize_input($_POST['notes'] ?? '');

    // Validate inputs
    if ($donor_id <= 0) {
        $errors[] = "Please select a valid donor";
    }

    if (empty($appointment_date)) {
        $errors[] = "Appointment date is required";
    } else {
        $appointment_date_obj = new DateTime($appointment_date);
        $today = new DateTime();

        if ($appointment_date_obj < $today) {
            $errors[] = "Appointment date cannot be in the past";
        }
    }

    if (empty($appointment_time)) {
        $errors[] = "Appointment time is required";
    }

    if (empty($location)) {
        $errors[] = "Location is required";
    }

    // Check if donor is eligible
    if ($donor_id > 0) {
        $donor_sql = "SELECT last_donation_date FROM donor_users WHERE id = ?";
        $donor = getRow($donor_sql, [$donor_id]);

        if ($donor) {
            if (!empty($donor['last_donation_date'])) {
                $last_donation = new DateTime($donor['last_donation_date']);
                $min_days_between_donations = 56; // 8 weeks

                $last_donation->add(new DateInterval("P{$min_days_between_donations}D"));
                $appointment_date_obj = new DateTime($appointment_date);

                if ($appointment_date_obj < $last_donation) {
                    $next_eligible_date = $last_donation->format('Y-m-d');
                    $errors[] = "Donor must wait until $next_eligible_date before donating again (minimum 8 weeks between donations)";
                }
            }
        } else {
            $errors[] = "Selected donor not found";
        }
    }

    // If no errors, insert appointment into database
    if (empty($errors)) {
        $sql = "INSERT INTO donor_appointments (
                donor_id, appointment_date, appointment_time,
                organization_type, organization_id, status, notes, created_at, updated_at
            ) VALUES (
                ?, ?, ?,
                'redcross', ?, 'Pending', ?, NOW(), NOW()
            )";

        $params = [
            $donor_id, $appointment_date, $appointment_time,
            $_SESSION['user_id'], $notes
        ];

        $appointment_id = insertRow($sql, $params);

        if ($appointment_id) {
            // Add notification for the donor about pending approval
            $notification_sql = "INSERT INTO notifications (
                title, message, user_id, user_role, is_read, created_at
            ) VALUES (?, ?, ?, 'donor', 0, NOW())";
            
            // Get donor information for personalized message
            $donorInfo = getRow("SELECT name FROM donor_users WHERE id = ?", [$donor_id]);
            $donorName = $donorInfo['name'] ?? '';
            
            // Use professional notification template
            require_once '../../includes/notification_templates.php';
            $pendingMessage = get_notification_message('appointment', $donorName, 'redcross', [
                'status' => 'pending',
                'date' => $appointment_date,
                'time' => $appointment_time,
                'location' => $location ?? ''
            ]);
            $pendingMessage = format_notification_message($pendingMessage);
            
            $notification_params = [
                'Donation Appointment Pending Approval',
                $pendingMessage,
                $donor_id
            ];
            
            insertRow($notification_sql, $notification_params);

            // Get donor phone number for SMS
            $donorInfoPhone = getRow("SELECT phone FROM donor_users WHERE id = ?", [$donor_id]);
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
            
            // SMS notification via SIM800C (creation) if phone number exists (skip enabled check for automated sends)
            try {
                require_once '../../includes/sim800c_sms.php';
                
                if (function_exists('secure_log')) {
                    secure_log('[SIM800C] Appointment creation SMS attempt', [
                        'donor_id' => $donor_id,
                        'phone_prefix' => !empty($donorPhone) ? substr($donorPhone, 0, 4) . '****' : 'EMPTY'
                    ]);
                }
                
                // Check if phone number exists
                if (!empty($donorPhone)) {
                    // Try to send SMS directly - skip enabled check for automated sends
                    // (enabled check may fail due to DB connection issues, but script still works)
                    if (function_exists('secure_log')) {
                        secure_log('[SIM800C] Attempting to send appointment creation SMS');
                    }
                    $smsResult = send_sms_sim800c($donorPhone, $pendingMessage);
                    
                    if ($smsResult['success']) {
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C] Appointment creation SMS sent successfully', ['phone_prefix' => substr($donorPhone, 0, 4) . '****']);
                        }
                    } else {
                        $smsError = $smsResult['error'] ?? 'Unknown error';
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C] Failed to send appointment creation SMS', [
                                'phone_prefix' => substr($donorPhone, 0, 4) . '****',
                                'error' => substr($smsError, 0, 500)
                            ]);
                        }
                    }
                } else {
                    if (function_exists('secure_log')) {
                        secure_log('[SIM800C] Cannot send appointment creation SMS - donor phone number not found', ['donor_id' => $donor_id]);
                    }
                }
            } catch (Throwable $e) {
                if (function_exists('secure_log')) {
                    secure_log('[SIM800C_ERR] Exception in appointment creation SMS', [
                        'error' => substr($e->getMessage(), 0, 500),
                        'trace' => substr($e->getTraceAsString(), 0, 1000)
                    ]);
                }
            }

            // Log the appointment creation
            $log_sql = "INSERT INTO audit_logs (user_role, user_id, action, details, ip_address)
                       VALUES (?, ?, ?, ?, ?)";
            $log_params = [
                'redcross', // Assuming user role
                1, // Assuming user ID
                'Create Appointment',
                "Created appointment ID: $appointment_id for donor ID: $donor_id",
                $_SERVER['REMOTE_ADDR']
            ];

            insertRow($log_sql, $log_params);

            // Store success message in session and redirect (POST-redirect-GET pattern)
            $_SESSION['appointment_message'] = "Appointment request submitted successfully! (ID: $appointment_id) - Waiting for approval.";
            header('Location: appointments.php?success=1');
            exit;
        } else {
            $_SESSION['appointment_errors'] = ["Error scheduling appointment. Please try again."];
            header('Location: appointments.php?success=1');
            exit;
        }
    } else {
        // Store errors in session if validation failed
        if (!empty($errors)) {
            $_SESSION['appointment_errors'] = $errors;
            header('Location: appointments.php?success=1');
            exit;
        }
    }
    }
}

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment_status']) && !isset($_GET['updated'])) {
    // CSRF protection
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['appointment_errors'] = ['Invalid form token. Please refresh the page and try again.'];
        header('Location: appointments.php?updated=1');
        exit;
    }
    
    $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
    if ($appointment_id <= 0 && isset($_GET['aid'])) {
        $appointment_id = (int)$_GET['aid'];
    }
    
    // Prevent duplicate submissions - use a session-based nonce
    // Generate a unique token for this specific action
    $action_key = 'appt_action_' . $appointment_id . '_' . ($_POST['status'] ?? '');
    if (!isset($_SESSION['processed_actions'])) {
        $_SESSION['processed_actions'] = [];
    }
    
    // Check if this exact action was processed in the last 5 seconds (prevent rapid double-clicks)
    if (isset($_SESSION['processed_actions'][$action_key])) {
        $last_action_time = $_SESSION['processed_actions'][$action_key];
        if (time() - $last_action_time < 5) {
            // Same action within 5 seconds - likely duplicate submission
            $_SESSION['appointment_message'] = "Please wait before repeating this action.";
            header('Location: appointments.php?updated=1');
            exit;
        }
    }
    
    // Mark this action as being processed (will be cleared after successful redirect)
    $_SESSION['processed_actions'][$action_key] = time();
    $status = sanitize($_POST['status'] ?? '');
    $collected_units = isset($_POST['collected_units']) ? (int)$_POST['collected_units'] : 1;
    if ($collected_units < 1) { $collected_units = 1; }
    if ($collected_units > 2) { $collected_units = 2; }
    // Normalize status input - ensure proper capitalization
    $status = trim($status);
    // Convert to lowercase for comparison, then normalize to proper case
    $statusLower = strtolower($status);
    if ($statusLower === 'approved' || $statusLower === 'approve') { $status = 'Approved'; }
    elseif ($statusLower === 'scheduled' || $statusLower === 'schedule') { $status = 'Scheduled'; }
    elseif ($statusLower === 'completed' || $statusLower === 'complete') { $status = 'Completed'; }
    elseif ($statusLower === 'rejected' || $statusLower === 'reject') { $status = 'Rejected'; }
    elseif ($statusLower === 'no show' || $statusLower === 'no_show' || $statusLower === 'noshow') { $status = 'No Show'; }
    elseif ($statusLower === 'pending') { $status = 'Pending'; }
    // Log for debugging
    if (function_exists('secure_log')) {
        secure_log("[APPOINTMENT UPDATE] Status normalization", [
            'original_status' => substr($_POST['status'] ?? 'NOT SET', 0, 50),
            'normalized_status' => substr($status, 0, 50)
        ]);
    }
    $notes = normalize_input($_POST['status_notes'] ?? '');
    // Optional schedule updates when approving
    $new_date = isset($_POST['appointment_date']) ? sanitize($_POST['appointment_date']) : null;
    $new_time = isset($_POST['appointment_time']) ? sanitize($_POST['appointment_time']) : null;
    $new_location = isset($_POST['location']) ? normalize_input($_POST['location'], true) : null;

    // If approving, change status to Scheduled
    if ($status === 'Approved') { $status = 'Scheduled'; }

    // Validate inputs
    if ($appointment_id <= 0) {
        $errors[] = "Invalid appointment selection";
    }

    if (empty($status) || !in_array($status, ['Pending', 'Scheduled', 'Rejected', 'Completed', 'No Show'], true)) {
        $errors[] = "Valid status is required";
    }

    // If no errors, update appointment status (and optionally schedule details)
    if (empty($errors)) {
        $status_note = "[" . date('Y-m-d H:i:s') . "] Status changed to $status: $notes";

        // Base update parts
        $update_fields = "status = ?, notes = CONCAT(IFNULL(notes, ''), '\n', ?), updated_at = NOW()";
        $params = [$status, $status_note];

        // If being scheduled and schedule fields provided, include them
        if ($status === 'Scheduled') {
            if (!empty($new_date)) {
                $update_fields .= ", appointment_date = ?";
                $params[] = $new_date;
            }
            if (!empty($new_time)) {
                $update_fields .= ", appointment_time = ?";
                $params[] = $new_time;
            }
            if (!empty($new_location)) {
                $update_fields .= ", location = ?";
                $params[] = $new_location;
            }
        }

        // Before updating, check if appointment exists and verify it belongs to redcross
        $beforeUpdate = getRow("SELECT id, status, organization_type, organization_id FROM donor_appointments WHERE id = ?", [$appointment_id]);
        if (!$beforeUpdate) {
            if (function_exists('secure_log')) {
                secure_log("[APPOINTMENT UPDATE] ERROR: Appointment does not exist", ['appointment_id' => $appointment_id]);
            }
            $_SESSION['appointment_errors'] = ["Appointment not found. ID: $appointment_id"];
            header('Location: appointments.php?updated=1');
            exit;
        }
        
        if (function_exists('secure_log')) {
            secure_log("[APPOINTMENT UPDATE] Before update", [
                'appointment_id' => $beforeUpdate['id'],
                'current_status' => substr($beforeUpdate['status'] ?? '', 0, 50),
                'org_type' => substr($beforeUpdate['organization_type'] ?? '', 0, 50),
                'org_id' => $beforeUpdate['organization_id'] ?? null
            ]);
        }
        
        // Check if appointment belongs to redcross
        if ($beforeUpdate['organization_type'] !== 'redcross') {
            if (function_exists('secure_log')) {
                secure_log("[APPOINTMENT UPDATE] ERROR: Appointment belongs to different organization", [
                    'appointment_id' => $appointment_id,
                    'org_type' => substr($beforeUpdate['organization_type'] ?? '', 0, 50)
                ]);
            }
            $_SESSION['appointment_errors'] = ["Cannot update appointment: This appointment belongs to a different organization."];
            header('Location: appointments.php?updated=1');
            exit;
        }
        
        // Add organization filter for security (double-check)
        $update_sql = "UPDATE donor_appointments SET {$update_fields} WHERE id = ? AND organization_type = 'redcross'";
        $params[] = $appointment_id;
        
        // Debug: Log the update query and parameters using secure_log
        if (function_exists('secure_log')) {
            $lastParamIndex = count($params) - 1;
            $firstParam = isset($params[0]) ? $params[0] : 'N/A';
            $lastParam = isset($params[$lastParamIndex]) ? $params[$lastParamIndex] : 'N/A';
            secure_log("[APPOINTMENT UPDATE] Executing update", [
                'appointment_id' => $appointment_id,
                'status' => substr($status, 0, 50),
                'params_count' => count($params),
                'first_param' => substr(is_string($firstParam) ? $firstParam : (string)$firstParam, 0, 100),
                'last_param' => substr(is_string($lastParam) ? $lastParam : (string)$lastParam, 0, 100)
            ]);
        }

        $updated = updateRow($update_sql, $params);

        // Debug: Check if update was successful and verify status
        if (function_exists('secure_log')) {
            secure_log("[APPOINTMENT UPDATE] Update result", [
                'appointment_id' => $appointment_id,
                'updated' => $updated,
                'update_type' => gettype($updated)
            ]);
        }
        
        if ($updated !== false && $updated > 0) {
            if (function_exists('secure_log')) {
                secure_log("[APPOINTMENT UPDATE] Update successful", [
                    'appointment_id' => $appointment_id,
                    'affected_rows' => $updated
                ]);
            }
            // Verify the status was actually saved correctly - use a small delay to ensure commit
            usleep(100000); // 0.1 second delay
            $verify = getRow("SELECT status, organization_type FROM donor_appointments WHERE id = ?", [$appointment_id]);
            if ($verify) {
                $verifiedStatus = $verify['status'] ?? 'NULL';
                $verifiedOrg = $verify['organization_type'] ?? 'NULL';
                if (function_exists('secure_log')) {
                    secure_log("[APPOINTMENT UPDATE] Verified status in DB", [
                        'appointment_id' => $appointment_id,
                        'verified_status' => substr($verifiedStatus, 0, 50),
                        'org_type' => substr($verifiedOrg, 0, 50)
                    ]);
                }
                
                // If status doesn't match, log a warning
                if ($verifiedStatus !== $status) {
                    if (function_exists('secure_log')) {
                        secure_log("[APPOINTMENT UPDATE] WARNING: Status mismatch", [
                            'appointment_id' => $appointment_id,
                            'expected_status' => substr($status, 0, 50),
                            'got_status' => substr($verifiedStatus, 0, 50)
                        ]);
                    }
                }
            } else {
                if (function_exists('secure_log')) {
                    secure_log("[APPOINTMENT UPDATE] Could not verify - appointment not found after update", ['appointment_id' => $appointment_id]);
                }
            }
        } else {
            if (function_exists('secure_log')) {
                secure_log("[APPOINTMENT UPDATE] Update FAILED or NO ROWS AFFECTED", [
                    'appointment_id' => $appointment_id,
                    'updated_value' => $updated
                ]);
            }
            
            // Check if appointment exists with different organization type
            $checkOrg = getRow("SELECT organization_type, organization_id FROM donor_appointments WHERE id = ?", [$appointment_id]);
            if ($checkOrg) {
                if (function_exists('secure_log')) {
                    secure_log("[APPOINTMENT UPDATE] Appointment exists but with different org", [
                        'appointment_id' => $appointment_id,
                        'org_type' => substr($checkOrg['organization_type'] ?? '', 0, 50),
                        'org_id' => $checkOrg['organization_id'] ?? null
                    ]);
                }
                if ($checkOrg['organization_type'] !== 'redcross') {
                    if (function_exists('secure_log')) {
                        secure_log("[APPOINTMENT UPDATE] Organization type mismatch", [
                            'appointment_id' => $appointment_id,
                            'appointment_org_type' => substr($checkOrg['organization_type'] ?? '', 0, 50)
                        ]);
                    }
                }
            } else {
                if (function_exists('secure_log')) {
                    secure_log("[APPOINTMENT UPDATE] Appointment does not exist", ['appointment_id' => $appointment_id]);
                }
            }
        }

        if ($updated !== false && $updated > 0) {
            // Get donor information for notification
            $get_donor_sql = "SELECT a.donor_id, a.appointment_date, a.appointment_time, a.organization_type, a.organization_id, d.email, d.phone, d.blood_type 
                            FROM donor_appointments a 
                            JOIN donor_users d ON a.donor_id = d.id 
                            WHERE a.id = ?";
            $appointment = getRow($get_donor_sql, [$appointment_id]);

            if ($appointment) {
                // Get donor name for personalized message
                $donorInfo = getRow("SELECT name FROM donor_users WHERE id = ?", [$appointment['donor_id']]);
                $donorName = $donorInfo['name'] ?? '';
                
                // Use professional notification templates
                require_once '../../includes/notification_templates.php';
                
                // Create notification based on status
                $notification_title = '';
                $notification_message = '';
                
                switch($status) {
                    case 'Scheduled':
                        $notification_title = 'Blood Donation Appointment Scheduled';
                        $notification_message = get_notification_message('appointment', $donorName, 'redcross', [
                            'status' => 'Scheduled',
                            'date' => $appointment['appointment_date'],
                            'time' => $appointment['appointment_time'],
                            'location' => $appointment['location'] ?? '',
                            'notes' => $notes ?? ''
                        ]);
                        break;
                    case 'Rejected':
                        $notification_title = 'Donation Appointment Rejected';
                        $notification_message = get_notification_message('rejected', $donorName, 'redcross', [
                            'type' => 'appointment',
                            'reason' => $notes ?? ''
                        ]);
                        break;
                    case 'Completed':
                        $notification_title = 'Donation Completed';
                        $notification_message = get_notification_message('completed', $donorName, 'redcross', [
                            'type' => 'donation',
                            'date' => $appointment['appointment_date']
                        ]);
                        break;
                    case 'No Show':
                        $notification_title = 'Missed Appointment';
                        $notification_message = get_notification_message('rejected', $donorName, 'redcross', [
                            'type' => 'appointment',
                            'reason' => 'No show - appointment was missed. Please reschedule if you would still like to donate.'
                        ]);
                        break;
                }

                if ($notification_title) {
                    // Format message
                    $notification_message = format_notification_message($notification_message);
                    
                    // Insert notification
                    $notification_sql = "INSERT INTO notifications (
                        title, message, user_id, user_role, is_read, created_at
                    ) VALUES (?, ?, ?, 'donor', 0, NOW())";
                    
                    insertRow($notification_sql, [
                        $notification_title,
                        $notification_message,
                        $appointment['donor_id']
                    ]);

                    // Get donor phone number for SMS
                    $donorInfoPhone = getRow("SELECT phone FROM donor_users WHERE id = ?", [$appointment['donor_id']]);
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
                    
                    // SMS notification via SIM800C (status change) if phone number exists (skip enabled check for automated sends)
                    try {
                        require_once '../../includes/sim800c_sms.php';
                        
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C] Appointment status change SMS attempt', [
                                'donor_id' => $appointment['donor_id'],
                                'phone_prefix' => !empty($donorPhone) ? substr($donorPhone, 0, 4) . '****' : 'EMPTY'
                            ]);
                        }
                        
                        // Check if phone number exists
                        if (!empty($donorPhone)) {
                            // Try to send SMS directly - skip enabled check for automated sends
                            // (enabled check may fail due to DB connection issues, but script still works)
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C] Attempting to send appointment status change SMS');
                            }
                            $smsResult = send_sms_sim800c($donorPhone, $notification_message);
                            
                            if ($smsResult['success']) {
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C] Appointment status change SMS sent successfully', [
                                        'phone_prefix' => substr($donorPhone, 0, 4) . '****'
                                    ]);
                                }
                            } else {
                                $smsError = $smsResult['error'] ?? 'Unknown error';
                                if (function_exists('secure_log')) {
                                    secure_log('[SIM800C] Failed to send appointment status change SMS', [
                                        'phone_prefix' => substr($donorPhone, 0, 4) . '****',
                                        'error' => substr($smsError, 0, 500)
                                    ]);
                                }
                            }
                        } else {
                            if (function_exists('secure_log')) {
                                secure_log('[SIM800C] Cannot send appointment status change SMS - donor phone number not found', [
                                    'donor_id' => $appointment['donor_id']
                                ]);
                            }
                        }
                    } catch (Throwable $e) { 
                        if (function_exists('secure_log')) {
                            secure_log('[SIM800C_ERR] Exception in appointment status change SMS', [
                                'error' => substr($e->getMessage(), 0, 500),
                                'trace' => substr($e->getTraceAsString(), 0, 1000)
                            ]);
                        }
                    }
                }

                // If status is Completed, update donor's last donation date
                if ($status === 'Completed') {
                    $update_donor_sql = "UPDATE donor_users
                                        SET last_donation_date = ?, donation_count = donation_count + 1, updated_at = NOW()
                                        WHERE id = ?";
                    updateRow($update_donor_sql, [$appointment['appointment_date'], $appointment['donor_id']]);

                    // Create a donation record using the appointment's organization context
                    $create_donation_sql = "INSERT INTO donations (
                                          donor_id, donation_date, units, blood_type,
                                          status, organization_type, organization_id,
                                          created_at, updated_at
                                        ) VALUES (?, ?, ?, ?, 'Completed', ?, ?, NOW(), NOW())";
                    insertRow($create_donation_sql, [
                        $appointment['donor_id'],
                        $appointment['appointment_date'],
                        $collected_units,
                        $appointment['blood_type'],
                        $appointment['organization_type'],
                        $appointment['organization_id']
                    ]);

                    // Add to blood inventory with 35-day expiry from appointment completion date
                    $inventory_sql = "INSERT INTO blood_inventory (
                                          organization_type, organization_id, blood_type, units, status, source, expiry_date, created_at
                                      ) VALUES (?, ?, ?, ?, 'Available', 'Appointment', DATE_ADD(?, INTERVAL 35 DAY), NOW())";
                    insertRow($inventory_sql, [
                        $appointment['organization_type'],
                        $appointment['organization_id'],
                        $appointment['blood_type'],
                        $collected_units,
                        $appointment['appointment_date']
                    ]);
                }
            }

            if ($status === 'Completed') {
                $_SESSION['appointment_message'] = "Appointment status updated successfully! <a href=\"inventory.php\" class=\"alert-link\">View Inventory</a>";
            } else {
                $_SESSION['appointment_message'] = "Appointment status updated successfully!";
            }
            // Clear old processed actions (older than 10 seconds) to prevent session bloat
            if (isset($_SESSION['processed_actions'])) {
                foreach ($_SESSION['processed_actions'] as $key => $timestamp) {
                    if (time() - $timestamp > 10) {
                        unset($_SESSION['processed_actions'][$key]);
                    }
                }
            }
            // Redirect to refresh the page and show updated status
            header('Location: appointments.php?updated=1');
            exit;
        } else {
            // On error, clear this specific action to allow retry
            if (isset($_SESSION['processed_actions'][$action_key])) {
                unset($_SESSION['processed_actions'][$action_key]);
        }
            $_SESSION['appointment_errors'] = ["Error updating appointment status. Please try again."];
            header('Location: appointments.php?updated=1');
            exit;
    }
    } else {
        // Validation errors - redirect without processing
        $_SESSION['appointment_errors'] = $errors;
        header('Location: appointments.php?updated=1');
        exit;
}
}


// Get donors for dropdown
$donors_query = "SELECT id, CONCAT(name, ' (', blood_type, ')') as donor_name
               FROM donor_users
               ORDER BY name";
$donors_result = executeQuery($donors_query);
if (!$donors_result || !is_array($donors_result)) {
    $donors_result = [];
}

// Get ALL appointments (no pagination, no date restrictions by default)
// This will fetch all Pending and Scheduled appointments regardless of date (past, present, future)
$page = 1; // Keep for compatibility but not used
$records_per_page = 0; // 0 means no limit - fetch ALL records
$offset = 0;

// Filter by date range, status, and donor (optional - only applied if user provides filters)
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';
$status_filter = isset($_GET['status_filter']) ? sanitize($_GET['status_filter']) : '';
$donor_filter = isset($_GET['donor_filter']) ? (int)$_GET['donor_filter'] : 0;

$where_clauses = [];
$params = [];

// Date filtering - allow single date or range
if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "a.appointment_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif (!empty($start_date)) {
    $where_clauses[] = "a.appointment_date >= ?";
    $params[] = $start_date;
} elseif (!empty($end_date)) {
    $where_clauses[] = "a.appointment_date <= ?";
    $params[] = $end_date;
}

// Only allow filtering by Pending or Scheduled status
if (!empty($status_filter) && in_array($status_filter, ['Pending', 'Scheduled'])) {
    $where_clauses[] = "a.status = ?";
    $params[] = $status_filter;
} else {
    // Always show only Pending and Scheduled appointments
    $where_clauses[] = "a.status IN ('Pending', 'Scheduled')";
}

if ($donor_filter > 0) {
    $where_clauses[] = "a.donor_id = ?";
    $params[] = $donor_filter;
}

// Add organization type filter for Red Cross
$where_clauses[] = "a.organization_type = 'redcross'";

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total records (for display purposes only)
$count_query = "SELECT COUNT(*) as total FROM donor_appointments a $where_sql";
$count_result = executeQuery($count_query, $params);
$total_records = 0;

if ($count_result && is_array($count_result) && isset($count_result[0]['total'])) {
    $total_records = $count_result[0]['total'];
}

// Get appointments - fetch ALL appointments for Red Cross (no date restrictions unless filtered)
$appointments_query = "SELECT a.*, a.id AS appointment_id,
                      d.name as donor_name,
                      d.blood_type, d.phone,
                      d.email, d.gender, d.date_of_birth, d.address, d.city,
                      d.last_donation_date, d.donation_count,
                      d.created_at AS donor_registration_date
                      FROM donor_appointments a
                      INNER JOIN donor_users d ON a.donor_id = d.id
                      $where_sql
                      ORDER BY
                          a.appointment_date ASC,
                          a.appointment_time ASC,
                          FIELD(a.status, 'Pending', 'Scheduled')
                      ";
$appointments_result = executeQuery($appointments_query, $params);
if (!$appointments_result) {
    $appointments_result = [];
}

// Attach latest interview (if any) to each appointment row for display
foreach ($appointments_result as &$__appt) {
    $aid = isset($__appt['appointment_id']) ? $__appt['appointment_id'] : (isset($__appt['id']) ? $__appt['id'] : 0);
    if ($aid) {
        // Try to get interview linked to this appointment first
        $__iv = getRow("SELECT * FROM donor_interviews WHERE appointment_id = ? ORDER BY created_at DESC LIMIT 1", [$aid]);
        if (!$__iv) {
            // Fallback: fetch latest draft interview by donor_id where appointment_id IS NULL
            $donorId = isset($__appt['donor_id']) ? $__appt['donor_id'] : null;
            if ($donorId) {
                $__iv = getRow("SELECT * FROM donor_interviews WHERE donor_id = ? AND (appointment_id IS NULL OR appointment_id = 0) ORDER BY created_at DESC LIMIT 1", [$donorId]);
            }
        }

        if ($__iv) {
            $__appt['interview_status'] = $__iv['status'] ?? '';
            $__appt['interview_responses'] = $__iv['responses_json'] ?? '';
            $__appt['interview_deferrals'] = $__iv['deferrals_json'] ?? '';
            $__appt['interview_reasons'] = $__iv['reasons_json'] ?? '';
            $__appt['interview_created_at'] = $__iv['created_at'] ?? '';
        } else {
            $__appt['interview_status'] = '';
            $__appt['interview_responses'] = '';
            $__appt['interview_deferrals'] = '';
            $__appt['interview_reasons'] = '';
            $__appt['interview_created_at'] = '';
        }
    }
}
unset($__appt);

// Include header
include_ONCE 'header.php';
?>

<style>
    /* Appointments Page Specific Styles */
    .appointments-header-section {
        margin-bottom: 2rem;
    }

    .appointments-hero {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        border-radius: 20px;
        padding: 3rem 2.5rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px -12px rgba(220, 20, 60, 0.3);
        margin-bottom: 2rem;
        text-align: center;
    }

    .appointments-hero::before {
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

    .appointments-hero h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        position: relative;
        z-index: 2;
    }

    .appointments-hero p {
        font-size: 1.1rem;
        opacity: 0.9;
        margin-bottom: 2rem;
        position: relative;
        z-index: 2;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
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
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        cursor: pointer;
    }

    .hero-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        color: white;
        transform: translateY(-2px);
    }

        /* Card styles */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .card-header.bg-primary {
            background: linear-gradient(135deg, #DC143C 0%, #B22222 100%) !important;
            border: none;
            padding: 1.5rem 2rem;
            color: white;
        }

        .card-header.bg-light {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
            border-bottom: 1px solid rgba(220, 53, 69, 0.1);
            padding: 1.25rem 2rem;
        }

        .card-header h5 {
            font-weight: 600;
            font-size: 1.2rem;
            letter-spacing: 0.5px;
        }

        .card-body {
            padding: 2rem;
        }

        /* Button styles */
        .btn-primary {
            background: linear-gradient(135deg, #DC143C 0%, #B22222 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #B22222 0%, #8B0000 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            color: #212529;
            transition: all 0.3s ease;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Status badge styles */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
            color: #212529;
        }

        .badge.bg-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%) !important;
            color: white;
        }

        .badge.bg-success {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%) !important;
            color: white;
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
            color: white;
        }

        .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
            color: white;
        }

        /* Form styles */
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: #DC143C;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
            background: white;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
        }

        /* Modal styles */
        .modal-header.bg-primary {
            background-color: #dc3545 !important;
        }

        /* Pagination styles */
        .pagination {
            gap: 0.5rem;
        }

        .page-item .page-link {
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: #DC143C;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 0 0.25rem;
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, #DC143C 0%, #B22222 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .page-link:hover {
            background: linear-gradient(135deg, #DC143C 0%, #B22222 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .dashboard-content {
                margin-left: 0;
                padding: 1rem;
            }

            .dashboard-header {
                padding: 1rem;
            }

            .card-body {
                padding: 1.5rem;
            }
        }

        /* Card and form responsiveness */
        @media (max-width: 767.98px) {
            .card {
                margin-bottom: 1rem;
            }

            .form-group {
                margin-bottom: 1rem;
            }

            .table-responsive {
                margin-bottom: 1rem;
            }

            .col-md-4, .col-md-8 {
                padding: 0 0.5rem;
            }
        }

        /* Table styles */
        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .table thead th {
            background: linear-gradient(135deg, #DC143C 0%, #B22222 100%);
            color: white;
            font-weight: 600;
            letter-spacing: 0.5px;
            border: none;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            border-color: #f8f9fa;
            vertical-align: middle;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(220, 53, 69, 0.05);
            transform: scale(1.01);
        }

        /* Table cell responsiveness */
        @media (max-width: 576px) {
            .table td, .table th {
                min-width: 120px;
                padding: 0.75rem 0.5rem;
            }

            .table td:first-child, .table th:first-child {
                position: sticky;
                left: 0;
                background: white;
                z-index: 1;
            }

            .card-header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .card-header .btn-group,
            .card-header .d-flex {
                width: 100%;
                justify-content: center !important;
            }

            .btn {
                width: 100%;
                margin: 0.25rem 0;
            }

            .dashboard-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        /* Print styles */
        @media print {
            .dashboard-content {
                margin-left: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

        }

            /* Breadcrumb styles */
        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
        }

        .breadcrumb-item a {
            color: #DC143C;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-item.active {
            color: #6c757d;
            font-weight: 600;
        }

        /* Filter and appointments card improvements */
        .filter-card,
        .appointments-card {
            width: 100%;
            margin-bottom: 2rem;
        }

        .filter-card form .col,
        .filter-card form .col-auto {
            min-width: 180px;
        }

        .filter-form {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            padding: 1.5rem;
        }

        /* Alert styles */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* Modal enhancements */
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.1);
            border-radius: 15px 15px 0 0;
        }

        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.1);
            border-radius: 0 0 15px 15px;
        }
    /* Enhanced Table Styling */
    .appointments-table-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(220, 20, 60, 0.1);
        overflow: hidden;
        margin-bottom: 2rem;
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

    /* Status Badges */
    .status-badge {
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
    }
    
    .status-badge.clickable-status {
        cursor: pointer;
        user-select: none;
    }
    
    .status-badge.clickable-status:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        opacity: 0.9;
    }

    .status-pending { background: #fff3cd; color: #856404; }
    .status-approved { background: #d1edff; color: #0c5460; }
    .status-fulfilled { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }

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
</style>

<!-- Hero Section -->
<div class="appointments-header-section">
    <div class="appointments-hero">
        <h1><i class="bi bi-calendar-check me-3"></i>Appointment Management</h1>
        <p>Schedule and manage blood donation appointments efficiently. Review donor appointments, approve or reschedule requests, and track appointment completion.</p>
        <div class="hero-actions">
            <button class="hero-btn" onclick="printAppointmentsReport()">
                <i class="bi bi-printer me-2"></i>Print Report
            </button>
            
        </div>
    </div>
</div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger feedback-message">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success feedback-message">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <div class="appointments-table-card">
        <div class="table-header">
            <h3><i class="bi bi-funnel me-2"></i>Filter Appointments</h3>
            <?php 
            $activeFiltersCount = 0;
            if (!empty($start_date)) $activeFiltersCount++;
            if (!empty($end_date)) $activeFiltersCount++;
            if (!empty($status_filter)) $activeFiltersCount++;
            if ($donor_filter > 0) $activeFiltersCount++;
            if ($activeFiltersCount > 0): ?>
                <span class="badge bg-light text-dark ms-2">
                    <i class="bi bi-funnel-fill me-1"></i><?php echo $activeFiltersCount; ?> Active Filter<?php echo $activeFiltersCount > 1 ? 's' : ''; ?>
                </span>
            <?php endif; ?>
        </div>
        <div style="padding: 2rem;">
            <form method="get" action="" id="filterForm" class="row g-3 align-items-end" onsubmit="return validateFilterForm()">
                <!-- Preserve page parameter if exists -->
                <?php if (isset($_GET['page']) && $_GET['page'] > 1): ?>
                    <input type="hidden" name="page" value="1">
                <?php endif; ?>
                
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date:</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date:</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <small class="form-text text-danger" id="dateError" style="display: none;"></small>
                </div>
                <div class="col-md-2">
                    <label for="status_filter" class="form-label">Status:</label>
                    <select class="form-select" id="status_filter" name="status_filter">
                        <option value="">All (Pending & Scheduled)</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Scheduled" <?php echo $status_filter === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="donor_filter" class="form-label">Donor:</label>
                    <select class="form-select" id="donor_filter" name="donor_filter">
                        <option value="0">All Donors</option>
                        <?php foreach ($donors_result as $donor): ?>
                            <option value="<?php echo $donor['id']; ?>" <?php echo ($donor_filter == $donor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($donor['donor_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <?php if ($activeFiltersCount > 0): ?>
                        <button type="button" class="btn btn-secondary" onclick="resetFilterForm()" title="Clear all filters">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Active Filters Display -->
            <?php if ($activeFiltersCount > 0): ?>
                <div class="mt-3">
                    <small class="text-muted">Active Filters:</small>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php if (!empty($start_date)): ?>
                            <span class="badge bg-primary">
                                From: <?php echo date('M d, Y', strtotime($start_date)); ?>
                                <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;" onclick="clearFilter('start_date')" aria-label="Remove"></button>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($end_date)): ?>
                            <span class="badge bg-primary">
                                To: <?php echo date('M d, Y', strtotime($end_date)); ?>
                                <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;" onclick="clearFilter('end_date')" aria-label="Remove"></button>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($status_filter)): ?>
                            <span class="badge bg-info">
                                Status: <?php echo htmlspecialchars($status_filter); ?>
                                <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;" onclick="clearFilter('status_filter')" aria-label="Remove"></button>
                            </span>
                        <?php endif; ?>
                        <?php if ($donor_filter > 0): 
                            $selectedDonor = null;
                            foreach ($donors_result as $donor) {
                                if ($donor['id'] == $donor_filter) {
                                    $selectedDonor = $donor;
                                    break;
                                }
                            }
                            if ($selectedDonor):
                        ?>
                            <span class="badge bg-success">
                                Donor: <?php echo htmlspecialchars($selectedDonor['donor_name']); ?>
                                <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;" onclick="clearFilter('donor_filter')" aria-label="Remove"></button>
                            </span>
                        <?php endif; endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="appointments-table-card">
        <div class="table-header">
            <h3><i class="bi bi-calendar-check me-2"></i>Appointment Records</h3>
        </div>
        <div class="table-responsive">
            <?php if (!empty($appointments_result)): ?>
                <table class="enhanced-table table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Donor</th>
                                <th>Date & Time</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments_result as $appointment): ?>
                                <tr data-appt-id="<?php echo isset($appointment['appointment_id']) ? (int)$appointment['appointment_id'] : (int)$appointment['id']; ?>">
                                    <td><?php echo isset($appointment['appointment_id']) ? (int)$appointment['appointment_id'] : (int)$appointment['id']; ?></td>
                                    <td>
                                        <?php echo $appointment['donor_name']; ?>
                                        <div class="small text-muted"><?php echo $appointment['phone']; ?></div>
                                    </td>
                                    
                                    <td>
                                        <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                        <div class="small text-muted"><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></div>
                                    </td>
                                    <td><?php echo $appointment['location']; ?></td>
                                    <td>
                                        <?php
                                        // Get status and handle NULL/empty values
                                        $status = !empty($appointment['status']) ? $appointment['status'] : 'Pending';
                                        // Normalize status - remove any extra whitespace and handle all variants
                                        $status = trim($status);
                                        // If status is still empty after trim, default to Pending
                                        if (empty($status)) {
                                            $status = 'Pending';
                                            // Update the database to fix NULL/empty status
                                            $fix_status_sql = "UPDATE donor_appointments SET status = 'Pending' WHERE id = ? AND (status IS NULL OR status = '')";
                                            updateRow($fix_status_sql, [isset($appointment['appointment_id']) ? $appointment['appointment_id'] : $appointment['id']]);
                                        }
                                        $statusLower = strtolower($status);
                                        
                                        // Handle all possible status variations
                                        $statusClass = '';
                                        $displayStatus = '';
                                        
                                        // Use strict comparison and handle all variations
                                        if (in_array($statusLower, ['pending'])) {
                                                $statusClass = 'status-pending';
                                                $displayStatus = 'Pending';
                                        } elseif (in_array($statusLower, ['scheduled', 'schedule', 'approved'])) {
                                                $statusClass = 'status-approved';
                                                $displayStatus = 'Scheduled';
                                        } elseif (in_array($statusLower, ['completed', 'complete'])) {
                                                $statusClass = 'status-fulfilled';
                                                $displayStatus = 'Completed';
                                        } elseif (in_array($statusLower, ['rejected', 'reject'])) {
                                                $statusClass = 'status-rejected';
                                                $displayStatus = 'Rejected';
                                        } elseif (in_array($statusLower, ['no show', 'no_show', 'noshow'])) {
                                                $statusClass = 'status-rejected';
                                                $displayStatus = 'No Show';
                                        } else {
                                            // Fallback: try to display whatever we got
                                                $statusClass = 'status-pending';
                                            $displayStatus = ucwords(strtolower($status));
                                            // Debug: log unexpected status values with hex dump
                                            $aptId = $appointment['appointment_id'] ?? $appointment['id'] ?? 'unknown';
                                            if (function_exists('secure_log')) {
                                                secure_log('[APPOINTMENT STATUS DEBUG] Unexpected status', [
                                                    'appointment_id' => $aptId,
                                                    'status' => substr($status, 0, 50),
                                                    'status_lowercase' => substr($statusLower, 0, 50),
                                                    'status_length' => strlen($status)
                                                ]);
                                            }
                                        }
                                        ?>
                                        <span class="status-badge <?php echo htmlspecialchars($statusClass); ?> clickable-status" 
                                              onclick="filterByStatus('<?php echo htmlspecialchars($displayStatus); ?>')" 
                                              style="cursor: pointer;" 
                                              title="Click to filter by <?php echo htmlspecialchars($displayStatus); ?> status">
                                            <?php echo htmlspecialchars($displayStatus); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="action-btn btn-view" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#donorDetailsModal"
                                                    data-id="<?php echo $appointment['id']; ?>"
                                                    data-donor-id="<?php echo $appointment['donor_id']; ?>"
                                                    data-donor-name="<?php echo htmlspecialchars($appointment['donor_name']); ?>"
                                                    data-blood-type="<?php echo htmlspecialchars($appointment['blood_type']); ?>"
                                                    data-gender="<?php echo htmlspecialchars($appointment['gender']); ?>"
                                                    <?php 
                                                    // Try to decrypt phone/email/address, but use original if decryption fails (plain text)
                                                    $__aph = null;
                                                    if (isset($appointment['phone']) && !empty($appointment['phone'])) {
                                                        $__aph = decrypt_value($appointment['phone']);
                                                        if (empty($__aph)) {
                                                            $__aph = $appointment['phone']; // Use original if decryption fails
                                                        }
                                                    }
                                                    $__aem = null;
                                                    if (isset($appointment['email']) && !empty($appointment['email'])) {
                                                        $__aem = decrypt_value($appointment['email']);
                                                        if (empty($__aem)) {
                                                            $__aem = $appointment['email']; // Use original if decryption fails
                                                        }
                                                    }
                                                    $__aad = null;
                                                    if (isset($appointment['address']) && !empty($appointment['address'])) {
                                                        $__aad = decrypt_value($appointment['address']);
                                                        if (empty($__aad)) {
                                                            $__aad = $appointment['address']; // Use original if decryption fails
                                                        }
                                                    }
                                                    ?>
                                                    data-phone="<?php echo htmlspecialchars($__aph !== null ? $__aph : ($appointment['phone'] ?? '')); ?>"
                                                    data-email="<?php echo htmlspecialchars($__aem !== null ? $__aem : ($appointment['email'] ?? '')); ?>"
                                                    data-address="<?php echo htmlspecialchars($__aad !== null ? $__aad : ($appointment['address'] ?? '')); ?>"
                                                    data-city="<?php echo htmlspecialchars($appointment['city']); ?>"
                                                    data-registered="<?php echo $appointment['donor_registration_date'] ? date('M d, Y', strtotime($appointment['donor_registration_date'])) : ''; ?>"
                                                    data-donation-count="<?php echo (int)($appointment['donation_count'] ?? 0); ?>"
                                                    data-last-donation="<?php echo !empty($appointment['last_donation_date']) ? date('M d, Y', strtotime($appointment['last_donation_date'])) : 'Never'; ?>"
                                                    data-eligible="1"
                                                    data-appt-status="<?php echo htmlspecialchars($appointment['status']); ?>"
                                                    data-appt-date="<?php echo $appointment['appointment_date'] ? date('M d, Y', strtotime($appointment['appointment_date'])) : ''; ?>"
                                                    data-appt-time="<?php echo $appointment['appointment_time'] ? date('h:i A', strtotime($appointment['appointment_time'])) : ''; ?>"
                                                    data-appt-date-raw="<?php echo htmlspecialchars($appointment['appointment_date']); ?>"
                                                    data-appt-time-raw="<?php echo htmlspecialchars($appointment['appointment_time']); ?>"
                                                    data-location="<?php echo htmlspecialchars($appointment['location'] ?? ''); ?>"
                                                    data-notes="<?php echo htmlspecialchars($appointment['notes'] ?? ''); ?>"
                                                    data-interview-status="<?php echo htmlspecialchars($appointment['interview_status'] ?? ''); ?>"
                                                    data-interview-responses="<?php echo htmlspecialchars($appointment['interview_responses'] ?? ''); ?>"
                                                    data-interview-created="<?php echo htmlspecialchars($appointment['interview_created_at'] ?? ''); ?>"
                                                    >
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if ($appointment['status'] === 'Pending'): ?>
                                                <button type="button" class="action-btn btn-approve"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#updateStatusModal"
                                                        data-id="<?php echo isset($appointment['appointment_id']) ? (int)$appointment['appointment_id'] : (int)$appointment['id']; ?>"
                                                        data-donor="<?php echo $appointment['donor_name']; ?>"
                                                        data-status="Scheduled"
                                                        data-current-status="<?php echo $appointment['status']; ?>"
                                                        data-appt-date-raw="<?php echo htmlspecialchars($appointment['appointment_date']); ?>"
                                                        data-appt-time-raw="<?php echo htmlspecialchars($appointment['appointment_time']); ?>"
                                                        data-location="<?php echo htmlspecialchars($appointment['location'] ?? ''); ?>"
                                                        onclick="(function(id){var f=document.querySelector('#updateStatusModal form');var hid=document.getElementById('appointment_id');if(hid){hid.value=id;} if(f){try{var u=new URL(window.location.origin+window.location.pathname);u.searchParams.set('aid', id);f.setAttribute('action', u.pathname+u.search);}catch(e){}}})(<?php echo isset($appointment['appointment_id']) ? (int)$appointment['appointment_id'] : (int)$appointment['id']; ?>);">
                                                    <i class="bi bi-calendar-check"></i>
                                                </button>
                                                <button type="button" class="action-btn btn-reject"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#updateStatusModal"
                                                        data-id="<?php echo isset($appointment['appointment_id']) ? (int)$appointment['appointment_id'] : (int)$appointment['id']; ?>"
                                                        data-donor="<?php echo $appointment['donor_name']; ?>"
                                                        data-status="Rejected"
                                                        data-current-status="<?php echo $appointment['status']; ?>"
                                                           data-appt-date-raw="<?php echo htmlspecialchars($appointment['appointment_date']); ?>"
                                                           data-appt-time-raw="<?php echo htmlspecialchars($appointment['appointment_time']); ?>"
                                                           data-location="<?php echo htmlspecialchars($appointment['location'] ?? ''); ?>"
                                                        onclick="(function(id){var f=document.querySelector('#updateStatusModal form');var hid=document.getElementById('appointment_id');if(hid){hid.value=id;} if(f){try{var u=new URL(window.location.origin+window.location.pathname);u.searchParams.set('aid', id);f.setAttribute('action', u.pathname+u.search);}catch(e){}}})(<?php echo isset($appointment['appointment_id']) ? (int)$appointment['appointment_id'] : (int)$appointment['id']; ?>);">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            <?php elseif ($appointment['status'] === 'Scheduled'): ?>
                                                <button type="button" class="action-btn btn-fulfill"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#updateStatusModal"
                                                        data-id="<?php echo isset($appointment['appointment_id']) ? (int)$appointment['appointment_id'] : (int)$appointment['id']; ?>"
                                                        data-donor="<?php echo $appointment['donor_name']; ?>"
                                                        data-status="Completed"
                                                        data-current-status="<?php echo $appointment['status']; ?>"
                                                        data-appt-date-raw="<?php echo htmlspecialchars($appointment['appointment_date']); ?>"
                                                        data-appt-time-raw="<?php echo htmlspecialchars($appointment['appointment_time']); ?>"
                                                        data-location="<?php echo htmlspecialchars($appointment['location'] ?? ''); ?>"
                                                        onclick="(function(id){var f=document.querySelector('#updateStatusModal form');var hid=document.getElementById('appointment_id');if(hid){hid.value=id;} if(f){try{var u=new URL(window.location.origin+window.location.pathname);u.searchParams.set('aid', id);f.setAttribute('action', u.pathname+u.search);}catch(e){}}})(<?php echo isset($appointment['appointment_id']) ? (int)$appointment['appointment_id'] : (int)$appointment['id']; ?>);">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                                <button type="button" class="action-btn btn-reject"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#updateStatusModal"
                                                        data-id="<?php echo isset($appointment['appointment_id']) ? (int)$appointment['appointment_id'] : (int)$appointment['id']; ?>"
                                                        data-donor="<?php echo $appointment['donor_name']; ?>"
                                                        data-status="No Show"
                                                        data-current-status="<?php echo $appointment['status']; ?>"
                                                        data-appt-date-raw="<?php echo htmlspecialchars($appointment['appointment_date']); ?>"
                                                        data-appt-time-raw="<?php echo htmlspecialchars($appointment['appointment_time']); ?>"
                                                        data-location="<?php echo htmlspecialchars($appointment['location'] ?? ''); ?>"
                                                        onclick="(function(id){var f=document.querySelector('#updateStatusModal form');var hid=document.getElementById('appointment_id');if(hid){hid.value=id;} if(f){try{var u=new URL(window.location.origin+window.location.pathname);u.searchParams.set('aid', id);f.setAttribute('action', u.pathname+u.search);}catch(e){}}})(<?php echo isset($appointment['appointment_id']) ? (int)$appointment['appointment_id'] : (int)$appointment['id']; ?>);">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">No actions available</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Display total count -->
                <?php if ($total_records > 0): ?>
                    
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                    <p class="mb-0 mt-3 text-muted">No appointments found.</p>
                    <small class="text-muted">Appointments will appear here when donors schedule appointments.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

<script>
function refreshAppointments() {
    location.reload();
}

// Filter form validation
function validateFilterForm() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const dateError = document.getElementById('dateError');
    
    // Reset error message
    if (dateError) {
        dateError.style.display = 'none';
        dateError.textContent = '';
    }
    
    // If both dates are provided, validate that end date is after start date
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        if (end < start) {
            if (dateError) {
                dateError.textContent = 'End date must be after start date.';
                dateError.style.display = 'block';
            }
            return false;
        }
    }
    
    return true;
}

// Reset filter form
function resetFilterForm() {
    // Clear all form fields
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    document.getElementById('status_filter').value = '';
    document.getElementById('donor_filter').value = '0';
    
    // Hide any error messages
    const dateError = document.getElementById('dateError');
    if (dateError) {
        dateError.style.display = 'none';
    }
    
    // Redirect to page without filter parameters
    const url = new URL(window.location.href);
    url.searchParams.delete('start_date');
    url.searchParams.delete('end_date');
    url.searchParams.delete('status_filter');
    url.searchParams.delete('donor_filter');
    url.searchParams.delete('page'); // Reset to page 1
    window.location.href = url.toString();
}

// Clear individual filter
function clearFilter(filterName) {
    const form = document.getElementById('filterForm');
    const input = form.querySelector('[name="' + filterName + '"]');
    if (input) {
        if (input.tagName === 'SELECT') {
            input.value = filterName === 'donor_filter' ? '0' : '';
        } else {
            input.value = '';
        }
    }
    
    // Submit form to apply the change
    form.submit();
}

// Filter by status when clicking on status badge
function filterByStatus(status) {
    const statusFilter = document.getElementById('status_filter');
    if (statusFilter) {
        // Set the status filter value
        statusFilter.value = status;
        
        // Clear page parameter to go to page 1
        const form = document.getElementById('filterForm');
        const pageInput = form.querySelector('input[name="page"]');
        if (pageInput) {
            pageInput.remove();
        }
        
        // Submit the form to apply the filter
        form.submit();
    }
}

// Real-time date validation and auto-submit on change
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const dateError = document.getElementById('dateError');
    const statusFilter = document.getElementById('status_filter');
    const donorFilter = document.getElementById('donor_filter');
    const filterForm = document.getElementById('filterForm');
    
    // Auto-submit on filter change (optional - can be disabled if too aggressive)
    let autoSubmitTimeout;
    function scheduleAutoSubmit() {
        clearTimeout(autoSubmitTimeout);
        // Uncomment the line below if you want auto-submit on change
        // autoSubmitTimeout = setTimeout(() => filterForm.submit(), 1000);
    }
    
    if (startDateInput && endDateInput) {
        // Validate when dates change
        function validateDates() {
            const startDate = startDateInput.value;
            const endDate = endDateInput.value;
            
            if (dateError) {
                dateError.style.display = 'none';
                dateError.textContent = '';
            }
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (end < start) {
                    if (dateError) {
                        dateError.textContent = 'End date must be after or equal to start date.';
                        dateError.style.display = 'block';
                    }
                    endDateInput.setCustomValidity('End date must be after or equal to start date');
                } else {
                    endDateInput.setCustomValidity('');
                }
            } else {
                if (endDateInput) {
                    endDateInput.setCustomValidity('');
                }
            }
            
            scheduleAutoSubmit();
        }
        
        startDateInput.addEventListener('change', validateDates);
        endDateInput.addEventListener('change', validateDates);
    }
    
    // Optional: Auto-submit on status or donor change
    if (statusFilter) {
        statusFilter.addEventListener('change', scheduleAutoSubmit);
    }
    if (donorFilter) {
        donorFilter.addEventListener('change', scheduleAutoSubmit);
    }
});

// Update the action buttons to use the existing modal functionality
function viewDetails(appointmentId) {
    // Find the existing view button and trigger its click
    const viewBtn = document.querySelector(`button[data-id="${appointmentId}"][data-bs-target="#donorDetailsModal"]`);
    if (viewBtn) {
        viewBtn.click();
    }
}

function approveAppointment(appointmentId) {
    // Find the existing approve button and trigger its click
    const approveBtn = document.querySelector(`button[data-id="${appointmentId}"][data-status="Approved"]`);
    if (approveBtn) {
        approveBtn.click();
    }
}

function rejectAppointment(appointmentId) {
    // Find the existing reject button and trigger its click
    const rejectBtn = document.querySelector(`button[data-id="${appointmentId}"][data-status="Rejected"]`);
    if (rejectBtn) {
        rejectBtn.click();
    }
}

function completeAppointment(appointmentId) {
    // Find the existing complete button and trigger its click
    const completeBtn = document.querySelector(`button[data-id="${appointmentId}"][data-status="Completed"]`);
    if (completeBtn) {
        completeBtn.click();
    }
}
</script>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="updateStatusModalLabel">Update Appointment Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                    <input type="hidden" id="appointment_id" name="appointment_id">

                    <div class="mb-3">
                        <label class="form-label" for="modal_donor_name">Donor</label>
                        <input type="text" class="form-control" id="modal_donor_name" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <!-- Options will be populated by JavaScript -->
                        </select>
                    </div>

                    <div class="mb-3" id="units_collected_group" style="display:none;">
                        <label for="collected_units" class="form-label">Units Collected</label>
                        <input type="number" step="1" min="1" max="2" value="1" class="form-control" id="collected_units" name="collected_units">
                        <div class="form-text">Typical whole blood donation yields 1 unit. Set to 2 only if applicable.</div>
                    </div>

                    <div class="mb-3" id="schedule_fields" style="display:none;">
                        <label for="scheduled_date" class="form-label">Scheduled Date</label>
                        <input type="date" class="form-control" id="scheduled_date" name="appointment_date">
                        <label for="scheduled_time" class="form-label mt-2">Scheduled Time</label>
                        <input type="time" class="form-control" id="scheduled_time" name="appointment_time">
                        <label for="scheduled_location" class="form-label mt-2">Location</label>
                        <input type="text" class="form-control" id="scheduled_location" name="location" placeholder="Enter venue or address" data-titlecase="1">
                    </div>

                    <div class="mb-3">
                        <label for="status_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="status_notes" name="status_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_appointment_status" class="btn btn-danger">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Donor Details Modal -->
<div class="modal fade" id="donorDetailsModal" tabindex="-1" aria-labelledby="donorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="donorDetailsModalLabel">Donor Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2">Personal Information</h6>
                        <div id="personalInfo">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Donation History -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2">Donation History</h6>
                        <div id="donationHistory">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Interview Details -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2">Interview Details</h6>
                        <div id="interviewDetails">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Appointment Schedule -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2">Appointment Schedule</h6>
                        <div id="appointmentSchedule">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="donorModalApproveBtn" class="btn btn-danger d-none">Approve</button>
                    <button type="button" id="donorModalRejectBtn" class="btn btn-danger d-none">Reject</button>
                </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Include print utilities -->
<script src="../../assets/js/print-utils.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle update status modal
        const updateStatusModal = document.getElementById('updateStatusModal');
        if (updateStatusModal) {
            // Pre-bind click handlers on all action buttons to ensure ID is captured early
            const actionButtons = document.querySelectorAll('button[data-bs-target="#updateStatusModal"][data-id]');
            actionButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id') || '';
                    const hiddenIdInput = document.getElementById('appointment_id');
                    if (hiddenIdInput) {
                        hiddenIdInput.value = id;
                    }
                    updateStatusModal.dataset.appointmentId = id;
                });
            });

            // Helper: open the Update Status modal with a consistent payload
            window.openUpdateStatusModal = function(opts) {
                try {
                    opts = opts || {};
                    const modalEl = document.getElementById('updateStatusModal');
                    const hiddenIdInput = document.getElementById('appointment_id');
                    const modalForm = modalEl.querySelector('form');
                    const donorInput = document.getElementById('modal_donor_name');
                    const statusSelect = document.getElementById('status');
                    const sd = document.getElementById('scheduled_date');
                    const st = document.getElementById('scheduled_time');
                    const sl = document.getElementById('scheduled_location');

                    const id = opts.appointment_id || opts.id || modalEl.dataset.appointmentId || '';
                    hiddenIdInput.value = id;
                    modalEl.dataset.appointmentId = id;

                    if (modalForm) {
                        const baseAction = window.location.pathname;
                        const url = new URL(window.location.origin + baseAction);
                        if (id) { url.searchParams.set('aid', id); }
                        modalForm.setAttribute('action', url.pathname + url.search);
                    }

                    if (donorInput) donorInput.value = opts.donor_name || opts.donor || '';

                    // Populate status options and select the intended action
                    const intended = opts.status || '';
                    if (statusSelect) {
                        if ((opts.current_status || '').toString() === 'Pending') {
                            statusSelect.innerHTML = '<option value="Scheduled">Scheduled</option><option value="Rejected">Reject</option>';
                        } else if ((opts.current_status || '').toString() === 'Scheduled') {
                            statusSelect.innerHTML = '<option value="Completed">Completed</option><option value="No Show">No Show</option>';
                        } else {
                            // Generic fallback
                            statusSelect.innerHTML = '<option value="Scheduled">Scheduled</option><option value="Rejected">Reject</option><option value="Completed">Completed</option>';
                        }
                        if (intended) statusSelect.value = intended;
                    }

                    if (sd) sd.value = opts.appointment_date || opts.appt_date || opts.appt_date_raw || '';
                    if (st) st.value = opts.appointment_time || opts.appt_time || opts.appt_time_raw || '';
                    if (sl) sl.value = opts.location || '';

                    // Toggle schedule/units groups based on selected value
                    function _toggle() {
                        const val = (statusSelect && statusSelect.value) ? statusSelect.value : '';
                        const scheduleGroup = document.getElementById('schedule_fields');
                        const unitsGroup = document.getElementById('units_collected_group');
                        if (scheduleGroup) scheduleGroup.style.display = (val === 'Scheduled') ? '' : 'none';
                        if (unitsGroup) unitsGroup.style.display = (val === 'Completed') ? '' : 'none';
                    }
                    _toggle();
                    if (statusSelect) {
                        // remove previous change listeners by cloning
                        const newSelect = statusSelect.cloneNode(true);
                        statusSelect.parentNode.replaceChild(newSelect, statusSelect);
                        newSelect.addEventListener('change', _toggle);
                    }

                    const bsModal = new bootstrap.Modal(modalEl);
                    bsModal.show();
                } catch (e) {
                    console.error('openUpdateStatusModal error', e);
                }
            };
            updateStatusModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;

                // If modal is shown programmatically (no relatedTarget), don't overwrite
                // fields that were already prefilled by `openUpdateStatusModal`.
                if (!button) {
                    return;
                }

                // Get data attributes from the button
                let id = button.getAttribute('data-id');
                if (!id) {
                    const row = button.closest('tr');
                    if (row && row.dataset.apptId) {
                        id = row.dataset.apptId;
                    }
                }
                // Final fallback: parse the first cell text as ID
                if (!id) {
                    const row = button.closest('tr');
                    if (row) {
                        const firstCell = row.querySelector('td');
                        if (firstCell) {
                            const maybeId = parseInt(firstCell.textContent.trim(), 10);
                            if (!isNaN(maybeId) && maybeId > 0) {
                                id = String(maybeId);
                            }
                        }
                    }
                }
                const donor = button.getAttribute('data-donor');
                // If donor name wasn't provided on the button, try sensible fallbacks:
                //  - find any element with the same appointment id that has data-donor or data-donor-name
                //  - if we can find a view button with data-donor-id, call get_donor_details.php to fetch the name
                let donorName = donor;
                if (!donorName) {
                    try {
                        const aid = id || button.dataset.apptId || updateStatusModal.dataset.appointmentId || '';
                        if (aid) {
                            // look for a view button or any control that carries donor info
                            let candidate = document.querySelector(`button[data-id="${aid}"][data-donor]`) || document.querySelector(`button[data-id="${aid}"][data-donor-name]`) || document.querySelector(`button[data-appt-id="${aid}"]`);
                            if (!candidate) {
                                // also try the donorDetails view button which has data-bs-target
                                candidate = document.querySelector(`button[data-bs-target="#donorDetailsModal"][data-id="${aid}"]`);
                            }
                            if (candidate) {
                                donorName = candidate.getAttribute('data-donor') || candidate.getAttribute('data-donor-name') || candidate.dataset.donorName || candidate.dataset.donor || '';
                            }

                            // If still missing, try to fetch via donor_id if available on a view button
                            if (!donorName) {
                                const viewBtn = document.querySelector(`button[data-bs-target="#donorDetailsModal"][data-id="${aid}"]`);
                                const donorIdAttr = viewBtn ? (viewBtn.getAttribute('data-donor-id') || viewBtn.dataset.donorId) : null;
                                if (donorIdAttr) {
                                    // fetch name asynchronously and fill in when ready
                                    fetch('get_donor_details.php?donor_id=' + encodeURIComponent(donorIdAttr), {credentials: 'same-origin'})
                                        .then(r => r.ok ? r.json() : Promise.reject('fetch failed'))
                                        .then(j => {
                                            if (j && !j.error) {
                                                const nameVal = (j.first_name || '') + (j.last_name ? ' ' + j.last_name : '');
                                                const dn = document.getElementById('modal_donor_name');
                                                if (dn) dn.value = nameVal;
                                            }
                                        }).catch(e => console.warn('Could not fetch donor name fallback', e));
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('donor name fallback error', e);
                    }
                }
                const status = button.getAttribute('data-status'); // intended action
                let currentStatus = button.getAttribute('data-current-status'); // actual current status

                // Set values in the form
                const hiddenIdInput = document.getElementById('appointment_id');
                if (hiddenIdInput) {
                    hiddenIdInput.value = id || '';
                }
                // Keep an additional copy on the modal element for fallback
                updateStatusModal.dataset.appointmentId = id || '';
                document.getElementById('modal_donor_name').value = donorName || '';
                // Also append the ID to the form action as a fallback query param
                const modalForm = updateStatusModal.querySelector('form');
                if (modalForm) {
                    const baseAction = window.location.pathname;
                    const url = new URL(window.location.origin + baseAction);
                    if (id) { url.searchParams.set('aid', id); }
                    modalForm.setAttribute('action', url.pathname + url.search);
                }
                
                // Update status dropdown based on current status
                const statusSelect = document.getElementById('status');
                statusSelect.innerHTML = ''; // Clear existing options
                let options = '';
                // Normalize some variants to unify old/new rows
                if (currentStatus === 'Approved' || currentStatus === 'approved') { currentStatus = 'Scheduled'; }
                if (currentStatus === 'pending' || currentStatus === 'PENDING') { currentStatus = 'Pending'; }
                if (currentStatus === 'Pending') {
                    options = `
                        <option value="Scheduled">Scheduled</option>
                        <option value="Rejected">Reject</option>
                    `;
                } else if (currentStatus === 'Scheduled') {
                    options = `
                        <option value="Completed">Completed</option>
                        <option value="No Show">No Show</option>
                    `;
                }
                statusSelect.innerHTML = options;
                // Pre-select the intended action if available
                if (status) {
                    statusSelect.value = status;
                }

                // Show/hide schedule fields depending on chosen status
                function toggleScheduleFields() {
                    const val = (statusSelect.value || '').toString();
                    const scheduleGroup = document.getElementById('schedule_fields');
                    const unitsGroup = document.getElementById('units_collected_group');
                    if (scheduleGroup) scheduleGroup.style.display = (val === 'Scheduled') ? '' : 'none';
                    if (unitsGroup) unitsGroup.style.display = (val === 'Completed') ? '' : 'none';
                }
                // initial toggle
                toggleScheduleFields();
                statusSelect.addEventListener('change', toggleScheduleFields);
                // Prefill schedule inputs from button data attributes or row dataset
                try {
                    const sd = document.getElementById('scheduled_date');
                    const st = document.getElementById('scheduled_time');
                    const sl = document.getElementById('scheduled_location');
                    const rawDate = button.getAttribute('data-appt-date-raw') || '';
                    const rawTime = button.getAttribute('data-appt-time-raw') || '';
                    const loc = button.getAttribute('data-location') || button.getAttribute('data-location') || '';
                    if (sd) sd.value = rawDate || (button.dataset.apptDateRaw || '');
                    if (st) st.value = rawTime || (button.dataset.apptTimeRaw || '');
                    if (sl) sl.value = loc || (button.dataset.location || '');
                } catch (e) { /* ignore */ }

                // Update modal title and notes label based on intended action
                const modalTitle = updateStatusModal.querySelector('.modal-title');
                const notesLabel = updateStatusModal.querySelector('label[for="status_notes"]');
                const notesInput = document.getElementById('status_notes');

                switch(status) {
                    case 'Approved':
                        modalTitle.textContent = 'Schedule Donation Appointment';
                        notesLabel.textContent = 'Additional Instructions (Optional)';
                        notesInput.placeholder = 'Add any special instructions for the donor';
                        notesInput.required = false;
                        document.getElementById('units_collected_group').style.display = 'none';
                        break;
                    case 'Rejected':
                        modalTitle.textContent = 'Reject Donation Appointment';
                        notesLabel.textContent = 'Reason for Rejection';
                        notesInput.placeholder = 'Please provide a reason for rejecting this appointment';
                        notesInput.required = true;
                        document.getElementById('units_collected_group').style.display = 'none';
                        break;
                    case 'Completed':
                        modalTitle.textContent = 'Complete Donation';
                        notesLabel.textContent = 'Additional Notes (Optional)';
                        notesInput.placeholder = 'Add any notes about the donation';
                        notesInput.required = false;
                        document.getElementById('units_collected_group').style.display = '';
                        break;
                    case 'No Show':
                        modalTitle.textContent = 'Mark as No Show';
                        notesLabel.textContent = 'Notes (Optional)';
                        notesInput.placeholder = 'Add any notes about the missed appointment';
                        notesInput.required = false;
                        document.getElementById('units_collected_group').style.display = 'none';
                        break;
                }
            });

            // As a final safety, on shown ensure the ID is present
            updateStatusModal.addEventListener('shown.bs.modal', function() {
                const hiddenIdInput = document.getElementById('appointment_id');
                if (!hiddenIdInput.value) {
                    const fallbackId = updateStatusModal.dataset.appointmentId || '';
                    if (fallbackId) {
                        hiddenIdInput.value = fallbackId;
                    }
                }

                // Ensure this modal appears above any other open modal (e.g., donorDetailsModal)
                try {
                    // Bring the modal to the front by raising its z-index and its backdrop
                    updateStatusModal.style.zIndex = 20050;
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops && backdrops.length) {
                        const lastBackdrop = backdrops[backdrops.length - 1];
                        lastBackdrop.style.zIndex = 20040;
                    }
                } catch (e) {
                    console.warn('Could not adjust modal stacking:', e);
                }
            });

            // Ensure the hidden appointment_id is present on submit
            const modalForm = updateStatusModal.querySelector('form');
            if (modalForm) {
                modalForm.addEventListener('submit', function(e) {
                    const hiddenId = document.getElementById('appointment_id');
                    if (!hiddenId.value) {
                        const fallbackId = updateStatusModal.dataset.appointmentId || '';
                        if (fallbackId) {
                            hiddenId.value = fallbackId;
                        } else {
                            e.preventDefault();
                            alert('No appointment selected. Please close the modal and click an action button again.');
                            return false;
                        }
                    }
                });
            }
        }

        // Form validation
        const form = document.querySelector('.needs-validation');

        if (form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                form.classList.add('was-validated');
            });
        }

        const donorDetailsModal = document.getElementById('donorDetailsModal');
        if (donorDetailsModal) {
            donorDetailsModal.addEventListener('show.bs.modal', function(event) {
                    const btn = event.relatedTarget;

                    const personalInfo = document.getElementById('personalInfo');
                    const donationHistory = document.getElementById('donationHistory');
                    const interviewDetails = document.getElementById('interviewDetails');
                    const appointmentSchedule = document.getElementById('appointmentSchedule');

                    // Determine donor id (fall back to data attributes)
                    const donorId = btn.getAttribute('data-donor-id') || btn.getAttribute('data-id') || btn.dataset.donorId || btn.dataset.id;

                    // Attempt to fetch live donor + interview details from server
                    (function fetchDonorDetails(did) {
                        if (!did) {
                            // Fallback to data attributes if no donor id
                            populateFromDataset(btn);
                            return;
                        }

                        fetch('get_donor_details.php?donor_id=' + encodeURIComponent(did), {
                            credentials: 'same-origin'
                        }).then(r => {
                            if (!r.ok) throw new Error('Network response was not ok');
                            return r.json();
                        }).then(data => {
                            if (data && !data.error) {
                                populateFromJson(data);
                            } else {
                                populateFromDataset(btn);
                            }
                        }).catch(err => {
                            // On any error, gracefully fall back to dataset values
                            console.warn('Could not fetch donor details:', err);
                            populateFromDataset(btn);
                        });
                    })(donorId);

                    // Populate UI using JSON returned from get_donor_details.php
                    function populateFromJson(d) {
                        personalInfo.innerHTML = `
                            <p><strong>Name:</strong> ${escapeHtml(d.first_name + (d.last_name ? ' ' + d.last_name : ''))}</p>
                            <p><strong>Blood Type:</strong> ${escapeHtml(d.blood_type || '')}</p>
                            <p><strong>Gender:</strong> ${escapeHtml(d.gender || '')}</p>
                            <p><strong>Date of Birth:</strong> ${escapeHtml(d.date_of_birth || '')}</p>
                            <p><strong>Phone:</strong> ${escapeHtml(d.phone || '')}</p>
                            <p><strong>Email:</strong> ${escapeHtml(d.email || '')}</p>
                            <p><strong>Address:</strong> ${escapeHtml((d.address || '') + (d.city ? ', ' + d.city : ''))}</p>
                            <p><strong>Registration Date:</strong> ${escapeHtml(d.registration_date || '')}</p>
                        `;

                        donationHistory.innerHTML = `
                            <p><strong>Total Donations:</strong> ${escapeHtml(d.donation_count ?? 0)}</p>
                            <p><strong>Last Donation:</strong> ${escapeHtml(d.last_donation_date || 'Never')}</p>
                        `;

                        // Interview block
                        if (!d.responses_json) {
                            interviewDetails.innerHTML = `<p><em>No interview data available.</em></p>`;
                        } else {
                            // Interview questions mapping
                            const interviewQuestions = {
                                'q1': 'Do you feel well and healthy today?',
                                'q2': 'Have you ever been refused as a blood donor or told not to donate blood for any reasons?',
                                'q3': 'Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?',
                                'q4': 'Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?',
                                'q5': 'Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?',
                                'q6': 'In the last 3 DAYS have you taken aspirin?',
                                'q7': 'In the past 3 MONTHS have you donated whole blood, platelets or plasma?',
                                'q8': 'In the past 4 WEEKS have you taken any medications and/or vaccinations?',
                                'q9': 'Been to any places in the Philippines or countries infected with ZIKA Virus?',
                                'q10': 'Had sexual contact with a person who was confirmed to have ZIKA Virus infection?',
                                'q11': 'Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?',
                                'q12': 'Received blood, blood products and/or had tissue/organ transplant or graft?',
                                'q13': 'Had surgical operation or dental extraction?',
                                'q14': 'Had a tattoo applied, ear and body piercing, acupuncture, needle stick injury or accidental contact with blood?',
                                'q15': 'Had sexual contact with high risks individuals or in exchange for material or monetary gain?',
                                'q16': 'Engaged in unprotected, unsafe or casual sex?',
                                'q17': 'Had jaundice/hepatitis/personal contact with person who had hepatitis?',
                                'q18': 'Been incarcerated, jailed or imprisoned?',
                                'q19': 'Spent time or have relatives in the United Kingdom or Europe?',
                                'q20': 'Travelled or lived outside of your place of residence or outside the Philippines?',
                                'q21': 'Taken prohibited drugs (orally, by nose, or by injection)?',
                                'q22': 'Used clotting factor concentrates?',
                                'q23': 'Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?',
                                'q24': 'Had Malaria or Hepatitis in the past?',
                                'q25': 'Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?',
                                'q27': 'Cancer, blood disease or bleeding disorder (haemophilia)?',
                                'q28': 'Heart disease/surgery, rheumatic fever or chest pains?',
                                'q29': 'Lung disease, tuberculosis or asthma?',
                                'q30': 'Kidney disease, thyroid disease, diabetes, epilepsy?',
                                'q31': 'Chicken pox and/or cold sores?',
                                'q32': 'Any other chronic medical condition or surgical operations?',
                                'is_female': 'Are you female?',
                                'q34_current_pregnant': 'Are you currently pregnant or have you ever been pregnant?',
                                'q35_last_childbirth': 'When was your last childbirth?',
                                'q35_miscarriage_1y': 'In the past 1 YEAR, did you have a miscarriage or abortion?',
                                'q36_breastfeeding': 'Are you currently breastfeeding?',
                                'q37_lmp_date': 'When was your last menstrual period?'
                            };
                            
                            let parsed = null;
                            try { parsed = JSON.parse(d.responses_json); } catch(e) { parsed = null; }
                            
                            let ivHtml = '';
                            if (d.interview_date || d.interview_status) {
                                ivHtml += `<p><strong>Submitted:</strong> ${escapeHtml(d.interview_date || 'N/A')}</p>`;
                            }
                            
                            if (parsed && typeof parsed === 'object') {
                                ivHtml += '<div class="table-responsive mt-3"><table class="table table-sm table-bordered">';
                                ivHtml += '<thead><tr><th style="width:70%;">Question</th><th style="width:30%;">Answer</th></tr></thead><tbody>';
                                
                                // Sort keys to display questions in order
                                const sortedKeys = Object.keys(parsed).sort((a, b) => {
                                    // Extract numbers for numeric sorting
                                    const numA = Number.parseInt(a.replace(/\D/g, ''), 10) || 0;
                                    const numB = Number.parseInt(b.replace(/\D/g, ''), 10) || 0;
                                    if (numA !== numB) return numA - numB;
                                    return a.localeCompare(b);
                                });
                                
                                sortedKeys.forEach(k => {
                                    const questionText = interviewQuestions[k] || k;
                                    const answerValue = parsed[k];
                                    let answerDisplay = '';
                                    
                                    if (answerValue === 'yes' || answerValue === 'no') {
                                        const badgeClass = answerValue === 'yes' ? 'bg-danger' : 'bg-success';
                                        const answerText = answerValue === 'yes' ? 'Yes' : 'No';
                                        answerDisplay = `<span class="badge ${badgeClass}">${answerText}</span>`;
                                    } else if (answerValue && answerValue.trim() !== '') {
                                        answerDisplay = escapeHtml(String(answerValue));
                                    } else {
                                        answerDisplay = '<span class="text-muted">—</span>';
                                    }
                                    
                                    ivHtml += `<tr><td>${escapeHtml(questionText)}</td><td>${answerDisplay}</td></tr>`;
                                });
                                
                                ivHtml += '</tbody></table></div>';
                            } else {
                                ivHtml += `<pre style="white-space:pre-wrap;word-wrap:break-word;">${escapeHtml(d.responses_json)}</pre>`;
                            }
                            interviewDetails.innerHTML = ivHtml;
                        }

                        appointmentSchedule.innerHTML = `
                            <p><strong>Current Status:</strong> ${escapeHtml(d.appointment_status || '')}</p>
                            <p><strong>Date:</strong> ${escapeHtml(d.appointment_date || '')}</p>
                            <p><strong>Time:</strong> ${escapeHtml(d.appointment_time || '')}</p>
                            <p><strong>Location:</strong> ${escapeHtml(d.location || '')}</p>
                            <p><strong>Notes:</strong> ${escapeHtml(d.notes || 'No notes available')}</p>
                        `;

                        wireQuickActions(d);
                    }

                    // Populate UI from data attributes (fallback)
                    function populateFromDataset(btnEl) {
                        personalInfo.innerHTML = `
                            <p><strong>Name:</strong> ${escapeHtml(btnEl.dataset.donorName || '')}</p>
                            <p><strong>Blood Type:</strong> ${escapeHtml(btnEl.dataset.bloodType || '')}</p>
                            <p><strong>Gender:</strong> ${escapeHtml(btnEl.dataset.gender || '')}</p>
                            <p><strong>Date of Birth:</strong> ${escapeHtml(btnEl.dataset.dob || '')}</p>
                            <p><strong>Phone:</strong> ${escapeHtml(btnEl.dataset.phone || '')}</p>
                            <p><strong>Email:</strong> ${escapeHtml(btnEl.dataset.email || '')}</p>
                            <p><strong>Address:</strong> ${escapeHtml((btnEl.dataset.address || '') + (btnEl.dataset.city ? ', ' + btnEl.dataset.city : ''))}</p>
                            <p><strong>Registration Date:</strong> ${escapeHtml(btnEl.dataset.registered || '')}</p>
                        `;

                        donationHistory.innerHTML = `
                            <p><strong>Total Donations:</strong> ${escapeHtml(btnEl.dataset.donationCount ?? 0)}</p>
                            <p><strong>Last Donation:</strong> ${escapeHtml(btnEl.dataset.lastDonation || 'Never')}</p>
                        `;

                        const ivStatus = btnEl.dataset.interviewStatus || '';
                        const ivResponsesRaw = btnEl.dataset.interviewResponses || '';
                        const ivCreated = btnEl.dataset.interviewCreated || '';
                        if (!ivResponsesRaw) {
                            interviewDetails.innerHTML = `<p><em>No interview data available.</em></p>`;
                        } else {
                            // Interview questions mapping
                            const interviewQuestions = {
                                'q1': 'Do you feel well and healthy today?',
                                'q2': 'Have you ever been refused as a blood donor or told not to donate blood for any reasons?',
                                'q3': 'Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?',
                                'q4': 'Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?',
                                'q5': 'Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?',
                                'q6': 'In the last 3 DAYS have you taken aspirin?',
                                'q7': 'In the past 3 MONTHS have you donated whole blood, platelets or plasma?',
                                'q8': 'In the past 4 WEEKS have you taken any medications and/or vaccinations?',
                                'q9': 'Been to any places in the Philippines or countries infected with ZIKA Virus?',
                                'q10': 'Had sexual contact with a person who was confirmed to have ZIKA Virus infection?',
                                'q11': 'Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?',
                                'q12': 'Received blood, blood products and/or had tissue/organ transplant or graft?',
                                'q13': 'Had surgical operation or dental extraction?',
                                'q14': 'Had a tattoo applied, ear and body piercing, acupuncture, needle stick injury or accidental contact with blood?',
                                'q15': 'Had sexual contact with high risks individuals or in exchange for material or monetary gain?',
                                'q16': 'Engaged in unprotected, unsafe or casual sex?',
                                'q17': 'Had jaundice/hepatitis/personal contact with person who had hepatitis?',
                                'q18': 'Been incarcerated, jailed or imprisoned?',
                                'q19': 'Spent time or have relatives in the United Kingdom or Europe?',
                                'q20': 'Travelled or lived outside of your place of residence or outside the Philippines?',
                                'q21': 'Taken prohibited drugs (orally, by nose, or by injection)?',
                                'q22': 'Used clotting factor concentrates?',
                                'q23': 'Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?',
                                'q24': 'Had Malaria or Hepatitis in the past?',
                                'q25': 'Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?',
                                'q27': 'Cancer, blood disease or bleeding disorder (haemophilia)?',
                                'q28': 'Heart disease/surgery, rheumatic fever or chest pains?',
                                'q29': 'Lung disease, tuberculosis or asthma?',
                                'q30': 'Kidney disease, thyroid disease, diabetes, epilepsy?',
                                'q31': 'Chicken pox and/or cold sores?',
                                'q32': 'Any other chronic medical condition or surgical operations?',
                                'is_female': 'Are you female?',
                                'q34_current_pregnant': 'Are you currently pregnant or have you ever been pregnant?',
                                'q35_last_childbirth': 'When was your last childbirth?',
                                'q35_miscarriage_1y': 'In the past 1 YEAR, did you have a miscarriage or abortion?',
                                'q36_breastfeeding': 'Are you currently breastfeeding?',
                                'q37_lmp_date': 'When was your last menstrual period?'
                            };
                            
                            let parsed = null;
                            try { parsed = JSON.parse(ivResponsesRaw); } catch(e) { parsed = null; }
                            
                            let ivHtml = '';
                            if (ivCreated) {
                                ivHtml += `<p><strong>Submitted:</strong> ${escapeHtml(ivCreated || '')}</p>`;
                            }
                            
                            if (parsed && typeof parsed === 'object') {
                                ivHtml += '<div class="table-responsive mt-3"><table class="table table-sm table-bordered">';
                                ivHtml += '<thead><tr><th style="width:70%;">Question</th><th style="width:30%;">Answer</th></tr></thead><tbody>';
                                
                                // Sort keys to display questions in order
                                const sortedKeys = Object.keys(parsed).sort((a, b) => {
                                    // Extract numbers for numeric sorting
                                    const numA = Number.parseInt(a.replace(/\D/g, ''), 10) || 0;
                                    const numB = Number.parseInt(b.replace(/\D/g, ''), 10) || 0;
                                    if (numA !== numB) return numA - numB;
                                    return a.localeCompare(b);
                                });
                                
                                sortedKeys.forEach(k => {
                                    const questionText = interviewQuestions[k] || k;
                                    const answerValue = parsed[k];
                                    let answerDisplay = '';
                                    
                                    if (answerValue === 'yes' || answerValue === 'no') {
                                        const badgeClass = answerValue === 'yes' ? 'bg-danger' : 'bg-success';
                                        const answerText = answerValue === 'yes' ? 'Yes' : 'No';
                                        answerDisplay = `<span class="badge ${badgeClass}">${answerText}</span>`;
                                    } else if (answerValue && answerValue.trim() !== '') {
                                        answerDisplay = escapeHtml(String(answerValue));
                                    } else {
                                        answerDisplay = '<span class="text-muted">—</span>';
                                    }
                                    
                                    ivHtml += `<tr><td>${escapeHtml(questionText)}</td><td>${answerDisplay}</td></tr>`;
                                });
                                
                                ivHtml += '</tbody></table></div>';
                                interviewDetails.innerHTML = ivHtml;
                            } else {
                                interviewDetails.innerHTML = `<p><strong>Submitted:</strong> ${escapeHtml(ivCreated || '')}</p><pre style="white-space:pre-wrap;word-wrap:break-word;">${escapeHtml(ivResponsesRaw)}</pre>`;
                            }
                        }

                        appointmentSchedule.innerHTML = `
                            <p><strong>Current Status:</strong> ${escapeHtml(btnEl.dataset.apptStatus || '')}</p>
                            <p><strong>Date:</strong> ${escapeHtml(btnEl.dataset.apptDate || '')}</p>
                            <p><strong>Time:</strong> ${escapeHtml(btnEl.dataset.apptTime || '')}</p>
                            <p><strong>Location:</strong> ${escapeHtml(btnEl.dataset.location || '')}</p>
                            <p><strong>Notes:</strong> ${escapeHtml(btnEl.dataset.notes || 'No notes available')}</p>
                        `;

                        wireQuickActions({
                            appointment_id: btnEl.getAttribute('data-id') || btnEl.dataset.id,
                            appointment_status: btnEl.dataset.apptStatus || '',
                            appointment_date: btnEl.dataset.apptDateRaw || btnEl.getAttribute('data-appt-date-raw') || '',
                            appointment_time: btnEl.dataset.apptTimeRaw || btnEl.getAttribute('data-appt-time-raw') || '',
                            location: btnEl.dataset.location || btnEl.getAttribute('data-location') || ''
                        });
                    }

                    // Wire quick action buttons (approve/reject) using either JSON or dataset info
                    function wireQuickActions(d) {
                        const approveBtn = document.getElementById('donorModalApproveBtn');
                        const rejectBtn = document.getElementById('donorModalRejectBtn');
                        const apptStatus = (d.appointment_status || '');
                        // If appointment is Pending (string match), show actions
                        if ((apptStatus || '').toString() === 'Pending') {
                            approveBtn.classList.remove('d-none');
                            rejectBtn.classList.remove('d-none');
                            approveBtn.onclick = function() {
                                // Prefer the visible name in the Donor Details pane if available
                                var donorName = '';
                                try {
                                    var p = personalInfo.querySelector('p');
                                    if (p) donorName = p.textContent.replace(/^Name:\s*/i, '').trim();
                                } catch (e) { /* ignore */ }
                                if (!donorName) {
                                    donorName = d.first_name ? (d.first_name + (d.last_name ? ' ' + d.last_name : '')) : '';
                                }
                                // Use shared helper to open the Update Status modal so behavior matches the check icon
                                window.openUpdateStatusModal({
                                    appointment_id: d.appointment_id || d.appointment_id || d.appointment_id,
                                    donor_name: donorName,
                                    status: 'Scheduled',
                                    current_status: d.appointment_status || 'Pending',
                                    appointment_date: d.appointment_date || '',
                                    appointment_time: d.appointment_time || '',
                                    location: d.location || ''
                                });
                            };
                            rejectBtn.onclick = function() {
                                var donorName = '';
                                try {
                                    var p = personalInfo.querySelector('p');
                                    if (p) donorName = p.textContent.replace(/^Name:\s*/i, '').trim();
                                } catch (e) { /* ignore */ }
                                if (!donorName) {
                                    donorName = d.first_name ? (d.first_name + (d.last_name ? ' ' + d.last_name : '')) : '';
                                }
                                window.openUpdateStatusModal({
                                    appointment_id: d.appointment_id || '',
                                    donor_name: donorName,
                                    status: 'Rejected',
                                    current_status: d.appointment_status || 'Pending'
                                });
                            };
                        } else {
                            approveBtn.classList.add('d-none');
                            rejectBtn.classList.add('d-none');
                            approveBtn.onclick = null;
                            rejectBtn.onclick = null;
                        }
                    }

                    // Basic HTML-escape helper
                    function escapeHtml(str) {
                        if (str === null || str === undefined) return '';
                        return String(str)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/\"/g, '&quot;')
                            .replace(/'/g, '&#39;');
                    }
            });
        }
    });
</script>
<script>
  // Defensive: append CSRF token to any POST form without one
  (function(){
    var csrf = '<?php echo htmlspecialchars(get_csrf_token()); ?>';
    document.addEventListener('DOMContentLoaded', function(){
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
  })();
  </script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.alert, .feedback-message').forEach(function(el) {
            el.style.display = 'none';
        });
    }, 5000);
});
</script>
<script src="../../assets/js/titlecase-formatter.js"></script>

</body>
</html>

