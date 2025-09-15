<?php
// ============================================================================
// File: mm/endpoints-p/get-swap.php
// Description: Retrieve swap transaction from Jupiter API
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

// Initialize logging context
$log_context = [
    'endpoint' => 'get-swap',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
];

// Start session
if (!ensure_session()) {
    log_message("Failed to initialize session in get-swap.php, session_id=" . (session_id() ?: 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected in get-swap.php", 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method in get-swap.php: {$_SERVER['REQUEST_METHOD']}", 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Request method not supported'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check X-Auth-Token
$headers = apache_request_headers();
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;
if ($authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=" . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ", session_id=" . (session_id() ?: 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get parameters from POST data
$input = json_decode(file_get_contents('php://input'), true);
$quoteResponse = isset($input['quoteResponse']) ? $input['quoteResponse'] : null;
$userPublicKey = isset($input['userPublicKey']) ? trim($input['userPublicKey']) : '';
$wrapAndUnwrapSol = isset($input['wrapAndUnwrapSol']) ? (bool)$input['wrapAndUnwrapSol'] : true;
$dynamicComputeUnitLimit = isset($input['dynamicComputeUnitLimit']) ? (bool)$input['dynamicComputeUnitLimit'] : true;
$prioritizationFeeLamports = isset($input['prioritizationFeeLamports']) ? (int)$input['prioritizationFeeLamports'] : 10000;
$network = isset($input['testnet']) && $input['testnet'] ? 'devnet' : 'mainnet';
$log_context['userPublicKey'] = $userPublicKey;
$log_context['network'] = $network;

// Validate inputs
if (!$quoteResponse) {
    log_message("Missing quoteResponse in get-swap.php, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing quote response'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($userPublicKey) || !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $userPublicKey)) {
    log_message("Invalid userPublicKey format in get-swap.php: $userPublicKey, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid user public key format'], JSON_UNESCAPED_UNICODE);
    exit;
}

log_message(
    "Parameters received: userPublicKey=$userPublicKey, wrapAndUnwrapSol=$wrapAndUnwrapSol, dynamicComputeUnitLimit=$dynamicComputeUnitLimit, prioritizationFeeLamports=$prioritizationFeeLamports, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
    'process.log', 'make-market', 'INFO', $log_context
);

try {
    // Call Jupiter API /swap
    $ch = curl_init();
    $url = JUPITER_API . '/swap';
    $postData = json_encode([
        'quoteResponse' => $quoteResponse,
        'userPublicKey' => $userPublicKey,
        'wrapAndUnwrapSol' => $wrapAndUnwrapSol,
        'dynamicComputeUnitLimit' => $dynamicComputeUnitLimit,
        'prioritizationFeeLamports' => $prioritizationFeeLamports,
        'testnet' => $network === 'devnet'
    ]);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: VinaNetwork/1.0'
    ];
    if ($network === 'devnet') {
        $headers[] = 'x-jupiter-network: devnet';
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
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
        "Response from Jupiter API /swap: url=$url, status=$httpCode, response=" . ($response ? substr($response, 0, 1000) . (strlen($response) > 1000 ? '...' : '') : 'none') . ", curl_error=$curlError, session_id=" . (session_id() ?: 'none'),
        'process.log', 'make-market', 'DEBUG', $log_context
    );

    if ($curlError) {
        log_message(
            "cURL error in get-swap.php: $curlError, userPublicKey=$userPublicKey, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
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
            "Jupiter API error: status=$httpCode, message=$errorMessage, errorCode=$errorCode, userPublicKey=$userPublicKey, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
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
    if (!$data || !isset($data['swapTransaction'])) {
        log_message(
            "Invalid response from Jupiter API /swap: userPublicKey=$userPublicKey, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
            'process.log', 'make-market', 'ERROR', $log_context
        );
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid response from Jupiter API'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    log_message(
        "Swap transaction retrieved from Jupiter API: userPublicKey=$userPublicKey, swapTransaction=" . substr($data['swapTransaction'], 0, 20) . "... , network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
        'process.log', 'make-market', 'INFO', $log_context
    );

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'message' => 'Swap transaction retrieved from Jupiter API'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    log_message(
        "Failed to retrieve swap transaction from Jupiter API: {$e->getMessage()}, userPublicKey=$userPublicKey, network=$network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
        'process.log', 'make-market', 'ERROR', $log_context
    );
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving swap transaction: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
