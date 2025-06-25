<?php
// ============================================================================
// File: tools/wallet-analysis/nft.php
// Description: Display NFTs tab content for Wallet Analysis
// Author: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$formatted_data = $_SESSION['wallet_analysis_data'] ?? null;
$walletAddress = $formatted_data['wallet_address'] ?? null;
if (!$formatted_data || !$walletAddress) {
    echo "<div class='result-error'><p>Error: No wallet data available.</p></div>";
    log_message("nft: No wallet data or address in session", 'wallet_api_log.txt', 'ERROR');
    exit;
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
