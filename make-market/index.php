<?php
// ============================================================================
// File: make-market/index.php
// Description: Make Market page for automated token trading on Solana using Jupiter API
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Add Security Headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' https://www.vina.network; connect-src 'self' https://www.vina.network https://quote-api.jup.ag https://api.mainnet-beta.solana.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

ob_start();
require_once __DIR__ . '/../config/bootstrap.php';

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

// Check session
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key && strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Make-market.php - Session public_key: " . ($short_public_key ?? 'Not set'), 'make-market.log', 'make-market', 'DEBUG');
if (!$public_key) {
    log_message("No public key in session, redirecting to login", 'make-market.log', 'make-market', 'INFO');
    header('Location: /accounts');
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

// SEO meta
$defaultSlippage = 0.5; // Đồng bộ với giá trị trong form
$root_path = '../';
$page_title = "Make Market - Automated Solana Token Trading | Vina Network";
$page_description = "Automate token trading on Solana with Vina Network's Make Market tool using Jupiter API. Secure, fast, and customizable.";
$page_keywords = "Solana trading, automated token trading, Jupiter API, make market, Vina Network, Solana tokens, crypto trading";
$page_og_title = "Make Market: Automate Your Solana Token Trades with Vina Network";
$page_og_description = "Use Vina Network's Make Market to automate buying and selling Solana tokens with Jupiter API. Try it now!";
$page_og_url = "https://www.vina.network/make-market/";
$page_canonical = "https://www.vina.network/make-market/";
$page_css = ['mm.css'];

// Header
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    log_message("make-market.php: header.php not found at $header_path", 'make-market.log', 'make-market', 'ERROR');
    die('Internal Server Error: Missing header.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<?php include $header_path; ?>
<body>
<?php
$navbar_path = $root_path . 'include/navbar.php';
if (!file_exists($navbar_path)) {
    log_message("make-market.php: navbar.php not found at $navbar_path", 'make-market.log', 'make-market', 'ERROR');
    die('Internal Server Error: Missing navbar.php');
}
include $navbar_path;
?>

<div class="mm-container">
    <h1>🟢 Make Market</h1>
    <div id="account-info">
        <table>
            <tr>
                <th>Public Key</th>
                <td>
                    <?php if ($short_public_key !== 'Invalid'): ?>
                        <a href="https://solscan.io/address/<?php echo htmlspecialchars($account['public_key']); ?>" target="_blank">
                            <?php echo htmlspecialchars($short_public_key); ?>
                        </a>
                        <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($account['public_key']); ?>"></i>
                    <?php else: ?>
                        <span>Invalid address</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    <p style="color: red;">⚠️ Cảnh báo: Nhập private key có rủi ro bảo mật. Hãy đảm bảo bạn hiểu rõ trước khi sử dụng!</p>
    
    <!-- Form Make Market -->
    <form id="makeMarketForm" autocomplete="off">
        <label for="processName">Tên tiến trình:</label>
        <input type="text" name="processName" id="processName" required>
        
        <label>🔑 Private Key (Base58):</label>
        <textarea name="privateKey" required placeholder="Nhập private key..."></textarea>

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

        <button type="submit">🚀 Make Market</button>
    </form>

    <div id="mm-result" class="status-box"></div>
</div>

<?php
$footer_path = $root_path . 'include/footer.php';
if (!file_exists($footer_path)) {
    log_message("make-market.php: footer.php not found at $footer_path", 'make-market.log', 'make-market', 'ERROR');
    die('Internal Server Error: Missing footer.php');
}
include $footer_path;
?>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/@solana/web3.js@1.95.3/lib/index.iife.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@project-serum/anchor@0.26.0/dist/browser/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@solana/spl-token@0.4.8/lib/index.iife.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.7.7/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bs58@6.0.0/index.js"></script>
<script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
<script src="mm-api.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load mm-api.js')"></script>
<script src="mm.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load mm.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
