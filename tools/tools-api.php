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

    // Handle Solscan public APIs
    if ($endpoint === 'getTokenHolders') {
        if (empty($params['mint'])) return ['error' => 'Missing token mint address'];
        $url = "https://public-api.solscan.io/token/holders?tokenAddress={$params['mint']}&limit=1&offset=0";
        $method = 'GET';
    } elseif ($endpoint === 'getTokenInfo') {
        if (empty($params['mint'])) return ['error' => 'Missing token mint address'];
        $url = "https://public-api.solscan.io/token/meta?tokenAddress={$params['mint']}";
        $method = 'GET';
    } elseif ($endpoint === 'getTokenTxCount') {
        if (empty($params['mint'])) return ['error' => 'Missing token mint address'];
        $url = "https://public-api.solscan.io/account/tokens?account={$params['mint']}";
        $method = 'GET';
    } elseif ($endpoint === 'getNamesByAddress') {
        $url = "$helius_api_url/addresses/{$params['address']}/names?api-key=$helius_api_key";
        $log_url = str_replace($helius_api_key, '****', $url);
        $method = 'GET';
    } else {
        $url = $helius_rpc_url;
    }

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

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, ($endpoint === 'getNamesByAddress') ? 90 : 30);
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
            log_message("api-helper: GET request - URL: $url, Retry: $retry_count/$max_retries", 'tools_api_log.txt');
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $curlError = curl_error($ch);
            log_message("api-error: cURL error: $curlError, URL: $url, Retry: $retry_count/$max_retries", 'tools_api_log.txt', 'ERROR');
            curl_close($ch);
            return ['error' => 'cURL error: ' . $curlError];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        curl_close($ch);

        log_message("api-helper: Response - HTTP: $httpCode, URL: $url, Body: " . substr($body, 0, 500) . "...", 'tools_api_log.txt');

        if (in_array($httpCode, [429, 504])) {
            log_message("api-helper: HTTP $httpCode, retrying ($retry_count/$max_retries), URL: $url", 'tools_api_log.txt', 'WARNING');
            if ($retry_count < $max_retries) {
                usleep($retry_delays[$retry_count]);
                $retry_count++;
                continue;
            }
            return ['error' => "Failed to fetch data from API. HTTP Code: $httpCode after retries."];
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
            log_message("api-error: API error - $errorMessage, URL: $url", 'tools_api_log.txt', 'ERROR');
            return ['error' => $errorMessage];
        }

        log_message("api-success: API success - Endpoint: $endpoint, URL: $url, Response: " . substr(json_encode($data), 0, 100) . "...", 'tools_api_log.txt');
        return $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached.'];
}
?>
