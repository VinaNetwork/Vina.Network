<?php
// ============================================================================
// File: mm/process/get-quote.php
// Description: Retrieve quote from Jupiter API for token swap
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

// Initialize logging context
$log_context = [
    'endpoint' => 'get-quote',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
];

// Start session
if (!ensure_session()) {
    log_message("Failed to initialize session in get-quote.php, session_id=" . (session_id() ?: 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected in get-quote.php", 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method in get-quote.php: {$_SERVER['REQUEST_METHOD']}", 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Request method not supported'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check X-Auth-Token
$headers = apache_request_headers();
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;
if ($authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=" . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ", session_id=" . (session_id() ?: 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get parameters from POST data
$input = json_decode(file_get_contents('php://input'), true);
$inputMint = isset($input['inputMint']) ? trim($input['inputMint']) : '';
$outputMint = isset($input['outputMint']) ? trim($input['outputMint']) : '';
$amount = isset($input['amount']) ? (int)$input['amount'] : 0;
$slippageBps = isset($input['slippageBps']) ? (int)$input['slippageBps'] : 0;
$network = isset($input['network']) ? trim($input['network']) : 'mainnet';
$log_context['inputMint'] = $inputMint;
$log_context['outputMint'] = $outputMint;
$log_context['amount'] = $amount;
$log_context['slippageBps'] = $slippageBps;
$log_context['network'] = $network;

// Validate inputs
if (empty($inputMint) || !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $inputMint)) {
    log_message("Invalid inputMint format in get-quote.php: $inputMint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input mint address format'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($outputMint) || !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $outputMint)) {
    log_message("Invalid outputMint format in get-quote.php: $outputMint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid output mint address format'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($amount <= 0) {
    log_message("Invalid amount in get-quote.php: $amount, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Amount must be greater than 0'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($slippageBps < 0) {
    log_message("Invalid slippageBps in get-quote.php: $slippageBps, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Slippage must be non-negative'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($network, ['mainnet', 'devnet'])) {
    log_message("Invalid network in get-quote.php: $network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid network'], JSON_UNESCAPED_UNICODE);
    exit;
}

log_message(
    "Parameters received: inputMint=$inputMint, outputMint=$outputMint, amount=$amount, slippageBps=$slippageBps, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
    'process.log', 'make-market', 'INFO', $log_context
);

try {
    // Call Jupiter API
    $ch = curl_init();
    $url = JUPITER_API . '?' . http_build_query([
        'inputMint' => $inputMint,
        'outputMint' => $outputMint,
        'amount' => $amount,
        'slippageBps' => $slippageBps
    ]);

    $headers = [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (compatible; VinaNetwork/1.0)',
        'Origin: https://vina.network'
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    log_message(
        "Response from Jupiter API: url=$url, status=$httpCode, response=" . ($response ? substr($response, 0, 1000) . (strlen($response) > 1000 ? '...' : '') : 'none') . ", curl_error=$curlError, session_id=" . (session_id() ?: 'none'),
        'process.log', 'make-market', 'DEBUG', $log_context
    );

    if ($curlError) {
        log_message(
            "cURL error in get-quote.php: $curlError, inputMint=$inputMint, outputMint=$outputMint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
            'process.log', 'make-market', 'ERROR', $log_context
        );
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => "cURL error: $curlError"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = isset($errorData['error']) ? $errorData['error'] : 'Unknown error from Jupiter API';
        $errorCode = isset($errorData['errorCode']) ? $errorData['errorCode'] : 'UNKNOWN';
        log_message(
            "Jupiter API error: status=$httpCode, message=$errorMessage, errorCode=$errorCode, inputMint=$inputMint, outputMint=$outputMint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
            'process.log', 'make-market', 'ERROR', $log_context
        );
        http_response_code($httpCode);
        echo json_encode([
            'status' => 'error',
            'message' => $errorMessage,
            'errorCode' => $errorCode
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode($response, true);
    if (!$data) {
        log_message(
            "Invalid response from Jupiter API: inputMint=$inputMint, outputMint=$outputMint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
            'process.log', 'make-market', 'ERROR', $log_context
        );
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid response from Jupiter API'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    log_message(
        "Quote retrieved from Jupiter API: inputMint=$inputMint, outputMint=$outputMint, amount=$amount, slippageBps=$slippageBps, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
        'process.log', 'make-market', 'INFO', $log_context
    );

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'message' => 'Quote retrieved from Jupiter API'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    log_message(
        "Failed to retrieve quote from Jupiter API: {$e->getMessage()}, inputMint=$inputMint, outputMint=$outputMint, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
        'process.log', 'make-market', 'ERROR', $log_context
    );
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving quote: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
