<?php
// ============================================================================
// File: make-market/process/start.php
// Description: Start a transaction by marking as pending and returning transaction details
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';

header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

log_message("start-transaction: Script started, session user_id: " . ($_SESSION['user_id'] ?? 'none'), 'make-market.log', 'make-market', 'DEBUG');

if (!isset($_SESSION['user_id'])) {
    log_message('Unauthorized access to start-transaction.php', 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
log_message("start-transaction: Input received: " . json_encode($input), 'make-market.log', 'make-market', 'DEBUG');
$transaction_id = $input['transaction_id'] ?? null;

if (empty($transaction_id)) {
    log_message("Missing transaction_id in start-transaction.php request", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing transaction_id']);
    exit;
}

try {
    $pdo = get_db_connection();
    log_message("start-transaction: Database connection established for transaction_id: $transaction_id", 'make-market.log', 'make-market', 'INFO');

    // Fetch transaction details
    $stmt = $pdo->prepare("
        SELECT token_mint, sol_amount, slippage, delay, loop_count, batch_size
        FROM make_market
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$transaction_id, $_SESSION['user_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Invalid or unauthorized transaction_id: $transaction_id for user_id: {$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or unauthorized transaction_id']);
        exit;
    }

    // Mark transaction as pending
    $stmt = $pdo->prepare("UPDATE make_market SET status = 'pending', current_loop = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$transaction_id, $_SESSION['user_id']]);
    
    log_message("start-transaction: Transaction ID $transaction_id marked as pending", 'make-market.log', 'make-market', 'INFO');
    echo json_encode([
        'status' => 'success',
        'message' => 'Transaction processing started',
        'transaction' => [
            'token_mint' => $transaction['token_mint'],
            'sol_amount' => $transaction['sol_amount'],
            'slippage' => $transaction['slippage'],
            'delay' => $transaction['delay'],
            'loop_count' => $transaction['loop_count'],
            'batch_size' => $transaction['batch_size']
        ]
    ]);
} catch (Exception $e) {
    log_message("start-transaction: Error for transaction_id $transaction_id: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
exit;
?>
