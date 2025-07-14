<?php
// File: tools/tools-api.php
// Description: Universal wrapper to call Helius RPC and API endpoints on Solana.
// Created by: Vina Network
// ============================================================================

define('VINANETWORK', true);
define('VINANETWORK_ENTRY', true);
require_once 'bootstrap.php';

function callAPI($endpoint, $params = [], $method = 'POST') {
    $helius_api_key = HELIUS_API_KEY;
    $helius_rpc_url = "https://mainnet.helius-rpc.com/?api-key=$helius_api_key";
    $helius_api_url = "https://api.helius.xyz/v0";
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

    log_message("api-helper: PHP version: " . phpversion() . ", cURL version: " . curl_version()['version'], 'tools_api_log.txt');

    $max_retries = $endpoint === 'getNamesByAddress' ? 5 : 3;
    $retry_count = 0;
    $retry_delays = [2000000, 5000000, 10000000, 15000000, 20000000];

    do {
        $ch = curl_init();
        if (!$ch) {
            log_message("api-helper: cURL initialization failed.", 'tools_api_log.txt', 'ERROR');
            return ['error' => 'Failed to initialize cURL.'];
        }

        $url = $helius_rpc_url;

        // Handle GET or REST endpoint overrides
        if ($endpoint === 'getNamesByAddress') {
            $url = "$helius_api_url/addresses/{$params['address']}/names?api-key=$helius_api_key";
            $log_url = str_replace($helius_api_key, '****', $url);
            $method = 'GET';
        } elseif ($endpoint === 'searchAssetsTransfers') {
            $url = "$helius_api_url/token-transfers?api-key=$helius_api_key";
            $method = 'POST';
        } elseif ($endpoint === 'searchAssets') {
            $url = "$helius_api_url/assets/search?api-key=$helius_api_key";
            $method = 'POST';
        } elseif ($endpoint === 'searchTransactions') {
            $url = "$helius_api_url/transactions/search?api-key=$helius_api_key";
            $method = 'POST';
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $endpoint === 'getNamesByAddress' ? 90 : 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        // Prepare POST data
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);

            if (!empty($params)) {
                if (in_array($endpoint, ['searchAssetsTransfers', 'searchAssets', 'searchTransactions'])) {
                    // REST API expects raw JSON (not JSON-RPC)
                    $postData = json_encode($params);
                    log_message("api-helper: REST API request - Endpoint: $endpoint, URL: $url, Params: " . substr($postData, 0, 100) . "...", 'tools_api_log.txt');
                } else {
                    // RPC API expects JSON-RPC format
                    $postData = json_encode([
                        'jsonrpc' => '2.0',
                        'id' => '1',
                        'method' => $endpoint,
                        'params' => $params
                    ]);
                    $postDataArray = json_encode([
                        'jsonrpc' => '2.0',
                        'id' => '1',
                        'method' => $endpoint,
                        'params' => [$params]
                    ]);
                    log_message("api-helper: RPC API request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 100) . "...", 'tools_api_log.txt');
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }
        } elseif ($method === 'GET') {
            log_message("api-helper: GET request - URL: $log_url, Retry: $retry_count/$max_retries", 'tools_api_log.txt');
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $curlError = curl_error($ch);
            log_message("api-error: cURL error: $curlError, URL: $log_url", 'tools_api_log.txt', 'ERROR');
            curl_close($ch);
            return ['error' => 'cURL error: ' . $curlError];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        curl_close($ch);

        if (preg_match('/X-Rate-Limit-Remaining: (\d+)/i', $headers, $matches)) {
            $remaining = (int)$matches[1];
            if ($remaining < 10) {
                log_message("api-helper: Low rate limit remaining ($remaining), pausing...", 'tools_api_log.txt', 'WARNING');
                usleep(1000000);
            }
        }

        log_message("api-helper: Response - HTTP: $httpCode, URL: $url, Body: " . substr($body, 0, 500) . "...", 'tools_api_log.txt');

        if (in_array($httpCode, [429, 504])) {
            if ($retry_count < $max_retries) {
                log_message("api-helper: HTTP $httpCode, retrying ($retry_count/$max_retries), Delay: " . ($retry_delays[$retry_count] / 1000000) . "s", 'tools_api_log.txt', 'WARNING');
                usleep($retry_delays[$retry_count]);
                $retry_count++;
                continue;
            }
            return ['error' => "Failed to fetch data from API. HTTP Code: $httpCode after retries."];
        }

        // Retry with array-style param if RPC API failed with 404
        if ($httpCode === 404 && isset($postDataArray)) {
            log_message("api-helper: Retry with array-style params after 404. Endpoint: $endpoint", 'tools_api_log.txt');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataArray);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headers = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            curl_close($ch);
        }

        if ($httpCode !== 200) {
            log_message("api-error: API request failed - HTTP: $httpCode, URL: $url, Response: $body", 'tools_api_log.txt', 'ERROR');
            return ['error' => "Failed to fetch data from API. HTTP Code: $httpCode"];
        }

        $data = json_decode($body, true);
        if ($data === null) {
            log_message("api-error: Failed to parse API response as JSON. URL: $url, Response: $body", 'tools_api_log.txt', 'ERROR');
            return ['error' => 'Failed to parse API response as JSON.'];
        }

        if (isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("api-error: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $errorMessage, URL: $url", 'tools_api_log.txt', 'ERROR');
            return ['error' => $errorMessage];
        }

        log_message("api-success: API success - Endpoint: $endpoint, URL: $url, Response: " . substr(json_encode($data), 0, 100) . "...", 'tools_api_log.txt');
        return $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached.'];
}
?>
