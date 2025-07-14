<?php
// ============================================================================
// File: tools/nft-transactions/nft-transactions.php
// Description: Check NFT transaction history by Mint Address using Helius API.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft_transactions: bootstrap.php not found at $bootstrap_path", 'nft_transactions_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

define('NFT_TRANSACTIONS_PATH', TOOLS_PATH . 'nft-transactions/');
$cache_dir = NFT_TRANSACTIONS_PATH . 'cache/';
$cache_file = $cache_dir . 'nft_transactions_cache.json';

if (!ensure_directory_and_file($cache_dir, $cache_file, 'nft_transactions_log.txt')) {
    log_message("nft_transactions: Cache setup failed", 'nft_transactions_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft_transactions: tools-api.php not found", 'nft_transactions_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
?>

<link rel="stylesheet" href="/tools/nft-transactions/nft-transactions.css">

<div class="nft-transactions">
<?php
$rate_limit_exceeded = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = "rate_limit_nft_tx:$ip";
    $rate_limit_count = $_SESSION[$rate_limit_key]['count'] ?? 0;
    $rate_limit_time = $_SESSION[$rate_limit_key]['time'] ?? 0;

    if (time() - $rate_limit_time > 60) {
        $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
    } elseif ($rate_limit_count >= 5) {
        $rate_limit_exceeded = true;
        echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
    } else {
        $_SESSION[$rate_limit_key]['count']++;
    }
}

if (!$rate_limit_exceeded): ?>
    <div class="tools-form">
        <h2>Check NFT Transactions</h2>
        <p>This tool retrieves recent transactions of compressed NFTs (cNFTs) on Solana. Enter the <strong>Mint Address</strong> of a cNFT.</p>
        <form id="nftTransactionForm" method="POST" action="" data-tool="nft-transactions">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="input-wrapper">
                <input type="text" name="mintAddress" id="mintAddressTx" placeholder="Enter NFT Mint Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
                <span class="clear-input" title="Clear input">×</span>
            </div>
            <button type="submit" class="cta-button">Check</button>
        </form>
        <div class="loader"></div>
    </div>
<?php endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress']) && !$rate_limit_exceeded) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }

        $mintAddress = trim($_POST['mintAddress']);
        $mintAddress = preg_replace('/\s+/', '', $mintAddress);

        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
            throw new Exception('Invalid Mint Address format');
        }

        // Check cache first
        $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
        if (isset($cache_data[$mintAddress])) {
            $cached_entry = $cache_data[$mintAddress];
            $cached_time = $cached_entry['timestamp'] ?? 0;

            if (time() - $cached_time < 3 * 3600) { // valid for 3h
                echo '<div class="tools-result nft-tx-result">';
                echo '<h2>NFT Transaction History (From Cache)</h2>';
                echo '<p>Mint Address: <code>' . htmlspecialchars($mintAddress) . '</code></p>';
                echo '<table><thead><tr><th>#</th><th>Signature</th><th>Type</th><th>Time</th></tr></thead><tbody>';

                $i = 1;
                foreach ($cached_entry['transactions'] as $tx) {
                    echo '<tr>';
                    echo '<td>' . $i++ . '</td>';
                    echo '<td><a href="https://solscan.io/tx/' . $tx['tx_signature'] . '" target="_blank">'
                        . substr($tx['tx_signature'], 0, 6) . '...' . substr($tx['tx_signature'], -6) . '</a></td>';
                    echo '<td>' . htmlspecialchars($tx['type']) . '</td>';
                    echo '<td>' . ($tx['timestamp'] ? date('Y-m-d H:i:s', $tx['timestamp']) . ' UTC' : '-') . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
                echo '<p class="cache-timestamp">Data retrieved at: ' . date('d M Y, H:i', $cached_time) . ' UTC+0</p>';
                echo '</div>';
                return;
            }
        }

        log_message("nft_transactions: Submitting mint address: $mintAddress", 'nft_transactions_log.txt');
        log_message("nft_transactions: Sending request to getSignaturesForAsset with mint: $mintAddress", 'nft_transactions_log.txt');

        $params = ['id' => $mintAddress];
        $response = callAPI('getSignaturesForAsset', $params, 'POST');
        log_message("nft_transactions: Raw API response: " . json_encode($response), 'nft_transactions_log.txt');

        if (isset($response['error'])) {
            log_message("nft_transactions: API returned error - " . $response['error'], 'nft_transactions_log.txt', 'ERROR');
            throw new Exception("Helius API error: " . $response['error']);
        }

        if (!isset($response['result']['items']) || !is_array($response['result']['items'])) {
            throw new Exception('Invalid API response');
        }

        $formatted = [
            'mint' => $mintAddress,
            'transactions' => [],
            'timestamp' => time()
        ];

        foreach ($response['result']['items'] as $tx) {
            $formatted['transactions'][] = [
                'tx_signature' => $tx[0] ?? '',
                'type' => $tx[1] ?? 'Unknown',
                'timestamp' => $tx[2] ?? null
            ];
        }

        // Save to cache
        $cache_data[$mintAddress] = $formatted;
        $fp = fopen($cache_file, 'c');
        if (flock($fp, LOCK_EX)) {
            file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
?>
        <div class="tools-result nft-tx-result">
            <h2>NFT Transaction History</h2>
            <p>Mint Address: <code><?php echo htmlspecialchars($formatted['mint']); ?></code></p>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Signature</th>
                        <th>Type</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1;
                    foreach ($formatted['transactions'] as $tx): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td>
                                <a href="https://solscan.io/tx/<?php echo $tx['tx_signature']; ?>" target="_blank">
                                    <?php echo substr($tx['tx_signature'], 0, 6) . '...' . substr($tx['tx_signature'], -6); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($tx['type']); ?></td>
                            <td>
                                <?php
                                echo $tx['timestamp']
                                    ? date('Y-m-d H:i:s', $tx['timestamp']) . ' UTC'
                                    : '-';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="cache-timestamp">Data retrieved at: <?php echo date('d M Y, H:i', $formatted['timestamp']); ?> UTC+0</p>
        </div>
<?php
    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
        log_message("nft_transactions: Exception - " . $e->getMessage(), 'nft_transactions_log.txt', 'ERROR');
    }
}
?>
    <div class="tools-about">
        <h2>About NFT Transactions</h2>
        <p>This tool allows you to look up transaction history for NFTs that are part of a Merkle Tree structure (compressed NFTs).</p>
    </div>
</div>
