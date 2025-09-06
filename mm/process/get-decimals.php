<?php
// ============================================================================
// File: mm/process/get-decimals.php
// Description: Retrieve token decimals from make_market table for a given token mint and network
// Created by: Vina Network
// ============================================================================

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
    log_message("Failed to initialize session in get-decimals.php, session_id=" . (session_id() ?: 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected in get-decimals.php", 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method in get-decimals.php: {$_SERVER['REQUEST_METHOD']}", 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Request method not supported'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check X-Auth-Token
$headers = apache_request_headers();
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;
if ($authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=" . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ", session_id=" . (session_id() ?: 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get parameters from POST data
$input = json_decode(file_get_contents('php://input'), true);
$token_mint = isset($input['tokenMint']) ? trim($input['tokenMint']) : '';
$network = isset($input['network']) ? trim($input['network']) : 'mainnet';
$log_context['token_mint'] = $token_mint;
$log_context['network'] = $network;

// Validate inputs
if (empty($token_mint) || !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $token_mint)) {
    log_message("Invalid token mint format in get-decimals.php: $token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token mint address format'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($network, ['mainnet', 'devnet'])) {
    log_message("Invalid network in get-decimals.php: $network, token_mint=$token_mint, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid network'], JSON_UNESCAPED_UNICODE);
    exit;
}

log_message("Parameters received: token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'INFO', $log_context);

try {
    // Get database connection
    $pdo = get_db_connection();
    if (!$pdo) {
        log_message("Failed to connect to database in get-decimals.php, token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection error'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Query decimals from make_market table
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    $stmt = $pdo->prepare("SELECT decimals FROM make_market WHERE token_mint = ? AND network = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$token_mint, $network, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !isset($row['decimals'])) {
        log_message(
            "No decimals found in make_market table for token_mint=$token_mint, network=$network, user_id=$user_id, session_id=" . (session_id() ?: 'none'),
            'process.log',
            'make-market',
            'ERROR',
            $log_context
        );
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => "No decimals found for token_mint=$token_mint, network=$network, user_id=$user_id"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $decimals = intval($row['decimals']);
    log_message(
        "Decimals retrieved from make_market table: $decimals for token_mint=$token_mint, network=$network, user_id=$user_id, session_id=" . (session_id() ?: 'none'),
        'process.log',
        'make-market',
        'INFO',
        $log_context
    );

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'decimals' => $decimals,
        'message' => 'Decimals retrieved from database'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    log_message(
        "Failed to retrieve decimals from make_market table: {$e->getMessage()}, token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
        'process.log',
        'make-market',
        'ERROR',
        $log_context
    );
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving decimals: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
