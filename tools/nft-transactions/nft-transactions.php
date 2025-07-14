<?php
// ============================================================================
// File: tools/nft-transactions/nft-transactions.php
// Description: Check NFT transaction history by Mint Address using Helius API (only for compressed NFTs - cNFT).
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);
require_once dirname(__DIR__) . '/bootstrap.php';

define('NFT_TRANSACTIONS_PATH', TOOLS_PATH . 'nft-transactions/');
$cache_dir = NFT_TRANSACTIONS_PATH . 'cache/';
$cache_file = $cache_dir . 'nft_transactions_cache.json';

if (!ensure_directory_and_file($cache_dir, $cache_file, 'nft_transactions_log.txt')) {
    log_message("nft_transactions: Cache setup failed", 'nft_transactions_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

require_once dirname(__DIR__) . '/tools-api.php';
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

    if (!$rate_limit_exceeded) {
    ?>
        <div class="tools-form">
            <h2>Check NFT Transactions (cNFT Only)</h2>
            <p>This tool allows you to check the recent transaction history of a <strong>compressed NFT (cNFT)</strong> on the Solana network using its <strong>Mint Address</strong>. Standard NFTs are not supported.</p>
            <form id="nftTransactionForm" method="POST" action="" data-tool="nft-transactions">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="input-wrapper">
                    <input type="text" name="mintAddress" id="mintAddressTx" placeholder="Enter compressed NFT Mint Address (cNFT only)" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
                    <span class="clear-input" title="Clear input">×</span>
                </div>
                <button type="submit" class="cta-button">Check</button>
            </form>
            <div class="loader"></div>
        </div>
    <?php } ?>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress']) && !$rate_limit_exceeded) {
        try {
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                throw new Exception('Invalid CSRF token');
            }

            $mintAddress = preg_replace('/\s+/', '', trim($_POST['mintAddress']));
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                throw new Exception('Invalid Mint Address format');
            }

            log_message("nft_transactions: Submitting mint address: $mintAddress", 'nft_transactions_log.txt');
            log_message("nft_transactions: Sending request to getSignaturesForAsset with mint: $mintAddress", 'nft_transactions_log.txt');

            $params = ['id' => $mintAddress];
            $response = callAPI('getSignaturesForAsset', $params, 'POST');

            log_message("nft_transactions: Raw API response: " . json_encode($response), 'nft_transactions_log.txt');

            if (isset($response['error'])) {
                if (strpos($response['error'], 'Tree not found') !== false) {
                    throw new Exception("This NFT is a standard NFT (non-compressed) and is not supported for transaction lookup. Only compressed NFTs (cNFTs) are supported.");
                }
                throw new Exception("Helius API error: " . $response['error']);
            }

            if (!isset($response['result']['items']) || !is_array($response['result']['items'])) {
                throw new Exception('Invalid API response structure');
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
                    'timestamp' => null
                ];
            }

            $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1;
                        foreach ($formatted['transactions'] as $tx) : ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <a href="https://solscan.io/tx/<?php echo $tx['tx_signature']; ?>" target="_blank">
                                        <?php echo substr($tx['tx_signature'], 0, 6) . '...' . substr($tx['tx_signature'], -6); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($tx['type']); ?></td>
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
        <h2>About This Tool</h2>
        <p>This tool allows you to look up the transaction history of a <strong>compressed NFT (cNFT)</strong> using Helius API. Please note that it does <strong>not support standard NFTs</strong>. If your NFT was not minted using a Merkle Tree (i.e., not a cNFT), transaction data will not be available.</p>
    </div>
</div>
