<?php
// ============================================================================
// File: tools/token-burn/token-burn.php
// Description: Check how many tokens have been burned (sent to 111... or burned)
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

require_once dirname(__DIR__) . '/../bootstrap.php';
require_once dirname(__DIR__) . '/../tools-api.php';

$burnWallet = '11111111111111111111111111111111';
$totalBurned = 0;
$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tokenAddress'])) {
    $tokenAddress = trim($_POST['tokenAddress']);
    $tokenAddress = preg_replace('/\s+/', '', $tokenAddress);

    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $tokenAddress)) {
        $error = 'Invalid token address';
    } else {
        $transactions = [];
        $afterCursor = null;

        do {
            $url = "addresses/{$tokenAddress}/transactions?limit=100";
            if ($afterCursor) $url .= "&before=" . urlencode($afterCursor);
            $url .= "&api-key=" . HELIUS_API_KEY;

            $response = callAPI($url, [], 'GET');

            if (!is_array($response)) {
                $error = 'Invalid API response format';
                break;
            }
            if (isset($response['error'])) {
                $error = 'API error: ' . $response['error'];
                break;
            }

            $batch = $response;
            $transactions = array_merge($transactions, $batch);

            $lastTx = end($batch);
            $afterCursor = is_array($lastTx) && isset($lastTx['signature']) ? $lastTx['signature'] : null;

        } while (count($batch) === 100 && count($transactions) < 1000); // Limit to 1000 txs

        // Tính tổng token đã đốt
        foreach ($transactions as $tx) {
            // 1. Gửi vào ví 111...
            if (!empty($tx['tokenTransfers'])) {
                foreach ($tx['tokenTransfers'] as $transfer) {
                    if (
                        $transfer['mint'] === $tokenAddress &&
                        isset($transfer['toUserAccount']) &&
                        $transfer['toUserAccount'] === $burnWallet
                    ) {
                        $totalBurned += (float)$transfer['tokenAmount'];
                    }
                }
            }

            // 2. Các burn không có người nhận
            if (!empty($tx['accountData'])) {
                foreach ($tx['accountData'] as $account) {
                    if (!empty($account['tokenBalanceChanges'])) {
                        foreach ($account['tokenBalanceChanges'] as $change) {
                            if (
                                $change['mint'] === $tokenAddress &&
                                isset($change['rawTokenAmount']['tokenAmount']) &&
                                isset($change['rawTokenAmount']['decimals']) &&
                                (float)$change['rawTokenAmount']['tokenAmount'] < 0 &&
                                (
                                    !isset($change['toUserAccount']) || 
                                    $change['toUserAccount'] === null || 
                                    $change['toUserAccount'] === ''
                                )
                            ) {
                                $amount = abs((float)$change['rawTokenAmount']['tokenAmount']) / pow(10, (int)$change['rawTokenAmount']['decimals']);
                                $totalBurned += $amount;
                            }
                        }
                    }
                }
            }
        }

        $result = [
            'token' => $tokenAddress,
            'total_burned' => $totalBurned
        ];
    }
}
?>

<link rel="stylesheet" href="/tools/token-burn/token-burn.css">
<div class="token-burn">
    <div class="tools-form">
        <h2>Check Token Burn</h2>
        <p>Enter the <strong>Token Mint Address</strong> to see how many tokens were burned (sent to <code>111...</code> or burned directly).</p>
        <form id="tokenBurnForm" method="POST" action="" data-tool="token-burn">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="input-wrapper">
                <input type="text" name="tokenAddress" placeholder="Enter Token Mint Address" required value="<?php echo isset($_POST['tokenAddress']) ? htmlspecialchars($_POST['tokenAddress']) : ''; ?>">
                <span class="clear-input" title="Clear input">×</span>
            </div>
            <button type="submit" class="cta-button">Check</button>
        </form>
        <div class="loader"></div>
    </div>

    <?php if ($error): ?>
        <div class="result-error"><p><?php echo htmlspecialchars($error); ?></p></div>
    <?php elseif ($result): ?>
        <div class="tools-result token-burn-result">
            <h2>Token Burn Result</h2>
            <p><strong>Token:</strong> <?php echo htmlspecialchars($result['token']); ?></p>
            <p><strong>Total Burned:</strong> <?php echo number_format($result['total_burned'], 6); ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="tools-about">
    <h2>About Token Burn</h2>
    <p>This tool scans Solana transactions to calculate total tokens burned by analyzing transfers to <code>11111111111111111111111111111111</code> and direct burn actions.</p>
</div>
