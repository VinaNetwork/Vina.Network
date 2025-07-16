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
            $url = "https://api.helius.xyz/v0/addresses/{$tokenAddress}/transactions?limit=100";
            if ($afterCursor) $url .= "&before=" . urlencode($afterCursor);
            $url .= "&api-key=" . HELIUS_API_KEY;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                $error = 'Failed to fetch transactions from Helius';
                break;
            }

            $batch = json_decode($response, true);
            if (!is_array($batch)) {
                $error = 'Invalid API response format';
                break;
            }

            $transactions = array_merge($transactions, $batch);
            $afterCursor = end($batch)['signature'] ?? null;
        } while (count($batch) === 100 && count($transactions) < 1000); // limit scan to 1000 txs for performance

        // Tính tổng số token đã đốt
        foreach ($transactions as $tx) {
            // 1. Check tokenTransfers (gửi vào ví 111...)
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

            // 2. Check tokenBalanceChanges để tìm các burn không có người nhận
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
        <form method="POST" action="" data-tool="token-burn">
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
