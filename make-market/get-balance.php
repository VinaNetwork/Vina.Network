<?php
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
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['walletAddress'])) {
    $walletAddress = trim($_GET['walletAddress']);
    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletAddress)) {
        log_message("get-balance: Invalid wallet address: $walletAddress", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['error' => 'Invalid wallet address']);
        exit;
    }

    $params = [
        'ownerAddress' => $walletAddress,
        'page' => 1,
        'limit' => 1000,
        'displayOptions' => [
            'showFungible' => true,
            'showNativeBalance' => true
        ]
    ];
    $data = callMarketAPI('getAssetsByOwner', $params);

    if (isset($data['error'])) {
        log_message("get-balance: Failed to fetch balance for $walletAddress: {$data['error']}", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['error' => $data['error']]);
        exit;
    }

    if (isset($data['result']['nativeBalance']['lamports'])) {
        $response = [
            'nativeBalance' => [
                'lamports' => $data['result']['nativeBalance']['lamports'],
                'total_price' => $data['result']['nativeBalance']['total_price'] ?? 0
            ],
            'tokens' => $data['result']['items'] ?? []
        ];
        log_message("get-balance: Success for $walletAddress: " . json_encode($response), 'make-market.log', 'make-market', 'INFO');
        echo json_encode($response);
    } else {
        log_message("get-balance: Invalid response structure for $walletAddress: " . json_encode($data), 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['error' => 'Invalid response structure']);
    }
    exit;
} else {
    log_message("get-balance: Invalid request", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['error' => 'Invalid request']);
    exit;
}
?>
