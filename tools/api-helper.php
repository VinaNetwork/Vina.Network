<?php
// api-helper.php

// Include file cấu hình
$config_path = dirname(__DIR__) . '/config/config.php'; // Đường dẫn tương đối từ tools/
if (!file_exists($config_path)) {
    error_log("Error: config.php not found at $config_path");
    die('Internal Server Error: Missing config.php');
}
include $config_path;

// Hàm gọi API Helius
function callHeliusAPI($endpoint, $params = []) {
    $url = "https://api.helius.xyz/v0/{$endpoint}?api-key=" . HELIUS_API_KEY;

    $ch = curl_init();
    if (!$ch) {
        error_log("cURL initialization failed.");
        return ['error' => 'Failed to initialize cURL.'];
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if (!empty($params)) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
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

// Hàm kiểm tra mint address
function validateMintAddress($mint_address) {
    return !empty($mint_address) && strlen($mint_address) > 20;
}

// Hàm phân trang
function getPaginatedData($data, $page, $per_page) {
    $offset = ($page - 1) * $per_page;
    $total_items = count($data);
    $paginated_data = array_slice($data, $offset, $per_page);
    return [
        'data' => $paginated_data,
        'total_items' => $total_items,
        'total_pages' => ceil($total_items / $per_page)
    ];
}
?>
