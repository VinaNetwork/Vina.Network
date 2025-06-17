<?php
// File: tools/tools-api2.php
// Description: Wrapper to call Helius REST APIs for NFT Transactions on Solana.
// Created by: Vina Network
// ============================================================================

// Define project constants for secured includes
define('VINANETWORK', true);
define('VINANETWORK_ENTRY', true);
require_once 'bootstrap.php';

function callAPI2($endpoint, $params = [], $method = 'POST') {
    $api_key = HELIUS_API_KEY;
    $is_rest = in_array($endpoint, ['v0/transactions', 'v0/addresses']);

    if (!$is_rest) {
        log_message("api2-error: Endpoint $endpoint not supported in tools-api2.php", 'api_log2.txt', 'ERROR');
        return ['error' => 'Endpoint not supported in tools-api2.php'];
    }

    // Set URL based on endpoint
    if ($endpoint === 'v0/addresses') {
        $address = $params['address'] ?? '';
        $url = "https://api.helius.xyz/v0/addresses/$address/transactions?api-key=$api_key";
        unset($params['address']);
    } else {
        $url = "https://api.helius.xyz/v0/$endpoint?api-key=$api_key";
    }
    $log_url = str_replace($api_key, '****', $url);

    // Log PHP and cURL versions
    log_message("api2-helper: PHP version: " . phpversion() . ", cURL version: " . curl_version()['version'], 'api_log2.txt');

    $max_retries = 3;
    $retry_count = 0;

    do {
        $ch = curl_init();
        if (!$ch) {
            log_message("api2-helper: cURL initialization failed.", 'api_log2.txt', 'ERROR');
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
                $postData = json_encode($params);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                log_message("api2-helper: API request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 100) . "...", 'api_log2.txt');
            }
        } elseif ($method === 'GET') {
            if (!empty($params)) {
                $url .= '&' . http_build_query($params);
                $log_url .= '&' . http_build_query($params);
                curl_setopt($ch, CURLOPT_URL, $url);
            }
            log_message("api2-helper: GET request - URL: $log_url", 'api_log2.txt');
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $curlError = curl_error($ch);
            log_message("api2-error: cURL error: $curlError, URL: $log_url", 'api_log2.txt', 'ERROR');
            curl_close($ch);
            return ['error' => 'cURL error: ' . $curlError];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        log_message("api2-helper: Response - HTTP: $httpCode, URL: $log_url, Body: " . substr($response, 0, 500) . "...", 'api_log2.txt');

        if ($httpCode === 429) {
            log_message("api2-helper: Rate limit exceeded (429), retrying ($retry_count/$max_retries), URL: $log_url", 'api_log2.txt', 'WARNING');
            if ($retry_count < $max_retries) {
                $retry_count++;
                usleep(2000000);
                continue;
            }
            return ['error' => 'Rate limit exceeded after retries.'];
        }

        if ($httpCode !== 200) {
            log_message("api2-error: API request failed - HTTP: $httpCode, URL: $log_url, Response: $response", 'api_log2.txt', 'ERROR');
            return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
        }

        $data = json_decode($response, true);
        if ($data === null) {
            log_message("api2-error: Failed to parse API response as JSON. URL: $log_url, Response: $response", 'api_log2.txt', 'ERROR');
            return ['error' => 'Failed to parse API response as JSON.'];
        }

        if (isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("api2-error: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $errorMessage, URL: $log_url", 'api_log2.txt', 'ERROR');
            return ['error' => $errorMessage];
        }

        log_message("api2-success: API success - Endpoint: $endpoint, URL: $log_url, Response: " . substr(json_encode($data), 0, 100) . "...", 'api_log2.txt');
        return $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries reached.'];
}
?>
