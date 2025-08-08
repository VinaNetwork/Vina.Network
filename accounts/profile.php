<?php
ob_start();
// ============================================================================
// File: accounts/profile.php
// Description: Account information page.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . '../vendor/autoload.php'; // Load composer for stephenhill/base58

// Add Security Headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' https://www.vina.network; connect-src 'self' https://www.vina.network; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

use StephenHill\Base58;

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

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Database connection
$start_time = microtime(true);
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection successful (took {$duration}ms)", 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Check session
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key && strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Profile.php - Session public_key: " . ($short_public_key ?? 'Not set'), 'accounts.log', 'accounts', 'DEBUG');
if (!$public_key) {
    log_message("No public key in session, redirecting to login", 'accounts.log', 'accounts', 'INFO');
    header('Location: /accounts');
    exit;
}

// Fetch account info
try {
    $stmt = $pdo->prepare("SELECT id, public_key, created_at, last_login FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for public_key: $short_public_key", 'accounts.log', 'accounts', 'ERROR');
        header('Location: /accounts');
        exit;
    }
    log_message("Profile accessed for public_key: $short_public_key", 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving account information']);
    exit;
}

// Handle logout
if (isset($_POST['logout']) && isset($_POST['csrf_token'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        log_message("Invalid CSRF token for logout attempt", 'accounts.log', 'accounts', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    log_message("User logged out: public_key=$short_public_key", 'accounts.log', 'accounts', 'INFO');
    session_destroy();
    header('Location: /accounts');
    exit;
}

// Validate public_key format
$base58 = new Base58();
$short_public_key = 'Invalid address';
try {
    $base58->decode($account['public_key']);
    $short_public_key = substr($account['public_key'], 0, 4) . '...' . substr($account['public_key'], -4);
} catch (Exception $e) {
    log_message("Invalid public_key format: {$e->getMessage()}", 'accounts.log', 'accounts', 'ERROR');
}

// SEO meta
$root_path = '../';
$page_title = "Vina Network - Profile";
$page_description = "View your Vina Network account information";
$page_keywords = "Vina Network, account, profile";
$page_og_title = "Vina Network - Profile";
$page_og_description = "View your Vina Network account information";
$page_og_url = BASE_URL . "accounts/profile.php";
$page_canonical = BASE_URL . "accounts/profile.php";
$page_css = ['/accounts/acc.css'];

// Header
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    log_message("profile.php: header.php not found at $header_path", 'accounts.log', 'accounts', 'ERROR');
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
    log_message("profile.php: navbar.php not found at $navbar_path", 'accounts.log', 'accounts', 'ERROR');
    die('Internal Server Error: Missing navbar.php');
}
include $navbar_path;
?>

<div class="acc-container">
    <div class="acc-content">
        <h1>Your Profile</h1>
        <div id="account-info">
            <table>
                <tr><th>ID:</th><td><?php echo htmlspecialchars($account['id']); ?></td></tr>
                <tr>
		    <th>Wallet address:</th>
		    <td>
			<?php if ($short_public_key !== 'Invalid address'): ?>
			<a href="https://solscan.io/address/<?php echo htmlspecialchars($account['public_key']); ?>" target="_blank">
				<?php echo htmlspecialchars($short_public_key); ?>
			</a>
			<i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($account['public_key']); ?>"></i>
			<?php else: ?>
			<span>Invalid address</span>
			<?php endif; ?>
		    </td>
                </tr>
                <tr><th>Created at:</th><td><?php echo htmlspecialchars($account['created_at']); ?></td></tr>
                <tr><th>Last Login:</th><td><?php echo htmlspecialchars($account['last_login'] ?: 'Never'); ?></td></tr>
            </table>
        </div>
		
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <button class="cta-button" type="submit" name="logout">Logout</button>
        </form>
    </div>
</div>

<?php
$footer_path = $root_path . 'include/footer.php';
if (!file_exists($footer_path)) {
    log_message("profile.php: footer.php not found at $footer_path", 'accounts.log', 'accounts', 'ERROR');
    die('Internal Server Error: Missing footer.php');
}
include $footer_path;
?>

<script>console.log('Attempting to load JS files...');</script>
<script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/navbar.js')"></script>
<script src="/accounts/acc.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /accounts/acc.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
