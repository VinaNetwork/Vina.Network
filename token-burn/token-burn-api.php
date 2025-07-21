<?php
// ============================================================================
// File: token-burn/token-burn-api.php
// Description: Backend API to calculate burned tokens for a given mint address.
// Created by: Vina Network
// ============================================================================

define('VINANETWORK_ENTRY', true);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$mint = trim($input['address'] ?? '');

if (!$mint) {
    echo json_encode(['error' => 'Missing token mint address']);
    exit;
}

// Call Helius API: getSignaturesForAsset
$rpcUrl = 'https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY;
$payload = [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'getSignaturesForAsset',
    'params' => (object)[
        'id' => $mint,
        'limit' => 1000
    ]
];

$ch = curl_init($rpcUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (!isset($data['result'])) {
    echo json_encode(['error' => 'Invalid API response from Helius.']);
    exit;
}

$totalBurned = 0;
$toBurnWallet = 0;
$explicitBurn = 0;

foreach ($data['result'] as $tx) {
    if (!isset($tx['tokenTransfers'])) continue;
    foreach ($tx['tokenTransfers'] as $transfer) {
        // Trường hợp gửi về ví 111111... (burn address)
        if ($transfer['toUserAccount'] === '11111111111111111111111111111111') {
            $toBurnWallet += $transfer['tokenAmount'];
            $totalBurned += $transfer['tokenAmount'];
        }
        // Trường hợp token bị giảm mà không có người nhận
        elseif ($transfer['toUserAccount'] === null && $transfer['tokenAmount'] < 0) {
            $explicitBurn += abs($transfer['tokenAmount']);
            $totalBurned += abs($transfer['tokenAmount']);
        }
    }
}

echo json_encode([
    'total_burned' => $totalBurned,
    'to_burn_wallet' => $toBurnWallet,
    'explicit_burn' => $explicitBurn
]);
