<?php
// ============================================================================
// File: mm/log.php
// Description: Handle client-side logging for Make Market
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/logging.php';

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message'] ?? '';
$log_file = $data['log_file'] ?? 'make-market.log';
$module = $data['module'] ?? 'make-market';
$log_type = $data['log_type'] ?? 'INFO';

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing log message']);
    exit;
}

log_message($message, $log_file, $module, $log_type);
http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Log recorded']);
?>
