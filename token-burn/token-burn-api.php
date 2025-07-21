<?php
// ============================================================================
// File: token-burn/token-burn-api.php
// Description: Detect burned tokens across all pages using Helius API.
// ============================================================================

define('VINANETWORK_ENTRY', true);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Lấy địa chỉ mint từ POST
$input = json_decode(file_get_contents('php://input'), true);
$mintAddress = trim($input['address'] ?? '');

if (!$mintAddress) {
    echo json_encode(['error' => 'Missing token mint address']);
    exit;
}

$totalBurned = 0;
$burnTxs = [];
$before = null;
$maxPages = 50; // Giới hạn số vòng lặp để tránh quá tải
$page = 0;

do {
    $url = "https://api.helius.xyz/v0/addresses/{$mintAddress}/transactions?limit=100&api-key=" . HELIUS_API_KEY;
    if ($before) $url .= "&before=" . $before;

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo json_encode(['error' => 'cURL error: ' . $err]);
        exit;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        echo json_encode(['error' => 'Invalid API response from Helius.']);
        exit;
    }

    foreach ($data as $tx) {
        if (!isset($tx['tokenTransfers'])) continue;

        foreach ($tx['tokenTransfers'] as $transfer) {
            if ($transfer['mint'] !== $mintAddress) continue;

            $isBurn =
                ($transfer['toUserAccount'] === null ||
                 $transfer['toUserAccount'] === '11111111111111111111111111111111') &&
                $transfer['tokenAmount'] < 0;

            if ($isBurn) {
                $amount = abs($transfer['tokenAmount']);
                $totalBurned += $amount;
                $burnTxs[] = [
                    'signature' => $tx['signature'],
                    'amount' => $amount,
                    'slot' => $tx['slot'],
                    'timestamp' => $tx['timestamp']
                ];
            }
        }
    }

    $page++;
    $lastTx = end($data);
    $before = $lastTx['signature'] ?? null;

} while (count($data) === 100 && $before && $page < $maxPages);

echo json_encode([
    'mint' => $mintAddress,
    'total_burned' => $totalBurned,
    'burn_transactions' => $burnTxs
]);
