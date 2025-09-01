<?php
// ============================================================================
// File: acc/profile.php
// Description: Account information page.
// Created by: Vina Network
// ============================================================================

ob_start();
// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
// constants | logging | config | error | session | database | header-auth | wallet-auth
require_once $root_path . 'acc/bootstrap.php';
use StephenHill\Base58;

// Database connection
$start_time = microtime(true);
try {
    $pdo = get_db_connection();
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection successful (took {$duration}ms)", 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Check session and public key
$public_key = $_SESSION['public_key'] ?? null;
log_message("Profile.php - Session public_key: " . ($public_key ? 'Set' : 'Not set'), 'accounts.log', 'accounts', 'DEBUG');
if (!$public_key) {
    log_message("No public key in session, redirecting to login", 'accounts.log', 'accounts', 'INFO');
    header('Location: /acc');
    exit;
}

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Validate public key
$base58 = new Base58();
$short_public_key = 'Invalid address';
try {
    if (strlen($public_key) >= 8) {
        $base58->decode($public_key);
        $short_public_key = substr($public_key, 0, 4) . '...' . substr($public_key, -4);
    }
} catch (Exception $e) {
    log_message("Invalid public_key format: {$e->getMessage()}", 'accounts.log', 'accounts', 'ERROR');
    header('Location: /acc');
    exit;
}

if ($short_public_key === 'Invalid address') {
    log_message("Invalid public_key detected, redirecting to login", 'accounts.log', 'accounts', 'WARNING');
    header('Location: /acc');
    exit;
}

log_message("Profile.php - Short public_key: $short_public_key", 'accounts.log', 'accounts', 'DEBUG');

// Fetch account information
try {
    $stmt = $pdo->prepare("SELECT id, public_key, created_at, previous_login, last_login FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for public_key: $short_public_key", 'accounts.log', 'accounts', 'ERROR');
        header('Location: /acc');
        exit;
    }
    log_message("Profile accessed for public_key: $short_public_key", 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving account information']);
    exit;
}

// Format dates
$created_at = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $account['created_at']) ? $account['created_at'] : 'Invalid date';
$last_login = $account['previous_login'] ? (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $account['previous_login']) ? $account['previous_login'] : 'Invalid date') : 'No previous login';

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    log_message("Logout attempt for public_key: $short_public_key", 'accounts.log', 'accounts', 'INFO');
    
    log_message("User logged out: public_key=$short_public_key", 'accounts.log', 'accounts', 'INFO');
    session_destroy();
    header('Location: /acc');
    exit;
}

// SEO meta
$page_title = "Vina Network - Profile";
$page_description = "View your Vina Network account information";
$page_url = BASE_URL . "acc/profile";
$page_keywords = "Vina Network, account, profile";
$page_css = ['/acc/acc.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php require_once $root_path . 'include/header.php';?>
<body>
<?php require_once $root_path . 'include/navbar.php';?>
<div class="acc-container">
    <div class="acc-content">
        <h1>Your Profile</h1>
        <div id="account-info" class="acc-info">
            <table>
                <tr><th>ID:</th><td><?php echo htmlspecialchars($account['id']); ?></td></tr>
                <tr>
                    <th>Wallet Address:</th>
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
                <tr><th>Created At:</th><td><?php echo htmlspecialchars($created_at); ?></td></tr>
                <tr><th>Previous Login:</th><td><?php echo htmlspecialchars($last_login); ?></td></tr>
            </table>
        </div>
        
        <form method="POST" id="logout-form" action="/acc/profile">
            <button class="cta-button" type="submit" name="logout">Logout</button>
        </form>
        <div id="wallet-info" style="display: none;">
            <span id="status"></span>
        </div>
    </div>
</div>
<?php require_once $root_path . 'include/footer.php';?>

<script>console.log('Attempting to load JS files...');</script>
<script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script src="/acc/acc.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /acc/acc.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
