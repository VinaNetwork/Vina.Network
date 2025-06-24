<?php
// ============================================================================
// File: tools/wallet-analysis/nft.php
// Description: Display NFTs tab content for Wallet Analysis, fetch data on tab click
// Author: Vina Network
// Version: 23.5 (Lazy-load NFTs)
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft: bootstrap.php not found at $bootstrap_path", 'wallet_api_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

$formatted_data = $_SESSION['wallet_analysis_data'] ?? null;
$walletAddress = $formatted_data['wallet_address'] ?? null;
if (!$formatted_data || !$walletAddress) {
    echo "<div class='result-error'><p>Error: No wallet data available.</p></div>";
    log_message("nft: No wallet data or address in session", 'wallet_api_log.txt', 'ERROR');
    exit;
}

$cache_dir = WALLET_ANALYSIS_PATH . 'cache/';
$cache_file = $cache_dir . 'wallet_analysis_cache.json';
$cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
$cache_expiration = 3 * 3600;
$cache_valid = isset($cache_data[$walletAddress]) && (time() - $cache_data[$walletAddress]['timestamp'] < $cache_expiration) && !empty($cache_data[$walletAddress]['data']['nfts']);

log_message("nft: Cache check for walletAddress=$walletAddress, cache_valid=$cache_valid", 'wallet_api_log.txt', 'DEBUG');

if (!$cache_valid || empty($formatted_data['nfts'])) {
    try {
        $params = [
            'ownerAddress' => $walletAddress,
            'page' => 1,
            'limit' => 1000,
            'displayOptions' => [
                'showFungible' => false,
                'showNativeBalance' => false,
                'showNonFungible' => true
            ]
        ];
        $assets = callAPI('getAssetsByOwner', $params, 'POST');

        if (isset($assets['error'])) {
            throw new Exception(is_array($assets['error']) ? ($assets['error']['message'] ?? 'API error') : $assets['error']);
        }
        if (empty($assets['result']) || !isset($assets['result']['items'])) {
            $formatted_data['nfts'] = [];
            log_message("nft: No NFTs found for walletAddress=$walletAddress", 'wallet_api_log.txt', 'INFO');
        } else {
            $formatted_data['nfts'] = [];
            foreach ($assets['result']['items'] as $item) {
                if (in_array($item['interface'], ['V1_NFT', 'ProgrammableNFT'])) {
                    $formatted_data['nfts'][] = [
                        'mint' => $item['id'] ?? 'N/A',
                        'name' => $item['content']['metadata']['name'] ?? 'N/A',
                        'collection' => isset($item['grouping'][0]['group_value']) ? $item['grouping'][0]['group_value'] : 'N/A'
                    ];
                }
            }
            log_message("nft: Fetched NFTs for walletAddress=$walletAddress, count=" . count($formatted_data['nfts']), 'wallet_api_log.txt', 'INFO');
        }

        // Update session and cache
        $_SESSION['wallet_analysis_data'] = $formatted_data;
        $cache_data[$walletAddress]['data'] = $formatted_data;
        if (file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT)) === false) {
            log_message("nft: Failed to write cache file at $cache_file", 'wallet_api_log.txt', 'ERROR');
            throw new Exception('Failed to write cache file');
        }
        log_message("nft: Updated cache with NFTs for walletAddress=$walletAddress", 'wallet_api_log.txt', 'INFO');
    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error fetching NFTs: " . htmlspecialchars($e->getMessage()) . "</p></div>";
        log_message("nft: Error fetching NFTs for walletAddress=$walletAddress: " . $e->getMessage(), 'wallet_api_log.txt', 'ERROR');
        exit;
    }
}
?>

<?php if (!empty($formatted_data['nfts'])): ?>
<h2>NFTs Details</h2>
<div class="wallet-details nft-details">
    <div class="nft-table">
        <table>
            <tr><th>Name</th><th>Mint Address</th><th>Collection</th></tr>
            <?php foreach ($formatted_data['nfts'] as $nft): ?>
            <tr>
                <td><?php echo htmlspecialchars($nft['name']); ?></td>
                <td>
                    <span><?php echo substr(htmlspecialchars($nft['mint']), 0, 4) . '...' . substr(htmlspecialchars($nft['mint']), -4); ?></span>
                    <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($nft['mint']); ?>"></i>
                </td>
                <td>
                    <?php if ($nft['collection'] !== 'N/A' && preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $nft['collection'])): ?>
                        <span><?php echo substr(htmlspecialchars($nft['collection']), 0, 4) . '...' . substr(htmlspecialchars($nft['collection']), -4); ?></span>
                        <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($nft['collection']); ?>"></i>
                    <?php else: ?>
                        <span>N/A</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php else: ?>
<p>No NFTs found for this wallet.</p>
<?php endif; ?>
