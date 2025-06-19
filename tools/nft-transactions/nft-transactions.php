<?php
// File: tools/nft-transactions/nft-transactions.php
// Description: Check transaction history for a Solana NFT collection.
// Created by: Vina Network
// ============================================================================

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__) . '/tools-api1.php';

$mintAddress = $_POST['mintAddress'] ?? '';
$mintAddress = trim(preg_replace('/\s+/', '', $mintAddress));

$page_title = 'Check NFT Transactions - Vina Network';
$page_description = 'Check transaction history for a Solana NFT collection address.';
$page_css = ['../../css/vina.css', '../tools1.css'];

include '../../include/header.php';
include '../../include/navbar.php';

?><div class="t-6 nft-transactions-content">
    <div class="t-7">
        <h2>Check NFT Transactions</h2>
        <form method="POST">
            <input type="text" name="mintAddress" placeholder="Enter Collection Address" value="<?php echo htmlspecialchars($mintAddress); ?>" required>
            <button type="submit" class="cta-button">Check Transactions</button>
        </form>
    </div>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
        $params = [
            'query' => [
                'interface' => 'DigitalAsset',
                'grouping' => [['group_key' => 'collection', 'group_value' => $mintAddress]]
            ],
            'options' => [
                'limit' => 50,
                'sort_direction' => 'desc'
            ]
        ];$response = callAPI('nft-events', $params, 'POST');
    if (isset($response['result'])) {
        $transactions = array_filter($response['result'], function($tx) {
            return isset($tx['type']) && $tx['type'] === 'NFT_SALE';
        });

        if (empty($transactions)) {
            echo "<div class='result-error'><p>No NFT sales found for this collection.</p></div>";
        } else {
            echo "<div class='result-section'><div class='transactions-table'><table class='transaction-table'><thead><tr><th>Signature</th><th>Time</th><th>Price (SOL)</th><th>Buyer</th><th>Seller</th></tr></thead><tbody>";
            foreach ($transactions as $tx) {
                $ev = $tx['nft'] ?? [];
                echo "<tr>" .
                     "<td>" . substr($tx['signature'], 0, 8) . "...</td>" .
                     "<td>" . date('d M Y, H:i', $tx['timestamp']) . "</td>" .
                     "<td>" . number_format(($ev['amount'] ?? 0) / 1e9, 2) . "</td>" .
                     "<td>" . substr($ev['buyer'] ?? '', 0, 8) . "...</td>" .
                     "<td>" . substr($ev['seller'] ?? '', 0, 8) . "...</td>" .
                     "</tr>";
            }
            echo "</tbody></table></div></div>";
        }
    } else {
        echo "<div class='result-error'><p>API error: " . htmlspecialchars(json_encode($response['error'] ?? 'Unknown error')) . "</p></div>";
    }
}
?>
<div class="t-9">
    <h2>About NFT Transactions Checker</h2>
    <p>The NFT Transactions Checker allows you to view the transaction history for a specific Solana NFT collection.</p>
</div>

</div>
<?php
include '../../include/footer.php';
?>
