<?php
// ============================================================================
// File: acc/logout.php
// Description: Handles user logout, disconnects Phantom wallet, and redirects to /acc/connect-p
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../../';
require_once $root_path . 'acc/bootstrap.php';

// Set response headers
header('Content-Type: text/html; charset=UTF-8');

// Log logout attempt
$public_key = $_SESSION['public_key'] ?? 'unknown';
$short_public_key = strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
log_message("Logout attempt for public_key: $short_public_key, IP=$ip_address", 'accounts.log', 'accounts', 'INFO');

// Check X-Auth-Token for AJAX requests
$headers = getallheaders();
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;
$isAjax = isset($headers['X-Requested-With']) && $headers['X-Requested-With'] === 'XMLHttpRequest';

if ($isAjax && $authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token for AJAX logout, IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token']);
    ob_end_flush();
    exit;
}

// Clear session
$_SESSION = [];
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/', '', true, false);
}
session_destroy();
log_message("Session destroyed for public_key: $short_public_key, IP=$ip_address", 'accounts.log', 'accounts', 'INFO');

// Prepare SEO meta
$page_title = "Logging out - Vina Network";
$page_description = "Logging out from Vina Network";
$page_keywords = "Vina Network, logout, disconnect";
$page_url = BASE_URL . "acc/disconnect";
$page_css = ['/acc/logout/disconnect.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php require_once $root_path . 'include/header.php'; ?>
<body>
    <div class="acc-container">
        <div class="acc-content">
            <h1>Logging out...</h1>
            <p>Please wait while we disconnect your wallet and log you out.</p>
            <div id="wallet-info" style="display: none;">
                <span id="status"></span>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
    <script defer src="/js/libs/solana.web3.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/solana.web3.iife.js')"></script>
    <script>
        // Pass JWT_SECRET securely
        const authToken = '<?php echo htmlspecialchars(JWT_SECRET); ?>';

        // Log message function
        async function log_message(message, log_file = 'accounts.log', module = 'accounts', log_type = 'INFO') {
            if (!authToken) {
                console.error('Log failed: authToken is missing');
                return;
            }

            const sanitizedMessage = message.replace(/privateKey=[^\s]+/g, 'privateKey=[HIDDEN]');
            try {
                const response = await axios.post('/acc/write-logs', {
                    message: sanitizedMessage,
                    log_file,
                    module,
                    log_type,
                    url: window.location.href,
                    userAgent: navigator.userAgent,
                    timestamp: new Date().toISOString()
                }, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Auth-Token': authToken
                    },
                    withCredentials: true
                });

                if (response.status === 200 && response.data.status === 'success') {
                    console.log(`Log sent successfully: ${sanitizedMessage}`);
                } else {
                    console.error(`Log failed: HTTP ${response.status}, message=${response.data.message || response.statusText}`);
                }
            } catch (err) {
                console.error('Log error:', {
                    message: err.message,
                    status: err.response?.status,
                    data: err.response?.data
                });
            }
        }

        // Function to show error messages
        function showError(message) {
            console.log('Showing error:', message);
            log_message(`Showing error: ${message}`, 'accounts.log', 'accounts', 'ERROR');
            let walletInfo = document.getElementById('wallet-info');
            let statusSpan = document.getElementById('status');
            
            if (!walletInfo) {
                walletInfo = document.createElement('div');
                walletInfo.id = 'wallet-info';
                document.querySelector('.acc-content').appendChild(walletInfo);
            }
            if (!statusSpan) {
                statusSpan = document.createElement('span');
                statusSpan.id = 'status';
                walletInfo.appendChild(statusSpan);
            }
            
            statusSpan.textContent = message;
            statusSpan.style.color = 'red';
            walletInfo.style.display = 'block';
        }

        // Function to show success messages
        function showSuccess(message) {
            console.log('Showing success:', message);
            log_message(`Showing success: ${message}`, 'accounts.log', 'accounts', 'INFO');
            let walletInfo = document.getElementById('wallet-info');
            let statusSpan = document.getElementById('status');
            
            if (!walletInfo) {
                walletInfo = document.createElement('div');
                walletInfo.id = 'wallet-info';
                document.querySelector('.acc-content').appendChild(walletInfo);
            }
            if (!statusSpan) {
                statusSpan = document.createElement('span');
                statusSpan.id = 'status';
                walletInfo.appendChild(statusSpan);
            }
            
            statusSpan.textContent = message;
            statusSpan.style.color = 'green';
            walletInfo.style.display = 'block';
        }

        // Disconnect wallet and redirect
        document.addEventListener('DOMContentLoaded', async () => {
            let walletInfo = document.getElementById('wallet-info');
            let statusSpan = document.getElementById('status');
            if (!walletInfo || !statusSpan) {
                console.error('DOM elements missing, creating dynamically');
                await log_message('DOM elements missing, creating dynamically', 'accounts.log', 'accounts', 'ERROR');
                walletInfo = document.createElement('div');
                walletInfo.id = 'wallet-info';
                statusSpan = document.createElement('span');
                statusSpan.id = 'status';
                walletInfo.appendChild(statusSpan);
                document.querySelector('.acc-content').appendChild(walletInfo);
            }

            await log_message('Logout page loaded, attempting to disconnect wallet', 'accounts.log', 'accounts', 'DEBUG');

            try {
                if (window.solana && window.solana.isPhantom) {
                    await log_message('Phantom wallet detected, attempting to disconnect', 'accounts.log', 'accounts', 'INFO');
                    try {
                        await window.solana.disconnect();
                        await log_message('Phantom wallet disconnected successfully', 'accounts.log', 'accounts', 'INFO');
                        showSuccess('Logout successful, redirecting...');
                    } catch (disconnectError) {
                        await log_message(`Failed to disconnect Phantom wallet: ${disconnectError.message}`, 'accounts.log', 'accounts', 'ERROR');
                        showError(`Failed to disconnect wallet: ${disconnectError.message}`);
                        setTimeout(() => {
                            window.location.href = '/acc/connect-p';
                        }, 2000);
                        return;
                    }
                } else {
                    await log_message('No Phantom wallet detected, skipping disconnect', 'accounts.log', 'accounts', 'INFO');
                    showSuccess('Logout successful, redirecting...');
                }

                // Redirect to /acc/connect-p
                await log_message('Redirecting to /acc/connect-p after logout', 'accounts.log', 'accounts', 'INFO');
                setTimeout(() => {
                    window.location.href = '/acc/connect-p';
                }, 3000);
            } catch (error) {
                await log_message(`Error during logout process: ${error.message}`, 'accounts.log', 'accounts', 'ERROR');
                showError(`Error during logout: ${error.message}`);
                setTimeout(() => {
                    window.location.href = '/acc/connect-p';
                }, 2000);
            }
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
