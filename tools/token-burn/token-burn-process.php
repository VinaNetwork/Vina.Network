<?php
// <!-- token-burn-process.php: Xử lý từng batch giao dịch để tính lượng token burn (dùng AJAX gọi riêng) -->

require_once('../../tools/tools-api.php');
require_once('../../include/config.php');

// Lấy dữ liệu
$tokenAddress = $_POST['tokenAddress'] ?? '';
$page = intval($_POST['page'] ?? 0);

header('Content-Type: application/json');

if (!$tokenAddress) {
    echo json_encode(['error' => 'Missing token address']);
    exit;
}

$limit = 100;
$before = $_POST['before'] ?? null;

$params = [
    'limit' => $limit,
];
if ($before) $params['before'] = $before;

$txEndpoint = "/v0/addresses/$tokenAddress/transactions";
$response = callAPI('GET', $txEndpoint, $params);

if (!$response || !is_array($response)) {
    echo json_encode(['error' => 'API call failed']);
    exit;
}

// Phân tích từng giao dịch
$burnedAmount = 0;
$sentTo1111 = 0;
$lastSignature = null;

foreach ($response as $tx) {
    $lastSignature = $tx['signature'] ?? $lastSignature;

    if (!isset($tx['tokenTransfers'])) continue;

    foreach ($tx['tokenTransfers'] as $transfer) {
        if ($transfer['mint'] !== $tokenAddress) continue;

        if ($transfer['toUserAccount'] === '11111111111111111111111111111111') {
            $sentTo1111 += $transfer['tokenAmount'];
        } elseif ($transfer['toUserAccount'] === null && $transfer['fromUserAccount']) {
            // trường hợp burn (không có người nhận)
            $burnedAmount += abs($transfer['tokenAmount']);
        }
    }
}

echo json_encode([
    'burnedAmount' => $burnedAmount,
    'sentTo1111' => $sentTo1111,
    'lastSignature' => $lastSignature
]);
exit;
