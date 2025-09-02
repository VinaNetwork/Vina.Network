<?php
// ============================================================================
// File: mm/get-logs.php
// Description: Handles client-side logging using core logging utilities.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

require_once __DIR__ . '/../core/logging.php';

// Set response header
header('Content-Type: application/json');

// Validate POST request and AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'], $data['log_type'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid log data']);
    exit;
}

// Log file configuration
$log_file = $data['log_file'] ?? 'make-market.log';
$level = strtoupper($data['log_type']);
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$message = "[IP:$ip_address] [URL:{$data['url']}] [UA:{$data['userAgent']}] {$data['message']}";

// Log the message using core logging function
log_message($message, $log_file, 'make-market', $level);

echo json_encode(['status' => 'success', 'message' => 'Log recorded']);
exit;
?>
