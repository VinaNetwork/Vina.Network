<?php
// ============================================================================
// File: make-market/check-private-key.php
// Description: Endpoint to check if a private key is running a pending process
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

header('Content-Type: application/json');
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

try {
    $transactionId = $_GET['transactionId'] ?? null;
    if (!$transactionId || !is_numeric($transactionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
        exit;
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT private_key 
        FROM make_market 
        WHERE id = ?
    ");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
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

    echo json_encode([
        'status' => 'success',
        'isPending' => $result['count'] > 0
    ]);
} catch (Exception $e) {
    log_message("Error checking private key status: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
?>
