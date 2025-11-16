<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

require_once '../../config/db.php';

$requestId = isset($_GET['request_id']) ? sanitize($_GET['request_id']) : null;

if (!$requestId) {
    http_response_code(400);
    die('Request ID is required.');
}

// Get barangay ID to verify access
$barangayId = $_SESSION['user_id'];

// Fetch the request form path - only if the request belongs to this barangay or was referred to this barangay
$request = getRow("
    SELECT br.request_form_path, br.id, br.patient_id, br.barangay_id
    FROM blood_requests br
    LEFT JOIN referrals r ON br.id = r.blood_request_id
    WHERE br.id = ? 
    AND (br.barangay_id = ? OR r.barangay_id = ?)
", [$requestId, $barangayId, $barangayId]);

if (!$request || !$request['request_form_path']) {
    http_response_code(404);
    header('Content-Type: text/html');
    echo '<html><body style="font-family:Arial;padding:20px;text-align:center;"><h3>Request Form Not Found</h3><p>The request form document is not available or you do not have access to this document.</p></body></html>';
    exit;
}

// Normalize the path - remove any leading ../ or ./ or /, ensure it starts with uploads/
$dbPath = $request['request_form_path'];
// Remove any leading relative path indicators (../../, ../, ./) - handle multiple occurrences
$dbPath = preg_replace('#^(\.\.?/)+#', '', $dbPath);
// Remove leading slash
$dbPath = ltrim($dbPath, '/');
// Extract just the filename if path contains uploads/
if (preg_match('#uploads/(.+)$#', $dbPath, $matches)) {
    $dbPath = 'uploads/' . $matches[1];
} elseif (strpos($dbPath, 'uploads/') !== 0) {
    // If it doesn't start with uploads/, assume it's just a filename
    $dbPath = 'uploads/' . $dbPath;
}

// Build absolute path from dashboard/barangay/ directory
// __DIR__ is: C:\xampp\htdocs\blood\dashboard\barangay
// Going up two levels: C:\xampp\htdocs\blood
// Then add uploads/filename: C:\xampp\htdocs\blood\uploads\filename
$filePath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . $dbPath;

// Debug logging (remove in production)
error_log("View Request Form - Request ID: " . $requestId);
error_log("View Request Form - DB Path: " . $dbPath);
error_log("View Request Form - File Path: " . $filePath);

// Normalize path separators for Windows
$filePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
$resolvedPath = realpath($filePath);

if (!$resolvedPath || !file_exists($resolvedPath)) {
    // Try alternative path resolution methods
    $altPaths = [
        __DIR__ . '/../../' . $dbPath,
        dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . $dbPath,
        $_SERVER['DOCUMENT_ROOT'] . '/blood/' . $dbPath,
        dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . $dbPath
    ];
    
    $found = false;
    foreach ($altPaths as $altPath) {
        $altPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $altPath);
        $altResolved = realpath($altPath);
        
        if ($altResolved && file_exists($altResolved)) {
            $resolvedPath = $altResolved;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        http_response_code(404);
        header('Content-Type: text/html');
        error_log("View Request Form - File not found. Tried paths: " . implode(", ", $altPaths));
        echo '<html><body style="font-family:Arial;padding:20px;text-align:center;"><h3>File Not Found</h3><p>The document file could not be located on the server.</p><p style="color:#666;font-size:12px;">DB Path: ' . htmlspecialchars($dbPath) . '</p><p style="color:#666;font-size:12px;">Please contact support if this file should exist.</p></body></html>';
        exit;
    }
}

$filePath = $resolvedPath;

// Get file info
$fileInfo = pathinfo($filePath);
$fileName = $fileInfo['basename'];
$extension = strtolower($fileInfo['extension'] ?? '');

// Determine MIME type
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif'
];

$mimeType = $mimeTypes[$extension] ?? mime_content_type($filePath);
if (!$mimeType) {
    $mimeType = 'application/octet-stream';
}

// Set headers for inline viewing
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');

// For images, add proper headers
if (strpos($mimeType, 'image/') === 0) {
    header('X-Content-Type-Options: nosniff');
}

// Output the file
readfile($filePath);
exit;

