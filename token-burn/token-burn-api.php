<?php
// ============================================================================
// File: token-burn/token-burn-api.php
// Description: API xử lý việc kiểm tra lượng token đã burn dựa trên Helius
// ============================================================================
define('VINANETWORK_ENTRY', true);
require_once(__DIR__ . '/../config/config.php');

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$mint = $input['mint'] ?? '';

if (!$mint || strlen($mint) < 32) {
    echo json_encode(['success' => false, 'message' => 'Invalid token mint address.']);
    exit;
}

// Kiểm tra các địa chỉ ví burn phổ biến
$burnAddresses = ['11111111111111111111111111111111'];
$totalBurned = 0;
$burnTxCount = 0;

try {
    // Truy vấn nhiều ví (tối đa 10K giao dịch theo mặc định)
    // Cần có ít nhất 1 địa chỉ ví đã từng sở hữu token này
    $holderAddresses = [$mint]; // giả sử địa chỉ token là ví sở hữu luôn (cần cải thiện sau)

    foreach ($holderAddresses as $address) {
        $url = "https://api.helius.xyz/v0/addresses/{$address}/transactions?api-key=" . HELIUS_API_KEY;

        $json = file_get_contents($url);
        $transactions = json_decode($json, true);

        if (!is_array($transactions)) {
            throw new Exception("Invalid API response from Helius.");
        }

        foreach ($transactions as $tx) {
            if (!isset($tx['tokenTransfers'])) continue;

            foreach ($tx['tokenTransfers'] as $transfer) {
                if (
                    $transfer['mint'] === $mint &&
                    in_array($transfer['toUserAccount'], $burnAddresses)
                ) {
                    $totalBurned += floatval($transfer['tokenAmount']);
                    $burnTxCount++;
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'totalBurned' => rtrim(rtrim(number_format($totalBurned, 6, '.', ''), '0'), '.'),
        'txCount' => $burnTxCount,
        'symbol' => '', // Bro có thể dùng thêm API để tra symbol
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
