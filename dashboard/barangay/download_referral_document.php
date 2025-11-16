<?php
// download_referral_document.php
// Streams referral document content (PDF or other non-image) for the logged-in barangay

session_start();
require_once '../../config/db.php';

// AuthN/AuthZ: only logged-in barangay users
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'barangay') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$barangayId = (int)$_SESSION['user_id'];
$referralId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($referralId <= 0) {
    http_response_code(400);
    echo 'Invalid referral id';
    exit;
}

// Fetch referral ensuring it belongs to the same barangay
$ref = getRow(
    "SELECT id, barangay_id, referral_document_name, referral_document_type, referral_document_data
     FROM referrals WHERE id = ? AND barangay_id = ?",
    [$referralId, $barangayId]
);

if (!$ref) {
    http_response_code(404);
    echo 'Document not found';
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

// Output headers. For PDFs and many office docs we want inline view in browser when possible.
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
