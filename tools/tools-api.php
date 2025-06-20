<?php
// File: tools/tools-api.php
// Description: Universal wrapper to call Helius RPC API on Solana.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}
require_once dirname(__DIR__) . '/bootstrap.php';

function callAPI($endpoint, $params = [], $method = 'POST') {
    $api_key = HELIUS_API_KEY;
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

    // Log PHP and cURL versions
    log_message("api_helper: PHP version: " . phpversion() . ", cURL version: " . curl_version()['version'], 'api_log.txt', true);

    $max_retries = 3;
    $retry_count = 0;

    do {
        $ch = curl_init();
        if (!$ch) {
            log_message("api_error: cURL initialization failed.", 'api_log.txt', true);
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
            log_message("api_helper: GET request - URL: " . str_replace($api_key, '****', $url), 'api_log.txt', true);
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
                log_message("api_helper: POST request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 500) . "...", 'api_log.txt', true);
            }
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        log_message("api_helper: Response - HTTP: $http_code, URL: " . str_replace($api_key, '****', $url) . ", Body: " . substr($response, 0, 1000) . "...", 'api_log.txt', true);

        if ($response === false) {
            log_message("api_error: cURL error - Code: $curl_errno, Message: $curl_error, URL: " . str_replace($api_key, '****', $url), 'api_log.txt', true);
            return ['error' => "cURL error: $curl_error (Code: $curl_errno)"];
        }

        if ($http_code === 429) {
            log_message("api_helper: Rate limit exceeded (429), retrying ($retry_count/$max_retries), URL: " . str_replace($api_key, '****', $url), 'api_log.txt', true);
            if ($retry_count < $max_retries) {
                $retry_count++;
                usleep(2000000); // Wait 2 seconds
                continue;
            }
            return ['error' => 'Rate limit exceeded after retries.'];
        }

        if ($http_code !== 200) {
            log_message("api_error: API request failed - HTTP: $http_code, URL: " . str_replace($api_key, '****', $url) . ", Response: " . substr($response, 0, 1000), 'api_log.txt', true);
            return ['error' => "API request failed. HTTP Code: $http_code, Response: $response"];
        }

        $data = json_decode($response, true);
        if ($data === null) {
            log_message("api_error: Failed to parse API response as JSON - Error: " . json_last_error_msg() . ", URL: " . str_replace($api_key, '****', $url) . ", Response: " . substr($response, 0, 1000), 'api_log.txt', true);
            return ['error' => 'Failed to parse API response as JSON: ' . json_last_error_msg()];
        }

        if (isset($data['error'])) {
            $error_message = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("api_error: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $error_message, URL: " . str_replace($api_key, '****', $url), 'api_log.txt', true);
            return ['error' => $error_message];
        }

        log_message("api_success: API success - Endpoint: $endpoint, URL: " . str_replace($api_key, '****', $url) . ", Response: " . substr(json_encode($data, JSON_PRETTY_PRINT), 0, 1000) . "...", 'api_log.txt', true);
        // Return result for JSON-RPC endpoints, full data for others
        return isset($data['result']) ? $data['result'] : $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached.'];
}
?>
