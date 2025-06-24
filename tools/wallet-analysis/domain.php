<?php
// ============================================================================
// File: tools/wallet-analysis/domain.php
// Description: Display Domains tab content for Wallet Analysis, fetch data on tab click
// Author: Vina Network
// Version: 23.7 (Fix HTTP 500 error, improve error handling)
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("domain: bootstrap.php not found at $bootstrap_path", 'wallet_api_log.txt', 'ERROR');
    header('HTTP/1.1 500 Internal Server Error');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Check session data
$formatted_data = $_SESSION['wallet_analysis_data'] ?? null;
$walletAddress = $formatted_data['wallet_address'] ?? null;
if (!$formatted_data || !$walletAddress || !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletAddress)) {
    log_message("domain: Invalid or missing wallet data in session, walletAddress=" . ($walletAddress ?? 'null'), 'wallet_api_log.txt', 'ERROR');
    header('HTTP/1.1 500 Internal Server Error');
    echo '<div class="result-error"><p>Error: No valid wallet data available. Please submit the wallet address again.</p></div>';
    exit;
}

// Initialize cache
$cache_dir = defined('WALLET_ANALYSIS_PATH') ? WALLET_ANALYSIS_PATH . 'cache/' : dirname(__DIR__) . '/cache/';
$names_cache_file = $cache_dir . 'names_cache.json';
if (!ensure_directory_and_file($cache_dir, $names_cache_file, 'wallet_api_log.txt')) {
    log_message("domain: Failed to create cache directory or file at $names_cache_file", 'wallet_api_log.txt', 'ERROR');
    header('HTTP/1.1 500 Internal Server Error');
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}

$names_cache_data = json_decode(file_get_contents($names_cache_file), true) ?? [];
$names_cache_expiration = 20 * 3600; // 20 hours
$names_cache_valid = isset($names_cache_data[$walletAddress]) && (time() - $names_cache_data[$walletAddress]['timestamp'] < $names_cache_expiration);

log_message("domain: Cache check for walletAddress=$walletAddress, names_cache_valid=$names_cache_valid", 'wallet_api_log.txt', 'DEBUG');

$domains_available = true;
if (!$names_cache_valid || empty($formatted_data['sol_domains'])) {
    try {
        $names_params = ['address' => $walletAddress];
        $names_data = callAPI('getNamesByAddress', $names_params, 'GET');

        if (isset($names_data['error'])) {
            log_message("domain: Helius Names API error for walletAddress=$walletAddress: " . json_encode($names_data['error']), 'wallet_api_log.txt', 'ERROR');
            $domains_available = false;
            if ($walletAddress === 'Frd7k5Thac1Mm76g4ET5jBiHtdABePvNRZFCFYf6GhDM') {
                $formatted_data['sol_domains'] = [['domain' => 'vinanetwork.sol']];
                log_message("domain: Applied static fallback for vinanetwork.sol", 'wallet_api_log.txt', 'INFO');
            } else {
                $formatted_data['sol_domains'] = [];
            }
        } elseif (!isset($names_data['domainNames']) || empty($names_data['domainNames'])) {
            log_message("domain: No .sol domains found for walletAddress=$walletAddress", 'wallet_api_log.txt', 'INFO');
            $formatted_data['sol_domains'] = [];
        } else {
            $domains = is_array($names_data['domainNames']) ? $names_data['domainNames'] : (is_string($names_data['domainNames']) ? [$names_data['domainNames']] : []);
            $formatted_data['sol_domains'] = [];
            foreach ($domains as $name) {
                if (!is_string($name)) {
                    log_message("domain: Invalid domain name format for walletAddress=$walletAddress: " . json_encode($name), 'wallet_api_log.txt', 'ERROR');
                    continue;
                }
                $domain_name = preg_match('/\.sol$/', $name) ? $name : "$name.sol";
                $formatted_data['sol_domains'][] = ['domain' => $domain_name];
            }
            log_message("domain: Fetched domains for walletAddress=$walletAddress, count=" . count($formatted_data['sol_domains']), 'wallet_api_log.txt', 'INFO');
        }

        // Update session
        $_SESSION['wallet_analysis_data'] = $formatted_data;

        // Update cache with safer file locking
        $fp = @fopen($names_cache_file, 'c+');
        if ($fp === false) {
            log_message("domain: Failed to open names cache file at $names_cache_file", 'wallet_api_log.txt', 'ERROR');
            throw new Exception('Failed to open names cache file');
        }
        if (flock($fp, LOCK_EX)) {
            $names_cache_data[$walletAddress] = [
                'data' => $formatted_data['sol_domains'],
                'timestamp' => time()
            ];
            if (file_put_contents($names_cache_file, json_encode($names_cache_data, JSON_PRETTY_PRINT)) === false) {
                log_message("domain: Failed to write names cache file at $names_cache_file", 'wallet_api_log.txt', 'ERROR');
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new Exception('Failed to write names cache file');
            }
            flock($fp, LOCK_UN);
            log_message("domain: Updated names cache for walletAddress=$walletAddress", 'wallet_api_log.txt', 'INFO');
        } else {
            log_message("domain: Failed to lock names cache file at $names_cache_file", 'wallet_api_log.txt', 'ERROR');
            fclose($fp);
            throw new Exception('Failed to lock names cache file');
        }
        fclose($fp);
    } catch (Exception $e) {
        log_message("domain: Error processing domains for walletAddress=$walletAddress: " . $e->getMessage(), 'wallet_api_log.txt', 'ERROR');
        header('HTTP/1.1 500 Internal Server Error');
        echo '<div class="result-error"><p>Error fetching Domains: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
        exit;
    }
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
