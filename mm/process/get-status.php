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

// Khởi tạo session
if (!ensure_session()) {
    $session_id = session_id() ?: 'none';
    log_message("Failed to initialize session for CSRF, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", cookies=" . (isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none') . ", session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra CSRF token cho yêu cầu POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_protect(false); // Chỉ kiểm tra token, không tạo mới
    } catch (Exception $e) {
        $session_id = session_id() ?: 'none';
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        $log_context = [
            'provided' => isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : 'none',
            'expected' => isset($_SESSION[CSRF_TOKEN_NAME]) ? $_SESSION[CSRF_TOKEN_NAME] : 'none',
            'session_id' => $session_id,
            'cookies' => isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none',
            'headers' => apache_request_headers()
        ];
        log_message("CSRF validation failed: {$e->getMessage()}, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", details=" . json_encode($log_context), 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Log request info
$session_id = session_id() ?: 'none';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("get-status.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none') . ", session_id=$session_id, CSRF_TOKEN: " . (isset($_SESSION[CSRF_TOKEN_NAME]) ? $_SESSION[CSRF_TOKEN_NAME] : 'none'), 'make-market.log', 'make-market', 'DEBUG');
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none') . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none') . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['id']) ? intval($input['id']) : 0;
$status = isset($input['status']) ? $input['status'] : '';
$error = isset($input['error']) ? $input['error'] : null;

// Validate input
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID: $transaction_id, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none') . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array($status, ['pending', 'processing', 'failed', 'success', 'canceled'])) {
    log_message("Invalid status: $status, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none') . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid status'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check transaction ownership
try {
    $stmt = $pdo->prepare("SELECT user_id FROM make_market WHERE id = ? AND network = ?");
    $stmt->execute([$transaction_id, SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction || $transaction['user_id'] != (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0)) {
        log_message("Transaction not found or unauthorized: ID=$transaction_id, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none') . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (PDOException $e) {
    log_message("Transaction ownership check failed: {$e->getMessage()}, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none') . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking transaction ownership'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Update transaction
try {
    $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ? AND network = ?");
    $stmt->execute([$status, $error, $transaction_id, SOLANA_NETWORK]);
    log_message("Transaction status updated: ID=$transaction_id, status=$status, error=" . ($error !== null ? $error : 'none') . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none') . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id", 'make-market.log', 'make-market', 'INFO');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    log_message("Database update failed: {$e->getMessage()}, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none') . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error updating transaction'], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
