<?php
// ============================================================================
// File: make-market/index.php
// Description: Make Market page for automated token trading on Solana using Jupiter API
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';

// Add Security Headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' https://www.vina.network; connect-src 'self' https://www.vina.network https://quote-api.jup.ag https://api.mainnet-beta.solana.com https://api.helius.xyz; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Error reporting
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

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
$short_public_key = $public_key && strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Session public_key: " . ($short_public_key ?? 'Not set'), 'make-market.log', 'make-market', 'DEBUG');
if (!$public_key) {
    log_message("No public key in session, redirecting to login", 'make-market.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/make-market';
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
        $_SESSION['redirect_url'] = '/make-market';
        header('Location: /accounts');
        exit;
    }
    // Lưu user_id vào session
    $_SESSION['user_id'] = $account['id'];
    log_message("Session updated with user_id: {$account['id']}, public_key: $short_public_key", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving account information']);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            log_message("Invalid CSRF token", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
            exit;
        }

        // Get form data
        $processName = $_POST['processName'] ?? '';
        $privateKey = $_POST['privateKey'] ?? '';
        $tokenMint = $_POST['tokenMint'] ?? '';
        $solAmount = floatval($_POST['solAmount'] ?? 0);
        $slippage = floatval($_POST['slippage'] ?? 0.5);
        $delay = intval($_POST['delay'] ?? 0);
        $loopCount = intval($_POST['loopCount'] ?? 1);
        $batchSize = intval($_POST['batchSize'] ?? 5);
        $transactionPublicKey = $_POST['transactionPublicKey'] ?? '';

        // Validate inputs
        if (empty($processName) || empty($privateKey) || empty($tokenMint) || empty($transactionPublicKey)) {
            log_message("Missing required fields", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit;
        }
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{64,128}$/', $privateKey)) {
            log_message("Invalid private key format: length=" . strlen($privateKey), 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid private key format, length=' . strlen($privateKey)]);
            exit;
        }
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $transactionPublicKey)) {
            log_message("Invalid transaction public key format: " . substr($transactionPublicKey, 0, 4) . "...", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid transaction public key format']);
            exit;
        }
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $tokenMint)) {
            log_message("Invalid token address: $tokenMint", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid token address']);
            exit;
        }
        if ($solAmount <= 0) {
            log_message("Invalid SOL amount: $solAmount", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'SOL amount must be positive']);
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

        // Encrypt private key for checking
        $encryptedPrivateKey = openssl_encrypt($privateKey, 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
        if ($encryptedPrivateKey === false) {
            log_message("Failed to encrypt private key", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Encryption failed']);
            exit;
        }

        // Check if private_key is already running a pending process
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM make_market 
            WHERE private_key = ? AND status = 'pending'
        ");
        $stmt->execute([$encryptedPrivateKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['count'] > 0) {
            log_message("Private key is already running a pending process", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'This private key is currently running another process. Please wait for it to complete or use a different private key.'
            ]);
            exit;
        }

        // Insert transaction into database
        $stmt = $pdo->prepare("
            INSERT INTO make_market (
                user_id, public_key, process_name, private_key, token_mint, 
                sol_amount, slippage, delay_seconds, loop_count, batch_size, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $_SESSION['user_id'], // Sử dụng user_id từ session
            $transactionPublicKey,
            $processName,
            $encryptedPrivateKey,
            $tokenMint,
            $solAmount,
            $slippage,
            $delay,
            $loopCount,
            $batchSize
        ]);
        $transactionId = $pdo->lastInsertId();
        log_message("Transaction saved to database: ID=$transactionId, processName=$processName, public_key=" . substr($transactionPublicKey, 0, 4) . "...", 'make-market.log', 'make-market', 'INFO');

        // Return transaction ID for JavaScript to process
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'transactionId' => $transactionId]);
        exit;
    } catch (Exception $e) {
        log_message("Error saving transaction: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error saving transaction']);
        exit;
    }
}

// SEO meta
$page_title = "Make Market - Automated Solana Token Trading | Vina Network";
$page_description = "Automate token trading on Solana with Vina Network's Make Market tool using Jupiter API. Secure, fast, and customizable.";
$page_keywords = "Solana trading, automated token trading, Jupiter API, make market, Vina Network, Solana tokens, crypto trading";
$page_og_title = "Make Market: Automate Your Solana Token Trades with Vina Network";
$page_og_description = "Use Vina Network's Make Market to automate buying and selling Solana tokens with Jupiter API. Try it now!";
$page_og_url = BASE_URL . "make-market/";
$page_canonical = BASE_URL . "make-market/";

// CSS for Make Market
$page_css = ['mm.css'];
// Slippage
$defaultSlippage = 0.5;

// Header
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    log_message("header.php not found at $header_path", 'make-market.log', 'make-market', 'ERROR');
    die('Internal Server Error: Missing header.php');
}
log_message("Including header.php", 'make-market.log', 'make-market', 'DEBUG');
?>

<!DOCTYPE html>
<html lang="en">
<?php include $header_path; ?>
<body>
<?php
$navbar_path = $root_path . 'include/navbar.php';
if (!file_exists($navbar_path)) {
    log_message("navbar.php not found at $navbar_path", 'make-market.log', 'make-market', 'ERROR');
    die('Internal Server Error: Missing navbar.php');
}
log_message("Including navbar.php", 'make-market.log', 'make-market', 'DEBUG');
include $navbar_path;
?>

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
        <form id="makeMarketForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="transactionPublicKey" id="transactionPublicKey">
            <label for="processName">Process Name:</label>
            <input type="text" name="processName" id="processName" required>

            <label for="privateKey">🔑 Private Key (Base58):</label>
            <textarea name="privateKey" id="privateKey" required placeholder="Enter private key..."></textarea>
            <p class="note-warning">⚠️ Warning: Entering a private key carries security risks. Ensure you understand before proceeding!</p>

            <label for="tokenMint">🎯 Token Address:</label>
            <input type="text" name="tokenMint" id="tokenMint" required placeholder="E.g., So111... or any SPL token">

            <label for="solAmount">💰 SOL Amount to Buy:</label>
            <input type="number" step="0.01" name="solAmount" id="solAmount" required placeholder="E.g., 0.1">

            <label for="slippage">📉 Slippage (%):</label>
            <input type="number" name="slippage" id="slippage" step="0.1" value="<?php echo $defaultSlippage; ?>">

            <label for="delay">⏱️ Delay between Buy and Sell (seconds):</label>
            <input type="number" name="delay" id="delay" value="0" min="0">

            <label for="loopCount">🔁 Loop Count:</label>
            <input type="number" name="loopCount" id="loopCount" min="1" value="1">

            <label for="batchSize">📦 Batch Size (1-10):</label>
            <input type="number" name="batchSize" id="batchSize" min="1" max="10" value="5" required>

            <button class="cta-button" type="submit">🚀 Make Market</button>
        </form>

        <div id="mm-result" class="status-box"></div>

        <!-- Transaction History -->
        <h2 class="history-title">Transaction History</h2>
        <div id="transaction-history">
            <p>Loading transaction history...</p>
        </div>
    </div>
</div>

<?php
$footer_path = $root_path . 'include/footer.php';
if (!file_exists($footer_path)) {
    log_message("footer.php not found at $footer_path", 'make-market.log', 'make-market', 'ERROR');
    die('Internal Server Error: Missing footer.php');
}
log_message("Including footer.php", 'make-market.log', 'make-market', 'DEBUG');
include $footer_path;
?>

<!-- Scripts - Internal library -->
<script defer src="/js/libs/solana.web3.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/solana.web3.iife.js')"></script>
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<script defer src="/js/libs/bs58.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/bs58.js')"></script>
<script defer src="/js/libs/anchor.umd.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/anchor.umd.js')"></script>
<script defer src="/js/libs/spl-token.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/spl-token.iife.js')"></script>
<!-- Scripts - Source code -->
<script defer src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script defer src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
<script defer src="mm-api.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load mm-api.js')"></script>
<script defer src="mm.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load mm.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
