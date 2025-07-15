<?php
// ============================================================================
// File: tools/wallet-analysis/wallet-analysis.php
// Description: Check wallet balance and assets (SOL, SPL tokens, NFTs, .sol domains) for a Solana wallet.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("wallet_analysis: bootstrap.php not found at $bootstrap_path", 'wallet_analysis_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Define cache directory and file
$cache_dir = WALLET_ANALYSIS_PATH . 'cache/';
$cache_file = $cache_dir . 'wallet_analysis_cache.json';
$names_cache_file = $cache_dir . 'names_cache.json';

// Check and create cache directory and files
if (!ensure_directory_and_file($cache_dir, $cache_file, 'wallet_analysis_log.txt') ||
    !ensure_directory_and_file($cache_dir, $names_cache_file, 'wallet_analysis_log.txt')) {
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("wallet_analysis: tools-api.php not found at $api_helper_path", 'wallet_analysis_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
log_message("wallet_analysis: tools-api.php loaded", 'wallet_analysis_log.txt', 'INFO');
?><link rel="stylesheet" href="/tools/wallet-analysis/wallet-analysis.css">
<div class="wallet-analysis">
    <?php
    $rate_limit_exceeded = false;// Check rate limit for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['walletAddress'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = "rate_limit_wallet_analysis:$ip";
    $rate_limit_count = isset($_SESSION[$rate_limit_key]) ? (int)$_SESSION[$rate_limit_key]['count'] : 0;
    $rate_limit_time = isset($_SESSION[$rate_limit_key]) ? (int)$_SESSION[$rate_limit_key]['time'] : 0;

    log_message("wallet_analysis: Rate limit check for IP=$ip, count=$rate_limit_count, time=$rate_limit_time", 'wallet_analysis_log.txt', 'DEBUG');

    if (time() - $rate_limit_time > 60) {
        $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
        log_message("wallet_analysis: Reset rate limit for IP=$ip, count=1", 'wallet_analysis_log.txt', 'INFO');
    } elseif ($rate_limit_count >= 5) {
        $rate_limit_exceeded = true;
        log_message("wallet_analysis: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'wallet_analysis_log.txt', 'ERROR');
        echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
    } else {
        $_SESSION[$rate_limit_key]['count'] = $rate_limit_count + 1;
        log_message("wallet_analysis: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'wallet_analysis_log.txt', 'INFO');
    }
}

// Always render form unless rate limit is exceeded
if (!$rate_limit_exceeded) {
    log_message("wallet_analysis: Rendering form", 'wallet_analysis_log.txt', 'INFO');
    ?>
    <div class="tools-form">
        <h2>Check Wallet Analysis</h2>
        <p>Enter a <strong>Solana Wallet Address</strong> to view its balance and assets, including SOL, SPL tokens, NFTs, and .sol domains.</p>
        <form id="walletAnalysisForm" method="POST" action="" data-tool="wallet-analysis">
            <?php
            try {
                $csrf_token = generate_csrf_token();
            } catch (Exception $e) {
                log_message("wallet_analysis: Failed to generate CSRF token: " . $e->getMessage(), 'wallet_analysis_log.txt', 'ERROR');
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
?>

<script>
// Override tab rendering with Solscan links for Token, Mint, Collection
document.addEventListener("DOMContentLoaded", function () {
    const updateLinks = (selector) => {
        document.querySelectorAll(selector).forEach(el => {
            const value = el.dataset.value;
            if (value && /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(value)) {
                const link = document.createElement('a');
                link.href = `https://solscan.io/token/${value}`;
                link.target = '_blank';
                link.innerText = value.slice(0, 4) + '...' + value.slice(-4);
                el.innerHTML = '';
                el.appendChild(link);
            }
        });
    };
    updateLinks('.token-address');
    updateLinks('.mint-address');
    updateLinks('.collection-address');
});
</script>

<div class="tools-about">
    <h2>About Check Wallet Analysis</h2>
    <p>The Check Wallet Analysis tool allows you to view the balance and assets of a Solana wallet, including SOL, SPL tokens, NFTs, and .sol domains.</p>
</div>
</div>
