<?php
// ============================================================================
// File: mm/endpoints-c/check-token.php
// Description: Check if a token mint is tradable on Jupiter API.
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth | network | csrf | vendor/autoload
require_once $root_path . 'mm/bootstrap.php';

// Initialize logging context
$log_context = [
    'endpoint' => 'check-token',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
];

// Start session
if (!ensure_session()) {
    log_message("Failed to initialize session in check-token.php, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected in check-token.php", 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get parameters from POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method in check-token.php: {$_SERVER['REQUEST_METHOD']}", 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Request method not supported'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Read from $_POST
$token_mint = $_POST['token_mint'] ?? '';
$network = $_POST['network'] ?? SOLANA_NETWORK;
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
$log_context['token_mint'] = $token_mint;
$log_context['network'] = $network;

// Assign token to $_POST for csrf_protect() to use
$_POST[CSRF_TOKEN_NAME] = $csrf_token;

// Protect POST requests with CSRF
try {
    csrf_protect();
} catch (Exception $e) {
    log_message("CSRF validation failed in check-token.php: {$e->getMessage()}, provided_token=$csrf_token, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate required inputs
if (empty($token_mint) || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $token_mint)) {
    log_message("Invalid or missing token mint in check-token.php: $token_mint", 'make-market.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token mint address'], JSON_UNESCAPED_UNICODE);
    exit;
}

log_message("Parameters received: token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'make-market.log', 'make-market', 'INFO', $log_context);

// Check token tradability using Jupiter API
try {
    $jupiter_api_url = JUPITER_API; // Use JUPITER_API defined in core/network.php
    $input_mint = 'So11111111111111111111111111111111111111112'; // SOL mint
    $amount = 1000000; // 0.001 SOL for testing
    $slippage_bps = 50; // 0.5% slippage for testing
    $log_context['input_mint'] = $input_mint;
    $log_context['amount'] = $amount;
    $log_context['slippage_bps'] = $slippage_bps;

    $ch = curl_init();
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

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    log_message(
        "Response from Jupiter API: url=$url, status=$http_code, response=" . ($response ? substr($response, 0, 1000) . (strlen($response) > 1000 ? '...' : '') : 'none') . ", curl_error=$curl_error, session_id=" . (session_id() ?: 'none'),
        'make-market.log', 'make-market', 'DEBUG', $log_context
    );

    if ($curl_error) {
        log_message(
            "cURL error in check-token.php: $curl_error, token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
            'make-market.log', 'make-market', 'ERROR', $log_context
        );
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => "cURL error: $curl_error"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_message = isset($error_data['error']) ? $error_data['error'] : 'Unknown error from Jupiter API';
        $error_code = isset($error_data['errorCode']) ? $error_data['errorCode'] : 'UNKNOWN';
        log_message(
            "Jupiter API error: status=$http_code, message=$error_message, errorCode=$error_code, token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
            'make-market.log', 'make-market', 'ERROR', $log_context
        );
        http_response_code($http_code);
        echo json_encode([
            'status' => 'error',
            'message' => $error_message,
            'errorCode' => $error_code
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode($response, true);
    if (!$data) {
        log_message(
            "Invalid response from Jupiter API: token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
            'make-market.log', 'make-market', 'ERROR', $log_context
        );
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid response from Jupiter API'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Store tradable status in session to avoid repeated API calls
    $_SESSION['tradable_' . $token_mint] = true;
    log_message(
        "Token tradability check passed for token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
        'make-market.log', 'make-market', 'INFO', $log_context
    );
    echo json_encode([
        'status' => 'success',
        'message' => 'Token is tradable on Jupiter API'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    log_message(
        "Token tradability check failed: {$e->getMessage()}, token_mint=$token_mint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), stack_trace={$e->getTraceAsString()}",
        'make-market.log', 'make-market', 'ERROR', $log_context
    );
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking token tradability: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>