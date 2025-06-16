<?php
/*
 * NFT Holders Checker - Vina Network
 *
 * This script allows users to check the total number of holders and NFTs for a given Solana on-chain collection address.
 * It queries Helius API, caches data with a 3-hour expiration, and displays summary information.
 * Update 3: Removed holders list and pagination, only shows summary card and export.
 * Update 5: Display last cached timestamp to inform users when data was last updated.
 * Fix: Use file-based cache to persist data across sessions, addressing session deletion on browser close.
 */

// Disable display of errors in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Define constants to mark script entry
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Load bootstrap dependencies
$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-holders: bootstrap.php not found at $bootstrap_path", 'nft_holders_log.txt', 'ERROR');
    die('Error: bootstrap.php not found');
}
require_once $bootstrap_path;

// Start session and configure error logging
session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

// Define cache directory and file
$cache_dir = __DIR__ . '/cache';
$cache_file = $cache_dir . '/nft_holders_cache.json';
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
    log_message("nft-holders: Created cache directory at $cache_dir", 'nft_holders_log.txt');
}
if (!file_exists($cache_file)) {
    file_put_contents($cache_file, json_encode([]));
    chmod($cache_file, 0644);
    log_message("nft-holders: Created cache file at $cache_file", 'nft_holders_log.txt');
}

// Set up page variables and include layout headers
$root_path = '../../';
$page_title = 'Check NFT Holders - Vina Network';
$page_description = 'Check NFT holders for a Solana collection address.';
$page_css = ['../../css/vina.css', '../tools.css'];
include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';

// Include tools API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-holders: tools-api.php not found at $api_helper_path", 'nft_holders_log.txt', 'ERROR');
    die('Internal Server Error: Missing tools-api.php');
}
log_message("nft-holders: Including tools-api.php from $api_helper_path", 'nft_holders_log.txt');
include $api_helper_path;

log_message("nft-holders: Loaded at " . date('Y-m-d H:i:s'), 'nft_holders_log.txt');
?>
<!-- Render input form for NFT Collection address -->
<div class="t-6 nft-holders-content">
    <div class="t-7">
        <h2>Check NFT Holders</h2>
        <p>Enter the <strong>NFT Collection</strong> address to see the total number of holders and NFTs. E.g: Find this address on MagicEden under "Details" > "On-chain Collection".</p>
        <form id="nftHoldersForm" method="POST" action="">
            <input type="text" name="mintAddress" id="mintAddressHolders" placeholder="Enter NFT Collection Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <button type="submit" class="cta-button">Check Holders</button>
        </form>
        <div class="loader"></div>
    </div>
    <?php
    // Handle form submission and NFT data fetching
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            $mintAddress = trim($_POST['mintAddress']);
            log_message("nft-holders: Form submitted with mintAddress=$mintAddress", 'nft_holders_log.txt');
            $limit = 1000;
            $max_pages = 100; // Limit max API page iterations
            $cache_expiration = 3 * 3600; // 3 hours in seconds

            // Validate address format (base58, 32–44 characters)
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                throw new Exception("Invalid collection address. Please enter a valid Solana collection address (32-44 characters, base58).");
            }

            // Load cache from file
            $cache_data = json_decode(file_get_contents($cache_file), true);
            if (!is_array($cache_data)) {
                $cache_data = [];
                log_message("nft-holders: Failed to parse cache file, initializing empty cache", 'nft_holders_log.txt', 'WARNING');
            }

            // Reset cache if new address submitted
            if (!isset($_SESSION['last_mintAddress']) || $_SESSION['last_mintAddress'] !== $mintAddress) {
                if (isset($cache_data[$mintAddress])) {
                    unset($cache_data[$mintAddress]);
                    file_put_contents($cache_file, json_encode($cache_data));
                    log_message("nft-holders: Cleared file cache for new mintAddress=$mintAddress", 'nft_holders_log.txt');
                }
                $_SESSION['last_mintAddress'] = $mintAddress;
            }

            // Check if cache exists and is not expired
            $cache_valid = isset($cache_data[$mintAddress]) && 
                           isset($cache_data[$mintAddress]['timestamp']) && 
                           isset($cache_data[$mintAddress]['total_items']) && 
                           isset($cache_data[$mintAddress]['items']) && 
                           (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration);

            if (!$cache_valid) {
                // Cache expired or not set, fetch from API
                if (isset($cache_data[$mintAddress]['timestamp']) && !$cache_valid) {
                    log_message("nft-holders: Cache expired for mintAddress=$mintAddress, fetching new data", 'nft_holders_log.txt');
                } elseif (!isset($cache_data[$mintAddress])) {
                    log_message("nft-holders: No cache found for mintAddress=$mintAddress, fetching new data", 'nft_holders_log.txt');
                }

                // Increase memory limit for large collections
                ini_set('memory_limit', '512M');
                $total_items = 0;
                $api_page = 1;
                $has_more = true;
                $items = [];
                while ($has_more && $api_page <= $max_pages) {
                    $total_params = [
                        'groupKey' => 'collection',
                        'groupValue' => $mintAddress,
                        'page' => $api_page,
                        'limit' => $limit
                    ];
                    log_message("nft-holders: Calling API for total items, page=$api_page, params=" . json_encode($total_params), 'nft_holders_log.txt');
                    $total_data = callAPI('getAssetsByGroup', $total_params, 'POST');
                    log_message("nft-holders: Total API response (page $api_page): URL=https://mainnet.helius-rpc.com/?api-key=****, Response=" . json_encode($total_data, JSON_PRETTY_PRINT), 'nft_holders_log.txt');

                    if (isset($total_data['error'])) {
                        $errorMessage = is_array($total_data['error']) && isset($total_data['error']['message']) ? $total_data['error']['message'] : json_encode($total_data['error']);
                        throw new Exception("API error: " . $errorMessage);
                    }

                    // Validate API response
                    if (!isset($total_data['result']['items'])) {
                        log_message("nft-holders: Invalid API response, no items found for page=$api_page, mintAddress=$mintAddress", 'nft_holders_log.txt', 'ERROR');
                        throw new Exception("Invalid API response: No items found.");
                    }

                    // Merge items and count
                    $page_items = $total_data['result']['items'];
                    $item_count = count($page_items);
                    $items = array_merge($items, array_map(function($item) {
                        if (!isset($item['ownership']['owner'])) {
                            log_message("nft-holders: Invalid item structure, missing owner: " . json_encode($item), 'nft_holders_log.txt', 'WARNING');
                            return null;
                        }
                        return [
                            'owner' => $item['ownership']['owner'],
                            'amount' => 1
                        ];
                    }, $page_items));
                    // Filter out null items
                    $items = array_filter($items);
                    $total_items += $item_count;

                    log_message("nft-holders: Page $api_page added $item_count items, total_items=$total_items, valid_items=" . count($items), 'nft_holders_log.txt');

                    $has_more = $item_count >= $limit;
                    $api_page++;
                    usleep(2000000); // 2-second delay to avoid rate limit
                }

                // Warning when max pages reached
                if ($api_page > $max_pages && $has_more) {
                    $max_items_possible = $max_pages * $limit;
                    log_message("nft-holders: Reached max pages ($max_pages) for $mintAddress, data may be incomplete. Total items fetched: $total_items", 'nft_holders_log.txt', 'WARNING');
                    echo "<div class='result-error'>";
                    echo "<p><strong>Warning:</strong> The collection is too large, and only the first $max_items_possible NFTs were retrieved due to API limitations.</p>";
                    echo "<p>This data may be incomplete. For full details, consider checking directly on the Solana blockchain or <a href='mailto:support@vina.network'>contact our support team</a>.</p>";
                    echo "</div>";
                }

                // Deduplicate wallet holders
                $unique_wallets = [];
                foreach ($items as $item) {
                    if (!isset($item['owner'])) {
                        log_message("nft-holders: Skipping invalid item during deduplication: " . json_encode($item), 'nft_holders_log.txt', 'WARNING');
                        continue;
                    }
                    $owner = $item['owner'];
                    if (!isset($unique_wallets[$owner])) {
                        $unique_wallets[$owner] = $item;
                    } else {
                        $unique_wallets[$owner]['amount'] += 1;
                    }
                }
                $wallets = array_values($unique_wallets);
                $total_wallets = count($wallets);

                // Validate data before caching
                if ($total_items > 0 && $total_wallets === 0) {
                    log_message("nft-holders: Inconsistent data: total_items=$total_items but total_wallets=0 for $mintAddress", 'nft_holders_log.txt', 'ERROR');
                    throw new Exception("Failed to retrieve wallet data. Please try again or contact support.");
                }

                // Store in file cache with timestamp
                $cache_data[$mintAddress] = [
                    'total_items' => $total_items,
                    'total_wallets' => $total_wallets,
                    'items' => $items,
                    'wallets' => $wallets,
                    'timestamp' => time()
                ];
                file_put_contents($cache_file, json_encode($cache_data));
                log_message("nft-holders: Cached total_items=$total_items, total_wallets=$total_wallets for $mintAddress with timestamp=" . date('Y-m-d H:i:s'), 'nft_holders_log.txt');

                // Sync session for compatibility
                $_SESSION['total_items'][$mintAddress] = $total_items;
                $_SESSION['total_wallets'][$mintAddress] = $total_wallets;
                $_SESSION['items'][$mintAddress] = $items;
                $_SESSION['wallets'][$mintAddress] = $wallets;
                $_SESSION['cache_timestamp'][$mintAddress] = time();
            } else {
                $total_items = $cache_data[$mintAddress]['total_items'];
                $total_wallets = $cache_data[$mintAddress]['total_wallets'];
                $items = $cache_data[$mintAddress]['items'];
                $wallets = $cache_data[$mintAddress]['wallets'];
                $cache_timestamp = $cache_data[$mintAddress]['timestamp'];
                log_message("nft-holders: Retrieved total_items=$total_items, total_wallets=$total_wallets from file cache for $mintAddress, cached at " . date('Y-m-d H:i:s', $cache_timestamp), 'nft_holders_log.txt');

                // Sync session for compatibility
                $_SESSION['total_items'][$mintAddress] = $total_items;
                $_SESSION['total_wallets'][$mintAddress] = $total_wallets;
                $_SESSION['items'][$mintAddress] = $items;
                $_SESSION['wallets'][$mintAddress] = $wallets;
                $_SESSION['cache_timestamp'][$mintAddress] = $cache_timestamp;
            }

            log_message("nft-holders: Final total_items=$total_items, total_wallets=$total_wallets for $mintAddress", 'nft_holders_log.txt');

            // Handle edge case: total = 0 or looks incomplete
            if ($total_items === 0) {
                throw new Exception("No items found or invalid collection address.");
            } elseif ($limit > 0 && $total_items % $limit === 0 && $total_items >= $limit) {
                log_message("nft-holders: Suspicious total_items ($total_items) is a multiple of limit for $mintAddress", 'nft_holders_log.txt', 'WARNING');
                echo "<div class='result-error'><p>Warning: Total items ($total_items) is a multiple of API limit ($limit). Actual number may be higher. For full details, check directly on the Solana blockchain or <a href='mailto:support@vina.network'>contact support</a>.</p></div>";
            }
            ?>
            <!-- Display summary card -->
            <div class="result-section">
                <?php if ($total_wallets === 0): ?>
                    <p class="result-error">No holders found for this collection.</p>
                <?php else: ?>
                    <div class="holders-summary">
                        <div class="summary-card">
                            <div class="summary-item">
                                <i class="fas fa-wallet"></i>
                                <p>Total wallets</p>
                                <h3><?php echo number_format($total_wallets); ?></h3>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-image"></i>
                                <p>Total NFTs</p>
                                <h3><?php echo number_format($total_items); ?></h3>
                            </div>
                        </div>
                        <?php if ($cache_valid): ?>
                            <p class="cache-timestamp">Data last updated: <?php echo date('d M Y, H:i', $cache_data[$mintAddress]['timestamp']) . ' UTC+7'; ?></p>
                        <?php endif; ?>
                    </div>
                    <!-- Export controls -->
                    <div class="export-section">
                        <form method="POST" action="/tools/nft-holders/nft-holders-export.php" class="export-form">
                            <input type="hidden" name="mintAddress" value="<?php echo htmlspecialchars($mintAddress); ?>">
                            <div class="export-controls">
                                <select name="export_format" class="export-format">
                                    <option value="csv">CSV</option>
                                    <option value="json">JSON</option>
                                </select>
                                <button type="submit" name="export_type" value="all" class="cta-button export-btn" id="export-all-btn">Export All Wallets</button>
                            </div>
                        </form>
                        <div class="progress-container" style="display: none;">
                            <p>Exporting... Please wait.</p>
                            <div class="progress-bar"><div class="progress-bar-fill" style="width: 0%;"></div></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            log_message("nft-holders: Exception - $error_msg", 'nft_holders_log.txt', 'ERROR');
            echo "<div class='result-error'><p>$error_msg. Please try again.</p></div>";
        }
    }
    ?>
    <!-- Informational block -->
    <div class="t-9">
        <h2>About NFT Holders Checker</h2>
        <p>
            The NFT Holders Checker allows you to view the total number of holders and NFTs for a specific Solana NFT collection by entering its On-chain Collection address. 
            This tool is useful for NFT creators, collectors, or investors who want to analyze the distribution and ownership of a collection on the Solana blockchain.
        </p>
    </div>
</div>

<?php
// Output and log footer
ob_start();
include $root_path . 'include/footer.php';
$footer_output = ob_get_clean();
log_message("nft-holders: Footer output length: " . strlen($footer_output), 'nft_holders_log.txt');
echo $footer_output;
?>
