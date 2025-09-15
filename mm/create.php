<?php
// ============================================================================
// File: mm/create.php
// Description: Transaction progress creation page.
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../';
// constants | logging | config | error | session | database | header-auth | CSRF | Network | vendor/autoload
require_once $root_path . 'mm/bootstrap.php';

use Attestto\SolanaPhpSdk\Keypair;
use StephenHill\Base58;

// Log request info
log_message("create.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');

// Protect POST requests with CSRF
csrf_protect();

// Set CSRF cookie for potential AJAX requests
if (!set_csrf_cookie()) {
    log_message("Failed to set CSRF cookie", 'make-market.log', 'make-market', 'ERROR');
} else {
    log_message("CSRF cookie set successfully for Make Market page", 'make-market.log', 'make-market', 'INFO');
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
if ($csrf_token === false) {
    log_message("Failed to generate CSRF token", 'make-market.log', 'make-market', 'ERROR');
} else {
    log_message("CSRF token generated successfully for Make Market page", 'make-market.log', 'make-market', 'INFO');
}

// Database connection
$start_time = microtime(true);
try {
    $pdo = get_db_connection();
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection retrieved (took {$duration}ms)", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()}, Stack trace: {$e->getTraceAsString()} (took {$duration}ms)", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Check session for authentication
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Session public_key: $short_public_key", 'make-market.log', 'make-market', 'DEBUG');
if (!$public_key) {
    log_message("No public key in session, redirecting to login", 'make-market.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/mm/create-process';
    header('Location: /acc/connect-p');
    exit;
}

// Fetch account info for user_id
try {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for session public_key: $short_public_key", 'make-market.log', 'make-market', 'ERROR');
        $_SESSION['redirect_url'] = '/mm/create-process';
        header('Location: /acc/connect-p');
        exit;
    }
    $_SESSION['user_id'] = $account['id'];
    log_message("Session updated with user_id: {$account['id']}, public_key: $short_public_key", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}, Stack trace: {$e->getTraceAsString()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving account information']);
    exit;
}

// Fetch private keys for the user
$noWallets = false;
$wallets = [];
try {
    $stmt = $pdo->prepare("SELECT id, wallet_name, public_key FROM private_key WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($wallets)) {
        log_message("No active private keys found for user_id: {$_SESSION['user_id']}", 'make-market.log', 'make-market', 'INFO');
        $noWallets = true;
    }
} catch (PDOException $e) {
    log_message("Failed to fetch private keys: {$e->getMessage()}, Stack trace: {$e->getTraceAsString()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving private keys']);
    exit;
}

// Initialize error message for form submission
$errorMessage = null;

// Function to validate Trade Direction conditions
function isValidTradeDirection($tradeDirection, $solAmount, $tokenAmount) {
    if ($tradeDirection === 'buy') {
        return $solAmount > 0 && $tokenAmount == 0;
    }
    if ($tradeDirection === 'sell') {
        return $tokenAmount > 0;
    }
    if ($tradeDirection === 'both') {
        return $solAmount > 0 && $tokenAmount > 0;
    }
    return false;
}

// Handle form submission (only if wallets exist)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$noWallets) {
    // Check X-Auth-Token for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $headers = getallheaders();
        $authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;
        if ($authToken !== JWT_SECRET) {
            log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token']);
            exit;
        }
    }

    try {
        log_message("Form submitted, is AJAX: " . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? 'Yes' : 'No'), 'make-market.log', 'make-market', 'INFO');
        
        // Get form data
        $form_data = $_POST;
        $processName = $form_data['processName'] ?? '';
        $walletId = $form_data['walletId'] ?? '';
        $tokenMint = $form_data['tokenMint'] ?? '';
        $tradeDirection = $form_data['tradeDirection'] ?? 'buy';
        $solAmount = $tradeDirection === 'sell' ? 0 : floatval($form_data['solAmount'] ?? 0);
        $tokenAmount = isset($form_data['tokenAmount']) && $form_data['tokenAmount'] !== '' ? floatval($form_data['tokenAmount']) : 0;
        $slippage = floatval($form_data['slippage'] ?? 0.5);
        $delay = intval($form_data['delay'] ?? 0);
        $loopCount = intval($form_data['loopCount'] ?? 1);
        $batchSize = intval($form_data['batchSize'] ?? 2);
        $network = SOLANA_NETWORK;
        $skipBalanceCheck = isset($form_data['skipBalanceCheck']) && $form_data['skipBalanceCheck'] == '1';

        // Log form data securely
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            $logFormData = $form_data;
            unset($logFormData['walletId']);
            $logFormData['walletId'] = $walletId;
            log_message("Form data: " . json_encode($logFormData), 'make-market.log', 'make-market', 'DEBUG');
            log_message(
                "Form data: processName=$processName, walletId=$walletId, tokenMint=$tokenMint, solAmount=$solAmount, tokenAmount=$tokenAmount, tradeDirection=$tradeDirection, slippage=$slippage, delay=$delay, loopCount=$loopCount, batchSize=$batchSize, network=$network, skipBalanceCheck=$skipBalanceCheck",
                'make-market.log',
                'make-market',
                'DEBUG'
            );
        } else {
            log_message(
                "Form data: processName=$processName, walletId=$walletId, tokenMint=$tokenMint, solAmount=$solAmount, tokenAmount=$tokenAmount, tradeDirection=$tradeDirection, slippage=$slippage, delay=$delay, loopCount=$loopCount, batchSize=$batchSize, network=$network, skipBalanceCheck=$skipBalanceCheck",
                'make-market.log',
                'make-market',
                'INFO'
            );
        }

        // Validate inputs
        if (empty($processName) || empty($walletId) || empty($tokenMint)) {
            log_message("Missing required fields: processName=" . ($processName ?: 'empty') . ", walletId=" . ($walletId ?: 'empty') . ", tokenMint=" . ($tokenMint ?: 'empty'), 'make-market.log', 'make-market', 'ERROR');
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
                exit;
            } else {
                $errorMessage = 'Missing required fields';
            }
        }
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $tokenMint)) {
            log_message("Invalid token address: $tokenMint", 'make-market.log', 'make-market', 'ERROR');
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Invalid token address']);
                exit;
            } else {
                $errorMessage = 'Invalid token address';
            }
        }
        if ($tradeDirection === 'buy') {
            if ($solAmount <= 0) {
                log_message("Invalid SOL amount for buy: $solAmount", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'SOL amount must be greater than 0 for buy transactions']);
                    exit;
                } else {
                    $errorMessage = 'SOL amount must be greater than 0 for buy transactions';
                }
            }
            if ($tokenAmount != 0) {
                log_message("Invalid token amount for buy: $tokenAmount, must be exactly 0", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Token amount must be exactly 0 for buy transactions']);
                    exit;
                } else {
                    $errorMessage = 'Token amount must be exactly 0 for buy transactions';
                }
            }
        } elseif ($tradeDirection === 'sell') {
            if ($tokenAmount <= 0) {
                log_message("Invalid token amount for sell: $tokenAmount", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Token amount must be greater than 0 for sell transactions']);
                    exit;
                } else {
                    $errorMessage = 'Token amount must be greater than 0 for sell transactions';
                }
            }
        } elseif ($tradeDirection === 'both') {
            if ($solAmount <= 0) {
                log_message("Invalid SOL amount for both: $solAmount", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'SOL amount must be greater than 0 for both transactions']);
                    exit;
                } else {
                    $errorMessage = 'SOL amount must be greater than 0 for both transactions';
                }
            }
            if ($tokenAmount <= 0) {
                log_message("Invalid token amount for both: $tokenAmount", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Token amount must be greater than 0 for both transactions']);
                    exit;
                } else {
                    $errorMessage = 'Token amount must be greater than 0 for both transactions';
                }
            }
        } else {
            log_message("Invalid trade direction: $tradeDirection", 'make-market.log', 'make-market', 'ERROR');
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Invalid trade direction']);
                exit;
            } else {
                $errorMessage = 'Invalid trade direction';
            }
        }
        if ($slippage < 0 || $slippage > 999.99) {
            log_message("Invalid slippage: $slippage, must be between 0 and 999.99", 'make-market.log', 'make-market', 'ERROR');
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Slippage must be between 0 and 999.99']);
                exit;
            } else {
                $errorMessage = 'Slippage must be between 0 and 999.99';
            }
        }
        if ($loopCount < 1) {
            log_message("Invalid loop count: $loopCount", 'make-market.log', 'make-market', 'ERROR');
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Loop count must be at least 1']);
                exit;
            } else {
                $errorMessage = 'Loop count must be at least 1';
            }
        }
        if ($batchSize < 2 || $batchSize > 10) {
            log_message("Invalid batch size: $batchSize", 'make-market.log', 'make-market', 'ERROR');
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Batch size must be between 2 and 10']);
                exit;
            } else {
                $errorMessage = 'Batch size must be between 2 and 10';
            }
        }

        // Fetch and decrypt private key from database
        try {
            $stmt = $pdo->prepare("SELECT private_key, public_key FROM private_key WHERE id = ? AND user_id = ? AND status = 'active'");
            $stmt->execute([$walletId, $_SESSION['user_id']]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$wallet) {
                log_message("Invalid or inactive wallet ID: $walletId for user_id: {$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Invalid or inactive wallet selected']);
                    exit;
                } else {
                    $errorMessage = 'Invalid or inactive wallet selected';
                }
            }
            $encryptedPrivateKey = $wallet['private_key'];
            $transactionPublicKey = $wallet['public_key'];

            // Decrypt private key
            $privateKey = openssl_decrypt($encryptedPrivateKey, 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
            if ($privateKey === false) {
                $error = openssl_error_string();
                log_message("Failed to decrypt private key for wallet ID: $walletId, error: $error", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Error decrypting private key']);
                    exit;
                } else {
                    $errorMessage = 'Error decrypting private key';
                }
            }
            log_message("Private key decrypted successfully for wallet ID: $walletId", 'make-market.log', 'make-market', 'INFO');
        } catch (PDOException $e) {
            log_message("Failed to fetch private key: {$e->getMessage()}, Stack trace: {$e->getTraceAsString()}", 'make-market.log', 'make-market', 'ERROR');
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Error retrieving private key']);
                exit;
            } else {
                $errorMessage = 'Error retrieving private key';
            }
        }

        // Validate private key using SolanaPhpSdk
        if (!$errorMessage) {
            try {
                $base58 = new Base58();
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    log_message("Decoding private key, length: " . strlen($privateKey), 'make-market.log', 'make-market', 'DEBUG');
                }
                $decodedKey = $base58->decode($privateKey);
                if (strlen($decodedKey) !== 64) {
                    log_message("Invalid private key length: " . strlen($decodedKey) . ", expected 64 bytes", 'make-market.log', 'make-market', 'ERROR');
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'error', 'message' => 'Invalid private key length']);
                        exit;
                    } else {
                        $errorMessage = 'Invalid private key length';
                    }
                }
                $keypair = Keypair::fromSecretKey($decodedKey);
                $derivedPublicKey = $keypair->getPublicKey()->toBase58();
                if ($derivedPublicKey !== $transactionPublicKey) {
                    log_message("Derived public key mismatch: derived=$derivedPublicKey, expected=$transactionPublicKey", 'make-market.log', 'make-market', 'ERROR');
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'error', 'message' => 'Public key mismatch for selected wallet']);
                        exit;
                    } else {
                        $errorMessage = 'Public key mismatch for selected wallet';
                    }
                }
                log_message("Private key validated: public_key=$transactionPublicKey", 'make-market.log', 'make-market', 'INFO');
            } catch (Exception $e) {
                log_message("Invalid private key: {$e->getMessage()}, Stack trace: {$e->getTraceAsString()}", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Invalid private key: ' . $e->getMessage()]);
                    exit;
                } else {
                    $errorMessage = 'Invalid private key: ' . $e->getMessage();
                }
            }
        }

        // Call decimals.php to get the decimals of the mint token
        if (!$errorMessage) {
            log_message("Calling check-decimals.php for token_mint=$tokenMint", 'make-market.log', 'make-market', 'INFO');
            try {
                ob_start();
                $_POST = [
                    'token_mint' => $tokenMint,
                    'network' => $network,
                    'csrf_token' => $csrf_token
                ];
                $_SERVER['REQUEST_METHOD'] = 'POST';
                $_SERVER['REQUEST_URI'] = '/mm/endpoints-c/check-decimals.php';
                $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
                $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf_token;
                $_COOKIE['PHPSESSID'] = session_id();

                // Refresh CSRF
                $csrf_token = generate_csrf_token();
                log_message("CSRF token refreshed before calling check-decimals.php: $csrf_token", 'make-market.log', 'make-market', 'INFO');
                $_POST['csrf_token'] = $csrf_token;
                $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf_token;

                // Call decimals.php
                require_once $root_path . 'mm/endpoints-c/check-decimals.php';
                $response = ob_get_clean();

                // Check feedback
                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    log_message("Failed to parse check-decimals.php response: " . json_last_error_msg() . ", raw_response=" . ($response ?: 'empty'), 'make-market.log', 'make-market', 'ERROR');
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'error', 'message' => 'Error parsing response from token decimals check']);
                        exit;
                    } else {
                        $errorMessage = 'Error parsing response from token decimals check';
                    }
                }

                if ($data['status'] !== 'success') {
                    log_message("Decimals check failed: {$data['message']}", 'make-market.log', 'make-market', 'ERROR');
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'error', 'message' => $data['message']]);
                        exit;
                    } else {
                        $errorMessage = $data['message'];
                    }
                }

                $decimals = intval($data['decimals']);
                log_message("Decimals check passed: decimals=$decimals", 'make-market.log', 'make-market', 'INFO');
            } catch (Exception $e) {
                if (!session_id()) {
                    session_start();
                }
                log_message("Decimals check failed: {$e->getMessage()}, Stack trace: {$e->getTraceAsString()}", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Error checking token decimals: ' . $e->getMessage()]);
                    exit;
                } else {
                    $errorMessage = 'Error checking token decimals: ' . $e->getMessage();
                }
            }
        }

        // Check wallet balance by calling check-balance.php, unless skipped or trade direction conditions are not met
        if (!$errorMessage && !$skipBalanceCheck && isValidTradeDirection($tradeDirection, $solAmount, $tokenAmount)) {
            log_message("Calling check-balance.php: tradeDirection=$tradeDirection, solAmount=$solAmount, tokenAmount=$tokenAmount, network=$network, session_id=" . session_id(), 'make-market.log', 'make-market', 'INFO');
            try {
                ob_start();
                $_POST = [
                    'public_key' => $transactionPublicKey,
                    'token_mint' => $tokenMint,
                    'trade_direction' => $tradeDirection,
                    'sol_amount' => $solAmount,
                    'token_amount' => $tokenAmount,
                    'loop_count' => $loopCount,
                    'batch_size' => $batchSize,
                    'network' => $network,
                    'csrf_token' => $csrf_token
                ];
                $_SERVER['REQUEST_METHOD'] = 'POST';
                $_SERVER['REQUEST_URI'] = '/mm/endpoints-c/check-balance.php';
                $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
                $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf_token;
                $_COOKIE['PHPSESSID'] = session_id();

                // Refresh CSRF
                $csrf_token = generate_csrf_token();
                log_message("CSRF token refreshed before calling check-balance.php: $csrf_token", 'make-market.log', 'make-market', 'INFO');
                $_POST['csrf_token'] = $csrf_token;
                $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf_token;
                
                // Call check-balance.php
                require_once $root_path . 'mm/endpoints-c/check-balance.php';
                $response = ob_get_clean();

                // Check feedback
                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    log_message("Failed to parse check-balance.php response: " . json_last_error_msg() . ", raw_response=" . ($response ?: 'empty'), 'make-market.log', 'make-market', 'ERROR');
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'error', 'message' => 'Error parsing response from wallet balance check']);
                        exit;
                    } else {
                        $errorMessage = 'Error parsing response from wallet balance check';
                    }
                }

                if ($data['status'] !== 'success') {
                    log_message("Balance check failed: {$data['message']}", 'make-market.log', 'make-market', 'ERROR');
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'error', 'message' => $data['message']]);
                        exit;
                    } else {
                        $errorMessage = $data['message'];
                    }
                } else {
                    log_message("Balance check passed: {$data['message']}, balance=" . json_encode($data['balance']), 'make-market.log', 'make-market', 'INFO');
                }
            } catch (Exception $e) {
                if (!session_id()) {
                    session_start();
                }
                log_message("Balance check failed: {$e->getMessage()}, Stack trace: {$e->getTraceAsString()}", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance: ' . $e->getMessage()]);
                    exit;
                } else {
                    $errorMessage = 'Error checking wallet balance: ' . $e->getMessage();
                }
            }
        } else {
            log_message("Wallet balance check skipped: skipBalanceCheck=$skipBalanceCheck, validTradeDirection=" . (isValidTradeDirection($tradeDirection, $solAmount, $tokenAmount) ? 'true' : 'false'), 'make-market.log', 'make-market', 'INFO');
        }

        // Proceed with transaction if no errors
        if (!$errorMessage) {
            // Check JWT_SECRET
            if (!defined('JWT_SECRET') || strlen(JWT_SECRET) < 32) {
                log_message("JWT_SECRET is invalid: length=" . (defined('JWT_SECRET') ? strlen(JWT_SECRET) : 0), 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Invalid JWT_SECRET']);
                    exit;
                } else {
                    $errorMessage = 'Server configuration error: Invalid JWT_SECRET';
                }
            }

            // Re-encrypt private key for storage
            $encryptedPrivateKey = openssl_encrypt($privateKey, 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
            if ($encryptedPrivateKey === false) {
                $error = openssl_error_string();
                log_message("Failed to encrypt private key: OpenSSL error - $error", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Error encrypting private key: ' . $error]);
                    exit;
                } else {
                    $errorMessage = 'Error encrypting private key: ' . $error;
                }
            }

            // Check user_id exists in accounts table
            try {
                $stmt = $pdo->prepare("SELECT id FROM accounts WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                if (!$stmt->fetch()) {
                    log_message("Invalid user_id: {$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
                        exit;
                    } else {
                        $errorMessage = 'Invalid user ID';
                    }
                }
            } catch (PDOException $e) {
                log_message("Failed to verify user_id: {$e->getMessage()}, Stack trace: {$e->getTraceAsString()}", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Error verifying user ID']);
                    exit;
                } else {
                    $errorMessage = 'Error verifying user ID';
                }
            }

            // Insert transaction into database with status 'new'
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO make_market (
                        user_id, public_key, process_name, private_key, token_mint, 
                        trade_direction, sol_amount, token_amount, slippage, delay_seconds, 
                        loop_count, batch_size, decimals, status, network
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $transactionPublicKey,
                    $processName,
                    $encryptedPrivateKey,
                    $tokenMint,
                    $tradeDirection,
                    $solAmount,
                    $tokenAmount,
                    $slippage,
                    $delay,
                    $loopCount,
                    $batchSize,
                    $decimals,
                    $network
                ]);
                $transactionId = $pdo->lastInsertId();
                log_message("Transaction saved to database: ID=$transactionId, processName=$processName, public_key=" . substr($transactionPublicKey, 0, 4) . "..., network=$network", 'make-market.log', 'make-market', 'INFO');
            } catch (PDOException $e) {
                log_message("Database insert failed: {$e->getMessage()}, Code: {$e->getCode()}, Query: INSERT INTO make_market..., Stack trace: {$e->getTraceAsString()}", 'make-market.log', 'make-market', 'ERROR');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Error saving transaction to database: ' . $e->getMessage()]);
                    exit;
                } else {
                    $errorMessage = 'Error saving transaction to database: ' . $e->getMessage();
                }
            }

            // Create transient token
            $transient_token = bin2hex(random_bytes(16));      // Generate random token
            $_SESSION['transient_token'] = $transient_token;   // Save to session
            $_SESSION['transient_token_expiry'] = time() + 60; // Token expires in 1 minute
            log_message("Transient token generated: $transient_token for transaction ID=$transactionId", 'make-market.log', 'make-market', 'INFO');

            // Check for headers sent before redirect
            if (headers_sent($file, $line)) {
                log_message("Headers already sent in $file at line $line", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Internal server error: Headers already sent']);
                exit;
            }

            // Send redirect
            $redirect_url = "/mm/process/$transactionId?token=" . urlencode($transient_token);
            log_message("Sending redirect to $redirect_url", 'make-market.log', 'make-market', 'INFO');
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'transactionId' => $transactionId, 'redirect' => $redirect_url]);
                log_message("AJAX response sent: " . json_encode(['status' => 'success', 'transactionId' => $transactionId, 'redirect' => $redirect_url]), 'make-market.log', 'make-market', 'DEBUG');
            } else {
                header("Location: $redirect_url");
                log_message("Non-AJAX redirect sent to: $redirect_url", 'make-market.log', 'make-market', 'INFO');
            }
            exit;
        }
    } catch (Exception $e) {
        log_message("Error saving transaction: {$e->getMessage()}, Stack trace: {$e->getTraceAsString()}", 'make-market.log', 'make-market', 'ERROR');
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error saving transaction: ' . $e->getMessage()]);
            exit;
        } else {
            $errorMessage = 'Error saving transaction: ' . $e->getMessage();
        }
    }
}

// SEO meta
$page_title = "Make Market - Automated Solana Token Trading | Vina Network";
$page_description = "Automate token trading on Solana with Vina Network's Make Market tool, using Jupiter API. Secure, fast, and customizable.";
$page_keywords = "Solana trading, automated token trading, Jupiter API, make market, Vina Network, Solana token, crypto trading";
$page_url = BASE_URL . "mm/create";
$page_css = ['/mm/css/create.css'];
$defaultSlippage = 0.5; // Slippage
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="mm-container">
    <div class="mm-content">
        <h1><i class="fas fa-chart-line"></i> Make Market</h1>
        <p class="mm-network">Network: <?php echo htmlspecialchars(SOLANA_NETWORK); ?></p>

        <?php if ($noWallets): ?>
            <div class="status-box active error">
                <p>No private keys available. You need to add a private key to create a market transaction.</p>
                <a href="/mm/add-private-key" class="cta-button">Add Private Key Now</a>
            </div>
        <?php elseif ($errorMessage): ?>
            <div class="status-box active error">
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
                <?php if (strpos($errorMessage, 'Insufficient SOL balance') !== false): ?>
                    <p>Please deposit more SOL to your wallet or enable "Skip Balance Check" to proceed.</p>
                <?php endif; ?>
                <button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear Notification</button>
            </div>
            <!-- Still show the form to allow retry -->
            <form id="makeMarketForm" autocomplete="off" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?: ''); ?>">
                <label for="processName">Process Name:</label>
                <input type="text" name="processName" id="processName" required>
                <label for="walletId">🔑 Wallet:</label>
                <select name="walletId" id="walletId" required>
                    <option value="">Select a wallet</option>
                    <?php foreach ($wallets as $wallet): ?>
                        <option value="<?php echo htmlspecialchars($wallet['id']); ?>">
                            <?php echo htmlspecialchars($wallet['wallet_name'] ?: 'Wallet ' . substr($wallet['public_key'], 0, 4) . '...' . substr($wallet['public_key'], -4)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="tokenMint">🎯 Token Address:</label>
                <input type="text" name="tokenMint" id="tokenMint" required placeholder="Enter token address...">
                <label for="tradeDirection">📈 Trade Direction:</label>
                <select name="tradeDirection" id="tradeDirection" required>
                    <option value="buy">Buy</option>
                    <option value="sell">Sell</option>
                    <option value="both">Both (Buy and Sell)</option>
                </select>
                <label for="solAmount">💰 SOL Amount:</label>
                <input type="number" step="0.01" name="solAmount" id="solAmount" required placeholder="Enter SOL Amount...">
                <label for="tokenAmount">🪙 Token Amount:</label>
                <input type="number" step="0.000000001" name="tokenAmount" id="tokenAmount" placeholder="Enter Token Amount..." disabled value="0">
                <label for="slippage">📉 Slippage (%):</label>
                <input type="number" name="slippage" id="slippage" step="0.1" value="<?php echo $defaultSlippage; ?>">
                <label for="delay">⏱️ Delay between 2 batch (seconds):</label>
                <input type="number" name="delay" id="delay" value="0" min="0">
                <label for="loopCount">🔁 Loop Count:</label>
                <input type="number" name="loopCount" id="loopCount" min="1" value="1">
                <label for="batchSize">📦 Batch Size (2-10):</label>
                <input type="number" name="batchSize" id="batchSize" min="2" max="10" value="2" required>
                <label for="skipBalanceCheck" class="check-box">
                    <input type="checkbox" name="skipBalanceCheck" id="skipBalanceCheck" value="1">
                    <p>Skip wallet balance check</p>
                </label>
                <p class="note">If you skip it, make sure your wallet balance is enough to complete the transaction.</p>
                <button class="cta-button" type="submit">🚀 Make Market</button>
            </form>
            <div id="mm-result" class="status-box"></div>
        <?php else: ?>
            <!-- Form Make Market -->
            <form id="makeMarketForm" autocomplete="off" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?: ''); ?>">
                <label for="processName">Process Name:</label>
                <input type="text" name="processName" id="processName" required>
                <label for="walletId">🔑 Wallet:</label>
                <select name="walletId" id="walletId" required>
                    <option value="">Select a wallet</option>
                    <?php foreach ($wallets as $wallet): ?>
                        <option value="<?php echo htmlspecialchars($wallet['id']); ?>">
                            <?php echo htmlspecialchars($wallet['wallet_name'] ?: 'Wallet ' . substr($wallet['public_key'], 0, 4) . '...' . substr($wallet['public_key'], -4)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="tokenMint">🎯 Token Address:</label>
                <input type="text" name="tokenMint" id="tokenMint" required placeholder="Enter token address...">
                <label for="tradeDirection">📈 Trade Direction:</label>
                <select name="tradeDirection" id="tradeDirection" required>
                    <option value="buy">Buy</option>
                    <option value="sell">Sell</option>
                    <option value="both">Both (Buy and Sell)</option>
                </select>
                <label for="solAmount">💰 SOL Amount:</label>
                <input type="number" step="0.01" name="solAmount" id="solAmount" required placeholder="Enter SOL Amount...">
                <label for="tokenAmount">🪙 Token Amount:</label>
                <input type="number" step="0.000000001" name="tokenAmount" id="tokenAmount" placeholder="Enter Token Amount..." disabled value="0">
                <label for="slippage">📉 Slippage (%):</label>
                <input type="number" name="slippage" id="slippage" step="0.1" value="<?php echo $defaultSlippage; ?>">
                <label for="delay">⏱️ Delay between 2 batch (seconds):</label>
                <input type="number" name="delay" id="delay" value="0" min="0">
                <label for="loopCount">🔁 Loop Count:</label>
                <input type="number" name="loopCount" id="loopCount" min="1" value="1">
                <label for="batchSize">📦 Batch Size (2-10):</label>
                <input type="number" name="batchSize" id="batchSize" min="2" max="10" value="2" required>
                <label for="skipBalanceCheck" class="check-box">
                    <input type="checkbox" name="skipBalanceCheck" id="skipBalanceCheck" value="1">
                    <p>Skip wallet balance check</p>
                </label>
                <p class="note">If you skip it, make sure your wallet balance is enough to complete the transaction.</p>
                <button class="cta-button" type="submit">🚀 Create Process</button>
            </form>
            <div id="mm-result" class="status-box"></div>
        <?php endif; ?>

        <!-- Link to Transaction History -->
        <div class="lists-process">
            <a href="/mm/lists-process">View lists process</a>
        </div>
    </div>
</div>
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts - Internal library -->
<script defer src="/js/libs/axios.min.js"></script>
<script defer src="/js/libs/anchor.umd.js"></script>
<script defer src="/js/libs/spl-token.iife.js"></script>
<!-- Global variable -->
<script>
    // Passing JWT_SECRET into JavaScript securely
    const authToken = '<?php echo htmlspecialchars(JWT_SECRET); ?>';
</script>
<!-- Scripts - Source code -->
<script defer src="/js/vina.js"></script>
<script defer src="/mm/js/create.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
