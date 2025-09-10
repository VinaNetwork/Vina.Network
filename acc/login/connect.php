<?php
// ============================================================================
// File: acc/login/connect.php
// Description: Connect Wallet to Vina Network.
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth
require_once $root_path . 'acc/bootstrap.php';

// Check if user is already logged in
if (isset($_SESSION['public_key']) && !empty($_SESSION['public_key'])) {
    log_message("User already logged in with public_key: " . substr($_SESSION['public_key'], 0, 4) . '...', 'accounts.log', 'accounts', 'INFO');
    $redirect_url = ($_SESSION['role'] === 'admin') ? '/manage/list-accounts' : (isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '/acc/profile');
    unset($_SESSION['redirect_url']);
    if ($_SERVER['REQUEST_URI'] !== $redirect_url) {
        header("Location: $redirect_url");
        exit;
    }
}

// Store referrer URL if coming from another page
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    $referrer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    // Validate referrer to prevent open redirect vulnerabilities
    if (strpos($referrer, '/mm/create') === 0 || strpos($referrer, '/other-protected-page') === 0) {
        $_SESSION['redirect_url'] = $referrer;
        log_message("Stored referrer URL: $referrer", 'accounts.log', 'accounts', 'INFO');
    }
}

// Generate nonce for anti-replay
$nonce = bin2hex(random_bytes(16));
$_SESSION['login_nonce'] = $nonce;

// SEO meta
$page_title = "Connect Wallet to Vina Network";
$page_description = "Connect your Solana wallet to register or login to Vina Network";
$page_keywords = "Vina Network, connect wallet, login, register";
$page_url = BASE_URL . "acc/connect";
$page_css = ['/acc/login/connect.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php require_once $root_path . 'include/header.php';?>
<body>
<?php require_once $root_path . 'include/navbar.php';?>
<div class="acc-container">
    <div class="acc-content">
        <h1>Connect to Vina Network with Phantom wallet</h1>
        <button class="cta-button" id="connect-wallet">Connect Wallet</button>
        <div id="wallet-info" style="display: none;">
            <p><strong>Wallet Address:</strong> <span id="public-key"></span></p>
            <p><strong>Status:</strong> <span id="status"></span></p>
        </div>
        <input type="hidden" id="login-nonce" value="<?php echo htmlspecialchars($nonce); ?>">
    </div>
</div>
<?php require_once $root_path . 'include/footer.php';?>

<script>console.log('Attempting to load JS files...');</script>
<!-- Scripts - Internal library -->
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<script src="/js/libs/solana.web3.iife.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/solana.web3.iife.js')"></script>
<!-- Scripts - Source code -->
<script>
    // Passing JWT_SECRET into JavaScript securely
    const authToken = '<?php echo htmlspecialchars(JWT_SECRET); ?>';
</script>
<script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script src="/acc/login/connect.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /acc/acc.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
