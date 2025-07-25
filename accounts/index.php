<?php
// File: accounts/index.php
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

ob_start();
$root_path = '../';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/utils.php'; // Thêm file utils cho CSRF

// Error reporting
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Session start
session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "rate_limit_login:$ip";
$rate_limit_count = $_SESSION[$rate_limit_key]['count'] ?? 0;
$rate_limit_time = $_SESSION[$rate_limit_key]['time'] ?? 0;

if (time() - $rate_limit_time > 60) {
    $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
} elseif ($rate_limit_count >= 5) {
    log_message("Rate limit exceeded for login attempt: IP=$ip", 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Too many login attempts. Try again in 1 minute.']);
    exit;
} else {
    $_SESSION[$rate_limit_key]['count']++;
}

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

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://unpkg.com; style-src 'self'; img-src 'self' https://www.vina.network;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Header
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    log_message("index.php: header.php not found at $header_path", 'ERROR');
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
    log_message("index.php: navbar.php not found at $navbar_path", 'ERROR');
    die('Internal Server Error: Missing navbar.php');
}
include $navbar_path;
?>

<div class="acc-container">
    <div class="acc-content">
        <h1>Login/Register with Phantom Wallet</h1>
        <button id="connect-wallet" data-csrf="<?php echo htmlspecialchars($csrf_token); ?>">Connect Phantom Wallet</button>
        <div id="wallet-info" style="display: none;">
            <p>Wallet address: <span id="public-key"></span></p>
            <p>Status: <span id="status"></span></p>
        </div>
    </div>
</div>

<?php
$footer_path = $root_path . 'include/footer.php';
if (!file_exists($footer_path)) {
    log_message("index.php: footer.php not found at $footer_path", 'ERROR');
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
