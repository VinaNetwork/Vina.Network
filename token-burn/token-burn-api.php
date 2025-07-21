<?php
/*
 * File: token-burn/token-burn-api.php
 * Description: API endpoint to check total burned tokens for a given token mint address.
 * Supports detection of both explicit burns and transfers to the 11111111111111111111111111111111 burn address.
 * Created by: Vina Network
 */

define('VINANETWORK_ENTRY', true);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Get token mint address from GET or POST
$mintAddress = $_GET['mint'] ?? $_POST['mint'] ?? '';
$mintAddress = trim($mintAddress);

// Validate address
if (!$mintAddress || strlen($mintAddress) < 32) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid token mint address.']);
    exit;
}

$apiKey = HELIUS_API_KEY;
$endpoint = "https://mainnet.helius-rpc.com/?api-key=$apiKey";

$totalBurned = 0;
$burnTxs = [];

$before = null;
$limit = 1000; // Max per request
$maxIterations = 20; // prevent infinite loop
$iterations = 0;

do {
    $payload = [
        'jsonrpc' => '2.0',
        'id' => 'burn-check',
        'method' => 'getParsedTransactionsByMint',
        'params' => [
            'mint' => $mintAddress,
            'options' => [
                'limit' => $limit,
                'before' => $before
            ]
        ]
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        echo json_encode(['error' => 'Invalid API response from Helius.']);
        exit;
    }

    $data = json_decode($response, true);
    if (!isset($data['result']) || !is_array($data['result'])) {
        echo json_encode(['error' => 'Unexpected response format.']);
        exit;
    }

    $transactions = $data['result'];
    if (empty($transactions)) {
        break;
    }

    foreach ($transactions as $tx) {
        if (!isset($tx['tokenTransfers']) || !is_array($tx['tokenTransfers'])) {
            continue;
        }

        foreach ($tx['tokenTransfers'] as $transfer) {
            $to = $transfer['toUserAccount'] ?? '';
            $from = $transfer['fromUserAccount'] ?? '';
            $amount = abs($transfer['tokenAmount']);

            $isToBurnWallet = ($to === '11111111111111111111111111111111');
            $isExplicitBurn = (empty($to) && $transfer['tokenAmount'] < 0);

            if ($isToBurnWallet || $isExplicitBurn) {
                $totalBurned += $amount;

                $burnTxs[] = [
                    'signature' => $tx['signature'],
                    'amount' => $amount,
                    'slot' => $tx['slot'],
                    'timestamp' => $tx['timestamp'],
                    'type' => $isToBurnWallet ? 'ToBurnWallet' : 'ExplicitBurn'
                ];
            }
        }
    }

    $before = end($transactions)['signature'] ?? null;
    $iterations++;
} while ($before && $iterations < $maxIterations);

// Optional breakdown
$toBurnWallet = 0;
$explicitBurn = 0;

foreach ($burnTxs as $b) {
    if ($b['type'] === 'ToBurnWallet') $toBurnWallet += $b['amount'];
    if ($b['type'] === 'ExplicitBurn') $explicitBurn += $b['amount'];
}

echo json_encode([
    'mint' => $mintAddress,
    'total_burned' => $totalBurned,
    'to_burn_wallet' => $toBurnWallet,
    'explicit_burn' => $explicitBurn,
    'burn_transactions' => array_map(fn($tx) => $tx['signature'], $burnTxs)
]);
exit;
