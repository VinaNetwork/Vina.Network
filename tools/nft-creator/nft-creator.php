<?php
// ============================================================================
// File: tools/nft-creator/nft-creator.php
// Description: Check all NFTs or Collections created by a Solana wallet address
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Load API helper
require_once dirname(__DIR__) . '/tools-api.php';

$cache_dir = __DIR__ . '/cache/';
$cache_file = $cache_dir . 'nft_creator_cache.json';
ensure_directory_and_file($cache_dir, $cache_file, 'nft_creator_log.txt');

?>

<link rel="stylesheet" href="/tools/nft-info/nft-info.css">
<div class="nft-info">

<?php
$rate_limit_exceeded = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creatorAddress'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "rate_limit_creator:$ip";
    $rate_count = $_SESSION[$key]['count'] ?? 0;
    $rate_time = $_SESSION[$key]['time'] ?? 0;

    if (time() - $rate_time > 60) {
        $_SESSION[$key] = ['count' => 1, 'time' => time()];
    } elseif ($rate_count >= 5) {
        $rate_limit_exceeded = true;
        echo "<div class='result-error'><p>Rate limit exceeded. Try again in a minute.</p></div>";
    } else {
        $_SESSION[$key]['count']++;
    }
}

if (!$rate_limit_exceeded): ?>
    <div class="tools-form">
        <h2>Check NFTs Created by Wallet</h2>
        <p>Enter a <strong>Solana wallet address</strong> to view NFTs or collections created by that wallet (uses Helius API).</p>
        <form method="POST" action="" data-tool="nft-creator">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="input-wrapper">
                <input type="text" name="creatorAddress" placeholder="Enter Creator Wallet Address" required value="<?php echo isset($_POST['creatorAddress']) ? htmlspecialchars($_POST['creatorAddress']) : ''; ?>">
                <span class="clear-input" title="Clear input">×</span>
            </div>
            <button type="submit" class="cta-button">Check</button>
        </form>
        <div class="loader"></div>
    </div>
<?php endif; ?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creatorAddress']) && !$rate_limit_exceeded) {
    try {
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }

        $creatorAddress = preg_replace('/\s+/', '', trim($_POST['creatorAddress']));
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $creatorAddress)) {
            throw new Exception('Invalid Solana wallet address');
        }

        $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
        $cache_expiration = 3 * 3600;
        $cache_valid = isset($cache_data[$creatorAddress]) && (time() - $cache_data[$creatorAddress]['timestamp'] < $cache_expiration);

        if (!$cache_valid) {
            $params = [
                "creatorAddress" => $creatorAddress,
                "onlyVerified" => true,
                "page" => 1,
                "limit" => 1000
            ];
            $response = callAPI('getAssetsByCreator', $params, 'POST');

            // Save raw response for debugging
            file_put_contents($cache_dir . 'api_response_debug.json', json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $items = $response['result']['items'] ?? [];
            if (empty($items)) {
                throw new Exception('No NFTs or Collections found for this creator');
            }

            $formatted = [];
            foreach ($items as $item) {
                $formatted[] = [
                    'id' => $item['id'] ?? 'N/A',
                    'name' => $item['content']['metadata']['name'] ?? 'Unnamed NFT',
                    'image' => $item['content']['links']['image'] ?? '',
                    'collection' => $item['grouping'][0]['group_value'] ?? 'N/A',
                    'royalty' => isset($item['royalty']['basis_points']) ? round($item['royalty']['basis_points'] / 100, 2) . '%' : 'N/A',
                    'verified' => !empty($item['creators'][0]['verified']) ? 'Yes' : 'No'
                ];
            }

            $cache_data[$creatorAddress] = [
                'data' => $formatted,
                'timestamp' => time()
            ];
            file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
        } else {
            $formatted = $cache_data[$creatorAddress]['data'];
        }

        if (empty($formatted)) {
            echo "<div class='result-error'><p>No data found for this wallet.</p></div>";
        } else {
            echo "<div class='tools-result'>";
            echo "<h2>Assets Created by Wallet</h2>";
            foreach ($formatted as $item) {
                echo "<div class='result-card'>";
                echo "<div class='nft-image'>";
                if (!empty($item['image'])) {
                    echo "<img src='" . htmlspecialchars($item['image']) . "' alt='NFT Image'>";
                } else {
                    echo "<p>No image available</p>";
                }
                echo "</div>";
                echo "<div class='nft-info-table'><table>";
                echo "<tr><th>Asset ID:</th><td>" . substr($item['id'], 0, 4) . "..." . substr($item['id'], -4) . "</td></tr>";
                echo "<tr><th>Name:</th><td>" . htmlspecialchars($item['name']) . "</td></tr>";
                echo "<tr><th>Collection:</th><td>" . htmlspecialchars($item['collection']) . "</td></tr>";
                echo "<tr><th>Royalty:</th><td>" . $item['royalty'] . "</td></tr>";
                echo "<tr><th>Verified:</th><td>" . $item['verified'] . "</td></tr>";
                echo "</table></div></div>";
            }

            echo "<p class='cache-timestamp'>Last updated: " . date('d M Y, H:i', $cache_data[$creatorAddress]['timestamp']) . " UTC+0. Data refreshes every 3 hours.</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
    }
}
?>

    <div class="tools-about">
        <h2>About NFT Creator Lookup</h2>
        <p>This tool shows all NFTs and collections created by a Solana wallet. Useful for creators, collectors, or on-chain analysts.</p>
    </div>
</div>
