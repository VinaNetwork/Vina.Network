<?php
// ============================================================================
// File: tools/nft-valuation/nft-valuation.php
// Description: Display floor price, last sale price, and 24h volume for a Solana NFT collection using Tensor API.
// Created by: Vina Network
// ============================================================================

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

require_once __DIR__ . '/../bootstrap.php';
session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

$root_path = '../../';
$page_title = 'Check NFT Valuation - Vina Network';
$page_description = 'Check floor price, last sale price, and 24h volume for a Solana NFT collection using Tensor API.';
$page_css = ['../../css/vina.css', '../tools.css'];

include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';

$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-valuation: tools-api.php not found", 'nft_valuation_log.txt', 'ERROR');
    die('Internal Server Error: Missing tools-api.php');
}
include $api_helper_path;
?>
<div class="t-6 nft-valuation-content">
    <div class="t-7">
        <h2>Check NFT Valuation</h2>
        <p>Enter the <strong>Collection Slug</strong> (e.g., <code>okay_bears</code>) to view floor price, last sale price, and 24h volume. Slug can be found from Tensor marketplace URLs.</p>
        <form id="nftValuationForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="text" name="collectionSlug" id="collectionSlug" placeholder="Enter Collection Slug" required value="<?php echo isset($_POST['collectionSlug']) ? htmlspecialchars($_POST['collectionSlug']) : ''; ?>">
            <button type="submit" class="cta-button">Check Valuation</button>
        </form>
        <div class="loader"></div>
    </div>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collectionSlug'])) {
        try {
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                throw new Exception("Invalid CSRF token. Please try again.");
            }

            $slug = trim($_POST['collectionSlug']);
            if (!preg_match('/^[a-z0-9\-_]{2,50}$/', $slug)) {
                throw new Exception("Invalid slug format. Only lowercase letters, numbers, dashes, and underscores allowed.");
            }

            $url = "https://api.tensor.trade/v1/collections/$slug";
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => "Accept: application/json\r\n"
                ]
            ];
            $context = stream_context_create($options);
            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                throw new Exception("Failed to fetch data from Tensor API.");
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new Exception("Invalid response from Tensor API.");
            }

            $floor = isset($data['floor_price']) ? $data['floor_price'] / 1_000_000_000 : null;
            $last_sale = isset($data['last_sale_price']) ? $data['last_sale_price'] / 1_000_000_000 : null;
            $volume = isset($data['volume_1d']) ? $data['volume_1d'] / 1_000_000_000 : null;

            if ($floor === null && $last_sale === null && $volume === null) {
                throw new Exception("No valuation data available for this collection.");
            }
    ?>
    <div class="result-section">
        <div class="holders-summary">
            <div class="summary-card">
                <?php if ($floor !== null): ?>
                <div class="summary-item">
                    <i class="fas fa-chart-line"></i>
                    <p>Floor Price</p>
                    <h3><?php echo number_format($floor, 2); ?> ◎</h3>
                </div>
                <?php endif; ?>
                <?php if ($last_sale !== null): ?>
                <div class="summary-item">
                    <i class="fas fa-tags"></i>
                    <p>Last Sale Price</p>
                    <h3><?php echo number_format($last_sale, 2); ?> ◎</h3>
                </div>
                <?php endif; ?>
                <?php if ($volume !== null): ?>
                <div class="summary-item">
                    <i class="fas fa-coins"></i>
                    <p>24h Volume</p>
                    <h3><?php echo number_format($volume, 2); ?> ◎</h3>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
        } catch (Exception $e) {
            $msg = $e->getMessage();
            log_message("nft-valuation: Exception - $msg", 'nft_valuation_log.txt', 'ERROR');
            echo "<div class='result-error'><p>Error: $msg</p></div>";
        }
    }
    ?>
    <div class="t-9">
        <h2>About NFT Valuation Tool</h2>
        <p>
            This tool queries Tensor API to retrieve live market data for any Solana NFT collection by slug. Use it to monitor floor prices, last transactions, and trading activity over 24 hours.
        </p>
    </div>
</div>
<?php
ob_start();
include $root_path . 'include/footer.php';
$footer_output = ob_get_clean();
log_message("nft-valuation: Footer output length: " . strlen($footer_output), 'nft_valuation_log.txt');
echo $footer_output;
?>
