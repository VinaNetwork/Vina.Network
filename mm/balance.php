<?php
// ============================================================================
// File: mm/balance.php
// Description: Check wallet balance for SOL and Token using Helius RPC getAssetsByOwner
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'mm/header-auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected in balance.php", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Get parameters from POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method in balance.php: {$_SERVER['REQUEST_METHOD']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Request method not supported']);
    exit;
}

// Read from $_POST
$public_key = $_POST['public_key'] ?? '';
$trade_direction = $_POST['trade_direction'] ?? 'buy';
$sol_amount = floatval($_POST['sol_amount'] ?? 0);
$token_amount = floatval($_POST['token_amount'] ?? 0);
$token_mint = $_POST['token_mint'] ?? '';
$loop_count = intval($_POST['loop_count'] ?? 1);
$batch_size = intval($_POST['batch_size'] ?? 5);
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';

// Assign token to $_POST for csrf_protect() to use
$_POST[CSRF_TOKEN_NAME] = $csrf_token;

// Protect POST requests with CSRF
try {
    csrf_protect();
} catch (Exception $e) {
    log_message("CSRF validation failed in balance.php: {$e->getMessage()}, provided_token=$csrf_token, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate minimal required inputs
if (empty($public_key)) {
    log_message("Missing public key in balance.php", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing wallet address']);
    exit;
}

if (empty($token_mint) || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $token_mint)) {
    log_message("Invalid token mint in balance.php: $token_mint", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token mint address']);
    exit;
}

$short_public_key = substr($public_key, 0, 4) . '...';
log_message("Parameters received: public_key=$short_public_key, sol_amount=$sol_amount, token_amount=$token_amount, token_mint=$token_mint, trade_direction=$trade_direction, loop_count=$loop_count, batch_size=$batch_size", 'make-market.log', 'make-market', 'INFO');

// Get decimals from session (set by decimals.php)
$decimals = isset($_SESSION['decimals_' . $token_mint]) ? intval($_SESSION['decimals_' . $token_mint]) : 9;
if ($trade_direction === 'sell' || $trade_direction === 'both') {
    if (!isset($_SESSION['decimals_' . $token_mint])) {
        log_message("Decimals not found in session for token_mint=$token_mint, using default=$decimals", 'make-market.log', 'make-market', 'INFO');
    } else {
        log_message("Decimals retrieved from session: $decimals for token_mint=$token_mint", 'make-market.log', 'make-market', 'INFO');
    }
}

// Check balance using Helius getAssetsByOwner
try {
    if (!defined('HELIUS_API_KEY') || empty(HELIUS_API_KEY)) {
        log_message("HELIUS_API_KEY is not defined or empty in balance.php", 'make-market.log', 'make-market', 'ERROR');
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
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'getAssetsByOwner',
            'params' => [
                'ownerAddress' => $public_key,
                'page' => 1,
                'limit' => 10,
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
        log_message("Helius RPC response in balance.php: HTTP=$http_code, error=" . ($err ?: 'none'), 'make-market.log', 'make-market', 'DEBUG');
    }

    if ($err) {
        log_message("Helius RPC failed in balance.php: cURL error: $err", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance: ' . $err]);
        // Clear session decimals after cURL error
        if (isset($_SESSION['decimals_' . $token_mint])) {
            unset($_SESSION['decimals_' . $token_mint]);
            log_message("Cleared session decimals for token_mint=$token_mint after cURL error", 'make-market.log', 'make-market', 'INFO');
        }
        exit;
    }

    if ($http_code !== 200) {
        log_message("Helius RPC failed in balance.php: HTTP $http_code", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance']);
        // Clear session decimals after HTTP error
        if (isset($_SESSION['decimals_' . $token_mint])) {
            unset($_SESSION['decimals_' . $token_mint]);
            log_message("Cleared session decimals for token_mint=$token_mint after HTTP error", 'make-market.log', 'make-market', 'INFO');
        }
        exit;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Helius RPC failed in balance.php: Invalid JSON response: " . json_last_error_msg(), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance']);
        // Clear session decimals after JSON error
        if (isset($_SESSION['decimals_' . $token_mint])) {
            unset($_SESSION['decimals_' . $token_mint]);
            log_message("Cleared session decimals for token_mint=$token_mint after JSON error", 'make-market.log', 'make-market', 'INFO');
        }
        exit;
    }

    if (isset($data['error'])) {
        log_message("Helius RPC failed in balance.php: {$data['error']['message']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance: ' . $data['error']['message']]);
        // Clear session decimals after RPC error
        if (isset($_SESSION['decimals_' . $token_mint])) {
            unset($_SESSION['decimals_' . $token_mint]);
            log_message("Cleared session decimals for token_mint=$token_mint after RPC error", 'make-market.log', 'make-market', 'INFO');
        }
        exit;
    }

    if (!isset($data['result']['nativeBalance']) || !isset($data['result']['nativeBalance']['lamports'])) {
        log_message("Helius RPC failed in balance.php: No nativeBalance or lamports found for public_key=$short_public_key", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance']);
        // Clear session decimals after balance error
        if (isset($_SESSION['decimals_' . $token_mint])) {
            unset($_SESSION['decimals_' . $token_mint]);
            log_message("Cleared session decimals for token_mint=$token_mint after balance error", 'make-market.log', 'make-market', 'INFO');
        }
        exit;
    }

    // Initialize variables
    $balanceInSol = floatval($data['result']['nativeBalance']['lamports']) / 1e9;
    $totalTransactions = $loop_count * $batch_size;
    $requiredSolAmount = 0;
    $requiredTokenAmount = 0;
    $tokenBalance = 0;
    $errors = [];

    // Calculate required amounts based on trade direction
    if ($trade_direction === 'buy') {
        $requiredSolAmount = ($sol_amount * $loop_count) + (0.000005 * $totalTransactions) + 0.00203928; // SOL amount + tx fees + ATA creation
    } elseif ($trade_direction === 'sell') {
        $requiredTokenAmount = $token_amount * $totalTransactions;
        $requiredSolAmount = (0.000005 * $totalTransactions); // Only tx fees for sell
    } elseif ($trade_direction === 'both') {
        $requiredSolAmount = ($sol_amount * ($loop_count / 2)) + (0.000005 * $totalTransactions) + 0.00203928; // Half for buy + tx fees + ATA
        $requiredTokenAmount = $token_amount * ($totalTransactions / 2); // Half for sell
    }

    // Log balance information
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        log_message("SOL balance for public_key=$short_public_key: $balanceInSol SOL, required=$requiredSolAmount SOL", 'make-market.log', 'make-market', 'DEBUG');
    }

    // Check SOL balance for 'buy' or 'both' transactions
    if ($trade_direction === 'buy' || $trade_direction === 'both') {
        if ($balanceInSol < $requiredSolAmount) {
            $errors[] = "Insufficient SOL balance: $balanceInSol SOL available, required=$requiredSolAmount SOL";
        }
    }

    // Check token balance for 'sell' or 'both' transactions
    if ($trade_direction === 'sell' || $trade_direction === 'both') {
        if (isset($data['result']['items']) && is_array($data['result']['items'])) {
            foreach ($data['result']['items'] as $item) {
                if ($item['interface'] === 'FungibleToken' && isset($item['id']) && $item['id'] === $token_mint) {
                    $tokenBalance = floatval($item['token_info']['balance'] ?? 0) / pow(10, $decimals);
                    break;
                }
            }
        }

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            log_message("Token balance for public_key=$short_public_key, token_mint=$token_mint: $tokenBalance tokens (decimals: $decimals)", 'make-market.log', 'make-market', 'DEBUG');
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
        // Clear session decimals after error
        if (isset($_SESSION['decimals_' . $token_mint])) {
            unset($_SESSION['decimals_' . $token_mint]);
            log_message("Cleared session decimals for token_mint=$token_mint after error", 'make-market.log', 'make-market', 'INFO');
        }
        exit;
    }

    log_message("Balance check passed: SOL balance=$balanceInSol, required SOL=$requiredSolAmount" . ($trade_direction === 'sell' || $trade_direction === 'both' ? ", Token balance=$tokenBalance, required Token=$requiredTokenAmount" : ""), 'make-market.log', 'make-market', 'INFO');
    echo json_encode([
        'status' => 'success',
        'message' => 'Wallet balance is sufficient to perform the transaction',
        'balance' => $trade_direction === 'buy' ? $balanceInSol : ($trade_direction === 'sell' ? $tokenBalance : ['sol' => $balanceInSol, 'token' => $tokenBalance])
    ], JSON_UNESCAPED_UNICODE);
    // Clear session decimals after success
    if (isset($_SESSION['decimals_' . $token_mint])) {
        unset($_SESSION['decimals_' . $token_mint]);
        log_message("Cleared session decimals for token_mint=$token_mint after success", 'make-market.log', 'make-market', 'INFO');
    }
} catch (Exception $e) {
    log_message("Balance check failed in balance.php: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance: ' . $e->getMessage()]);
    // Clear session decimals after exception
    if (isset($_SESSION['decimals_' . $token_mint])) {
        unset($_SESSION['decimals_' . $token_mint]);
        log_message("Cleared session decimals for token_mint=$token_mint after exception", 'make-market.log', 'make-market', 'INFO');
    }
    exit;
}
?>
