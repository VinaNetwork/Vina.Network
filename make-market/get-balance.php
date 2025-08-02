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

$input = json_decode(file_get_contents('php://input'), true);
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

// Call mm-api.php to get balance
try {
    $response = file_get_contents($root_path . 'make-market/mm-api.php', false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode([
                'endpoint' => 'getBalance',
                'params' => [$public_key]
            ])
        ]
    ]));
    $data = json_decode($response, true);

    if ($data['status'] === 'error') {
        log_message("Balance check failed for public_key $public_key: {$data['message']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $data['message']]);
        exit;
    }

    if (isset($data['result']['result']['value'])) {
        $balance = $data['result']['result']['value'] / 1e9; // Convert lamports to SOL
        log_message("Balance check passed for public_key $public_key: $balance SOL", 'make-market.log', 'make-market', 'INFO');
        echo json_encode(['status' => 'success', 'balance' => $balance]);
    } else {
        log_message("Invalid response structure for public_key $public_key: " . json_encode($data), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid response structure']);
    }
} catch (Exception $e) {
    log_message("Error checking balance for public_key $public_key: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
exit;
?>
