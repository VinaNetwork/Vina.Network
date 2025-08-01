<?php
// ============================================================================
// File: make-market/get-transaction.php
// Description: Endpoint to retrieve transaction parameters for a given transaction ID
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';

header('Content-Type: application/json');

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

try {
    // Log session info for debugging
    log_message("Session data: " . json_encode($_SESSION), 'make-market.log', 'make-market', 'DEBUG');

    // Kiểm tra session user_id
    if (!isset($_SESSION['user_id'])) {
        log_message("No user_id in session", 'make-market.log', 'make-market', 'ERROR');
        throw new Exception('Unauthorized: Please log in');
    }

    // Kiểm tra transactionId
    $transactionId = isset($_GET['id']) ? trim($_GET['id']) : null;
    if (!$transactionId || !is_numeric($transactionId) || $transactionId <= 0) {
        log_message("Invalid transaction ID: " . var_export($transactionId, true), 'make-market.log', 'make-market', 'ERROR');
        throw new Exception('Invalid transaction ID');
    }

    // Kết nối database
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT process_name, token_mint, sol_amount, slippage, delay_seconds, loop_count, batch_size, status
        FROM make_market
        WHERE id = ? AND user_id = ? AND status IN ('success', 'failed')
    ");
    $stmt->execute([$transactionId, $_SESSION['user_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        log_message("Transaction not found for ID: $transactionId, user_id: {$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        throw new Exception('Transaction not found or not accessible');
    }

    log_message("Transaction retrieved for ID: $transactionId", 'make-market.log', 'make-market', 'INFO');
    echo json_encode([
        'status' => 'success',
        'process_name' => $transaction['process_name'],
        'token_mint' => $transaction['token_mint'],
        'sol_amount' => $transaction['sol_amount'],
        'slippage' => $transaction['slippage'],
        'delay_seconds' => $transaction['delay_seconds'],
        'loop_count' => $transaction['loop_count'],
        'batch_size' => $transaction['batch_size']
    ]);
} catch (Exception $e) {
    log_message("Error retrieving transaction: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
