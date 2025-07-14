<?php
// File: tools/tools-api.php
// Description: Universal wrapper to call Helius RPC and API endpoints on Solana.
// Created by: Vina Network
// ============================================================================

define('VINANETWORK', true);
define('VINANETWORK_ENTRY', true);
require_once 'bootstrap.php';

function callAPI($endpoint, $params = [], $method = 'POST') {
    $helius_api_key = HELIUS_API_KEY;
    $helius_rpc_url = "https://mainnet.helius-rpc.com/?api-key=$helius_api_key";
    $helius_api_url = "https://api.helius.xyz/v0";
    $log_url = "https://mainnet.helius-rpc.com/?api-key=****";

    $retry_count = 0;
    $max_retries = 3;
    $retry_delays = [2000000, 5000000, 10000000];

    do {
        $ch = curl_init();
        if (!$ch) return ['error' => 'cURL initialization failed'];

        $url = $helius_rpc_url;
        if ($endpoint === 'getNamesByAddress') {
            $url = "$helius_api_url/addresses/{$params['address']}/names?api-key=$helius_api_key";
            $method = 'GET';
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);

            // ✅ Use proper params format depending on endpoint
            $payload = [
                'jsonrpc' => '2.0',
                'id' => '1',
                'method' => $endpoint,
                'params' => ($endpoint === 'getSignaturesForAsset') ? $params : [$params]
            ];

            $postData = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $response = curl_exec($ch);
        if ($response === false) return ['error' => curl_error($ch)];

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        curl_close($ch);

        if ($httpCode !== 200) {
            if (in_array($httpCode, [429, 504]) && $retry_count < $max_retries) {
                usleep($retry_delays[$retry_count]);
                $retry_count++;
                continue;
            }
            return ['error' => "API HTTP error $httpCode"];
        }

        $json = json_decode($body, true);
        if (!$json) return ['error' => 'Invalid JSON response'];

        return $json;

    } while ($retry_count < $max_retries);

    return ['error' => 'Max retries exceeded'];
}
?>
