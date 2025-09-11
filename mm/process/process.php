<?php
// ============================================================================
// File: mm/process/process.php
// Description: Process page for Make Market to execute Solana token swap with looping
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

// Solana Library
use StephenHill\Base58;
use Attestto\SolanaPhpSdk\Keypair;
use Attestto\SolanaPhpSdk\Connection;
use Attestto\SolanaPhpSdk\PublicKey;

// Initialize logging context
$log_context = [
    'endpoint' => 'process',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
];

// Log request info
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$session_id = session_id() ?: 'none';
$headers = apache_request_headers();
$cookies = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message(
        "process.php: Request received, method=$request_method, uri=$request_uri, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers) . ", raw_GET=" . json_encode($_GET),
        'process.log', 'make-market', 'DEBUG', $log_context
    );
}

// Check request method
if ($request_method !== 'GET') {
    log_message("Invalid request method: $request_method, uri=$request_uri", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check transient token
$transient_token = isset($_GET['token']) ? trim($_GET['token']) : null;
if (!$transient_token || !isset($_SESSION['transient_token']) || $transient_token !== $_SESSION['transient_token'] || time() > $_SESSION['transient_token_expiry']) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message(
        "Invalid or missing transient token: token=" . ($transient_token ?: 'none') . ", user_id=$user_id, session_token=" . (isset($_SESSION['transient_token']) ? $_SESSION['transient_token'] : 'none') . ", expiry=" . (isset($_SESSION['transient_token_expiry']) ? $_SESSION['transient_token_expiry'] : 'none'),
        'process.log', 'make-market', 'ERROR', $log_context
    );
    // Clear transient token from session
    unset($_SESSION['transient_token'], $_SESSION['transient_token_expiry']);
    // Redirect to error page
    $_SESSION['error_message'] = 'Access denied: Please initiate the transaction from the Make Market page.';
    log_message("Setting error_message in session: {$_SESSION['error_message']}, session_id=$session_id", 'process.log', 'make-market', 'DEBUG', $log_context);
    session_write_close();
    header('Location: /mm/error');
    exit;
}
// Clear transient token after use
unset($_SESSION['transient_token'], $_SESSION['transient_token_expiry']);
log_message("Transient token validated and cleared, token=$transient_token, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'INFO', $log_context);

// Initialize session
if (!ensure_session()) {
    log_message("Failed to initialize session, method=$request_method, uri=$request_uri", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check session for authentication
$user_public_key = $_SESSION['public_key'] ?? null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    $short_user_public_key = $user_public_key ? substr($user_public_key, 0, 4) . '...' . substr($user_public_key, -4) : 'Invalid';
    log_message("Session public_key: $short_user_public_key, user_id=" . ($user_id ?? 'none'), 'process.log', 'make-market', 'DEBUG', $log_context);
}
if (!$user_public_key || !$user_id) {
    log_message(
        "Missing public key or user_id in session, clearing session and redirecting to login, public_key=" . ($user_public_key ? substr($user_public_key, 0, 4) . '...' . substr($user_public_key, -4) : 'none') . ", user_id=" . ($user_id ?? 'none'),
        'process.log', 'make-market', 'INFO', $log_context
    );
    session_destroy(); // Clear session to avoid using old session
    $_SESSION['redirect_url'] = '/mm/process';
    session_write_close();
    header('Location: /acc/connect-p');
    exit;
}

// Database connection
$start_time = microtime(true);
try {
    $pdo = get_db_connection();
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection retrieved (took {$duration}ms), user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'INFO', $log_context);
} catch (Exception $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: " . $e->getMessage() . " (took {$duration}ms), user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    $_SESSION['error_message'] = 'Database connection failed';
    session_write_close();
    header('Location: /mm/error');
    exit;
}

// Get transaction ID from query parameter
$transaction_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;
$log_context['transaction_id'] = $transaction_id;
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID from query parameter: id={$_GET['id']}, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    $_SESSION['error_message'] = 'Invalid transaction ID';
    log_message("Setting error_message in session: {$_SESSION['error_message']}, session_id=$session_id", 'process.log', 'make-market', 'DEBUG', $log_context);
    session_write_close();
    header('Location: /mm/error');
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT user_id, public_key, process_name, token_mint, sol_amount, token_amount, trade_direction, slippage, delay_seconds, loop_count, batch_size, status, error, network FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
    $stmt->execute([$transaction_id, $user_id, SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        $stmt_check_id = $pdo->prepare("SELECT COUNT(*) FROM make_market WHERE id = ?");
        $stmt_check_id->execute([$transaction_id]);
        $id_exists = $stmt_check_id->fetchColumn();

        $stmt_check_user = $pdo->prepare("SELECT COUNT(*) FROM make_market WHERE id = ? AND user_id = ?");
        $stmt_check_user->execute([$transaction_id, $user_id]);
        $user_matches = $stmt_check_user->fetchColumn();

        $stmt_check_network = $pdo->prepare("SELECT COUNT(*) FROM make_market WHERE id = ? AND network = ?");
        $stmt_check_network->execute([$transaction_id, SOLANA_NETWORK]);
        $network_matches = $stmt_check_network->fetchColumn();

        $error_message = "Transaction not found, unauthorized, or network mismatch: ID=$transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", id_exists=$id_exists, user_matches=$user_matches, network_matches=$network_matches";
        log_message($error_message, 'process.log', 'make-market', 'ERROR', $log_context);
        $_SESSION['error_message'] = 'Transaction not found or you do not have permission to access it.';
        log_message("Setting error_message in session: {$_SESSION['error_message']}, session_id=$session_id", 'process.log', 'make-market', 'DEBUG', $log_context);
        session_write_close();
        header('Location: /mm/error');
        exit;
    }
    log_message("Transaction fetched: ID=$transaction_id, process_name={$transaction['process_name']}, public_key=" . substr($transaction['public_key'], 0, 4) . "... , trade_direction={$transaction['trade_direction']}, status={$transaction['status']}, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'INFO', $log_context);
} catch (PDOException $e) {
    log_message("Database query failed: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    $_SESSION['error_message'] = 'Error retrieving transaction. Please try again later.';
    log_message("Setting error_message in session: {$_SESSION['error_message']}, session_id=$session_id", 'process.log', 'make-market', 'DEBUG', $log_context);
    session_write_close();
    header('Location: /mm/error');
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
$page_og_url = BASE_URL . "mm/create";
$page_canonical = BASE_URL . "mm/create";
$page_css = ['/mm/process/process.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="process-container">
    <div class="process-content">
        <h1><i class="fas fa-cogs"></i> Make Market Process</h1>
        <div class="transaction-details">
            <table class="details-table">
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
                    <th>Transaction ID:</th>
                    <td><?php echo htmlspecialchars($transaction_id); ?></td>
                </tr>
                <tr>
                    <th>Process Name:</th>
                    <td><?php echo htmlspecialchars($transaction['process_name']); ?></td>
                </tr>
                <tr>
                    <th>Network:</th>
                    <td><?php echo htmlspecialchars(ucfirst($transaction['network'])); ?></td>
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
                <form id="cancel-form" method="POST" action="/mm/get-status">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($transaction_id); ?>">
                    <input type="hidden" name="status" value="canceled">
                    <input type="hidden" name="error" value="Transaction canceled by user">
                    <button type="submit" class="cta-button cancel-btn" id="cancel-btn">Cancel</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts - Internal library -->
<script src="/js/libs/solana.web3.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/solana.web3.iife.js')"></script>
<script src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<script src="/js/libs/bs58.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/bs58.js')"></script>
<!-- Global variable -->
<script>
    // Passing JWT_SECRET into JavaScript securely
    const authToken = '<?php echo htmlspecialchars(JWT_SECRET); ?>';
</script>
<!-- Scripts - Source code -->
<script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script type="module" src="/mm/process/process.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load process.js')"></script>
<!-- Note: Transaction processing is handled by /mm/process/process.js via Jupiter API -->
</body>
</html>
<?php ob_end_flush(); ?>
