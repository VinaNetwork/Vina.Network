<?php
// ============================================================================
// File: acc/profile-page/profile.php
// Description: Account information page.
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth
require_once $root_path . 'acc/bootstrap.php';

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
$short_public_key = $public_key ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Profile.php - Session public_key: " . ($public_key ? 'Set' : 'Not set'), 'accounts.log', 'accounts', 'DEBUG');
log_message("Profile.php - Short public_key: $short_public_key", 'accounts.log', 'accounts', 'DEBUG');
if (!$public_key || $short_public_key === 'Invalid') {
    log_message("No or invalid public key in session, redirecting to login", 'accounts.log', 'accounts', 'INFO');
    header('Location: /acc/connect-p');
    exit;
}

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Fetch account information
try {
    $stmt = $pdo->prepare("SELECT id, public_key, role, is_active, created_at, previous_login, last_login FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for public_key: $short_public_key", 'accounts.log', 'accounts', 'ERROR');
        header('Location: /acc/connect-p');
        exit;
    }
    log_message("Profile accessed for public_key: $short_public_key, role: {$account['role']}, is_active: " . ($account['is_active'] ? 'true' : 'false'), 'accounts.log', 'accounts', 'INFO');
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
    header('Location: /acc/connect-p');
    exit;
}

// SEO meta
$page_title = "Vina Network - Profile";
$page_description = "View your Vina Network account information";
$page_keywords = "Vina Network, account, profile";
$page_url = BASE_URL . "acc/profile";
$page_css = ['/acc/profile-page/profile.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php require_once $root_path . 'include/header.php';?>
<body>
<?php require_once $root_path . 'include/navbar.php';?>
<div class="acc-container">
    <div class="acc-content">
        <h1><i class="fas fa-id-card"></i> Your Profile</h1>
        <div id="account-info" class="acc-info">
            <table>
                <tr><th>ID:</th><td><?php echo htmlspecialchars($account['id']); ?></td></tr>
                <tr>
                    <th>Wallet address:</th>
                    <td>
                        <?php if ($short_public_key !== 'Invalid'): ?>
                            <a href="https://solscan.io/address/<?php echo htmlspecialchars($account['public_key']); ?>" target="_blank">
                                <?php echo htmlspecialchars($short_public_key); ?>
                            </a>
                            <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($account['public_key']); ?>"></i>
                        <?php else: ?>
                            <span>Invalid address</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr><th>Role:</th><td><?php echo htmlspecialchars($account['role']); ?></td></tr>
                <tr><th>Status:</th><td><?php echo $account['is_active'] ? 'Active' : 'Locked'; ?></td></tr>
                <tr><th>Created at:</th><td><?php echo htmlspecialchars($created_at); ?></td></tr>
                <tr><th>Last login:</th><td><?php echo htmlspecialchars($last_login); ?></td></tr>
            </table>
        </div>

        <form method="POST" id="logout-form" action="/acc/logout">
            <button class="cta-button" type="submit" name="logout">Disconnect</button>
        </form>
        
        <div id="wallet-info" style="display: none;">
            <span id="status"></span>
        </div>
    </div>
</div>
<?php require_once $root_path . 'include/footer.php';?>

<!-- Scripts - Internal library -->
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<!-- Global variable -->
<script>
    // Passing JWT_SECRET into JavaScript securely
    const authToken = '<?php echo htmlspecialchars(JWT_SECRET); ?>';
</script>
<!-- Scripts - Source code -->
<script>console.log('Attempting to load JS files...');</script>
<script defer src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script defer src="/acc/profile-page/profile.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /acc/profile.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
