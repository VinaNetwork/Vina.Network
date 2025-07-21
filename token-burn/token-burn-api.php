<?php
require_once '../config/config.php';

header('Content-Type: application/json');

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
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return ['error' => "cURL Error #: $err"];
    }
    
    return json_decode($response, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mintAddress = $_POST['mintAddress'] ?? '';

    if (empty($mintAddress)) {
        echo json_encode(['error' => 'Mint Address is required']);
        exit;
    }

    // Gọi API Helius để lấy lịch sử giao dịch
    $transactions = callHeliusApi($mintAddress);

    if (isset($transactions['error'])) {
        echo json_encode(['error' => $transactions['error']]);
        exit;
    }

    $totalBurned = 0;

    // Lọc các giao dịch burn
    foreach ($transactions as $tx) {
        // Kiểm tra các giao dịch liên quan đến token burn
        // Burn thường có toTokenAccount là null hoặc địa chỉ burn (11111111111111111111111111111111)
        if (isset($tx['tokenTransfers'])) {
            foreach ($tx['tokenTransfers'] as $transfer) {
                if ($transfer['mint'] === $mintAddress && 
                    ($transfer['toTokenAccount'] === null || 
                     $transfer['toTokenAccount'] === '11111111111111111111111111111111')) {
                    $totalBurned += $transfer['tokenAmount'];
                }
            }
        }
    }

    echo json_encode(['totalBurned' => $totalBurned]);
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
