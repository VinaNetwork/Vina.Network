<?php
// ============================================================================
// File: mm/index.php
// Description: Make Market page for automated token trading on Solana using Jupiter API
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . '../vendor/autoload.php';
require_once $root_path . 'mm/header-auth.php'; // Security Headers

use Attestto\SolanaPhpSdk\Keypair;
use StephenHill\Base58;

// Session start: in config/bootstrap.php
// Error reporting: in config/bootstrap.php

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("index.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
}

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
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Check session for authentication
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("Session public_key: $short_public_key", 'make-market.log', 'make-market', 'DEBUG');
}
if (!$public_key) {
    log_message("No public key in session, redirecting to login", 'make-market.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/mm';
    header('Location: /accounts');
    exit;
}

// Fetch account info for user_id
try {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for session public_key: $short_public_key", 'make-market.log', 'make-market', 'ERROR');
        $_SESSION['redirect_url'] = '/mm';
        header('Location: /accounts');
        exit;
    }
    $_SESSION['user_id'] = $account['id'];
    log_message("Session updated with user_id: {$account['id']}, public_key: $short_public_key", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving account information']);
    exit;
}

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        log_message("Form submitted, is AJAX: " . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? 'Yes' : 'No'), 'make-market.log', 'make-market', 'INFO');
        $form_data = $_POST;
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            log_message("Form data: " . json_encode($form_data), 'make-market.log', 'make-market', 'DEBUG');
        }

        // Get form data
        $processName = $form_data['processName'] ?? '';
        $privateKey = trim($form_data['privateKey'] ?? '');
        $tokenMint = $form_data['tokenMint'] ?? '';
        $tradeDirection = $form_data['tradeDirection'] ?? 'buy';
        $solAmount = $tradeDirection === 'sell' ? 0 : floatval($form_data['solAmount'] ?? 0);
        $tokenAmount = isset($form_data['tokenAmount']) && $form_data['tokenAmount'] !== '' ? floatval($form_data['tokenAmount']) : 0;
        $slippage = floatval($form_data['slippage'] ?? 0.5);
        $delay = intval($form_data['delay'] ?? 0);
        $loopCount = intval($form_data['loopCount'] ?? 1);
        $batchSize = intval($form_data['batchSize'] ?? 5);
        $skipBalanceCheck = isset($form_data['skipBalanceCheck']) && $form_data['skipBalanceCheck'] == '1';

        // Log form data for debugging
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            log_message("Form data: processName=$processName, tokenMint=$tokenMint, solAmount=$solAmount, tokenAmount=$tokenAmount, tradeDirection=$tradeDirection, slippage=$slippage, delay=$delay, loopCount=$loopCount, batchSize=$batchSize, privateKey_length=" . strlen($privateKey) . ", skipBalanceCheck=$skipBalanceCheck", 'make-market.log', 'make-market', 'DEBUG');
        }

        // Validate inputs
        if (empty($processName) || empty($privateKey) || empty($tokenMint)) {
            log_message("Missing required fields", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit;
        }
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{64,128}$/', $privateKey)) {
            log_message("Invalid private key format: length=" . strlen($privateKey), 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid private key format']);
            exit;
        }
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $tokenMint)) {
            log_message("Invalid token address: $tokenMint", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid token address']);
            exit;
        }
        if ($tradeDirection === 'buy') {
            if ($solAmount <= 0) {
                log_message("Invalid SOL amount for buy: $solAmount", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'SOL amount must be greater than 0 for buy transactions']);
                exit;
            }
            if ($tokenAmount != 0) {
                log_message("Invalid token amount for buy: $tokenAmount, must be exactly 0", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Token amount must be exactly 0 for buy transactions']);
                exit;
            }
        } elseif ($tradeDirection === 'sell') {
            if ($tokenAmount <= 0) {
                log_message("Invalid token amount for sell: $tokenAmount", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Token amount must be greater than 0 for sell transactions']);
                exit;
            }
        } elseif ($tradeDirection === 'both') {
            if ($solAmount <= 0) {
                log_message("Invalid SOL amount for both: $solAmount", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'SOL amount must be greater than 0 for both transactions']);
                exit;
            }
            if ($tokenAmount <= 0) {
                log_message("Invalid token amount for both: $tokenAmount", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Token amount must be greater than 0 for both transactions']);
                exit;
            }
        } else {
            log_message("Invalid trade direction: $tradeDirection", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid trade direction']);
            exit;
        }
        if ($slippage < 0) {
            log_message("Invalid slippage: $slippage", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Slippage must be non-negative']);
            exit;
        }
        if ($loopCount < 1) {
            log_message("Invalid loop count: $loopCount", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Loop count must be at least 1']);
            exit;
        }
        if ($batchSize < 1 || $batchSize > 10) {
            log_message("Invalid batch size: $batchSize", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Batch size must be between 1 and 10']);
            exit;
        }

        // Validate private key using SolanaPhpSdk and derive transactionPublicKey
        try {
            $base58 = new Base58();
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                log_message("Decoding private key, length: " . strlen($privateKey), 'make-market.log', 'make-market', 'DEBUG');
            }
            $decodedKey = $base58->decode($privateKey);
            if (strlen($decodedKey) !== 64) {
                log_message("Invalid private key length: " . strlen($decodedKey) . ", expected 64 bytes", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Invalid private key length']);
                exit;
            }
            $keypair = Keypair::fromSecretKey($decodedKey);
            $transactionPublicKey = $keypair->getPublicKey()->toBase58();
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                log_message("Derived public key: $transactionPublicKey", 'make-market.log', 'make-market', 'DEBUG');
            }
            log_message("Private key validated: public_key=$transactionPublicKey", 'make-market.log', 'make-market', 'INFO');
        } catch (Exception $e) {
            log_message("Invalid private key: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid private key: ' . $e->getMessage()]);
            exit;
        }

        // Check wallet balance by calling balance.php, unless skipped or trade direction conditions are not met
if (!$skipBalanceCheck && isValidTradeDirection($tradeDirection, $solAmount, $tokenAmount)) {
    log_message("Calling balance.php: tradeDirection=$tradeDirection, solAmount=$solAmount, tokenAmount=$tokenAmount, session_id=" . session_id(), 'make-market.log', 'make-market', 'INFO');
    try {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => BASE_URL . "mm/balance.php",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                'public_key' => $transactionPublicKey,
                'token_mint' => $tokenMint,
                'trade_direction' => $tradeDirection,
                'sol_amount' => $solAmount,
                'token_amount' => $tokenAmount,
                'loop_count' => $loopCount,
                'batch_size' => $batchSize,
                'csrf_token' => $csrf_token
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json; charset=utf-8",
                "X-Requested-With: XMLHttpRequest",
                "X-CSRF-Token: $csrf_token",
                "Cookie: PHPSESSID=" . session_id()
            ],
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        // Ghi log chi tiết phản hồi
        log_message("balance.php response: HTTP=$http_code, response=" . ($response ?: 'empty'), 'make-market.log', 'make-market', 'DEBUG');

        if ($err) {
            log_message("Failed to call balance.php: cURL error: $err", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Connection error while checking wallet balance: ' . $err]);
            exit;
        }

        if ($http_code !== 200) {
            log_message("Failed to call balance.php: HTTP $http_code, response=" . ($response ?: 'empty'), 'make-market.log', 'make-market', 'ERROR');
            $data = json_decode($response, true);
            $errorMessage = isset($data['message']) ? $data['message'] : 'Unknown error while checking wallet balance';
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $errorMessage]);
            exit;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message("Failed to parse balance.php response: " . json_last_error_msg() . ", raw_response=" . ($response ?: 'empty'), 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error parsing response from wallet balance check']);
            exit;
        }

        if ($data['status'] !== 'success') {
            log_message("Balance check failed: {$data['message']}", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $data['message']]);
            exit;
        }

        log_message("Balance check passed: {$data['message']}, balance=" . json_encode($data['balance']), 'make-market.log', 'make-market', 'INFO');
    } catch (Exception $e) {
        log_message("Balance check failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error checking wallet balance: ' . $e->getMessage()]);
        exit;
    }
        } else {
            log_message("Wallet balance check skipped: skipBalanceCheck=$skipBalanceCheck, validTradeDirection=" . (isValidTradeDirection($tradeDirection, $solAmount, $tokenAmount) ? 'true' : 'false'), 'make-market.log', 'make-market', 'INFO');
        }

        // Check JWT_SECRET
        if (!defined('JWT_SECRET') || empty(JWT_SECRET)) {
            log_message("JWT_SECRET is not defined or empty", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Missing JWT_SECRET']);
            exit;
        }

        // Encrypt private key
        $encryptedPrivateKey = openssl_encrypt($privateKey, 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
        if ($encryptedPrivateKey === false) {
            log_message("Failed to encrypt private key: OpenSSL error", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error encrypting private key']);
            exit;
        }

        // Insert transaction into database with status 'new'
        try {
            $stmt = $pdo->prepare("
                INSERT INTO make_market (
                    user_id, public_key, process_name, private_key, token_mint, 
                    trade_direction, sol_amount, token_amount, slippage, delay_seconds, 
                    loop_count, batch_size, status, network
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?)
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
                SOLANA_NETWORK
            ]);
            $transactionId = $pdo->lastInsertId();
            log_message("Transaction saved to database: ID=$transactionId, processName=$processName, public_key=" . substr($transactionPublicKey, 0, 4) . "...", 'make-market.log', 'make-market', 'INFO');
            log_message("Form saved to database: transactionId=$transactionId", 'make-market.log', 'make-market', 'INFO');
        } catch (PDOException $e) {
            log_message("Database insert failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error saving transaction to database']);
            exit;
        }

        // Send redirect
        $redirect_url = "/mm/process/$transactionId";
        log_message("Sending redirect to $redirect_url", 'make-market.log', 'make-market', 'INFO');
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'transactionId' => $transactionId, 'redirect' => $redirect_url]);
        } else {
            header("Location: $redirect_url");
        }
        exit;
    } catch (Exception $e) {
        log_message("Error saving transaction: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error saving transaction: ' . $e->getMessage()]);
        exit;
    }
}

// SEO meta
$page_title = "Make Market - Automated Solana Token Trading | Vina Network";
$page_description = "Automate token trading on Solana with Vina Network's Make Market tool, using Jupiter API. Secure, fast, and customizable.";
$page_keywords = "Solana trading, automated token trading, Jupiter API, make market, Vina Network, Solana token, crypto trading";
$page_og_title = "Make Market: Automate Solana Token Trading with Vina Network";
$page_og_description = "Use Vina Network's Make Market to automatically buy and sell Solana tokens with Jupiter API. Try now!";
$page_og_url = BASE_URL . "mm/";
$page_canonical = BASE_URL . "mm/";
$page_css = ['/mm/mm.css'];
// Slippage
$defaultSlippage = 0.5;
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>

<div class="mm-container">
    <div class="mm-content">
        <h1><i class="fas fa-chart-line"></i> Make Market</h1>
        <div id="account-info">
            <table>
                <tr>
                    <th>Account:</th>
                    <td>
                        <?php if ($short_public_key !== 'Invalid'): ?>
                            <a href="https://solscan.io/address/<?php echo htmlspecialchars($public_key); ?>" target="_blank">
                                <?php echo htmlspecialchars($short_public_key); ?>
                            </a>
                            <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($public_key); ?>"></i>
                        <?php else: ?>
                            <span>Invalid address</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Form Make Market -->
        <form id="makeMarketForm" autocomplete="off" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?: ''); ?>">
            <label for="processName">Process Name:</label>
            <input type="text" name="processName" id="processName" required>
            <label for="privateKey">🔑 Private Key (Base58):</label>
            <textarea name="privateKey" id="privateKey" required placeholder="Enter private key..."></textarea>
            <label for="tokenMint">🎯 Token Address:</label>
            <input type="text" name="tokenMint" id="tokenMint" required placeholder="Example: So111... or any SPL token">
            <label for="tradeDirection">📈 Trade Direction:</label>
            <select name="tradeDirection" id="tradeDirection" required>
                <option value="buy">Buy</option>
                <option value="sell">Sell</option>
                <option value="both">Both (Buy and Sell)</option>
            </select>
            <label for="solAmount">💰 SOL Amount:</label>
            <input type="number" step="0.01" name="solAmount" id="solAmount" required placeholder="Example: 0.1">
            <label for="tokenAmount">🪙 Token Amount:</label>
            <input type="number" step="0.000000001" name="tokenAmount" id="tokenAmount" placeholder="Example: 1000.0" disabled value="0">
            <label for="slippage">📉 Slippage (%):</label>
            <input type="number" name="slippage" id="slippage" step="0.1" value="<?php echo $defaultSlippage; ?>">
            <label for="delay">⏱️ Delay between 2 batch (seconds):</label>
            <input type="number" name="delay" id="delay" value="0" min="0">
            <label for="loopCount">🔁 Loop Count:</label>
            <input type="number" name="loopCount" id="loopCount" min="1" value="1">
            <label for="batchSize">📦 Batch Size (1-10):</label>
            <input type="number" name="batchSize" id="batchSize" min="1" max="10" value="5" required>
            <label for="skipBalanceCheck" class="check-box">
                <input type="checkbox" name="skipBalanceCheck" id="skipBalanceCheck" value="1">
                <p>Skip wallet balance check</p>
            </label>
            <p class="note">If you skip it, make sure your wallet balance is enough to complete the transaction.</p>
            <button class="cta-button" type="submit">🚀 Make Market</button>
        </form>

        <div id="mm-result" class="status-box"></div>

        <!-- Link to Transaction History -->
        <div class="history-link">
            <a href="/mm/history/">View Transaction History</a>
        </div>
    </div>
</div>

<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts - Internal library -->
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<script defer src="/js/libs/anchor.umd.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/anchor.umd.js')"></script>
<script defer src="/js/libs/spl-token.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/spl-token.iife.js')"></script>
<!-- Scripts - Source code -->
<script defer src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script defer src="/mm/mm.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /mm/mm.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
