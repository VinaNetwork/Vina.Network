<?php
// File: tools/tools-api.php
// Description: Universal wrapper to call Helius RPC and API endpoints on Solana.
// Created by: Vina Network
// ============================================================================

// Define project constants for secured includes
define('VINANETWORK', true);
define('VINANETWORK_ENTRY', true);
require_once 'bootstrap.php';

function callAPI($endpoint, $params = [], $method = 'POST') {
    $helius_api_key = HELIUS_API_KEY;
    $helius_rpc_url = "https://mainnet.helius-rpc.com/?api-key=$helius_api_key";
    $helius_api_url = "https://api.helius.xyz/v0";
    // Mask API key for logging
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

    // Log PHP and cURL versions for debugging
    log_message("api-helper: PHP version: " . phpversion() . ", cURL version: " . curl_version()['version'], 'tools_api_log.txt');

    $max_retries = 3;
    $retry_count = 0;

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
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Include response headers

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
                log_message("api-helper: API request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 100) . "...", 'tools_api_log.txt');

                // Prepare alternative format (array payload) in case standard fails
                $postDataArray = json_encode([
                    'jsonrpc' => '2.0',
                    'id' => '1',
                    'method' => $endpoint,
                    'params' => [$params]
                ]);
                log_message("api-helper: Alternative payload - Params: " . substr($postDataArray, 0, 100) . "...", 'tools_api_log.txt');
            }
        } elseif ($method === 'GET') {
            log_message("api-helper: GET request - URL: $log_url", 'tools_api_log.txt');
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

        // Check for rate limit from response headers
        if (preg_match('/X-Rate-Limit-Remaining: (\d+)/i', $headers, $matches)) {
            $remaining = (int)$matches[1];
            if ($remaining < 10) {
                log_message("api-helper: Low rate limit remaining ($remaining), pausing..., URL: $log_url", 'tools_api_log.txt', 'WARNING');
                usleep(1000000); // Wait 1 second
            }
        }

        curl_close($ch);

        log_message("api-helper: Response - HTTP: $httpCode, URL: $log_url, Body: " . substr($body, 0, 500) . "...", 'tools_api_log.txt');

        // Retry if rate limited (HTTP 429)
        if ($httpCode === 429) {
            log_message("api-helper: Rate limit exceeded (429), retrying ($retry_count/$max_retries), URL: $log_url", 'tools_api_log.txt', 'WARNING');
            if ($retry_count < $max_retries) {
                $retry_count++;
                usleep(2000000); // Wait 2 seconds
                continue;
            }
            return ['error' => 'Rate limit exceeded after retries.'];
        }

        // Fallback retry logic for 404 or failed POST
        if ($httpCode !== 200 && $method === 'POST') {
            log_message("api-error: API request failed - HTTP: $httpCode, URL: $log_url, Response: $body", 'tools_api_log.txt', 'ERROR');

            // Retry with alternative param format if POST fails
            if ($httpCode === 404) {
                log_message("api-helper: Retrying with array params payload, URL: $log_url", 'tools_api_log.txt');
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataArray);
                curl_setopt($ch, CURLOPT_HEADER, true);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $headers = substr($response, 0, $header_size);
                $body = substr($response, $header_size);
                curl_close($ch);
                log_message("api-helper: Retry response - HTTP: $httpCode, URL: $log_url, Body: " . substr($body, 0, 500) . "...", 'tools_api_log.txt');
            }
        }

        if ($httpCode !== 200) {
            log_message("api-error: API request failed - HTTP: $httpCode, URL: $log_url, Response: $body", 'tools_api_log.txt', 'ERROR');
            return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
        }

        // Decode JSON response
        $data = json_decode($body, true);
        if ($data === null) {
            log_message("api-error: Failed to parse API response as JSON. URL: $log_url, Response: $body", 'tools_api_log.txt', 'ERROR');
            return ['error' => 'Failed to parse API response as JSON.'];
        }

        // Log full response for debugging
        log_message("api-helper: Full response - Endpoint: $endpoint, URL: $log_url, Response: " . json_encode($data), 'tools_api_log.txt', 'DEBUG');

        // Return error if API-level error exists
        if (isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("api-error: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $errorMessage, URL: $log_url", 'tools_api_log.txt', 'ERROR');
            return ['error' => $errorMessage];
        }

        // Success: log and return data
        log_message("api-success: API success - Endpoint: $endpoint, URL: $log_url, Response: " . substr(json_encode($data), 0, 100) . "...", 'tools_api_log.txt');
        return $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached.'];
}
?>
