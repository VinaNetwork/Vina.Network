<?php
// ============================================================================
// File: mm/process/get-decimals.php
// Description: Retrieve token decimals from make_market table for a given token mint and network
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

// Initialize logging context
$log_context = [
    'endpoint' => 'get-decimals',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
];

// Start session
if (!ensure_session()) {
    log_message("Failed to initialize session in get-decimals.php, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected in get-decimals.php", 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method in get-decimals.php: {$_SERVER['REQUEST_METHOD']}", 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Request method not supported'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get parameters from POST data
$token_mint = $_POST['tokenMint'] ?? '';
$network = $_POST['network'] ?? 'mainnet';
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
$log_context['token_mint'] = $token_mint;
$log_context['network'] = $network;

// Assign token to $_POST for csrf_protect()
$_POST[CSRF_TOKEN_NAME] = $csrf_token;

// Protect POST requests with CSRF
try {
    csrf_protect();
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("CSRF validation failed in get-decimals.php: {$e->getMessage()}, session_id=" . (session_id() ?: 'none') . ", user_id=$user_id", 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate inputs
if (empty($token_mint) || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $token_mint)) {
    log_message("Invalid token mint in get-decimals.php: $token_mint", 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token mint address'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($network, ['mainnet', 'testnet', 'devnet'])) {
    log_message("Invalid network in get-decimals.php: $network", 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid network'], JSON_UNESCAPED_UNICODE);
    exit;
}

log_message("Parameters received: token_mint=$token_mint, network=$network, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'INFO', $log_context);

try {
    // Get database connection
    $pdo = get_db_connection();
    if (!$pdo) {
        log_message("Failed to connect to database in get-decimals.php", 'make-market.log', 'make-market', 'ERROR', $log_context);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection error'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Query decimals from make_market table
    $stmt = $pdo->prepare("SELECT decimals FROM make_market WHERE token_mint = ? AND network = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$token_mint, $network, $_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        log_message("No decimals found in make_market table for token_mint=$token_mint, network=$network, user_id={$_SESSION['user_id']}, using default=9", 'make-market.log', 'make-market', 'INFO', $log_context);
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'decimals' => 9,
            'message' => 'No decimals found in database, using default value'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $decimals = intval($row['decimals']);
    log_message("Decimals retrieved from make_market table: $decimals for token_mint=$token_mint, network=$network, user_id={$_SESSION['user_id']}, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'INFO', $log_context);

    http_response_code(200);
    // Note: CSRF token is cleared by client-side (process.js) after transaction completion
    echo json_encode([
        'status' => 'success',
        'decimals' => $decimals,
        'message' => 'Decimals retrieved successfully'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    log_message("Failed to retrieve decimals from make_market table: {$e->getMessage()}, token_mint=$token_mint, network=$network, user_id={$_SESSION['user_id']}, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving decimals: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
