<?php
// ============================================================================
// File: tools/nft-info/nft-info.php
// Description: Check detailed information for a single Solana NFT using its Mint Address.
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
    log_message("nft_info: bootstrap.php not found at $bootstrap_path", 'nft_info_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Start session and configure error logging
session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

// Cache directory and file
$cache_dir = NFT_INFO_PATH . 'cache/';
$cache_file = $cache_dir . 'nft_info_cache.json';

// Check and create cache directory and file
if (!ensure_directory_and_file($cache_dir, $cache_file, 'nft_info_log.txt')) {
    log_message("nft_info: Cache setup failed for $cache_dir or $cache_file", 'nft_info_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

// Page configuration
$root_path = '../../';
$page_title = 'Check NFT Info - Vina Network';
$page_description = 'Check detailed information for a single Solana NFT or Collection using its Mint Address or Collection Address.';
$page_css = ['../../css/vina.css', '../tools.css'];

log_message("nft_info: Including header.php", 'nft_info_log.txt', 'INFO');
include_once $root_path . 'include/header.php';
log_message("nft_info: Including navbar.php", 'nft_info_log.txt', 'INFO');
include_once $root_path . 'include/navbar.php';

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft_info: tools-api.php not found at $api_helper_path", 'nft_info_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
log_message("nft_info: tools-api.php loaded", 'nft_info_log.txt', 'INFO');
?>

<div class="nft-info">
    <!-- Render form unless rate limit exceeded -->
    <?php
    $rate_limit_exceeded = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        // Rate limiting: 5 requests per minute per IP for form submission
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_limit_key = "rate_limit_nft_info:$ip";
        $rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['count'] : 0;
        $rate_limit_time = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['time'] : 0;
        if (time() - $rate_limit_time > 60) {
            $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
            log_message("nft_info: Reset rate limit for IP=$ip, count=1", 'nft_info_log.txt', 'INFO');
        } elseif ($rate_limit_count >= 5) {
            log_message("nft_info: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'nft_info_log.txt', 'ERROR');
            $rate_limit_exceeded = true;
            echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
        } else {
            $_SESSION[$rate_limit_key]['count']++;
            log_message("nft_info: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'nft_info_log.txt', 'INFO');
        }
    }

    if (!$rate_limit_exceeded) {
        log_message("nft_info: Rendering form", 'nft_info_log.txt', 'INFO');
        ?>
        <div class="tools-form">
            <h2>Check NFT Info</h2>
            <p>Enter the <strong>NFT Mint Address</strong> or <strong>NFT Collection Address</strong> to view detailed information. For example, find these addresses on MagicEden under "Details" > "Mint Address" or "On-chain Collection".</p>
            <form id="nftInfoForm" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="text" name="mintAddress" id="mintAddressInfo" placeholder="Enter NFT Mint or Collection Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
                <button type="submit" class="cta-button">Check Info</button>
            </form>
            <div class="loader"></div>
        </div>
        <?php
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress']) && !$rate_limit_exceeded) {
        log_message("nft_info: POST request received, mintAddress=" . ($_POST['mintAddress'] ?? 'N/A'), 'nft_info_log.txt', 'INFO');
        try {
            log_message("nft_info: Validating CSRF token", 'nft_info_log.txt', 'INFO');
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                log_message("nft_info: Invalid CSRF token", 'nft_info_log.txt', 'ERROR');
                throw new Exception('Invalid CSRF token');
            }

            $mintAddress = trim($_POST['mintAddress']);
            $mintAddress = preg_replace('/\s+/', '', $mintAddress);
            log_message("nft_info: Validating mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                log_message("nft_info: Invalid Mint Address format", 'nft_info_log.txt', 'ERROR');
                throw new Exception('Invalid Mint or Collection Address format');
            }

            $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
            $cache_expiration = 3 * 3600;
            $cache_valid = isset($cache_data[$mintAddress]) && (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration);
            log_message("nft_info: Cache valid=$cache_valid for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');

            if (!$cache_valid) {
                log_message("nft_info: Calling getAsset API for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');
                $params = ['id' => $mintAddress];
                $asset = callAPI('getAsset', $params, 'POST');

                log_message("nft_info: API raw response=" . json_encode($asset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'nft_info_log.txt', 'DEBUG');
                if (isset($asset['error'])) {
                    log_message("nft_info: API error: " . json_encode($asset['error']), 'nft_info_log.txt', 'ERROR');
                    throw new Exception(is_array($asset['error']) ? ($asset['error']['message'] ?? 'API error') : $asset['error']);
                }
                if (empty($asset['result']) || !isset($asset['result']['id'])) {
                    log_message("nft_info: Asset not found or invalid response for mintAddress=$mintAddress", 'nft_info_log.txt', 'ERROR');
                    throw new Exception('Asset not found for Mint or Collection Address');
                }

                $asset = $asset['result'];

                // Check if the asset is a collection (grouping is empty or interface indicates collection)
                $is_collection = empty($asset['grouping']) || (isset($asset['interface']) && in_array($asset['interface'], ['V1_NFT', 'ProgrammableNFT']) && empty($asset['grouping']));

                $formatted_data = [
                    'mint_address' => $asset['id'] ?? $mintAddress,
                    'name' => $asset['content']['metadata']['name'] ?? 'N/A',
                    'image' => $asset['content']['links']['image'] ?? '',
                    'attributes' => isset($asset['content']['metadata']['attributes']) && !empty($asset['content']['metadata']['attributes']) ? json_encode($asset['content']['metadata']['attributes'], JSON_PRETTY_PRINT) : 'N/A',
                    'owner' => $asset['ownership']['owner'] ?? 'N/A',
                    'collection' => $is_collection ? $mintAddress : (isset($asset['grouping'][0]['group_value']) ? $asset['grouping'][0]['group_value'] : 'N/A'),
                    'is_compressed' => isset($asset['compression']['compressed']) ? $asset['compression']['compressed'] : false,
                    'is_burned' => isset($asset['ownership']['frozen']) ? $asset['ownership']['frozen'] : false,
                    'is_listed' => isset($asset['marketplace_listings']) && !empty($asset['marketplace_listings']) ? true : false,
                    'timestamp' => time()
                ];
                log_message("nft_info: Formatted data=" . json_encode($formatted_data, JSON_PRETTY_PRINT), 'nft_info_log.txt', 'DEBUG');

                $cache_data[$mintAddress] = [
                    'data' => $formatted_data,
                    'timestamp' => time()
                ];
                $fp = fopen($cache_file, 'c');
                if (flock($fp, LOCK_EX)) {
                    if (!file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT))) {
                        log_message("nft_info: Failed to write to cache file", 'nft_info_log.txt', 'ERROR');
                        flock($fp, LOCK_UN);
                        fclose($fp);
                        throw new Exception('Failed to write to cache file');
                    }
                    flock($fp, LOCK_UN);
                } else {
                    log_message("nft_info: Failed to lock cache file", 'nft_info_log.txt', 'ERROR');
                    fclose($fp);
                    throw new Exception('Failed to lock cache file');
                }
                fclose($fp);
                log_message("nft_info: Cache updated for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');
            } else {
                $formatted_data = $cache_data[$mintAddress]['data'];
                log_message("nft_info: Retrieved from cache for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');
            }

            ob_start();
            ?>
            <div class="tools-result nft-info-result">
                <h2><?php echo $formatted_data['collection'] === $mintAddress ? 'Collection Details' : 'NFT Details'; ?></h2>
                <div class="result-summary">
                    <div class="result-card">
                        <div class="nft-image">
                            <?php if ($formatted_data['image']): ?>
                                <img src="<?php echo htmlspecialchars($formatted_data['image']); ?>" alt="NFT Image">
                            <?php else: ?>
                                <p>No image available</p>
                            <?php endif; ?>
                        </div>
                        <div class="nft-info-table">
                            <table>
                                <tr>
                                    <th><?php echo $formatted_data['collection'] === $mintAddress ? 'Collection Address' : 'Mint Address'; ?></th>
                                    <td>
                                        <span><?php echo substr(htmlspecialchars($formatted_data['mint_address']), 0, 4) . '...' . substr(htmlspecialchars($formatted_data['mint_address']), -4); ?></span>
                                        <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($formatted_data['mint_address']); ?>"></i>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Name</th>
                                    <td><?php echo htmlspecialchars($formatted_data['name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Attributes</th>
                                    <td><pre><?php echo htmlspecialchars($formatted_data['attributes']); ?></pre></td>
                                </tr>
                                <tr>
                                    <th>Owner</th>
                                    <td>
                                        <?php if ($formatted_data['owner'] !== 'N/A' && preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $formatted_data['owner'])): ?>
                                            <span><?php echo substr(htmlspecialchars($formatted_data['owner']), 0, 4) . '...' . substr(htmlspecialchars($formatted_data['owner']), -4); ?></span>
                                            <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($formatted_data['owner']); ?>"></i>
                                        <?php else: ?>
                                            <span>N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Collection</th>
                                    <td>
                                        <?php if ($formatted_data['collection'] !== 'N/A' && preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $formatted_data['collection'])): ?>
                                            <span><?php echo substr(htmlspecialchars($formatted_data['collection']), 0, 4) . '...' . substr(htmlspecialchars($formatted_data['collection']), -4); ?></span>
                                            <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($formatted_data['collection']); ?>"></i>
                                        <?php else: ?>
                                            <span>N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Compressed</th>
                                    <td><?php echo $formatted_data['is_compressed'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                                <tr>
                                    <th>Burned</th>
                                    <td><?php echo $formatted_data['is_burned'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                                <tr>
                                    <th>Listed</th>
                                    <td><?php echo $formatted_data['is_listed'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <?php if ($cache_valid): ?>
                    <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$mintAddress]['timestamp']) . ' UTC+0'; ?>. Data will be updated every 3 hours.</p>
                <?php endif; ?>
            </div>
            <?php
            $output = ob_get_clean();
            log_message("nft_info: Output length: " . strlen($output), 'nft_info_log.txt', 'INFO');
            echo $output;
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            log_message("nft_info: Exception - Message: $error_msg, File: " . $e->getFile() . ", Line: " . $e->getLine(), 'nft_info_log.txt', 'ERROR');
            echo "<div class='result-error'><p>$error_msg</p></div>";
        }
    }
    log_message("nft_info: Script ended", 'nft_info_log.txt', 'INFO');
    ?>

    <div class="tools-about">
        <h2>About Check NFT Info</h2>
        <p>The Check NFT Info tool allows you to view detailed information for a specific Solana NFT or Collection by entering its Mint Address or Collection Address.</p>
    </div>
</div>
