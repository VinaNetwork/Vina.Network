<?php
// ============================================================================
// File: mm/private-key/delete-private-key.php
// Description: API for deleting private key.
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

// Log request
log_message("delete-private-key.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}", 'private-key-page.log', 'make-market', 'DEBUG');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method: {$_SERVER['REQUEST_METHOD']}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method not supported']);
    exit;
}

// Protect POST requests with CSRF
csrf_protect();

// Database connection
try {
    $pdo = get_db_connection();
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection error']);
    exit;
}

// Get public_key from session
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
if (!$public_key) {
    log_message("Missing public_key in session, redirecting to login", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Session expired, please log in again']);
    exit;
}

// Get user_id from public_key
try {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for public_key: $short_public_key", 'private-key-page.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Account not found']);
        exit;
    }
    $user_id = $account['id'];
} catch (PDOException $e) {
    log_message("Account query error: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Account query error']);
    exit;
}

// Get walletId from POST or JSON body
$walletId = $_POST['walletId'] ?? null;
if (!$walletId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $walletId = $input['walletId'] ?? null;
}
log_message("Received delete request with walletId: " . ($walletId ?? 'null'), 'private-key-page.log', 'make-market', 'DEBUG');
log_message("Raw POST data: " . json_encode($_POST), 'private-key-page.log', 'make-market', 'DEBUG');
log_message("Raw JSON input: " . file_get_contents('php://input'), 'private-key-page.log', 'make-market', 'DEBUG');

if (!$walletId) {
    log_message("Missing walletId in POST data and JSON input", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid wallet ID']);
    exit;
}

// Validate walletId belongs to user
try {
    $stmt = $pdo->prepare("SELECT id FROM private_key WHERE id = ? AND user_id = ?");
    $stmt->execute([$walletId, $user_id]);
    if (!$stmt->fetch()) {
        log_message("Wallet ID $walletId not found or does not belong to user_id=$user_id", 'private-key-page.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Wallet not found']);
        exit;
    }
} catch (PDOException $e) {
    log_message("Wallet validation error: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Wallet validation error']);
    exit;
}

// Delete private key
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("DELETE FROM private_key WHERE id = ? AND user_id = ?");
    $stmt->execute([$walletId, $user_id]);
    $affectedRows = $stmt->rowCount();
    if ($affectedRows === 0) {
        $pdo->rollBack();
        log_message("No rows deleted for walletId=$walletId, user_id=$user_id", 'private-key-page.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete wallet']);
        exit;
    }
    $pdo->commit();
    log_message("Successfully deleted wallet with ID: $walletId for user_id=$user_id", 'private-key-page.log', 'make-market', 'INFO');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Wallet deleted successfully']);
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    log_message("Delete error for walletId=$walletId: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error deleting wallet']);
    exit;
}

ob_end_flush();
?>
