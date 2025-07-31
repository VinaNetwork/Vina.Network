<?php
// ============================================================================
// File: make-market/get-transaction.php
// Description: Endpoint to retrieve transaction parameters for a given transaction ID
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

header('Content-Type: application/json');
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

session_start();

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized: Please log in');
    }

    $transactionId = $_GET['id'] ?? null;
    if (!$transactionId || !is_numeric($transactionId)) {
        throw new Exception('Invalid transaction ID');
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT process_name, token_mint, sol_amount, slippage, delay_seconds, loop_count, batch_size, status
        FROM make_market
        WHERE id = ? AND user_id = ? AND status IN ('success', 'failed')
    ");
    $stmt->execute([$transactionId, $_SESSION['user_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Transaction not found or not accessible');
    }

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
