<?php
// ============================================================================
// File: make-market/process/view-process.php
// Description: View Process page for Make Market.
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

$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($transaction_id <= 0) {
    log_message("Invalid transaction ID: {$_GET['id'] ?? 'not set'}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM make_market WHERE id = ? AND user_id = ?");
    $stmt->execute([$transaction_id, $_SESSION['user_id'] ?? 0]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Transaction not found: ID=$transaction_id, user_id={$_SESSION['user_id'] ?? 'not set'}", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }
    log_message("Transaction loaded for view: ID=$transaction_id, process_name={$transaction['process_name']}", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit;
}

// SEO meta
$page_title = "View Transaction #{$transaction_id} - Vina Network";
$page_description = "View details of Make Market transaction #{$transaction_id} on Vina Network.";
$page_keywords = "Solana trading, transaction details, Vina Network, make market";
$page_og_title = "Transaction #{$transaction_id} Details";
$page_og_description = "View details of your Make Market transaction on Vina Network.";
$page_og_url = BASE_URL . "make-market/process/{$transaction_id}";
$page_canonical = BASE_URL . "make-market/process/{$transaction_id}";
$page_css = ['/make-market/process/process.css'];
?>
<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="process-container">
    <div class="process-content">
        <h1><i class="fas fa-cogs"></i> Transaction Details #<?php echo htmlspecialchars($transaction_id); ?></h1>
        <div class="alert alert-info">
            <h2>Transaction Information</h2>
            <table class="transaction-details">
                <tr>
                    <th>Process Name:</th>
                    <td><?php echo htmlspecialchars($transaction['process_name']); ?></td>
                </tr>
                <tr>
                    <th>Public Key:</th>
                    <td>
                        <a href="https://solscan.io/address/<?php echo htmlspecialchars($transaction['public_key']); ?>" target="_blank">
                            <?php echo htmlspecialchars(substr($transaction['public_key'], 0, 4) . '...' . substr($transaction['public_key'], -4)); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th>Token Address:</th>
                    <td>
                        <a href="https://solscan.io/token/<?php echo htmlspecialchars($transaction['token_mint']); ?>" target="_blank">
                            <?php echo htmlspecialchars($transaction['token_mint']); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th>SOL Amount:</th>
                    <td><?php echo htmlspecialchars($transaction['sol_amount']); ?> SOL</td>
                </tr>
                <tr>
                    <th>Slippage:</th>
                    <td><?php echo htmlspecialchars($transaction['slippage']); ?>%</td>
                </tr>
                <tr>
                    <th>Delay Between Buy/Sell:</th>
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
                    <th>Status:</th>
                    <td><?php echo htmlspecialchars($transaction['status']); ?></td>
                </tr>
                <?php if ($transaction['error']): ?>
                <tr>
                    <th>Error Details:</th>
                    <td><?php echo htmlspecialchars($transaction['error']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <br><a href="/make-market/history">View Transaction History</a>
        </div>
    </div>
</div>
  
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts - Source code -->
<script defer src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script defer src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
<script defer src="/make-market/process/process.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load process.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
