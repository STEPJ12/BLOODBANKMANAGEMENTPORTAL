<?php
// download-referral.php
// Streams referral document content for the logged-in patient

session_start();
require_once '../../config/db.php';

// AuthN/AuthZ: only logged-in patient users
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$patientId = (int)$_SESSION['user_id'];
$referralId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($referralId <= 0) {
    http_response_code(400);
    echo 'Invalid referral id';
    exit;
}

// Fetch referral and verify it belongs to this patient
$ref = getRow(
    "SELECT r.id, r.barangay_id, r.referral_document_name, r.referral_document_type, r.referral_document_data,
            br.patient_id
     FROM referrals r
     INNER JOIN blood_requests br ON r.blood_request_id = br.id
     WHERE r.id = ? AND br.patient_id = ?",
    [$referralId, $patientId]
);

if (!$ref) {
    http_response_code(404);
    echo 'Document not found or access denied';
    exit;
}

$mime = $ref['referral_document_type'] ?: 'application/octet-stream';
$filename = $ref['referral_document_name'] ?: ('referral-' . $referralId);
$data = $ref['referral_document_data'];

if (!$data) {
    http_response_code(404);
    echo 'No document data available';
    exit;
}

// Output headers. For PDFs we want inline view in browser when possible.
$inlineTypes = [
    'application/pdf',
    'text/plain',
];
$disposition = in_array(strtolower($mime), $inlineTypes) ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($data));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '""', $filename) . '"');
header('X-Content-Type-Options: nosniff');

echo $data;
exit;
?>

