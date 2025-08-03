<?php
// ============================================================================
// File: make-market/process/process.php
// Description: Process page for displaying transaction progress and validation results
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}
$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';

// Add Security Headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' https://www.vina.network; connect-src 'self' https://www.vina.network https://quote-api.jup.ag https://mainnet.helius-rpc.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
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
try {
    $pdo = get_db_connection();
    log_message("Database connection established for process.php", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Check session for authentication
$public_key = $_SESSION['public_key'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;
$short_public_key = $public_key && strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Session public_key: $short_public_key, user_id: $user_id", 'make-market.log', 'make-market', 'DEBUG');
if (!$public_key || !$user_id) {
    log_message("No public key or user_id in session, redirecting to login", 'make-market.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/make-market';
    header('Location: /accounts');
    exit;
}

// Get transaction ID from URL
$transaction_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$transaction_id) {
    log_message("Invalid or missing transaction ID in URL", 'make-market.log', 'make-market', 'ERROR');
    header('Location: /make-market');
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("
        SELECT process_name, public_key, token_mint, sol_amount, slippage, delay_seconds, loop_count, batch_size, status
        FROM make_market
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$transaction_id, $user_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Transaction ID $transaction_id not found or not authorized for user_id $user_id", 'make-market.log', 'make-market', 'ERROR');
        header('Location: /make-market');
        exit;
    }
    log_message("Fetched transaction ID $transaction_id: process_name={$transaction['process_name']}", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Error fetching transaction ID $transaction_id: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Location: /make-market');
    exit;
}

// SEO meta
$page_title = "Transaction Progress - Make Market | Vina Network";
$page_description = "Track the progress of your automated token trading transaction on Solana.";
$page_keywords = "Solana trading, transaction progress, Make Market, Vina Network";
$page_og_title = "Track Your Make Market Transaction Progress";
$page_og_description = "Monitor your automated Solana token trading with Vina Network.";
$page_og_url = BASE_URL . "make-market/process/$transaction_id";
$page_canonical = BASE_URL . "make-market/process/$transaction_id";
$page_css = ['/make-market/mm.css'];

// Header
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    log_message("header.php not found at $header_path", 'make-market.log', 'make-market', 'ERROR');
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
    log_message("navbar.php not found at $navbar_path", 'make-market.log', 'make-market', 'ERROR');
    die('Internal Server Error: Missing navbar.php');
}
include $navbar_path;
?>

<div class="process-container">
    <h1><i class="fas fa-spinner"></i> Transaction Progress</h1>
    <h2>Process: <?php echo htmlspecialchars($transaction['process_name']); ?> (ID: <?php echo $transaction_id; ?>)</h2>

    <!-- Pre-transaction checks -->
    <div class="check-list">
        <h3>Pre-transaction Checks</h3>
        <p id="check-private-key">Checking private key: <span>Loading...</span></p>
        <p id="check-token">Checking token mint: <span>Loading...</span></p>
        <p id="check-liquidity">Checking liquidity: <span>Loading...</span></p>
    </div>

    <!-- Error message (if checks fail) -->
    <div id="check-error" class="status-box" style="display: none;"></div>

    <!-- Transaction progress (shown if checks pass) -->
    <div id="progress-section" style="display: none;">
        <h3>Transaction Progress</h3>
        <div class="progress-bar">
            <div class="progress" id="progress-bar"></div>
        </div>
        <p>Progress: <span id="progress-text">0%</span></p>
        <div class="transaction-details">
            <p><strong>Process Name:</strong> <?php echo htmlspecialchars($transaction['process_name']); ?></p>
            <p><strong>Token Address:</strong> <a href="https://solscan.io/token/<?php echo htmlspecialchars($transaction['token_mint']); ?>" target="_blank"><?php echo substr($transaction['token_mint'], 0, 4) . '...' . substr($transaction['token_mint'], -4); ?></a></p>
            <p><strong>SOL Amount:</strong> <?php echo htmlspecialchars($transaction['sol_amount']); ?></p>
            <p><strong>Slippage:</strong> <?php echo htmlspecialchars($transaction['slippage']); ?>%</p>
            <p><strong>Delay:</strong> <?php echo htmlspecialchars($transaction['delay_seconds']); ?>s</p>
            <p><strong>Loop Count:</strong> <span id="current-loop">0</span>/<?php echo htmlspecialchars($transaction['loop_count']); ?></p>
            <p><strong>Batch Size:</strong> <?php echo htmlspecialchars($transaction['batch_size']); ?></p>
            <p><strong>Status:</strong> <span id="transaction-status"><?php echo htmlspecialchars($transaction['status']); ?></span></p>
        </div>
        <div class="transaction-log" id="transaction-log">
            <p>Loading transaction log...</p>
        </div>
        <div class="action-buttons">
            <button id="cancel-btn" class="cta-button cancel-btn" style="display: none;" onclick="showCancelConfirmation(<?php echo $transaction_id; ?>)">Cancel</button>
            <button onclick="window.location.href='/make-market/'">Back</button>
        </div>
    </div>

    <!-- Confirmation popup -->
    <div class="confirmation-popup" id="cancel-confirmation" style="display: none;">
        <div class="confirmation-popup-content">
            <p>Are you sure you want to cancel process <?php echo $transaction_id; ?>?</p>
            <button class="cta-button confirm-btn" onclick="confirmCancel(<?php echo $transaction_id; ?>)">Confirm</button>
            <button class="cta-button cancel-popup-btn" onclick="closeCancelConfirmation()">Cancel</button>
        </div>
    </div>
</div>

<?php
$footer_path = $root_path . 'include/footer.php';
if (!file_exists($footer_path)) {
    log_message("footer.php not found at $footer_path", 'make-market.log', 'make-market', 'ERROR');
    die('Internal Server Error: Missing footer.php');
}
include $footer_path;
?>

<!-- Scripts - Internal library -->
<script defer src="/js/libs/solana.web3.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/solana.web3.iife.js')"></script>
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<script defer src="/js/libs/bs58.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/bs58.js')"></script>
<script defer src="/js/libs/spl-token.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/spl-token.iife.js')"></script>
<!-- Scripts - Source code -->
<script defer src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script defer src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
<script defer src="/make-market/mm-api.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load mm-api.js')"></script>
<script defer src="/make-market/process.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load process.js')"></script>
<!-- Define PHP variables for process.js -->
<script defer>
    const TRANSACTION_ID = <?php echo json_encode($transaction_id); ?>;
    const LOOP_COUNT = <?php echo json_encode($transaction['loop_count']); ?>;
    const PUBLIC_KEY = <?php echo json_encode($transaction['public_key']); ?>;
    const TOKEN_MINT = <?php echo json_encode($transaction['token_mint']); ?>;
    const SOL_AMOUNT = <?php echo json_encode($transaction['sol_amount']); ?>;
    const SLIPPAGE = <?php echo json_encode($transaction['slippage']); ?>;
    const HELIUS_API_KEY = <?php echo defined('HELIUS_API_KEY') ? json_encode(HELIUS_API_KEY) : "''"; ?>;
</script>
</body>
</html>
<?php ob_end_flush(); ?>
