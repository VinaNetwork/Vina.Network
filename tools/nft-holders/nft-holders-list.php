<?php
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-holders-list: bootstrap.php not found at $bootstrap_path", 'nft_holders_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Server error: Missing bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

$api_helper_path = __DIR__ . '/../api-helper.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-holders-list: api-helper.php not found at $api_helper_path", 'nft_holders_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Server error: Missing api-helper.php</p></div>';
    exit;
}
require_once $api_helper_path;

session_start();
log_message("nft-holders-list: Loaded at " . date('Y-m-d H:i:s'), 'nft_holders_log.txt');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("nft-holders-list: Invalid request method: {$_SERVER['REQUEST_METHOD']}", 'nft_holders_log.txt', 'ERROR');
    http_response_code(400);
    echo '<div class="result-error"><p>Invalid request method</p></div>';
    exit;
}

$mintAddress = trim($_POST['mintAddress'] ?? '');
log_message("nft-holders-list: Processing mintAddress=$mintAddress", 'nft_holders_log.txt');

if (empty($mintAddress) || !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("nft-holders-list: Invalid mintAddress: $mintAddress", 'nft_holders_log.txt', 'ERROR');
    http_response_code(400);
    echo '<div class="result-error"><p>Invalid collection address</p></div>';
    exit;
}

$total_items = $_SESSION['total_items'][$mintAddress] ?? 0;
$total_wallets = $_SESSION['total_wallets'][$mintAddress] ?? 0;
$wallets = $_SESSION['wallets'][$mintAddress] ?? [];
log_message("nft-holders-list: Retrieved from session - total_items=$total_items, total_wallets=$total_wallets", 'nft_holders_log.txt');

// Tính toán dữ liệu cho biểu đồ phân phối
$distribution = [];
foreach ($wallets as $wallet) {
    $amount = $wallet['amount'];
    $distribution[$amount] = ($distribution[$amount] ?? 0) + 1;
}
ksort($distribution); // Sắp xếp theo số NFT
$chart_labels = array_map(function($amount) { return "$amount NFT" . ($amount > 1 ? 's' : ''); }, array_keys($distribution));
$chart_data = array_values($distribution);
log_message("nft-holders-list: Chart data - labels=" . json_encode($chart_labels) . ", data=" . json_encode($chart_data), 'nft_holders_log.txt');

try {
    ob_start();
    echo "<div class='result-section'>";
    if ($total_wallets === 0) {
        echo "<p class='result-error'>No holders found for this collection.</p>";
    } else {
        echo "<div class='holders-summary'>";
        echo "<p>Total Holders: <strong>$total_wallets</strong></p>";
        echo "<p>Total NFTs: <strong>$total_items</strong></p>";
        echo "<h3>NFT Distribution</h3>";
        echo "<canvas id='nftDistributionChart' style='max-height: 300px;'></canvas>";
        echo "</div>";

        echo "<div class='export-section'>";
        echo "<form method='POST' action='/tools/nft-holders/export-holders.php' class='export-form'>";
        echo "<input type='hidden' name='mintAddress' value='" . htmlspecialchars($mintAddress) . "'>";
        echo "<div class='export-controls'>";
        echo "<select name='export_format' class='export-format'>";
        echo "<option value='csv'>CSV</option>";
        echo "<option value='json'>JSON</option>";
        echo "</select>";
        echo "<button type='submit' name='export_type' value='all' class='export-btn' id='export-all-btn'>Export All Holders</button>";
        echo "</div>";
        echo "</form>";
        echo "<div class='progress-container' style='display: none;'>";
        echo "<p>Exporting... Please wait.</p>";
        echo "<div class='progress-bar'><div class='progress-bar-fill' style='width: 0%;'></div></div>";
        echo "</div>";
        echo "</div>";

        // Truyền dữ liệu biểu đồ sang JS
        echo "<script>";
        echo "window.nftDistributionData = { labels: " . json_encode($chart_labels) . ", data: " . json_encode($chart_data) . " };";
        echo "</script>";
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
