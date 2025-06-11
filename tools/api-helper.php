<?php
// api-helper.php
require_once '/var/www/vinanetwork/public_html/tools/bootstrap.php';

error_log("api-helper.php: PHP version: " . phpversion());
error_log("api-helper.php: cURL version: " . curl_version()['version']);

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

// Hàm lấy toàn bộ holders
function getAllHolders($mintAddress, &$total_holders = 0) {
    $all_holders = [];
    $api_page = 1;
    $limit = 1000;
    $has_more = true;

    while ($has_more) {
        $params = [
            'groupKey' => 'collection',
            'groupValue' => $mintAddress,
            'page' => $api_page,
            'limit' => $limit
        ];
        $data = callHeliusAPI('getAssetsByGroup', $params, 'POST');

        if (isset($data['error'])) {
            error_log("api-helper.php: getAllHolders error - " . json_encode($data));
            return ['error' => $data['error']];
        }

        $items = $data['result']['items'] ?? [];
        $item_count = count($items);
        $total_holders += $item_count;

        foreach ($items as $item) {
            $all_holders[] = [
                'owner' => $item['ownership']['owner'] ?? 'unknown',
                'amount' => 1
            ];
        }

        if ($item_count < $limit) {
            $has_more = false;
        } else {
            $api_page++;
        }
    }

    return ['holders' => $all_holders];
}
?>
