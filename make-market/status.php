<?php
// ============================================================================
// File: make-market/status.php
// Description: Endpoint to update Make Market transaction status
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}
require_once __DIR__ . '/config/bootstrap.php';

// Chỉ chấp nhận POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Đọc JSON từ request body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['transactionId'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Xây dựng câu lệnh update động
    $fields = [];
    $params = [];
    if (isset($input['buy_tx_id'])) {
        $fields[] = 'buy_tx_id = ?';
        $params[] = $input['buy_tx_id'];
    }
    if (isset($input['sell_tx_id'])) {
        $fields[] = 'sell_tx_id = ?';
        $params[] = $input['sell_tx_id'];
    }
    if (isset($input['status'])) {
        $fields[] = 'status = ?';
        $params[] = $input['status'];
    }
    if (isset($input['error'])) {
        $fields[] = 'error = ?';
        $params[] = $input['error'];
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No fields to update']);
        exit;
    }

    $params[] = $input['transactionId'];
    $query = "UPDATE make_market_transactions SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    log_message("Transaction updated: ID={$input['transactionId']}, fields=" . implode(', ', $fields), 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    log_message("Error updating transaction {$input['transactionId']}: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error updating transaction']);
}
?>
