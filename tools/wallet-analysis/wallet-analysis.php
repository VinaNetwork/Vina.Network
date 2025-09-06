<?php
// ============================================================================
// File: tools/wallet-analysis/wallet-analysis.php
// Description: Check wallet balance and assets (SOL, SPL tokens, NFTs, .sol domains) for a Solana wallet.
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
require_once $root_path . 'tools/bootstrap.php';

// Define cache directory and files
$cache_dir = TOOLS_PATH . 'cache/';
$cache_file = $cache_dir . 'wallet_analysis_cache.json';
$names_cache_file = $cache_dir . 'names_cache.json';
log_message("wallet_analysis: Checking cache directory: $cache_dir, files: $cache_file, $names_cache_file", 'nft-analysis.log', 'tools', 'DEBUG');

// Check and create cache directory and files
if (!ensure_directory_and_file($cache_dir, $cache_file) ||
    !ensure_directory_and_file($cache_dir, $names_cache_file)) {
    log_message("wallet_analysis: Cache setup failed for $cache_dir, $cache_file or $names_cache_file", 'nft-analysis.log', 'tools', 'ERROR');
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

// Load API helper
$api_helper_path = $root_path . 'tools/core/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("wallet_analysis: tools-api.php not found at $api_helper_path", 'nft-analysis.log', 'tools', 'ERROR');
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
log_message("wallet_analysis: tools-api.php loaded", 'nft-analysis.log', 'tools', 'INFO');
?>

<link rel="stylesheet" href="/tools/wallet-analysis/wallet-analysis.css">
<div class="wallet-analysis">
    <?php
    $rate_limit_exceeded = false;

    // Check rate limit for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['walletAddress'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_limit_key = "rate_limit_wallet_analysis:$ip";
        $rate_limit_count = isset($_SESSION[$rate_limit_key]) ? (int)$_SESSION[$rate_limit_key]['count'] : 0;
        $rate_limit_time = isset($_SESSION[$rate_limit_key]) ? (int)$_SESSION[$rate_limit_key]['time'] : 0;

        log_message("wallet_analysis: Rate limit check for IP=$ip, count=$rate_limit_count, time=$rate_limit_time", 'nft-analysis.log', 'tools', 'DEBUG');

        if (time() - $rate_limit_time > 60) {
            $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
            log_message("wallet_analysis: Reset rate limit for IP=$ip, count=1", 'nft-analysis.log', 'tools', 'INFO');
        } elseif ($rate_limit_count >= 5) {
            $rate_limit_exceeded = true;
            log_message("wallet_analysis: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'nft-analysis.log', 'tools', 'ERROR');
            echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
        } else {
            $_SESSION[$rate_limit_key]['count'] = $rate_limit_count + 1;
            log_message("wallet_analysis: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'nft-analysis.log', 'tools', 'INFO');
        }
    }

    // Always render form unless rate limit is exceeded
    if (!$rate_limit_exceeded) {
        log_message("wallet_analysis: Rendering form", 'nft-analysis.log', 'tools', 'INFO');
        ?>
        <div class="tools-form">
            <h2>Check Wallet Analysis</h2>
            <p>Enter a <strong>Solana Wallet Address</strong> to view its balance and assets, including SOL, SPL tokens, NFTs, and .sol domains.</p>
            <form id="walletAnalysisForm" method="POST" action="" data-tool="wallet-analysis">
                <?php
                try {
                    $csrf_token = generate_csrf_token();
                } catch (Exception $e) {
                    log_message("wallet_analysis: Failed to generate CSRF token: " . $e->getMessage(), 'nft-analysis.log', 'tools', 'ERROR');
                    $csrf_token = '';
                }
                ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">          
                <div class="input-wrapper">
                    <input type="text" name="walletAddress" id="walletAddress" placeholder="Enter Solana Wallet Address" required value="<?php echo isset($_POST['walletAddress']) ? htmlspecialchars($_POST['walletAddress']) : ''; ?>">
                    <span class="clear-input" title="Clear input">×</span>
                </div>
                <button type="submit" class="cta-button">Check</button>
            </form>
            <div class="loader"></div>
        </div>
        <?php
    }

    // Handle form submission
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

            log_message("wallet_analysis: Processing walletAddress=$walletAddress", 'nft-analysis.log', 'tools', 'INFO');

            $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
            $cache_expiration = 3 * 3600;
            $cache_valid = isset($cache_data[$walletAddress]) && (time() - $cache_data[$walletAddress]['timestamp'] < $cache_expiration);

            log_message("wallet_analysis: Cache check for walletAddress=$walletAddress, cache_valid=$cache_valid", 'nft-analysis.log', 'tools', 'DEBUG');

            if (!$cache_valid) {
                $formatted_data = [
                    'wallet_address' => $walletAddress,
                    'sol_balance' => 0.0,
                    'sol_price_usd' => 0.0,
                    'tokens' => [],
                    'nfts' => [],
                    'sol_domains' => [], // Load on tab click
                    'timestamp' => time()
                ];

                // Fetch Tokens, NFTs, and SOL balance
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
                $formatted_data['sol_balance'] = isset($result['nativeBalance']['lamports']) ? $result['nativeBalance']['lamports'] / 1000000000 : 0.0;
                $formatted_data['sol_price_usd'] = isset($result['nativeBalance']['total_price']) ? $result['nativeBalance']['total_price'] : 0.0;

                foreach ($result['items'] as $item) {
                    log_message("wallet_analysis: Processing item, id=" . ($item['id'] ?? 'N/A') . ", interface=" . ($item['interface'] ?? 'N/A'), 'nft-analysis.log', 'tools', 'DEBUG');
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
                $fp = fopen($cache_file, 'c');
                if (flock($fp, LOCK_EX)) {
                    if (file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT)) === false) {
                        log_message("wallet_analysis: Failed to write cache file at $cache_file", 'nft-analysis.log', 'tools', 'ERROR');
                        throw new Exception('Failed to write cache file');
                    }
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
                log_message("wallet_analysis: Successfully processed and cached Tokens, NFTs, and SOL balance for walletAddress=$walletAddress, tokens=" . count($formatted_data['tokens']) . ", nfts=" . count($formatted_data['nfts']), 'nft-analysis.log', 'tools', 'INFO');
            } else {
                $formatted_data = $cache_data[$walletAddress]['data'];
                log_message("wallet_analysis: Used cached Tokens, NFTs, and SOL balance for walletAddress=$walletAddress, tokens=" . count($formatted_data['tokens']) . ", nfts=" . count($formatted_data['nfts']), 'nft-analysis.log', 'tools', 'INFO');
            }

            // Store formatted_data in session for tab navigation
            $_SESSION['wallet_analysis_data'] = $formatted_data;
            $_SESSION['wallet_analysis_timestamp'] = $cache_data[$walletAddress]['timestamp'] ?? time();

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

                <!-- Tab Navigation -->
                <div class="wallet-tabs">
                    <a href="?tab=token" class="wallet-tab-link <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'token') ? 'active' : ''; ?>" data-tab="token">
                        <i class="fas fa-coins"></i> Tokens
                    </a>
                    <a href="?tab=nft" class="wallet-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'nft') ? 'active' : ''; ?>" data-tab="nft">
                        <i class="fas fa-image"></i> NFTs
                    </a>
                    <a href="?tab=domain" class="wallet-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'domain') ? 'active' : ''; ?>" data-tab="domain">
                        <i class="fas fa-globe"></i> Domains
                    </a>
                </div>

                <!-- Tab Content -->
                <div class="wallet-tab-content">
                    <?php
                    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'token';
                    $valid_tabs = ['token', 'nft', 'domain'];
                    if (!in_array($tab, $valid_tabs)) {
                        $tab = 'token';
                        log_message("wallet_analysis: Invalid tab, defaulted to token", 'nft-analysis.log', 'tools', 'ERROR');
                    }

                    $tab_file = __DIR__ . '/' . $tab . '.php';
                    if (file_exists($tab_file)) {
                        log_message("wallet_analysis: Including tab file: $tab_file", 'nft-analysis.log', 'tools', 'INFO');
                        include $tab_file;
                    } else {
                        echo "<div class='result-error'><p>Error: Tab file not found at $tab_file.</p></div>";
                        log_message("wallet_analysis: Tab file not found: $tab_file", 'nft-analysis.log', 'tools', 'ERROR');
                    }
                    ?>
                </div>

                <?php if ($cache_valid): ?>
                <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $_SESSION['wallet_analysis_timestamp']) . ' UTC+0'; ?>. Data will be updated every 3 hours.</p>
                <?php endif; ?>
            </div>
            <?php
        } catch (Exception $e) {
            echo "<div class='result-error'><p>Error processing request: " . htmlspecialchars($e->getMessage()) . "</p></div>";
            log_message("wallet_analysis: Exception - " . $e->getMessage(), 'nft-analysis.log', 'tools', 'ERROR');
        }
    }
    ?>

    <div class="tools-about">
        <h2>About Check Wallet Analysis</h2>
        <p>The Check Wallet Analysis tool allows you to view the balance and assets of a Solana wallet, including SOL, SPL tokens, NFTs, and .sol domains.</p>
    </div>
</div>
