<?php
// File: tools/tools-api.php
// Description: Universal wrapper to call Helius RPC API on Solana.
// Created by: Vina Network
// ============================================================================

// Define project constants for secured includes
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}
require_once 'bootstrap.php';

function callAPI($endpoint, $params = [], $method = 'POST') {
    $url = "https://mainnet.helius-rpc.com/?api-key=" . HELIUS_API_KEY;
    // Mask API key for logging
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

    // Log PHP and cURL versions for debugging
    log_message("api_helper: PHP version: " . phpversion() . ", cURL version: " . curl_version()['version'], 'api_log.txt', true);

    $max_retries = 3;
    $retry_count = 0;

    do {
        $ch = curl_init();
        if (!$ch) {
            log_message("api_error: cURL initialization failed.", 'api_log.txt', true);
            return ['error' => 'Failed to initialize cURL.'];
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);

            if (!empty($params)) {
                // Prepare standard JSON-RPC payload
                $postData = json_encode([
                    'jsonrpc' => '2.0',
                    'id' => '1',
                    'method' => $endpoint,
                    'params' => $params
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                log_message("api_helper: POST request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 500) . "...", 'api_log.txt', true);
            }

        } elseif ($method === 'GET') {
            if (!empty($params)) {
                $url .= '&' . http_build_query($params);
                $log_url .= '&' . http_build_query($params);
                curl_setopt($ch, CURLOPT_URL, $url);
            }
            log_message("api_helper: GET request - URL: $log_url", 'api_log.txt', true);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        log_message("api_helper: Response - HTTP: $http_code, URL: $log_url, Body: " . substr($response, 0, 1000) . "...", 'api_log.txt', true);

        if ($response === false) {
            log_message("api_error: cURL error - Code: $curl_errno, Message: $curl_error, URL: $log_url", 'api_log.txt', true);
            return ['error' => "cURL error: $curl_error (Code: $curl_errno)"];
        }

        if ($http_code === 429) {
            log_message("api_helper: Rate limit exceeded (429), retrying ($retry_count/$max_retries), URL: $log_url", 'api_log.txt', true);
            if ($retry_count < $max_retries) {
                $retry_count++;
                usleep(2000000); // Wait 2 seconds
                continue;
            }
            return ['error' => 'Rate limit exceeded after retries.'];
        }

        if ($http_code !== 200) {
            log_message("api_error: API request failed - HTTP: $http_code, URL: $log_url, Response: " . substr($response, 0, 1000), 'api_log.txt', true);
            return ['error' => "Failed to fetch data from API. HTTP Code: $http_code"];
        }

        $data = json_decode($response, true);
        if ($data === null) {
            log_message("api_error: Failed to parse API response as JSON - Error: " . json_last_error_msg() . ", URL: $log_url, Response: " . substr($response, 0, 1000), 'api_log.txt', true);
            return ['error' => 'Failed to parse API response as JSON: ' . json_last_error_msg()];
        }

        if (isset($data['error'])) {
            $error_message = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("api_error: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $error_message, URL: $log_url", 'api_log.txt', true);
            return ['error' => $error_message];
        }

        log_message("api_success: API success - Endpoint: $endpoint, URL: $log_url, Response: " . substr(json_encode($data, JSON_PRETTY_PRINT), 0, 1000) . "...", 'api_log.txt', true);
        // Return result for JSON-RPC endpoints
        return isset($data['result']) ? $data['result'] : $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached.'];
}
?>
