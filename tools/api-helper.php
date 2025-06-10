// tools/api-helper.php
<?php
error_log("api-helper.php: PHP version: " . phpversion());
error_log("api-helper.php: cURL version: " . curl_version()['version']);

// Include file cấu hình
$config_path = dirname(__DIR__) . '/config/config.php';
if (!file_exists($config_path)) {
    error_log("Error: config.php not found at $config_path");
    die('Internal Server Error: Missing config.php');
}
include $config_path;

// Hàm gọi API Helius
function callHeliusAPI($endpoint, $params = [], $method = 'POST') {
    $url = "https://mainnet.helius-rpc.com/?api-key=" . HELIUS_API_KEY;

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
            $postData = json_encode([
                'jsonrpc' => '2.0',
                'id' => '1',
                'method' => $endpoint,
                'params' => $params
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            error_log("api-helper.php: Helius API request - Endpoint: $endpoint, Params: " . substr($postData, 0, 100) . "...");
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

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
        error_log("api-helper.php: Helius API request failed with HTTP code: $httpCode, Response: $response");
        return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
    }

    $data = json_decode($response, true);
    if ($data === null) {
        error_log("api-helper.php: Failed to parse Helius API response as JSON. Response: $response");
        return ['error' => 'Failed to parse API response as JSON.'];
    }

    if (isset($data['error'])) {
        error_log("api-helper.php: Helius API error - Code: {$data['error']['code']}, Message: {$data['error']['message']}");
        return ['error' => $data['error']['message']];
    }

    error_log("api-helper.php: Helius API success - Endpoint: $endpoint");
    return $data;
}
?>
