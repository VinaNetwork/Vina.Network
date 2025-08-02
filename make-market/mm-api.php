<?php
// ============================================================================
// File: make-market/mm-api.php
// Description: API wrapper for Make Market to call Helius JSON-RPC methods
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';

header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

if (!isset($_SESSION['user_id'])) {
    log_message('Unauthorized access to mm-api.php', 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$endpoint = $input['endpoint'] ?? '';
$params = $input['params'] ?? [];

if (empty($endpoint)) {
    log_message("Missing endpoint in mm-api.php request", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing endpoint']);
    exit;
}

// Validate endpoint
$allowed_endpoints = ['getAccountInfo', 'getAssetsByOwner', 'getTransaction'];
if (!in_array($endpoint, $allowed_endpoints)) {
    log_message("Invalid endpoint: $endpoint", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid endpoint']);
    exit;
}

function callMarketAPI($endpoint, $params = []) {
    $helius_api_key = HELIUS_API_KEY;
    $helius_rpc_url = "https://mainnet.helius-rpc.com/?api-key=$helius_api_key";
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

    log_message("make-market-api: PHP version: " . phpversion() . ", cURL version: " . curl_version()['version'], 'make-market.log', 'make-market', 'DEBUG');

    $max_retries = 3;
    $retry_count = 0;
    $retry_delays = [2000000, 5000000, 10000000]; // 2s, 5s, 10s

    do {
        $ch = curl_init();
        if (!$ch) {
            log_message("make-market-api: cURL initialization failed.", 'make-market.log', 'make-market', 'ERROR');
            return ['error' => 'Failed to initialize cURL'];
        }

        curl_setopt($ch, CURLOPT_URL, $helius_rpc_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $postData = json_encode([
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => $endpoint,
            'params' => $params
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        log_message("make-market-api: API request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 200) . "...", 'make-market.log', 'make-market', 'DEBUG');

        $response = curl_exec($ch);
        if ($response === false) {
            $curlError = curl_error($ch);
            log_message("make-market-api: cURL error: $curlError, URL: $log_url, Retry: $retry_count/$max_retries", 'make-market.log', 'make-market', 'ERROR');
            curl_close($ch);
            if ($retry_count < $max_retries) {
                usleep($retry_delays[$retry_count]);
                $retry_count++;
                continue;
            }
            return ['error' => 'cURL error: ' . $curlError];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        $response_size = strlen($body);
        curl_close($ch);

        log_message("make-market-api: Response - HTTP: $httpCode, URL: $log_url, Size: $response_size bytes, Body: " . substr($body, 0, 500) . "...", 'make-market.log', 'make-market', 'DEBUG');

        if ($response_size > 10485760) { // 10MB limit
            log_message("make-market-api: Response too large: $response_size bytes, URL: $log_url", 'make-market.log', 'make-market', 'ERROR');
            return ['error' => 'Response too large, please try again later'];
        }

        if (in_array($httpCode, [429, 504])) {
            log_message("make-market-api: HTTP $httpCode, retrying ($retry_count/$max_retries), URL: $log_url, Delay: " . ($retry_delays[$retry_count] / 1000000) . "s", 'make-market.log', 'make-market', 'WARNING');
            if ($retry_count < $max_retries) {
                usleep($retry_delays[$retry_count]);
                $retry_count++;
                continue;
            }
            return ['error' => "Failed to fetch data from API after $max_retries retries. HTTP Code: $httpCode"];
        }

        if ($httpCode !== 200) {
            $errorMsg = $httpCode === 401 ? 'Unauthorized: Invalid or expired Helius API key' : "HTTP $httpCode: Failed to fetch data from API";
            log_message("make-market-api: API request failed - HTTP: $httpCode, URL: $log_url, Response: $body", 'make-market.log', 'make-market', 'ERROR');
            return ['error' => $errorMsg];
        }

        $data = json_decode($body, true);
        if ($data === null) {
            log_message("make-market-api: Failed to parse API response as JSON. URL: $log_url, Response: $body", 'make-market.log', 'make-market', 'ERROR');
            return ['error' => 'Failed to parse API response as JSON'];
        }

        log_message("make-market-api: Full response - Endpoint: $endpoint, URL: $log_url, Response: " . json_encode($data), 'make-market.log', 'make-market', 'DEBUG');

        if (isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("make-market-api: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $errorMessage, URL: $log_url", 'make-market.log', 'make-market', 'ERROR');
            return ['error' => $errorMessage];
        }

        log_message("make-market-api: API success - Endpoint: $endpoint, URL: $log_url, Response: " . substr(json_encode($data), 0, 100) . "...", 'make-market.log', 'make-market', 'INFO');
        return $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached'];
}

// Handle API request
try {
    $pdo = get_db_connection();
    log_message("Database connection established for mm-api.php", 'make-market.log', 'make-market', 'INFO');

    if ($endpoint === 'getAccountInfo' && !empty($params[0])) {
        // Validate token mint address format
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $params[0])) {
            log_message("Invalid token mint address format: {$params[0]}", 'make-market.log', 'make-market', 'ERROR');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid token mint address format']);
            exit;
        }
    }

    // Call Helius API
    $result = callMarketAPI($endpoint, $params);
    if (isset($result['error'])) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $result['error']]);
        exit;
    }

    echo json_encode(['status' => 'success', 'result' => $result]);
} catch (Exception $e) {
    log_message("Error in mm-api.php: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
exit;
?>
