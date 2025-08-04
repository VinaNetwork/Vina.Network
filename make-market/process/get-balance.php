<?php
// ============================================================================
// File: make-market/process/get-balance.php
// Description: Check wallet balance server-side using Helius RPC getAssetsByOwner
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://vina.network');
header('Access-Control-Allow-Methods: GET');
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

// Get transaction ID
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID: $transaction_id", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID giao dịch không hợp lệ']);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối cơ sở dữ liệu']);
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT public_key, sol_amount FROM make_market WHERE id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Transaction not found: ID=$transaction_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy giao dịch']);
        exit;
    }
    if (empty($transaction['public_key']) || $transaction['public_key'] === 'undefined') {
        log_message("Invalid public key: {$transaction['public_key']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Địa chỉ ví không hợp lệ']);
        exit;
    }
    if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $transaction['public_key'])) {
        log_message("Invalid public key format: ID=$transaction_id, public_key={$transaction['public_key']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Địa chỉ ví không đúng định dạng']);
        exit;
    }
    log_message("Transaction fetched: ID=$transaction_id, public_key={$transaction['public_key']}, sol_amount={$transaction['sol_amount']}", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi truy xuất giao dịch']);
    exit;
}

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
                'ownerAddress' => $transaction['public_key'],
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
        echo json_encode(['status' => 'error', 'message' => 'Lỗi kiểm tra số dư ví']);
        exit;
    }

    if ($http_code !== 200) {
        log_message("Helius RPC failed: HTTP $http_code", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi kiểm tra số dư ví']);
        exit;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Helius RPC failed: Invalid JSON response: " . json_last_error_msg(), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi kiểm tra số dư ví']);
        exit;
    }

    if (isset($data['error'])) {
        log_message("Helius RPC failed: {$data['error']['message']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi kiểm tra số dư ví']);
        exit;
    }

    if (!isset($data['result']['nativeBalance']) || !isset($data['result']['nativeBalance']['lamports'])) {
        log_message("Helius RPC failed: No nativeBalance or lamports in response", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi kiểm tra số dư ví']);
        exit;
    }

    $balanceInSol = floatval($data['result']['nativeBalance']['lamports']) / 1e9; // Convert lamports to SOL
    $requiredAmount = floatval($transaction['sol_amount']) + 0.005; // Add 0.005 SOL for fees
    if ($balanceInSol < $requiredAmount) {
        log_message("Insufficient balance: $balanceInSol SOL available, required=$requiredAmount SOL", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode([
            'status' => 'error', 
            'message' => "Số dư ví không đủ để thực hiện giao dịch. Vui lòng gửi thêm SOL vào ví {$transaction['public_key']} để tiếp tục."
        ], JSON_UNESCAPED_UNICODE);
        try {
            $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Insufficient balance: $balanceInSol SOL available, required=$requiredAmount SOL", $transaction_id]);
            log_message("Transaction status updated: ID=$transaction_id, status=failed, error=Insufficient balance: $balanceInSol SOL available, required=$requiredAmount SOL", 'make-market.log', 'make-market', 'INFO');
        } catch (PDOException $e) {
            log_message("Failed to update transaction status: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        }
        exit;
    }

    log_message("Balance check passed: $balanceInSol SOL available, required=$requiredAmount SOL", 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'message' => 'Số dư ví đủ để thực hiện giao dịch', 'balance' => $balanceInSol], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    log_message("Balance check failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    try {
        $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
        $stmt->execute(['failed', $e->getMessage(), $transaction_id]);
        log_message("Transaction status updated: ID=$transaction_id, status=failed, error={$e->getMSage()}", 'make-market.log', 'make-market', 'INFO');
    } catch (PDOException $e2) {
        log_message("Failed to update transaction status: {$e2->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kiểm tra số dư ví'], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
