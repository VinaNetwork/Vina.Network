<?php
// File: make-market/mm-api.php
// Description: API wrapper for Make Market to call Helius JSON-RPC getAssetsByOwner
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/bootstrap.php';

function callMarketAPI($endpoint, $params = []) {
    $helius_api_key = HELIUS_API_KEY;
    $helius_rpc_url = "https://mainnet.helius-rpc.com/?api-key=$helius_api_key";
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

    log_message("make-market-api: PHP version: " . phpversion() . ", cURL version: " . curl_version()['version'], 'make-market.log', 'make-market');

    $max_retries = 3;
    $retry_count = 0;
    $retry_delays = [2000000, 5000000, 10000000]; // 2s, 5s, 10s

    do {
        $ch = curl_init();
        if (!$ch) {
            log_message("make-market-api: cURL initialization failed.", 'make-market.log', 'make-market', 'ERROR');
            return ['error' => 'Failed to initialize cURL.'];
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
        log_message("make-market-api: API request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 200) . "...", 'make-market.log', 'make-market');

        $response = curl_exec($ch);
        if ($response === false) {
            $curlError = curl_error($ch);
            log_message("make-market-api: cURL error: $curlError, URL: $log_url, Retry: $retry_count/$max_retries", 'make-market.log', 'make-market', 'ERROR');
            curl_close($ch);
            return ['error' => 'cURL error: ' . $curlError];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        $response_size = strlen($body);
        curl_close($ch);

        log_message("make-market-api: Response - HTTP: $httpCode, URL: $log_url, Size: $response_size bytes, Body: " . substr($body, 0, 500) . "...", 'make-market.log', 'make-market');

        if ($response_size > 10485760) { // 10MB limit
            log_message("make-market-api: Response too large: $response_size bytes, URL: $log_url", 'make-market.log', 'make-market', 'ERROR');
            return ['error' => 'Response too large, please try again later.'];
        }

        if (in_array($httpCode, [429, 504])) {
            log_message("make-market-api: HTTP $httpCode, retrying ($retry_count/$max_retries), URL: $log_url, Delay: " . ($retry_delays[$retry_count] / 1000000) . "s", 'make-market.log', 'make-market', 'WARNING');
            if ($retry_count < $max_retries) {
                usleep($retry_delays[$retry_count]);
                $retry_count++;
                continue;
            }
            return ['error' => "Failed to fetch data from API. HTTP Code: $httpCode after retries."];
        }

        if ($httpCode !== 200) {
            log_message("make-market-api: API request failed - HTTP: $httpCode, URL: $log_url, Response: $body", 'make-market.log', 'make-market', 'ERROR');
            return ['error' => "Failed to fetch data from API. HTTP Code: $httpCode"];
        }

        $data = json_decode($body, true);
        if ($data === null) {
            log_message("make-market-api: Failed to parse API response as JSON. URL: $log_url, Response: $body", 'make-market.log', 'make-market', 'ERROR');
            return ['error' => 'Failed to parse API response as JSON.'];
        }

        log_message("make-market-api: Full response - Endpoint: $endpoint, URL: $log_url, Response: " . json_encode($data), 'make-market.log', 'make-market', 'DEBUG');

        if (isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("make-market-api: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $errorMessage, URL: $log_url", 'make-market.log', 'make-market', 'ERROR');
            return ['error' => $errorMessage];
        }

        log_message("make-market-api: API success - Endpoint: $endpoint, URL: $log_url, Response: " . substr(json_encode($data), 0, 100) . "...", 'make-market.log', 'make-market');
        return $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached.'];
}
?>
