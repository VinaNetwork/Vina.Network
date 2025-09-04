<?php
// ============================================================================
// File: mm/core/liquidity.php
// Description: Check token liquidity on Solana using Jupiter API
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth.php | network.php | csrf.php | vendor/autoload.php
require_once $root_path . 'mm/bootstrap.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    log_message("Non-AJAX request rejected in liquidity.php, IP={$_SERVER['REMOTE_ADDR']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Get parameters from POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method in liquidity.php: {$_SERVER['REQUEST_METHOD']}, IP={$_SERVER['REMOTE_ADDR']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Request method not supported']);
    exit;
}

// Read from $_POST
$token_mint = $_POST['token_mint'] ?? '';
$network = $_POST['network'] ?? SOLANA_NETWORK;
$csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$trade_direction = $_POST['trade_direction'] ?? 'buy';
$sol_amount = floatval($_POST['sol_amount'] ?? 0.01); // Default to small amount for testing liquidity
$slippage = floatval($_POST['slippage'] ?? 0.5);

// Assign token to $_POST for csrf_protect()
$_POST[CSRF_TOKEN_NAME] = $csrf_token;

// Log CSRF token for debugging
log_message("Received CSRF token: $csrf_token, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'DEBUG');

// Protect POST requests with CSRF
try {
    csrf_protect();
} catch (Exception $e) {
    log_message("CSRF validation failed in liquidity.php: {$e->getMessage()}, provided_token=$csrf_token, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'CRITICAL');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired CSRF token']);
    exit;
}

// Validate inputs
if (empty($token_mint) || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $token_mint)) {
    log_message("Invalid token mint in liquidity.php: $token_mint", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token mint address']);
    exit;
}

if (!in_array($network, ['mainnet', 'devnet'])) {
    log_message("Invalid network in liquidity.php: $network", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid network']);
    exit;
}

// Check liquidity using Jupiter API
try {
    $jupiter_api = 'https://quote-api.jup.ag/v6/quote';
    $input_mint = $trade_direction === 'buy' ? 'So11111111111111111111111111111111111111112' : $token_mint;
    $output_mint = $trade_direction === 'buy' ? $token_mint : 'So11111111111111111111111111111111111111112';
    $params = [
        'inputMint' => $input_mint,
        'outputMint' => $output_mint,
        'amount' => intval($sol_amount * 1_000_000_000), // Convert SOL to lamports for buy, or use token amount
        'slippageBps' => intval($slippage * 100),
        'testnet' => $network === 'devnet' ? true : false
    ];
    
    $query = http_build_query($params);
    log_message("Calling Jupiter API to check liquidity: $jupiter_api?$query", 'make-market.log', 'make-market', 'INFO');

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "$jupiter_api?$query",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "X-CSRF-Token: $csrf_token"
        ],
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err || $http_code !== 200) {
        log_message("Jupiter API request failed in liquidity.php: cURL error=$err, HTTP=$http_code, network=$network, token_mint=$token_mint", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking token liquidity']);
        exit;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Failed to parse Jupiter API response in liquidity.php: " . json_last_error_msg() . ", raw_response=" . ($response ?: 'empty'), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error parsing Jupiter API response']);
        exit;
    }

    if (isset($data['error'])) {
        log_message("Jupiter API error in liquidity.php: {$data['error']}, errorCode={$data['errorCode']}, token_mint=$token_mint, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $data['errorCode'] === 'TOKEN_NOT_TRADABLE' 
                ? 'The token is not tradable. Please choose another token.'
                : 'Error checking token liquidity: ' . $data['error']
        ]);
        exit;
    }

    // Log successful liquidity check
    log_message("Liquidity check passed: token_mint=$token_mint, network=$network, response=" . json_encode($data), 'make-market.log', 'make-market', 'INFO');
    echo json_encode([
        'status' => 'success',
        'message' => 'Token has sufficient liquidity for trading',
        'liquidity' => [
            'inputMint' => $data['inputMint'],
            'outputMint' => $data['outputMint'],
            'inAmount' => floatval($data['inAmount']) / 1_000_000_000,
            'outAmount' => floatval($data['outAmount']) / pow(10, $data['outDecimals'])
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    log_message("Liquidity check failed in liquidity.php: {$e->getMessage()}, token_mint=$token_mint, network=$network, Stack trace: {$e->getTraceAsString()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking token liquidity: ' . $e->getMessage()]);
    exit;
}
?>
