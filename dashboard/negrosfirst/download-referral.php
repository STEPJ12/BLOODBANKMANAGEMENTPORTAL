<?php
require_once '../../includes/negrosfirst_auth.php';
require_once '../../config/db.php';

$request_id = $_GET['request_id'] ?? null;
if (!$request_id) {
    http_response_code(400);
    exit('Invalid request.');
}

// Verify the request belongs to Negros First
$row = getRow("
    SELECT r.referral_document_name, r.referral_document_type, r.referral_document_data 
    FROM referrals r
    JOIN blood_requests br ON r.blood_request_id = br.id
    WHERE r.blood_request_id = ? 
      AND br.organization_type = 'negrosfirst'
", [$request_id]);

if (!$row || empty($row['referral_document_data'])) {
    http_response_code(404);
    exit('No referral found.');
}

header('Content-Type: ' . $row['referral_document_type']);
header('Content-Disposition: attachment; filename="' . $row['referral_document_name'] . '"');
echo $row['referral_document_data'];
exit;

