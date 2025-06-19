<?php
// File: tools/tools-api.php
// Description: Universal wrapper to call Helius RPC API on Solana.
// Created by: Vina Network

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

require_once 'bootstrap.php';

function callAPI($endpoint, $params = [], $method = 'POST') {
    $api_key = defined('HELIUS_API_KEY') ? HELIUS_API_KEY : '';
    $url = "https://mainnet.helius-rpc.com/?api-key=$api_key";
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

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
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($params)) {
                $postData = json_encode([
                    'jsonrpc' => '2.0',
                    'id' => '1',
                    'method' => $endpoint,
                    'params' => [$params]
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                log_message("api-helper: API request - URL: $log_url, Endpoint: $endpoint, Params: " . substr($postData, 0, 100) . "...", 'api_log.txt');
            }
        } else {
            if (!empty($params)) {
                $url .= '&' . http_build_query($params);
                $log_url .= '&' . http_build_query($params);
                curl_setopt($ch, CURLOPT_URL, $url);
            }
            log_message("api-helper: GET request - URL: $log_url", 'api_log.txt');
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $curl_error = curl_error($ch);
            log_message("api-helper: cURL error: $curl_error, URL: $log_url", 'api_log.txt', 'ERROR');
            curl_close($ch);
            $retry_count++;
            if ($retry_count >= $max_retries) {
                return ['error' => 'cURL error: ' . $curl_error];
            }
            usleep(1000000 * $retry_count);
            continue;
        }

        curl_close($ch);
        log_message("api-helper: Response - HTTP: $http_code, URL: $log_url, Body: " . substr($response, 0, 500) . "...", 'api_log.txt');

        if ($http_code === 429) {
            log_message("api_helper: Rate limit exceeded (429), retrying ($retry_count/$max_retries), URL: $log_url", 'api_log.txt', 'WARNING');
            $retry_count++;
            usleep(2000000);
            continue;
        }

        if ($http_code !== 200) {
            log_message("api-error: API request failed - HTTP: $http_code, URL: $log_url, response: $response", 'api_log.txt', 'ERROR');
            $retry_count++;
            if ($retry_count >= $max_retries) {
                return ['error' => "HTTP $http_code: $response"];
            }
            usleep(1000000 * $retry_count);
            continue;
        }

        $data = json_decode($response, true);
        if ($data === null) {
            log_message("api-error: Failed to parse JSON response: $response", 'api_log.txt', 'ERROR');
            return ['error' => 'Failed to parse JSON response from server.'];
        }

        if (isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("api-error: API error - Code: " . ($data['error']['code'] ?? 'N/A') . ", Message: $errorMessage, URL: $log_url", 'api_log.txt', 'ERROR');
            return ['error' => $errorMessage];
        }

        log_message("api-success: API call succeeded - Endpoint: $endpoint, URL: $log_url", 'api_log.txt');
        return $data;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries exceeded.'];
}
?>
