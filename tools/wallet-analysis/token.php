<?php
// ============================================================================
// File: tools/wallet-analysis/token.php
// Description: Display Tokens tab content for Wallet Analysis
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$formatted_data = $_SESSION['wallet_analysis_data'] ?? null;
if (!$formatted_data) {
    echo "<div class='result-error'><p>Error: No wallet data available.</p></div>";
    log_message("token: No wallet data in session", 'wallet_analysis_log.txt', 'ERROR');
    exit;
}
?>

<?php if (!empty($formatted_data['tokens'])): ?>
<div class="wallet-details token-details">
    <div class="token-table">
        <table>
            <tr><th>Name</th><th>Token Address</th><th>Balance</th><th>Value (USD)</th></tr>
            <?php foreach ($formatted_data['tokens'] as $token): ?>
            <tr>
                <td><?php echo htmlspecialchars($token['name']); ?></td>
                <td>
                    <a href="https://solscan.io/token/<?php echo urlencode($token['mint']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo substr(htmlspecialchars($token['mint']), 0, 4) . '...' . substr(htmlspecialchars($token['mint']), -4); ?>
                    </a>
                    <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($token['mint']); ?>"></i>
                </td>
                <td><?php echo number_format($token['balance'], 2); ?></td>
                <td><?php echo number_format($token['price_usd'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php else: ?>
<p>No tokens found for this wallet.</p>
<?php endif; ?>
