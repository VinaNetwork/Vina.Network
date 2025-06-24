<?php
// ============================================================================
// File: tools/wallet-analysis/domain.php
// Description: Display Domains tab content for Wallet Analysis
// Author: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$formatted_data = $_SESSION['wallet_analysis_data'] ?? null;
$domains_available = true; // Assume API availability, adjust if needed
if (!$formatted_data) {
    echo "<div class='result-error'><p>Error: No wallet data available.</p></div>";
    log_message("domain: No wallet data in session", 'wallet_api_log.txt', 'ERROR');
    exit;
}
?>

<h2>.sol Domains</h2>
<div class="wallet-details sol-domains">
    <?php if (!$domains_available && empty($formatted_data['sol_domains'])): ?>
        <p>Domains temporarily unavailable due to API issues. Please try again later.</p>
    <?php elseif (empty($formatted_data['sol_domains'])): ?>
        <p>No .sol domains found for this wallet.</p>
    <?php else: ?>
        <div class="sol-domains-table">
            <table>
                <tr><th>Domain Name</th></tr>
                <?php foreach ($formatted_data['sol_domains'] as $domain): ?>
                <tr>
                    <td><?php echo htmlspecialchars($domain['domain']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
