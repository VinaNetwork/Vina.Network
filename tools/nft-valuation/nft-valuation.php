<?php
// ============================================================================
// File: tools/nft-valuation/nft-valuation.php
// Description: Check real-time market valuation for Solana NFT Collections using MagicEden API.
// Created by: Vina Network
// ============================================================================

// Disable display of errors in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Define constants to mark script entry
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Load bootstrap dependencies
$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-valuation: bootstrap.php not found at $bootstrap_path", 'nft_valuation_log.txt', 'ERROR');
    die('Error: bootstrap.php not found');
}
require_once $bootstrap_path;

// Start session and configure error logging
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
    log_message("nft-valuation: Reset rate limit for IP=$ip, count=1", 'nft_valuation_log.txt');
} elseif ($rate_limit_count >= 5) {
    log_message("nft-valuation: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'nft_valuation_log.txt', 'ERROR');
    die("<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>");
} else {
    $_SESSION[$rate_limit_key]['count']++;
    log_message("nft-valuation: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'nft_valuation_log.txt');
}

// Include tools API helper
$api_helper_path = __DIR__ . '/../tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-valuation: tools-api.php not found at $api_helper_path", 'nft_valuation_log.txt', 'ERROR');
    die('Internal Server Error: Missing tools-api.php');
}
include $api_helper_path;

// MagicEden API helper function
function callMagicEdenAPI($endpoint) {
    $url = "https://api-mainnet.magiceden.dev/v2/collections/$endpoint";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        log_message("nft-valuation: MagicEden API error - Endpoint: $endpoint, HTTP: $http_code, Response: $response", 'nft_valuation_log.txt', 'ERROR');
        throw new Exception("MagicEden API error: HTTP $http_code");
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("nft-valuation: MagicEden JSON decode error - Endpoint: $endpoint, Response: $response", 'nft_valuation_log.txt', 'ERROR');
        throw new Exception("Invalid MagicEden API response");
    }

    log_message("nft-valuation: MagicEden API success - Endpoint: $endpoint, Response: " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 'nft_valuation_log.txt');
    return $data;
}

// Set up page variables
$root_path = '../../';
$page_title = 'Check NFT Valuation - Vina Network';
$page_description = 'Check real-time market valuation for a Solana NFT Collection.';
$page_css = ['../../css/vina.css', '../tools.css'];
include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';
?>

<!-- Render input form for NFT Collection address -->
<div class="t-6 nft-valuation-content">
    <div class="t-7">
        <h2>Check NFT Valuation</h2>
        <p>Enter the <strong>NFT Collection Address</strong> (Collection ID) to check its real-time market valuation. E.g: Find this address on MagicEden under "Details" > "On-chain Collection".</p>
        <form id="nftValuationForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="text" name="mintAddress" id="mintAddressValuation" placeholder="Enter NFT Collection Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <button type="submit" class="cta-button">Check Valuation</button>
        </form>
        <div class="loader"></div>
    </div>

    <?php
    // Handle form submission and valuation fetching
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            // Validate CSRF token
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                log_message("nft-valuation: Invalid CSRF token for mintAddress=" . ($_POST['mintAddress'] ?? 'unknown'), 'nft_valuation_log.txt', 'ERROR');
                throw new Exception("Invalid CSRF token. Please try again.");
            }

            $mintAddress = trim($_POST['mintAddress']);
            log_message("nft-valuation: Form submitted with mintAddress=$mintAddress", 'nft_valuation_log.txt');

            // Validate address format (base58, 32–44 characters)
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                log_message("nft-valuation: Invalid mintAddress format: $mintAddress", 'nft_valuation_log.txt', 'ERROR');
                throw new Exception("Invalid collection address. Please enter a valid Solana collection address (32-44 characters, base58).");
            }

            // Step 1: Get collection_symbol from Helius API
            $params = [
                'groupKey' => 'collection',
                'groupValue' => $mintAddress,
                'page' => 1,
                'limit' => 1
            ];
            $helius_data = callAPI('getAssetsByGroup', $params, 'POST');

            if (isset($helius_data['error'])) {
                $errorMessage = is_array($helius_data['error']) && isset($helius_data['error']['message']) ? $helius_data['error']['message'] : json_encode($helius_data['error']);
                log_message("nft-valuation: Helius API error for mintAddress=$mintAddress: $errorMessage", 'nft_valuation_log.txt', 'ERROR');
                throw new Exception("Helius API error: $errorMessage");
            }

            if (!isset($helius_data['result']['items'][0]['content']['metadata']['collection']['name'])) {
                log_message("nft-valuation: No collection name found for mintAddress=$mintAddress", 'nft_valuation_log.txt', 'ERROR');
                throw new Exception("Collection not found. Please check the collection address.");
            }

            $collection_symbol = strtolower(str_replace(' ', '_', $helius_data['result']['items'][0]['content']['metadata']['collection']['name']));
            log_message("nft-valuation: Found collection_symbol=$collection_symbol for mintAddress=$mintAddress", 'nft_valuation_log.txt');

            // Step 2: Get valuation data from MagicEden API
            $stats = callMagicEdenAPI("$collection_symbol/stats");
            $activities = callMagicEdenAPI("$collection_symbol/activities?type=sale&limit=100");

            // Convert floor price (lamports to SOL)
            $floor_price = isset($stats['floorPrice']) ? $stats['floorPrice'] / 1000000000 : 'N/A';

            // Get last sale price (first sale within 24h)
            $last_sale_price = '0.00';
            $volume_24h = 0.00;
            $now = time();
            foreach ($activities as $activity) {
                if ($activity['type'] === 'sale' && isset($activity['blockTime']) && ($now - $activity['blockTime']) <= 86400) {
                    if ($last_sale_price === '0.00') {
                        $last_sale_price = $activity['price'];
                    }
                    $volume_24h += $activity['price'];
                }
            }

            log_message("nft-valuation: Retrieved floor_price=$floor_price, last_sale_price=$last_sale_price, volume_24h=$volume_24h for collection_symbol=$collection_symbol", 'nft_valuation_log.txt');
            ?>

            <!-- Display valuation table -->
            <div class="result-section">
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>Floor Price (SOL)</th>
                            <th>Last Sale Price (SOL)</th>
                            <th>24h Trading Volume (SOL)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo is_numeric($floor_price) ? number_format($floor_price, 2) : $floor_price; ?></td>
                            <td><?php echo number_format($last_sale_price, 2); ?></td>
                            <td><?php echo number_format($volume_24h, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
                <p>Collection: <?php echo htmlspecialchars($collection_symbol); ?></p>
            </div>
            <?php
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            log_message("nft-valuation: Exception - $error_msg", 'nft_valuation_log.txt', 'ERROR');
            echo "<div class='result-error'><p>$error_msg. Please try again.</p></div>";
        }
    }
    ?>

    <!-- Informational block -->
    <div class="t-9">
        <h2>About NFT Valuation Checker</h2>
        <p>
            The NFT Valuation Tool allows you to view real-time market valuation for a specific Solana NFT Collection by entering its on-chain collection address.
            This tool provides key financial metrics such as floor price, last sale price, and 24-hour trading volume, useful for NFT creators, collectors, or investors.
        </p>
    </div>
</div>

<?php
// Output footer
ob_start();
include $root_path . 'include/footer.php';
$footer_output = ob_get_clean();
log_message("nft-valuation: Footer output length: " . strlen($footer_output), 'nft_valuation_log.txt');
echo $footer_output;
?>
