<?php
/*
 * nft-holders-info.php - Display NFT Holder Summary & Export Options (AJAX Handler)
 *
 * This script is used to handle AJAX requests to display summary information
 * about the total number of holders and NFTs for a given collection address.
 * It retrieves data from the session cache populated during the initial form
 * submission in nft-holders.php and provides export functionality (CSV/JSON).
 */

// Define constants to mark script entry (if not already defined)
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Include bootstrap file to load configuration and helpers
$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-holders-list: bootstrap.php not found at $bootstrap_path", 'nft_holders_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Server error: Missing bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Include tools API helper for logging and shared functions
$api_helper_path = __DIR__ . '/../tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-holders-list: tools-api.php not found at $api_helper_path", 'nft_holders_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;

// Start session and log load event
session_start();
log_message("nft-holders-list: Loaded at " . date('Y-m-d H:i:s'), 'nft_holders_log.txt');

// Validate that the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("nft-holders-list: Invalid request method: {$_SERVER['REQUEST_METHOD']}", 'nft_holders_log.txt', 'ERROR');
    http_response_code(400);
    echo '<div class="result-error"><p>Invalid request method</p></div>';
    exit;
}

// Retrieve collection address from POST data and validate format
$mintAddress = trim($_POST['mintAddress'] ?? '');
log_message("nft-holders-list: Processing mintAddress=$mintAddress", 'nft_holders_log.txt');

if (empty($mintAddress) || !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("nft-holders-list: Invalid mintAddress: $mintAddress", 'nft_holders_log.txt', 'ERROR');
    http_response_code(400);
    echo '<div class="result-error"><p>Invalid collection address</p></div>';
    exit;
}

// Retrieve total items and wallets from session cache
$total_items = $_SESSION['total_items'][$mintAddress] ?? 0;
$total_wallets = $_SESSION['total_wallets'][$mintAddress] ?? 0;
$wallets = $_SESSION['wallets'][$mintAddress] ?? [];
log_message("nft-holders-list: Retrieved from session - total_items=$total_items, total_wallets=$total_wallets", 'nft_holders_log.txt');

try {
    ob_start();
    echo "<div class='result-section'>";
    if ($total_wallets === 0) {
        echo "<p class='result-error'>No holders found for this collection.</p>";
    } else {
        // Display summary cards with totals
        echo "<div class='holders-summary'>";
        echo "<div class='summary-card'>";
        echo "<div class='summary-item'>";
        echo "<i class='fas fa-wallet'></i>";
        echo "<p>Total wallets</p>";
        echo "<h3>" . number_format($total_wallets) . "</h3>";
        echo "</div>";
        echo "<div class='summary-item'>";
        echo "<i class='fas fa-image'></i>";
        echo "<p>Total NFTs</p>";
        echo "<h3>" . number_format($total_items) . "</h3>";
        echo "</div>";
        echo "</div>";
        echo "</div>";

        // Display export controls
        echo "<div class='export-section'>";
        echo "<form method='POST' action='/tools/nft-holders/nft-holders-export.php' class='export-form'>";
        echo "<input type='hidden' name='mintAddress' value='" . htmlspecialchars($mintAddress) . "'>";
        echo "<div class='export-controls'>";
        echo "<select name='export_format' class='export-format'>";
        echo "<option value='csv'>CSV</option>";
        echo "<option value='json'>JSON</option>";
        echo "</select>";
        echo "<button type='submit' name='export_type' value='all' class='cta-button export-btn' id='export-all-btn'>Export All Wallets</button>";
        echo "</div>";
        echo "</form>";

        echo "<div class='progress-container' style='display: none;'>";
        echo "<p>Exporting... Please wait.</p>";
        echo "<div class='progress-bar'><div class='progress-bar-fill' style='width: 0%;'></div></div>";
        echo "</div>";
        echo "</div>";
    }
    echo "</div>";
    $output = ob_get_clean();
    log_message("nft-holders-list: Output length: " . strlen($output), 'nft_holders_log.txt');
    echo $output;
} catch (Exception $e) {
    log_message("nft-holders-list: Exception - " . $e->getMessage(), 'nft_holders_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Server error: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
}
?>
