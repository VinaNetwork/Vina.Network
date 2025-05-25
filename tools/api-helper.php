<?php
// api-helper.php

// Include file cấu hình
$config_path = dirname(__DIR__) . '/config/config.php';
if (!file_exists($config_path)) {
    error_log("Error: config.php not found at $config_path");
    die('Internal Server Error: Missing config.php');
}
include $config_path;

// Hàm gọi API Helius với caching
function callHeliusAPI($endpoint, $params = [], $method = 'POST') {
    // Tạo key cho cache dựa trên endpoint và params
    $cache_key = md5($endpoint . serialize($params));
    $cache_dir = BASE_PATH . 'cache/';
    $cache_file = $cache_dir . $cache_key . '.cache';
    $cache_time = 300; // 5 phút

    // Đảm bảo thư mục cache tồn tại
    if (!is_dir($cache_dir)) {
        if (!mkdir($cache_dir, 0755, true) && !is_dir($cache_dir)) {
            error_log("Failed to create cache directory: $cache_dir");
            // Bỏ qua cache nếu không tạo được thư mục
        }
    }

    // Kiểm tra cache
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        $cached_data = file_get_contents($cache_file);
        $data = json_decode($cached_data, true);
        if ($data !== null) {
            return $data;
        }
    }

    // Nếu không có cache, gọi API
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
        return ['error' => 'Failed to fetch data. Please try again later. Error: ' . $curlError];
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
        return ['error' => 'Failed to parse API response. Please try again later.'];
    }

    // Lưu vào cache nếu thư mục tồn tại
    if (is_dir($cache_dir)) {
        file_put_contents($cache_file, json_encode($data));
    }

    return $data;
}
?>
