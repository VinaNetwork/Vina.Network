<?php
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Include dependencies
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

// Nhận tham số
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

// Lấy tổng số holders
$total_holders = 0;
$params = [
    'groupKey' => 'collection',
    'groupValue' => $mintAddress,
    'page' => 1,
    'limit' => 1
];
log_message("nft-holders-list: Calling API for total holders - mintAddress: $mintAddress", 'nft_holders_log.txt');
$data = callAPI('getAssetsByGroup', $params, 'POST');
if (isset($data['error'])) {
    log_message("nft-holders-list: getAssetsByGroup error - " . json_encode($data), 'nft_holders_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Error fetching holders: ' . htmlspecialchars($data['error']) . '</p></div>';
    exit;
}
$total_holders = $data['result']['totalItems'] ?? $data['result']['total'] ?? 0;
$_SESSION['total_holders'][$mintAddress] = $total_holders;
log_message("nft-holders-list: Total holders: $total_holders", 'nft_holders_log.txt');

try {
    ob_start();
    echo "<div class='result-section'>";
    if ($total_holders === 0) {
        echo "<p class='result-error'>No holders found for this collection.</p>";
    } else {
        echo "<div class='holders-summary'>";
        echo "<p>Total Holders: <strong>$total_holders</strong></p>";
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
