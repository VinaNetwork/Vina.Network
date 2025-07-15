<?php
// ============================================================================
// File: tools/nft-holders/nft-holders.php
// Description: Perform functions to check the number of wallets holding NFTs.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-holders: bootstrap.php not found at $bootstrap_path", 'nft_holders_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Cache setup
$cache_dir = NFT_HOLDERS_PATH . 'cache/';
$cache_file = $cache_dir . 'nft_holders_cache.json';
if (!ensure_directory_and_file($cache_dir, $cache_file, 'nft_holders_log.txt')) {
    log_message("nft-holders: Cache setup failed", 'nft_holders_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-holders: tools-api.php not found", 'nft_holders_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;

// Load NFT holders helper
require_once __DIR__ . '/nft-holders-helper.php';
?>

<link rel="stylesheet" href="/tools/nft-holders/nft-holders.css">
<div class="nft-holders">
<?php
$rate_limit_exceeded = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "rate_limit_nft_holders:$ip";
    $count = $_SESSION[$key]['count'] ?? 0;
    $time = $_SESSION[$key]['time'] ?? 0;

    if (time() - $time > 60) {
        $_SESSION[$key] = ['count' => 1, 'time' => time()];
    } elseif ($count >= 5) {
        $rate_limit_exceeded = true;
        echo "<div class='result-error'><p>Rate limit exceeded. Try again in 1 minute.</p></div>";
    } else {
        $_SESSION[$key]['count']++;
    }
}

if (!$rate_limit_exceeded): ?>
<div class="tools-form">
    <h2>Check NFT Holders</h2>
    <p>Enter the <strong>NFT Collection Address</strong> (Collection ID) to see the total number of holders and NFTs.</p>
    <form id="nftHoldersForm" method="POST" action="" data-tool="nft-holders">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="input-wrapper">
            <input type="text" name="mintAddress" id="mintAddressHolders" placeholder="Enter NFT Collection Address" required value="<?php echo htmlspecialchars($_POST['mintAddress'] ?? ''); ?>">
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
            throw new Exception("Invalid CSRF token");
        }

        $mintAddress = preg_replace('/\s+/', '', trim($_POST['mintAddress']));
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
            throw new Exception("Invalid collection address format");
        }

        $cache_data = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) ?? [] : [];
        $cache_expiration = 3 * 3600;
        $cache_valid = isset($cache_data[$mintAddress]) && (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration);

        if (!$cache_valid) {
            $params = ['id' => $mintAddress];
            $asset = callAPI('getAsset', $params, 'POST');
            if (!isset($asset['result']['id'])) throw new Exception("Collection not found");

            $asset = $asset['result'];
            $collection_data = [
                'name' => $asset['content']['metadata']['name'] ?? 'N/A',
                'image' => $asset['content']['links']['image'] ?? '',
                'owner' => $asset['ownership']['owner'] ?? 'N/A'
            ];

            $holderData = fetchNFTCollectionHolders($mintAddress);
            $total_items = $holderData['total_items'];
            $total_wallets = $holderData['total_wallets'];
            $wallet_list = $holderData['wallets'];

            $cache_data[$mintAddress] = [
                'total_items' => $total_items,
                'total_wallets' => $total_wallets,
                'items' => $wallet_list,
                'wallets' => $wallet_list,
                'collection_data' => $collection_data,
                'timestamp' => time()
            ];
            file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
        } else {
            extract($cache_data[$mintAddress]);
            $total_wallets = $total_wallets ?? count($wallets ?? []);
        }

        ?>
        <div class="tools-result nft-holders-result">
            <?php if (($total_wallets ?? 0) === 0): ?>
                <p class="result-error">No holders found for this collection.</p>
            <?php else: ?>
                <div class="nft-collection-info">
                    <?php if (!empty($collection_data['image'])): ?>
                        <img src="<?php echo htmlspecialchars($collection_data['image']); ?>" alt="Collection Image">
                    <?php else: ?>
                        <p>No image available</p>
                    <?php endif; ?>
                    <table>
                        <tr>
                            <th>Collection Name:</th>
                            <td><?php echo htmlspecialchars($collection_data['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Owner address:</th>
                            <td>
                                <?php if ($collection_data['owner'] !== 'N/A' && preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $collection_data['owner'])): ?>
                                    <a href="https://solscan.io/address/<?php echo htmlspecialchars($collection_data['owner']); ?>" target="_blank">
                                        <?php echo substr($collection_data['owner'], 0, 4) . '...' . substr($collection_data['owner'], -4); ?>
                                    </a>
                                    <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($collection_data['owner']); ?>"></i>
                                <?php else: ?>
                                    <span>N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="result-summary">
                    <div class="result-card">
                        <div class="result-item">
                            <i class="fas fa-wallet"></i>
                            <p>Total wallets</p>
                            <h3><?php echo number_format($total_wallets); ?></h3>
                        </div>
                        <div class="result-item">
                            <i class="fas fa-image"></i>
                            <p>Total NFTs</p>
                            <h3><?php echo number_format($total_items); ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="export-section">
                    <p>Export wallet list:</p>
                    <?php if ($cache_valid): ?>
                        <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$mintAddress]['timestamp']) . ' UTC+0'; ?>. Data will be updated every 3 hours.</p>
                    <?php endif; ?>
                    <form method="POST" action="/tools/nft-holders/nft-holders-export.php" class="export-form">
                        <input type="hidden" name="mintAddress" value="<?php echo htmlspecialchars($mintAddress); ?>">
                        <div class="export-controls">
                            <select name="export_format" class="export-format" id="export_format">
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                            </select>
                            <select name="export_type" class="export-type" id="export_type">
                                <option value="all">Wallets + NFT Count</option>
                                <option value="address-only">Wallets Only</option>
                            </select>
                            <button type="submit" class="cta-button export-btn">Export</button>
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
        echo "<div class='result-error'><p>Error processing request: " . htmlspecialchars($e->getMessage()) . "</p></div>";
    }
}
?>
<div class="tools-about">
    <h2>About NFT Holders Checker</h2>
    <p>The NFT Holders Checker allows you to view the total number of holders and NFTs for a specific Solana NFT collection by entering its On-chain Collection address.</p>
</div>
</div>
