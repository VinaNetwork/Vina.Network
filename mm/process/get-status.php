<?php
// ============================================================================
// File: mm/process/get-status.php
// Description: Update transaction status, error, tx_hash, and explorer_url in make_market table
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

// Initialize logging context
$log_context = [
    'endpoint' => 'get-status',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
];

// Log request details
$session_id = session_id() ?: 'none';
$headers = apache_request_headers();
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$cookies = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message(
        "get-status.php: Request received, method=$request_method, uri=$request_uri, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers),
        'process.log',
        'make-market',
        'DEBUG',
        $log_context
    );
}

// Check POST method
if ($request_method !== 'POST') {
    log_message("Invalid request method: $request_method, uri=$request_uri, session_id=$session_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check X-Auth-Token
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;
if ($authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=$request_uri, session_id=$session_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Initialize session
if (!ensure_session()) {
    log_message("Failed to initialize session, method=$request_method, uri=$request_uri, session_id=$session_id, cookies=$cookies", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['id']) ? intval($input['id']) : 0;
$status = isset($input['status']) ? trim($input['status']) : '';
$error = isset($input['error']) ? trim($input['error']) : null;
$tx_hash = isset($input['tx_hash']) ? trim($input['tx_hash']) : null;
$explorer_url = isset($input['explorer_url']) ? trim($input['explorer_url']) : null;
$log_context['transaction_id'] = $transaction_id;
$log_context['status'] = $status;

// Validate input
if ($transaction_id <= 0) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message(
        "Invalid or missing transaction ID: $transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'process.log',
        'make-market',
        'ERROR',
        $log_context
    );
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array($status, ['pending', 'processing', 'failed', 'success', 'canceled', 'partial'])) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message(
        "Invalid status: $status, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'process.log',
        'make-market',
        'ERROR',
        $log_context
    );
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid status'], JSON_UNESCAPED_UNICODE);
    exit;
}
// Validate tx_hash (if provided)
if ($tx_hash !== null && !preg_match('/^[A-Za-z0-9]{43,88}$/', $tx_hash)) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message(
        "Invalid tx_hash: $tx_hash, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'process.log',
        'make-market',
        'ERROR',
        $log_context
    );
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction hash'], JSON_UNESCAPED_UNICODE);
    exit;
}
// Validate explorer_url (if provided)
if ($explorer_url !== null && !filter_var($explorer_url, FILTER_VALIDATE_URL)) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message(
        "Invalid explorer_url: $explorer_url, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'process.log',
        'make-market',
        'ERROR',
        $log_context
    );
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid explorer URL'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message(
        "Database connection retrieved, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'process.log',
        'make-market',
        'INFO',
        $log_context
    );
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message(
        "Database connection failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'process.log',
        'make-market',
        'ERROR',
        $log_context
    );
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check transaction ownership
try {
    $stmt = $pdo->prepare("SELECT user_id FROM make_market WHERE id = ? AND network = ?");
    $stmt->execute([$transaction_id, SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction || $transaction['user_id'] != (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0)) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message(
            "Transaction not found or unauthorized: ID=$transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
            'process.log',
            'make-market',
            'ERROR',
            $log_context
        );
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (PDOException $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message(
        "Transaction ownership check failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'process.log',
        'make-market',
        'ERROR',
        $log_context
    );
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking transaction ownership'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Update transaction
try {
    $params = [$status, $error, $transaction_id, SOLANA_NETWORK];
    $sql = "UPDATE make_market SET status = ?, error = ?";
    if ($tx_hash !== null) {
        $sql .= ", tx_hash = ?";
        $params[] = $tx_hash;
    }
    if ($explorer_url !== null) {
        $sql .= ", explorer_url = ?";
        $params[] = $explorer_url;
    }
    $sql .= " WHERE id = ? AND network = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    $short_tx_hash = $tx_hash ? substr($tx_hash, 0, 8) . '...' : 'none';
    $short_explorer_url = $explorer_url ? substr($explorer_url, 0, 20) . '...' : 'none';
    log_message(
        "Transaction status updated: ID=$transaction_id, status=$status, error=" . ($error !== null ? $error : 'none') . ", tx_hash=$short_tx_hash, explorer_url=$short_explorer_url, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'process.log',
        'make-market',
        'INFO',
        $log_context
    );
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message(
        "Database update failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'process.log',
        'make-market',
        'ERROR',
        $log_context
    );
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error updating transaction'], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
