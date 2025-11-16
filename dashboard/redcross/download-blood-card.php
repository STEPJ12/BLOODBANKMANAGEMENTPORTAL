<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'redcross') {
    header("Location: ../../loginredcross.php?role=redcross");
    exit;
}

require_once '../../config/db.php';

$requestId = isset($_GET['request_id']) ? sanitize($_GET['request_id']) : null;

if (!$requestId) {
    http_response_code(400);
    die('Request ID is required.');
}

// Fetch the blood card path
$request = getRow("
    SELECT br.blood_card_path, br.id, br.patient_id
    FROM blood_requests br
    WHERE br.id = ? AND br.organization_type = 'redcross'
", [$requestId]);

if (!$request || !$request['blood_card_path']) {
    http_response_code(404);
    die('Blood card not found.');
}

// Normalize the path - remove any leading ../ or ./ or /, ensure it starts with uploads/
$dbPath = $request['blood_card_path'];
// Remove any leading relative path indicators (../../, ../, ./)
$dbPath = preg_replace('#^(\.\.?/)+#', '', $dbPath);
// Remove leading slash
$dbPath = ltrim($dbPath, '/');
// Ensure it starts with uploads/
if (strpos($dbPath, 'uploads/') !== 0) {
    $dbPath = 'uploads/' . ltrim($dbPath, 'uploads/');
}

// Build absolute path from dashboard/redcross/ directory
// __DIR__ is: C:\xampp\htdocs\blood\dashboard\redcross
// Going up two levels: C:\xampp\htdocs\blood
// Then add uploads/filename: C:\xampp\htdocs\blood\uploads\filename
$filePath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . $dbPath;

// Normalize path separators for Windows
$filePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
$resolvedPath = realpath($filePath);

if (!$resolvedPath || !file_exists($resolvedPath)) {
    // Try alternative path resolution
    $altPath = __DIR__ . '/../../' . $dbPath;
    $altPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $altPath);
    $altResolved = realpath($altPath);
    
    if ($altResolved && file_exists($altResolved)) {
        $resolvedPath = $altResolved;
    } else {
        http_response_code(404);
        header('Content-Type: text/html');
        echo '<html><body style="font-family:Arial;padding:20px;text-align:center;"><h3>File Not Found</h3><p>The document file could not be located on the server.</p><p style="color:#666;font-size:12px;">DB Path: ' . htmlspecialchars($request['blood_card_path']) . '</p><p style="color:#666;font-size:12px;">Resolved: ' . htmlspecialchars($filePath) . '</p></body></html>';
        exit;
    }
}

$filePath = $resolvedPath;

// Get file info
$fileInfo = pathinfo($filePath);
$fileName = $fileInfo['basename'];
$mimeType = mime_content_type($filePath);

// Set headers for download
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));

// Output the file
readfile($filePath);
exit;
?>

