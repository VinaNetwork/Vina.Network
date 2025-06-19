<?php
// File: tools/nft-transactions/nft-transactions.php
// Description: Check transaction history for a Solana NFT collection.
// Created by: Vina Network

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    error_log("nft-transactions: bootstrap.php not found at $bootstrap_path");
    echo "<div class='result-error'><p>Error: Cannot find bootstrap.php</p></div>";
    exit;
}
require_once $bootstrap_path;

session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

if (!defined('LOGS_PATH') || !is_writable(LOGS_PATH)) {
    error_log("nft-transactions: Logs directory " . (defined('LOGS_PATH') ? LOGS_PATH : 'undefined') . " is not writable");
    echo "<div class='result-error'><p>Error: Logs directory is not writable</p></div>";
    exit;
}

log_message("nft-transactions: Script started successfully", 'nft_transactions_log.txt', 'DEBUG');

$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "rate_limit:$ip";
$rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['count'] : 0;
$rate_limit_time = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['time'] : 0;
if (time() - $rate_limit_time > 60) {
    $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
    log_message("nft-transactions: Reset rate limit for IP=$ip, count=1", 'nft_transactions_log.txt');
} elseif ($rate_limit_count >= 5) {
    log_message("nft-transactions: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'nft_transactions_log.txt', 'ERROR');
    echo "<div class='result-error'><p>Rate limit exceeded. Please try again after 1 minute.</p></div>";
    exit;
} else {
    $_SESSION[$rate_limit_key]['count']++;
    log_message("nft-transactions: Increment rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'nft_transactions_log.txt');
}

$cache_dir = __DIR__ . '/cache/';
$cache_file = $cache_dir . 'nft_transactions_cache.json';

if (!is_dir($cache_dir)) {
    if (!mkdir($cache_dir, 0755, true)) {
        log_message("nft-transactions: Could not create cache directory at $cache_dir", 'nft_transactions_log.txt', 'ERROR');
        echo "<div class='result-error'><p>Error: Could not create cache directory</p></div>";
        exit;
    }
    log_message("nft-transactions: Created cache directory at $cache_dir", 'nft_transactions_log.txt');
    @chown($cache_dir, 'www-data');
    @chgrp($cache_dir, 'www-data');
    @chmod($cache_dir, 0755);
}

if (!file_exists($cache_file)) {
    if (file_put_contents($cache_file, json_encode([])) === false) {
        log_message("nft-transactions: Could not create cache file at $cache_file", 'nft_transactions_log.txt', 'ERROR');
        echo "<div class='result-error'><p>Error: Could not create cache file</p></div>";
        exit;
    }
    @chmod($cache_file, 0644);
    log_message("nft-transactions: Created cache file at $cache_file", 'nft_transactions_log.txt');
}

if (!is_writable($cache_file)) {
    log_message("nft-transactions: Cache file $cache_file is not writable", 'nft_transactions_log.txt', 'ERROR');
    echo "<div class='result-error'><p>Error: Cache file is not writable</p></div>";
    exit;
}

$root_path = '../../';
$page_title = 'NFT Transactions Checker - Vina Network';
$page_description = 'Check transaction history for a Solana NFT collection address.';
$page_css = ['../../css/vina.css', '../tools1.css'];

try {
    log_message("nft-transactions: Including header and navbar", 'nft_transactions_log.txt', 'DEBUG');
    include $root_path . 'include/header.php';
    include $root_path . 'include/navbar.php';
} catch (Exception $e) {
    log_message("nft-transactions: Error including header/navbar: " . $e->getMessage(), 'nft_transactions_log.txt', 'ERROR');
    echo "<div class='result-error'><p>Error loading page layout: " . htmlspecialchars($e->getMessage()) . "</p></div>";
    exit;
}

$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-transactions: tools-api.php not found at $api_helper_path", 'nft_transactions_log.txt', 'ERROR');
    echo "<div class='result-error'><p>Error: tools-api.php is missing</p></div>";
    exit;
}
log_message("nft-transactions: Including tools-api.php from $api_helper_path", 'nft_transactions_log.txt');
require_once $api_helper_path;

log_message("nft-transactions: Script started, mintAddress=" . ($_POST['mintAddress'] ?? 'none'), 'nft_transactions_log.txt', 'DEBUG');
?>
<div class="t-6 nft-transactions-content">
    <div class="t-7">
        <h2>NFT Transactions Checker</h2>
        <p>Enter the <strong>NFT Collection Address</strong> (Collection ID) to view transaction history. Example: Find this address on MagicEden under "Details" > "On-chain Collection".</p>
        <form id="nftTransactionsForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="text" name="mintAddress" id="mintAddressTransactions" placeholder="Enter NFT Collection Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <button type="submit" class="cta-button">Check Transactions</button>
        </form>
        <div class="loader"></div>
        <?php log_message("nft-transactions: Rendered form", 'nft_transactions_log.txt', 'DEBUG'); ?>
    </div>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            log_message("nft-transactions: Processing form, mintAddress=" . $_POST['mintAddress'], 'nft_transactions_log.txt', 'DEBUG');
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                log_message("nft-transactions: Invalid CSRF token for mintAddress=" . ($_POST['mintAddress'] ?? 'unknown'), 'nft_transactions_log.txt', 'ERROR');
                throw new Exception("Invalid CSRF token. Please try again.");
            }

            $mintAddress = trim($_POST['mintAddress']);
            $mintAddress = preg_replace('/\s+/', '', $mintAddress);
            log_message("nft-transactions: Raw mintAddress=" . ($_POST['mintAddress'] ?? 'null') . ", processed mintAddress=$mintAddress", 'nft_transactions_log.txt', 'DEBUG');
            $limit = 100;
            $max_pages = 5; // Limit to 5 pages to avoid rate limits
            $cache_expiration = 3 * 3600;

            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                log_message("nft-transactions: Invalid mintAddress format: $mintAddress", 'nft_transactions_log.txt', 'ERROR');
                throw new Exception("Invalid collection address. Please enter a valid Solana collection address (32-44 chars, base58).");
            }

            $cache_data = [];
            if (file_exists($cache_file)) {
                $cache_content = file_get_contents($cache_file);
                if ($cache_content !== false) {
                    $cache_data = json_decode($cache_content, true);
                    if (!is_array($cache_data)) {
                        $cache_data = [];
                        log_message("nft-transactions: Could not decode cache, resetting cache", 'nft_transactions_log.txt', 'ERROR');
                    }
                } else {
                    log_message("nft-transactions: Could not read cache file, initializing empty cache", 'nft_transactions_log.txt', 'WARNING');
                }
            }

            $cache_valid = isset($cache_data[$mintAddress]) && 
                           isset($cache_data[$mintAddress]['timestamp']) && 
                           (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration);

            if (!$cache_valid) {
                if (isset($cache_data[$mintAddress]['timestamp']) && !$cache_valid) {
                    log_message("nft-transactions: Cache expired for mintAddress=$mintAddress, fetching new data", 'nft_transactions_log.txt');
                } elseif (!isset($cache_data[$mintAddress])) {
                    log_message("nft-transactions: No cache found for mintAddress=$mintAddress, fetching new data", 'nft_transactions_log.txt');
                }

                foreach ($cache_data as $address => $data) {
                    if (!isset($data['timestamp']) || (time() - $data['timestamp'] > $cache_expiration)) {
                        unset($cache_data[$address]);
                        log_message("nft-transactions: Removed expired cache for mintAddress=$address", 'nft_transactions_log.txt');
                    }
                }

                $total_transactions = 0;
                $api_page = 1;
                $has_more = true;
                $transactions = [];
                $before_signature = null;

                while ($has_more && $api_page <= $max_pages) {
                    $params = [
                        'address' => $mintAddress,
                        'limit' => $limit
                    ];
                    if ($before_signature) {
                        $params['before'] = $before_signature;
                    }
                    log_message("nft-transactions: Calling getTransactionHistory API, page=$api_page, params=" . json_encode($params), 'nft_transactions_log.txt', 'DEBUG');
                    $data = callAPI('getTransactionHistory', $params, 'POST');
                    log_message("nft-transactions: getTransactionHistory API response, page=$api_page: " . json_encode($data, JSON_PRETTY_PRINT), 'nft_transactions_log.txt', 'DEBUG');

                    if (isset($data['error'])) {
                        $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
                        log_message("nft-transactions: getTransactionHistory API error: $errorMessage", 'nft_transactions_log.txt', 'ERROR');
                        throw new Exception("API error: $errorMessage");
                    }

                    if (!isset($data['result'])) {
                        log_message("nft-transactions: Invalid API response, no result found for page=$api_page, mintAddress=$mintAddress", 'nft_transactions_log.txt', 'ERROR');
                        throw new Exception("Invalid API response: No data found.");
                    }

                    $page_txs = array_filter($data['result'], function($tx) {
                        $is_nft_related = isset($tx['events']['nft']) || 
                                          isset($tx['events']['compressed']) || 
                                          in_array($tx['type'] ?? '', ['NFT_SALE', 'NFT_BID', 'NFT_TRANSFER', 'COMPRESSED_NFT_MINT', 'NFT_MINT', 'TRANSFER']);
                        log_message("nft-transactions: Transaction signature={$tx['signature']}, type={$tx['type']}, is_nft_related=" . ($is_nft_related ? 'true' : 'false'), 'nft_transactions_log.txt', 'DEBUG');
                        return $is_nft_related;
                    });

                    $tx_count = count($page_txs);
                    $total_transactions += $tx_count;
                    $transactions = array_merge($transactions, array_map(function($tx) {
                        $nft_event = $tx['events']['nft'] ?? $tx['events']['compressed'][0] ?? [];
                        return [
                            'signature' => $tx['signature'] ?? 'N/A',
                            'timestamp' => isset($tx['timestamp']) ? date('d M Y, H:i', $tx['timestamp']) : 'N/A',
                            'price' => isset($nft_event['amount']) ? $nft_event['amount'] / 1e9 : 0,
                            'buyer' => $nft_event['buyer'] ?? $nft_event['newLeafOwner'] ?? 'N/A',
                            'seller' => $nft_event['seller'] ?? $nft_event['oldLeafOwner'] ?? 'N/A',
                            'type' => $tx['type'] ?? 'N/A'
                        ];
                    }, $page_txs));

                    $last_tx = end($page_txs);
                    $before_signature = $last_tx ? $last_tx['signature'] : null;
                    $has_more = $tx_count >= $limit;
                    $api_page++;
                    log_message("nft-transactions: Page $api_page added $tx_count transactions, total_transactions=$total_transactions", 'nft_transactions_log.txt');
                    usleep(1000000); // 1s delay
                }

                $cache_data[$mintAddress] = [
                    'total_transactions' => $total_transactions,
                    'transactions' => $transactions,
                    'timestamp' => time()
                ];
                $fp = fopen($cache_file, 'c');
                if (flock($fp, LOCK_EX)) {
                    if (file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT)) === false) {
                        log_message("nft-transactions: Could not write cache to $cache_file", 'nft_transactions_log.txt', 'ERROR');
                        flock($fp, LOCK_UN);
                        fclose($fp);
                        throw new Exception("Could not save cache data");
                    }
                    flock($fp, LOCK_UN);
                } else {
                    log_message("nft-transactions: Could not lock cache file $cache_file", 'nft_transactions_log.txt', 'ERROR');
                    fclose($fp);
                    throw new Exception("Could not lock cache file");
                }
                fclose($fp);
                log_message("nft-transactions: Cached total_transactions=$total_transactions for $mintAddress, timestamp=" . date('Y-m-d H:i:s'), 'nft_transactions_log.txt');
            } else {
                $total_transactions = $cache_data[$mintAddress]['total_transactions'] ?? 0;
                $transactions = $cache_data[$mintAddress]['transactions'] ?? [];
                $cache_timestamp = $cache_data[$mintAddress]['timestamp'];
                log_message("nft-transactions: Retrieved total_transactions=$total_transactions from cache for $mintAddress, cached at " . date('Y-m-d H:i:s', $cache_timestamp), 'nft_transactions_log.txt');
            }

            log_message("nft-transactions: Final total_transactions=$total_transactions for $mintAddress", 'nft_transactions_log.txt');

            if ($total_transactions === 0) {
                log_message("nft-transactions: No transactions found for mintAddress=$mintAddress", 'nft_transactions_log.txt', 'WARNING');
                echo "<div class='result-error'><p>No transactions found for this collection. Please check the address or try again later.</p></div>";
            } else {
                ?>
                <div class="result-section">
                    <div class="transactions-table">
                        <table class="transaction-table" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th>Signature</th>
                                    <th>Timestamp</th>
                                    <th>Price (SOL)</th>
                                    <th>Buyer</th>
                                    <th>Seller</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $per_page = 50;
                                $current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                                $offset = ($current_page - 1) * $per_page;
                                $paged_transactions = array_slice($transactions, $offset, $per_page);
                                foreach ($paged_transactions as $tx): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(substr($tx['signature'], 0, 8)) . '...'; ?></td>
                                        <td><?php echo htmlspecialchars($tx['timestamp']); ?></td>
                                        <td><?php echo number_format($tx['price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars(substr($tx['buyer'], 0, 8)) . '...'; ?></td>
                                        <td><?php echo htmlspecialchars(substr($tx['seller'], 0, 8)) . '...'; ?></td>
                                        <td><?php echo htmlspecialchars($tx['type']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                    $total_pages = ceil($total_transactions / $per_page);
                    if ($total_pages > 1):
                    ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?tool=nft-transactions&mintAddress=<?php echo urlencode($mintAddress); ?>&page=<?php echo $i; ?>" class="page-link <?php echo $i === $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($cache_valid): ?>
                        <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$mintAddress]['timestamp']) . ' UTC+0'; ?>. Data refreshes every 3 hours.</p>
                    <?php endif; ?>
                    <div class="export-section">
                        <form method="POST" action="/tools/nft-transactions/nft-transactions-export.php" class="export-form">
                            <input type="hidden" name="mintAddress" value="<?php echo htmlspecialchars($mintAddress); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <div class="export-controls">
                                <select name="export_format" class="export-format">
                                    <option value="csv">CSV</option>
                                    <option value="json">JSON</option>
                                </select>
                                <button type="submit" name="export_type" value="all" class="cta-button export-btn" id="export-all-btn">Export All Transactions</button>
                            </div>
                        </form>
                        <div class="progress-container" style="display: none;">
                            <p>Exporting data... Please wait.</p>
                            <div class="progress-bar"><div class="progress-bar-fill" style="width: 0%;"></div></div>
                        </div>
                    </div>
                </div>
                <?php
            }
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            log_message("nft-transactions: Exception - $error_msg", 'nft_transactions_log.txt', 'ERROR');
            echo "<div class='result-error'><p>$error_msg. Please try again.</p></div>";
        }
    }
    ?>
    <div class="t-9">
        <h2>About the NFT Transactions Checker</h2>
        <p>
            The NFT Transactions Checker allows you to view the transaction history of a specific Solana NFT collection by entering its On-chain Collection Address. 
            This tool is useful for tracking sales, analyzing market activity, and understanding the transaction history of a collection on the Solana blockchain.
        </p>
    </div>
</div>
<?php
log_message("nft-transactions: Including footer", 'nft_transactions_log.txt', 'DEBUG');
include $root_path . 'include/footer.php';
?>
