<?php
// File: tools/tools-api.php
// Description: Universal wrapper to call Helius RPC and API endpoints on Solana.
// Created by: Vina Network

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);
require_once 'bootstrap.php';

function callAPI($endpoint, $params = [], $method = 'POST') {
    $helius_api_key = HELIUS_API_KEY;
    $helius_rpc_url = "https://mainnet.helius-rpc.com/?api-key=$helius_api_key";
    $helius_api_url = "https://api.helius.xyz/v0";
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

    log_message("api-helper: PHP version: " . phpversion() . ", cURL version: " . curl_version()['version'], 'tools_api_log.txt');

    $max_retries = $endpoint === 'getNamesByAddress' ? 5 : 3;
    $retry_count = 0;
    $retry_delays = $endpoint === 'getNamesByAddress' ? [2000000, 5000000, 10000000, 15000000, 20000000] : [2000000, 5000000, 10000000];

    do {
        $ch = curl_init();
        if (!$ch) {
            log_message("api-helper: cURL initialization failed.", 'tools_api_log.txt', 'ERROR');
            return ['error' => 'Failed to initialize cURL.'];
        }

        $url = $helius_rpc_url;
        if ($endpoint === 'getNamesByAddress') {
            $url = "$helius_api_url/addresses/{$params['address']}/names?api-key=$helius_api_key";
            $log_url = str_replace($helius_api_key, '****', $url);
            $method = 'GET';
        } elseif ($endpoint === 'transactions') {
            $url = "$helius_api_url/addresses/{$params['address']}/transactions?api-key=$helius_api_key";
            if (isset($params['before'])) {
                $url .= "&before={$params['before']}";
            }
            $log_url = str_replace($helius_api_key, '****', $url);
            $method = 'GET';
            log_message("api-helper: Fetching transactions batch, before=" . ($params['before'] ?? 'none'), 'tools_api_log.txt', 'INFO');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $endpoint === 'transactions' ? 60 : ($endpoint === 'getNamesByAddress' ? 90 : 30));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (!empty($params)) {
                $actualParams = $params;
                if ($endpoint === 'getSignaturesForAsset') {
                    if (!isset($params['id'])) {
                        log_message("api-error: Missing 'id' for getSignaturesForAsset", 'tools_api_log.txt', 'ERROR');
                        return ['error' => "Missing 'id' for getSignaturesForAsset"];
                    }
                    $actualParams = (object)['id' => $params['id']];
                }
                $postData = json_encode([
                    'jsonrpc' => '2.0',
                    'id' => '1',
                    'method' => $endpoint,
                    'params' => $actualParams
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                log_message("api-helper: API request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 200) . "...", 'tools_api_log.txt');
            }
        } elseif ($method === 'GET') {
            log_message("api-helper: GET request - URL: $log_url, Endpoint: $endpoint, Retry: $retry_count/$max_retries", 'tools_api_log.txt');
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $curlError = curl_error($ch);
            log_message("api-error: cURL error: $curlError, URL: $log_url, Retry: $retry_count/$max_retries", 'tools_api_log.txt', 'ERROR');
            curl_close($ch);
            return ['error' => 'cURL error: ' . $curlError];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        $response_size = strlen($body);
        curl_close($ch);

        log_message("api-helper: Response - HTTP: $httpCode, URL: $log_url, Size: $response_size bytes, Body: " . substr($body, 0, 500) . "...", 'tools_api_log.txt');

        if ($response_size > 10485760) { // 10MB limit
            log_message("api-error: Response too large: $response_size bytes, URL: $log_url", 'tools_api_log.txt', 'ERROR');
            return ['error' => 'Response too large, please try again later.'];
        }

        if (in_array($httpCode, [429, 504])) {
            log_message("api-helper: HTTP $httpCode, retrying ($retry_count/$max_retries), URL: $log_url, Delay: " . ($retry_delays[$retry_count] / 1000000) . "s", 'tools_api_log.txt', 'WARNING');
            if ($retry_count < $max_retries) {
                usleep($retry_delays[$retry_count]);
                $retry_count++;
                continue;
            }
            return ['error' => "Failed to fetch data from API. HTTP Code: $httpCode after retries."];
        }

        if ($httpCode !== 200) {
            log_message("api-error: API request failed - HTTP: $httpCode, URL: $log_url, Response: $body", 'tools_api_log.txt', 'ERROR');
            return ['error' => "Failed to fetch data from API. HTTP Code: $httpCode"];
        }

        $data = json_decode($body, true);
        if ($data === null) {
            log_message("api-error: Failed to parse API response as JSON. URL: $log_url, Response: $body", 'tools_api_log.txt', 'ERROR');
            return ['error' => 'Failed to parse API response as JSON.'];
        }

        log_message("api-helper: Full response - Endpoint: $endpoint, URL: $log_url, Response: " . json_encode($data), 'tools_api_log.txt', 'DEBUG');

        if (isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("api-error: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $errorMessage, URL: $log_url", 'tools_api_log.txt', 'ERROR');
            return ['error' => $errorMessage];
        }

        log_message("api-success: API success - Endpoint: $endpoint, URL: $log_url, Response: " . substr(json_encode($data), 0, 100) . "...", 'tools_api_log.txt');
        return $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached.'];
}
?>
