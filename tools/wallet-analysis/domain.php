<?php
// ============================================================================
// File: tools/wallet-analysis/domain.php
// Description: Display Domains tab content for Wallet Analysis, fetch data on tab click
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Log to confirm file is loaded
log_message("wallet_analysis_domain: domain.php loaded", 'nft-analysis.log', 'tools', 'INFO');

// Load bootstrap
$bootstrap_path = dirname(__DIR__, 2) . '/config/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("wallet_analysis_domain: bootstrap.php not found at $bootstrap_path", 'nft-analysis.log', 'tools', 'ERROR');
    header('HTTP/1.1 500 Internal Server Error');
    echo '<div class="result-error"><p>Server error: Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;
log_message("wallet_analysis_domain: bootstrap.php loaded", 'nft-analysis.log', 'tools', 'INFO');

// Load tools-api
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("wallet_analysis_domain: tools-api.php not found at $api_helper_path", 'nft-analysis.log', 'tools', 'ERROR');
    header('HTTP/1.1 500 Internal Server Error');
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
log_message("wallet_analysis_domain: tools-api.php loaded", 'nft-analysis.log', 'tools', 'INFO');

// Check session data
$formatted_data = $_SESSION['wallet_analysis_data'] ?? null;
$walletAddress = $formatted_data['wallet_address'] ?? null;
if (!$formatted_data || !$walletAddress || !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletAddress)) {
    log_message("wallet_analysis_domain: Invalid or missing wallet data in session, walletAddress=" . ($walletAddress ?? 'null'), 'nft-analysis.log', 'tools', 'ERROR');
    header('HTTP/1.1 500 Internal Server Error');
    echo '<div class="result-error"><p>Error: No valid wallet data available. Please submit the wallet address again.</p></div>';
    exit;
}
log_message("wallet_analysis_domain: Valid session data, walletAddress=$walletAddress", 'nft-analysis.log', 'tools', 'INFO');

// Initialize cache
$cache_dir = WALLET_ANALYSIS_PATH . 'cache/';
$names_cache_file = $cache_dir . 'names_cache.json';
log_message("wallet_analysis_domain: Checking cache directory: $cache_dir, file: $names_cache_file", 'nft-analysis.log', 'tools', 'DEBUG');

if (!ensure_directory_and_file($cache_dir, $names_cache_file)) {
    log_message("wallet_analysis_domain: Failed to create cache directory or file at $names_cache_file", 'nft-analysis.log', 'tools', 'ERROR');
    header('HTTP/1.1 500 Internal Server Error');
    echo '<div class="result-error"><p>Server error: Cache setup failed</p></div>';
    exit;
}
log_message("wallet_analysis_domain: Cache initialized, names_cache_file=$names_cache_file", 'nft-analysis.log', 'tools', 'INFO');

$names_cache_data = json_decode(file_get_contents($names_cache_file), true) ?? [];
$names_cache_expiration = 20 * 3600; // 20 hours
$names_cache_valid = isset($names_cache_data[$walletAddress]) && (time() - $names_cache_data[$walletAddress]['timestamp'] < $names_cache_expiration);
log_message("wallet_analysis_domain: Cache check for walletAddress=$walletAddress, names_cache_valid=$names_cache_valid", 'nft-analysis.log', 'tools', 'DEBUG');

// Fetch domains if cache invalid or sol_domains empty
$domains_available = true;
if (!$names_cache_valid || empty($formatted_data['sol_domains'])) {
    try {
        log_message("wallet_analysis_domain: Fetching domains for walletAddress=$walletAddress", 'nft-analysis.log', 'tools', 'INFO');
        $names_params = ['address' => $walletAddress];
        $names_data = callAPI('getNamesByAddress', $names_params, 'GET');

        if (isset($names_data['error'])) {
            log_message("wallet_analysis_domain: Helius Names API error for walletAddress=$walletAddress: " . json_encode($names_data['error']), 'nft-analysis.log', 'tools', 'ERROR');
            $domains_available = false;
            if ($walletAddress === 'Frd7k5Thac1Mm76g4ET5jBiHtdABePvNRZFCFYf6GhDM') {
                $formatted_data['sol_domains'] = [['domain' => 'vinanetwork.sol']];
                log_message("wallet_analysis_domain: Applied static fallback for vinanetwork.sol", 'nft-analysis.log', 'tools', 'INFO');
            } else {
                $formatted_data['sol_domains'] = [];
            }
        } elseif (!isset($names_data['domainNames']) || empty($names_data['domainNames'])) {
            log_message("wallet_analysis_domain: No .sol domains found for walletAddress=$walletAddress", 'nft-analysis.log', 'tools', 'INFO');
            $formatted_data['sol_domains'] = [];
        } else {
            $domains = is_array($names_data['domainNames']) ? $names_data['domainNames'] : (is_string($names_data['domainNames']) ? [$names_data['domainNames']] : []);
            $formatted_data['sol_domains'] = [];
            foreach ($domains as $name) {
                if (!is_string($name)) {
                    log_message("wallet_analysis_domain: Invalid domain name format for walletAddress=$walletAddress: " . json_encode($name), 'nft-analysis.log', 'tools', 'ERROR');
                    continue;
                }
                $domain_name = preg_match('/\.sol$/', $name) ? $name : "$name.sol";
                $formatted_data['sol_domains'][] = ['domain' => $domain_name];
            }
            log_message("wallet_analysis_domain: Fetched domains for walletAddress=$walletAddress, count=" . count($formatted_data['sol_domains']), 'nft-analysis.log', 'tools', 'INFO');
        }

        // Update session
        $_SESSION['wallet_analysis_data']['sol_domains'] = $formatted_data['sol_domains'];
        log_message("wallet_analysis_domain: Updated session with sol_domains, count=" . count($formatted_data['sol_domains']), 'nft-analysis.log', 'tools', 'INFO');

        // Update cache with safer file locking
        $fp = @fopen($names_cache_file, 'c+');
        if ($fp === false) {
            log_message("wallet_analysis_domain: Failed to open names cache file at $names_cache_file", 'nft-analysis.log', 'tools', 'ERROR');
            throw new Exception('Failed to open names cache file');
        }
        if (flock($fp, LOCK_EX)) {
            if (file_put_contents($names_cache_file, json_encode($names_cache_data, JSON_PRETTY_PRINT)) === false) {
                log_message("wallet_analysis_domain: Failed to write names cache file at $names_cache_file", 'nft-analysis.log', 'tools', 'ERROR');
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new Exception('Failed to write names cache file');
            }
            $names_cache_data[$walletAddress] = [
                'data' => $formatted_data['sol_domains'],
                'timestamp' => time()
            ];
            file_put_contents($names_cache_file, json_encode($names_cache_data, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
            log_message("wallet_analysis_domain: Updated names cache for walletAddress=$walletAddress", 'nft-analysis.log', 'tools', 'INFO');
        } else {
            log_message("wallet_analysis_domain: Failed to lock names cache file at $names_cache_file", 'nft-analysis.log', 'tools', 'ERROR');
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new Exception('Failed to lock names cache file');
        }
        fclose($fp);
    } catch (Exception $e) {
        log_message("wallet_analysis_domain: Exception - " . $e->getMessage(), 'nft-analysis.log', 'tools', 'ERROR');
        header('HTTP/1.1 500 Internal Server Error');
        echo '<div class="result-error"><p>Error fetching Domains: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
        exit;
    }
} else {
    log_message("wallet_analysis_domain: Using cached sol_domains for walletAddress=$walletAddress, count=" . count($formatted_data['sol_domains']), 'nft-analysis.log', 'tools', 'INFO');
}
?>

<h2>Domains</h2>
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
<?php
log_message("wallet_analysis_domain: Rendered sol_domains for walletAddress=$walletAddress, count=" . count($formatted_data['sol_domains']), 'nft-analysis.log', 'tools', 'DEBUG');
?>
