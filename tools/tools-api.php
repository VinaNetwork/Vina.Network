<?php
// File: tools/tools-api.php
// Description: Universal wrapper to call Helius RPC API on Solana.
// Created by: Vina Network
// ============================================================================

define('VINANETWORK', true);
define('VINANETWORK_ENTRY', true);
require_once 'bootstrap.php';

function callAPI($endpoint, $params = [], $method = 'POST') {
    $api_key = HELIUS_API_KEY;
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

    // Log PHP and cURL versions
    log_message("api-helper: PHP version: " . phpversion() . ", cURL version: " . curl_version()['version'], 'api_log.txt');

    $max_retries = 3;
    $retry_count = 0;

    do {
        $ch = curl_init();
        if (!$ch) {
            log_message("api-helper: cURL initialization failed.", 'api_log.txt', 'ERROR');
            return ['error' => 'Failed to initialize cURL.'];
        }

        // Handle different endpoints
        if ($endpoint === 'transactions') {
            // GET request for transactions endpoint
            $url = "https://api.helius.xyz/v0/addresses/{$params['address']}/transactions?api-key=$api_key";
            if (isset($params['limit'])) {
                $url .= "&limit={$params['limit']}";
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            log_message("api-helper: GET request - URL: " . str_replace($api_key, '****', $url), 'api_log.txt');
        } else {
            // POST request for JSON-RPC endpoints
            $url = "https://mainnet.helius-rpc.com/?api-key=$api_key";
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);

            if (!empty($params)) {
                $postData = json_encode([
                    'jsonrpc' => '2.0',
                    'id' => '1',
                    'method' => $endpoint,
                    'params' => $params
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                log_message("api-helper: API request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 100) . "...", 'api_log.txt');
            }
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        if ($response === false) {
            $curlError = curl_error($ch);
            log_message("api-error: cURL error: $curlError, URL: " . str_replace($api_key, '****', $url), 'api_log.txt', 'ERROR');
            curl_close($ch);
            return ['error' => 'cURL error: ' . $curlError];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        log_message("api-helper: Response - HTTP: $httpCode, URL: " . str_replace($api_key, '****', $url) . ", Body: " . substr($response, 0, 500) . "...", 'api_log.txt');

        if ($httpCode === 429) {
            log_message("api-helper: Rate limit exceeded (429), retrying ($retry_count/$max_retries), URL: " . str_replace($api_key, '****', $url), 'api_log.txt', 'WARNING');
            if ($retry_count < $max_retries) {
                $retry_count++;
                usleep(2000000); // Wait 2 seconds
                continue;
            }
            return ['error' => 'Rate limit exceeded after retries.'];
        }

        if ($httpCode !== 200) {
            log_message("api-error: API request failed - HTTP: $httpCode, URL: " . str_replace($api_key, '****', $url) . ", Response: $response", 'api_log.txt', 'ERROR');
            return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
        }

        $data = json_decode($response, true);
        if ($data === null) {
            log_message("api-error: Failed to parse API response as JSON. URL: " . str_replace($api_key, '****', $url) . ", Response: $response", 'api_log.txt', 'ERROR');
            return ['error' => 'Failed to parse API response as JSON.'];
        }

        if (isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("api-error: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $errorMessage, URL: " . str_replace($api_key, '****', $url), 'api_log.txt', 'ERROR');
            return ['error' => $errorMessage];
        }

        log_message("api-success: API success - Endpoint: $endpoint, URL: " . str_replace($api_key, '****', $url) . ", Response: " . substr(json_encode($data), 0, 100) . "...", 'api_log.txt');
        return $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached.'];
}
?>
