<?php
// ============================================================================
// File: mm/decimals.php
// Description: Fetch decimals for a given token mint using Solana RPC getAccountInfo
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'mm/header-auth.php';
require_once $root_path . 'mm/network.php'; // Include network configuration

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check AJAX request (though called via include, for consistency)
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected in decimals.php", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Get parameters from POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method in decimals.php: {$_SERVER['REQUEST_METHOD']}", 'make-market.log', 'make-market', 'ERROR');
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
    log_message("CSRF validation failed in decimals.php: {$e->getMessage()}, provided_token=$csrf_token, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate required input
if (empty($token_mint) || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $token_mint)) {
    log_message("Invalid token mint in decimals.php: $token_mint", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token mint address']);
    exit;
}

log_message("Fetching decimals for token_mint: $token_mint, network: $network", 'make-market.log', 'make-market', 'INFO');

// Fetch decimals using Solana getAccountInfo
try {
    $rpc_endpoint = RPC_ENDPOINT; // Use RPC_ENDPOINT from mm/network.php
    if (empty($rpc_endpoint)) {
        log_message("RPC_ENDPOINT is not defined or empty in decimals.php, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Missing RPC endpoint']);
        exit;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $rpc_endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'getAccountInfo',
            'params' => [
                $token_mint,
                [
                    'encoding' => 'jsonParsed'
                ]
            ]
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json; charset=utf-8",
            "X-CSRF-Token: $csrf_token"
        ],
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    curl_close($curl);

    // Log HTTP status and error (if any)
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        log_message("RPC response in decimals.php: HTTP=$http_code, error=" . ($err ?: 'none'), 'make-market.log', 'make-market', 'DEBUG');
    }

    if ($err) {
        log_message("RPC failed in decimals.php: cURL error: $err, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error fetching token decimals: ' . $err]);
        exit;
    }

    if ($http_code !== 200) {
        log_message("RPC failed in decimals.php: HTTP $http_code, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error fetching token decimals']);
        exit;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("RPC failed in decimals.php: Invalid JSON response: " . json_last_error_msg(), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error fetching token decimals']);
        exit;
    }

    if (isset($data['error'])) {
        log_message("RPC failed in decimals.php: {$data['error']['message']}, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error fetching token decimals: ' . $data['error']['message']]);
        exit;
    }

    if (!isset($data['result']['value']) || !isset($data['result']['value']['data']['parsed']['info']['decimals'])) {
        log_message("RPC failed in decimals.php: No decimals found for token_mint=$token_mint, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid token mint or not a fungible token']);
        exit;
    }

    $decimals = intval($data['result']['value']['data']['parsed']['info']['decimals']);

    // Store decimals in session
    $_SESSION['decimals_' . $token_mint] = $decimals;

    log_message("Decimals fetched successfully: $decimals for token_mint=$token_mint, network=$network", 'make-market.log', 'make-market', 'INFO');
    echo json_encode([
        'status' => 'success',
        'message' => 'Token decimals fetched successfully',
        'decimals' => $decimals
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    log_message("Decimals fetch failed in decimals.php: {$e->getMessage()}, network=$network", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error fetching token decimals: ' . $e->getMessage()]);
    exit;
}
?>
