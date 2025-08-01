<?php
// ============================================================================
// File: make-market/get-balance.php
// Description: Endpoint to fetch SOL balance using callMarketAPI for Make Market
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';
require_once __DIR__ . '/mm-api.php';

header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

if (!isset($_SESSION['user_id'])) {
    log_message('Unauthorized access to get-balance.php', 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['public_key'])) {
    $public_key = trim($_GET['public_key']);
    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $public_key)) {
        log_message("get-balance: Invalid public key: $public_key", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid public key']);
        exit;
    }

    $params = [
        'ownerAddress' => $public_key,
        'page' => 1,
        'limit' => 1000,
        'displayOptions' => [
            'showFungible' => true,
            'showNativeBalance' => true
        ]
    ];
    $data = callMarketAPI('getAssetsByOwner', $params);

    if (isset($data['error'])) {
        log_message("get-balance: Failed to fetch balance for $public_key: {$data['error']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $data['error']]);
        exit;
    }

    if (isset($data['result']['nativeBalance']['lamports'])) {
        $balance = $data['result']['nativeBalance']['lamports'] / 1e9; // Convert lamports to SOL
        $response = [
            'status' => 'success',
            'balance' => $balance,
            'nativeBalance' => [
                'lamports' => $data['result']['nativeBalance']['lamports'],
                'total_price' => $data['result']['nativeBalance']['total_price'] ?? 0
            ],
            'tokens' => $data['result']['items'] ?? []
        ];
        log_message("get-balance: Success for $public_key: $balance SOL", 'make-market.log', 'make-market', 'INFO');
        echo json_encode($response);
    } else {
        log_message("get-balance: Invalid response structure for $public_key: " . json_encode($data), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid response structure']);
    }
} else {
    log_message("get-balance: Invalid request, expected GET with public_key", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
exit;
?>
