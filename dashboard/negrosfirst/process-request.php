<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $action = isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject'], true) ? sanitize($_POST['action']) : null;
    
    if (!$request_id || !$action) {
        $_SESSION['error'] = "Invalid request parameters.";
        header("Location: blood-requests.php");
        exit;
    }

    try {
        if ($action === 'approve') {
            // Get request details for notification
            $request = getRow("SELECT patient_id, blood_type, units_requested FROM blood_requests WHERE id = ?", [$request_id]);
            
            if (!$request) {
                throw new Exception("Blood request not found.");
            }

            // Update request status to Approved
            $conn = getConnection();
            $stmt = $conn->prepare("UPDATE blood_requests SET status = 'Approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$_SESSION['user_id'], $request_id]);
            
            if (!$result) {
                throw new Exception("Failed to update blood request status.");
            }
            
            // Send notification with SMS using template
            require_once '../../includes/notification_templates.php';
            
            $notificationData = [
                'units_requested' => $request['units_requested'],
                'blood_type' => $request['blood_type'],
                'request_id' => $request_id
            ];
            
            send_notification_with_sms(
                $request['patient_id'],
                'patient',
                'approved',
                'Blood Request Approved',
                $notificationData,
                'negrosfirst'
            );
            
            $_SESSION['success'] = "Blood request has been approved successfully.";
        } 
        elseif ($action === 'reject') {
            // Sanitize rejection reason to prevent XSS and log injection
            $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');
            
            if (empty($rejection_reason)) {
                $_SESSION['error'] = "Please provide a reason for rejection.";
                header("Location: blood-requests.php");
                exit;
            }

            // Get request details for notification
            $request = getRow("SELECT patient_id, blood_type, units_requested FROM blood_requests WHERE id = ?", [$request_id]);
            
            if (!$request) {
                throw new Exception("Blood request not found.");
            }

            // Update request status to Rejected
            $conn = getConnection();
            $stmt = $conn->prepare("UPDATE blood_requests SET status = 'Rejected', rejection_reason = ?, rejected_by = ?, rejected_at = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$rejection_reason, $_SESSION['user_id'], $request_id]);
            
            if (!$result) {
                throw new Exception("Failed to update blood request status.");
            }
            
            // Send notification with SMS using template
            require_once '../../includes/notification_templates.php';
            
            $notificationData = [
                'type' => 'request',
                'reason' => $rejection_reason,
                'units_requested' => $request['units_requested'],
                'blood_type' => $request['blood_type']
            ];
            
            send_notification_with_sms(
                $request['patient_id'],
                'patient',
                'rejected',
                'Blood Request Rejected',
                $notificationData,
                'negrosfirst'
            );
            
            $_SESSION['success'] = "Blood request has been rejected.";
        }
        else {
            $_SESSION['error'] = "Invalid action specified.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred while processing the request. Please try again.";
        if (function_exists('secure_log')) {
            secure_log("Error processing blood request", [
                'request_id' => $request_id,
                'action' => $action,
                'error' => substr($e->getMessage(), 0, 200)
            ]);
        }
    }

    header("Location: blood-requests.php");
    exit;
} else {
    header("Location: blood-requests.php");
    exit;
} 