<?php
// ============================================================================
// File: mm/create-process/check-balance.php
// Description: Check wallet balance for SOL and Token using Solana RPC
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth.php | network.php | csrf.php | vendor/autoload.php
require_once $root_path . 'mm/bootstrap.php';

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
$network = $_POST['network'] ?? SOLANA_NETWORK;
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
log_message("Parameters received: public_key=$short_public_key, sol_amount=$sol_amount, token_amount=$token_amount, token_mint=$token_mint, trade_direction=$trade_direction, loop_count=$loop_count, batch_size=$batch_size, network=$network", 'make-market.log', 'make-market', 'INFO');

// Get decimals from session (set by decimals.php)
$decimals = isset($_SESSION['decimals_' . $token_mint]) ? intval($_SESSION['decimals_' . $token_mint]) : 9;
if ($trade_direction === 'sell' || $trade_direction === 'both') {
    if (!isset($_SESSION['decimals_' . $token_mint])) {
        log_message("Decimals not found in session for token_mint=$token_mint, using default=$decimals", 'make-market.log', 'make-market', 'INFO');
    } else {
        log_message("Decimals retrieved from session: $decimals for token_mint=$token_mint", 'make-market.log', 'make-market', 'INFO');
    }
}

// Check balance using Solana RPC
try {
    $rpc_endpoint = RPC_ENDPOINT;
    if (empty($rpc_endpoint)) {
        log_message("RPC_ENDPOINT is not defined or empty in balance.php, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Missing RPC endpoint']);
        exit;
    }

    // Initialize variables
    $balanceInSol = 0;
    $tokenBalance = 0;
    $errors = [];
    $is_helius = strpos($rpc_endpoint, 'helius-rpc.com') !== false;

    // Get SOL balance (required for all trade directions)
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
            'method' => 'getBalance',
            'params' => [$public_key]
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json; charset=utf-8",
            "X-CSRF-Token: $csrf_token"
        ],
    ]);

    $sol_response = curl_exec($curl);
    $sol_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $sol_err = curl_error($curl);
    curl_close($curl);

    if ($sol_err || $sol_http_code !== 200) {
        log_message("RPC getBalance failed in balance.php: cURL error=$sol_err, HTTP=$sol_http_code, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking SOL balance']);
        if (isset($_SESSION['decimals_' . $token_mint])) {
            unset($_SESSION['decimals_' . $token_mint]);
            log_message("Cleared session decimals for token_mint=$token_mint after SOL balance error", 'make-market.log', 'make-market', 'INFO');
        }
        exit;
    }

    $sol_data = json_decode($sol_response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($sol_data['result']['value'])) {
        log_message("RPC getBalance failed in balance.php: Invalid JSON or no balance found for public_key=$short_public_key, network=$network", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking SOL balance']);
        if (isset($_SESSION['decimals_' . $token_mint])) {
            unset($_SESSION['decimals_' . $token_mint]);
            log_message("Cleared session decimals for token_mint=$token_mint after SOL balance error", 'make-market.log', 'make-market', 'INFO');
        }
        exit;
    }

    $balanceInSol = floatval($sol_data['result']['value']) / 1e9;

    // Check token balance only for 'sell' or 'both'
    if ($trade_direction === 'sell' || $trade_direction === 'both') {
        $method = $is_helius ? 'getAssetsByOwner' : 'getTokenAccountsByOwner';
        $params = $is_helius ? [
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
        ] : [
            $public_key,
            ['mint' => $token_mint],
            ['encoding' => 'jsonParsed']
        ];

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
                'id' => '2',
                'method' => $method,
                'params' => $params
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

        if ($err || $http_code !== 200) {
            log_message("RPC $method failed in balance.php: cURL error=$err, HTTP=$http_code, network=$network", 'make-market.log', 'make-market', 'ERROR');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error checking token balance']);
            if (isset($_SESSION['decimals_' . $token_mint])) {
                unset($_SESSION['decimals_' . $token_mint]);
                log_message("Cleared session decimals for token_mint=$token_mint after token balance error", 'make-market.log', 'make-market', 'INFO');
            }
            exit;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['result'])) {
            log_message("RPC $method failed in balance.php: Invalid JSON or no result found, network=$network", 'make-market.log', 'make-market', 'ERROR');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error checking token balance']);
            if (isset($_SESSION['decimals_' . $token_mint])) {
                unset($_SESSION['decimals_' . $token_mint]);
                log_message("Cleared session decimals for token_mint=$token_mint after JSON error", 'make-market.log', 'make-market', 'INFO');
            }
            exit;
        }

        if (isset($data['error'])) {
            log_message("RPC $method failed in balance.php: {$data['error']['message']}, network=$network", 'make-market.log', 'make-market', 'ERROR');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error checking token balance: ' . $data['error']['message']]);
            if (isset($_SESSION['decimals_' . $token_mint])) {
                unset($_SESSION['decimals_' . $token_mint]);
                log_message("Cleared session decimals for token_mint=$token_mint after RPC error", 'make-market.log', 'make-market', 'INFO');
            }
            exit;
        }

        if ($is_helius) {
            if (isset($data['result']['items']) && is_array($data['result']['items'])) {
                foreach ($data['result']['items'] as $item) {
                    if ($item['interface'] === 'FungibleToken' && isset($item['id']) && $item['id'] === $token_mint) {
                        $tokenBalance = floatval($item['token_info']['balance'] ?? 0) / pow(10, $decimals);
                        break;
                    }
                }
            }
        } else {
            if (isset($data['result']['value']) && is_array($data['result']['value'])) {
                foreach ($data['result']['value'] as $account) {
                    if (isset($account['account']['data']['parsed']['info']['mint']) && $account['account']['data']['parsed']['info']['mint'] === $token_mint) {
                        $tokenBalance = floatval($account['account']['data']['parsed']['info']['tokenAmount']['uiAmount'] ?? 0);
                        break;
                    }
                }
            }
        }
    }

    // Calculate required amounts
    $totalTransactions = $loop_count * $batch_size;
    $requiredSolAmount = 0;
    $requiredTokenAmount = 0;

    // Use TRANSACTION_FEE from config.php
    $transactionFee = defined('TRANSACTION_FEE') ? TRANSACTION_FEE : 0.000005; // Fallback to 0.000005 if not defined

    // Calculate required amounts based on trade direction
    if ($trade_direction === 'buy') {
        $requiredSolAmount = $totalTransactions * ($sol_amount + $transactionFee);
    } elseif ($trade_direction === 'sell') {
        $requiredTokenAmount = $totalTransactions * $token_amount;
        $requiredSolAmount = $totalTransactions * $transactionFee; // Require SOL for transaction fees
    } elseif ($trade_direction === 'both') {
        $buyTransactions = floor($totalTransactions / 2);
        $sellTransactions = $totalTransactions - $buyTransactions;
        $requiredSolAmount = $buyTransactions * ($sol_amount + $transactionFee) + $sellTransactions * $transactionFee;
        $requiredTokenAmount = $sellTransactions * $token_amount;
    }

    // Log balance information
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        log_message("SOL balance for public_key=$short_public_key: $balanceInSol SOL, required=$requiredSolAmount SOL, network=$network", 'make-market.log', 'make-market', 'DEBUG');
        if ($trade_direction === 'sell' || $trade_direction === 'both') {
            log_message("Token balance for public_key=$short_public_key, token_mint=$token_mint: $tokenBalance tokens (decimals: $decimals), required=$requiredTokenAmount tokens, network=$network", 'make-market.log', 'make-market', 'DEBUG');
        }
    }

    // Check SOL balance for all transactions (buy, sell, or both)
    if ($balanceInSol < $requiredSolAmount) {
        $errors[] = "Insufficient SOL balance: $balanceInSol SOL available, required=$requiredSolAmount SOL";
    }

    // Check token balance for 'sell' or 'both' transactions
    if ($trade_direction === 'sell' || $trade_direction === 'both') {
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
        if (isset($_SESSION['decimals_' . $token_mint])) {
            unset($_SESSION['decimals_' . $token_mint]);
            log_message("Cleared session decimals for token_mint=$token_mint after error", 'make-market.log', 'make-market', 'INFO');
        }
        exit;
    }

    log_message("Balance check passed: SOL balance=$balanceInSol, required SOL=$requiredSolAmount" . ($trade_direction === 'sell' || $trade_direction === 'both' ? ", Token balance=$tokenBalance, required Token=$requiredTokenAmount" : "") . ", network=$network", 'make-market.log', 'make-market', 'INFO');
    echo json_encode([
        'status' => 'success',
        'message' => 'Wallet balance is sufficient to perform the transaction',
        'balance' => $trade_direction === 'buy' ? $balanceInSol : ($trade_direction === 'sell' ? $tokenBalance : ['sol' => $balanceInSol, 'token' => $tokenBalance])
    ], JSON_UNESCAPED_UNICODE);
    if (isset($_SESSION['decimals_' . $token_mint])) {
        unset($_SESSION['decimals_' . $token_mint]);
        log_message("Cleared session decimals for token_mint=$token_mint after success", 'make-market.log', 'make-market', 'INFO');
    }
} catch (Exception $e) {
    log_message("Balance check failed in balance.php: {$e->getMessage()}, network=$network", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance: ' . $e->getMessage()]);
    if (isset($_SESSION['decimals_' . $token_mint])) {
        unset($_SESSION['decimals_' . $token_mint]);
        log_message("Cleared session decimals for token_mint=$token_mint after exception", 'make-market.log', 'make-market', 'INFO');
    }
    exit;
}
?>
