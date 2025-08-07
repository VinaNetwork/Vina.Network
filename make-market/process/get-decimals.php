<?php
// ============================================================================
// File: make-market/process/get-decimals.php
// Description:  Get Decimals Token Solana
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php'; // Load SOLANA_NETWORK and HELIUS_API_KEY

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$endpoints = SOLANA_NETWORK === 'testnet' ? [
    'https://api.testnet.solana.com',
    'https://api.devnet.solana.com'
] : [
    defined('HELIUS_API_KEY') ? 'https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY : null,
    'https://api.mainnet-beta.solana.com'
];
$endpoints = array_filter($endpoints); // Remove null entries

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['tokenMint'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing tokenMint']);
    error_log('get-decimals.php: Invalid or missing tokenMint');
    exit;
}

$tokenMint = $data['tokenMint'];
$maxRetries = 5;
$attempt = 0;
$endpointIndex = 0;

while ($attempt < $maxRetries) {
    $url = $endpoints[$endpointIndex];
    error_log("get-decimals.php: Attempting to get token decimals (attempt " . ($attempt + 1) . "/$maxRetries): mint=$tokenMint, endpoint=$url, network=" . SOLANA_NETWORK);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'jsonrpc' => '2.0',
        'id' => '1',
        'method' => 'getAccountInfo',
        'params' => [$tokenMint, ['encoding' => 'jsonParsed']]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        $attempt++;
        error_log("get-decimals.php: Failed to get token decimals (attempt $attempt/$maxRetries): mint=$tokenMint, error=cURL error: $curl_error, network=" . SOLANA_NETWORK . ", endpoint=$url");
        if ($attempt === $maxRetries && $endpointIndex < count($endpoints) - 1) {
            $endpointIndex++;
            $attempt = 0;
            error_log("get-decimals.php: Switching to fallback endpoint: {$endpoints[$endpointIndex]}");
        } elseif ($attempt === $maxRetries) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => "Failed to retrieve token decimals after $maxRetries attempts: cURL error: $curl_error"]);
            error_log("get-decimals.php: Failed to retrieve token decimals after $maxRetries attempts: cURL error: $curl_error");
            exit;
        }
        sleep($attempt); // Wait 1s, 2s, 3s, 4s, 5s
        continue;
    }

    if ($http_code !== 200) {
        $attempt++;
        error_log("get-decimals.php: Failed to get token decimals (attempt $attempt/$maxRetries): mint=$tokenMint, error=HTTP $http_code, network=" . SOLANA_NETWORK . ", endpoint=$url");
        if ($attempt === $maxRetries && $endpointIndex < count($endpoints) - 1) {
            $endpointIndex++;
            $attempt = 0;
            error_log("get-decimals.php: Switching to fallback endpoint: {$endpoints[$endpointIndex]}");
        } elseif ($attempt === $maxRetries) {
            http_response_code($http_code);
            echo json_encode(['status' => 'error', 'message' => "Failed to retrieve token decimals after $maxRetries attempts: HTTP $http_code"]);
            error_log("get-decimals.php: Failed to retrieve token decimals after $maxRetries attempts: HTTP $http_code");
            exit;
        }
        sleep($attempt);
        continue;
    }

    $result = json_decode($response, true);
    error_log("get-decimals.php: Response from getAccountInfo: " . json_encode($result));

    if (!isset($result['result']['value']['data']['parsed']['type']) || $result['result']['value']['data']['parsed']['type'] !== 'mint') {
        http_response_code(400);
        $message = isset($result['result']['value']) 
            ? "Invalid account type: received type={$result['result']['value']['data']['parsed']['type']}, expected 'mint'"
            : "Invalid response: no valid account data";
        echo json_encode(['status' => 'error', 'message' => $message]);
        error_log("get-decimals.php: $message, mint=$tokenMint, endpoint=$url");
        exit;
    }

    $decimals = $result['result']['value']['data']['parsed']['info']['decimals'] ?? 9;
    error_log("get-decimals.php: Token decimals retrieved: mint=$tokenMint, decimals=$decimals, network=" . SOLANA_NETWORK . ", endpoint=$url");
    echo json_encode(['status' => 'success', 'decimals' => $decimals]);
    exit;
}
?>
