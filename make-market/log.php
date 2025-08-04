<?php
// ============================================================================
// File: make-market/log.php
// Description: Handle logging from JavaScript to server-side log files
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';

// Chỉ chấp nhận POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Đọc dữ liệu từ request
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['message'], $input['log_file'], $input['module'], $input['log_type'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

// Ghi log
log_message(
    $input['message'],
    $input['log_file'],
    $input['module'],
    $input['log_type']
);

http_response_code(200);
echo json_encode(['status' => 'success']);
?>
