<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
    $status = isset($_POST['status']) ? sanitize($_POST['status']) : '';
    
    // Validate status
    $valid_statuses = ['Scheduled', 'Completed', 'Cancelled', 'No Show'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Update appointment status
    $update_sql = "UPDATE donor_appointments 
                  SET status = ?, 
                      updated_at = NOW() 
                  WHERE id = ? 
                  AND organization_type = 'negrosfirst' 
                  AND organization_id = ?";
    
    $result = executeQuery($update_sql, [$status, $appointment_id, $_SESSION['user_id']]);
    
    if ($result) {
        // Get appointment details for notification
        $appointment = getRow("
            SELECT a.*, d.name as donor_name, d.email, d.phone
            FROM donor_appointments a
            JOIN donor_users d ON a.donor_id = d.id
            WHERE a.id = ?
        ", [$appointment_id]);
        
        if ($appointment) {
            // Send notification with SMS using template
            require_once '../../includes/notification_templates.php';
            
            $notificationData = [
                'status' => $status,
                'date' => $appointment['appointment_date'],
                'time' => $appointment['appointment_time'],
                'location' => $appointment['location'] ?? ''
            ];
            
            // Determine notification type based on status
            $notificationType = 'appointment';
            if ($status === 'Completed') {
                $notificationType = 'completed';
                $notificationData['type'] = 'donation';
            } elseif ($status === 'Cancelled' || $status === 'No Show') {
                $notificationType = 'rejected';
                $notificationData['type'] = 'appointment';
                $notificationData['reason'] = $status === 'No Show' ? 'No show - appointment was missed' : 'Appointment was cancelled';
            }
            
            send_notification_with_sms(
                $appointment['donor_id'],
                'donor',
                $notificationType,
                'Appointment Status Updated',
                $notificationData,
                'negrosfirst'
            );
            
            // If status is Completed, create a donation record
            if ($status === 'Completed') {
                $donation_sql = "INSERT INTO donations (
                    donor_id, donation_date, units, blood_type,
                    status, organization_type, organization_id,
                    created_at, updated_at
                )
                SELECT
                    a.donor_id, a.appointment_date, 1, d.blood_type,
                    'Completed', 'negrosfirst', ?,
                    NOW(), NOW()
                FROM donor_appointments a
                JOIN donor_users d ON a.donor_id = d.id
                WHERE a.id = ?";
                
                insertRow($donation_sql, [$_SESSION['user_id'], $appointment_id]);
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment status']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 