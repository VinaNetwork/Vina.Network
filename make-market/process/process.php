<?php
// ============================================================================
// File: make-market/process/process.php
// Description: Process page for Make Market to execute Solana token swap with looping
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'make-market/process/network.php';
require_once $root_path . '../vendor/autoload.php';

use StephenHill\Base58;
use Attestto\SolanaPhpSdk\Keypair;
use Attestto\SolanaPhpSdk\Connection;
use Attestto\SolanaPhpSdk\PublicKey;

// Add Security Headers
header("Access-Control-Allow-Origin: $csp_base");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With, X-CSRF-Token");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

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

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("process.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
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
$user_public_key = $_SESSION['public_key'] ?? null;
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    $short_user_public_key = $user_public_key ? substr($user_public_key, 0, 4) . '...' . substr($user_public_key, -4) : 'Invalid';
    log_message("Session public_key: $short_user_public_key", 'make-market.log', 'make-market', 'DEBUG');
}
if (!$user_public_key) {
    log_message("No public key in session, redirecting to login", 'make-market.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/make-market/process';
    header('Location: /accounts');
    exit;
}

// Get transaction ID from query parameter
$transaction_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID from query parameter: id={$_GET['id']}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
    exit;
}
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID from URL path: $request_uri", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT user_id, public_key, process_name, token_mint, sol_amount, token_amount, trade_direction, slippage, delay_seconds, loop_count, batch_size, status, error, network FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
    $stmt->execute([$transaction_id, $_SESSION['user_id'], SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Transaction not found, unauthorized, or network mismatch: ID=$transaction_id, user_id={$_SESSION['user_id']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found, unauthorized, or network mismatch']);
        exit;
    }
    log_message("Transaction fetched: ID=$transaction_id, process_name={$transaction['process_name']}, public_key={$transaction['public_key']}, trade_direction={$transaction['trade_direction']}, status={$transaction['status']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction']);
    exit;
}

// Check if transaction is already completed
if (in_array($transaction['status'], ['success', 'failed', 'canceled', 'partial'])) {
    log_message("Transaction already processed: ID=$transaction_id, status={$transaction['status']}", 'make-market.log', 'make-market', 'INFO');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <?php include $root_path . 'include/header.php'; ?>
    <body>
    <?php include $root_path . 'include/navbar.php'; ?>
    <div class="process-container">
        <div class="process-content">
            <h1><i class="fas fa-cogs"></i> Make Market Process</h1>
            <div class="alert alert-info">
                <strong>Transaction Completed:</strong> This transaction has already been processed with status: <?php echo htmlspecialchars($transaction['status']); ?>.
                <?php if ($transaction['error']): ?>
                    <br><strong>Error Details:</strong> <?php echo htmlspecialchars($transaction['error']); ?>
                <?php endif; ?>
                <br><a href="/make-market/history">View Transaction History</a>
            </div>
        </div>
    </div>
    <?php include $root_path . 'include/footer.php'; ?>
    </body>
    </html>
    <?php
    ob_end_flush();
    exit;
}

// Shorten public_key and token_mint for display
$public_key = $transaction['public_key'];
$short_public_key = $public_key ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'N/A';
$token_mint = $transaction['token_mint'];
$short_token_mint = $token_mint ? substr($token_mint, 0, 4) . '...' . substr($token_mint, -4) : 'N/A';

// Check if Cancel button should be displayed
$show_cancel_button = in_array($transaction['status'], ['new', 'pending', 'processing']);

// SEO meta
$page_title = "Make Market Process - Vina Network";
$page_description = "Execute your automated Solana token trading with Vina Network's Make Market tool.";
$page_keywords = "Solana trading, automated trading, Jupiter API, make market, Vina Network";
$page_og_title = "Make Market Process: Automate Solana Token Trading";
$page_og_description = "Execute Solana token swaps using Jupiter Aggregator.";
$page_og_url = BASE_URL . "make-market/process/";
$page_canonical = BASE_URL . "make-market/process/";

// CSS for Process page
$page_css = ['/make-market/process/process.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>

<?php if (SOLANA_NETWORK === 'mainnet'): ?>
<div class="alert alert-warning">
    <strong>Warning:</strong> You are operating on Solana Mainnet. Transactions will use real SOL and tokens. Please verify all details before proceeding.
</div>
<?php endif; ?>

<div class="process-container">
    <div class="process-content">
        <h1><i class="fas fa-cogs"></i> Make Market Process</h1>
        <div class="transaction-details">
            <h2>Transaction Details</h2>
            <table class="details-table">
                <tr>
                    <th>Transaction ID:</th>
                    <td><?php echo htmlspecialchars($transaction_id); ?></td>
                </tr>
                <tr>
                    <th>Process Name:</th>
                    <td><?php echo htmlspecialchars($transaction['process_name']); ?></td>
                </tr>
                <tr>
                    <th>Wallet Address:</th>
                    <td>
                        <a href="https://solscan.io/address/<?php echo htmlspecialchars($public_key); ?><?php echo EXPLORER_QUERY; ?>" target="_blank">
                            <?php echo htmlspecialchars($short_public_key); ?>
                        </a>
                        <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($public_key); ?>"></i>
                    </td>
                </tr>
                <tr>
                    <th>Token Address:</th>
                    <td>
                        <a href="https://solscan.io/address/<?php echo htmlspecialchars($token_mint); ?><?php echo EXPLORER_QUERY; ?>" target="_blank">
                            <?php echo htmlspecialchars($short_token_mint); ?>
                        </a>
                        <i class="fas fa-copy copy-icon" title="Copy full token address" data-full="<?php echo htmlspecialchars($token_mint); ?>"></i>
                    </td>
                </tr>
                <tr>
                    <th>Trade Direction:</th>
                    <td><?php echo htmlspecialchars(ucfirst($transaction['trade_direction'])); ?></td>
                </tr>
                <tr>
                    <th>SOL Amount:</th>
                    <td><?php echo htmlspecialchars($transaction['sol_amount']); ?></td>
                </tr>
                <tr>
                    <th>Token Amount:</th>
                    <td><?php echo htmlspecialchars($transaction['token_amount']); ?></td>
                </tr>
                <tr>
                    <th>Slippage:</th>
                    <td><?php echo htmlspecialchars($transaction['slippage']); ?>%</td>
                </tr>
                <tr>
                    <th>Delay:</th>
                    <td><?php echo htmlspecialchars($transaction['delay_seconds']); ?> seconds</td>
                </tr>
                <tr>
                    <th>Loop Count:</th>
                    <td><?php echo htmlspecialchars($transaction['loop_count']); ?></td>
                </tr>
                <tr>
                    <th>Batch Size:</th>
                    <td><?php echo htmlspecialchars($transaction['batch_size']); ?></td>
                </tr>
                <tr>
                    <th>Total Transactions:</th>
                    <td><?php echo htmlspecialchars($transaction['loop_count'] * $transaction['batch_size']); ?></td>
                </tr>
                <tr>
                    <th>Network:</th>
                    <td><?php echo htmlspecialchars(ucfirst($transaction['network'])); ?></td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td><span id="transaction-status" class="<?php echo $transaction['status'] === 'success' ? 'text-success' : ($transaction['status'] === 'partial' ? 'text-warning' : 'text-danger'); ?>">
                        <?php echo htmlspecialchars($transaction['status']); ?>
                    </span></td>
                </tr>
            </table>
            <p id="swap-status">Preparing swap...</p>
        </div>
        <div id="process-result" class="status-box"></div>
        <div class="action-buttons">
            <?php if ($show_cancel_button): ?>
                <button class="cta-button cancel-btn" id="cancel-btn" data-transaction-id="<?php echo htmlspecialchars($transaction_id); ?>">Cancel</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts - Internal library -->
<script src="/js/libs/solana.web3.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/solana.web3.iife.js')"></script>
<script src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<script src="/js/libs/bs58.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/bs58.js')"></script>
<!-- Scripts - Source code -->
<script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
<script src="/make-market/process/auth.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load auth.js')"></script>
<!-- Pass network to JavaScript -->
<script>
    window.SOLANA_NETWORK = '<?php echo SOLANA_NETWORK; ?>';
    window.ENVIRONMENT = '<?php echo defined('ENVIRONMENT') ? ENVIRONMENT : 'production'; ?>';
</script>
<script src="/make-market/process/process.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load process.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
