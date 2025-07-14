<?php
// ============================================================================
// File: tools/nft-transactions/nft-transactions.php
// Description: Check transaction history for a Solana NFT using its Mint Address.
// Created by: Vina Network
// ============================================================================

// Define constants
if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft_tx: bootstrap.php not found at $bootstrap_path", 'nft_transactions_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft_tx: tools-api.php not found at $api_helper_path", 'nft_transactions_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
log_message("nft_tx: tools-api.php loaded", 'nft_transactions_log.txt', 'INFO');

// Form and result output
?>
<link rel="stylesheet" href="/tools/nft-transactions/nft-transactions.css">
<div class="nft-transactions">
    <div class="tools-form">
        <h2>Check NFT Transactions</h2>
        <p>Enter a <strong>NFT Mint Address</strong> to see full transfer/sale history.</p>
        <form id="nftTransactionsForm" method="POST" action="" data-tool="nft-transactions">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="input-wrapper">
                <input type="text" name="mintAddress" id="mintAddressTx" placeholder="Enter NFT Mint Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
                <span class="clear-input" title="Clear input">×</span>
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

            log_message("nft_tx: Fetching transaction events for $mintAddress", 'nft_transactions_log.txt', 'INFO');

            $params = [
                'query' => [
                    'assetId' => $mintAddress
                ],
                'options' => [
                    'limit' => 50
                ]
            ];
            $events = callAPI('nft-events', $params, 'POST');

            if (!isset($events['result']) || !is_array($events['result'])) {
                throw new Exception('No transaction history found for this NFT');
            }

            echo '<div class="tools-result nft-transactions-result">';
            echo '<h2>Transaction History</h2>';
            echo '<div class="result-summary">';
            echo '<table class="nft-tx-table">';
            echo '<tr><th>Time</th><th>Event</th><th>From</th><th>To</th><th>Signature</th></tr>';

            foreach ($events['result'] as $event) {
                $ts = isset($event['timestamp']) ? date('Y-m-d H:i', $event['timestamp']) : 'N/A';
                $type = ucfirst($event['type'] ?? 'Unknown');
                $from = $event['source'] ?? '-';
                $to = $event['destination'] ?? '-';
                $sig = $event['signature'] ?? '-';

                echo '<tr>';
                echo '<td>' . htmlspecialchars($ts) . '</td>';
                echo '<td>' . htmlspecialchars($type) . '</td>';
                echo '<td>' . (preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $from) ? '<span>' . substr($from, 0, 4) . '...' . substr($from, -4) . '</span> <i class="fas fa-copy copy-icon" data-full="' . $from . '" title="Copy"></i>' : '-') . '</td>';
                echo '<td>' . (preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $to) ? '<span>' . substr($to, 0, 4) . '...' . substr($to, -4) . '</span> <i class="fas fa-copy copy-icon" data-full="' . $to . '" title="Copy"></i>' : '-') . '</td>';
                echo '<td>' . (strlen($sig) > 8 ? '<a href="https://solscan.io/tx/' . $sig . '" target="_blank">' . substr($sig, 0, 6) . '...</a>' : '-') . '</td>';
                echo '</tr>';
            }

            echo '</table>';
            echo '</div>';
            echo '</div>';
        } catch (Exception $e) {
            log_message("nft_tx: Exception - " . $e->getMessage(), 'nft_transactions_log.txt', 'ERROR');
            echo '<div class="result-error"><p>' . htmlspecialchars($e->getMessage()) . '</p></div>';
        }
    }
    ?>

    <div class="tools-about">
        <h2>About Check NFT Transactions</h2>
        <p>This tool retrieves the full transaction history (mint, transfers, sales) for a specific NFT on Solana using its Mint Address.</p>
    </div>
</div>
