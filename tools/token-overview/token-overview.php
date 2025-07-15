<?php
// ============================================================================
// File: tools/token-overview/token-overview.php
// Description: Display Token Overview by Mint Address (Solana) using Solscan APIs.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    echo '<div class="result-error"><p>Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;

// Cache setup
$cache_dir = TOKEN_OVERVIEW_PATH . 'cache/';
$cache_file = $cache_dir . 'token_overview_cache.json';
if (!ensure_directory_and_file($cache_dir, $cache_file, 'token_overview_log.txt')) {
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

$rate_limit_exceeded = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tokenAddress'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rate_key = "rate_limit_token_overview:$ip";
    $count = $_SESSION[$rate_key]['count'] ?? 0;
    $time = $_SESSION[$rate_key]['time'] ?? 0;

    if (time() - $time > 60) {
        $_SESSION[$rate_key] = ['count' => 1, 'time' => time()];
    } elseif ($count >= 5) {
        $rate_limit_exceeded = true;
        echo "<div class='result-error'><p>Rate limit exceeded. Try again in 1 minute.</p></div>";
    } else {
        $_SESSION[$rate_key]['count']++;
    }
}
?>

<div class="token-overview">
<?php if (!$rate_limit_exceeded): ?>
    <div class="tools-form">
        <h2>Token Overview</h2>
        <p>Check key metrics of a Solana token using its Mint Address.</p>
        <form id="tokenOverviewForm" method="POST" action="" data-tool="token-overview">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="input-wrapper">
                <input type="text" name="tokenAddress" placeholder="Enter Token Mint Address" required value="<?php echo isset($_POST['tokenAddress']) ? htmlspecialchars($_POST['tokenAddress']) : ''; ?>">
                <span class="clear-input" title="Clear input">&times;</span>
            </div>
            <button type="submit" class="cta-button">Check</button>
        </form>
        <div class="loader"></div>
    </div>
<?php endif; ?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tokenAddress']) && !$rate_limit_exceeded) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }

        $mint = trim($_POST['tokenAddress']);
        $mint = preg_replace('/\s+/', '', $mint);

        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mint)) {
            throw new Exception('Invalid Mint Address format');
        }

        log_message("token_overview: Requesting data for mint $mint", 'token_overview_log.txt');

        // Check cache
        $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
        $cache_expiration = 3 * 3600;
        $use_cache = isset($cache_data[$mint]) && (time() - $cache_data[$mint]['timestamp'] < $cache_expiration);

        if ($use_cache) {
            $result = $cache_data[$mint];
        } else {
            $info = callAPI('getTokenInfo', ['mint' => $mint], 'GET');
            $holders = callAPI('getTokenHolders', ['mint' => $mint], 'GET');
            $tx = callAPI('getTokenTxCount', ['mint' => $mint], 'GET');

            if (isset($info['error']) || isset($holders['error']) || isset($tx['error'])) {
                throw new Exception('Failed to fetch data from APIs');
            }

            $result = [
                'mint' => $mint,
                'name' => $info['name'] ?? 'N/A',
                'symbol' => $info['symbol'] ?? 'N/A',
                'supply' => $info['supply'] ?? 0,
                'price' => $info['priceUsdt'] ?? 0,
                'marketcap' => $info['marketCap'] ?? 0,
                'holders' => $holders['total'] ?? 0,
                'tx_count' => $tx[0]['txCount'] ?? 'N/A',
                'timestamp' => time()
            ];

            $cache_data[$mint] = $result;
            file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
        }
?>
    <div class="tools-result">
        <h2>Token Metrics</h2>
        <table>
            <tr><th>Token</th><td><?php echo htmlspecialchars($result['name']) . ' (' . htmlspecialchars($result['symbol']) . ')'; ?></td></tr>
            <tr><th>Mint Address</th><td><code><?php echo $result['mint']; ?></code></td></tr>
            <tr><th>Holders</th><td><?php echo number_format($result['holders']); ?></td></tr>
            <tr><th>Total Supply</th><td><?php echo number_format($result['supply']); ?></td></tr>
            <tr><th>Price (USD)</th><td>$<?php echo number_format($result['price'], 6); ?></td></tr>
            <tr><th>Market Cap</th><td>$<?php echo number_format($result['marketcap'], 2); ?></td></tr>
            <tr><th>Total Transactions</th><td><?php echo number_format($result['tx_count']); ?></td></tr>
        </table>
        <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $result['timestamp']); ?> UTC+0</p>
    </div>
<?php
    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
        log_message("token_overview: Exception - " . $e->getMessage(), 'token_overview_log.txt', 'ERROR');
    }
}
?>

    <div class="tools-about">
        <h2>About Token Overview</h2>
        <p>This tool helps you get quick insights about any SPL token on Solana including holders, price, market cap, and transaction count.</p>
    </div>
</div>
