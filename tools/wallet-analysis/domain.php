<?php
// ============================================================================
// File: tools/wallet-analysis/domain.php
// Description: Display Domains tab content for Wallet Analysis, fetch data on tab click
// Author: Vina Network
// Version: 23.5 (Lazy-load Domains)
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("domain: bootstrap.php not found at $bootstrap_path", 'wallet_api_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

$formatted_data = $_SESSION['wallet_analysis_data'] ?? null;
$walletAddress = $formatted_data['wallet_address'] ?? null;
if (!$formatted_data || !$walletAddress) {
    echo "<div class='result-error'><p>Error: No wallet data available.</p></div>";
    log_message("domain: No wallet data or address in session", 'wallet_api_log.txt', 'ERROR');
    exit;
}

$cache_dir = WALLET_ANALYSIS_PATH . 'cache/';
$names_cache_file = $cache_dir . 'names_cache.json';
$names_cache_data = json_decode(file_get_contents($names_cache_file), true) ?? [];
$names_cache_expiration = 20 * 3600; // 20 hours
$names_cache_valid = isset($names_cache_data[$walletAddress]) && (time() - $names_cache_data[$walletAddress]['timestamp'] < $names_cache_expiration);

log_message("domain: Names cache check for walletAddress=$walletAddress, names_cache_valid=$names_cache_valid", 'wallet_api_log.txt', 'DEBUG');

$domains_available = true;
if (!$names_cache_valid || empty($formatted_data['sol_domains'])) {
    try {
        $names_params = ['address' => $walletAddress];
        $names_data = callAPI('getNamesByAddress', $names_params, 'GET');

        if (isset($names_data['error'])) {
            log_message("domain: Helius Names API error: " . json_encode($names_data), 'wallet_api_log.txt', 'ERROR');
            $domains_available = false;
            if ($walletAddress === 'Frd7k5Thac1Mm76g4ET5jBiHtdABePvNRZFCFYf6GhDM') {
                $formatted_data['sol_domains'] = [['domain' => 'vinanetwork.sol']];
                log_message("domain: Applied static fallback for vinanetwork.sol", 'wallet_api_log.txt', 'INFO');
            } else {
                $formatted_data['sol_domains'] = [];
            }
        } elseif (empty($names_data['domainNames'])) {
            log_message("domain: No .sol domains found for walletAddress=$walletAddress", 'wallet_api_log.txt', 'INFO');
            $formatted_data['sol_domains'] = [];
        } else {
            $domains = is_array($names_data['domainNames']) ? $names_data['domainNames'] : [$names_data['domainNames']];
            $formatted_data['sol_domains'] = [];
            foreach ($domains as $name) {
                $domain_name = preg_match('/\.sol$/', $name) ? $name : "$name.sol";
                $formatted_data['sol_domains'][] = ['domain' => $domain_name];
            }
            log_message("domain: Helius names fetched, sol_domains=" . json_encode($formatted_data['sol_domains']), 'wallet_api_log.txt', 'INFO');
        }

        // Update session and names cache
        $_SESSION['wallet_analysis_data'] = $formatted_data;
        $names_cache_data[$walletAddress] = [
            'data' => $formatted_data['sol_domains'],
            'timestamp' => time()
        ];
        $fp = fopen($names_cache_file, 'c+');
        if (flock($fp, LOCK_EX)) {
            if (file_put_contents($names_cache_file, json_encode($names_cache_data, JSON_PRETTY_PRINT)) === false) {
                log_message("domain: Failed to write names cache file at $names_cache_file", 'wallet_api_log.txt', 'ERROR');
                throw new Exception('Failed to write names cache file');
            }
            flock($fp, LOCK_UN);
            log_message("domain: Cached names data for walletAddress=$walletAddress", 'wallet_api_log.txt', 'INFO');
        } else {
            log_message("domain: Failed to lock names cache file at $names_cache_file", 'wallet_api_log.txt', 'ERROR');
            throw new Exception('Failed to lock names cache file');
        }
        fclose($fp);
    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error fetching Domains: " . htmlspecialchars($e->getMessage()) . "</p></div>";
        log_message("domain: Error fetching Domains for walletAddress=$walletAddress: " . $e->getMessage(), 'wallet_api_log.txt', 'ERROR');
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
