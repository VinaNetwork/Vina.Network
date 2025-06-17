<?php
// =============================================================================
// File: tools/nft-valuation/nft-valuation.php
// Description: Check NFT Valuation for a given Solana NFT Collection Address.
// Created by: Vina Network
// =============================================================================

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    die('Error: bootstrap.php not found');
}
require_once $bootstrap_path;

session_start();

$root_path = '../../';
$page_title = 'Check NFT Valuation - Vina Network';
$page_description = 'View NFT valuation stats using collection address.';
$page_css = ['../../css/vina.css', '../tools.css'];
include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';

?><div class="t-6 nft-valuation-content">
  <div class="t-7">
    <h2>Check NFT Valuation</h2>
    <p>Enter the <strong>NFT Collection Address</strong> (Collection ID) to get valuation data. You can find it on Magic Eden under "Details" > "On-chain Collection".</p>
    <form id="nftValuationForm" method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <input type="text" name="mintAddress" id="mintAddressValuation" placeholder="Enter NFT Collection Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
      <button type="submit" class="cta-button">Check Valuation</button>
    </form>
    <div class="loader"></div>
  </div><?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception("Invalid CSRF token. Please try again.");
        }

        $mintAddress = trim($_POST['mintAddress']);
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
            throw new Exception("Invalid collection address format.");
        }

        // Step 1: Get collection slug via Helius
        $heliusParams = ["id" => $mintAddress];
        $heliusResp = callAPI('getAsset', $heliusParams, 'POST');
        if (!isset($heliusResp['result']['grouping'][0][1])) {
            throw new Exception("Failed to retrieve collection slug from Helius.");
        }
        $slug = $heliusResp['result']['grouping'][0][1];

        // Step 2: Get valuation data from Tensor API
        $tensorUrl = "https://api.tensor.trade/v1/collections/" . urlencode($slug);
        $tensorResp = file_get_contents($tensorUrl);
        if (!$tensorResp) {
            throw new Exception("Failed to fetch data from Tensor API.");
        }
        $tensorData = json_decode($tensorResp, true);
        if (!is_array($tensorData)) {
            throw new Exception("Invalid data format returned by Tensor API.");
        }

        $floor = $tensorData['floorPrice'] ?? null;
        $last = $tensorData['listedPrices']['0'] ?? null;
        $vol24h = $tensorData['volume24h'] ?? null;

        if (!$floor && !$last && !$vol24h) {
            throw new Exception("No valuation data found for this collection.");
        }

        ?><div class="result-section">
        <div class="holders-summary">
            <div class="summary-card">
                <div class="summary-item">
                    <i class="fas fa-tag"></i>
                    <p>Floor Price</p>
                    <h3><?php echo $floor / 1e9; ?> ◎</h3>
                </div>
                <div class="summary-item">
                    <i class="fas fa-clock"></i>
                    <p>Last Listed</p>
                    <h3><?php echo $last / 1e9; ?> ◎</h3>
                </div>
                <div class="summary-item">
                    <i class="fas fa-chart-line"></i>
                    <p>Volume (24h)</p>
                    <h3><?php echo round($vol24h / 1e9, 2); ?> ◎</h3>
                </div>
            </div>
        </div>
    </div>
    <?php

} catch (Exception $e) {
    echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
}

} ?>

</div><?php
include $root_path . 'include/footer.php';
?>
