<?php
// ============================================================================
// File: make-market/check-private-key.php
// Description: Endpoint to check if a private key is running a pending process
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';
require_once $root_path . '../vendor/autoload.php';

header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

if (!isset($_SESSION['user_id'])) {
    log_message('Unauthorized access to check-private-key.php', 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = $input['transaction_id'] ?? null;

if (!$transaction_id || !is_numeric($transaction_id)) {
    log_message("Invalid transaction_id: $transaction_id", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT private_key 
        FROM make_market 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$transaction_id, $_SESSION['user_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        log_message("Transaction ID $transaction_id not found or unauthorized for user_id {$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM make_market 
        WHERE private_key = ? AND status = 'pending'
    ");
    $stmt->execute([$transaction['private_key']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    log_message("Private key check for transaction ID $transaction_id: isPending=" . ($result['count'] > 0 ? 'true' : 'false'), 'make-market.log', 'make-market', 'INFO');
    echo json_encode([
        'status' => 'success',
        'isPending' => $result['count'] > 0
    ]);
} catch (Exception $e) {
    log_message("Error checking private key for transaction ID $transaction_id: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
exit;
?>
