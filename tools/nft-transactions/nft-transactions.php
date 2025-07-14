<?php
// ============================================================================
// File: tools/nft-transactions/nft-transactions.php
// Description: Check NFT transaction history by Mint Address using Helius API (getSignaturesForAsset).
// Created by: Vina Network
// ============================================================================

// Define constants
if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;

// Path constants
define('NFT_TRANSACTIONS_PATH', TOOLS_PATH . 'nft-transactions/');
$cache_dir = NFT_TRANSACTIONS_PATH . 'cache/';
$cache_file = $cache_dir . 'nft_transactions_cache.json';

// Check cache directory
ensure_directory_and_file($cache_dir, $cache_file);

?>
<link rel="stylesheet" href="/tools/nft-transactions/nft-transactions.css">
<div class="nft-transactions">
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            $mintAddress = trim($_POST['mintAddress']);
            $mintAddress = preg_replace('/\s+/', '', $mintAddress);

            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                throw new Exception('Invalid Mint Address format');
            }

            log_message("nft_transactions: Submitting mint address: $mintAddress", 'nft_transactions_log.txt');

            $params = [
                'id' => $mintAddress
            ];

            log_message("nft_transactions: Calling getSignaturesForAsset", 'nft_transactions_log.txt');
            $response = callAPI('getSignaturesForAsset', $params, 'POST');

            if (isset($response['error'])) {
                throw new Exception(is_array($response['error']) ? ($response['error']['message'] ?? 'API error') : $response['error']);
            }

            $items = $response['result']['items'] ?? [];

            ?>
            <div class="tools-result nft-tx-result">
                <h2>NFT Transaction History</h2>
                <p>Mint Address: <code><?php echo htmlspecialchars($mintAddress); ?></code></p>
                <?php if (count($items) === 0): ?>
                    <p>No transactions found for this NFT.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Signature</th>
                            <th>Type</th>
                            <th>Explorer</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><code><?php echo substr($item[0], 0, 6) . '...' . substr($item[0], -6); ?></code></td>
                            <td><?php echo htmlspecialchars($item[1]); ?></td>
                            <td>
                                <a href="https://solscan.io/tx/<?php echo $item[0]; ?>" target="_blank">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php
        } catch (Exception $e) {
            echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
            log_message("nft_transactions: Exception - " . $e->getMessage(), 'nft_transactions_log.txt', 'ERROR');
        }
    }
    ?>
    <div class="tools-form">
        <h2>Check NFT Transactions</h2>
        <p>Enter the <strong>NFT Mint Address</strong> to view recent transaction history on Solana.</p>
        <form method="POST" action="">
            <div class="input-wrapper">
                <input type="text" name="mintAddress" id="mintAddressTx" placeholder="Enter NFT Mint Address" required>
                <span class="clear-input" title="Clear input">&times;</span>
            </div>
            <button type="submit" class="cta-button">Check</button>
        </form>
    </div>
    <div class="tools-about">
        <h2>About NFT Transactions</h2>
        <p>This tool shows the transaction history of a Solana NFT using Helius API.</p>
    </div>
</div>
