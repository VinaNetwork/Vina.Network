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

// Log script start
log_message("nft-info: Script started, method={$_SERVER['REQUEST_METHOD']}", 'nft_info_log.txt', 'INFO');

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-info: bootstrap.php not found at $bootstrap_path", 'nft_info_log.txt', 'ERROR');
    exit("<div class='result-error'><p>Error: Cannot find bootstrap.php</p></div>");
}
require_once $bootstrap_path;

// Start session and configure error logging
session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);
log_message("nft-info: Session started, session_id=" . session_id(), 'nft_info_log.txt', 'INFO');

// Cache directory and file
$cache_dir = __DIR__ . '/cache/';
$cache_file = $cache_dir . 'nft_info_cache.json';

// Create cache directory if it doesn't exist
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
    log_message("nft-info: Created cache directory at $cache_dir", 'nft_info_log.txt', 'INFO');
}
if (!file_exists($cache_file)) {
    $attempts = 3;
    while ($attempts > 0) {
        if (file_put_contents($cache_file, json_encode([]))) {
            chmod($cache_file, 0644);
            log_message("nft-info: Created cache file at $cache_file", 'nft_info_log.txt', 'INFO');
            break;
        }
        $attempts--;
        sleep(1);
    }
    if (!file_exists($cache_file)) {
        log_message("nft-info: Failed to create cache file at $cache_file", 'nft_info_log.txt', 'ERROR');
        exit("<div class='result-error'><p>Error: Cannot create cache file</p></div>");
    }
}
if (!is_writable($cache_file)) {
    log_message("nft-info: Cache file $cache_file is not writable", 'nft_info_log.txt', 'ERROR');
    exit("<div class='result-error'><p>Error: Cache file is not writable</p></div>");
}

// Page configuration
$root_path = '../../';
$page_title = 'Check NFT Info - Vina Network';
$page_description = 'Check detailed information for a single Solana NFT using its Mint Address.';
$page_css = ['../../css/vina.css', '../tools1.css'];

log_message("nft-info: Including header.php", 'nft_info_log.txt', 'INFO');
include_once $root_path . 'include/header.php';
log_message("nft-info: Including navbar.php", 'nft_info_log.txt', 'INFO');
include_once $root_path . 'include/navbar.php';

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-info: tools-api.php not found at $api_helper_path", 'nft_info_log.txt', 'ERROR');
    exit("<div class='result-error'><p>Error: Missing tools-api.php</p></div>");
}
require_once $api_helper_path;
log_message("nft-info: tools-api.php loaded", 'nft_info_log.txt', 'INFO');

log_message("nft-info: Rendering form", 'nft_info_log.txt', 'INFO');
?>

<div class="t-6 nft-info-content">
    <!-- DEBUG: Form should appear below -->
    <div class="t-7">
        <h2>Check NFT Info</h2>
        <p>Enter the <strong>NFT Mint Address</strong> to view detailed information. For example, find this address on MagicEden under "Details" > "Mint Address".</p>
        <form id="nftInfoForm" method="POST" action="" style="display: block !important;">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="text" name="mintAddress" id="mintAddressInfo" placeholder="Enter NFT Mint Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <button type="submit" class="cta-button">Check NFT Info</button>
        </form>
        <div class="loader"></div>
    </div>
    <!-- DEBUG: Form end -->

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        log_message("nft-info: POST request received, mintAddress=" . ($_POST['mintAddress'] ?? 'N/A'), 'nft_info_log.txt', 'INFO');
        if (!isset($_POST['mintAddress'])) {
            log_message("nft-info: Missing mintAddress in POST data", 'nft_info_log.txt', 'ERROR');
            echo "<div class='result-error'><p>Error: Mint Address is required.</p></div>";
        } else {
            try {
                // Validate CSRF token
                log_message("nft-info: Validating CSRF token", 'nft_info_log.txt', 'INFO');
                if (!validate_csrf_token($_POST['csrf_token'])) {
                    throw new Exception("Invalid CSRF token.");
                }

                // Validate Mint Address
                $mintAddress = trim($_POST['mintAddress']);
                $mintAddress = preg_replace('/\s+/', '', $mintAddress);
                log_message("nft-info: Validating mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');
                if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                    throw new Exception("Invalid Mint Address format.");
                }

                // Check cache
                $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
                $cache_expiration = 3 * 3600; // Cache for 3 hours
                $cache_valid = isset($cache_data[$mintAddress]) && (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration);
                log_message("nft-info: Cache valid=$cache_valid for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');

                if (!$cache_valid) {
                    // Call getAsset API
                    log_message("nft-info: Calling getAsset API for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');
                    $params = ['id' => $mintAddress];
                    $response = call_api('POST', 'https://api.helius.xyz/v1/getAsset', $params, ['api-key' => HELIUS_API_KEY]);

                    log_message("nft-info: API response status=" . $response['statusCode'] . ", response=" . substr(json_encode($response['response'] ?? ''), 0, 500), 'nft_info_log.txt', 'INFO');
                    if ($response['statusCode'] !== 200) {
                        throw new Exception("API error: " . ($response['error'] ?? 'Unknown error') . " - Response: " . substr(json_encode($response['response'] ?? ''), 0, 500));
                    }
                    $asset = json_decode($response['response'], true)['result'] ?? [];
                    if (empty($asset)) {
                        throw new Exception("NFT not found for Mint Address: $mintAddress");
                    }

                    // Format data
                    $formatted_data = [
                        'mint_address' => $asset['id'] ?? 'N/A',
                        'name' => $asset['content']['metadata']['name'] ?? 'N/A',
                        'image' => $asset['content']['links']['image'] ?? '',
                        'attributes' => isset($asset['content']['metadata']['attributes']) ? json_encode($asset['content']['metadata']['attributes'], JSON_PRETTY_PRINT) : 'N/A',
                        'owner' => $asset['ownership']['owner'] ?? 'N/A',
                        'collection' => $asset['grouping'][0]['group_value'] ?? 'N/A',
                        'is_compressed' => $asset['compression']['compressed'] ?? false,
                        'is_burned' => $asset['ownership']['frozen'] ?? false,
                        'is_listed' => isset($asset['marketplace_listings']) && !empty($asset['marketplace_listings']) ? true : false,
                        'timestamp' => time()
                    ];
                    log_message("nft-info: Formatted data for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');

                    // Save to cache
                    $cache_data[$mintAddress] = [
                        'data' => $formatted_data,
                        'timestamp' => time()
                    ];
                    if (!file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT))) {
                        throw new Exception("Failed to write to cache file.");
                    }
                    log_message("nft-info: Cache updated for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');
                } else {
                    $formatted_data = $cache_data[$mintAddress]['data'];
                }

                // Display results
                log_message("nft-info: Displaying results for mintAddress=$mintAddress", 'nft_info_log.txt', 'INFO');
                if (empty($formatted_data)) {
                    log_message("nft-info: No data found for mintAddress=$mintAddress", 'nft_info_log.txt', 'ERROR');
                    echo "<div class='result-error'><p>No data found for this NFT.</p></div>";
                } else {
                    ?>
                    <div class="result-section">
                        <div class="nft-details">
                            <h3>NFT Details</h3>
                            <div class="nft-card">
                                <div class="nft-image">
                                    <?php if ($formatted_data['image']): ?>
                                        <img src="<?php echo htmlspecialchars($formatted_data['image']); ?>" alt="NFT Image" style="max-width: 100%;">
                                    <?php else: ?>
                                        <p>No image available</p>
                                    <?php endif; ?>
                                </div>
                                <div class="nft-info-table">
                                    <table>
                                        <tr><th>Mint Address</th><td><?php echo htmlspecialchars(substr($formatted_data['mint_address'], 0, 8)) . '...'; ?></td></tr>
                                        <tr><th>Name</th><td><?php echo htmlspecialchars($formatted_data['name']); ?></td></tr>
                                        <tr><th>Attributes</th><td><pre><?php echo htmlspecialchars($formatted_data['attributes']); ?></pre></td></tr>
                                        <tr><th>Owner</th><td><?php echo htmlspecialchars(substr($formatted_data['owner'], 0, 8)) . '...'; ?></td></tr>
                                        <tr><th>Collection</th><td><?php echo htmlspecialchars(substr($formatted_data['collection'], 0, 8)) . '...'; ?></td></tr>
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
                }
            } catch (Exception $e) {
                log_message("nft-info: Exception - " . $e->getMessage() . " at line " . $e->getLine(), 'nft_info_log.txt', 'ERROR');
                echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
            }
        }
    }
    log_message("nft-info: Script ended", 'nft_info_log.txt', 'INFO');
    ?>

    <div class="t-9">
        <h2>About Check NFT Info</h2>
        <p>The Check NFT Info tool allows you to view detailed information for a specific Solana NFT by entering its Mint Address.</p>
    </div>
</div>
