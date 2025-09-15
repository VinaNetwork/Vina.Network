<?php
// ============================================================================
// File: mm/endpoints-p/create-tx.php
// Description: Create sub-transaction records for Solana token swap
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

// Initialize logging context
$log_context = [
    'endpoint' => 'create-tx',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
];

// Log request details
$session_id = session_id() ?: 'none';
$headers = apache_request_headers();
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$cookies = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("create-tx.php: Request received, method=$request_method, uri=$request_uri, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers), 'process.log', 'make-market', 'DEBUG', $log_context);
}

// Check POST method
if ($request_method !== 'POST') {
    log_message("Invalid request method: $request_method, uri=$request_uri, session_id=$session_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check X-Auth-Token
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;
if ($authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=$request_uri, session_id=$session_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Initialize session
if (!ensure_session()) {
    log_message("Failed to initialize session, method=$request_method, uri=$request_uri, session_id=$session_id, cookies=$cookies", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($input['id']) ? intval($input['id']) : 0);
$sub_transactions = isset($input['sub_transactions']) ? $input['sub_transactions'] : null;
$client_network = isset($input['network']) ? $input['network'] : null;
$log_context['transaction_id'] = $transaction_id;
$log_context['client_network'] = $client_network;

if ($transaction_id <= 0 || !is_array($sub_transactions) || !in_array($client_network, ['mainnet', 'devnet'])) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Invalid input: transaction_id=$transaction_id, sub_transactions=" . json_encode($sub_transactions) . ", client_network=$client_network, user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction data or network'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check network consistency
if ($client_network !== SOLANA_NETWORK) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Network mismatch: client_network=$client_network, server_network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Network mismatch: client ($client_network) vs server (" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ")"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Database connection retrieved, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'INFO', $log_context);
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Database connection failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection error'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fetch transaction details
try {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $stmt = $pdo->prepare("SELECT user_id, trade_direction, loop_count, batch_size, network FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
    $stmt->execute([$transaction_id, $user_id, SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message("Transaction not found, unauthorized, or network mismatch: ID=$transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found, unauthorized, or network mismatch'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    log_message("Transaction fetched: ID=$transaction_id, trade_direction={$transaction['trade_direction']}, loop_count={$transaction['loop_count']}, batch_size={$transaction['batch_size']}, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", user_id=$user_id", 'process.log', 'make-market', 'INFO', $log_context);
} catch (PDOException $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Database query failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate sub-transactions
$expected_count = $transaction['trade_direction'] === 'both' 
    ? $transaction['loop_count'] * $transaction['batch_size'] * 2 
    : $transaction['loop_count'] * $transaction['batch_size'];
if (count($sub_transactions) !== $expected_count) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Sub-transaction count mismatch: expected=$expected_count, received=" . count($sub_transactions) . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Sub-transaction count mismatch'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Insert sub-transactions
$sub_transaction_ids = [];
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO make_market_sub (parent_id, loop_number, batch_index, direction, status, network, created_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
    foreach ($sub_transactions as $sub_tx) {
        $loop = isset($sub_tx['loop']) ? intval($sub_tx['loop']) : 0;
        $batch_index = isset($sub_tx['batch_index']) ? intval($sub_tx['batch_index']) : 0;
        $direction = isset($sub_tx['direction']) && in_array($sub_tx['direction'], ['buy', 'sell']) ? $sub_tx['direction'] : null;
        if ($loop <= 0 || $batch_index < 0 || !$direction) {
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
            log_message("Invalid sub-transaction data: loop=$loop, batch_index=$batch_index, direction=$direction, user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
            $pdo->rollBack();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid sub-transaction data'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt->execute([$transaction_id, $loop, $batch_index, $direction, SOLANA_NETWORK]);
        $sub_transaction_ids[] = $pdo->lastInsertId();
    }
    $pdo->commit();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Created " . count($sub_transaction_ids) . " sub-transactions for transaction ID=$transaction_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", user_id=$user_id", 'process.log', 'make-market', 'INFO', $log_context);
} catch (PDOException $e) {
    $pdo->rollBack();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Failed to create sub-transactions: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error creating sub-transactions'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Return success response
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Sub-transactions created successfully',
    'sub_transaction_ids' => $sub_transaction_ids
], JSON_UNESCAPED_UNICODE);
?>
