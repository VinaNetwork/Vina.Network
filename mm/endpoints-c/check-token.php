<?php
// ============================================================================
// File: mm/endpoints-c/check-token.php
// Description: Check if a token mint is tradable on Jupiter API.
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth | network | csrf | vendor/autoload
require_once $root_path . 'mm/bootstrap.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected in check-token.php", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Get parameters from POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method in check-token.php: {$_SERVER['REQUEST_METHOD']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Request method not supported']);
    exit;
}

// Read from $_POST
$token_mint = $_POST['token_mint'] ?? '';
$network = $_POST['network'] ?? SOLANA_NETWORK;
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';

// Assign token to $_POST for csrf_protect() to use
$_POST[CSRF_TOKEN_NAME] = $csrf_token;

// Protect POST requests with CSRF
try {
    csrf_protect();
} catch (Exception $e) {
    log_message("CSRF validation failed in check-token.php: {$e->getMessage()}, provided_token=$csrf_token, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate required inputs
if (empty($token_mint) || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $token_mint)) {
    log_message("Invalid or missing token mint in check-token.php: $token_mint", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token mint address']);
    exit;
}

log_message("Parameters received: token_mint=$token_mint, network=$network", 'make-market.log', 'make-market', 'INFO');

// Check token tradability using Jupiter API
try {
    $jupiter_api_url = JUPITER_API; // Use JUPITER_API defined in core/network.php
    $input_mint = 'So11111111111111111111111111111111111111112'; // SOL mint (same for devnet and mainnet)
    $amount = 1000000; // 0.001 SOL for testing
    $slippage_bps = 50; // 0.5% slippage for testing

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "$jupiter_api_url?inputMint=$input_mint&outputMint=$token_mint&amount=$amount&slippageBps=$slippage_bps",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json; charset=utf-8",
            "Accept: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($curl_error || $http_code !== 200) {
        log_message("Jupiter API request failed in check-token.php: cURL error=$curl_error, HTTP=$http_code, url=$jupiter_api_url?inputMint=$input_mint&outputMint=$token_mint&amount=$amount&slippageBps=$slippage_bps, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code($http_code === 400 ? 400 : 500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking token tradability']);
        exit;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data)) {
        log_message("Jupiter API response parsing failed in check-token.php: " . json_last_error_msg() . ", raw_response=" . ($response ?: 'empty'), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error parsing Jupiter API response']);
        exit;
    }

    if (isset($data['error']) || isset($data['errorCode'])) {
        $error_message = $data['error'] ?? $data['errorCode'] ?? 'Unknown error';
        log_message("Jupiter API error in check-token.php: $error_message, token_mint=$token_mint, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Token is not tradable: $error_message"]);
        exit;
    }

    log_message("Token tradability check passed for token_mint=$token_mint, network=$network", 'make-market.log', 'make-market', 'INFO');
    echo json_encode([
        'status' => 'success',
        'message' => 'Token is tradable on Jupiter API'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    log_message("Token tradability check failed in check-token.php: {$e->getMessage()}, token_mint=$token_mint, network=$network", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking token tradability: ' . $e->getMessage()]);
    exit;
}
?>
