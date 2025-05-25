<?php
// api-helper.php

// Định nghĩa API Key (sẽ chuyển sang file config ở bước sau)
$helius_api_key = '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63';

// Hàm gọi API Helius
function callHeliusAPI($endpoint, $params = [], $method = 'POST') {
    global $helius_api_key;
    $url = "https://api.helius.xyz/v0/{$endpoint}?api-key={$helius_api_key}";

    $ch = curl_init();
    if (!$ch) {
        error_log("cURL initialization failed.");
        return ['error' => 'Failed to initialize cURL.'];
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if (!empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    if ($response === false) {
        $curlError = curl_error($ch);
        error_log("cURL error: $curlError");
        curl_close($ch);
        return ['error' => 'cURL error: ' . $curlError];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("API request failed with HTTP code: $httpCode");
        return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
    }

    $data = json_decode($response, true);
    if ($data === null) {
        error_log("Failed to parse API response as JSON. Response: $response");
        return ['error' => 'Failed to parse API response as JSON.'];
    }

    return $data;
}
?>
