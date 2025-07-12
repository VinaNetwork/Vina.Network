<?php
// ============================================================================
// File: tools/nft-creator/nft-creator.php
// Description: Check NFTs and Collections created by a Solana wallet address.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

require_once dirname(__DIR__) . '/bootstrap.php';

$cache_dir = NFT_CREATOR_PATH . 'cache/';
$cache_file = $cache_dir . 'nft_creator_cache.json';

if (!ensure_directory_and_file($cache_dir, $cache_file, 'nft_creator_log.txt')) {
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

require_once dirname(__DIR__) . '/tools-api.php';
?>

<link rel="stylesheet" href="/tools/nft-creator/nft-creator.css">
<div class="nft-creator">
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
}

if (!$rate_limit_exceeded): ?>
    <div class="tools-form">
        <h2>Check NFT Creator</h2>
        <p>Enter the <strong>Solana Wallet Address</strong> to view all NFTs and Tokens created by this address.</p>
        <form id="nftCreatorForm" method="POST" action="" data-tool="nft-creator">
            <input type="hidden" name="csrf_token" value="<?php echo function_exists('generate_csrf_token') ? generate_csrf_token() : ''; ?>">
            <div class="input-wrapper">
                <input type="text" name="creatorAddress" id="creatorAddress" placeholder="Enter Solana Creator Address" required value="<?php echo htmlspecialchars($_POST['creatorAddress'] ?? ''); ?>">
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
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }

        $creatorAddress = preg_replace('/\s+/', '', trim($_POST['creatorAddress']));
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $creatorAddress)) {
            throw new Exception('Invalid Creator Address format');
        }

        $cache_data = @json_decode(@file_get_contents($cache_file), true) ?? [];
        $cache_expiration = 3 * 3600;
        $cache_valid = isset($cache_data[$creatorAddress]) && (time() - $cache_data[$creatorAddress]['timestamp'] < $cache_expiration);

        if ($cache_valid) {
            $formatted_data = $cache_data[$creatorAddress]['data'];
        } else {
            $params = [
                'creatorAddress' => $creatorAddress,
                'onlyVerified' => false,
                'page' => 1,
                'limit' => 1000,
                'sortBy' => ['sortBy' => 'created', 'sortDirection' => 'asc']
            ];
            $response = callAPI('getAssetsByCreator', $params, 'POST');
            if (isset($response['error'])) {
                throw new Exception(is_array($response['error']) ? ($response['error']['message'] ?? 'API error') : $response['error']);
            }

            $items = $response['items'] ?? ($response['result']['items'] ?? []);
            if (empty($items)) {
                echo "<div class='result-empty'><p>This wallet has not created any NFTs or Tokens yet.</p></div>";
                log_message("nft_creator: No NFTs or Tokens found for $creatorAddress", 'nft_creator_log.txt', 'INFO');
                return;
            }


            $formatted_data = [];
            foreach ($items as $asset) {
                $is_collection = empty($asset['grouping']) || !isset($asset['grouping'][0]['group_value']);
                $collection_value = $is_collection ? 'Self (Collection)' : ($asset['grouping'][0]['group_value'] ?? 'N/A');

                // Detect token: nếu không có collection, không có grouping, không verified, và creator là chính nó
                $category = 'NFT';
                $creators = $asset['creators'] ?? [];
                $main_creator = $creators[0]['address'] ?? '';
                $is_token_like = $is_collection && !$creators[0]['verified'] && $main_creator === $creatorAddress;
                if ($is_token_like) {
                    $category = 'Token';
                }

                $royalty_percent = isset($asset['royalty']['percent']) ? $asset['royalty']['percent'] : 0;
                $royalty_basis = isset($asset['royalty']['basis_points']) ? $asset['royalty']['basis_points'] : 0;

                $formatted_data[] = [
                    'category' => $category,
                    'asset_id' => $asset['id'] ?? 'N/A',
                    'name' => $asset['content']['metadata']['name'] ?? ($asset['name'] ?? 'Unnamed NFT'),
                    'image' => $asset['content']['links']['image'] ?? ($asset['image'] ?? ''),
                    'collection' => $collection_value,
                    'royalty' => $royalty_percent ? number_format(floatval($royalty_percent) * 100, 2) . '%' : ($royalty_basis ? $royalty_basis : '0.00%'),
                    'verified' => isset($asset['creators'][0]['verified']) && $asset['creators'][0]['verified'] ? 'Yes' : 'No'
                ];
            }

            $cache_data[$creatorAddress] = ['data' => $formatted_data, 'timestamp' => time()];
            $fp = @fopen($cache_file, 'c');
            if ($fp && flock($fp, LOCK_EX)) {
                @file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

        ob_start();
        ?>
        <div class="tools-result nft-creator-result">
            <h2>NFTs and Collections by Creator</h2>
            <div class="result-summary">
                <?php foreach ($formatted_data as $asset): ?>
                    <div class="result-card">
                        <div class="nft-image">
                            <?php if ($asset['image']): ?>
                                <img src="<?php echo htmlspecialchars($asset['image']); ?>" alt="NFT Image">
                            <?php else: ?>
                                <p>No image available</p>
                            <?php endif; ?>
                        </div>
                        <div class="nft-info-table">
                            <table>
                                <tr><th>Category</th><td><?php echo htmlspecialchars($asset['category']); ?></td></tr>
                                <tr><th>Asset ID</th>
                                    <td>
                                        <span><?php echo substr($asset['asset_id'], 0, 4) . '...' . substr($asset['asset_id'], -4); ?></span>
                                        <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($asset['asset_id']); ?>"></i>
                                    </td>
                                </tr>
                                <tr><th>Name</th><td><?php echo htmlspecialchars($asset['name']); ?></td></tr>
                                <tr><th>Collection</th>
                                    <td>
                                        <?php if ($asset['collection'] === 'Self (Collection)'): ?>
                                            <span>Self (Collection)</span>
                                        <?php elseif (preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $asset['collection'])): ?>
                                            <span><?php echo substr($asset['collection'], 0, 4) . '...' . substr($asset['collection'], -4); ?></span>
                                            <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($asset['collection']); ?>"></i>
                                        <?php else: ?>
                                            <span>N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr><th>Royalty</th><td><?php echo htmlspecialchars($asset['royalty']); ?></td></tr>
                                <tr><th>Verified</th><td><?php echo htmlspecialchars($asset['verified']); ?></td></tr>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$creatorAddress]['timestamp']); ?> UTC+0. Data will be updated every 3 hours.</p>
        </div>
        <?php
        echo ob_get_clean();

    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
    }
}
?>

<div class="tools-about">
    <h2>About Check NFT Creator</h2>
    <p>The Check NFT Creator tool allows you to view all NFTs and Tokens created by a specific Solana wallet address, including both collections and fungible tokens.</p>
</div>
</div>
