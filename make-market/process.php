<?php
// ============================================================================
// File: make-market/process.php
// Description: Process page for displaying transaction progress and validation results
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
$page_css = ['mm.css'];

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
        <p id="check-balance">Checking wallet balance: <span>Loading...</span></p>
        <p id="check-token">Checking token mint: <span>Loading...</span></p>
        <p id="check-liquidity">Checking liquidity: <span>Loading...</span></p>
        <p id="check-private-key">Checking private key: <span>Loading...</span></p>
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
            <button id="cancel-btn" class="cancel-btn" style="display: none;" onclick="showCancelConfirmation(<?php echo $transaction_id; ?>)">Cancel</button>
            <button onclick="window.location.href='/make-market/'">Back</button>
        </div>
    </div>

    <!-- Confirmation popup -->
    <div class="confirmation-popup" id="cancel-confirmation" style="display: none;">
        <div class="confirmation-popup-content">
            <p>Are you sure you want to cancel process <?php echo $transaction_id; ?>?</p>
            <button class="confirm-btn" onclick="confirmCancel(<?php echo $transaction_id; ?>)">Confirm</button>
            <button class="cancel-popup-btn" onclick="closeCancelConfirmation()">Cancel</button>
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

<!-- Scripts -->
<script defer src="/js/libs/solana.web3.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/solana.web3.iife.js')"></script>
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<script defer src="/js/libs/bs58.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/bs58.js')"></script>
<script defer src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script defer src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
<script defer src="/make-market/mm-api.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load mm-api.js')"></script>
<script>
  
    // ============================================================================
    // Description: JavaScript for handling transaction progress and pre-transaction checks
    // ============================================================================

    const transactionId = <?php echo $transaction_id; ?>;
    const loopCount = <?php echo $transaction['loop_count']; ?>;
    let currentLoop = 0;

    // Log message function
    function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
        fetch('/make-market/log.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message, log_file, module, log_type })
        }).catch(err => console.error('Log error:', err));
    }

    // Perform pre-transaction checks
    async function performChecks() {
        const checkBalance = document.getElementById('check-balance').querySelector('span');
        const checkToken = document.getElementById('check-token').querySelector('span');
        const checkLiquidity = document.getElementById('check-liquidity').querySelector('span');
        const checkPrivateKey = document.getElementById('check-private-key').querySelector('span');
        const checkError = document.getElementById('check-error');
        let allChecksPassed = true;

        try {
            // Check wallet balance
            const balanceResponse = await fetch(`/make-market/get-balance.php?public_key=<?php echo urlencode($transaction['public_key']); ?>`);
            const balanceData = await balanceResponse.json();
            if (balanceData.status === 'success' && balanceData.balance >= <?php echo $transaction['sol_amount']; ?>) {
                checkBalance.textContent = 'Done';
                checkBalance.classList.add('done');
            } else {
                checkBalance.textContent = 'Insufficient';
                checkBalance.classList.add('error');
                checkError.innerHTML = '<p style="color: red;">Process stopped: Insufficient wallet balance</p>';
                checkError.style.display = 'block';
                allChecksPassed = false;
            }

            // Check token mint
            const tokenResponse = await fetch(`https://api.mainnet-beta.solana.com`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    jsonrpc: '2.0',
                    id: 1,
                    method: 'getAccountInfo',
                    params: ['<?php echo $transaction['token_mint']; ?>']
                })
            });
            const tokenData = await tokenResponse.json();
            if (tokenData.result && tokenData.result.value) {
                checkToken.textContent = 'Done';
                checkToken.classList.add('done');
            } else {
                checkToken.textContent = 'Does not exist';
                checkToken.classList.add('error');
                checkError.innerHTML = '<p style="color: red;">Process stopped: Invalid token mint</p>';
                checkError.style.display = 'block';
                allChecksPassed = false;
            }

            // Check liquidity
            const liquidityResponse = await fetch(`https://quote-api.jup.ag/v4/quote?inputMint=So11111111111111111111111111111111111111112&outputMint=<?php echo urlencode($transaction['token_mint']); ?>&amount=<?php echo $transaction['sol_amount'] * 1e9; ?>&slippage=<?php echo $transaction['slippage']; ?>`);
            const liquidityData = await liquidityResponse.json();
            if (liquidityData.data && liquidityData.data.length > 0) {
                checkLiquidity.textContent = 'Done';
                checkLiquidity.classList.add('done');
            } else {
                checkLiquidity.textContent = 'Insufficient';
                checkLiquidity.classList.add('error');
                checkError.innerHTML = '<p style="color: red;">Process stopped: Insufficient liquidity</p>';
                checkError.style.display = 'block';
                allChecksPassed = false;
            }

            // Check private key
            const privateKeyResponse = await fetch('/make-market/check-private-key.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transaction_id: transactionId })
            });
            const privateKeyData = await privateKeyResponse.json();
            if (privateKeyData.status === 'success') {
                checkPrivateKey.textContent = 'Done';
                checkPrivateKey.classList.add('done');
            } else {
                checkPrivateKey.textContent = 'Invalid';
                checkPrivateKey.classList.add('error');
                checkError.innerHTML = '<p style="color: red;">Process stopped: Invalid private key</p>';
                checkError.style.display = 'block';
                allChecksPassed = false;
            }

            // If all checks pass, start transaction
            if (allChecksPassed) {
                document.getElementById('progress-section').style.display = 'block';
                startTransaction();
            }
        } catch (error) {
            log_message(`Error performing checks: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
            checkError.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
            checkError.style.display = 'block';
        }
    }

    // Start transaction
    async function startTransaction() {
        const resultDiv = document.getElementById('check-error');
        try {
            log_message(`Starting transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
            const response = await fetch('/make-market/mm-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transaction_id: transactionId })
            });
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to start transaction');
            }
            log_message(`Transaction ID ${transactionId} started`, 'make-market.log', 'make-market', 'INFO');
            pollTransactionStatus();
        } catch (error) {
            log_message(`Error starting transaction ID ${transactionId}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
            resultDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
            resultDiv.style.display = 'block';
        }
    }

    // Poll transaction status
    function pollTransactionStatus() {
        const transactionLog = document.getElementById('transaction-log');
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        const currentLoopSpan = document.getElementById('current-loop');
        const statusSpan = document.getElementById('transaction-status');
        const cancelBtn = document.getElementById('cancel-btn');

        const interval = setInterval(async () => {
            try {
                const response = await fetch(`/make-market/status.php?id=${transactionId}`);
                const data = await response.json();
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Failed to fetch status');
                }

                // Update status
                statusSpan.textContent = data.transaction.status;
                currentLoop = data.transaction.current_loop || 0;
                currentLoopSpan.textContent = currentLoop;

                // Update progress bar
                const progressPercent = (currentLoop / loopCount) * 100;
                progressBar.style.width = `${progressPercent}%`;
                progressText.textContent = `${Math.round(progressPercent)}%`;

                // Update transaction log
                if (data.transaction.logs && data.transaction.logs.length > 0) {
                    transactionLog.innerHTML = data.transaction.logs.map(log => 
                        `<p>${log.timestamp} ${log.message} ${log.tx_id ? `<a href="https://solscan.io/tx/${log.tx_id}" target="_blank">${log.tx_id.substring(0, 4)}...</a>` : ''}</p>`
                    ).join('');
                }

                // Show/hide cancel button
                cancelBtn.style.display = ['pending', 'processing'].includes(data.transaction.status.toLowerCase()) ? 'inline-block' : 'none';

                // Stop polling if transaction is complete or canceled
                if (['success', 'failed', 'canceled'].includes(data.transaction.status.toLowerCase())) {
                    clearInterval(interval);
                    const resultDiv = document.getElementById('check-error');
                    resultDiv.innerHTML = `<p style="color: ${data.transaction.status.toLowerCase() === 'success' ? 'green' : 'red'};">Process ${data.transaction.status.toLowerCase() === 'success' ? 'completed successfully!' : data.transaction.status.toLowerCase() === 'failed' ? 'failed: ' + (data.transaction.error || 'Unknown error') : 'canceled.'}</p>`;
                    resultDiv.style.display = 'block';
                }
            } catch (error) {
                log_message(`Error polling status for transaction ID ${transactionId}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
                transactionLog.innerHTML += `<p style="color: red;">Error polling status: ${error.message}</p>`;
            }
        }, 5000); // Poll every 5 seconds
    }

    // Show cancel confirmation popup
    function showCancelConfirmation(transactionId) {
        const popup = document.getElementById('cancel-confirmation');
        popup.style.display = 'flex';
        log_message(`Displayed cancel confirmation popup for transaction ID: ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
    }

    // Close cancel confirmation popup
    function closeCancelConfirmation() {
        const popup = document.getElementById('cancel-confirmation');
        popup.style.display = 'none';
        log_message('Cancel confirmation popup closed', 'make-market.log', 'make-market', 'DEBUG');
    }

    // Confirm cancel action
    async function confirmCancel(transactionId) {
        const resultDiv = document.getElementById('check-error');
        try {
            log_message(`Sending cancel request for transaction ID: ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
            const response = await fetch('/make-market/cancel-transaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id: transactionId })
            });
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            const data = await response.json();
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to cancel transaction');
            }
            closeCancelConfirmation();
            resultDiv.innerHTML = `<p style="color: green;">Transaction ${transactionId} canceled successfully!</p>`;
            resultDiv.style.display = 'block';
            log_message(`Transaction ${transactionId} canceled successfully`, 'make-market.log', 'make-market', 'INFO');
        } catch (error) {
            log_message(`Error canceling transaction ${transactionId}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
            resultDiv.innerHTML = `<p style="color: red;">Error canceling transaction: ${error.message}</p>`;
            resultDiv.style.display = 'block';
        }
    }

    // Initialize checks on page load
    document.addEventListener('DOMContentLoaded', () => {
        log_message(`process.php loaded for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
        performChecks();
    });
</script>
</body>
</html>
<?php ob_end_flush(); ?>
