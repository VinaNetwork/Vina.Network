<?php
// ============================================================================
// File: tools/wallet-creators/wallet-creators.php
// Description: Check NFTs and Tokens created by a Solana wallet address.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

require_once dirname(__DIR__) . '/bootstrap.php';

$cache_dir = WALLET_CREATORS_PATH . 'cache/';
$cache_file = $cache_dir . 'wallet_creators_cache.json';

if (!ensure_directory_and_file($cache_dir, $cache_file, 'wallet_creators_log.txt')) {
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

require_once dirname(__DIR__) . '/tools-api.php';
?>

<link rel="stylesheet" href="/tools/wallet-creators/wallet-creators.css">
<div class="nft-creator">
<?php
$rate_limit_exceeded = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creatorAddress'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = "rate_limit_wallet_creators:$ip";
    $rate_limit_count = $_SESSION[$rate_limit_key]['count'] ?? 0;
    $rate_limit_time = $_SESSION[$rate_limit_key]['time'] ?? 0;

    if (time() - $rate_limit_time > 60) {
        $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
        log_message("wallet_creators: Reset rate limit for IP=$ip, count=1", 'wallet_creators_log.txt', 'INFO');
    } elseif ($rate_limit_count >= 5) {
        $rate_limit_exceeded = true;
        log_message("wallet_creators: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'wallet_creators_log.txt', 'ERROR');
        echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
    } else {
        $_SESSION[$rate_limit_key]['count']++;
        log_message("wallet_creators: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'wallet_creators_log.txt', 'INFO');
    }
}

if (!$rate_limit_exceeded): ?>
    <div class="tools-form">
        <h2>Check Wallet Creators</h2>
        <p>Enter the <strong>Solana Wallet Address</strong> to view all NFTs and Tokens created by this address.</p>
        <form id="walletCreatorForm" method="POST" action="" data-tool="wallet-creators">
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
        if (!isset($_POST['csrf_token']) || !function_exists('validate_csrf_token') || !validate_csrf_token($_POST['csrf_token'])) {
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
            log_message("wallet_creators: Loaded from cache for creator=$creatorAddress", 'wallet_creators_log.txt', 'INFO');
        } else {
            $params = [
                'creatorAddress' => $creatorAddress,
                'onlyVerified' => false,
                'page' => 1,
                'limit' => 1000,
                'sortBy' => ['sortBy' => 'created', 'sortDirection' => 'asc']
            ];
            $response = callAPI('getAssetsByCreator', $params, 'POST');
            @file_put_contents($cache_dir . 'api_response_debug.json', json_encode($response, JSON_PRETTY_PRINT));

            if (isset($response['error'])) {
                throw new Exception(is_array($response['error']) ? ($response['error']['message'] ?? 'API error') : $response['error']);
            }

            $items = $response['items'] ?? ($response['result']['items'] ?? []);
            if (empty($items)) {
                echo "<div class='result-info'><p>This wallet has not created any NFTs or Tokens yet.</p></div>";
                return;
            }

            $formatted_data = [];
            foreach ($items as $asset) {
                $is_collection = empty($asset['grouping']) || !isset($asset['grouping'][0]['group_value']);
                $collection_value = 'N/A';
                if (!$is_collection && isset($asset['grouping'][0]['group_value'])) {
                    $collection_value = $asset['grouping'][0]['group_value'];
                } elseif ($is_collection) {
                    $collection_value = 'Self (Collection)';
                }

                $is_token = isset($asset['interface']) && $asset['interface'] === 'FungibleToken';
                $category = $is_token ? 'Token' : 'NFT';

                $formatted_data[] = [
                    'category' => $category,
                    'asset_id' => $asset['id'] ?? 'N/A',
                    'name' => $asset['content']['metadata']['name'] ?? ($asset['name'] ?? 'Unnamed NFT'),
                    'image' => $asset['content']['links']['image'] ?? ($asset['image'] ?? ''),
                    'collection' => $collection_value,
                    'royalty' => isset($asset['royalty']['percent']) ? number_format($asset['royalty']['percent'] * 100, 2) . '%' : ($asset['royalty']['basis_points'] ?? 'N/A'),
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
            log_message("wallet_creators: Cache updated for creator=$creatorAddress", 'wallet_creators_log.txt', 'INFO');
        }

        ob_start();
        ?>
        <div class="tools-result nft-creator-result">
            <h2>NFTs and Tokens by Creator</h2>
            <div class="result-summary">
                <div class="nft-grid">
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
                                    <tr><th>Category</th>
                                        <td><?php echo htmlspecialchars($asset['category']); ?></td></tr>
                                    <tr><th>Asset ID</th>
                                        <td>
                                            <span><?php echo substr($asset['asset_id'], 0, 4) . '...' . substr($asset['asset_id'], -4); ?></span>
                                            <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($asset['asset_id']); ?>"></i>
                                        </td>
                                    </tr>
                                    <tr><th>Name</th>
                                        <td><?php echo htmlspecialchars($asset['name']); ?></td>
                                    </tr>
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
                                    <tr><th>Royalty</th>
                                        <td><?php echo htmlspecialchars($asset['royalty']); ?></td>
                                    </tr>
                                    <tr><th>Verified</th>
                                        <td><?php echo htmlspecialchars($asset['verified']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$creatorAddress]['timestamp']) . ' UTC+0'; ?>. Data will be updated every 3 hours.</p>
        </div>
        <?php
        echo ob_get_clean();

    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
    }
}
?>

<div class="tools-about">
    <h2>About Check Wallet Creators</h2>
    <p>The Check Wallet Creators tool allows you to view all NFTs and Tokens created by a specific Solana wallet address.</p>
</div>
</div>
