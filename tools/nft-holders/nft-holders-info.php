<?php
/*
 * nft-holders-info.php - Display NFT Holder Summary & Paginated List (AJAX Handler)
 *
 * This script handles AJAX requests to display summary information
 * about the total number of holders and NFTs, and a paginated list of holders.
 * Restored from Update 1 with fixes for session validation.
 */

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Include bootstrap file
$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-holders-list: bootstrap.php not found at $bootstrap_path", 'nft_holders_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Server error: Missing bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Include tools API helper
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

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("nft-holders-list: Invalid request method: {$_SERVER['REQUEST_METHOD']}", 'nft_holders_log.txt', 'ERROR');
    http_response_code(400);
    echo '<div class="result-error"><p>Invalid request method</p></div>';
    exit;
}

// Retrieve collection address and page
$mintAddress = trim($_POST['mintAddress'] ?? '');
$page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
$holders_per_page = 50;
log_message("nft-holders-list: Processing mintAddress=$mintAddress, page=$page", 'nft_holders_log.txt');

// Validate mintAddress
if (empty($mintAddress) || !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("nft-holders-list: Invalid mintAddress: $mintAddress", 'nft_holders_log.txt', 'ERROR');
    http_response_code(400);
    echo '<div class="result-error"><p>Invalid collection address</p></div>';
    exit;
}

// Check session cache
if (!isset($_SESSION['total_items'][$mintAddress]) || !isset($_SESSION['wallets'][$mintAddress])) {
    log_message("nft-holders-list: No session cache for mintAddress=$mintAddress, redirecting", 'nft_holders_log.txt');
    http_response_code(307);
    header("Location: /tools/nft-holders/nft-holders.php?tool=nft-holders&mintAddress=" . urlencode($mintAddress));
    exit;
}

// Retrieve data from session
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
        // Display summary cards
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

        // Display paginated holders list
        $total_pages = ceil($total_wallets / $holders_per_page);
        $page = max(1, min($page, $total_pages));
        $offset = ($page - 1) * $holders_per_page;
        $paged_wallets = array_slice($wallets, $offset, $holders_per_page);

        echo "<div class='holders-list'>";
        echo "<table>";
        echo "<thead><tr><th>Wallet Address</th><th>NFT Count</th></tr></thead>";
        echo "<tbody>";
        foreach ($paged_wallets as $wallet) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($wallet['owner'] ?? 'N/A') . "</td>";
            echo "<td>" . ($wallet['amount'] ?? 0) . "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
        echo "</div>";

        // Display pagination controls
        if ($total_pages > 1) {
            echo "<div class='pagination'>";
            echo "<form method='POST' action=''>";
            echo "<input type='hidden' name='mintAddress' value='" . htmlspecialchars($mintAddress) . "'>";
            echo "<input type='hidden' name='page' value='" . ($page - 1) . "'>";
            if ($page > 1) {
                echo "<button type='submit' class='page-button' data-page='" . ($page - 1) . "'>Previous</button>";
            }
            $range = 2;
            $start = max(1, $page - $range);
            $end = min($total_pages, $page + $range);
            if ($start > 1) {
                echo "<button type='button' class='page-button' data-page='1'>1</button>";
                if ($start > 2) {
                    echo "<span class='ellipsis' data-type='ellipsis'>...</span>";
                }
            }
            for ($i = $start; $i <= $end; $i++) {
                echo "<button type='button' class='page-button" . ($i === $page ? ' active' : '') . "' data-page='$i'>$i</button>";
            }
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) {
                    echo "<span class='ellipsis' data-type='ellipsis'>...</span>";
                }
                echo "<button type='button' class='page-button' data-page='$total_pages'>$total_pages</button>";
            }
            if ($page < $total_pages) {
                echo "<button type='submit' class='page-button' data-page='" . ($page + 1) . "'>Next</button>";
            }
            echo "</form>";
            echo "</div>";
        }

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
