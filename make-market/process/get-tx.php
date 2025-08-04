<?php
// ============================================================================
// File: make-market/process/get-tx.php
// Description: Retrieve transaction details from make_market table
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://vina.network');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Non-AJAX request']);
    exit;
}

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("get-tx.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Get transaction ID
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT token_mint, sol_amount, slippage, delay_seconds, loop_count, batch_size FROM make_market WHERE id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Transaction not found: ID=$transaction_id", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }
    log_message("Transaction fetched: ID=$transaction_id, token_mint={$transaction['token_mint']}", 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'data' => $transaction]);
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction']);
    exit;
}
?>
