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
require_once __DIR__ . '/../config/bootstrap.php';
$root_path = '../';

// Add Security Headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://unpkg.com 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' https://www.vina.network; connect-src 'self' https://www.vina.network; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

require_once __DIR__ . '/auth.php';

// Start session
session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

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

// Error reporting
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

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

// CSS for Accounts
$page_css = ['/accounts/acc.css'];

// Header
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    log_message("index.php: header.php not found at $header_path", 'accounts.log', 'accounts', 'ERROR');
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
        log_message("index.php: navbar.php not found at $navbar_path", 'accounts.log', 'accounts', 'ERROR');
        die('Internal Server Error: Missing navbar.php');
    }
    include $navbar_path;
    ?>
    
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
    
    <?php
    $footer_path = $root_path . 'include/footer.php';
    if (!file_exists($footer_path)) {
        log_message("index.php: footer.php not found at $footer_path", 'accounts.log', 'accounts', 'ERROR');
        die('Internal Server Error: Missing footer.php');
    }
    include $footer_path;
    ?>
    
    <script>console.log('Attempting to load JS files...');</script>
    <script src="https://unpkg.com/@solana/web3.js@latest/lib/index.iife.min.js"></script>
    <script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
    <script src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
    <script src="/accounts/acc.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /accounts/acc.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
