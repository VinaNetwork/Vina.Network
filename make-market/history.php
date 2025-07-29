<?php
// ============================================================================
// File: make-market/history.php
// Description: Endpoint to fetch Make Market transaction history
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

require_once __DIR__ . '/../config/bootstrap.php';

// Chỉ chấp nhận GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

// Check session
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key && strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
if (!$public_key) {
    log_message("No public key in session for history fetch", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
if (!preg_match('/^[A-Za-z0-9]{32,44}$/', $public_key)) {
    log_message("Invalid public key format: $short_public_key", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid public key format']);
    exit;
}
if (strlen($public_key) > 255) {
    log_message("Public key too long for history fetch: $short_public_key", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Public key too long']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT id, process_name, token_mint, sol_amount, slippage, delay_seconds, 
               loop_count, status, buy_tx_id, sell_tx_id, created_at
        FROM make_market 
        WHERE public_key = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$public_key]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    log_message("Fetched " . count($transactions) . " transactions for public_key: $short_public_key", 'make-market.log', 'make-market', 'INFO');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'transactions' => $transactions]);
} catch (Exception $e) {
    log_message("Error fetching transaction history: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error fetching transaction history']);
}
?>
