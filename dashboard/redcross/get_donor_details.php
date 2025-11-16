<?php
session_start();
// Start buffering immediately to capture any stray output
ob_start();
require_once '../../config/db.php';

// Always return JSON
header('Content-Type: application/json');

// Add missing database helper function that uses getConnection()
function getRow($sql, $params = []) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare($sql);
        if ($params) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        return false;
    }
}

// Check if donor_id is provided
if (!isset($_GET['donor_id']) || !is_numeric($_GET['donor_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid donor ID']);
    // Auto-hide error feedback for frontend (if rendered)
    echo '<script>setTimeout(function(){var msg=document.querySelector(".error-feedback");if(msg)msg.style.display="none";},5000);</script>';
    exit;
}

$donor_id = (int)$_GET['donor_id'];

// Get donor details from donor_users, latest appointment from donor_appointments, and latest interview from donor_interviews
$donor_query = "SELECT 
    du.name AS full_name,
    du.blood_type,
    du.phone,
    du.email,
    du.gender,
    du.date_of_birth,
    du.address,
    du.city,
    du.created_at as registration_date,
    du.last_donation_date,
    du.donation_count,
    a.id as appointment_id,
    a.appointment_date,
    a.appointment_time,
    a.location,
    a.status as appointment_status,
    a.notes,
    a.created_at as schedule_date,
    di.status as interview_status,
    di.responses_json,
    di.created_at as interview_created_at
    FROM donor_users du
    LEFT JOIN donor_appointments a ON a.donor_id = du.id
    LEFT JOIN donor_interviews di ON di.appointment_id = a.id
    WHERE du.id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC, di.created_at DESC
    LIMIT 1";

$donor = getRow($donor_query, [$donor_id]);

// If query layer failed entirely, return a helpful error JSON
if ($donor === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error while fetching donor details']);
    exit;
}

if (!$donor) {
    // Try to get basic user info even without appointment
    $basic_query = "SELECT 
        name AS full_name,
        blood_type,
        phone,
        email,
        gender,
        date_of_birth,
        address,
        city,
        created_at as registration_date,
        last_donation_date,
        donation_count
        FROM donor_users 
        WHERE id = ?";
    
    $donor = getRow($basic_query, [$donor_id]);
    
    if (!$donor) {
        http_response_code(404);
        echo json_encode(['error' => 'Donor not found']);
        exit;
    }
    
    // Add default appointment values
    $donor['appointment_status'] = 'No appointment scheduled';
    $donor['appointment_date'] = 'Not scheduled';
    $donor['appointment_time'] = 'Not scheduled';
    $donor['location'] = 'Not specified';
    $donor['notes'] = 'No notes available';
}

// Format dates safely
if (!empty($donor['date_of_birth'])) {
    $donor['date_of_birth'] = date('M d, Y', strtotime($donor['date_of_birth']));
}
if (!empty($donor['registration_date'])) {
    $donor['registration_date'] = date('M d, Y', strtotime($donor['registration_date']));
}
if (!empty($donor['appointment_date']) && $donor['appointment_date'] !== 'Not scheduled') {
    $donor['appointment_date'] = date('M d, Y', strtotime($donor['appointment_date']));
}
if (!empty($donor['appointment_time']) && $donor['appointment_time'] !== 'Not scheduled') {
    $donor['appointment_time'] = date('h:i A', strtotime($donor['appointment_time']));
}

// Add additional fields with default values
if (empty($donor['donation_count'])) $donor['donation_count'] = 0;
if (empty($donor['last_donation_date'])) {
    $donor['last_donation_date'] = 'Never';
} else {
    $donor['last_donation_date'] = date('M d, Y', strtotime($donor['last_donation_date']));
}
// Eligibility flag (default true if column not present)
$donor['is_eligible'] = isset($donor['is_eligible']) ? (bool)$donor['is_eligible'] : true;

// Map full_name into first_name/last_name for UI compatibility
if (!empty($donor['full_name'])) {
    $parts = preg_split('/\s+/', trim($donor['full_name']), 2);
    $donor['first_name'] = $parts[0];
    $donor['last_name'] = isset($parts[1]) ? $parts[1] : '';
} else {
    $donor['first_name'] = '';
    $donor['last_name'] = '';
}

// Interview related defaults
$donor['interview_date'] = !empty($donor['interview_created_at']) ? date('M d, Y', strtotime($donor['interview_created_at'])) : 'Not Scheduled';
$donor['interview_status'] = !empty($donor['interview_status']) ? $donor['interview_status'] : 'Not Available';

// If the LEFT JOIN didn't return an interview (for example when the interview was saved
// as a draft before being linked to an appointment), try to fetch the latest interview
// for this donor_id as a fallback so the UI shows draft interviews.
if (empty($donor['responses_json']) || $donor['interview_status'] === 'Not Available') {
    try {
        $fallback_sql = "SELECT * FROM donor_interviews WHERE donor_id = ? ORDER BY created_at DESC LIMIT 1";
        $fallback = getRow($fallback_sql, [$donor_id]);
        if ($fallback && !empty($fallback['id'])) {
            // Overwrite interview-related fields with the latest draft/interview
            $donor['interview_status'] = !empty($fallback['status']) ? $fallback['status'] : $donor['interview_status'];
            $donor['responses_json'] = !empty($fallback['responses_json']) ? $fallback['responses_json'] : $donor['responses_json'];
            $donor['interview_created_at'] = !empty($fallback['created_at']) ? $fallback['created_at'] : $donor['interview_created_at'];
            $donor['interview_date'] = !empty($donor['interview_created_at']) ? date('M d, Y', strtotime($donor['interview_created_at'])) : $donor['interview_date'];
        }
    } catch (Exception $e) {
        error_log('Fallback interview lookup failed: ' . $e->getMessage());
    }
}

// Best-effort parse of responses_json to infer booleans (optional)
$donor['medical_conditions'] = false;
$donor['medications'] = false;
$donor['recent_travel'] = false;
$donor['risk_factors'] = false;
$donor['interview_notes'] = 'No notes available';
if (!empty($donor['responses_json'])) {
    $resp = json_decode($donor['responses_json'], true);
    if (is_array($resp)) {
        // Simple heuristics based on keys
        $donor['medications'] = isset($resp['q8']) && $resp['q8'] === 'yes';
        $donor['recent_travel'] = (isset($resp['q9']) && $resp['q9'] === 'yes') || (isset($resp['q20']) && $resp['q20'] === 'yes');
        $donor['medical_conditions'] = (isset($resp['q27']) && $resp['q27'] === 'yes') || (isset($resp['q28']) && $resp['q28'] === 'yes') || (isset($resp['q29']) && $resp['q29'] === 'yes') || (isset($resp['q30']) && $resp['q30'] === 'yes');
        $donor['risk_factors'] = (isset($resp['q15']) && $resp['q15'] === 'yes') || (isset($resp['q16']) && $resp['q16'] === 'yes');
    }
}

// Flush any unexpected output as a JSON error to avoid broken JSON in the client
$unexpected = trim(ob_get_clean());
if ($unexpected !== '') {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected output from server', 'detail' => $unexpected]);
    exit;
}

// Return the donor details as JSON
echo json_encode($donor);
