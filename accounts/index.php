<?php
// ============================================================================
// File: accounts/index.php
// Description: Accounts page for Vina Network.
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/bootstrap.php';
require_once __DIR__ . '/auth.php';

// Add Security Headers
require_once $root_path . 'accounts/auth-headers.php';

// Session start: in config/bootstrap.php
// Error reporting: in config/bootstrap.php

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

// SEO meta
$page_title = "Connect Wallet to Vina Network";
$page_description = "Connect your Solana wallet to register or login to Vina Network";
$page_keywords = "Vina Network, connect wallet, login, register";
$page_og_title = "Connect Wallet to Vina Network";
$page_og_description = "Connect your Solana wallet to register or login to Vina Network";
$page_og_url = BASE_URL . "accounts/";
$page_canonical = BASE_URL . "accounts/";
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
        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    </div>
</div>
<?php require_once $root_path . 'include/footer.php';?>

<!-- Scripts - Internal library -->
<script>console.log('Attempting to load JS files...');</script>
<script src="/js/libs/solana.web3.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/solana.web3.iife.js')"></script>
<!-- Scripts - Source code -->
<script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
<script src="/accounts/js/ui.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /accounts/js/ui.js')"></script>
<script src="/accounts/js/acc.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /accounts/js/acc.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
