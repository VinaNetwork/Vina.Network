<?php
// ============================================================================
// File: make-market/process/create-tx.php
// Description: Create sub-transaction records for Solana token swap
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';
require_once $root_path . 'make-market/process/network.php';
require_once $root_path . 'make-market/process/auth.php';

// Initialize security headers and authentication
initialize_auth();
if (!perform_auth_check($pdo, $transaction_id)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication or CSRF validation failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("create-tx.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'DEBUG');
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['id']) ? intval($input['id']) : 0;
$sub_transactions = $input['sub_transactions'] ?? null;
$client_network = $input['network'] ?? null;

if ($transaction_id <= 0 || !is_array($sub_transactions) || !in_array($client_network, ['testnet', 'mainnet'])) {
    log_message("Invalid input: transaction_id=$transaction_id, sub_transactions=" . json_encode($sub_transactions) . ", client_network=$client_network, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction data or network'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check network consistency
if ($client_network !== SOLANA_NETWORK) {
    log_message("Network mismatch: client_network=$client_network, server_network=" . SOLANA_NETWORK . ", user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Network mismatch: client ($client_network) vs server (" . SOLANA_NETWORK . ")"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection error'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT user_id, trade_direction, loop_count, batch_size, network FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
    $stmt->execute([$transaction_id, $_SESSION['user_id'], SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Transaction not found, unauthorized, or network mismatch: ID=$transaction_id, user_id={$_SESSION['user_id']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found, unauthorized, or network mismatch'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    log_message("Transaction fetched: ID=$transaction_id, trade_direction={$transaction['trade_direction']}, loop_count={$transaction['loop_count']}, batch_size={$transaction['batch_size']}, network=" . SOLANA_NETWORK . ", user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate sub-transactions
$expected_count = $transaction['trade_direction'] === 'both' 
    ? $transaction['loop_count'] * $transaction['batch_size'] * 2 
    : $transaction['loop_count'] * $transaction['batch_size'];
if (count($sub_transactions) !== $expected_count) {
    log_message("Sub-transaction count mismatch: expected=$expected_count, received=" . count($sub_transactions) . ", user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Sub-transaction count mismatch'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Insert sub-transactions
$sub_transaction_ids = [];
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO make_market_sub (transaction_id, loop_number, batch_index, direction, status, network) VALUES (?, ?, ?, ?, 'pending', ?)");
    foreach ($sub_transactions as $sub_tx) {
        $loop = isset($sub_tx['loop']) ? intval($sub_tx['loop']) : 0;
        $batch_index = isset($sub_tx['batch_index']) ? intval($sub_tx['batch_index']) : 0;
        $direction = isset($sub_tx['direction']) && in_array($sub_tx['direction'], ['buy', 'sell']) ? $sub_tx['direction'] : null;
        if ($loop <= 0 || $batch_index < 0 || !$direction) {
            log_message("Invalid sub-transaction data: loop=$loop, batch_index=$batch_index, direction=$direction, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid sub-transaction data'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt->execute([$transaction_id, $loop, $batch_index, $direction, SOLANA_NETWORK]);
        $sub_transaction_ids[] = $pdo->lastInsertId();
    }
    $pdo->commit();
    log_message("Created " . count($sub_transaction_ids) . " sub-transactions for transaction ID=$transaction_id, network=" . SOLANA_NETWORK . ", user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    $pdo->rollBack();
    log_message("Failed to create sub-transactions: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error creating sub-transactions'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Return success response
echo json_encode([
    'status' => 'success',
    'message' => 'Sub-transactions created successfully',
    'sub_transaction_ids' => $sub_transaction_ids
], JSON_UNESCAPED_UNICODE);
?>
