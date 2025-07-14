<?php
// =============================================================================
// File: tools/nft-transactions/nft-transactions.php
// Description: Check transaction history for a single Solana NFT using its Mint Address.
// Created by: Vina Network
// =============================================================================

// Define constants
if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft_transactions: bootstrap.php not found at $bootstrap_path", 'nft_transactions_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft_transactions: tools-api.php not found at $api_helper_path", 'nft_transactions_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
log_message("nft_transactions: tools-api.php loaded", 'nft_transactions_log.txt', 'INFO');
?>

<div class="nft-transactions tools-form">
    <h2>Check NFT Transactions</h2>
    <p>Enter the <strong>Mint Address</strong> of a Solana NFT to view its transaction history (up to 100 entries).</p>
    <form id="nftTransactionsForm" method="POST" action="" data-tool="nft-transactions">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="input-wrapper">
            <input type="text" name="mintAddress" id="mintAddressTx" placeholder="Enter NFT Mint Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <span class="clear-input" title="Clear input">&times;</span>
        </div>
        <button type="submit" class="cta-button">Check</button>
    </form>
    <div class="loader"></div>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }

        $mintAddress = trim($_POST['mintAddress']);
        $mintAddress = preg_replace('/\s+/', '', $mintAddress);

        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
            throw new Exception('Invalid Mint Address format');
        }

        log_message("nft_transactions: Fetching transactions for $mintAddress", 'nft_transactions_log.txt', 'INFO');
        $params = ["query" => ["mint" => $mintAddress], "limit" => 100];
        $response = callAPI("getAssetProofs", $params, 'POST');

        if (isset($response['error'])) {
            throw new Exception(is_array($response['error']) ? ($response['error']['message'] ?? 'API error') : $response['error']);
        }

        $transactions = $response['result'] ?? [];
        log_message("nft_transactions: Received " . count($transactions) . " transactions", 'nft_transactions_log.txt', 'INFO');

        echo '<div class="tools-result">';
        echo '<h2>Transaction History</h2>';
        if (empty($transactions)) {
            echo '<p>No transaction history found for this NFT.</p>';
        } else {
            echo '<table class="tools-table">';
            echo '<thead><tr><th>Signature</th><th>Slot</th><th>Type</th><th>Date</th></tr></thead><tbody>';
            foreach ($transactions as $tx) {
                $signature = $tx['signature'] ?? 'N/A';
                $slot = $tx['slot'] ?? 'N/A';
                $type = $tx['type'] ?? 'N/A';
                $ts = $tx['timestamp'] ?? null;
                $date = $ts ? date('Y-m-d H:i', $ts) . ' UTC+0' : 'N/A';

                echo '<tr>';
                echo '<td><a href="https://solscan.io/tx/' . htmlspecialchars($signature) . '" target="_blank">' . substr($signature, 0, 6) . '...' . substr($signature, -4) . '</a></td>';
                echo '<td>' . htmlspecialchars($slot) . '</td>';
                echo '<td>' . htmlspecialchars($type) . '</td>';
                echo '<td>' . htmlspecialchars($date) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
        log_message("nft_transactions: Exception - $error_msg", 'nft_transactions_log.txt', 'ERROR');
        echo "<div class='result-error'><p>$error_msg</p></div>";
    }
}
?>

<div class="tools-about">
    <h2>About Check NFT Transactions</h2>
    <p>This tool fetches the transaction history of a specific Solana NFT using its mint address. Useful for tracking ownership transfers, sales, and other events.</p>
</div>
