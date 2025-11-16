<?php
require_once '../../config/db.php';
$request_id = $_GET['request_id'] ?? null;
if (!$request_id) {
    http_response_code(400);
    exit('Invalid request ID.');
}

$row = getRow("SELECT r.id, r.referral_document_name, r.referral_document_type, r.referral_document_data
               FROM referrals r
               WHERE r.blood_request_id = ?", [$request_id]);

if (!$row || empty($row['referral_document_data'])) {
    http_response_code(404);
    exit('No referral document found.');
}

header('Content-Type: ' . ($row['referral_document_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . ($row['referral_document_name'] ?: 'referral_document') . '"');
header('Content-Length: ' . strlen($row['referral_document_data']));
echo $row['referral_document_data'];
exit;
?>