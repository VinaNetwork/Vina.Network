<?php
define('VINANETWORK', true);
define('VINANETWORK_ENTRY', true);
require_once 'bootstrap.php';

function callAPI($endpoint, $params = [], $method = 'POST') {
    $url = "https://mainnet.helius-rpc.com/?api-key=" . HELIUS_API_KEY;
    $ch = curl_init();
    if (!$ch) {
        log_message("api-helper: cURL initialization failed.", 'api_log.txt', 'ERROR');
        return ['error' => 'Failed to initialize cURL.'];
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if (!empty($params)) {
            $postData = json_encode([
                'jsonrpc' => '2.0',
                'id' => '1',
                'method' => $endpoint,
                'params' => $params
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            log_message("api-helper: API request - URL: $url, Endpoint: $endpoint, Params: " . substr($postData, 0, 100) . "...", 'api_log.txt');
        }
    } elseif ($method === 'GET') {
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        log_message("api-helper: GET request - URL: $url", 'api_log.txt');
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $curlError = curl_error($ch);
        log_message("api-error: cURL error: $curlError", 'api_log.txt', 'ERROR');
        curl_close($ch);
        return ['error' => 'cURL error: ' . $curlError];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    log_message("api-helper: Response - HTTP: $httpCode, Body: " . substr($response, 0, 200) . "...", 'api_log.txt');

    if ($httpCode !== 200) {
        log_message("api-error: API request failed - HTTP: $httpCode, Response: $response", 'api_log.txt', 'ERROR');
        return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
    }

    $data = json_decode($response, true);
    if ($data === null) {
        log_message("api-error: Failed to parse API response as JSON. Response: $response", 'api_log.txt', 'ERROR');
        return ['error' => 'Failed to parse API response as JSON.'];
    }

    if (isset($data['error'])) {
        log_message("api-error: API error - Code: {$data['error']['code']}, Message: {$data['error']['message']}", 'api_log.txt', 'ERROR');
        return ['error' => $data['error']['message']];
    }

    log_message("api-success: API success - Endpoint: $endpoint, Response: " . substr(json_encode($data), 0, 100) . "...", 'api_log.txt');
    return $data;
}
?>
