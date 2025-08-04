<?php
// ============================================================================
// File: make-market/history/history.php
// Description: History page for Make Market to display transaction history
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';
require_once $root_path . '../vendor/autoload.php';

use StephenHill\Base58;
use Attestto\SolanaPhpSdk\PublicKey;

// Add Security Headers
$csp_base = rtrim(BASE_URL, '/');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' $csp_base; connect-src 'self' $csp_base; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header("Access-Control-Allow-Origin: $csp_base");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With");
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
    log_message("history.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
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
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_public_key || !$user_id) {
    log_message("No public key or user ID in session, redirecting to login", 'make-market.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/make-market/history';
    header('Location: /accounts');
    exit;
}

// Fetch transaction history
try {
    $stmt = $pdo->prepare("
        SELECT id, process_name, public_key, token_mint, sol_amount, slippage, delay_seconds, loop_count, batch_size, status, error, created_at 
        FROM make_market 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    log_message("Fetched " . count($transactions) . " transactions for user_id=$user_id", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction history']);
    exit;
}

// SEO meta
$page_title = "Make Market History - Vina Network";
$page_description = "View your automated Solana token trading history with Vina Network's Make Market tool.";
$page_keywords = "Solana trading, transaction history, make market, Vina Network";
$page_og_title = "Make Market History: View Solana Token Trading";
$page_og_description = "Review your Solana token swap history using Jupiter Aggregator.";
$page_og_url = BASE_URL . "make-market/history/";
$page_canonical = BASE_URL . "make-market/history/";

// CSS for History page
$page_css = ['/make-market/history/history.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>

<div class="history-container">
    <div class="history-content">
        <h1><i class="fas fa-history"></i> Make Market History</h1>
        <div id="account-info">
            <table>
                <tr>
                    <th>Wallet Address:</th>
                    <td>
                        <a href="https://solscan.io/address/<?php echo htmlspecialchars($user_public_key); ?>" target="_blank">
                            <?php echo htmlspecialchars(substr($user_public_key, 0, 4) . '...' . substr($user_public_key, -4)); ?>
                        </a>
                        <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($user_public_key); ?>"></i>
                    </td>
                </tr>
            </table>
        </div>
        <div class="transaction-history">
            <?php if (empty($transactions)): ?>
                <p>No transactions found.</p>
            <?php else: ?>
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th class="desktop-only">ID</th>
                            <th>Process Name</th>
                            <th>Token Address</th>
                            <th class="desktop-only">SOL Amount</th>
                            <th class="desktop-only">Slippage</th>
                            <th class="desktop-only">Delay (s)</th>
                            <th class="desktop-only">Loops</th>
                            <th class="desktop-only">Batches</th>
                            <th>Total Tx</th>
                            <th>Status</th>
                            <th class="desktop-only">Created At</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <?php
                            $short_token_mint = substr($tx['token_mint'], 0, 4) . '...' . substr($tx['token_mint'], -4);
                            $total_tx = $tx['loop_count'] * $tx['batch_size'];
                            $status_class = $tx['status'] === 'success' ? 'text-success' : ($tx['status'] === 'partial' ? 'text-warning' : 'text-danger');
                            ?>
                            <tr class="transaction-row" data-id="<?php echo $tx['id']; ?>">
                                <td class="desktop-only"><?php echo htmlspecialchars($tx['id']); ?></td>
                                <td><?php echo htmlspecialchars($tx['process_name']); ?></td>
                                <td>
                                    <a href="https://solscan.io/address/<?php echo htmlspecialchars($tx['token_mint']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($short_token_mint); ?>
                                    </a>
                                    <i class="fas fa-copy copy-icon" title="Copy full token address" data-full="<?php echo htmlspecialchars($tx['token_mint']); ?>"></i>
                                </td>
                                <td class="desktop-only"><?php echo htmlspecialchars($tx['sol_amount']); ?></td>
                                <td class="desktop-only"><?php echo htmlspecialchars($tx['slippage']); ?>%</td>
                                <td class="desktop-only"><?php echo htmlspecialchars($tx['delay_seconds']); ?></td>
                                <td class="desktop-only"><?php echo htmlspecialchars($tx['loop_count']); ?></td>
                                <td class="desktop-only"><?php echo htmlspecialchars($tx['batch_size']); ?></td>
                                <td><?php echo htmlspecialchars($total_tx); ?></td>
                                <td class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($tx['status']); ?></td>
                                <td class="desktop-only"><?php echo htmlspecialchars($tx['created_at']); ?></td>
                                <td>
                                    <button class="details-btn" data-id="<?php echo $tx['id']; ?>">View Details</button>
                                </td>
                            </tr>
                            <tr class="mobile-details" style="display: none;">
                                <td colspan="12">
                                    <div class="mobile-details-content">
                                        <p><strong>ID:</strong> <?php echo htmlspecialchars($tx['id']); ?></p>
                                        <p><strong>SOL Amount:</strong> <?php echo htmlspecialchars($tx['sol_amount']); ?></p>
                                        <p><strong>Slippage:</strong> <?php echo htmlspecialchars($tx['slippage']); ?>%</p>
                                        <p><strong>Delay:</strong> <?php echo htmlspecialchars($tx['delay_seconds']); ?>s</p>
                                        <p><strong>Loops:</strong> <?php echo htmlspecialchars($tx['loop_count']); ?></p>
                                        <p><strong>Batches:</strong> <?php echo htmlspecialchars($tx['batch_size']); ?></p>
                                        <p><strong>Created At:</strong> <?php echo htmlspecialchars($tx['created_at']); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div id="sub-transaction-details" class="sub-transaction-box"></div>
    </div>
</div>

<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts -->
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<script defer src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script defer src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
<script defer src="/make-market/history/history.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load history.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
