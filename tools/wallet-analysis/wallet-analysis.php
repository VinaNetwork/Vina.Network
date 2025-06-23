<?php
// ============================================================================
// File: tools/wallet-analysis/wallet-analysis.php
// Description: Handles form submission and API calls for Check Wallet Analysis tool
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
define('VINANETWORK_ENTRY', true);
require_once '../bootstrap.php';
require_once '../tools-api.php';

$root_path = ROOT_PATH;
session_start();
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check if header.php and navbar.php exist
if (!file_exists($root_path . 'include/header.php')) {
    log_message("Header file not found at {$root_path}include/header.php", 'wallet_analysis_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Internal Server Error: Missing header.php</p></div>';
    exit;
}
if (!file_exists($root_path . 'include/navbar.php')) {
    log_message("Navbar file not found at {$root_path}include/navbar.php", 'wallet_analysis_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Internal Server Error: Missing navbar.php</p></div>';
    exit;
}

// Include header and navbar
include_once $root_path . 'include/header.php';
include_once $root_path . 'include/navbar.php';

// Define cache file using WALLET_ANALYSIS_PATH
$cache_file = WALLET_ANALYSIS_PATH . 'cache/wallet_analysis_cache.json';

// Check if cache directory is writable
if (!is_writable(WALLET_ANALYSIS_PATH . 'cache/')) {
    log_message("Cache directory " . WALLET_ANALYSIS_PATH . "cache/ is not writable", 'wallet_analysis_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Server error: Cache directory is not writable</p></div>';
    exit;
}

$cache_expiration = 3 * 3600; // 3 hours
$cache_data = [];
if (file_exists($cache_file)) {
    $cache_data = json_decode(file_get_contents($cache_file), true);
    if (!is_array($cache_data)) {
        $cache_data = [];
        log_message("wallet-analysis: Failed to parse cache file, initializing empty cache", 'wallet_analysis_log.txt', 'WARNING');
    }
}

$rate_limit_exceeded = false;
$walletAddress = '';
$result = null;
$cache_timestamp = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        log_message("wallet-analysis: Invalid CSRF token", 'wallet_analysis_log.txt', 'ERROR');
        http_response_code(403);
        echo '<div class="result-error"><p>Invalid CSRF token</p></div>';
        exit;
    }

    // Check rate limit
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = "rate_limit_wallet_analysis:$ip_address";
    $rate_limit_count = $_SESSION[$rate_limit_key] ?? 0;
    $rate_limit_time = $_SESSION["{$rate_limit_key}_time"] ?? 0;

    if (time() - $rate_limit_time > 60) {
        $rate_limit_count = 0;
        $_SESSION["{$rate_limit_key}_time"] = time();
    }

    if ($rate_limit_count >= 5) {
        $rate_limit_exceeded = true;
        log_message("wallet-analysis: Rate limit exceeded for IP $ip_address", 'wallet_analysis_log.txt', 'WARNING');
    } else {
        $_SESSION[$rate_limit_key] = $rate_limit_count + 1;
        $walletAddress = trim($_POST['walletAddress'] ?? '');

        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletAddress)) {
            log_message("wallet-analysis: Invalid wallet address: $walletAddress", 'wallet_analysis_log.txt', 'ERROR');
            $result = ['error' => 'Invalid wallet address'];
        } else {
            // Check cache
            if (isset($cache_data[$walletAddress]) && 
                isset($cache_data[$walletAddress]['timestamp']) && 
                (time() - $cache_data[$walletAddress]['timestamp'] < $cache_expiration)) {
                $result = $cache_data[$walletAddress]['data'];
                $cache_timestamp = date('Y-m-d H:i:s', $cache_data[$walletAddress]['timestamp']);
                log_message("wallet-analysis: Using cache for walletAddress=$walletAddress", 'wallet_analysis_log.txt');
            } else {
                // Call API
                $api_result = callAPI('getAssetsByOwner', ['ownerAddress' => $walletAddress]);
                if (isset($api_result['error'])) {
                    log_message("wallet-analysis: API error for walletAddress=$walletAddress: " . json_encode($api_result['error']), 'wallet_analysis_log.txt', 'ERROR');
                    $result = ['error' => $api_result['error']];
                } else {
                    $result = $api_result['result'];
                    $cache_data[$walletAddress] = [
                        'data' => $result,
                        'timestamp' => time()
                    ];
                    if (file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT), LOCK_EX) === false) {
                        log_message("wallet-analysis: Failed to write cache for walletAddress=$walletAddress", 'wallet_analysis_log.txt', 'ERROR');
                    } else {
                        log_message("wallet-analysis: Cached data for walletAddress=$walletAddress", 'wallet_analysis_log.txt');
                    }
                }
            }
        }
    }
}

?>

<section class="tools">
    <div class="tools-container">
        <h1>Vina Network Tools</h1>
        <div class="tools-nav">
            <a href="?tool=nft-info" class="tools-nav-link" data-tool="nft-info"><i class="fa-solid fa-circle-info"></i> NFT Info</a>
            <a href="?tool=nft-holders" class="tools-nav-link" data-tool="nft-holders"><i class="fas fa-user"></i> NFT Holders</a>
            <a href="?tool=wallet-analysis" class="tools-nav-link active" data-tool="wallet-analysis"><i class="fas fa-wallet"></i> Wallet Analysis</a>
        </div>
        <p class="note">Note: Only supports checking on the Solana blockchain.</p>
        <div class="tools-content">
            <!-- Form always displayed first -->
            <form id="walletAnalysisForm" action="" method="POST" class="tools-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <div class="input-group">
                    <input type="text" name="walletAddress" value="<?php echo htmlspecialchars($walletAddress); ?>" placeholder="Enter Solana Wallet Address" class="form-control">
                    <button type="submit" class="btn btn-primary">Check Wallet</button>
                </div>
                <div class="loader" style="display: none;"></div>
            </form>

            <?php if ($rate_limit_exceeded): ?>
                <div class="result-error">
                    <p>Rate limit exceeded. Please try again in a minute.</p>
                </div>
            <?php elseif ($result !== null): ?>
                <?php if (isset($result['error'])): ?>
                    <div class="result-error">
                        <p>Error: <?php echo htmlspecialchars($result['error']); ?></p>
                    </div>
                <?php else: ?>
                    <div class="tools-result wallet-analysis-result">
                        <div class="result-card">
                            <h3>Wallet Summary</h3>
                            <table class="wallet-info-table">
                                <tr>
                                    <th>Wallet Address</th>
                                    <td>
                                        <span class="short-address"><?php echo htmlspecialchars(substr($walletAddress, 0, 4) . '...' . substr($walletAddress, -4)); ?></span>
                                        <i class="fas fa-copy copy-icon" data-full="<?php echo htmlspecialchars($walletAddress); ?>" title="Copy full address"></i>
                                    </td>
                                </tr>
                                <tr>
                                    <th>SOL Balance</th>
                                    <td>
                                        <?php
                                        $sol_balance = $result['nativeBalance'] ?? 0;
                                        $sol_balance_usd = $sol_balance * 135; // Assume $135/SOL for demo
                                        echo htmlspecialchars(number_format($sol_balance, 4) . ' SOL (~$' . number_format($sol_balance_usd, 2) . ' USD)');
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php if (!empty($result['tokens'])): ?>
                            <h3>Tokens</h3>
                            <div class="table-container">
                                <table class="token-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Token Address</th>
                                            <th>Balance</th>
                                            <th>Value (USD)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($result['tokens'] as $token): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($token['info']['name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="short-address"><?php echo htmlspecialchars(substr($token['mint'], 0, 4) . '...' . substr($token['mint'], -4)); ?></span>
                                                    <i class="fas fa-copy copy-icon" data-full="<?php echo htmlspecialchars($token['mint']); ?>" title="Copy full address"></i>
                                                </td>
                                                <td><?php echo htmlspecialchars(number_format($token['amount'] / (10 ** $token['info']['decimals']), 4)); ?></td>
                                                <td><?php echo htmlspecialchars('$'. number_format($token['amount'] / (10 ** $token['info']['decimals']) * ($token['price'] ?? 0), 2)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($result['items'])): ?>
                            <h3>NFTs</h3>
                            <div class="table-container">
                                <table class="nft-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Mint Address</th>
                                            <th>Collection</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($result['items'] as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['content']['metadata']['name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="short-address"><?php echo htmlspecialchars(substr($item['id'], 0, 4) . '...' . substr($item['id'], -4)); ?></span>
                                                    <i class="fas fa-copy copy-icon" data-full="<?php echo htmlspecialchars($item['id']); ?>" title="Copy full address"></i>
                                                </td>
                                                <td>
                                                    <?php if (isset($item['grouping'][0]['group_value'])): ?>
                                                        <span class="short-address"><?php echo htmlspecialchars(substr($item['grouping'][0]['group_value'], 0, 4) . '...' . substr($item['grouping'][0]['group_value'], -4)); ?></span>
                                                        <i class="fas fa-copy copy-icon" data-full="<?php echo htmlspecialchars($item['grouping'][0]['group_value']); ?>" title="Copy full address"></i>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if ($cache_timestamp): ?>
                            <p class="cache-timestamp">Data cached at: <?php echo htmlspecialchars($cache_timestamp); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="tools-about">
                <p>This tool analyzes the balance and assets (SOL, SPL tokens, NFTs) of a Solana wallet address.</p>
            </div>
        </div>
    </div>
</section>

<?php
include_once $root_path . 'include/footer.php';
?>
