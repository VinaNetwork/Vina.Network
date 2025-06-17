<?php
// ============================================================================
// File: tools/nft-transactions/nft-transactions.php
// Description: Check transaction history for Solana NFT using Helius API.
// Created by: Vina Network
// ============================================================================

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-transactions: bootstrap.php not found at $bootstrap_path", 'nft_transactions_log.txt', 'ERROR');
    die('Error: bootstrap.php not found');
}
require_once $bootstrap_path;

session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

// Rate limiting: 5 requests per minute per IP
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "rate_limit:$ip";
$rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['count'] : 0;
$rate_limit_time = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['time'] : 0;
if (time() - $rate_limit_time > 60) {
    $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
    log_message("nft-transactions: Reset rate limit for IP=$ip, count=1", 'nft_transactions_log.txt');
} elseif ($rate_limit_count >= 5) {
    log_message("nft-transactions: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'nft_transactions_log.txt', 'ERROR');
    die("<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>");
} else {
    $_SESSION[$rate_limit_key]['count']++;
    log_message("nft-transactions: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'nft_transactions_log.txt');
}

$api_helper_path = __DIR__ . '/../tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-transactions: tools-api.php not found at $api_helper_path", 'nft_transactions_log.txt', 'ERROR');
    die('Internal Server Error: Missing tools-api.php');
}
include $api_helper_path;

// CSV export function
function exportToCsv($transactions, $mintAddress) {
    $filename = "nft_transactions_{$mintAddress}_" . date('YmdHis') . ".csv";
    $filepath = __DIR__ . "/exports/{$filename}";
    if (!is_dir(__DIR__ . '/exports')) {
        mkdir(__DIR__ . '/exports', 0755, true);
    }
    $fp = fopen($filepath, 'w');
    fputcsv($fp, ['Signature', 'Type', 'Timestamp', 'From', 'To', 'Price (SOL)']);
    foreach ($transactions as $tx) {
        fputcsv($fp, [
            $tx['signature'] ?? 'N/A',
            $tx['type'] ?? 'Unknown',
            isset($tx['timestamp']) ? date('Y-m-d H:i:s', $tx['timestamp']) : 'N/A',
            $tx['source'] ?? 'N/A',
            $tx['destination'] ?? 'N/A',
            number_format($tx['price'] ?? 0, 2)
        ]);
    }
    fclose($fp);
    return $filename;
}

$root_path = '../../';
$page_title = 'Check NFT Transactions - Vina Network';
$page_description = 'Check transaction history for a Solana NFT.';
$page_css = ['../../css/vina.css', '../tools.css'];
include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';
?>

<div class="t-6 nft-transactions-content">
    <div class="t-7">
        <h2>Check NFT Transactions</h2>
        <p>Enter the <strong>NFT Mint Address</strong> to view its transaction history.</p>
        <form id="nftTransactionsForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="text" name="mintAddress" id="mintAddressTransactions" placeholder="Enter NFT Mint Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <button type="submit" class="cta-button">Check Transactions</button>
        </form>
        <div class="loader"></div>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                log_message("nft-transactions: Invalid CSRF token for mintAddress=" . ($_POST['mintAddress'] ?? 'unknown'), 'nft_transactions_log.txt', 'ERROR');
                throw new Exception("Invalid CSRF token. Please try again.");
            }

            $mintAddress = trim($_POST['mintAddress']);
            log_message("nft-transactions: Form submitted with mintAddress=$mintAddress", 'nft_transactions_log.txt');

            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                log_message("nft-transactions: Invalid mintAddress format: $mintAddress", 'nft_transactions_log.txt', 'ERROR');
                throw new Exception("Invalid mint address. Please enter a valid Solana mint address (32-44 characters, base58).");
            }

            // Get transactions from Helius API
            $params = [
                'id' => $mintAddress,
                'page' => 1,
                'limit' => 100
            ];
            $helius_data = callAPI('getSignaturesForAsset', $params, 'POST');

            if (isset($helius_data['error'])) {
                $errorMessage = is_array($helius_data['error']) && isset($helius_data['error']['message']) ? $helius_data['error']['message'] : json_encode($helius_data['error']);
                log_message("nft-transactions: Helius API error for mintAddress=$mintAddress: $errorMessage", 'nft_transactions_log.txt', 'ERROR');
                throw new Exception("Helius API error: $errorMessage");
            }

            log_message("nft-transactions: Helius API response: " . json_encode($helius_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 'nft_transactions_log.txt');

            $transactions = $helius_data['result']['items'] ?? [];
            if (empty($transactions)) {
                throw new Exception("No transactions found for the provided mint address.");
            }

            // Export to CSV
            $csv_filename = exportToCsv($transactions, $mintAddress);
            ?>

            <!-- Display transactions table -->
            <div class="result-section">
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>Signature</th>
                            <th>Type</th>
                            <th>Timestamp</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Price (SOL)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx) : ?>
                            <tr>
                                <td><a href="https://solscan.io/tx/<?php echo htmlspecialchars($tx['signature']); ?>" target="_blank"><?php echo htmlspecialchars(substr($tx['signature'], 0, 10)) . '...'; ?></a></td>
                                <td><?php echo htmlspecialchars($tx['type'] ?? 'Unknown'); ?></td>
                                <td><?php echo isset($tx['timestamp']) ? date('Y-m-d H:i:s', $tx['timestamp']) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars(substr($tx['source'] ?? 'N/A', 0, 10)) . '...'; ?></td>
                                <td><?php echo htmlspecialchars(substr($tx['destination'] ?? 'N/A', 0, 10)) . '...'; ?></td>
                                <td><?php echo number_format($tx['price'] ?? 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>Mint Address: <?php echo htmlspecialchars($mintAddress); ?></p>
                <a href="exports/<?php echo htmlspecialchars($csv_filename); ?>" download class="cta-button">Download CSV</a>
            </div>
            <?php
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            log_message("nft-transactions: Exception - $error_msg", 'nft_transactions_log.txt', 'ERROR');
            echo "<div class='result-error'><p>$error_msg. Please try again.</p></div>";
        }
    }
    ?>

    <div class="t-9">
        <h2>About NFT Transactions Checker</h2>
        <p>
            The NFT Transactions Tool allows you to view the transaction history for a specific Solana NFT by entering its mint address.
            This tool provides details such as transaction signature, type, timestamp, and price, useful for tracking NFT activity.
        </p>
    </div>
</div>

<?php
ob_start();
include $root_path . 'include/footer.php';
$footer_output = ob_get_clean();
log_message("nft-transactions: Footer output length: " . strlen($footer_output), 'nft_transactions_log.txt');
echo $footer_output;
?>
