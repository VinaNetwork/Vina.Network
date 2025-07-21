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
$toBurnWallet = 0;
$explicitBurn = 0;
$burnTxs = [];
$before = null;
$maxPages = 50;
$page = 0;

do {
    $url = "https://api.helius.xyz/v0/addresses/{$mintAddress}/transactions?limit=100&api-key=" . HELIUS_API_KEY;
    if ($before) $url .= "&before=" . $before;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($res, true);
    if (!is_array($data)) {
        echo json_encode(['error' => 'Invalid API response from Helius.']);
        exit;
    }

    foreach ($data as $tx) {
        if (!isset($tx['tokenBalanceChanges'])) continue;

        foreach ($tx['tokenBalanceChanges'] as $change) {
            if ($change['mint'] !== $mintAddress) continue;

            $amount = (float) $change['rawTokenAmount']['amount'];
            $decimals = (int) $change['rawTokenAmount']['decimals'];
            $uiAmount = $amount / (10 ** $decimals);

            if ($uiAmount < 0) {
                // Trường hợp gửi vào ví burn
                if (
                    isset($change['owner']) &&
                    $change['owner'] === '11111111111111111111111111111111'
                ) {
                    $toBurnWallet += abs($uiAmount);
                    $burnTxs[] = $tx['signature'];
                }
                // Trường hợp token bị giảm mà không có counterparty (explicit burn)
                elseif (empty($change['counterparty'])) {
                    $explicitBurn += abs($uiAmount);
                    $burnTxs[] = $tx['signature'];
                }
            }
        }
    }

    $page++;
    $lastTx = end($data);
    $before = $lastTx['signature'] ?? null;

} while (count($data) === 100 && $before && $page < $maxPages);

$totalBurned = $toBurnWallet + $explicitBurn;

echo json_encode([
    'mint' => $mintAddress,
    'total_burned' => $totalBurned,
    'to_burn_wallet' => $toBurnWallet,
    'explicit_burn' => $explicitBurn,
    'burn_transactions' => array_values(array_unique($burnTxs))
]);
