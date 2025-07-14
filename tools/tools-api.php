<?php
// ============================================================================
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

    $url = $helius_rpc_url;
    $log_url = $url;

    // Set special endpoint URLs
    if ($endpoint === 'getNamesByAddress') {
        $url = "$helius_api_url/addresses/{$params['address']}/names?api-key=$helius_api_key";
        $method = 'GET';
    } elseif ($endpoint === 'searchAssetsTransfers') {
        $url = "$helius_api_url/token-transfers?api-key=$helius_api_key";
        $method = 'POST';
    }

    // Mask API key in logs
    $log_url = str_replace($helius_api_key, '****', $url);

    $retry_count = 0;
    $max_retries = ($endpoint === 'getNamesByAddress') ? 5 : 3;
    $retry_delays = [2000000, 5000000, 10000000, 15000000, 20000000]; // microseconds

    do {
        $ch = curl_init();
        if (!$ch) {
            log_message("api-helper: cURL initialization failed", 'tools_api_log.txt', 'ERROR');
            return ['error' => 'Failed to initialize cURL'];
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, ($endpoint === 'getNamesByAddress') ? 90 : 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);

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

            // Use raw $params for REST-style endpoints
            if ($endpoint === 'searchAssetsTransfers') {
                $postData = json_encode($params);
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            log_message("api-helper: POST request to $log_url | Payload: " . substr($postData, 0, 500), 'tools_api_log.txt');
        } elseif ($method === 'GET') {
            log_message("api-helper: GET request to $log_url", 'tools_api_log.txt');
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            log_message("api-error: cURL failed: $err", 'tools_api_log.txt', 'ERROR');
            return ['error' => "cURL error: $err"];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        curl_close($ch);

        // Log rate limit
        if (preg_match('/X-Rate-Limit-Remaining: (\d+)/i', $headers, $matches)) {
            $remaining = (int)$matches[1];
            if ($remaining < 10) {
                log_message("api-warning: Low rate limit remaining ($remaining) on $log_url", 'tools_api_log.txt', 'WARNING');
                usleep(1000000);
            }
        }

        // Retry logic for certain status codes
        if (in_array($httpCode, [429, 504])) {
            log_message("api-warning: HTTP $httpCode on $log_url, retrying... ($retry_count)", 'tools_api_log.txt', 'WARNING');
            if ($retry_count < $max_retries) {
                usleep($retry_delays[$retry_count]);
                $retry_count++;
                continue;
            }
            return ['error' => "Failed to fetch data from API. HTTP Code: $httpCode after retries."];
        }

        // Retry fallback for RPC POST failure (404)
        if ($httpCode === 404 && $method === 'POST' && $endpoint !== 'searchAssetsTransfers') {
            log_message("api-fallback: Retrying with array-style params for $log_url", 'tools_api_log.txt');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataArray);
            curl_setopt($ch, CURLOPT_HEADER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headers = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            curl_close($ch);
            log_message("api-fallback: Response HTTP $httpCode, Body: " . substr($body, 0, 500), 'tools_api_log.txt');
        }

        if ($httpCode !== 200) {
            log_message("api-error: HTTP $httpCode from $log_url. Body: $body", 'tools_api_log.txt', 'ERROR');
            return ['error' => "Failed to fetch data from API. HTTP Code: $httpCode"];
        }

        // Parse JSON
        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message("api-error: Invalid JSON from $log_url", 'tools_api_log.txt', 'ERROR');
            return ['error' => 'Invalid JSON response from API'];
        }

        if (isset($json['error'])) {
            $errMsg = is_array($json['error']) ? ($json['error']['message'] ?? json_encode($json['error'])) : $json['error'];
            log_message("api-error: API-level error - $errMsg", 'tools_api_log.txt', 'ERROR');
            return ['error' => $errMsg];
        }

        log_message("api-success: Success from $log_url", 'tools_api_log.txt');
        return $json;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached'];
}
?>
