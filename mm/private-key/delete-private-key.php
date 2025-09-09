<?php
// ============================================================================
// File: mm/private-key/delete-private-key.php
// Description: API for private key page.
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method not supported']);
    exit;
}

csrf_protect();
$pdo = get_db_connection();
$walletId = $_POST['walletId'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$walletId || !$user_id) {
    log_message("Missing walletId or user_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Missing information']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM private_key WHERE id = ? AND user_id = ?");
    $stmt->execute([$walletId, $user_id]);
    if ($stmt->rowCount() > 0) {
        log_message("Deleted wallet ID=$walletId for user_id=$user_id", 'make-market.log', 'make-market', 'INFO');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Wallet deleted successfully']);
    } else {
        log_message("Wallet ID=$walletId not found", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Wallet not found']);
    }
} catch (PDOException $e) {
    log_message("Error deleting wallet: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error deleting wallet']);
}
exit;
?>
