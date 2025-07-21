<?php
require_once '../config/config.php';

header('Content-Type: application/json');

define('HELIUS_API_URL', 'https://api.helius.xyz/v0/addresses/');

function callHeliusApi($address) {
    $url = HELIUS_API_URL . $address . '/transactions?api-key=' . HELIUS_API_KEY;
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    curl_close($curl);

    // Ghi log phản hồi thô để debug
    error_log("Helius API URL: $url");
    error_log("Helius API Response: $response");
    error_log("HTTP Code: $httpCode");

    if ($err) {
        return ['error' => "cURL Error #: $err"];
    }

    // Kiểm tra mã HTTP
    if ($httpCode !== 200) {
        return ['error' => "API returned HTTP code $httpCode: $response"];
    }

    // Kiểm tra JSON hợp lệ
    $decodedResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => "Invalid JSON response: $response"];
    }

    return $decodedResponse;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mintAddress = $_POST['mintAddress'] ?? '';

    if (empty($mintAddress)) {
        echo json_encode(['error' => 'Mint Address is required']);
        exit;
    }

    // Gọi API Helius
    $transactions = callHeliusApi($mintAddress);

    if (isset($transactions['error'])) {
        echo json_encode(['error' => $transactions['error']]);
        exit;
    }

    // Kiểm tra nếu không có giao dịch
    if (empty($transactions)) {
        echo json_encode(['totalBurned' => 0, 'message' => 'No transactions found for this Mint Address']);
        exit;
    }

    $totalBurned = 0;

    // Lọc các giao dịch burn
    foreach ($transactions as $tx) {
        if (isset($tx['tokenTransfers'])) {
            foreach ($tx['tokenTransfers'] as $transfer) {
                if ($transfer['mint'] === $mintAddress && 
                    ($transfer['toTokenAccount'] === null || 
                     $transfer['toTokenAccount'] === '11111111111111111111111111111111')) {
                    $totalBurned += $transfer['tokenAmount'];
                }
            }
        }
        // Kiểm tra thêm lệnh Burn trong instructions
        if (isset($tx['instructions'])) {
            foreach ($tx['instructions'] as $instruction) {
                if ($instruction['programId'] === 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' && 
                    isset($instruction['parsed']) && 
                    $instruction['parsed']['type'] === 'burn') {
                    if ($instruction['parsed']['info']['mint'] === $mintAddress) {
                        $totalBurned += $instruction['parsed']['info']['amount'] / pow(10, $instruction['parsed']['info']['decimals']);
                    }
                }
            }
        }
    }

    echo json_encode(['totalBurned' => $totalBurned]);
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
