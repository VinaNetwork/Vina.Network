<?php
// ============================================================================
// File: mm/process/get-status.php
// Description: Update transaction status and error in make_market table
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/csrf.php';
require_once $root_path . 'mm/header-auth.php';
require_once $root_path . 'mm/network.php';

// Initialize logging context
$log_context = [
    'endpoint' => 'get-status',
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
    $csrf_token = isset($_SESSION[CSRF_TOKEN_NAME]) ? $_SESSION[CSRF_TOKEN_NAME] : 'none';
    log_message("get-status.php: Request received, method=$request_method, uri=$request_uri, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers) . ", CSRF_TOKEN: $csrf_token", 'make-market.log', 'make-market', 'DEBUG', $log_context);
}

// Kiểm tra phương thức POST
if ($request_method !== 'POST') {
    log_message("Invalid request method: $request_method, uri=$request_uri, session_id=$session_id", 'make-market.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Khởi tạo session
if (!ensure_session()) {
    log_message("Failed to initialize session, method=$request_method, uri=$request_uri, session_id=$session_id, cookies=$cookies", 'make-market.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra CSRF token
try {
    csrf_protect();
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("CSRF validation failed: " . $e->getMessage() . ", method=$request_method, uri=$request_uri, session_id=$session_id, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['id']) ? intval($input['id']) : 0;
$status = isset($input['status']) ? $input['status'] : '';
$error = isset($input['error']) ? $input['error'] : null;
$log_context['transaction_id'] = $transaction_id;
$log_context['status'] = $status;

// Validate input
if ($transaction_id <= 0) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Invalid or missing transaction ID: $transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array($status, ['pending', 'processing', 'failed', 'success', 'canceled'])) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Invalid status: $status, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid status'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Database connection retrieved, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'INFO', $log_context);
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Database connection failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check transaction ownership
try {
    $stmt = $pdo->prepare("SELECT user_id FROM make_market WHERE id = ? AND network = ?");
    $stmt->execute([$transaction_id, SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction || $transaction['user_id'] != (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0)) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message("Transaction not found or unauthorized: ID=$transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR', $log_context);
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (PDOException $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Transaction ownership check failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking transaction ownership'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Update transaction
try {
    $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ? AND network = ?");
    $stmt->execute([$status, $error, $transaction_id, SOLANA_NETWORK]);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Transaction status updated: ID=$transaction_id, status=$status, error=" . ($error !== null ? $error : 'none') . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'INFO', $log_context);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Database update failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error updating transaction'], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
