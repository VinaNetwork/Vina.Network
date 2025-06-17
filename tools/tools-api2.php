<?php
// ==========================================================================
// File: tools/tools-api2.php
// Description: API wrapper for Helius v0 endpoints and RPC (NFT Transactions)
// Created by: Vina Network
// Updated: 2025-06-17 - Fix endpoint v0/addresses; add getAsset support
// ==========================================================================

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) define('VINANETWORK', true);

$bootstrap_path = __DIR__ . '/../include/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("tools-api2: bootstrap.php not found at $bootstrap_path", 'api_log2.txt', 'ERROR');
    die(json_encode(['error' => 'Internal Server Error']));
}
require_once $bootstrap_path;

session_start();

function callAPI2($endpoint, $params, $method = 'GET') {
    $api_key = '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63';
    $base_url = 'https://api.helius.xyz/';
    $rpc_url = 'https://mainnet.helius-rpc.com/';
    $url = $base_url . $endpoint;

    // Handle v0/addresses endpoint
    if ($endpoint === 'v0/addresses' && isset($params['address'])) {
        $url = $base_url . 'v0/addresses/' . urlencode($params['address']) . '/transactions';
        unset($params['address']);
    }
    // Handle getAsset RPC
    elseif ($endpoint === 'getAsset') {
        $url = $rpc_url;
        $method = 'POST';
        $params = ['jsonrpc' => '2.0', 'id' => '1', 'method' => 'getAsset', 'params' => $params];
    }

    $log_params = $params;
    if (isset($log_params['api_key'])) $log_params['api_key'] = '*';
    log_message("api2.php: $method request - URL: $url, Params: " . json_encode($log_params), 'api_log2.txt');

    $headers = ['Content-Type: application/json'];
    $ch = curl_init();

    if ($method === 'GET') {
        $params['api_key'] = $api_key;
        $url .= '?' . http_build_query($params);
        curl_setopt($ch, CURLOPT_URL, $url);
    } else {
        $params['api_key'] = $api_key;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    log_message("api2.php: Response - HTTP: $http_code, URL: $url, Body: " . substr($response, 0, 256), 'api_log2.txt');

    if ($http_code >= 400) {
        log_message("api2.php: API error ($http_code) - $url: $response", 'api_log2.txt', 'ERROR');
        return json_encode(['error' => ['code' => $http_code, 'message' => json_decode($response, true)['error']['message'] ?? 'API error']]);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("api2.php: JSON decode error - URL: $url, Response: $response", 'api_log2.txt', 'ERROR');
        return json_encode(['error' => ['json_decode' => json_last_error_msg()]]);
    }

    // Extract result for RPC calls
    if (isset($data['result'])) {
        $data = $data['result'];
    }

    log_message("api2.php: API success - Endpoint: $endpoint, URL: $url, Response: " . json_encode($data, JSON_UNESCAPED_SLASHES), 'api_log2.txt');
    return $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("api2.php: Invalid JSON input: " . json_last_error_msg(), 'api_log2.txt', 'ERROR');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $endpoint = $input['endpoint'] ?? '';
    $params = $input['params'] ?? [];
    if (empty($endpoint)) {
        log_message("api2.php: Missing endpoint in request", 'api_log2.txt', 'ERROR');
        http_response_code(400);
        echo json_encode(['error' => 'Missing endpoint']);
        exit;
    }

    $response = callAPI2($endpoint, $params, $endpoint === 'getAsset' ? 'POST' : 'GET');
    header('Content-Type: application/json');
    echo is_string($response) ? $response : json_encode($response);
}
?>
