<?php
// ============================================================================
// File: make-market/process/get-decimals.php
// Description: Get Decimals Token Solana with caching
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php'; // Load SOLANA_NETWORK and HELIUS_API_KEY

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Define cache settings
define('CACHE_DIR', MAKE_MARKET_PATH . 'cache/');
define('CACHE_FILE', CACHE_DIR . 'cache-decimals.json');
define('CACHE_TTL', 24 * 60 * 60); // Cache TTL: 24 hours in seconds

// Function to read from cache
function read_from_cache($tokenMint, $network) {
    if (!file_exists(CACHE_FILE)) {
        return null;
    }

    $cache_data = json_decode(file_get_contents(CACHE_FILE), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("get-decimals.php: Failed to parse cache file: " . json_last_error_msg());
        return null;
    }

    $cache_key = $tokenMint . '_' . $network;
    if (isset($cache_data[$cache_key]) && $cache_data[$cache_key]['timestamp'] + CACHE_TTL > time()) {
        return $cache_data[$cache_key]['decimals'];
    }

    return null;
}

// Function to write to cache
function write_to_cache($tokenMint, $network, $decimals) {
    $cache_data = file_exists(CACHE_FILE) ? json_decode(file_get_contents(CACHE_FILE), true) : [];
    if (json_last_error() !== JSON_ERROR_NONE) {
        $cache_data = [];
    }

    $cache_key = $tokenMint . '_' . $network;
    $cache_data[$cache_key] = [
        'decimals' => $decimals,
        'timestamp' => time()
    ];

    if (!ensure_directory_and_file(CACHE_DIR, CACHE_FILE)) {
        error_log("get-decimals.php: Failed to ensure cache directory or file: " . CACHE_FILE);
        return;
    }

    if (file_put_contents(CACHE_FILE, json_encode($cache_data, JSON_PRETTY_PRINT), LOCK_EX) === false) {
        error_log("get-decimals.php: Failed to write to cache file: " . CACHE_FILE);
    } else {
        error_log("get-decimals.php: Cache updated for mint=$tokenMint, network=$network, decimals=$decimals");
    }
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['tokenMint']) || !isset($data['network'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing tokenMint or network']);
    error_log('get-decimals.php: Invalid or missing tokenMint or network');
    exit;
}

$tokenMint = $data['tokenMint'];
$network = $data['network'];
if (!in_array($network, ['testnet', 'mainnet'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid network']);
    error_log("get-decimals.php: Invalid network: $network");
    exit;
}

// Check cache first
$cached_decimals = read_from_cache($tokenMint, $network);
if ($cached_decimals !== null) {
    error_log("get-decimals.php: Cache hit for mint=$tokenMint, network=$network, decimals=$cached_decimals");
    echo json_encode(['status' => 'success', 'decimals' => $cached_decimals]);
    exit;
}

$endpoints = $network === 'testnet' ? [
    'https://api.testnet.solana.com',
    'https://api.devnet.solana.com'
] : [
    defined('HELIUS_API_KEY') ? 'https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY : null,
    'https://api.mainnet-beta.solana.com'
];
$endpoints = array_filter($endpoints); // Remove null entries

$maxRetries = 5;
$attempt = 0;
$endpointIndex = 0;

while ($attempt < $maxRetries) {
    $url = $endpoints[$endpointIndex];
    error_log("get-decimals.php: Attempting to get token decimals (attempt " . ($attempt + 1) . "/$maxRetries): mint=$tokenMint, endpoint=$url, network=$network");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'jsonrpc' => '2.0',
        'id' => '1',
        'method' => 'getAccountInfo',
        'params' => [$tokenMint, ['encoding' => 'jsonParsed']]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        $attempt++;
        error_log("get-decimals.php: Failed to get token decimals (attempt $attempt/$maxRetries): mint=$tokenMint, error=cURL error: $curl_error, network=$network, endpoint=$url");
        if ($attempt === $maxRetries && $endpointIndex < count($endpoints) - 1) {
            $endpointIndex++;
            $attempt = 0;
            error_log("get-decimals.php: Switching to fallback endpoint: {$endpoints[$endpointIndex]}");
        } elseif ($attempt === $maxRetries) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => "Failed to retrieve token decimals after $maxRetries attempts: cURL error: $curl_error"]);
            error_log("get-decimals.php: Failed to retrieve token decimals after $maxRetries attempts: cURL error: $curl_error");
            exit;
        }
        sleep($attempt); // Wait 1s, 2s, 3s, 4s, 5s
        continue;
    }

    if ($http_code !== 200) {
        $attempt++;
        error_log("get-decimals.php: Failed to get token decimals (attempt $attempt/$maxRetries): mint=$tokenMint, error=HTTP $http_code, network=$network, endpoint=$url");
        if ($attempt === $maxRetries && $endpointIndex < count($endpoints) - 1) {
            $endpointIndex++;
            $attempt = 0;
            error_log("get-decimals.php: Switching to fallback endpoint: {$endpoints[$endpointIndex]}");
        } elseif ($attempt === $maxRetries) {
            http_response_code($http_code);
            echo json_encode(['status' => 'error', 'message' => "Failed to retrieve token decimals after $maxRetries attempts: HTTP $http_code"]);
            error_log("get-decimals.php: Failed to retrieve token decimals after $maxRetries attempts: HTTP $http_code");
            exit;
        }
        sleep($attempt);
        continue;
    }

    $result = json_decode($response, true);
    error_log("get-decimals.php: Response from getAccountInfo: " . json_encode($result));

    if (!isset($result['result']['value']['data']['parsed']['type']) || $result['result']['value']['data']['parsed']['type'] !== 'mint') {
        http_response_code(400);
        $message = isset($result['result']['value'])
            ? "Invalid account type: received type={$result['result']['value']['data']['parsed']['type']}, expected 'mint'"
            : "Invalid response: no valid account data";
        echo json_encode(['status' => 'error', 'message' => $message]);
        error_log("get-decimals.php: $message, mint=$tokenMint, endpoint=$url");
        exit;
    }

    $decimals = $result['result']['value']['data']['parsed']['info']['decimals'] ?? 9;
    error_log("get-decimals.php: Token decimals retrieved: mint=$tokenMint, decimals=$decimals, network=$network, endpoint=$url");

    // Save to cache
    write_to_cache($tokenMint, $network, $decimals);

    echo json_encode(['status' => 'success', 'decimals' => $decimals]);
    exit;
}
?>
