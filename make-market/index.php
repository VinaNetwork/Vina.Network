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
require_once __DIR__ . '/../config/bootstrap.php';
$root_path = '../';

// Add Security Headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' https://www.vina.network; connect-src 'self' https://www.vina.network https://quote-api.jup.ag https://api.mainnet-beta.solana.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
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
    'cookie_samesite' => 'Strict',
    'gc_maxlifetime' => 3600 // 1 giờ
]);

// Kiểm tra session và public_key
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key && strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Session public_key: " . ($short_public_key ?? 'Not set'), 'make-market.log', 'make-market', 'DEBUG');
if (!$public_key || !preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $public_key)) {
    log_message("Invalid or missing public key in session: " . ($public_key ? substr($public_key, 0, 4) . '...' : 'null'), 'make-market.log', 'make-market', 'ERROR');
    header('Location: /accounts');
    exit;
}

// Database connection
$start_time = microtime(true);
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection successful (took {$duration}ms)", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Fetch account info
try {
    $stmt = $pdo->prepare("SELECT id, public_key FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for public_key: $short_public_key", 'make-market.log', 'make-market', 'ERROR');
        header('Location: /accounts');
        exit;
    }
    log_message("Make Market accessed for public_key: $short_public_key", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving account information']);
    exit;
}

// Fetch transaction history
try {
    $stmt = $pdo->prepare("
        SELECT id, process_name, token_mint, sol_amount, slippage, delay_seconds, 
               loop_count, status, buy_tx_id, sell_tx_id, created_at
        FROM make_market 
        WHERE public_key = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$public_key]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    log_message("Fetched " . count($transactions) . " transactions for public_key: $short_public_key", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Error fetching transaction history: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    $transactions = [];
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

        // Validate inputs
        if (empty($processName) || empty($privateKey) || empty($tokenMint)) {
            log_message("Missing required fields", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit;
        }
        if (!preg_match('/^[A-Za-z0-9]{32,44}$/', $tokenMint)) {
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
        if (strlen($public_key) > 255) {
            log_message("Public key too long: $short_public_key", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Public key too long']);
            exit;
        }

        // Encrypt private key
        $encryptedPrivateKey = openssl_encrypt($privateKey, 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
        if ($encryptedPrivateKey === false) {
            log_message("Failed to encrypt private key", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Encryption failed']);
            exit;
        }

        // Insert transaction into database
        $stmt = $pdo->prepare("
            INSERT INTO make_market (
                user_id, public_key, process_name, private_key, token_mint, 
                sol_amount, slippage, delay_seconds, loop_count, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $account['id'],
            $public_key,
            $processName,
            $encryptedPrivateKey,
            $tokenMint,
            $solAmount,
            $slippage,
            $delay,
            $loopCount
        ]);
        $transactionId = $pdo->lastInsertId();
        log_message("Transaction saved to database: ID=$transactionId, processName=$processName", 'make-market.log', 'make-market', 'INFO');

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
        <h1>🟢 Make Market</h1>
        <div id="account-info">
            <table>
            <tr>
            <th>Public Key</th>
            <td>
            <?php if ($short_public_key !== 'Invalid'): ?>
                <a href="https://solscan.io/address/<?php echo htmlspecialchars($account['public_key'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                    <?php echo htmlspecialchars($short_public_key, ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($account['public_key'], ENT_QUOTES, 'UTF-8'); ?>"></i>
            <?php else: ?>
                <span>Invalid address</span>
            <?php endif; ?>
            </td>
            </tr>
            </table>
        </div>

        <!-- Form Make Market -->
        <form id="makeMarketForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <label for="processName">Tên tiến trình:</label>
            <input type="text" name="processName" id="processName" required>

            <label>🔑 Private Key (Base58):</label>
            <textarea name="privateKey" required placeholder="Nhập private key..."></textarea>
            <p class="note-warning">⚠️ Cảnh báo: Nhập private key có rủi ro bảo mật. Hãy đảm bảo bạn hiểu rõ trước khi sử dụng!</p>

            <label>🎯 Token Address:</label>
            <input type="text" name="tokenMint" required placeholder="VD: So111... hoặc bất kỳ SPL token nào">

            <label>💰 Số lượng SOL muốn mua:</label>
            <input type="number" step="0.01" name="solAmount" required placeholder="VD: 0.1">

            <label>📉 Slippage (%):</label>
            <input type="number" name="slippage" step="0.1" value="<?php echo $defaultSlippage; ?>">

            <label>⏱️ Delay giữa mua và bán (giây):</label>
            <input type="number" name="delay" value="0" min="0">

            <label>🔁 Số vòng lặp:</label>
            <input type="number" name="loopCount" min="1" value="1">

            <button class="cta-button" type="submit">🚀 Make Market</button>
        </form>

        <div id="mm-result" class="status-box"></div>

        <!-- Transaction History -->
        <h2 class="history-title">Lịch sử giao dịch</h2>
        <div id="transaction-history">
            <?php if (empty($transactions)): ?>
                <p>Chưa có giao dịch nào.</p>
            <?php else: ?>
                <table>
                    <tr>
                    <th>ID</th>
                    <th>Tên tiến trình</th>
                    <th>Token Address</th>
                    <th>SOL Amount</th>
                    <th>Slippage (%)</th>
                    <th>Delay (s)</th>
                    <th>Vòng lặp</th>
                    <th>Trạng thái</th>
                    <th>Buy Tx</th>
                    <th>Sell Tx</th>
                    <th>Thời gian</th>
                    </tr>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                        <td><?php echo htmlspecialchars($tx['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($tx['process_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a href="https://solscan.io/token/<?php echo htmlspecialchars($tx['token_mint'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                <?php echo htmlspecialchars(substr($tx['token_mint'], 0, 4) . '...' . substr($tx['token_mint'], -4), ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($tx['sol_amount'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($tx['slippage'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($tx['delay_seconds'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($tx['loop_count'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($tx['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                        <?php if ($tx['buy_tx_id']): ?>
                            <a href="https://solscan.io/tx/<?php echo htmlspecialchars($tx['buy_tx_id'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                <?php echo htmlspecialchars(substr($tx['buy_tx_id'], 0, 4) . '...', ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                        </td>
                        <td>
                        <?php if ($tx['sell_tx_id']): ?>
                            <a href="https://solscan.io/tx/<?php echo htmlspecialchars($tx['sell_tx_id'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                <?php echo htmlspecialchars(substr($tx['sell_tx_id'], 0, 4) . '...', ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($tx['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
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

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/@solana/web3.js@1.95.3/lib/index.iife.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@project-serum/anchor@0.26.0/dist/browser/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@solana/spl-token@0.4.8/lib/index.iife.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.7.7/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios-retry/dist/axios-retry.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bs58@6.0.0/index.js"></script>
<script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
<script src="mm-api.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load mm-api.js')"></script>
<script src="mm.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load mm.js')"></script>
<script>
    console.log('Public Key: <?php echo htmlspecialchars($public_key, ENT_QUOTES, 'UTF-8'); ?>');
</script>
</body>
</html>
<?php ob_end_flush(); ?>
