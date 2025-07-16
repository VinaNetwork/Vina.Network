<?php
// ============================================================================
// File: tools/token-burn/token-burn.php
// Description: Check how many tokens have been burned for a given token address.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
require_once $bootstrap_path;

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
require_once $api_helper_path;
?>

<link rel="stylesheet" href="/tools/token-burn/token-burn.css">
<div class="token-burn">
    <div class="tools-form">
        <h2>Check Token Burned</h2>
        <p>Enter the <strong>Token Mint Address</strong> to check how many tokens have been burned (sent to <code>11111111111111111111111111111111</code> or burned).</p>
        <form method="POST" action="" id="tokenBurnForm" data-tool="token-burn">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="input-wrapper">
                <input type="text" name="mintAddress" placeholder="Enter Token Mint Address" required>
                <span class="clear-input" title="Clear input">×</span>
            </div>
            <button type="submit" class="cta-button">Check</button>
        </form>
        <div class="loader"></div>
    </div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
    try {
        if (!validate_csrf_token($_POST['csrf_token'])) throw new Exception('Invalid CSRF token');

        $mint = trim($_POST['mintAddress']);
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mint)) throw new Exception('Invalid token mint address');

        $burnAddress = "11111111111111111111111111111111";
        $totalBurned = 0;
        $cursor = null;
        $maxPages = 5;

        for ($page = 0; $page < $maxPages; $page++) {
            $endpoint = "https://api.helius.xyz/v0/addresses/$burnAddress/transactions?api-key=" . HELIUS_API_KEY;
            if ($cursor) $endpoint .= "&before=$cursor";

            $response = file_get_contents($endpoint);
            if (!$response) throw new Exception("API request failed");

            $data = json_decode($response, true);
            if (!is_array($data)) throw new Exception("Invalid API response");

            foreach ($data as $tx) {
                if (!empty($tx['tokenTransfers'])) {
                    foreach ($tx['tokenTransfers'] as $transfer) {
                        if (
                            isset($transfer['toUserAccount']) &&
                            $transfer['toUserAccount'] === $burnAddress &&
                            isset($transfer['mint']) &&
                            $transfer['mint'] === $mint &&
                            isset($transfer['tokenAmount'])
                        ) {
                            $totalBurned += $transfer['tokenAmount'];
                        }
                    }
                }
            }

            if (count($data) < 50 || empty(end($data)['signature'])) break;
            $cursor = end($data)['signature'];
        }

        echo '<div class="tools-result token-burn-result">';
        echo '<h2>Burn Result</h2>';
        echo '<p><strong>Token:</strong> ' . htmlspecialchars($mint) . '</p>';
        echo '<p><strong>Total Burned:</strong> ' . number_format($totalBurned, 0) . '</p>';
        echo '</div>';
    } catch (Exception $e) {
        echo '<div class="result-error"><p>Error: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
        log_message("token_burn: " . $e->getMessage(), 'token_burn_log.txt', 'ERROR');
    }
}
?>
    <div class="tools-about">
        <h2>About Token Burn Checker</h2>
        <p>This tool fetches transactions to the burn address (111...) and calculates how many tokens of the selected mint have been destroyed.</p>
    </div>
</div>
