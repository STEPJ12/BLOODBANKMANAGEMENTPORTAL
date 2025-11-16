<?php
// Start session
session_start();

// Check if user is logged in and is Red Cross
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'redcross') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection
require_once '../../config/db.php';

// Get drive ID from request
$driveId = isset($_GET['id']) ? sanitize($_GET['id']) : null;

if (!$driveId) {
    http_response_code(400);
    echo json_encode(['error' => 'Drive ID is required']);
    exit;
}

try {
    // Get drive details
    $drive = getRow("
        SELECT bd.*, bu.name as barangay_name,
        (SELECT COUNT(*) FROM donor_appointments WHERE blood_drive_id = bd.id) as registered_donors
        FROM blood_drives bd
        JOIN barangay_users bu ON bd.barangay_id = bu.id
        WHERE bd.id = ? AND bd.organization_type = 'redcross' AND bd.organization_id = ?
    ", [$driveId, $_SESSION['user_id']]);

    if (!$drive) {
        http_response_code(404);
        echo json_encode(['error' => 'Drive not found']);
        exit;
    }

    // Return drive details
    header('Content-Type: application/json');
    echo json_encode($drive);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to fetch drive details: ' . $e->getMessage()]);
    exit;
}