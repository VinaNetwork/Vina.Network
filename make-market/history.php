<?php
// ============================================================================
// File: make-market/history.php
// Description: Endpoint to fetch Make Market transaction history with pagination
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

require_once __DIR__ . '/../config/bootstrap.php';

// Chỉ chấp nhận GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
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
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
if (strlen($public_key) > 255) {
    log_message("Public key too long for history fetch: $short_public_key", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
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

    // Xử lý phân trang
    $per_page = isset($_GET['per_page']) && is_numeric($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $per_page;

    // Đếm tổng số giao dịch
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM make_market WHERE public_key = ?");
    $countStmt->execute([$public_key]);
    $total_transactions = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_transactions / $per_page);

    // Lấy giao dịch theo trang
    $stmt = $pdo->prepare("
        SELECT id, process_name, token_mint, sol_amount, slippage, delay_seconds, 
               loop_count, status, buy_tx_id, sell_tx_id, created_at, error
        FROM make_market 
        WHERE public_key = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$public_key, $per_page, $offset]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    log_message("Fetched " . count($transactions) . " transactions for public_key: $short_public_key, page: $page, per_page: $per_page", 'make-market.log', 'make-market', 'INFO');
    echo json_encode([
        'status' => 'success',
        'transactions' => $transactions,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
            'total_transactions' => $total_transactions
        ]
    ]);
} catch (Exception $e) {
    log_message("Error fetching transaction history: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error fetching transaction history']);
}
?>
