<?php
// ============================================================================
// File: make-market/history/get-sub-transactions.php
// Description: Fetch sub-transaction details for a given transaction ID
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';

// Add Security Headers
require_once $root_path . 'make-market/security/auth-headers.php';

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("get-sub-transactions.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection error'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Session start: in config/bootstrap.php
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    log_message("No user ID in session", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get transaction ID
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verify transaction belongs to user
try {
    $stmt = $pdo->prepare("SELECT user_id FROM make_market WHERE id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction || $transaction['user_id'] != $user_id) {
        log_message("Transaction not found or unauthorized: ID=$transaction_id, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fetch sub-transactions
try {
    $stmt = $pdo->prepare("
        SELECT id, loop_number, batch_index, status, txid, error 
        FROM make_market_sub 
        WHERE parent_id = ? 
        ORDER BY loop_number, batch_index
    ");
    $stmt->execute([$transaction_id]);
    $sub_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    log_message("Fetched " . count($sub_transactions) . " sub-transactions for transaction ID=$transaction_id", 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'data' => $sub_transactions], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving sub-transactions'], JSON_UNESCAPED_UNICODE);
}
?>
