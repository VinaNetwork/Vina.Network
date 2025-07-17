<?php
// File: tools/token-burn/fetch-transactions.php
// Description: Fetch and process transactions in batches for token burn calculation.
// Created by: Vina Network

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/tools-api.php';

header('Content-Type: application/json');

$burn_address = '11111111111111111111111111111111';
$cache_dir = TOKEN_BURN_PATH.'cache/';
$cache_file = $cache_dir.'token_burn_cache.json';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['walletAddress'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        log_message("fetch_transactions: Invalid CSRF token", 'token_burn_log.txt', 'ERROR');
        throw new Exception('Invalid CSRF token');
    }
    $walletAddress = trim($_POST['walletAddress']);
    $walletAddress = preg_replace('/\s+/', '', $walletAddress);
    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletAddress)) {
        log_message("fetch_transactions: Invalid Wallet Address format", 'token_burn_log.txt', 'ERROR');
        throw new Exception('Invalid Wallet Address format');
    }
    $before = $_POST['before'] ?? null;
    $batch_size = 500;
    $max_transactions = 2000;
    $session_key = "token_burn:$walletAddress";
    $batch_data = $_SESSION[$session_key] ?? [
        'transactions_processed' => 0,
        'total_burned' => 0,
        'burned_by_token' => [],
        'before' => null,
        'last_batch_time' => time()
    ];
    if (time() - $batch_data['last_batch_time'] > 3600) {
        $batch_data = [
            'transactions_processed' => 0,
            'total_burned' => 0,
            'burned_by_token' => [],
            'before' => null,
            'last_batch_time' => time()
        ];
    }
    if ($batch_data['transactions_processed'] >= $max_transactions) {
        echo json_encode(['error' => 'Max transaction limit reached']);
        exit;
    }
    log_message("fetch_transactions: Fetching batch for walletAddress=$walletAddress, before=$before", 'token_burn_log.txt', 'INFO');
    $params = ['address' => $walletAddress];
    if ($before) $params['before'] = $before;
    $data = callAPI('transactions', $params, 'GET');
    if (isset($data['error'])) {
        log_message("fetch_transactions: API error: ".json_encode($data['error']), 'token_burn_log.txt', 'ERROR');
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
                    log_message("fetch_transactions: Burn to $burn_address, mint=$mint, amount=$adjusted_amount", 'token_burn_log.txt', 'DEBUG');
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
                                    log_message("fetch_transactions: Burn instruction, mint=$mint, amount=$adjusted_amount", 'token_burn_log.txt', 'DEBUG');
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
    $progress = min(100, ($batch_data['transactions_processed'] / $max_transactions) * 100);
    if ($batch_data['transactions_processed'] >= $max_transactions || !$batch_data['before']) {
        // Hoàn tất: Lưu cache
        $cache_data = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) ?? [] : [];
        $cache_data[$walletAddress] = [
            'total_burned' => $batch_data['total_burned'],
            'burned_by_token' => $batch_data['burned_by_token'],
            'timestamp' => time()
        ];
        $fp = fopen($cache_file, 'c');
        if (!$fp || !flock($fp, LOCK_EX)) {
            log_message("fetch_transactions: Failed to lock cache file: $cache_file", 'token_burn_log.txt', 'ERROR');
            throw new Exception('Failed to lock cache file');
        }
        if (file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT)) === false) {
            log_message("fetch_transactions: Failed to write to cache file: $cache_file", 'token_burn_log.txt', 'ERROR');
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new Exception('Failed to write to cache file');
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        log_message("fetch_transactions: Cache updated for walletAddress=$walletAddress", 'token_burn_log.txt', 'INFO');
        unset($_SESSION[$session_key]);
        echo json_encode([
            'success' => true,
            'complete' => true,
            'total_burned' => number_format($batch_data['total_burned'], 6),
            'burned_by_token' => $batch_data['burned_by_token'],
            'transactions_processed' => $batch_data['transactions_processed']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'partial' => true,
            'progress' => $progress,
            'total_burned' => number_format($batch_data['total_burned'], 6),
            'burned_by_token' => $batch_data['burned_by_token'],
            'transactions_processed' => $batch_data['transactions_processed'],
            'next_before' => $batch_data['before']
        ]);
    }
} catch (Exception $e) {
    log_message("fetch_transactions: Exception - ".$e->getMessage(), 'token_burn_log.txt', 'ERROR');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
