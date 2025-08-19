<?php
// ============================================================================
// File: tools/nft-info/nft-info.php
// Description: Check detailed information for a single Solana NFT using its Mint Address.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'tools/bootstrap.php';

// Cache
$cache_dir = TOOLS_PATH . 'cache/';
$cache_file = $cache_dir . 'nft_info_cache.json';

if (!ensure_directory_and_file($cache_dir, $cache_file)) {
    log_message("nft_info: Cache setup failed for $cache_dir or $cache_file", 'nft-info.log', 'tools', 'ERROR');
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft_info: tools-api.php not found at $api_helper_path", 'nft-info.log', 'tools', 'ERROR');
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
?>

<link rel="stylesheet" href="/tools/nft-info/nft-info.css">
<div class="nft-info">
<?php
$rate_limit_exceeded = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = "rate_limit_nft_info:$ip";
    $rate_limit_count = $_SESSION[$rate_limit_key]['count'] ?? 0;
    $rate_limit_time = $_SESSION[$rate_limit_key]['time'] ?? 0;

    log_message("nft_info: POST request received, mintAddress=" . ($_POST['mintAddress'] ?? 'N/A'), 'nft-info.log', 'tools', 'INFO');

    if (time() - $rate_limit_time > 60) {
        $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
    } elseif ($rate_limit_count >= 5) {
        $rate_limit_exceeded = true;
        echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
    } else {
        $_SESSION[$rate_limit_key]['count']++;
    }
}

if (!$rate_limit_exceeded): ?>
    <div class="tools-form">
        <h2>Check NFT Info</h2>
        <p>Enter the <strong>NFT Mint Address</strong> or <strong>NFT Collection Address</strong> to view detailed information.</p>
        <form id="nftInfoForm" method="POST" action="" data-tool="nft-info">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="input-wrapper">
                <input type="text" name="mintAddress" id="mintAddressInfo" placeholder="Enter NFT Mint or Collection Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
                <span class="clear-input" title="Clear input">×</span>
            </div>
            <button type="submit" class="cta-button">Check</button>
        </form>
        <div class="loader"></div>
    </div>
<?php endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress']) && !$rate_limit_exceeded) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }

        $mintAddress = trim($_POST['mintAddress']);
        $mintAddress = preg_replace('/\s+/', '', $mintAddress);

        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
            throw new Exception('Invalid Mint or Collection Address format');
        }

        $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
        $cache_expiration = 3 * 3600;
        $cache_valid = isset($cache_data[$mintAddress]) && (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration);

        if (!$cache_valid) {
            $params = ['id' => $mintAddress];
            $asset = callAPI('getAsset', $params, 'POST');
            if (isset($asset['error'])) throw new Exception($asset['error']);

            if (empty($asset['result']['id'])) throw new Exception('Asset not found');

            $asset = $asset['result'];
            $is_collection = empty($asset['grouping']) || (isset($asset['interface']) && in_array($asset['interface'], ['V1_NFT', 'ProgrammableNFT']) && empty($asset['grouping']));

            $formatted_data = [
                'mint_address' => $asset['id'] ?? $mintAddress,
                'name' => $asset['content']['metadata']['name'] ?? 'N/A',
                'image' => $asset['content']['links']['image'] ?? '',
                'owner' => $asset['ownership']['owner'] ?? 'N/A',
                'collection' => $is_collection ? $mintAddress : ($asset['grouping'][0]['group_value'] ?? 'N/A'),
                'is_compressed' => $asset['compression']['compressed'] ?? false,
                'is_burned' => $asset['ownership']['frozen'] ?? false,
                'is_listed' => !empty($asset['marketplace_listings']),
                'attributes' => !empty($asset['content']['metadata']['attributes']) ? json_encode($asset['content']['metadata']['attributes'], JSON_PRETTY_PRINT) : 'N/A',
                'timestamp' => time()
            ];

            $cache_data[$mintAddress] = ['data' => $formatted_data, 'timestamp' => time()];
            $fp = fopen($cache_file, 'c');
            if (flock($fp, LOCK_EX)) {
                file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
                flock($fp, LOCK_UN);
            }
            fclose($fp);

            log_message("nft_info: Successfully processed mintAddress=$mintAddress", 'nft-info.log', 'tools', 'INFO');
        } else {
            $formatted_data = $cache_data[$mintAddress]['data'];
            log_message("nft_info: Used cached data for mintAddress=$mintAddress", 'nft-info.log', 'tools', 'INFO');
        }
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
                                    <a href="https://solscan.io/address/<?php echo htmlspecialchars($formatted_data['mint_address']); ?>" target="_blank">
                                        <?php echo substr($formatted_data['mint_address'], 0, 4) . '...' . substr($formatted_data['mint_address'], -4); ?>
                                    </a>
                                    <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($formatted_data['mint_address']); ?>"></i>
                                </td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td><?php echo htmlspecialchars($formatted_data['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Owner</th>
                                <td>
                                    <?php if (preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $formatted_data['owner'])): ?>
                                        <a href="https://solscan.io/address/<?php echo htmlspecialchars($formatted_data['owner']); ?>" target="_blank">
                                            <?php echo substr($formatted_data['owner'], 0, 4) . '...' . substr($formatted_data['owner'], -4); ?>
                                        </a>
                                        <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($formatted_data['owner']); ?>"></i>
                                    <?php else: ?>
                                        <span>N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Collection</th>
                                <td>
                                    <?php if (preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $formatted_data['collection'])): ?>
                                        <a href="https://solscan.io/address/<?php echo htmlspecialchars($formatted_data['collection']); ?>" target="_blank">
                                            <?php echo substr($formatted_data['collection'], 0, 4) . '...' . substr($formatted_data['collection'], -4); ?>
                                        </a>
                                        <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($formatted_data['collection']); ?>"></i>
                                    <?php else: ?>
                                        <span>N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr><th>Compressed</th><td><?php echo $formatted_data['is_compressed'] ? 'Yes' : 'No'; ?></td></tr>
                            <tr><th>Burned</th><td><?php echo $formatted_data['is_burned'] ? 'Yes' : 'No'; ?></td></tr>
                            <tr><th>Listed</th><td><?php echo $formatted_data['is_listed'] ? 'Yes' : 'No'; ?></td></tr>
                            <tr><th>Attributes</th><td><pre><?php echo htmlspecialchars($formatted_data['attributes']); ?></pre></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <?php if ($cache_valid): ?>
                <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$mintAddress]['timestamp']); ?> UTC+0</p>
            <?php endif; ?>
        </div>
<?php
    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
        log_message("nft_info: Exception - " . $e->getMessage(), 'nft-info.log', 'tools', 'ERROR');
    }
}
?>
    <div class="tools-about">
        <h2>About Check NFT Info</h2>
        <p>This tool allows you to look up metadata and status for NFTs and collections on Solana.</p>
    </div>
</div>
