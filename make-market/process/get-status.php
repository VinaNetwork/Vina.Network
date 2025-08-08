<?php
// ============================================================================
// File: make-market/process/get-status.php
// Description: Update transaction status and error in make_market table
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';
require_once $root_path . 'make-market/process/auth.php';

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['id']) ? intval($input['id']) : 0;
$status = $input['status'] ?? '';
$error = $input['error'] ?? null;

// Validate input
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
    exit;
}
if (!in_array($status, ['pending', 'processing', 'failed', 'success', 'canceled'])) {
    log_message("Invalid status: $status", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit;
}

// Perform authentication check with transaction ownership
if (!perform_auth_check($pdo, $transaction_id)) {
    exit;
}

// Update transaction
try {
    $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
    $stmt->execute([$status, $error, $transaction_id]);
    log_message("Transaction status updated: ID=$transaction_id, status=$status, error=" . ($error ?? 'none'), 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    log_message("Database update failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error updating transaction']);
    exit;
}
?>
