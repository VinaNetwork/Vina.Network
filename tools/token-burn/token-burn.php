<?php
// File: tools/token-burn/token-burn.php
// Description: Calculate total burned tokens for a Solana wallet address.
// Created by: Vina Network

error_log("[".date('Y-m-d H:i:s')."] [INFO] token_burn: Starting token-burn.php", 3, '/var/www/vinanetwork/public_html/tools/logs/php_errors.txt');

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = dirname(__DIR__).'/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    error_log("[".date('Y-m-d H:i:s')."] [CRITICAL] token_burn: bootstrap.php not found at $bootstrap_path", 3, '/var/www/vinanetwork/public_html/tools/logs/php_errors.txt');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

$cache_dir = TOKEN_BURN_PATH.'cache/';
$cache_file = $cache_dir.'token_burn_cache.json';
if (!file_exists($cache_file)) {
    file_put_contents($cache_file, json_encode([]));
    chmod($cache_file, 0664);
    error_log("[".date('Y-m-d H:i:s')."] [INFO] token_burn: Created cache file $cache_file", 3, '/var/www/vinanetwork/public_html/tools/logs/php_errors.txt');
}
if (!ensure_directory_and_file($cache_dir, $cache_file, 'token_burn_log.txt')) {
    error_log("[".date('Y-m-d H:i:s')."] [CRITICAL] token_burn: Cache setup failed for $cache_dir or $cache_file", 3, '/var/www/vinanetwork/public_html/tools/logs/php_errors.txt');
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}
log_message("token_burn: Cache setup completed, cache_dir=$cache_dir", 'token_burn_log.txt', 'INFO');

$api_helper_path = dirname(__DIR__).'/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("token_burn: tools-api.php not found at $api_helper_path", 'token_burn_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;

$burn_address = '11111111111111111111111111111111';
?>
<link rel="stylesheet" href="/tools/token-burn/token-burn.css">
<div class="token-burn">
<?php
$rate_limit_exceeded = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['walletAddress'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = "rate_limit_token_burn:$ip";
    $rate_limit_count = $_SESSION[$rate_limit_key]['count'] ?? 0;
    $rate_limit_time = $_SESSION[$rate_limit_key]['time'] ?? 0;
    if (time() - $rate_limit_time > 60) {
        $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
        log_message("token_burn: Reset rate limit for IP=$ip, count=1", 'token_burn_log.txt', 'INFO');
    } elseif ($rate_limit_count >= 5) {
        $rate_limit_exceeded = true;
        log_message("token_burn: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'token_burn_log.txt', 'ERROR');
        echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
    } else {
        $_SESSION[$rate_limit_key]['count']++;
        log_message("token_burn: Incremented rate limit for IP=$ip, count=".$_SESSION[$rate_limit_key]['count'], 'token_burn_log.txt', 'INFO');
    }
}
if (!$rate_limit_exceeded): ?>
    <div class="tools-form">
        <h2>Check Token Burn</h2>
        <p>Enter the <strong>Solana Wallet Address</strong> to calculate total burned tokens (sent to burn address or burned via burn instruction).</p>
        <form id="tokenBurnForm" method="POST" action="" data-tool="token-burn">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="input-wrapper">
                <input type="text" name="walletAddress" id="walletAddress" placeholder="Enter Solana Wallet Address" required value="<?php echo isset($_POST['walletAddress']) ? htmlspecialchars($_POST['walletAddress']) : ''; ?>">
                <span class="clear-input" title="Clear input">×</span>
            </div>
            <button type="submit" class="cta-button">Check</button>
        </form>
        <div class="loader"></div>
        <p class="loading-message" style="display: none;">Processing large transaction data, please wait...</p>
    </div>
<?php endif;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['walletAddress']) && !$rate_limit_exceeded) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            log_message("token_burn: Invalid CSRF token", 'token_burn_log.txt', 'ERROR');
            throw new Exception('Invalid CSRF token');
        }
        $walletAddress = trim($_POST['walletAddress']);
        $walletAddress = preg_replace('/\s+/', '', $walletAddress);
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletAddress)) {
            log_message("token_burn: Invalid Wallet Address format", 'token_burn_log.txt', 'ERROR');
            throw new Exception('Invalid Wallet Address format');
        }
        echo "<script>document.querySelector('.loader').style.display = 'block'; document.querySelector('.loading-message').style.display = 'block';</script>";
        $cache_data = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) ?? [] : [];
        $cache_expiration = 6 * 3600; // Tăng lên 6 giờ
        $cache_key = $walletAddress;
        $cache_valid = isset($cache_data[$cache_key]) && (time() - $cache_data[$cache_key]['timestamp'] < $cache_expiration);
        log_message("token_burn: Cache valid=$cache_valid for walletAddress=$walletAddress", 'token_burn_log.txt', 'INFO');
        $total_burned = 0;
        $burned_by_token = [];
        if (!$cache_valid) {
            log_message("token_burn: Fetching transactions for walletAddress=$walletAddress", 'token_burn_log.txt', 'INFO');
            $transactions = [];
            $before = null;
            $max_transactions = 5000; // Giới hạn 5000 giao dịch
            $transaction_count = 0;
            do {
                $params = ['address' => $walletAddress];
                if ($before) $params['before'] = $before;
                $data = callAPI('transactions', $params, 'GET');
                if (isset($data['error'])) {
                    log_message("token_burn: API error: ".json_encode($data['error']), 'token_burn_log.txt', 'ERROR');
                    throw new Exception($data['error']);
                }
                $transactions = array_merge($transactions, $data);
                $transaction_count += count($data);
                $before = end($data)['signature'] ?? null;
                log_message("token_burn: Retrieved ".count($data)." transactions, total: $transaction_count", 'token_burn_log.txt', 'INFO');
                if ($transaction_count >= $max_transactions) {
                    log_message("token_burn: Reached max transaction limit ($max_transactions) for walletAddress=$walletAddress", 'token_burn_log.txt', 'WARNING');
                    break;
                }
            } while ($before && count($data) > 0);
            foreach ($transactions as $tx) {
                if (isset($tx['tokenTransfers'])) {
                    foreach ($tx['tokenTransfers'] as $transfer) {
                        if (($transfer['toUserAccount'] === $burn_address || $transfer['toTokenAccount'] === $burn_address) && $transfer['fromUserAccount'] === $walletAddress) {
                            $mint = $transfer['mint'];
                            $amount = $transfer['tokenAmount'];
                            $decimals = $transfer['rawTokenAmount']['decimals'] ?? 0;
                            $adjusted_amount = $amount / pow(10, $decimals);
                            $total_burned += $adjusted_amount;
                            $burned_by_token[$mint] = ($burned_by_token[$mint] ?? 0) + $adjusted_amount;
                            log_message("token_burn: Burn to $burn_address, mint=$mint, amount=$adjusted_amount", 'token_burn_log.txt', 'DEBUG');
                        }
                    }
                }
                if (isset($tx['instructions'])) {
                    foreach ($tx['instructions'] as $instruction) {
                        if ($instruction['programId'] === 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' && strpos($instruction['data'], 'burn') !== false) {
                            foreach ($tx['accountData'] as $account) {
                                if (isset($account['tokenBalanceChanges'])) {
                                    foreach ($account['tokenBalanceChanges'] as $change) {
                                        if ($change['userAccount'] === $walletAddress && $change['rawTokenAmount']['tokenAmount'] < 0) {
                                            $mint = $change['mint'];
                                            $amount = abs($change['rawTokenAmount']['tokenAmount']);
                                            $decimals = $change['rawTokenAmount']['decimals'];
                                            $adjusted_amount = $amount / pow(10, $decimals);
                                            $total_burned += $adjusted_amount;
                                            $burned_by_token[$mint] = ($burned_by_token[$mint] ?? 0) + $adjusted_amount;
                                            log_message("token_burn: Burn instruction, mint=$mint, amount=$adjusted_amount", 'token_burn_log.txt', 'DEBUG');
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $cache_data[$cache_key] = [
                'total_burned' => $total_burned,
                'burned_by_token' => $burned_by_token,
                'timestamp' => time()
            ];
            $fp = fopen($cache_file, 'c');
            if (flock($fp, LOCK_EX)) {
                if (!file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT))) {
                    log_message("token_burn: Failed to write to cache file", 'token_burn_log.txt', 'ERROR');
                    throw new Exception('Failed to write to cache file');
                }
                flock($fp, LOCK_UN);
            } else {
                log_message("token_burn: Failed to lock cache file", 'token_burn_log.txt', 'ERROR');
                throw new Exception('Failed to lock cache file');
            }
            fclose($fp);
            log_message("token_burn: Cache updated for walletAddress=$walletAddress", 'token_burn_log.txt', 'INFO');
        } else {
            $total_burned = $cache_data[$cache_key]['total_burned'];
            $burned_by_token = $cache_data[$cache_key]['burned_by_token'];
            log_message("token_burn: Retrieved from cache for walletAddress=$walletAddress", 'token_burn_log.txt', 'INFO');
        }
        echo "<script>document.querySelector('.loader').style.display = 'none'; document.querySelector('.loading-message').style.display = 'none';</script>";
?>
        <div class="tools-result token-burn-result">
            <h2>Total Burned Tokens</h2>
            <div class="result-summary">
                <div class="result-card">
                    <div class="token-burn-table">
                        <table>
                            <tr>
                                <th>Total Burned</th>
                                <td><?php echo number_format($total_burned, 6); ?> tokens</td>
                            </tr>
                            <tr>
                                <th>Wallet Address</th>
                                <td>
                                    <a href="https://solscan.io/address/<?php echo htmlspecialchars($walletAddress); ?>" target="_blank">
                                        <?php echo substr($walletAddress, 0, 4).'...'.substr($walletAddress, -4); ?>
                                    </a>
                                    <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($walletAddress); ?>"></i>
                                </td>
                            </tr>
                            <tr>
                                <th>Breakdown by Token</th>
                                <td>
                                    <table class="inner-table">
                                        <tr>
                                            <th>Mint Address</th>
                                            <th>Burned Amount</th>
                                        </tr>
                                        <?php foreach ($burned_by_token as $mint => $amount): ?>
                                            <tr>
                                                <td>
                                                    <a href="https://solscan.io/address/<?php echo htmlspecialchars($mint); ?>" target="_blank">
                                                        <?php echo substr(htmlspecialchars($mint), 0, 4).'...'.substr(htmlspecialchars($mint), -4); ?>
                                                    </a>
                                                    <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($mint); ?>"></i>
                                                </td>
                                                <td><?php echo number_format($amount, 6); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <?php if ($cache_valid): ?>
                <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$cache_key]['timestamp']); ?> UTC+0</p>
            <?php endif; ?>
        </div>
<?php
    } catch (Exception $e) {
        $error_msg = "Error: ".$e->getMessage();
        log_message("token_burn: Exception - $error_msg", 'token_burn_log.txt', 'ERROR');
        echo "<script>document.querySelector('.loader').style.display = 'none'; document.querySelector('.loading-message').style.display = 'none';</script>";
        echo "<div class='result-error'><p>$error_msg</p></div>";
    }
}
?>
    <div class="tools-about">
        <h2>About Check Token Burn</h2>
        <p>This tool calculates the total tokens burned by a Solana wallet address, including tokens sent to the burn address and tokens burned via burn instructions.</p>
    </div>
</div>
