<?php
// =============================================================================
// File: tools/nft-creator/nft-creator.php
// Description: Check NFTs created by a given creator address on Solana (Helius API)
// Enhanced with rate limiting, CSRF protection, and caching (3h)
// Created by: Vina Network
// =============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

$cache_file = __DIR__ . '/cache/nft_creator_cache.json';
if (!file_exists(dirname($cache_file))) mkdir(dirname($cache_file), 0775, true);
if (!file_exists($cache_file)) file_put_contents($cache_file, '{}');

require_once dirname(__DIR__) . '/tools-api.php';

?><link rel="stylesheet" href="/tools/nft-creator/nft-creator.css">
<div class="nft-creator">
    <?php
    $rate_limit_exceeded = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creatorAddress'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_key = "rate_limit_creator:$ip";
        $count = $_SESSION[$rate_key]['count'] ?? 0;
        $time = $_SESSION[$rate_key]['time'] ?? 0;
        if (time() - $time > 60) {
            $_SESSION[$rate_key] = ['count' => 1, 'time' => time()];
        } elseif ($count >= 5) {
            echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
            $rate_limit_exceeded = true;
        } else {
            $_SESSION[$rate_key]['count']++;
        }
    }if (!$rate_limit_exceeded): ?>
    <div class="tools-form">
        <h2>Check NFTs by Creator</h2>
        <form method="POST" action="" data-tool="nft-creator">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="input-wrapper">
                <input type="text" name="creatorAddress" placeholder="Enter Creator Address" required value="<?php echo isset($_POST['creatorAddress']) ? htmlspecialchars($_POST['creatorAddress']) : ''; ?>">
                <span class="clear-input">×</span>
            </div>
            <button type="submit" class="cta-button">Check</button>
            <div class="loader"></div>
        </form>
    </div>
<?php endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creatorAddress']) && !$rate_limit_exceeded) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }

        $creator_address = trim($_POST['creatorAddress']);
        $creator_address = preg_replace('/\s+/', '', $creator_address);
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $creator_address)) {
            throw new Exception('Invalid address format');
        }

        $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
        $cache_expired = 3 * 3600;
        $results = [];

        if (isset($cache_data[$creator_address]) && (time() - $cache_data[$creator_address]['timestamp'] < $cache_expired)) {
            $results = $cache_data[$creator_address]['data'];
        } else {
            $params = ["creatorAddress" => $creator_address];
            $api_response = callAPI("searchAssets", $params);

            if (isset($api_response['error'])) {
                throw new Exception('API error: ' . json_encode($api_response['error']));
            }

            $assets = $api_response['result']['items'] ?? [];
            foreach ($assets as $item) {
                $image = $item['content']['links']['image'] ?? '';
                $name = $item['content']['metadata']['name'] ?? 'Unnamed NFT';
                $mint = $item['id'] ?? 'N/A';
                $collection = $item['grouping'][0]['group_value'] ?? 'N/A';
                $royalty = isset($item['royalty']['basis_points']) ? ($item['royalty']['basis_points'] / 100) . '%' : 'N/A';
                $verified = (isset($item['creators'][0]['verified']) && $item['creators'][0]['verified']) ? 'Yes' : 'No';

                $results[] = compact('image', 'name', 'mint', 'collection', 'royalty', 'verified');
            }

            $cache_data[$creator_address] = [
                'timestamp' => time(),
                'data' => $results
            ];
            file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
        }

        echo '<div class="tools-result nft-creator-result">';
        echo '<h2>NFTs Created</h2>';
        echo '<div class="result-summary">';

        if (empty($results)) {
            echo '<p>No NFTs found for this creator.</p>';
        } else {
            foreach ($results as $r) {
                echo '<div class="result-card">';
                echo '<div class="nft-image">';
                echo $r['image'] ? '<img src="' . htmlspecialchars($r['image']) . '" alt="NFT Image">' : '<p>No image available</p>';
                echo '</div>';
                echo '<div class="nft-info-table">';
                echo '<table>';
                echo '<tr><th>Asset ID:</th><td>' . substr($r['mint'], 0, 4) . '...' . substr($r['mint'], -4) . '</td></tr>';
                echo '<tr><th>Name:</th><td>' . htmlspecialchars($r['name']) . '</td></tr>';
                echo '<tr><th>Collection:</th><td>' . (preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $r['collection']) ? substr($r['collection'], 0, 4) . '...' . substr($r['collection'], -4) : 'N/A') . '</td></tr>';
                echo '<tr><th>Royalty:</th><td>' . $r['royalty'] . '</td></tr>';
                echo '<tr><th>Verified:</th><td>' . $r['verified'] . '</td></tr>';
                echo '</table>';
                echo '</div></div>';
            }
        }
        echo '</div>';
        if (isset($cache_data[$creator_address]['timestamp'])) {
            echo '<p class="cache-timestamp">Last updated: ' . date('d M Y, H:i', $cache_data[$creator_address]['timestamp']) . ' UTC+0. Data will be updated every 3 hours.</p>';
        }
        echo '</div>';
    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error: " . $e->getMessage() . "</p></div>";
    }
}
?>

<div class="tools-about">
    <h2>About Check NFTs by Creator</h2>
    <p>This tool allows you to explore all NFTs created by a specific creator address on the Solana blockchain.</p>
</div>
</div>
