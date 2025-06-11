<?php
define('VINANETWORK_STATUS', true);
require_once 'bootstrap.php';

log_message('api-helper.php: Script started');
log_message('api-helper.php: PHP version: ' . phpversion());
log_message('api-helper.php: cURL version: ' . curl_version()['version']);

// Hàm gọi API Helius
function callHeliusAPI($endpoint, $params = [], $method = 'POST') {
    $url = "https://mainnet.helius-rpc.com/?api-key=" . HELIUS_API_KEY;

    $ch = curl_init();
    if (!$ch) {
        log_message("api-helper.php: cURL initialization failed", 'error_log.txt', 'ERROR');
        return ['error' => 'Failed to initialize cURL.'];
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
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
            log_message("api-helper.php: Helius API request - Endpoint: $endpoint, Params: " . substr($postData, 0, 100) . "...");
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    if ($response === false) {
        $curlError = curl_error($ch);
        log_message("api-helper.php: cURL error: $curlError", 'error_log.txt', 'ERROR');
        curl_close($ch);
        return ['error' => 'cURL error: ' . $curlError];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        log_message("api-helper.php: Helius API request failed with HTTP code: $httpCode, Response: $response", 'error_log.txt', 'ERROR');
        return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
    }

    $data = json_decode($response, true);
    if ($data === null) {
        log_message("api-helper.php: Failed to parse Helius API response as JSON. Response: $response", 'error_log.txt', 'ERROR');
        return ['error' => 'Failed to parse API response as JSON.'];
    }

    if (isset($data['error'])) {
        log_message("api-helper.php: Helius API error - Code: {$data['error']['code']}, Message: {$data['error']['message']}", 'error_log.txt', 'ERROR');
        return ['error' => $data['error']['message']];
    }

    log_message("api-helper.php: Helius API success - Endpoint: $endpoint");
    return $data;
}
?>
