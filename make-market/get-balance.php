<?php
// ============================================================================
// File: make-market/get-balance.php
// Description: Endpoint to fetch SOL balance using Helius RPC for Make Market
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';
require_once $root_path . 'make-market/mm-api.php'; // Include mm-api.php for callMarketAPI

header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log ngay đầu file
log_message("get-balance: Script started", 'make-market.log', 'make-market', 'DEBUG');

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

log_message("get-balance: File accessed, session user_id: " . ($_SESSION['user_id'] ?? 'none'), 'make-market.log', 'make-market', 'DEBUG');

if (!isset($_SESSION['user_id'])) {
    log_message('Unauthorized access to get-balance.php', 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
log_message("get-balance: Input received: " . json_encode($input), 'make-market.log', 'make-market', 'DEBUG');
$public_key = $input['public_key'] ?? '';

if (empty($public_key)) {
    log_message("Missing public_key in get-balance.php request", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing public_key']);
    exit;
}

// Validate public key format
if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $public_key)) {
    log_message("Invalid public key format: $public_key", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid public key format']);
    exit;
}

// Call Helius API using callMarketAPI from mm-api.php
try {
    log_message("get-balance: Preparing to call callMarketAPI for public_key $public_key", 'make-market.log', 'make-market', 'DEBUG');
    $params = [
        'ownerAddress' => $public_key,
        'page' => 1,
        'limit' => 1000,
        'displayOptions' => [
            'showNativeBalance' => true
        ]
    ];
    log_message("get-balance: Calling callMarketAPI with params: " . json_encode($params), 'make-market.log', 'make-market', 'DEBUG');
    $result = callMarketAPI('getAssetsByOwner', $params);
    
    log_message("get-balance: API response for public_key $public_key: " . json_encode($result), 'make-market.log', 'make-market', 'DEBUG');
    
    if (isset($result['error'])) {
        log_message("get-balance: API error for public_key $public_key: {$result['error']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $result['error']]);
        exit;
    }

    if (isset($result['result']['nativeBalance']['lamports'])) {
        $balance = $result['result']['nativeBalance']['lamports'] / 1e9; // Convert lamports to SOL
        log_message("get-balance: Balance check passed for public_key $public_key: $balance SOL", 'make-market.log', 'make-market', 'INFO');
        echo json_encode(['status' => 'success', 'balance' => $balance]);
    } else {
        log_message("get-balance: Invalid response structure for public_key $public_key: " . json_encode($result), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid response structure']);
        exit;
    }
} catch (Exception $e) {
    log_message("get-balance: Error checking balance for public_key $public_key: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}
?>
