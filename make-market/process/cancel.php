<?php
// ============================================================================
// File: make-market/process/cancel.php
// Description: API endpoint to cancel a pending transaction in make_market table
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';

// Add Security Headers
header("Content-Type: application/json");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Error reporting
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

// Database connection
try {
    $pdo = get_db_connection();
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Check session for authentication
if (!isset($_SESSION['user_id'])) {
    log_message("No user_id in session", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please log in']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method: {$_SERVER['REQUEST_METHOD']}", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);
$transactionId = $input['id'] ?? null;

if (!$transactionId || !is_numeric($transactionId)) {
    log_message("Invalid or missing transaction ID", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing transaction ID']);
    exit;
}

// Verify transaction belongs to user and is pending
try {
    $stmt = $pdo->prepare("
        SELECT status 
        FROM make_market 
        WHERE id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$transactionId, $_SESSION['user_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        log_message("Transaction ID $transactionId not found or not pending for user_id {$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or not pending']);
        exit;
    }

    // Update transaction status to Canceled
    $stmt = $pdo->prepare("
        UPDATE make_market 
        SET status = 'Canceled', updated_at = NOW()
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$transactionId, $_SESSION['user_id']]);

    log_message("Transaction ID $transactionId canceled by user_id {$_SESSION['user_id']}", 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'message' => 'Transaction canceled successfully']);
} catch (PDOException $e) {
    log_message("Error canceling transaction ID $transactionId: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Error canceling transaction']);
    exit;
}

ob_end_flush();
?>
