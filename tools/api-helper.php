<?php
error_log("api-helper.php: PHP version: " . phpversion());
error_log("api-helper.php: cURL version: " . curl_version()['version']);

// Include file cấu hình
$config_path = dirname(__DIR__) . '/config/config.php'; // Đường dẫn tương đối từ tools/
if (!file_exists($config_path)) {
    error_log("Error: config.php not found at $config_path");
    die('Internal Server Error: Missing config.php');
}
include $config_path;

// Hàm gọi API Helius
function callHeliusAPI($endpoint, $params = [], $method = 'POST') {
    global $helius_api_key; // Xóa định nghĩa trực tiếp, dùng hằng số từ config
    $url = "https://api.helius.xyz/v0/{$endpoint}?api-key=" . HELIUS_API_KEY;

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

// Solscan API
function callSolscanAPI($endpoint, $params = []) {
    $base_url = 'https://pro-api.solscan.io/v2.0/';
    $url = $base_url . $endpoint;
    
    // Thêm query params nếu có
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $api_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJjcmVhdGVkQXQiOjE3NDgyNDcyMjI3OTgsImVtYWlsIjoibjl1OTNuQGdtYWlsLmNvbSIsImFjdGlvbiI6InRva2VuLWFwaSIsImFwaVZlcnNpb24iOiJ2MiIsImlhdCI6MTc0ODI0NzIyMn0.ukV8lKST8a1G46dA8rc3yu-CtZ90nxDI50o0q4xvgMk'; // Thay bằng key từ pro-api.solscan.io
    error_log("api-helper.php: Calling Solscan API - URL: $url"); // Debug
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("api-helper.php: Solscan API error - HTTP $http_code, Response: $response");
        return ['error' => "Solscan API error: HTTP $http_code"];
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("api-helper.php: Solscan API JSON decode error - Response: $response");
        return ['error' => 'Invalid JSON response'];
    }
    
    error_log("api-helper.php: Solscan API success - Endpoint: $endpoint");
    return $data;
}
?>
