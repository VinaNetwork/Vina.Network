<?php
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
define('VINANETWORK_ENTRY', true);
require_once '../bootstrap.php';
require_once '../api-helper.php';

session_start();
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/vinanetwork/public_html/nft-holders/nfts/logs/php_error.log');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Define log path
define('EXPORT_LOG_PATH', '/var/www/vinanetwork/public_html/nft-holders/nfts/logs/holders_export_log.txt');

// Test log writing
file_put_contents(EXPORT_LOG_PATH, "export-holders: Script started - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Invalid request method - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$mintAddress = trim($_POST['mintAddress'] ?? '');
$export_type = $_POST['export_type'] ?? 'all';
$export_format = $_POST['export_format'] ?? 'csv';

file_put_contents(EXPORT_LOG_PATH, "export-holders: Parameters - mintAddress=$mintAddress, export_type=$export_type, export_format=$export_format - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Invalid collection address: $mintAddress - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid collection address']);
    exit;
}

if (!in_array($export_format, ['csv', 'json'])) {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Invalid export format: $export_format - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export format']);
    exit;
}

if ($export_type !== 'all') {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Invalid export type: $export_type - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export type']);
    exit;
}

function getHolders($mintAddress, $page = 1, $size = 100) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => $page,
        'limit' => $size
    ];
    $max_retries = 3;
    $retry_count = 0;
    do {
        file_put_contents(EXPORT_LOG_PATH, "export-holders: Fetching holders - mintAddress=$mintAddress, page=$page, size=$size, retry=$retry_count, params=" . json_encode($params) . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        $data = callAPI('getAssetsByGroup', $params, 'POST');
        if (isset($data['error'])) {
            file_put_contents(EXPORT_LOG_PATH, "export-holders: API error - " . json_encode($data['error']) . ", retry=$retry_count - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            if ($retry_count < $max_retries) {
                $retry_count++;
                usleep(2000000); // Delay 2s before retry
                continue;
            }
            return ['error' => $data['error']];
        }
        $items = $data['result']['items'] ?? [];
        $total = $data['result']['total'] ?? $data['result']['totalItems'] ?? count($items);
        file_put_contents(EXPORT_LOG_PATH, "export-holders: API response - total=$total, items_count=" . count($items) . ", raw_response=" . json_encode($data, JSON_PRETTY_PRINT) . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        if (empty($items) && $retry_count < $max_retries && $page > 1) {
            file_put_contents(EXPORT_LOG_PATH, "export-holders: Empty items on page $page, retry=$retry_count - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            $retry_count++;
            usleep(2000000); // Delay 2s before retry
            continue;
        }
        $holders = array_map(function($item) {
            return [
                'owner' => $item['ownership']['owner'] ?? 'unknown',
                'amount' => 1
            ];
        }, $items);
        file_put_contents(EXPORT_LOG_PATH, "export-holders: Fetched " . count($holders) . " holders for page $page, total=$total - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        return ['holders' => $holders, 'total' => $total];
    } while ($retry_count < $max_retries);
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Max retries reached for page $page - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    return ['holders' => [], 'total' => 0];
}

try {
    $holders = [];
    $filename = $export_format === 'csv'
        ? "holders_all_{$mintAddress}.csv"
        : "holders_all_{$mintAddress}.json";

    // Get total holders from session
    $total_expected = isset($_SESSION['total_holders'][$mintAddress]) ? $_SESSION['total_holders'][$mintAddress] : 0;
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Total expected holders from session: $total_expected - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

    if ($total_expected === 0) {
        $result = getHolders($mintAddress, 1, 100);
        if (isset($result['error'])) {
            throw new Exception('API error: ' . json_encode($result['error']));
        }
        $total_expected = $result['total'];
        file_put_contents(EXPORT_LOG_PATH, "export-holders: Total expected holders from API fallback: $total_expected - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    }

    if ($total_expected === 0) {
        file_put_contents(EXPORT_LOG_PATH, "export-holders: No holders found for mintAddress=$mintAddress - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        throw new Exception('No holders found');
    }

    // Fetch all holders
    $api_page = 1;
    $limit = 100;
    $total_fetched = 0;
    while ($total_fetched < $total_expected && $api_page <= 100) {
        $result = getHolders($mintAddress, $api_page, $limit);
        if (isset($result['error'])) {
            throw new Exception('API error: ' . json_encode($result['error']));
        }
        $page_holders = $result['holders'];
        $holders = array_merge($holders, $page_holders);
        $total_fetched += count($page_holders);
        file_put_contents(EXPORT_LOG_PATH, "export-holders: Page $api_page - Fetched $total_fetched/$total_expected holders - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        if (count($page_holders) == 0) {
            file_put_contents(EXPORT_LOG_PATH, "export-holders: No more holders on page $api_page, stopping - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            break;
        }
        $api_page++;
        usleep(2000000); // Delay 2s between pages
    }

    if (empty($holders)) {
        file_put_contents(EXPORT_LOG_PATH, "export-holders: No holders found for mintAddress=$mintAddress - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        throw new Exception('No holders found');
    }

    // Remove duplicates and count amounts
    $total_items = count($holders);
    $unique_holders = [];
    foreach ($holders as $holder) {
        $owner = $holder['owner'];
        if (!isset($unique_holders[$owner])) {
            $unique_holders[$owner] = $holder;
        } else {
            $unique_holders[$owner]['amount'] += 1;
        }
    }
    $holders = array_values($unique_holders);
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Total items before deduplication: $total_items, Total unique holders after deduplication: " . count($holders) . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

    if ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Wallet Address', 'NFT Count']);
        foreach ($holders as $holder) {
            fputcsv($output, [
                $holder['owner'] ?? 'N/A',
                $holder['amount'] ?? 0
            ]);
        }
        fclose($output);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $json_data = array_map(function($holder) {
            return [
                'address' => $holder['owner'] ?? 'N/A',
                'amount' => $holder['amount'] ?? 0
            ];
        }, $holders);
        echo json_encode($json_data, JSON_PRETTY_PRINT);
    }
    exit;
} catch (Exception $e) {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Exception - " . $e->getMessage() . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>
