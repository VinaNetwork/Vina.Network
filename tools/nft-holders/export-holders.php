<?php
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
define('VINANETWORK_ENTRY', true);
require_once '../bootstrap.php';
require_once '../api-helper.php';

session_start();
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

log_message("export-holders: Request received - method={$_SERVER['REQUEST_METHOD']}, POST=" . json_encode($_POST), 'export_log.txt');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("export-holders: Invalid request method", 'export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$mintAddress = trim($_POST['mintAddress'] ?? '');
$export_type = $_POST['export_type'] ?? 'all';
$export_format = $_POST['export_format'] ?? 'csv';

log_message("export-holders: Parameters - mintAddress=$mintAddress, export_type=$export_type, export_format=$export_format", 'export_log.txt');

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("export-holders: Invalid collection address: $mintAddress", 'export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid collection address']);
    exit;
}

if (!in_array($export_format, ['csv', 'json'])) {
    log_message("export-holders: Invalid export format: $export_format", 'export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export format']);
    exit;
}

if ($export_type !== 'all') {
    log_message("export-holders: Invalid export type: $export_type", 'export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export type']);
    exit;
}

function getHolders($mintAddress, $page = 1, $size = 1000) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => $page,
        'limit' => $size
    ];
    $max_retries = 3;
    $retry_count = 0;
    do {
        log_message("export-holders: Fetching holders - mintAddress=$mintAddress, page=$page, size=$size, retry=$retry_count, params=" . json_encode($params), 'export_log.txt');
        $data = callAPI('getAssetsByGroup', $params, 'POST');
        if (isset($data['error'])) {
            log_message("export-holders: API error - " . json_encode($data['error']) . ", retry=$retry_count", 'export_log.txt', 'ERROR');
            if ($retry_count < $max_retries) {
                $retry_count++;
                usleep(500000); // Delay 500ms before retry
                continue;
            }
            return ['error' => $data['error']];
        }
        $items = $data['result']['items'] ?? [];
        $total = $data['result']['total'] ?? $data['result']['totalItems'] ?? count($items);
        log_message("export-holders: API response - total=$total, items_count=" . count($items), 'export_log.txt');
        if (empty($items) && $retry_count < $max_retries) {
            log_message("export-holders: Empty items on page $page, retry=$retry_count", 'export_log.txt', 'WARN');
            $retry_count++;
            usleep(500000); // Delay 500ms before retry
            continue;
        }
        $holders = array_map(function($item) {
            return [
                'owner' => $item['ownership']['owner'] ?? 'unknown',
                'amount' => 1
            ];
        }, $items);
        log_message("export-holders: Fetched " . count($holders) . " holders for page $page, total=$total", 'export_log.txt');
        return ['holders' => $holders, 'total' => $total];
    } while ($retry_count < $max_retries);
    log_message("export-holders: Max retries reached for page $page", 'export_log.txt', 'ERROR');
    return ['holders' => [], 'total' => 0];
}

try {
    $holders = [];
    $filename = $export_format === 'csv'
        ? "holders_all_{$mintAddress}.csv"
        : "holders_all_{$mintAddress}.json";

    // Get total holders from session (set in nft-holders-list.php)
    $total_expected = isset($_SESSION['total_holders'][$mintAddress]) ? $_SESSION['total_holders'][$mintAddress] : 0;
    log_message("export-holders: Total expected holders from session: $total_expected", 'export_log.txt');

    if ($total_expected === 0) {
        // Fallback to API if session is empty
        $result = getHolders($mintAddress, 1, 1000);
        if (isset($result['error'])) {
            throw new Exception('API error: ' . json_encode($result['error']));
        }
        $total_expected = $result['total'];
        log_message("export-holders: Total expected holders from API fallback: $total_expected", 'export_log.txt');
    }

    if ($total_expected === 0) {
        log_message("export-holders: No holders found for mintAddress=$mintAddress", 'export_log.txt', 'ERROR');
        throw new Exception('No holders found');
    }

    // Fetch all holders
    $api_page = 1;
    $limit = 1000;
    $total_fetched = 0;
    while ($total_fetched < $total_expected && $api_page <= 100) {
        $result = getHolders($mintAddress, $api_page, $limit);
        if (isset($result['error'])) {
            throw new Exception('API error: ' . json_encode($result['error']));
        }
        $page_holders = $result['holders'];
        $holders = array_merge($holders, $page_holders);
        $total_fetched += count($page_holders);
        log_message("export-holders: Page $api_page - Fetched $total_fetched/$total_expected holders", 'export_log.txt');
        if (count($page_holders) == 0) {
            log_message("export-holders: No more holders on page $api_page, stopping", 'export_log.txt');
            break;
        }
        $api_page++;
        usleep(500000); // Delay 500ms to avoid rate limit
    }

    if (empty($holders)) {
        log_message("export-holders: No holders found for mintAddress=$mintAddress", 'export_log.txt', 'ERROR');
        throw new Exception('No holders found');
    }

    // Remove duplicates and count amounts
    $unique_holders = [];
    $total_items = count($holders);
    foreach ($holders as $holder) {
        $owner = $holder['owner'];
        if (!isset($unique_holders[$owner])) {
            $unique_holders[$owner] = $holder;
        } else {
            $unique_holders[$owner]['amount'] += 1;
        }
    }
    $holders = array_values($unique_holders);
    log_message("export-holders: Total items before deduplication: $total_items, Total unique holders after deduplication: " . count($holders), 'export_log.txt');

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
    log_message("export-holders: Exception - " . $e->getMessage(), 'export_log.txt', 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>
