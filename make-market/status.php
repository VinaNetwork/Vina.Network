<?php
// ============================================================================
// File: make-market/status.php
// Description: Endpoint to update Make Market transaction status and delete private_key
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}
require_once __DIR__ . '/../config/bootstrap.php';

// Chỉ chấp nhận POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Đọc dữ liệu JSON từ request body
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
    $pdo = get_db_connection();

    // Xử lý xóa private_key
    if ($action === 'delete_private_key') {
        $stmt = $pdo->prepare('UPDATE make_market SET private_key = NULL WHERE id = ?');
        $stmt->execute([$transaction_id]);
        log_message("Deleted private_key for transaction $transaction_id", 'make-market.log', 'make-market', 'INFO');
        echo json_encode(['status' => 'success', 'message' => 'Private key deleted']);
        exit;
    }

    // Cập nhật trạng thái giao dịch
    $status = $input['status'] ?? null;
    $buy_tx_id = $input['buy_tx_id'] ?? null;
    $sell_tx_id = $input['sell_tx_id'] ?? null;
    $error = $input['error'] ?? null;

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

    if (!empty($update_fields)) {
        $params[] = $transaction_id;
        $query = "UPDATE make_market SET " . implode(', ', $update_fields) . " WHERE id = ?";
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
?>
