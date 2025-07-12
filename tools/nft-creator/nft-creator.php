<?php
// ============================================================================
// File: tools/nft-creator/nft-creator.php
// Description: Check NFTs and Collections created by a Solana wallet address.
// Created by: Vina Network
// ============================================================================

// Define constants
if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Cache directory and file
$cache_dir = NFT_CREATOR_PATH . 'cache/';
$cache_file = $cache_dir . 'nft_creator_cache.json';

// Check and create cache directory and file
if (!ensure_directory_and_file($cache_dir, $cache_file, 'nft_creator_log.txt')) {
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
?><div class="nft-creator">
<?php
$rate_limit_exceeded = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creatorAddress'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = "rate_limit_nft_creator:$ip";
    $rate_limit_count = $_SESSION[$rate_limit_key]['count'] ?? 0;
    $rate_limit_time = $_SESSION[$rate_limit_key]['time'] ?? 0;
    if (time() - $rate_limit_time > 60) {
        $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
    } elseif ($rate_limit_count >= 5) {
        $rate_limit_exceeded = true;
        echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
    } else {
        $_SESSION[$rate_limit_key]['count']++;
    }
}if (!$rate_limit_exceeded) { ?> <div class="tools-form"> <h2>Check NFT Creator</h2> <form id="nftCreatorForm" method="POST" action="" data-tool="nft-creator"> <input type="hidden" name="csrf_token" value="<?php echo function_exists('generate_csrf_token') ? generate_csrf_token() : ''; ?>"> <div class="input-wrapper"> <input type="text" name="creatorAddress" id="creatorAddress" placeholder="Enter Solana Creator Address" required value="<?php echo isset($_POST['creatorAddress']) ? htmlspecialchars($_POST['creatorAddress']) : ''; ?>"> <span class="clear-input" title="Clear input">×</span> </div> <button type="submit" class="cta-button">Check</button> </form> <div class="loader"></div> </div>

<?php }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creatorAddress']) && !$rate_limit_exceeded) {
    try {
        if (isset($_POST['csrf_token']) && function_exists('validate_csrf_token') && !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }

        $creatorAddress = trim($_POST['creatorAddress']);
        $creatorAddress = preg_replace('/\s+/', '', $creatorAddress);
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $creatorAddress)) {
            throw new Exception('Invalid Creator Address format');
        }

        $cache_data = @json_decode(@file_get_contents($cache_file), true) ?? [];
        $cache_key = $creatorAddress;
        unset($cache_data[$cache_key]);

        $params = [
            'creatorAddress' => $creatorAddress,
            'onlyVerified' => false,
            'page' => 1,
            'limit' => 1000,
            'sortBy' => ['sortBy' => 'created', 'sortDirection' => 'asc']
        ];
        $response = callAPI('getAssetsByCreator', $params, 'POST');

        $items = $response['items'] ?? ($response['result']['items'] ?? []);
        if (empty($items)) throw new Exception('No NFTs or Collections found for this creator');

        $formatted_data = [];
        foreach ($items as $asset) {
            $interface = $asset['interface'] ?? '';
            $is_collection = ($interface === 'MplCoreCollection');

            $image = $asset['content']['links']['image'] ?? ($asset['content']['files'][0]['uri'] ?? '');
            $name = $asset['content']['metadata']['name'] ?? ($asset['name'] ?? ($is_collection ? 'Unnamed Collection' : 'Unnamed NFT'));

            $collection = 'N/A';
            if (!$is_collection && !empty($asset['grouping'][0]['group_value'])) {
                $collection = $asset['grouping'][0]['group_value'];
            } elseif ($is_collection) {
                $collection = 'Collection';
            }

            $royalty = 'N/A';
            if (isset($asset['royalty']['percent'])) {
                $royalty = number_format($asset['royalty']['percent'] * 100, 2) . '%';
            }

            $verified = (isset($asset['creators'][0]['verified']) && $asset['creators'][0]['verified']) ? 'Yes' : 'No';

            $formatted_data[] = [
                'asset_id'   => $asset['id'] ?? 'N/A',
                'name'       => $name,
                'image'      => $image,
                'collection' => $collection,
                'royalty'    => $royalty,
                'verified'   => $verified
            ];
        }

        $cache_data[$cache_key] = [
            'data' => $formatted_data,
            'timestamp' => time()
        ];
        file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));

        echo '<div class="tools-result nft-creator-result">';
        echo '<h2>NFTs and Collections by Creator</h2><div class="result-summary"><div class="nft-grid">';
        foreach ($formatted_data as $asset) {
            echo '<div class="result-card"><div class="nft-image">';
            echo $asset['image'] ? '<img src="' . htmlspecialchars($asset['image']) . '" alt="NFT Image">' : '<p>No image available</p>';
            echo '</div><div class="nft-info-table"><table>';
            echo '<tr><th>Asset ID</th><td><span>' . substr(htmlspecialchars($asset['asset_id']), 0, 4) . '...' . substr(htmlspecialchars($asset['asset_id']), -4) . '</span></td></tr>';
            echo '<tr><th>Name</th><td>' . htmlspecialchars($asset['name']) . '</td></tr>';
            echo '<tr><th>Collection</th><td>' . htmlspecialchars($asset['collection']) . '</td></tr>';
            echo '<tr><th>Royalty</th><td>' . htmlspecialchars($asset['royalty']) . '</td></tr>';
            echo '<tr><th>Verified</th><td>' . htmlspecialchars($asset['verified']) . '</td></tr>';
            echo '</table></div></div>';
        }
        echo '</div></div><p class="cache-timestamp">Last updated: ' . date('d M Y, H:i', time()) . ' UTC+0</p></div>';
    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
    }
}
?><div class="tools-about">
    <h2>About Check NFT Creator</h2>
    <p>The Check NFT Creator tool allows you to view all NFTs and Collections created by a specific Solana wallet address.</p>
</div>
</div>
