<?php
// ============================================================================
// File: tools/nft-valuation/nft-valuation.php
// Description: Check floor price, last sale, and 24h volume of a Solana NFT Collection.
// Created by: Vina Network
// ============================================================================

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    die('Error: bootstrap.php not found');
}
require_once $bootstrap_path;

session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

// Page metadata
$root_path = '../../';
$page_title = 'Check NFT Valuation - Vina Network';
$page_description = 'View floor price, last sale price, and 24h volume of a Solana NFT collection.';
$page_css = ['../../css/vina.css', '../tools.css'];

include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';

// Include tools API
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-valuation: tools-api.php missing", 'nft_valuation_log.txt', 'ERROR');
    die('Internal Server Error');
}
include $api_helper_path;

?>
<div class="t-6 nft-valuation-content">
    <div class="t-7">
        <h2>Check NFT Valuation</h2>
        <p>Enter the <strong>NFT Collection Address</strong> (Collection ID) to view floor price, last sale, and 24h volume. Find this on MagicEden under "Details" > "On-chain Collection".</p>
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
                throw new Exception("Invalid CSRF token");
            }

            $mintAddress = trim($_POST['mintAddress']);
            log_message("nft-valuation: Submitted mintAddress=$mintAddress", 'nft_valuation_log.txt');

            // Validate format
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                throw new Exception("Invalid Solana collection address (must be base58, 32–44 chars)");
            }

            // Helius API params
            $params = ['id' => $mintAddress];
            $valuation_data = callAPI('getCollectionStats', $params, 'POST'); // Hypothetical endpoint
            log_message("nft-valuation: API response: " . json_encode($valuation_data, JSON_PRETTY_PRINT), 'nft_valuation_log.txt');

            if (isset($valuation_data['error'])) {
                throw new Exception("API error: " . json_encode($valuation_data['error']));
            }

            // Extract values (adjust fields as per actual Helius API)
            $floor_price = $valuation_data['result']['floorPrice'] ?? null;
            $last_sale = $valuation_data['result']['lastSalePrice'] ?? null;
            $volume_24h = $valuation_data['result']['volume24h'] ?? null;

            if ($floor_price === null || $last_sale === null || $volume_24h === null) {
                throw new Exception("Incomplete data received from API.");
            }

            ?>
            <!-- Display result card -->
            <div class="result-section">
                <div class="holders-summary">
                    <div class="summary-card">
                        <div class="summary-item">
                            <i class="fas fa-chart-line"></i>
                            <p>Floor Price</p>
                            <h3><?php echo number_format($floor_price / 1e9, 2) . ' ◎'; ?></h3>
                        </div>
                        <div class="summary-item">
                            <i class="fas fa-dollar-sign"></i>
                            <p>Last Sale</p>
                            <h3><?php echo number_format($last_sale / 1e9, 2) . ' ◎'; ?></h3>
                        </div>
                        <div class="summary-item">
                            <i class="fas fa-coins"></i>
                            <p>24h Volume</p>
                            <h3><?php echo number_format($volume_24h / 1e9, 2) . ' ◎'; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <?php

        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            log_message("nft-valuation: Exception - $error_msg", 'nft_valuation_log.txt', 'ERROR');
            echo "<div class='result-error'><p>$error_msg</p></div>";
        }
    }
    ?>

    <!-- Info block -->
    <div class="t-9">
        <h2>About NFT Valuation Tool</h2>
        <p>
            This tool helps you analyze the value of a Solana NFT collection using key metrics such as floor price, last sale price, and 24-hour trading volume. Data is retrieved from blockchain APIs in real time.
        </p>
    </div>
</div>

<?php
ob_start();
include $root_path . 'include/footer.php';
$footer_output = ob_get_clean();
log_message("nft-valuation: Footer length=" . strlen($footer_output), 'nft_valuation_log.txt');
echo $footer_output;
?>
