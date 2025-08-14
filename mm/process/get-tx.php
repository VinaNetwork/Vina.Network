<?php
// ============================================================================
// File: mm/process/get-tx.php
// Description: Retrieve transaction details from database
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/csrf.php';
require_once $root_path . 'mm/network.php';
require_once $root_path . 'mm/header-auth.php';

// Log request details
$session_id = session_id() ?: 'none';
$headers = apache_request_headers();
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$cookies = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    $csrf_token = isset($_SESSION[CSRF_TOKEN_NAME]) ? $_SESSION[CSRF_TOKEN_NAME] : 'none';
    log_message("get-tx.php: Request received, method=$request_method, uri=$request_uri, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers) . ", CSRF_TOKEN: $csrf_token", 'make-market.log', 'make-market', 'DEBUG');
}

// Khởi tạo session
if (!ensure_session()) {
    log_message("Failed to initialize session for CSRF, method=$request_method, uri=$request_uri, session_id=$session_id, cookies=$cookies", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra CSRF token cho yêu cầu GET
try {
    $csrf_token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : (isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '');
    if (!validate_csrf_token($csrf_token)) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message("CSRF validation failed for GET request, method=$request_method, uri=$request_uri, session_id=$session_id, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("CSRF validation error: " . $e->getMessage() . ", method=$request_method, uri=$request_uri, session_id=$session_id, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Database connection retrieved, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Database connection failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get transaction ID from URL
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($transaction_id <= 0) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Invalid or missing transaction ID: $transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fetch transaction details
try {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $stmt = $pdo->prepare("SELECT id, user_id, public_key, token_mint, sol_amount, token_amount, trade_direction, process_name, loop_count, batch_size, slippage, delay_seconds, status, network FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
    $stmt->execute([$transaction_id, $user_id, SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message("Transaction not found, unauthorized, or network mismatch: ID=$transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found, unauthorized, or network mismatch'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (empty($transaction['public_key']) || $transaction['public_key'] === 'undefined') {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message("Invalid public key in database: ID=$transaction_id, public_key={$transaction['public_key']}, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid public key in transaction'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $transaction['public_key'])) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message("Invalid public key format: ID=$transaction_id, public_key={$transaction['public_key']}, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid public key format'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Ensure defaults
    $transaction['loop_count'] = intval(isset($transaction['loop_count']) ? $transaction['loop_count'] : 1);
    $transaction['batch_size'] = intval(isset($transaction['batch_size']) ? $transaction['batch_size'] : 1);
    $transaction['slippage'] = floatval(isset($transaction['slippage']) ? $transaction['slippage'] : 0.5);
    $transaction['delay_seconds'] = intval(isset($transaction['delay_seconds']) ? $transaction['delay_seconds'] : 1);
    $transaction['sol_amount'] = floatval(isset($transaction['sol_amount']) ? $transaction['sol_amount'] : 0);
    $transaction['token_amount'] = floatval(isset($transaction['token_amount']) ? $transaction['token_amount'] : 0);
    $transaction['trade_direction'] = isset($transaction['trade_direction']) ? $transaction['trade_direction'] : 'buy';
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Transaction fetched: ID=$transaction_id, token_mint={$transaction['token_mint']}, public_key={$transaction['public_key']}, sol_amount={$transaction['sol_amount']}, token_amount={$transaction['token_amount']}, trade_direction={$transaction['trade_direction']}, loop_count={$transaction['loop_count']}, batch_size={$transaction['batch_size']}, slippage={$transaction['slippage']}, delay_seconds={$transaction['delay_seconds']}, status={$transaction['status']}, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'INFO');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $transaction], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Database query failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
