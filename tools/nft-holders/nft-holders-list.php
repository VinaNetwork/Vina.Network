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
$page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
log_message("nft-holders-list: Processing mintAddress=$mintAddress, page=$page", 'nft_holders_log.txt');

if (empty($mintAddress) || !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("nft-holders-list: Invalid mintAddress: $mintAddress", 'nft_holders_log.txt', 'ERROR');
    http_response_code(400);
    echo '<div class="result-error"><p>Invalid collection address</p></div>';
    exit;
}

$holders_per_page = 50;
$total_holders = isset($_SESSION['total_holders'][$mintAddress]) ? $_SESSION['total_holders'][$mintAddress] : 0;
log_message("nft-holders-list: Total holders from session: $total_holders", 'nft_holders_log.txt');

// Định nghĩa getNFTHolders
function getNFTHolders($mintAddress, $offset = 0, $size = 50) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => ceil(($offset + $size) / $size),
        'limit' => $size
    ];
    log_message("nft-holders-list: Calling API for holders - mintAddress: $mintAddress, offset: $offset, size: $size, page: {$params['page']}", 'nft_holders_log.txt');
    $data = callAPI('getAssetsByGroup', $params, 'POST');
    log_message("nft-holders-list: API response: " . json_encode($data), 'nft_holders_log.txt');
    if (isset($data['error'])) {
        log_message("nft-holders-list: getAssetsByGroup error - " . json_encode($data), 'nft_holders_log.txt', 'ERROR');
        return ['error' => 'This is not an NFT collection address. Please enter a valid NFT collection address.'];
    }
    if (isset($data['result']['items']) && !empty($data['result']['items'])) {
        $holders = array_map(function($item) {
            return [
                'owner' => $item['ownership']['owner'] ?? 'unknown',
                'amount' => 1
            ];
        }, $data['result']['items']);
        return ['holders' => $holders];
    }
    log_message("nft-holders-list: No holders found for address $mintAddress", 'nft_holders_log.txt', 'ERROR');
    return ['error' => 'This is not an NFT collection address. Please enter a valid NFT collection address.'];
}

// Lấy danh sách holders
try {
    $offset = ($page - 1) * $holders_per_page;
    $holders_data = getNFTHolders($mintAddress, $offset, $holders_per_page);
    log_message("nft-holders-list: Holders data: " . json_encode($holders_data), 'nft_holders_log.txt');

    ob_start();
    echo "<div class='export-section'>";
    echo "<form method='POST' action='/tools/nft-holders/export-holders.php' class='export-form'>";
    echo "<input type='hidden' name='mintAddress' value='" . htmlspecialchars($mintAddress) . "'>";
    echo "<input type='hidden' name='page' value='$page'>";
    echo "<div class='export-controls'>";
    echo "<select name='export_format' class='export-format'>";
    echo "<option value='csv'>CSV</option>";
    echo "<option value='json'>JSON</option>";
    echo "</select>";
    echo "<button type='submit' name='export_type' value='all' class='export-btn' id='export-all-btn'>Export All Holders</button>";
    echo "<button type='submit' name='export_type' value='current' class='export-btn'>Export Current Page</button>";
    echo "</div>";
    echo "</form>";
    echo "<div class='progress-container' style='display: none;'>";
    echo "<p>Exporting... Please wait.</p>";
    echo "<div class='progress-bar'><div class='progress-bar-fill' style='width: 0%;'></div></div>";
    echo "</div>";
    echo "</div>";

    if (isset($holders_data['error'])) {
        echo "<div class='result-error'><p>" . htmlspecialchars($holders_data['error']) . "</p></div>";
    } elseif ($holders_data && !empty($holders_data['holders'])) {
        $paginated_holders = $holders_data['holders'];
        $current_holders = min($page * $holders_per_page, $total_holders);
        $percentage = $total_holders > 0 ? number_format(($current_holders / $total_holders) * 100, 1) : 0;

        echo "<div class='result-section'>";
        echo "<p class='result-info'>Page $page: $current_holders/$total_holders ($percentage%)</p>";

        echo "<table class='holders-table'>";
        echo "<thead><tr><th>Address</th><th>Amount</th></tr></thead>";
        echo "<tbody>";
        foreach ($paginated_holders as $holder) {
            $address = htmlspecialchars($holder['owner'] ?? 'N/A');
            $amount = htmlspecialchars($holder['amount'] ?? 'N/A');
            echo "<tr><td>$address</td><td>$amount</td></tr>";
        }
        echo "</tbody>";
        echo "</table>";

        // Phân trang
        echo "<div class='pagination'>";
        $total_pages = ceil($total_holders / $holders_per_page);

        if ($page > 1) {
            echo "<form method='POST' class='page-form' style='display:inline;'><input type='hidden' name='mintAddress' value='" . htmlspecialchars($mintAddress) . "'><input type='hidden' name='page' value='1'><button type='submit' class='page-button' data-type='number' data-page='1'>1</button></form>";
        } else {
            echo "<span class='page-button active' data-type='number'>1</span>";
        }

        if ($page > 2) {
            echo "<span class='page-button ellipsis' data-type='ellipsis'>...</span>";
        }

        if ($page > 1) {
            echo "<form method='POST' class='page-form' style='display:inline;'><input type='hidden' name='mintAddress' value='" . htmlspecialchars($mintAddress) . "'><input type='hidden' name='page' value='" . ($page - 1) . "'><button type='submit' class='page-button nav' data-type='nav' data-page='" . ($page - 1) . "' title='Previous'><</button></form>";
        }

        if ($page > 1 && $page < $total_pages) {
            echo "<span class='page-button active' data-type='number'>$page</span>";
        }

        if ($page < $total_pages) {
            echo "<form method='POST' class='page-form' style='display:inline;'><input type='hidden' name='mintAddress' value='" . htmlspecialchars($mintAddress) . "'><input type='hidden' name='page' value='" . ($page + 1) . "'><button type='submit' class='page-button nav' data-type='nav' data-page='" . ($page + 1) . "' title='Next'>></button></form>";
        }

        if ($page < $total_pages - 1) {
            echo "<span class='page-button ellipsis' data-type='ellipsis'>...</span>";
        }

        if ($page < $total_pages) {
            echo "<form method='POST' class='page-form' style='display:inline;'><input type='hidden' name='mintAddress' value='" . htmlspecialchars($mintAddress) . "'><input type='hidden' name='page' value='$total_pages'><button type='submit' class='page-button' data-type='number' data-page='$total_pages'>$total_pages</button></form>";
        } else {
            echo "<span class='page-button active' data-type='number'>$total_pages</span>";
        }

        echo "</div>"; // .pagination
        echo "</div>"; // .result-section
    } else {
        echo "<div class='result-error'><p>No holders found for this page or invalid collection address.</p></div>";
    }
    $output = ob_get_clean();
    log_message("nft-holders-list: Output length: " . strlen($output), 'nft_holders_log.txt');
    echo $output;
} catch (Exception $e) {
    log_message("nft-holders-list: Exception - " . $e->getMessage(), 'nft_holders_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Server error: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
}
?>
