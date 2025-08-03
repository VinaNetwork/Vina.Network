<?php
// ============================================================================
// File: make-market/process/get-private-key.php
// Description: Endpoint to fetch and decrypt private_key for a transaction
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';

// Chỉ chấp nhận GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$transaction_id = $_GET['transactionId'] ?? null;

if (!$transaction_id || !is_numeric($transaction_id)) {
    log_message("Invalid transaction ID: $transaction_id", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT private_key FROM make_market WHERE id = ?');
    $stmt->execute([$transaction_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !$row['private_key']) {
        log_message("No private_key found for transaction $transaction_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'No private key found']);
        exit;
    }

    // Giải mã private_key
    $decrypted_private_key = openssl_decrypt($row['private_key'], 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
    if ($decrypted_private_key === false) {
        log_message("Failed to decrypt private_key for transaction $transaction_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to decrypt private key']);
        exit;
    }

    log_message("Successfully fetched and decrypted private_key for transaction $transaction_id", 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'privateKey' => $decrypted_private_key]);
} catch (Exception $e) {
    log_message("Error fetching private_key for transaction $transaction_id: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error fetching private key']);
}
?>
