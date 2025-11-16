<?php
require_once '../../includes/negrosfirst_auth.php';
require_once '../../config/db.php';

$requestId = isset($_GET['request_id']) ? sanitize($_GET['request_id']) : null;

if (!$requestId) {
    http_response_code(400);
    die('Request ID is required.');
}

// Fetch the request form path
$request = getRow("
    SELECT br.request_form_path, br.id, br.patient_id
    FROM blood_requests br
    WHERE br.id = ? AND br.organization_type = 'negrosfirst'
", [$requestId]);

if (!$request || !$request['request_form_path']) {
    http_response_code(404);
    die('Request form not found.');
}

$filePath = '../../' . $request['request_form_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found.');
}

// Get file info
$fileInfo = pathinfo($filePath);
$fileName = $fileInfo['basename'];
$mimeType = mime_content_type($filePath);

// Set headers for inline viewing
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');

// Output the file
readfile($filePath);
exit;
?>

