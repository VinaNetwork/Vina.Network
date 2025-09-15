<?php
// ============================================================================
// File: mm/endpoints-c/check-token.php
// Description: Check if a token mint is tradable on Jupiter API.
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth | network | csrf | vendor/autoload
require_once $root_path . 'mm/bootstrap.php';

// Debug: Log ngay đầu file để xác nhận script được gọi
file_put_contents('/var/www/vinanetwork/web/logs/make-market/make-market.log', 
    "[" . date('Y-m-d H:i:s') . "] [DEBUG] check-token.php: Script started\n", 
    FILE_APPEND
);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log sau khi include bootstrap
file_put_contents('/var/www/vinanetwork/web/logs/make-market/make-market.log', 
    "[" . date('Y-m-d H:i:s') . "] [DEBUG] check-token.php: Bootstrap loaded\n", 
    FILE_APPEND
);

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    log_message("Non-AJAX request rejected in check-token.php", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get parameters from POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method in check-token.php: {$_SERVER['REQUEST_METHOD']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Request method not supported'], JSON_UNESCAPED_UNICODE);
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
    log_message(
        "CSRF validation failed in check-token.php: {$e->getMessage()}, provided_token=$csrf_token, session_id=" . (session_id() ?: 'none'),
        'make-market.log', 'make-market', 'ERROR'
    );
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate required inputs
if (empty($token_mint) || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $token_mint)) {
    log_message("Invalid or missing token mint in check-token.php: $token_mint", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token mint address'], JSON_UNESCAPED_UNICODE);
    exit;
}

log_message(
    "Parameters received: token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
    'make-market.log', 'make-market', 'INFO'
);

// Check token tradability using Jupiter API
try {
    if (!defined('JUPITER_API')) {
        log_message("JUPITER_API constant not defined", 'make-market.log', 'make-market', 'ERROR');
        throw new Exception('JUPITER_API constant not defined');
    }

    $jupiter_api_url = JUPITER_API; // Defined in core/network.php
    $input_mint = 'So11111111111111111111111111111111111111112'; // SOL mint
    $amount = 1000000; // 0.001 SOL for testing
    $slippage_bps = 50; // 0.5% slippage for testing

    $url = $jupiter_api_url . '?' . http_build_query([
        'inputMint' => $input_mint,
        'outputMint' => $token_mint,
        'amount' => $amount,
        'slippageBps' => $slippage_bps
    ]);

    $headers = [
        'Accept: application/json',
        'User-Agent: VinaNetwork/1.0'
    ];
    if ($network === 'devnet') {
        $headers[] = 'x-jupiter-network: devnet';
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $start_time = microtime(true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    $duration = (microtime(true) - $start_time) * 1000;

    log_message(
        "Response from Jupiter API: url=$url, status=$http_code, response=" . ($response ? substr($response, 0, 1000) . (strlen($response) > 1000 ? '...' : '') : 'none') . ", curl_error=$curl_error, duration={$duration}ms",
        'make-market.log', 'make-market', 'DEBUG'
    );

    if ($curl_error || $http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_message = isset($error_data['error']) ? $error_data['error'] : ($curl_error ? $curl_error : 'Unknown error from Jupiter API');
        $error_code = isset($error_data['errorCode']) ? $error_data['errorCode'] : 'UNKNOWN';
        log_message(
            "Jupiter API error: status=$http_code, message=$error_message, errorCode=$error_code, token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
            'make-market.log', 'make-market', 'ERROR'
        );
        http_response_code($http_code === 400 ? 400 : 500);
        echo json_encode([
            'status' => 'error',
            'message' => $error_message,
            'errorCode' => $error_code
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data)) {
        log_message(
            "Invalid response from Jupiter API: error=" . json_last_error_msg() . ", raw_response=" . ($response ? substr($response, 0, 1000) . (strlen($response) > 1000 ? '...' : '') : 'none') . ", token_mint=$token_mint, network=$network",
            'make-market.log', 'make-market', 'ERROR'
        );
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid response from Jupiter API'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Store tradable status in session to avoid repeated API calls
    $_SESSION['tradable_' . $token_mint] = true;
    log_message(
        "Token tradability check passed for token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
        'make-market.log', 'make-market', 'INFO'
    );
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Token is tradable on Jupiter API'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    log_message(
        "Token tradability check failed: {$e->getMessage()}, token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none') . ", stack_trace=" . $e->getTraceAsString(),
        'make-market.log', 'make-market', 'ERROR'
    );
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking token tradability: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>