<?php
// ============================================================================
// File: accounts/index.php
// Description: Accounts page for Vina Network.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

ob_start();
$root_path = '../';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/bootstrap.php';

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
$page_og_image = "https://www.vina.network/assets/images/og-connect.jpg";
$page_og_url = "https://www.vina.network/accounts/";
$page_canonical = "https://www.vina.network/accounts/";
$page_css = ['/accounts/acc.css'];

// Header
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    log_message("index.php: header.php not found at $header_path", 'acc_auth.txt', 'accounts', 'ERROR');
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
    log_message("index.php: navbar.php not found at $navbar_path", 'acc_auth.txt', 'accounts', 'ERROR');
    die('Internal Server Error: Missing navbar.php');
}
include $navbar_path;
?>

<div class="acc-container">
    <div class="acc-content">
        <h1>Login/Register with Phantom Wallet</h1>
        <button class="cta-button" id="connect-wallet">Connect Phantom Wallet</button>
        <div id="wallet-info" style="display: none;">
            <p>Wallet address: <span id="public-key"></span></p>
            <p>Status: <span id="status"></span></p>
        </div>
        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    </div>
</div>

<?php
$footer_path = $root_path . 'include/footer.php';
if (!file_exists($footer_path)) {
    log_message("index.php: footer.php not found at $footer_path", 'acc_auth.txt', 'accounts', 'ERROR');
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
