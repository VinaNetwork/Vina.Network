<?php
// ============================================================================
// File: make-market/process/get-tx.php
// Description: Retrieve transaction details from database
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'make-market/process/network.php';
require_once $root_path . 'make-market/process/auth.php';

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get transaction ID from URL
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID: $transaction_id, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Perform authentication check with transaction ownership
if (!perform_auth_check($pdo, $transaction_id)) {
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT id, user_id, public_key, token_mint, sol_amount, token_amount, trade_direction, process_name, loop_count, batch_size, slippage, delay_seconds, status, network FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
    $stmt->execute([$transaction_id, $_SESSION['user_id'], SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Transaction not found, unauthorized, or network mismatch: ID=$transaction_id, session_user_id=" . ($_SESSION['user_id'] ?? 'none') . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found, unauthorized, or network mismatch'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (empty($transaction['public_key']) || $transaction['public_key'] === 'undefined') {
        log_message("Invalid public key in database: ID=$transaction_id, public_key={$transaction['public_key']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid public key in transaction'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $transaction['public_key'])) {
        log_message("Invalid public key format: ID=$transaction_id, public_key={$transaction['public_key']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid public key format'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Ensure defaults
    $transaction['loop_count'] = intval($transaction['loop_count'] ?? 1);
    $transaction['batch_size'] = intval($transaction['batch_size'] ?? 1);
    $transaction['slippage'] = floatval($transaction['slippage'] ?? 0.5);
    $transaction['delay_seconds'] = intval($transaction['delay_seconds'] ?? 1);
    $transaction['sol_amount'] = floatval($transaction['sol_amount'] ?? 0);
    $transaction['token_amount'] = floatval($transaction['token_amount'] ?? 0);
    $transaction['trade_direction'] = $transaction['trade_direction'] ?? 'buy';
    log_message("Transaction fetched: ID=$transaction_id, token_mint={$transaction['token_mint']}, public_key={$transaction['public_key']}, sol_amount={$transaction['sol_amount']}, token_amount={$transaction['token_amount']}, trade_direction={$transaction['trade_direction']}, loop_count={$transaction['loop_count']}, batch_size={$transaction['batch_size']}, slippage={$transaction['slippage']}, delay_seconds={$transaction['delay_seconds']}, status={$transaction['status']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'data' => $transaction], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
