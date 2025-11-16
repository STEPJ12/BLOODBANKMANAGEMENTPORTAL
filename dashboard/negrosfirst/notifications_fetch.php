<?php
// Fetch latest notifications and unread count for Negros First (JSON)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'negrosfirst') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

require_once '../../config/db.php';

try {
    $unread = getCount("SELECT COUNT(*) FROM notifications WHERE (user_role='negrosfirst' OR user_role='negros') AND is_read=0");
    $list = executeQuery("SELECT id, title, message, created_at, is_read FROM notifications WHERE (user_role='negrosfirst' OR user_role='negros') ORDER BY created_at DESC LIMIT 10");
    if (!is_array($list)) { $list = []; }
    echo json_encode(['unread' => (int)$unread, 'items' => $list]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}
