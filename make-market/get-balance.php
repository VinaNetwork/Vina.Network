<?php
// ============================================================================
// File: make-market/get-balance.php
// Description: Check wallet balance server-side using Helius RPC getAssetsByOwner
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: $csp_base");
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ']);
    exit;
}

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("get-balance.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
}

// Get parameters from POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method: {$_SERVER['REQUEST_METHOD']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Phương thức yêu cầu không được hỗ trợ']);
    exit;
}

$post_data = json_decode(file_get_contents('php://input'), true);
$public_key = $post_data['public_key'] ?? '';
$sol_amount = floatval($post_data['sol_amount'] ?? 0);
$loop_count = intval($post_data['loop_count'] ?? 1);
$batch_size = intval($post_data['batch_size'] ?? 5);

// Validate input
if (empty($public_key) || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $public_key)) {
    log_message("Invalid public key: $public_key", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Địa chỉ ví không hợp lệ']);
    exit;
}
if ($sol_amount <= 0) {
    log_message("Invalid SOL amount: $sol_amount", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Số lượng SOL phải lớn hơn 0']);
    exit;
}
if ($loop_count < 1) {
    log_message("Invalid loop count: $loop_count", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Số vòng lặp phải ít nhất là 1']);
    exit;
}
if ($batch_size < 1 || $batch_size > 10) {
    log_message("Invalid batch size: $batch_size", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Kích thước batch phải từ 1 đến 10']);
    exit;
}

log_message("Parameters received: public_key=$public_key, sol_amount=$sol_amount, loop_count=$loop_count, batch_size=$batch_size", 'make-market.log', 'make-market', 'INFO');

// Check balance using Helius getAssetsByOwner
try {
    if (!defined('HELIUS_API_KEY') || empty(HELIUS_API_KEY)) {
        log_message("HELIUS_API_KEY is not defined or empty", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi cấu hình server: Thiếu HELIUS_API_KEY']);
        exit;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://mainnet.helius-rpc.com/?api-key=" . HELIUS_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'getAssetsByOwner',
            'params' => [
                'ownerAddress' => $public_key,
                'page' => 1,
                'limit' => 50,
                'sortBy' => [
                    'sortBy' => 'created',
                    'sortDirection' => 'asc'
                ],
                'options' => [
                    'showNativeBalance' => true
                ]
            ]
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json; charset=utf-8"
        ],
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        log_message("Helius RPC failed: cURL error: $err", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi kiểm tra số dư ví']);
        exit;
    }

    if ($http_code !== 200) {
        log_message("Helius RPC failed: HTTP $http_code", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi kiểm tra số dư ví']);
        exit;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Helius RPC failed: Invalid JSON response: " . json_last_error_msg(), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi kiểm tra số dư ví']);
        exit;
    }

    if (isset($data['error'])) {
        log_message("Helius RPC failed: {$data['error']['message']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi kiểm tra số dư ví']);
        exit;
    }

    if (!isset($data['result']['nativeBalance']) || !isset($data['result']['nativeBalance']['lamports'])) {
        log_message("Helius RPC failed: No nativeBalance or lamports in response", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi kiểm tra số dư ví']);
        exit;
    }

    $balanceInSol = floatval($data['result']['nativeBalance']['lamports']) / 1e9; // Chuyển từ lamports sang SOL
    $totalTransactions = $loop_count * $batch_size;
    $requiredAmount = ($sol_amount + 0.005) * ($totalTransactions / 2); // Công thức mới
    if ($balanceInSol < $requiredAmount) {
        log_message("Insufficient balance: $balanceInSol SOL available, required=$requiredAmount SOL", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode([
            'status' => 'error', 
            'message' => "Số dư ví không đủ để thực hiện giao dịch. Cần ít nhất $requiredAmount SOL trong ví $public_key."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    log_message("Balance check passed: $balanceInSol SOL available, required=$requiredAmount SOL", 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'message' => 'Số dư ví đủ để thực hiện giao dịch', 'balance' => $balanceInSol], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    log_message("Balance check failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi kiểm tra số dư ví'], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
