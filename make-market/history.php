<?php
// ============================================================================
// File: make-market/history.php
// Description: Fetch transaction history for Make Market
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

// Check session
$public_key = $_SESSION['public_key'] ?? null;
if (!$public_key) {
    log_message("No public key in session, redirecting to login", 'make-market.log', 'make-market', 'INFO');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for session public_key: " . substr($public_key, 0, 4) . "...", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Account not found']);
        exit;
    }

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 10;
    $offset = ($page - 1) * $per_page;

    $stmt = $pdo->prepare("
        SELECT id, process_name, public_key, token_mint, sol_amount, slippage, delay_seconds, 
               loop_count, status, buy_tx_id, sell_tx_id, created_at, error
        FROM make_market 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $account['id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM make_market WHERE user_id = ?");
    $stmt->execute([$account['id']]);
    $total_transactions = $stmt->fetchColumn();
    $total_pages = ceil($total_transactions / $per_page);

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'transactions' => $transactions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_transactions' => $total_transactions
        ]
    ]);
} catch (Exception $e) {
    log_message("Error fetching transaction history: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error fetching transaction history']);
}
?>
