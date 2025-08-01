<?php
// ============================================================================
// File: make-market/status.php
// Description: API endpoint to check and update transaction status and details
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

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

// Check session
if (!isset($_SESSION['user_id'])) {
    log_message("No user_id in session", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please log in']);
    exit;
}

// Handle POST request for updating status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        log_message("Invalid JSON input", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
        exit;
    }

    $transaction_id = $input['transactionId'] ?? null;
    $action = $input['action'] ?? null;

    if (!$transaction_id || !is_numeric($transaction_id)) {
        log_message("Invalid transaction ID: $transaction_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
        exit;
    }

    try {
        // Handle delete private_key
        if ($action === 'delete_private_key') {
            $stmt = $pdo->prepare('UPDATE make_market SET private_key = NULL WHERE id = ? AND user_id = ?');
            $stmt->execute([$transaction_id, $_SESSION['user_id']]);
            log_message("Deleted private_key for transaction $transaction_id", 'make-market.log', 'make-market', 'INFO');
            echo json_encode(['status' => 'success', 'message' => 'Private key deleted']);
            exit;
        }

        // Update transaction status
        $status = $input['status'] ?? null;
        $buy_tx_id = $input['buy_tx_id'] ?? null;
        $sell_tx_id = $input['sell_tx_id'] ?? null;
        $error = $input['error'] ?? null;
        $current_loop = isset($input['current_loop']) ? (int)$input['current_loop'] : null;

        if ($status && !in_array($status, ['pending', 'success', 'failed'])) {
            log_message("Invalid status: $status", 'make-market.log', 'make-market', 'ERROR');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
            exit;
        }

        $update_fields = [];
        $params = [];
        if ($status) {
            $update_fields[] = 'status = ?';
            $params[] = $status;
        }
        if ($buy_tx_id) {
            $update_fields[] = 'buy_tx_id = ?';
            $params[] = $buy_tx_id;
        }
        if ($sell_tx_id) {
            $update_fields[] = 'sell_tx_id = ?';
            $params[] = $sell_tx_id;
        }
        if ($error !== null) {
            $update_fields[] = 'error = ?';
            $params[] = $error;
        }
        if ($current_loop !== null) {
            $update_fields[] = 'current_loop = ?';
            $params[] = $current_loop;
        }

        if (!empty($update_fields)) {
            $params[] = $transaction_id;
            $params[] = $_SESSION['user_id'];
            $query = "UPDATE make_market SET " . implode(', ', $update_fields) . " WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            log_message("Transaction status updated: transactionId=$transaction_id, status=$status, error=" . ($error ?? 'NULL'), 'make-market.log', 'make-market', 'INFO');
        }

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        log_message("Error updating transaction status: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error updating transaction status']);
    }
    exit;
}

// Handle GET request for polling status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $transaction_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$transaction_id) {
        log_message("Invalid or missing transaction ID", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing transaction ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT status, error, buy_tx_id, sell_tx_id, current_loop, loop_count
            FROM make_market
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$transaction_id, $_SESSION['user_id']]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transaction) {
            log_message("Transaction ID $transaction_id not found or not authorized for user_id {$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
            echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized']);
            exit;
        }

        // Mock transaction logs (replace with actual logs from database or worker)
        $logs = [];
        if ($transaction['current_loop']) {
            $logs[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => "Loop {$transaction['current_loop']} started",
                'tx_id' => null
            ];
        }
        if ($transaction['buy_tx_id']) {
            $logs[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => 'Buy transaction completed',
                'tx_id' => $transaction['buy_tx_id']
            ];
        }
        if ($transaction['sell_tx_id']) {
            $logs[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => 'Sell transaction completed',
                'tx_id' => $transaction['sell_tx_id']
            ];
        }

        echo json_encode([
            'status' => 'success',
            'transaction' => [
                'status' => $transaction['status'],
                'error' => $transaction['error'],
                'current_loop' => $transaction['current_loop'] ?? 0,
                'loop_count' => $transaction['loop_count'],
                'logs' => $logs
            ]
        ]);
    } catch (PDOException $e) {
        log_message("Error fetching transaction status ID $transaction_id: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Error fetching transaction status']);
        exit;
    }
}

ob_end_flush();
?>
