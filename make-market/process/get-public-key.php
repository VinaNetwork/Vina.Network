<?php
// ============================================================================
// File: make-market/process/get-public-key.php
// Description: Retrieve public key from database for a transaction
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: $csp_base");
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("get-public-key.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
}

// Get transaction ID from URL path
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', rtrim($request_uri, '/'));
$transaction_id = end($path_parts);
$transaction_id = is_numeric($transaction_id) ? intval($transaction_id) : 0;
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID from URL path: $request_uri", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Fetch public key from transaction
try {
    $stmt = $pdo->prepare("SELECT user_id, public_key FROM make_market WHERE id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction || $transaction['user_id'] != $_SESSION['user_id']) {
        log_message("Transaction not found or unauthorized: ID=$transaction_id, session_user_id=" . ($_SESSION['user_id'] ?? 'none'), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized']);
        exit;
    }
    if (empty($transaction['public_key']) || $transaction['public_key'] === 'undefined') {
        log_message("Invalid public key: ID=$transaction_id, public_key={$transaction['public_key']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid public key in transaction']);
        exit;
    }
    if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $transaction['public_key'])) {
        log_message("Invalid public key format: ID=$transaction_id, public_key={$transaction['public_key']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid public key format']);
        exit;
    }
    log_message("Public key retrieved: ID=$transaction_id, public_key={$transaction['public_key']}", 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'public_key' => $transaction['public_key']]);
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving public key: ' . $e->getMessage()]);
    exit;
}
?>
