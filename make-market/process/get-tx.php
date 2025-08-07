<?php
// ============================================================================
// File: make-market/process/get-tx.php
// Description: Retrieve transaction details from database
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'make-market/get-bootstrap.php';
require_once $root_path . 'config/config.php';

setup_cors_headers($csp_base, 'GET');
check_ajax_request();
$user_id = check_authenticated_user();
log_request_info('get-tx.php');

// Get transaction ID from URL
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID: $transaction_id, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Database connection
$pdo = get_db_connection_safe();

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT id, user_id, public_key, token_mint, sol_amount, token_amount, trade_direction, process_name, loop_count, batch_size, slippage, delay_seconds, status FROM make_market WHERE id = ? AND user_id = ?");
    $stmt->execute([$transaction_id, $user_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Transaction not found or unauthorized: ID=$transaction_id, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validate public key
    if (empty($transaction['public_key']) || $transaction['public_key'] === 'undefined') {
        log_message("Invalid public key in database: ID=$transaction_id, public_key={$transaction['public_key']}, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid public key in transaction'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $transaction['public_key'])) {
        log_message("Invalid public key format: ID=$transaction_id, public_key={$transaction['public_key']}, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid public key format'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validate token mint on network
    $rpc_endpoint = SOLANA_NETWORK === 'testnet' ? 'https://api.testnet.solana.com' : 'https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY;
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $rpc_endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getAccountInfo',
            'params' => [$transaction['token_mint'], ['encoding' => 'jsonParsed']]
        ])
    ]);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $result = json_decode($response, true);
    if ($http_code !== 200 || !isset($result['result']['value']) || $result['result']['value'] === null) {
        log_message("Invalid token mint for network: ID=$transaction_id, token_mint={$transaction['token_mint']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Token mint is invalid or does not exist on ' . SOLANA_NETWORK], JSON_UNESCAPED_UNICODE);
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

    log_message("Transaction fetched: ID=$transaction_id, token_mint={$transaction['token_mint']}, public_key={$transaction['public_key']}, sol_amount={$transaction['sol_amount']}, token_amount={$transaction['token_amount']}, trade_direction={$transaction['trade_direction']}, loop_count={$transaction['loop_count']}, batch_size={$transaction['batch_size']}, slippage={$transaction['slippage']}, delay_seconds={$transaction['delay_seconds']}, status={$transaction['status']}, user_id=$user_id, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'data' => $transaction], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
