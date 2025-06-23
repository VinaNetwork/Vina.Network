<?php
// ============================================================================
// File: tools/nft-info/nft-info.php
// Description: Handles form submission and API calls for Check NFT Info tool
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
    log_message("Header file not found at {$root_path}include/header.php", 'nft_info_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Internal Server Error: Missing header.php</p></div>';
    exit;
}
if (!file_exists($root_path . 'include/navbar.php')) {
    log_message("Navbar file not found at {$root_path}include/navbar.php", 'nft_info_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Internal Server Error: Missing navbar.php</p></div>';
    exit;
}

// Include header and navbar
include_once $root_path . 'include/header.php';
include_once $root_path . 'include/navbar.php';

// Define cache file using NFT_INFO_PATH
$cache_file = NFT_INFO_PATH . 'cache/nft_info_cache.json';

// Check if cache directory is writable
if (!is_writable(NFT_INFO_PATH . 'cache/')) {
    log_message("Cache directory " . NFT_INFO_PATH . "cache/ is not writable", 'nft_info_log.txt', 'ERROR');
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
        log_message("nft-info: Failed to parse cache file, initializing empty cache", 'nft_info_log.txt', 'WARNING');
    }
}

$rate_limit_exceeded = false;
$mintAddress = '';
$result = null;
$cache_timestamp = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        log_message("nft-info: Invalid CSRF token", 'nft_info_log.txt', 'ERROR');
        http_response_code(403);
        echo '<div class="result-error"><p>Invalid CSRF token</p></div>';
        exit;
    }

    // Check rate limit
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = "rate_limit_nft_info:$ip_address";
    $rate_limit_count = $_SESSION[$rate_limit_key] ?? 0;
    $rate_limit_time = $_SESSION["{$rate_limit_key}_time"] ?? 0;

    if (time() - $rate_limit_time > 60) {
        $rate_limit_count = 0;
        $_SESSION["{$rate_limit_key}_time"] = time();
    }

    if ($rate_limit_count >= 5) {
        $rate_limit_exceeded = true;
        log_message("nft-info: Rate limit exceeded for IP $ip_address", 'nft_info_log.txt', 'WARNING');
    } else {
        $_SESSION[$rate_limit_key] = $rate_limit_count + 1;
        $mintAddress = trim($_POST['mintAddress'] ?? '');

        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
            log_message("nft-info: Invalid mint address: $mintAddress", 'nft_info_log.txt', 'ERROR');
            $result = ['error' => 'Invalid NFT mint address'];
        } else {
            // Check cache
            if (isset($cache_data[$mintAddress]) && 
                isset($cache_data[$mintAddress]['timestamp']) && 
                (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration)) {
                $result = $cache_data[$mintAddress]['data'];
                $cache_timestamp = date('Y-m-d H:i:s', $cache_data[$mintAddress]['timestamp']);
                log_message("nft-info: Using cache for mintAddress=$mintAddress", 'nft_info_log.txt');
            } else {
                // Call API
                $api_result = callAPI('getAsset', ['id' => $mintAddress]);
                if (isset($api_result['error'])) {
                    log_message("nft-info: API error for mintAddress=$mintAddress: " . json_encode($api_result['error']), 'nft_info_log.txt', 'ERROR');
                    $result = ['error' => $api_result['error']];
                } else {
                    $result = $api_result['result'];
                    $cache_data[$mintAddress] = [
                        'data' => $result,
                        'timestamp' => time()
                    ];
                    if (file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT), LOCK_EX) === false) {
                        log_message("nft-info: Failed to write cache for mintAddress=$mintAddress", 'nft_info_log.txt', 'ERROR');
                    } else {
                        log_message("nft-info: Cached data for mintAddress=$mintAddress", 'nft_info_log.txt');
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
            <a href="?tool=nft-info" class="tools-nav-link active" data-tool="nft-info"><i class="fa-solid fa-circle-info"></i> NFT Info</a>
            <a href="?tool=nft-holders" class="tools-nav-link" data-tool="nft-holders"><i class="fas fa-user"></i> NFT Holders</a>
            <a href="?tool=wallet-analysis" class="tools-nav-link" data-tool="wallet-analysis"><i class="fas fa-wallet"></i> Wallet Analysis</a>
        </div>
        <p class="note">Note: Only supports checking on the Solana blockchain.</p>
        <div class="tools-content">
            <!-- Form always displayed first -->
            <form id="nftInfoForm" action="" method="POST" class="tools-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <div class="input-group">
                    <input type="text" name="mintAddress" value="<?php echo htmlspecialchars($mintAddress); ?>" placeholder="Enter NFT Mint Address" class="form-control">
                    <button type="submit" class="btn btn-primary">Check Info</button>
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
                    <div class="tools-result nft-info-result">
                        <div class="result-card">
                            <?php if (isset($result['content']['links']['image'])): ?>
                                <img src="<?php echo htmlspecialchars($result['content']['links']['image']); ?>" alt="NFT Image" class="nft-image">
                            <?php endif; ?>
                            <table class="nft-info-table">
                                <tr>
                                    <th>Mint Address</th>
                                    <td>
                                        <span class="short-address"><?php echo htmlspecialchars(substr($result['id'], 0, 4) . '...' . substr($result['id'], -4)); ?></span>
                                        <i class="fas fa-copy copy-icon" data-full="<?php echo htmlspecialchars($result['id']); ?>" title="Copy full address"></i>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Name</th>
                                    <td><?php echo htmlspecialchars($result['content']['metadata']['name'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Attributes</th>
                                    <td>
                                        <?php
                                        $attributes = $result['content']['metadata']['attributes'] ?? [];
                                        if (empty($attributes)) {
                                            echo 'N/A';
                                        } else {
                                            echo '<ul>';
                                            foreach ($attributes as $attr) {
                                                echo '<li>' . htmlspecialchars($attr['trait_type'] . ': ' . $attr['value']) . '</li>';
                                            }
                                            echo '</ul>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Owner</th>
                                    <td>
                                        <span class="short-address"><?php echo htmlspecialchars(substr($result['ownership']['owner'], 0, 4) . '...' . substr($result['ownership']['owner'], -4)); ?></span>
                                        <i class="fas fa-copy copy-icon" data-full="<?php echo htmlspecialchars($result['ownership']['owner']); ?>" title="Copy full address"></i>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Collection</th>
                                    <td>
                                        <?php if (isset($result['grouping'][0]['group_value'])): ?>
                                            <span class="short-address"><?php echo htmlspecialchars(substr($result['grouping'][0]['group_value'], 0, 4) . '...' . substr($result['grouping'][0]['group_value'], -4)); ?></span>
                                            <i class="fas fa-copy copy-icon" data-full="<?php echo htmlspecialchars($result['grouping'][0]['group_value']); ?>" title="Copy full address"></i>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Compressed</th>
                                    <td><?php echo $result['compression']['compressed'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                                <tr>
                                    <th>Burned</th>
                                    <td><?php echo $result['burnt'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                                <tr>
                                    <th>Listed</th>
                                    <td><?php echo !empty($result['token_info']['supply']) ? 'Yes' : 'No'; ?></td>
                                </tr>
                            </table>
                            <?php if ($cache_timestamp): ?>
                                <p class="cache-timestamp">Data cached at: <?php echo htmlspecialchars($cache_timestamp); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="tools-about">
                <p>This tool allows you to check detailed information about an NFT on the Solana blockchain using its Mint Address.</p>
            </div>
        </div>
    </div>
</section>

<?php
include_once $root_path . 'include/footer.php';
?>
