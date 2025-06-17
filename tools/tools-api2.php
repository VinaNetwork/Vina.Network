<?php
// ============================================================================
// File: tools/tools-api2.php
// Description: Helper functions for calling Helius API v0 endpoints (REST).
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) {
    die('Direct access not permitted');
}

function callAPI2($endpoint, $params, $method = 'GET') {
    $api_key = '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63'; // Replace with your Helius API key
    $base_url = 'https://api.helius.xyz/';
    $url = $base_url . $endpoint;

    // Build query string for GET requests
    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
        if (strpos($url, 'api-key') === false) {
            $url .= '&api-key=' . $api_key;
        }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    // Handle POST requests
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        $post_data = json_encode($params);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        if (!isset($params['api-key'])) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'api-key=' . $api_key;
            curl_setopt($ch, CURLOPT_URL, $url);
        }
    }

    // Log request
    log_message("api2-helper: $method request - URL: $url, Params: " . json_encode($params), 'api_log2.txt');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        log_message("api2-error: cURL error - URL: $url, Error: $error", 'api_log2.txt', 'ERROR');
        return ['error' => 'cURL error: ' . $error];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("api2-error: JSON decode error - URL: $url, Response: $response", 'api_log2.txt', 'ERROR');
        return ['error' => 'Invalid JSON response'];
    }

    if ($http_code !== 200) {
        log_message("api2-error: API error - Endpoint: $endpoint, URL: $url, HTTP Code: $http_code, Response: $response", 'api_log2.txt', 'ERROR');
        return ['error' => $data['error'] ?? 'API request failed', 'http_code' => $http_code];
    }

    log_message("api2-success: API success - Endpoint: $endpoint, URL: $url, Response: " . json_encode($data), 'api_log2.txt');
    return $data;
}
