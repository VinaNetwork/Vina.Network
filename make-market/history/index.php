<?php
// ============================================================================
// File: make-market/history/index.php
// Description: Transaction history page for Make Market
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

// Add Security Headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' https://www.vina.network; connect-src 'self' https://www.vina.network https://quote-api.jup.ag https://api.mainnet-beta.solana.com https://mainnet.helius-rpc.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Access-Control-Allow-Origin: https://www.vina.network");
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

// Check session
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key && strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
if (!$public_key) {
    log_message("No public key in session, redirecting to login", 'make-market.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/make-market/history';
    header('Location: /accounts');
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for session public_key: " . $short_public_key, 'make-market.log', 'make-market', 'ERROR');
        $_SESSION['redirect_url'] = '/make-market/history';
        header('Location: /accounts');
        exit;
    }
} catch (Exception $e) {
    log_message("Error fetching account: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error fetching account']);
    exit;
}

// Handle AJAX request for transaction history
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    try {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 10;
        $offset = ($page - 1) * $per_page;

        $stmt = $pdo->prepare("
            SELECT id, process_name, public_key, token_mint, sol_amount, slippage, delay_seconds, 
                   loop_count, batch_size, status, buy_tx_id, sell_tx_id, created_at, error
            FROM make_market 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $account['id'], PDO::PARAM_INT);
        $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM make_market WHERE user_id = ?");
        $stmt->execute([$account['id']]);
        $total_transactions = $stmt->fetchColumn();
        $total_pages = ceil($total_transactions / $per_page);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'transactions' => $transactions,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_transactions' => $total_transactions
            ]
        ]);
        exit;
    } catch (Exception $e) {
        log_message("Error fetching transaction history: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error fetching transaction history']);
        exit;
    }
}

// SEO meta
$page_title = "Transaction History - Make Market | Vina Network";
$page_description = "View your transaction history for automated Solana token trading with Vina Network's Make Market tool.";
$page_keywords = "Solana trading history, transaction history, make market, Vina Network, Solana tokens";
$page_og_title = "Transaction History: Make Market | Vina Network";
$page_og_description = "Review your past transactions for automated Solana token trading with Vina Network.";
$page_og_url = BASE_URL . "make-market/history/";
$page_canonical = BASE_URL . "make-market/history/";

// CSS for History page
$page_css = ['/make-market/mm.css'];

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
        <h1><i class="fas fa-history"></i> Transaction History</h1>
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

        <div id="transaction-history">
            <p>Loading transaction history...</p>
        </div>

        <div id="mm-result" class="status-box"></div>

        <!-- Link back to Make Market -->
        <div class="history-link">
            <a href="/make-market/" class="cta-button">Back to Make Market</a>
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
<script defer src="/make-market/history/history.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load history.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
