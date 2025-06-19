<?php
// File: tools/tools-api.php
// Description: Universal wrapper to call Helius RPC API on Solana.
// Created by: Vina Network
// ============================================================================

// Define project constants for secured includes
define('VINANETWORK', true);
define('VINANETWORK_ENTRY', true);
require_once 'bootstrap.php';

function callAPI($endpoint, $params = [], $method = 'POST') {
    $url = "https://mainnet.helius-rpc.com/?api-key=" . HELIUS_API_KEY;
    // Mask API key for logging
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

    // Log PHP and cURL versions for debugging
    log_message("api-helper: PHP version: " . phpversion() . ", cURL version: " . curl_version()['version'], 'api_log.txt');

    $max_retries = 3;
    $retry_count = 0;

    do {
        $ch = curl_init();
        if (!$ch) {
            log_message("api-helper: cURL initialization failed.", 'api_log.txt', 'ERROR');
            return ['error' => 'Failed to initialize cURL.'];
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);

            if (!empty($params)) {
                // Prepare JSON-RPC payload based on endpoint
                if ($endpoint === 'getAssetActivity') {
                    // getAssetActivity requires params as array of values
                    $postData = json_encode([
                        'jsonrpc' => '2.0',
                        'id' => '1',
                        'method' => $endpoint,
                        'params' => [isset($params['id']) ? $params['id'] : '']
                    ]);
                } else {
                    // Standard payload for other endpoints (e.g., getAssetsByGroup)
                    $postData = json_encode([
                        'jsonrpc' => '2.0',
                        'id' => '1',
                        'method' => $endpoint,
                        'params' => $params
                    ]);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                log_message("api-helper: API request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 100) . "...", 'api_log.txt');
            }
        } elseif ($method === 'GET') {
            if (!empty($params)) {
                $url .= '&' . http_build_query($params);
                $log_url .= '&' . http_build_query($params); // Update log_url for GET params
                curl_setopt($ch, CURLOPT_URL, $url);
            }
            log_message("api-helper: GET request - URL: $log_url", 'api_log.txt');
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $curlError = curl_error($ch);
            log_message("api-error: cURL error: $curlError, URL: $log_url", 'api_log.txt', 'ERROR');
            curl_close($ch);
            return ['error' => 'cURL error: ' . $curlError];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);

        // Check for rate limit from response headers
        if (preg_match('/X-Rate-Limit-Remaining: (\d+)/i', $headers, $matches)) {
            $remaining = (int)$matches[1];
            if ($remaining < 10) {
                log_message("api-helper: Low rate limit remaining ($remaining), pausing..., URL: $log_url", 'api_log.txt', 'WARNING');
                usleep(1000000); // Wait 1 second
            }
        }

        curl_close($ch);

        log_message("api-helper: Response - HTTP: $httpCode, URL: $log_url, Body: " . substr($response, 0, 500) . "...", 'api_log.txt');

        // Retry if rate limited (HTTP 429)
        if ($httpCode === 429) {
            log_message("api-helper: Rate limit exceeded (429), retrying ($retry_count/$max_retries), URL: $log_url", 'api_log.txt', 'WARNING');
            if ($retry_count < $max_retries) {
                $retry_count++;
                usleep(2000000); // Wait 2 seconds
                continue;
            }
            return ['error' => 'Rate limit exceeded after retries.'];
        }

        if ($httpCode !== 200) {
            log_message("api-error: API request failed - HTTP: $httpCode, URL: $log_url, Response: $response", 'api_log.txt', 'ERROR');
            return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
        }

        // Decode JSON response
        $data = json_decode($response, true);
        if ($data === null) {
            log_message("api-error: Failed to parse API response as JSON. URL: $log_url, Response: $response", 'api_log.txt', 'ERROR');
            return ['error' => 'Failed to parse API response as JSON.'];
        }

        // Return error if API-level error exists
        if (isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("api-error: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $errorMessage, URL: $log_url", 'api_log.txt', 'ERROR');
            return ['error' => $errorMessage];
        }

        // Success: log and return data
        log_message("api-success: API success - Endpoint: $endpoint, URL: $log_url, Response: " . substr(json_encode($data), 0, 100) . "...", 'api_log.txt');
        return $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached.'];
}
?>
