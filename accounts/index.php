<?php
// ============================================================================
// File: accounts/index.php
// Description: Accounts page for Vina Network.
// Created by: Vina Network
// ============================================================================

ob_start();
define('VINANETWORK_ENTRY', true);

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'accounts/header-auth.php'; // Security Headers

// Check $domain and $is_secure
global $domain, $is_secure;
if (!isset($domain) || !isset($is_secure)) {
    log_message("Server configuration error: \$domain or \$is_secure not defined", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error']);
    exit;
}

// Protect POST requests with CSRF
csrf_protect();

// Set CSRF cookie for AJAX requests
if (!set_csrf_cookie()) {
    log_message("Failed to set CSRF cookie", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Failed to set CSRF cookie']);
    exit;
}

// Check if user is already logged in
if (isset($_SESSION['public_key']) && !empty($_SESSION['public_key'])) {
    log_message("User already logged in with public_key: " . substr($_SESSION['public_key'], 0, 4) . '...', 'accounts.log', 'accounts', 'INFO');
    // Redirect to referrer if set, otherwise to profile
    $redirect_url = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '/accounts/profile.php';
    unset($_SESSION['redirect_url']); // Clear after use
    header("Location: $redirect_url");
    exit;
}

// Store referrer URL if coming from another page
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    $referrer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    // Validate referrer to prevent open redirect vulnerabilities
    if (strpos($referrer, '/make-market') === 0 || strpos($referrer, '/other-protected-page') === 0) {
        $_SESSION['redirect_url'] = $referrer;
        log_message("Stored referrer URL: $referrer", 'accounts.log', 'accounts', 'INFO');
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
if ($csrf_token === false) {
    log_message("Failed to generate CSRF token", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate CSRF token']);
    exit;
}

// Generate nonce for anti-replay
$nonce = bin2hex(random_bytes(16));
$_SESSION['login_nonce'] = $nonce;

// SEO meta
$page_title = "Connect Wallet to Vina Network";
$page_description = "Connect your Solana wallet to register or login to Vina Network";
$page_url = BASE_URL . "accounts/";
$page_keywords = "Vina Network, connect wallet, login, register";
$page_og_title = $page_title;
$page_og_description = $page_description;
$page_og_url = $page_url;
$page_canonical = $page_url;
$page_css = ['/accounts/acc.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php require_once $root_path . 'include/header.php';?>
<body>
<?php require_once $root_path . 'include/navbar.php';?>

<div class="acc-container">
    <div class="acc-content">
        <h1>Login with Phantom Wallet</h1>
        <button class="cta-button" id="connect-wallet">Connect Wallet</button>
        <div id="wallet-info" style="display: none;">
            <p><span id="public-key"></span></p>
            <p><span id="status"></span></p>
        </div>
        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrf_token ?: ''); ?>">
        <input type="hidden" id="login-nonce" value="<?php echo htmlspecialchars($nonce); ?>">
    </div>
</div>

<?php require_once $root_path . 'include/footer.php';?>

<!-- Scripts - Internal library -->
<script>console.log('Attempting to load JS files...');</script>
<script>
    // Expose CSRF_TOKEN_TTL for acc.js to refresh token
    window.CSRF_TOKEN_TTL = <?php echo CSRF_TOKEN_TTL; ?>;
</script>
<script src="/js/libs/solana.web3.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/solana.web3.iife.js')"></script>
<!-- Scripts - Source code -->
<script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script src="/accounts/acc.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /accounts/acc.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
