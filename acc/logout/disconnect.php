<?php
// ============================================================================
// File: acc/logout/disconnect.php
// Description: Handles user logout, disconnects Phantom wallet.
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
$page_css = ['/acc/logout/disconnect.css?t=' . time()];
?>

<!DOCTYPE html>
<html lang="en">
<?php require_once $root_path . 'include/header.php'; ?>
<body>
    <?php require_once $root_path . 'include/navbar.php';?>
    <div class="acc-container">
        <div class="acc-content">
            <h1>Logging out...</h1>
            <p>Please wait while we disconnect your wallet and log you out.</p>
            <div id="wallet-info" style="display: none;">
                <span id="status"></span>
            </div>
        </div>
    </div>
    <?php require_once $root_path . 'include/footer.php';?>

    <!-- Scripts - Internal library -->
    <script src="/js/libs/axios.min.js"></script>
    <script src="/js/libs/solana.web3.iife.js"></script>
    <!-- Scripts - Source code -->
    <script>
        // Pass JWT_SECRET securely to disconnect.js
        window.authToken = '<?php echo htmlspecialchars(JWT_SECRET); ?>';
    </script>
    <script src="/js/vina.js"></script>
    <script src="/acc/logout/disconnect.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
