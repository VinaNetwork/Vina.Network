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
$cache_dir = WALLET_ANALYSIS_PATH . 'cache/';
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
?>

<div class="wallet-analysis">
    <!-- Render form unless rate limit exceeded -->
    <?php
    $rate_limit_exceeded = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['walletAddress'])) {
        // Rate limiting: 5 requests per minute per IP for form submission
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_limit_key = "rate_limit_wallet_analysis:$ip";
        $rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['count'] : 0;
        $rate_limit_time = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['time'] : 0;
        if (time() - $rate_limit_time > 60) {
            $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
            log_message("wallet_analysis: Reset rate limit for IP=$ip, count=1", 'wallet_analysis_log.txt', 'INFO');
        } elseif ($rate_limit_count >= 5) {
            log_message("wallet_analysis: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'wallet_analysis_log.txt', 'ERROR');
            $rate_limit_exceeded = true;
            echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
        } else {
            $_SESSION[$rate_limit_key]['count']++;
            log_message("wallet_analysis: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'wallet_analysis_log.txt', 'INFO');
        }
    }

    if (!$rate_limit_exceeded) {
        log_message("wallet_analysis: Rendering form", 'wallet_analysis_log.txt', 'INFO');
        ?>
        <div class="tools-form">
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
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['walletAddress']) && !$rate_limit_exceeded) {
        try {
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                throw new Exception('Invalid CSRF token');
            }

            $walletAddress = trim($_POST['walletAddress']);
            $walletAddress = preg_replace('/\s+/', '', $walletAddress);
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletAddress)) {
                throw new Exception('Invalid Solana wallet address format');
            }

            $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
            $cache_expiration = 3 * 3600;
            $cache_valid = isset($cache_data[$walletAddress]) && (time() - $cache_data[$walletAddress]['timestamp'] < $cache_expiration);

            if (!$cache_valid) {
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

                if (isset($assets['error'])) {
                    throw new Exception(is_array($assets['error']) ? ($assets['error']['message'] ?? 'API error') : $assets['error']);
                }
                if (empty($assets['result']) || !isset($assets['result']['items'])) {
                    throw new Exception('No assets found for the wallet');
                }

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

                $cache_data[$walletAddress] = [
                    'data' => $formatted_data,
                    'timestamp' => time()
                ];
                file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
            } else {
                $formatted_data = $cache_data[$walletAddress]['data'];
            }

            ?>
            <div class="tools-result wallet-analysis-result">
                <div class="result-summary">
                    <div class="result-card">
                        <div class="result-item">
                            <i class="fas fa-wallet"></i>
                            <p class="wallet-address">
                                <span><?php echo substr(htmlspecialchars($formatted_data['wallet_address']), 0, 4) . '...' . substr(htmlspecialchars($formatted_data['wallet_address']), -4); ?></span>
                                <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($formatted_data['wallet_address']); ?>"></i>
                            </p>
                            <h3>SOL Balance</h3>
                            <h4><?php echo number_format($formatted_data['sol_balance'], 9) . ' SOL (' . number_format($formatted_data['sol_price_usd'], 2) . ' USD)'; ?></h4>
                        </div>
                    </div>
                </div>

                <?php if (!empty($formatted_data['tokens'])): ?>
                <h2>Tokens details</h2>
                <div class="wallet-details token-details">
                    <div class="token-table">
                        <table>
                            <tr><th>Name</th><th>Token Address</th><th>Balance</th><th>Value (USD)</th></tr>
                            <?php foreach ($formatted_data['tokens'] as $token): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($token['name']); ?></td>
                                <td>
                                    <span><?php echo substr(htmlspecialchars($token['mint']), 0, 4) . '...' . substr(htmlspecialchars($token['mint']), -4); ?></span>
                                    <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($token['mint']); ?>"></i>
                                </td>
                                <td><?php echo number_format($token['balance'], 2); ?></td>
                                <td><?php echo number_format($token['price_usd'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($formatted_data['nfts'])): ?>
                <h2>NFTs details</h2>
                <div class="wallet-details nft-details">
                    <div class="nft-table">
                        <table>
                            <tr><th>Name</th><th>Mint Address</th><th>Collection</th></tr>
                            <?php foreach ($formatted_data['nfts'] as $nft): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($nft['name']); ?></td>
                                <td>
                                    <span><?php echo substr(htmlspecialchars($nft['mint']), 0, 4) . '...' . substr(htmlspecialchars($nft['mint']), -4); ?></span>
                                    <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($nft['mint']); ?>"></i>
                                </td>
                                <td>
                                    <?php if ($nft['collection'] !== 'N/A' && preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $nft['collection'])): ?>
                                        <span><?php echo substr(htmlspecialchars($nft['collection']), 0, 4) . '...' . substr(htmlspecialchars($nft['collection']), -4); ?></span>
                                        <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($nft['collection']); ?>"></i>
                                    <?php else: ?>
                                        <span>N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($cache_valid): ?>
                    <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$walletAddress]['timestamp']) . ' UTC+0'; ?>. Data will be updated every 3 hours.</p>
                <?php endif; ?>
            </div>
            <?php
        } catch (Exception $e) {
            echo "<div class='result-error'><p>Error processing request: " . $e->getMessage() . "</p></div>";
        }
    }
    ?>

    <div class="tools-about">
        <h2>About Check Wallet Analysis</h2>
        <p>The Check Wallet Analysis tool allows you to view the balance and assets of a Solana wallet, including SOL, SPL tokens, and NFTs.</p>
    </div>
</div>
