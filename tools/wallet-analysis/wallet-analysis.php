<?php
// ============================================================================
// File: tools/wallet-analysis/wallet-analysis.php
// Description: Check wallet balance and assets (SOL, SPL tokens, NFTs) for a Solana wallet.
// Author: Vina Network
// ============================================================================

// Disable error display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Define constants
if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("wallet_analysis: bootstrap.php not found at $bootstrap_path", 'wallet_analysis_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Start session and configure error logging
session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);
log_message("wallet_analysis: Session started, session_id=" . session_id(), 'wallet_analysis_log.txt', 'INFO');

// Cache directory and file
$cache_dir = __DIR__ . '/cache/';
$cache_file = $cache_dir . 'wallet_analysis_cache.json';

// Create cache directory if it doesn't exist
if (!is_dir($cache_dir)) {
    if (!mkdir($cache_dir, 0755, true)) {
        log_message("wallet_analysis: Failed to create cache directory at $cache_dir", 'wallet_analysis_log.txt', 'ERROR');
        echo '<div class="result-error"><p>Cannot create cache directory</p></div>';
        exit;
    }
    log_message("wallet_analysis: Created cache directory at $cache_dir", 'wallet_analysis_log.txt', 'INFO');
}
if (!file_exists($cache_file)) {
    if (file_put_contents($cache_file, json_encode([])) === false) {
        log_message("wallet_analysis: Failed to create cache file at $cache_file", 'wallet_analysis_log.txt', 'ERROR');
        echo '<div class="result-error"><p>Cannot create cache file</p></div>';
        exit;
    }
    chmod($cache_file, 0644);
    log_message("wallet_analysis: Created cache file at $cache_file", 'wallet_analysis_log.txt', 'INFO');
}
if (!is_writable($cache_file)) {
    log_message("wallet_analysis: Cache file $cache_file is not writable", 'wallet_analysis_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cache file is not writable</p></div>';
    exit;
}

// Page configuration
$root_path = '../../';
$page_title = 'Check Wallet Analysis - Vina Network';
$page_description = 'Check the balance and assets (SOL, SPL tokens, NFTs) of a Solana wallet by entering its address.';
$page_css = ['../../css/vina.css', '../tools.css'];

log_message("wallet_analysis: Including header.php", 'wallet_analysis_log.txt', 'INFO');
include_once $root_path . 'include/header.php';
log_message("wallet_analysis: Including navbar.php", 'wallet_analysis_log.txt', 'INFO');
include_once $root_path . 'include/navbar.php';

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("wallet_analysis: tools-api.php not found at $api_helper_path", 'wallet_analysis_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
log_message("wallet_analysis: tools-api.php loaded", 'wallet_analysis_log.txt', 'INFO');

log_message("wallet_analysis: Rendering form", 'wallet_analysis_log.txt', 'INFO');
?>

<div class="t-6 wallet-analysis-content">
    <div class="t-7">
        <h2>Check Wallet Analysis</h2>
        <p>Enter a <strong>Solana Wallet Address</strong> to view its balance and assets, including SOL, SPL tokens (e.g., USDT), and NFTs.</p>
        <form id="walletAnalysisForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="text" name="walletAddress" id="walletAddress" placeholder="Enter Solana Wallet Address" required value="<?php echo isset($_POST['walletAddress']) ? htmlspecialchars($_POST['walletAddress']) : ''; ?>">
            <button type="submit" class="cta-button">Check Wallet</button>
        </form>
        <div class="loader"></div>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['walletAddress'])) {
        log_message("wallet_analysis: POST request received, walletAddress=" . ($_POST['walletAddress'] ?? 'none'), 'wallet_analysis_log.txt', 'INFO');
        try {
            // Validate CSRF token
            log_message("wallet_analysis: Validating CSRF token", 'wallet_analysis_log.txt', 'INFO');
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                log_message("wallet_analysis: Invalid CSRF token", 'wallet_analysis_log.txt', 'ERROR');
                throw new Exception('Invalid CSRF token');
            }

            // Validate wallet address
            $walletAddress = trim($_POST['walletAddress']);
            $walletAddress = preg_replace('/\s+/', '', $walletAddress);
            log_message("wallet_analysis: Validating walletAddress=$walletAddress", 'wallet_analysis_log.txt', 'INFO');
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletAddress)) {
                log_message("wallet_analysis: Invalid wallet address format", 'wallet_analysis_log.txt', 'ERROR');
                throw new Exception('Invalid Solana wallet address format');
            }

            // Check cache
            $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
            $cache_expiration = 3 * 3600; // Cache for 3 hours
            $cache_valid = isset($cache_data[$walletAddress]) && (time() - $cache_data[$walletAddress]['timestamp'] < $cache_expiration);
            log_message("wallet_analysis: Cache valid=$cache_valid for walletAddress=$walletAddress", 'wallet_analysis_log.txt', 'INFO');

            if (!$cache_valid) {
                // Call getAssetsByOwner API
                log_message("wallet_analysis: Calling getAssetsByOwner API for walletAddress=$walletAddress", 'wallet_analysis_log.txt', 'INFO');
                $params = [
                    'ownerAddress' => $walletAddress,
                    'page' => 1,
                    'limit' => 1000,
                    'displayOptions' => [
                        'showFungible' => true,
                        'showNativeBalance' => true
                    ]
                ];
                $assets = callAPI('getAssetsByOwner', $params, 'POST');

                log_message("wallet_analysis: API raw response=" . json_encode($assets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'wallet_analysis_log.txt', 'DEBUG');
                if (isset($assets['error'])) {
                    log_message("wallet_analysis: API error: " . json_encode($assets['error']), 'wallet_analysis_log.txt', 'ERROR');
                    throw new Exception(is_array($assets['error']) ? ($assets['error']['message'] ?? 'API error') : $assets['error']);
                }
                if (empty($assets['result']) || !isset($assets['result']['items'])) {
                    log_message("wallet_analysis: No assets found for walletAddress=$walletAddress", 'wallet_analysis_log.txt', 'ERROR');
                    throw new Exception('No assets found for the wallet');
                }

                // Format data
                $result = $assets['result'];
                $formatted_data = [
                    'wallet_address' => $walletAddress,
                    'sol_balance' => isset($result['nativeBalance']['lamports']) ? $result['nativeBalance']['lamports'] / 1000000000 : 0.0,
                    'sol_price_usd' => isset($result['nativeBalance']['total_price']) ? $result['nativeBalance']['total_price'] : 0.0,
                    'tokens' => [],
                    'nfts' => [],
                    'timestamp' => time()
                ];

                foreach ($result['items'] as $item) {
                    if ($item['interface'] === 'FungibleToken') {
                        $formatted_data['tokens'][] = [
                            'mint' => $item['id'] ?? 'N/A',
                            'name' => $item['content']['metadata']['name'] ?? $item['content']['metadata']['symbol'] ?? 'Unknown',
                            'balance' => isset($item['token_info']['balance']) ? $item['token_info']['balance'] / pow(10, $item['token_info']['decimals']) : 0,
                            'price_usd' => isset($item['token_info']['price_info']['total_price']) ? $item['token_info']['price_info']['total_price'] : 0.0
                        ];
                    } elseif (in_array($item['interface'], ['V1_NFT', 'ProgrammableNFT'])) {
                        $formatted_data['nfts'][] = [
                            'mint' => $item['id'] ?? 'N/A',
                            'name' => $item['content']['metadata']['name'] ?? 'N/A',
                            'collection' => isset($item['grouping'][0]['group_value']) ? $item['grouping'][0]['group_value'] : 'N/A'
                        ];
                    }
                }

                log_message("wallet_analysis: Formatted data=" . json_encode($formatted_data, JSON_PRETTY_PRINT), 'wallet_analysis_log.txt', 'DEBUG');

                // Save to cache
                $cache_data[$walletAddress] = [
                    'data' => $formatted_data,
                    'timestamp' => time()
                ];
                $fp = fopen($cache_file, 'c');
                if (flock($fp, LOCK_EX)) {
                    if (!file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT))) {
                        log_message("wallet_analysis: Failed to write to cache file", 'wallet_analysis_log.txt', 'ERROR');
                        flock($fp, LOCK_UN);
                        fclose($fp);
                        throw new Exception('Failed to write to cache file');
                    }
                    flock($fp, LOCK_UN);
                } else {
                    log_message("wallet_analysis: Failed to lock cache file", 'wallet_analysis_log.txt', 'ERROR');
                    fclose($fp);
                    throw new Exception('Failed to lock cache file');
                }
                fclose($fp);
                log_message("wallet_analysis: Cache updated for walletAddress=$walletAddress", 'wallet_analysis_log.txt', 'INFO');
            } else {
                $formatted_data = $cache_data[$walletAddress]['data'];
                log_message("wallet_analysis: Retrieved from cache for walletAddress=$walletAddress", 'wallet_analysis_log.txt', 'INFO');
            }

            // Output results as HTML
            ob_start();
            ?>
            <div class="t-8 result-section">
                <h2>Wallet Details</h2>
                <h3><?php echo htmlspecialchars($formatted_data['wallet_address']); ?></h3>
                <div class="t-8-1 wallet-details">
                    <div class="summary-card">
                        <div class="summary-item">
                            <i class="fas fa-wallet"></i>
                            <p>SOL Balance</p>
                            <h3><?php echo number_format($formatted_data['sol_balance'], 9) . ' SOL (' . number_format($formatted_data['sol_price_usd'], 2) . ' USD)'; ?></h3>
                        </div>
                    </div>
                </div>

                <?php if (!empty($formatted_data['tokens'])): ?>
                <h2>Tokens details</h2>
                <div class="token-details">
                    <div class="token-table">
                        <table>
                            <tr><th>Mint Address</th><th>Name</th><th>Balance</th><th>Value (USD)</th></tr>
                            <?php foreach ($formatted_data['tokens'] as $token): ?>
                            <tr>
                                <td style="word-break: break-all;"><?php echo htmlspecialchars($token['mint']); ?></td>
                                <td><?php echo htmlspecialchars($token['name']); ?></td>
                                <td><?php echo number_format($token['balance'], 6); ?></td>
                                <td><?php echo number_format($token['price_usd'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($formatted_data['nfts'])): ?>
                <h2>NFTs details</h2>
                <div class="nft-details">
                    <div class="nft-table">
                        <table>
                            <tr><th>Mint Address</th><th>Name</th><th>Collection</th></tr>
                            <?php foreach ($formatted_data['nfts'] as $nft): ?>
                            <tr>
                                <td style="word-break: break-all;"><?php echo htmlspecialchars($nft['mint']); ?></td>
                                <td><?php echo htmlspecialchars($nft['name']); ?></td>
                                <td style="word-break: break-all;"><?php echo htmlspecialchars($nft['collection']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($cache_valid): ?>
                    <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$walletAddress]['timestamp']) . ' UTC+0'; ?></p>
                <?php endif; ?>
            </div>
            <?php
            $output = ob_get_clean();
            log_message("wallet_analysis: Output length: " . strlen($output), 'wallet_analysis_log.txt', 'INFO');
            echo $output;
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            log_message("wallet_analysis: Exception - Message: $error_msg, File: " . $e->getFile() . ", Line: " . $e->getLine(), 'wallet_analysis_log.txt', 'ERROR');
            echo "<div class='result-error'><p>$error_msg</p></div>";
        }
    }
    log_message("wallet_analysis: Script ended", 'wallet_analysis_log.txt', 'INFO');
    ?>

    <div class="t-9">
        <h2>About Check Wallet Analysis</h2>
        <p>The Check Wallet Analysis tool allows you to view the balance and assets of a Solana wallet, including SOL, SPL tokens, and NFTs.</p>
    </div>
</div>
