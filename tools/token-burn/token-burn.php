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
    echo json_encode(['error' => 'Cannot find bootstrap.php']);
    exit;
}
require_once $bootstrap_path;

$cache_dir = TOKEN_BURN_PATH.'cache/';
$cache_file = $cache_dir.'token_burn_cache.json';
if (!file_exists($cache_file)) {
    if (file_put_contents($cache_file, json_encode([])) === false) {
        error_log("[".date('Y-m-d H:i:s')."] [ERROR] token_burn: Failed to create cache file $cache_file", 3, '/var/www/vinanetwork/public_html/tools/logs/php_errors.txt');
        echo json_encode(['error' => 'Failed to create cache file']);
        exit;
    }
    chmod($cache_file, 0664);
    error_log("[".date('Y-m-d H:i:s')."] [INFO] token_burn: Created cache file $cache_file", 3, '/var/www/vinanetwork/public_html/tools/logs/php_errors.txt');
}
if (!ensure_directory_and_file($cache_dir, $cache_file, 'token_burn_log.txt')) {
    error_log("[".date('Y-m-d H:i:s')."] [CRITICAL] token_burn: Cache setup failed for $cache_dir or $cache_file", 3, '/var/www/vinanetwork/public_html/tools/logs/php_errors.txt');
    echo json_encode(['error' => 'Cache setup failed']);
    exit;
}
log_message("token_burn: Cache setup completed, cache_dir=$cache_dir", 'token_burn_log.txt', 'INFO');

$api_helper_path = dirname(__DIR__).'/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("token_burn: tools-api.php not found at $api_helper_path", 'token_burn_log.txt', 'ERROR');
    echo json_encode(['error' => 'Server error: Missing tools-api.php']);
    exit;
}
require_once $api_helper_path;

$burn_address = '11111111111111111111111111111111';
header('Content-Type: application/json');
ob_start();
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
        echo json_encode(['error' => 'Rate limit exceeded. Please try again in a minute.']);
        exit;
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
        <div class="progress-container" style="display: none;">
            <p>Fetching transactions: <span id="progress-percentage">0%</span></p>
            <div class="progress-bar"><div class="progress-bar-fill" style="width: 0%;"></div></div>
        </div>
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
        $cache_expiration = 6 * 3600;
        $cache_key = $walletAddress;
        $cache_valid = isset($cache_data[$cache_key]) && (time() - $cache_data[$cache_key]['timestamp'] < $cache_expiration);
        log_message("token_burn: Cache valid=$cache_valid for walletAddress=$walletAddress", 'token_burn_log.txt', 'INFO');
        $total_burned = 0;
        $burned_by_token = [];
        if (!$cache_valid) {
            // Dùng session để lưu trạng thái batch
            $session_key = "token_burn:$walletAddress";
            $batch_size = 500; // Batch 500 giao dịch
            $max_transactions = 2000; // Giới hạn tổng
            $batch_data = $_SESSION[$session_key] ?? [
                'transactions_processed' => 0,
                'total_burned' => 0,
                'burned_by_token' => [],
                'before' => null,
                'last_batch_time' => time()
            ];
            if (time() - $batch_data['last_batch_time'] > 3600) {
                // Reset nếu quá 1 giờ
                $batch_data = [
                    'transactions_processed' => 0,
                    'total_burned' => 0,
                    'burned_by_token' => [],
                    'before' => null,
                    'last_batch_time' => time()
                ];
            }
            log_message("token_burn: Fetching transactions for walletAddress=$walletAddress, batch_size=$batch_size, processed={$batch_data['transactions_processed']}", 'token_burn_log.txt', 'INFO');
            $params = ['address' => $walletAddress];
            if ($batch_data['before']) $params['before'] = $batch_data['before'];
            $data = callAPI('transactions', $params, 'GET');
            if (isset($data['error'])) {
                log_message("token_burn: API error: ".json_encode($data['error']), 'token_burn_log.txt', 'ERROR');
                throw new Exception($data['error']);
            }
            $batch_transactions = $data;
            $batch_data['transactions_processed'] += count($batch_transactions);
            foreach ($batch_transactions as $tx) {
                if (isset($tx['tokenTransfers'])) {
                    foreach ($tx['tokenTransfers'] as $transfer) {
                        if (($transfer['toUserAccount'] === $burn_address || $transfer['toTokenAccount'] === $burn_address) && $transfer['fromUserAccount'] === $walletAddress) {
                            $mint = $transfer['mint'];
                            $amount = $transfer['tokenAmount'];
                            $decimals = $transfer['rawTokenAmount']['decimals'] ?? 0;
                            $adjusted_amount = $amount / pow(10, $decimals);
                            $batch_data['total_burned'] += $adjusted_amount;
                            $batch_data['burned_by_token'][$mint] = ($batch_data['burned_by_token'][$mint] ?? 0) + $adjusted_amount;
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
                                            $batch_data['total_burned'] += $adjusted_amount;
                                            $batch_data['burned_by_token'][$mint] = ($batch_data['burned_by_token'][$mint] ?? 0) + $adjusted_amount;
                                            log_message("token_burn: Burn instruction, mint=$mint, amount=$adjusted_amount", 'token_burn_log.txt', 'DEBUG');
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $batch_data['before'] = end($batch_transactions)['signature'] ?? null;
            $batch_data['last_batch_time'] = time();
            $_SESSION[$session_key] = $batch_data;
            $total_burned = $batch_data['total_burned'];
            $burned_by_token = $batch_data['burned_by_token'];
            $progress = min(100, ($batch_data['transactions_processed'] / $max_transactions) * 100);
            // Flush partial result
            if ($batch_data['transactions_processed'] < $max_transactions && $batch_data['before']) {
                echo json_encode([
                    'success' => true,
                    'partial' => true,
                    'progress' => $progress,
                    'total_burned' => number_format($total_burned, 6),
                    'burned_by_token' => $burned_by_token,
                    'transactions_processed' => $batch_data['transactions_processed'],
                    'next_before' => $batch_data['before']
                ]);
                ob_flush();
                flush();
                exit; // Chờ AJAX tiếp theo
            }
            // Hoàn tất: Lưu cache
            $cache_data[$cache_key] = [
                'total_burned' => $total_burned,
                'burned_by_token' => $burned_by_token,
                'timestamp' => time()
            ];
            $fp = fopen($cache_file, 'c');
            if (!$fp || !flock($fp, LOCK_EX)) {
                log_message("token_burn: Failed to lock cache file: $cache_file", 'token_burn_log.txt', 'ERROR');
                throw new Exception('Failed to lock cache file');
            }
            if (file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT)) === false) {
                log_message("token_burn: Failed to write to cache file: $cache_file", 'token_burn_log.txt', 'ERROR');
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new Exception('Failed to write to cache file');
            }
            flock($fp, LOCK_UN);
            fclose($fp);
            log_message("token_burn: Cache updated for walletAddress=$walletAddress", 'token_burn_log.txt', 'INFO');
            unset($_SESSION[$session_key]); // Xóa session
        } else {
            $total_burned = $cache_data[$cache_key]['total_burned'];
            $burned_by_token = $cache_data[$cache_key]['burned_by_token'];
            log_message("token_burn: Retrieved from cache for walletAddress=$walletAddress", 'token_burn_log.txt', 'INFO');
        }
        echo "<script>document.querySelector('.loader').style.display = 'none'; document.querySelector('.loading-message').style.display = 'none'; document.querySelector('.progress-container').style.display = 'none';</script>";
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
                                        <?php if (empty($burned_by_token)): ?>
                                            <tr><td colspan="2">No burned tokens found</td></tr>
                                        <?php else: ?>
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
                                        <?php endif; ?>
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
        echo "<script>document.querySelector('.loader').style.display = 'none'; document.querySelector('.loading-message').style.display = 'none'; document.querySelector('.progress-container').style.display = 'none';</script>";
        echo json_encode(['error' => $error_msg]);
        exit;
    }
}
?>
    <div class="tools-about">
        <h2>About Check Token Burn</h2>
        <p>This tool calculates the total tokens burned by a Solana wallet address, including tokens sent to the burn address and tokens burned via burn instructions.</p>
    </div>
</div>
<?php
$output = ob_get_clean();
echo json_encode(['success' => true, 'html' => $output]);
?>
