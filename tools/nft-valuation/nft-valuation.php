<?php
// =============================================================================
// File: tools/nft-valuation/nft-valuation.php
// Description: Check NFT Valuation for a given Solana NFT Collection Address.
// Created by: Vina Network
// Updated: 17/06/2025 - Fix log path to /var/www/vinanetwork/public_html/tools/
// =============================================================================

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    error_log("nft-valuation: bootstrap.php not found at $bootstrap_path");
    die('Error: bootstrap.php not found');
}
require_once $bootstrap_path;

session_start();
ini_set('log_errors', true);
ini_set('error_log', '/var/www/vinanetwork/public_html/tools/php_errors.txt');

$api_helper_path = __DIR__ . '/../tools-api.php';
if (!file_exists($api_helper_path)) {
    error_log("nft-valuation: tools-api.php not found at $api_helper_path");
    die('Internal Server Error: Missing tools-api.php');
}
include $api_helper_path;

// Define log path
define('LOG_PATH', '/var/www/vinanetwork/public_html/tools/');

// Rate limiting: 5 requests per minute per IP
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "rate_limit_valuation:$ip";
$rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['count'] : 0;
$rate_limit_time = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['time'] : 0;
if (time() - $rate_limit_time > 60) {
    $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
    log_message("nft-valuation: Reset rate limit for IP=$ip, count=1", LOG_PATH . 'nft_valuation_log.txt');
} elseif ($rate_limit_count >= 5) {
    log_message("nft-valuation: Rate limit exceeded for IP=$ip, count=$rate_limit_count", LOG_PATH . 'nft_valuation_log.txt', 'ERROR');
    die("<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>");
} else {
    $_SESSION[$rate_limit_key]['count']++;
    log_message("nft-valuation: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], LOG_PATH . 'nft_valuation_log.txt');
}

// Fetch JSON from URI (for Tensor API)
function fetchJsonUri($uri) {
    $ch = curl_init($uri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    log_message("nft-valuation: cURL request - URL: $uri, HTTP: $http_code, Response: " . substr($response, 0, 256), LOG_PATH . 'nft_valuation_log.txt');
    if ($http_code >= 400) {
        log_message("nft-valuation: cURL error ($http_code) - URL: $uri, Response: $response", LOG_PATH . 'nft_valuation_log.txt', 'ERROR');
        return null;
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("nft-valuation: JSON decode error - URL: $uri, Response: $response", LOG_PATH . 'nft_valuation_log.txt', 'ERROR');
        return null;
    }
    return $data;
}

$root_path = '../../';
$page_title = 'Check NFT Valuation - Vina Network';
$page_description = 'View NFT valuation stats using collection address.';
$page_css = ['../../css/vina.css', '../tools.css'];
include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';
?>

<div class="t-6 nft-valuation-content">
    <div class="t-7">
        <h2>Check NFT Valuation</h2>
        <p>Enter the <strong>NFT Collection Address</strong> (Collection ID) to get valuation data. You can find it on Magic Eden under "Details" > "On-chain Collection".</p>
        <form id="nftValuationForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="text" name="mintAddress" id="mintAddressValuation" placeholder="Enter NFT Collection Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <button type="submit" class="cta-button">Check Valuation</button>
        </form>
        <div class="loader"></div>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                log_message("nft-valuation: Invalid CSRF token for mintAddress=" . ($_POST['mintAddress'] ?? 'unknown'), LOG_PATH . 'nft_valuation_log.txt', 'ERROR');
                throw new Exception("Invalid CSRF token. Please try again.");
            }

            $mintAddress = trim($_POST['mintAddress']);
            log_message("nft-valuation: Form submitted with mintAddress=$mintAddress", LOG_PATH . 'nft_valuation_log.txt');

            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                log_message("nft-valuation: Invalid mintAddress format: $mintAddress", LOG_PATH . 'nft_valuation_log.txt', 'ERROR');
                throw new Exception("Invalid collection address. Please enter a valid Solana address (32-44 characters, base58).");
            }

            // Step 1: Get collection slug via Helius
            $heliusParams = ['id' => $mintAddress];
            $heliusResp = callAPI('getAsset', $heliusParams, 'POST');
            log_message("nft-valuation: Helius getAsset response for mintAddress=$mintAddress: " . json_encode($heliusResp, JSON_UNESCAPED_SLASHES), LOG_PATH . 'nft_valuation_log.txt');

            if (isset($heliusResp['error']) || !isset($heliusResp['result']['grouping'][0]['group_value'])) {
                $errorMessage = $heliusResp['error']['message'] ?? 'No collection slug found';
                log_message("nft-valuation: Helius getAsset error for mintAddress=$mintAddress: $errorMessage", LOG_PATH . 'nft_valuation_log.txt', 'ERROR');
                throw new Exception("Failed to retrieve collection slug from Helius: $errorMessage");
            }

            $slug = $heliusResp['result']['grouping'][0]['group_value'];
            $collection_name = $heliusResp['result']['content']['metadata']['name'] ?? 'Unknown';

            // Step 2: Get valuation data from Tensor API
            $tensorUrl = "https://api.tensor.trade/v1/collections/" . urlencode($slug);
            $tensorData = fetchJsonUri($tensorUrl);
            if (!$tensorData || isset($tensorData['error'])) {
                $errorMessage = $tensorData['error']['message'] ?? 'Failed to fetch data';
                log_message("nft-valuation: Tensor API error for slug=$slug: $errorMessage", LOG_PATH . 'nft_valuation_log.txt', 'ERROR');
                throw new Exception("Tensor API error: $errorMessage");
            }

            $floor = $tensorData['floorPrice'] ?? null;
            $last = isset($tensorData['listedPrices']) && is_array($tensorData['listedPrices']) && !empty($tensorData['listedPrices']) ? $tensorData['listedPrices'][0] : null;
            $vol24h = $tensorData['volume24h'] ?? null;

            if (!$floor && !$last && !$vol24h) {
                log_message("nft-valuation: No valuation data found for slug=$slug, mintAddress=$mintAddress", LOG_PATH . 'nft_valuation_log.txt', 'WARNING');
                throw new Exception("No valuation data found for this collection.");
            }

            ?>
            <div class="result-section">
                <h3>Collection: <?php echo htmlspecialchars($collection_name); ?></h3>
                <p>Collection Address: <?php echo htmlspecialchars($mintAddress); ?></p>
                <p><a href="https://magiceden.io/marketplace/<?php echo urlencode($slug); ?>" target="_blank">View on Magic Eden</a> | <a href="https://tensor.trade/collection/<?php echo urlencode($slug); ?>" target="_blank">View on Tensor</a></p>
                <div class="holders-summary">
                    <div class="summary-card">
                        <div class="summary-item">
                            <i class="fas fa-tag"></i>
                            <p>Floor Price</p>
                            <h3><?php echo $floor ? number_format($floor / 1e9, 2) : 'N/A'; ?> ◎</h3>
                        </div>
                        <div class="summary-item">
                            <i class="fas fa-clock"></i>
                            <p>Last Listed</p>
                            <h3><?php echo $last ? number_format($last / 1e9, 2) : 'N/A'; ?> ◎</h3>
                        </div>
                        <div class="summary-item">
                            <i class="fas fa-chart-line"></i>
                            <p>Volume (24h)</p>
                            <h3><?php echo $vol24h ? number_format($vol24h / 1e9, 2) : 'N/A'; ?> ◎</h3>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            log_message("nft-valuation: Exception - $error_msg", LOG_PATH . 'nft_valuation_log.txt', 'ERROR');
            echo "<div class='result-error'><p>$error_msg. Please try again.</p></div>";
        }
    }
    ?>
</div>

<?php
include $root_path . 'include/footer.php';
?>
