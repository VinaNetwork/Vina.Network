<?php
// ============================================================================
// File: tools/token-burn/token-burn.php
// Description: Check how many tokens have been burned (sent to 111... or burned)
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    echo '<div class="result-error"><p>Error: Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    echo '<div class="result-error"><p>Error: Cannot find tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;

$burnWallet = '11111111111111111111111111111111';
$totalBurned = 0;
$error = '';
$result = null;
?>

<link rel="stylesheet" href="/tools/token-burn/token-burn.css">
<div class="token-burn">
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tokenAddress'])) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }

        $tokenAddress = trim($_POST['tokenAddress']);
        $tokenAddress = preg_replace('/\s+/', '', $tokenAddress);

        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $tokenAddress)) {
            throw new Exception('Invalid token address format');
        }

        $transactions = [];
        $afterCursor = null;
        $maxTxs = 1000;

        do {
            $endpoint = "addresses/{$tokenAddress}/transactions?limit=100";
            if ($afterCursor) $endpoint .= "&before=" . urlencode($afterCursor);

            $response = callAPI($endpoint, [], 'GET');
            if (isset($response['error'])) throw new Exception($response['error']);
            if (!is_array($response)) throw new Exception('Invalid API response');

            $transactions = array_merge($transactions, $response);
            $afterCursor = end($response)['signature'] ?? null;
        } while (count($response) === 100 && count($transactions) < $maxTxs);

        foreach ($transactions as $tx) {
            // 1. Burn via transfer to 111...
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

            // 2. Burn via balance change (no recipient)
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
                                    empty($change['toUserAccount'])
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
    } catch (Exception $e) {
        $error = $e->getMessage();
        log_message("token_burn: Exception - " . $e->getMessage(), 'token_burn_log.txt', 'ERROR');
    }
}
?>

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

    <div class="tools-about">
        <h2>About Token Burn</h2>
        <p>This tool scans Solana transactions to calculate total tokens burned by analyzing transfers to <code>11111111111111111111111111111111</code> and direct burn actions.</p>
    </div>
</div>
