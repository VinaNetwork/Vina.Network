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
log_message("nft_info: Session started, session_id=" . session_id(), 'nft_info_log.txt', 'INFO');

// Cache directory and file
$cache_dir = __DIR__ . '/cache/';
$cache_file = $cache_dir . 'nft_info_cache.json';

// Create cache directory if it doesn't exist
if (!is_dir($cache_dir)) {
    if (!mkdir($cache_dir, 0755, true)) {
        log_message("nft_info: Failed to create cache directory at $cache_dir", 'nft_info_log.txt', 'ERROR');
        echo '<div class="result-error"><p>Cannot create cache directory</p></div>';
        exit;
    }
    log_message("nft_info: Created cache directory at $cache_dir", 'nft_info_log.txt', 'INFO');
}
if (!file_exists($cache_file)) {
    if (file_put_contents($cache_file, json_encode([])) === false) {
        log_message("nft_info: Failed to create cache file at $cache_file", 'nft_info_log.txt', 'ERROR');
        echo '<div class="result-error"><p>Cannot create cache file</p></div>';
        exit;
    }
    chmod($cache_file, 0644);
    log_message("nft_info: Created cache file at $cache_file", 'nft_info_log.txt', 'INFO');
}
if (!is_writable($cache_file)) {
    log_message("nft_info: Cache file $cache_file is not writable", 'nft_info_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cache file is not writable</p></div>';
    exit;
}

// Page configuration
$root_path = '../../';
$page_title = 'Check NFT Info - Vina Network';
$page_description = 'Check detailed information for a single Solana NFT using its Mint Address.';
$page_css = ['../../css/vina.css', '../tools.css'];

log_message("nft_info: Including header.php", 'nft_info_log.txt', 'INFO');
include_once $root_path . 'include/header.php';
log_message("nft_info: Including navbar.php", 'nft_info_log.txt', 'INFO');
include_once $root_path . 'include/navbar.php';

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft_info: tools-api.php not found at $api_helper_path", 'nft_info_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
log_message("nft_info: tools-api.php loaded", 'nft_info_log.txt', 'INFO');

log_message("nft_info: Rendering form", 'nft_info_log.txt', 'INFO');
?>

<div class="t-6 nft-info-content">
    <div class="t-7">
        <h2>Check NFT Info</h2>
        <p>Enter the <strong>NFT Mint Address</strong> to view detailed information. For example, find this address on MagicEden under "Details" > "Mint Address".</p>
        <form id="nftInfoForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="text" name="mintAddress" id="mintAddressInfo" placeholder="Enter NFT Mint Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <button type="submit" class="cta-button">Check Info</button>
        </form>
        <div class="loader"></div>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        log_message("nft_info: POST request received, mintAddress=" . ($_POST['mintAddress'] ?? 'N/A'), 'nft_info_log.txt', 'INFO');
        try {
            // Validate CSRF token
            log_message("nft_info: Validating CSRF token", 'nft_info_log.txt', 'INFO');
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                log_message("nft_info: Invalid CSRF token", 'nft_info_log.txt', 'ERROR');
                throw new Exception('Invalid CSRF token');
            }

            // Validate Mint Address
            $mintAddress = trim($_POST['mintAddress']);
            $mintAddress = preg_replace('/\s+/', '', $mintAddress);
            log_message("nft_info: Validating mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                log_message("nft_info: Invalid Mint Address format", 'nft_info_log.txt', 'ERROR');
                throw new Exception('Invalid Mint Address format');
            }

            // Check cache
            $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
            $cache_expiration = 3 * 3600; // Cache for 3 hours
            $cache_valid = isset($cache_data[$mintAddress]) && (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration);
            log_message("nft_info: Cache valid=$cache_valid for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');

            if (!$cache_valid) {
                // Call getAsset API
                log_message("nft_info: Calling getAsset API for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');
                $params = ['id' => $mintAddress];
                $asset = callAPI('getAsset', $params, 'POST');

                log_message("nft_info: API raw response=" . json_encode($asset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'nft_info_log.txt', 'DEBUG');
                if (isset($asset['error'])) {
                    log_message("nft_info: API error: " . json_encode($asset['error']), 'nft_info_log.txt', 'ERROR');
                    throw new Exception(is_array($asset['error']) ? ($asset['error']['message'] ?? 'API error') : $asset['error']);
                }
                if (empty($asset['result']) || !isset($asset['result']['id'])) {
                    log_message("nft_info: NFT not found or invalid response for mintAddress=$mintAddress", 'nft_info_log.txt', 'ERROR');
                    throw new Exception('NFT not found for Mint Address');
                }

                // Extract result from API response
                $asset = $asset['result'];

                // Format data
                $formatted_data = [
                    'mint_address' => $asset['id'] ?? $mintAddress,
                    'name' => $asset['content']['metadata']['name'] ?? 'N/A',
                    'image' => $asset['content']['links']['image'] ?? '',
                    'attributes' => isset($asset['content']['metadata']['attributes']) && !empty($asset['content']['metadata']['attributes']) ? json_encode($asset['content']['metadata']['attributes'], JSON_PRETTY_PRINT) : 'N/A',
                    'owner' => $asset['ownership']['owner'] ?? 'N/A',
                    'collection' => isset($asset['grouping'][0]['group_value']) ? $asset['grouping'][0]['group_value'] : 'N/A',
                    'is_compressed' => isset($asset['compression']['compressed']) ? $asset['compression']['compressed'] : false,
                    'is_burned' => isset($asset['ownership']['frozen']) ? $asset['ownership']['frozen'] : false,
                    'is_listed' => isset($asset['marketplace_listings']) && !empty($asset['marketplace_listings']) ? true : false,
                    'timestamp' => time()
                ];
                log_message("nft_info: Formatted data=" . json_encode($formatted_data, JSON_PRETTY_PRINT), 'nft_info_log.txt', 'DEBUG');

                // Save to cache
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

            // Output results as HTML
            ob_start();
            ?>
            <div class="result-section">
                <div class="nft-details">
                    <h3>NFT Details</h3>
                    <div class="nft-card">
                        <div class="nft-image">
                            <?php if ($formatted_data['image']): ?>
                                <img src="<?php echo htmlspecialchars($formatted_data['image']); ?>" alt="NFT Image">
                            <?php else: ?>
                                <p>No image available</p>
                            <?php endif; ?>
                        </div>
                        <div class="nft-info-table">
                            <table>
                                <tr><th>Mint Address</th><td style="word-break: break-all;"><?php echo htmlspecialchars($formatted_data['mint_address']); ?></td></tr>
                                <tr><th>Name</th><td><?php echo htmlspecialchars($formatted_data['name']); ?></td></tr>
                                <tr><th>Attributes</th><td><pre><?php echo htmlspecialchars($formatted_data['attributes']); ?></pre></td></tr>
                                <tr><th>Owner</th><td style="word-break: break-all;"><?php echo htmlspecialchars($formatted_data['owner']); ?></td></tr>
                                <tr><th>Collection</th><td style="word-break: break-all;"><?php echo htmlspecialchars($formatted_data['collection']); ?></td></tr>
                                <tr><th>Compressed</th><td><?php echo $formatted_data['is_compressed'] ? 'Yes' : 'No'; ?></td></tr>
                                <tr><th>Burned</th><td><?php echo $formatted_data['is_burned'] ? 'Yes' : 'No'; ?></td></tr>
                                <tr><th>Listed</th><td><?php echo $formatted_data['is_listed'] ? 'Yes' : 'No'; ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <?php if ($cache_valid): ?>
                    <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$mintAddress]['timestamp']) . ' UTC+0'; ?></p>
                <?php endif; ?>
                <div class="export-section">
                    <form method="POST" action="/tools/nft-info/nft-info-export.php" class="export-form">
                        <input type="hidden" name="mintAddress" value="<?php echo htmlspecialchars($mintAddress); ?>">
                        <div class="export-controls">
                            <select name="export_format" class="export-format">
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                            </select>
                            <button type="submit" name="export_type" value="all" class="cta-button export-btn">Export NFT Info</button>
                        </div>
                    </form>
                    <div class="progress-container" style="display: none;">
                        <p>Exporting... Please wait.</p>
                        <div class="progress-bar"><div class="progress-bar-fill" style="width: 0%;"></div></div>
                    </div>
                </div>
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

    <div class="t-9">
        <h2>About Check NFT Info</h2>
        <p>The Check NFT Info tool allows you to view detailed information for a specific Solana NFT by entering its Mint Address.</p>
    </div>
</div>
