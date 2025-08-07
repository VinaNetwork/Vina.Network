<?php
// ============================================================================
// File: make-market/get-balance.php
// Description: Check wallet balance for SOL and Token using Helius RPC getAssetsByOwner
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
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
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
    echo json_encode(['status' => 'error', 'message' => 'Request method not supported']);
    exit;
}

$post_data = json_decode(file_get_contents('php://input'), true);
$public_key = $post_data['public_key'] ?? '';
$sol_amount = floatval($post_data['sol_amount'] ?? 0);
$token_amount = floatval($post_data['token_amount'] ?? 0);
$token_mint = $post_data['token_mint'] ?? '';
$trade_direction = $post_data['trade_direction'] ?? 'buy';
$loop_count = intval($post_data['loop_count'] ?? 1);
$batch_size = intval($post_data['batch_size'] ?? 5);

// Validate minimal required inputs
if (empty($public_key) || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $public_key)) {
    log_message("Invalid public key: $public_key", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid wallet address']);
    exit;
}
if (empty($token_mint) || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $token_mint)) {
    log_message("Invalid token mint: $token_mint", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token mint address']);
    exit;
}

log_message("Parameters received: public_key=" . substr($public_key, 0, 4) . "..., sol_amount=$sol_amount, token_amount=$token_amount, token_mint=$token_mint, trade_direction=$trade_direction, loop_count=$loop_count, batch_size=$batch_size", 'make-market.log', 'make-market', 'INFO');

// Check balance using Helius getAssetsByOwner
try {
    if (!defined('HELIUS_API_KEY') || empty(HELIUS_API_KEY)) {
        log_message("HELIUS_API_KEY is not defined or empty", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Missing HELIUS_API_KEY']);
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
                    'showNativeBalance' => true,
                    'showFungible' => true
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
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance']);
        exit;
    }

    if ($http_code !== 200) {
        log_message("Helius RPC failed: HTTP $http_code", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance']);
        exit;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Helius RPC failed: Invalid JSON response: " . json_last_error_msg(), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance']);
        exit;
    }

    if (isset($data['error'])) {
        log_message("Helius RPC failed: {$data['error']['message']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance']);
        exit;
    }

    if (!isset($data['result']['nativeBalance']) || !isset($data['result']['nativeBalance']['lamports'])) {
        log_message("Helius RPC failed: No nativeBalance or lamports in response", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance']);
        exit;
    }

    // Initialize variables
    $balanceInSol = floatval($data['result']['nativeBalance']['lamports']) / 1e9; // Convert from lamports to SOL
    $totalTransactions = $loop_count * $batch_size;
    $requiredSolAmount = ($sol_amount + 0.005) * ($totalTransactions / 2); // Formula for SOL
    $tokenBalance = 0;
    $decimals = 9; // Default decimals
    $requiredTokenAmount = $token_amount * ($totalTransactions / 2); // Formula for token
    $errors = [];

    // Check SOL balance for 'buy' or 'both' transactions
    if (in_array($trade_direction, ['buy', 'both'])) {
        if ($balanceInSol < $requiredSolAmount) {
            $errors[] = "Insufficient SOL balance: $balanceInSol SOL available, required=$requiredSolAmount SOL";
        }
    }

    // Check token balance for 'sell' or 'both' transactions
    if (in_array($trade_direction, ['sell', 'both'])) {
        if (isset($data['result']['items']) && is_array($data['result']['items'])) {
            foreach ($data['result']['items'] as $item) {
                if ($item['interface'] === 'FungibleToken' && isset($item['id']) && $item['id'] === $token_mint) {
                    $tokenBalance = floatval($item['token_info']['balance'] ?? 0) / pow(10, $item['token_info']['decimals'] ?? 9);
                    $decimals = $item['token_info']['decimals'] ?? 9;
                    break;
                }
            }
        }

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            log_message("Token balance for $public_key (mint: $token_mint): $tokenBalance tokens (decimals: $decimals)", 'make-market.log', 'make-market', 'DEBUG');
        }

        if ($tokenBalance < $requiredTokenAmount) {
            $errors[] = "Insufficient token balance: $tokenBalance tokens available, required=$requiredTokenAmount tokens";
        }
    }

    // Return errors if any
    if (!empty($errors)) {
        log_message(implode("; ", $errors), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => implode("; ", $errors)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    log_message("Balance check passed: SOL balance=$balanceInSol, required SOL=$requiredSolAmount" . (in_array($trade_direction, ['sell', 'both']) ? ", Token balance=$tokenBalance, required Token=$requiredTokenAmount" : ""), 'make-market.log', 'make-market', 'INFO');
    echo json_encode([
        'status' => 'success',
        'message' => 'Wallet balance is sufficient to perform the transaction',
        'balance' => $trade_direction === 'buy' ? $balanceInSol : ($trade_direction === 'sell' ? $tokenBalance : ['sol' => $balanceInSol, 'token' => $tokenBalance])
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    log_message("Balance check failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
