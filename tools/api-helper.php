<?php
// tools/api-helper.php
// Yêu cầu VINANETWORK_STATUS để ngăn truy cập trực tiếp
if (!defined('VINANETWORK_STATUS')) {
    http_response_code(403);
    exit('No direct script access allowed!');
}

// Include bootstrap để load config
require_once __DIR__ . '/../config/bootstrap.php';

// Hàm ghi log
function log_message($message, $type = 'error') {
    $log_path = ($type === 'debug') ? DEBUG_LOG_PATH : ERROR_LOG_PATH;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp UTC] api-helper.php: $message" . PHP_EOL;
    file_put_contents($log_path, $log_entry, FILE_APPEND | LOCK_EX);
}

// Hàm gọi API Helius
function callHeliusAPI($endpoint, $params = [], $method = 'POST') {
    $url = "https://mainnet.helius-rpc.com/?api-key=" . HELIUS_API_KEY;
    
    $ch = curl_init();
    if (!$ch) {
        log_message("cURL initialization failed.");
        return ['error' => 'Failed to initialize cURL.'];
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($params)) {
            $postData = json_encode([
                'jsonrpc' => '2.0',
                'id' => '1',
                'method' => $endpoint,
                'params' => $params
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            log_message("Helius API request - Endpoint: $endpoint, Params: " . substr($postData, 0, 100) . "...", 'debug');
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    if ($response === false) {
        $curlError = curl_error($ch);
        log_message("cURL error: $curlError");
        curl_close($ch);
        return ['error' => 'cURL error: ' . $curlError];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        log_message("Helius API request failed with HTTP code: $httpCode, Response: $response");
        return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
    }
    
    $data = json_decode($response, true);
    if ($data === null) {
        log_message("Failed to parse Helius API response as JSON. Response: $response");
        return ['error' => 'Failed to parse API response as JSON.'];
    }
    
    if (isset($data['error'])) {
        log_message("Helius API error - Code: {$data['error']['code']}, Message: {$data['error']['message']}");
        return ['error' => $data['error']['message']];
    }
    
    log_message("Helius API success - Endpoint: $endpoint", 'debug');
    return $data;
}

// Ghi log phiên bản PHP và cURL
log_message("PHP version: " . phpversion(), 'debug');
log_message("cURL version: " . curl_version()['version'], 'debug');
?>
