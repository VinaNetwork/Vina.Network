<?php
// ============================================================================
// File: manage/list-accounts/list-acc.php
// Description: List members of Vina Network page.
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../../';
require_once $root_path . 'manage/bootstrap.php';

// Check session and admin rights
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key && strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Attempting to access admin page, public_key: $short_public_key, session_role: " . ($_SESSION['role'] ?? 'Not set'), 'manage-list-acc.log', 'accounts', 'DEBUG');

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection successful for admin page", 'manage-list-acc.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'manage-list-acc.log', 'accounts', 'ERROR');
    header('Location: /acc/connect-p?error=Database connection failed');
    exit;
}

// Check role from database
if ($public_key) {
    try {
        $stmt = $pdo->prepare("SELECT role, is_active FROM accounts WHERE public_key = ?");
        $stmt->execute([$public_key]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($account && $account['is_active'] && $account['role'] === 'admin') {
            $_SESSION['role'] = 'admin'; // Update session roles
            log_message("Role verified from database for public_key: $short_public_key, role: admin", 'manage-list-acc.log', 'accounts', 'INFO');
        } else {
            log_message("Unauthorized access attempt to admin page, public_key: $short_public_key, role: " . ($account['role'] ?? 'Not found') . ", is_active: " . ($account['is_active'] ?? 'Not found'), 'manage-list-acc.log', 'accounts', 'ERROR');
            header('Location: /acc/connect-p?error=Unauthorized access');
            exit;
        }
    } catch (PDOException $e) {
        log_message("Database query failed for role check: {$e->getMessage()}, public_key: $short_public_key", 'manage-list-acc.log', 'accounts', 'ERROR');
        header('Location: /acc/connect-p?error=Database error');
        exit;
    }
} else {
    log_message("Unauthorized access attempt to admin page, public_key: $short_public_key, session_role: Not set, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'manage-list-acc.log', 'accounts', 'ERROR');
    header('Location: /acc/connect-p?error=Unauthorized access');
    exit;
}

// Account lock/unlock processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['action'])) {
    $target_public_key = $_POST['public_key'];
    $action = $_POST['action'];
    $target_short_public_key = strlen($target_public_key) >= 8 ? substr($target_public_key, 0, 4) . '...' . substr($target_public_key, -4) : 'Invalid';
    
    if ($target_public_key === $public_key) {
        log_message("Admin attempted to modify own account: public_key=$target_short_public_key", 'manage-list-acc.log', 'accounts', 'ERROR');
        $error = "Cannot modify your own account.";
    } else {
        try {
            $is_active = ($action === 'lock') ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE accounts SET is_active = ? WHERE public_key = ?");
            $stmt->execute([$is_active, $target_public_key]);
            log_message("Account $target_short_public_key " . ($action === 'lock' ? 'locked' : 'unlocked') . " by admin $short_public_key", 'manage-list-acc.log', 'accounts', 'INFO');
            $success = "Account $target_short_public_key has been " . ($action === 'lock' ? 'locked' : 'unlocked') . ".";
        } catch (PDOException $e) {
            log_message("Failed to update account $target_short_public_key: {$e->getMessage()}", 'manage-list-acc.log', 'accounts', 'ERROR');
            $error = "Failed to update account: {$e->getMessage()}";
        }
    }
}

// Get list of accounts
try {
    $stmt = $pdo->prepare("SELECT public_key, role, is_active, created_at, last_login FROM accounts ORDER BY created_at DESC");
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_message("Failed to fetch accounts: {$e->getMessage()}", 'manage-list-acc.log', 'accounts', 'ERROR');
    $error = "Failed to fetch accounts: {$e->getMessage()}";
}

// SEO meta
$page_title = "Manage - Account management page.";
$page_description = "Admin panel to manage user accounts on Vina Network";
$page_keywords = "Vina Network, admin, manage accounts";
$page_css = ['/manage/list-accounts/list-acc.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php require_once $root_path . 'include/header.php'; ?>
<body>
<?php require_once $root_path . 'include/navbar.php'; ?>
<div class="admin-container">
    <div class="admin-content">
        <h1><i class="fa-solid fa-people-roof"></i> Manage Accounts</h1>
        <?php if (isset($success)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <table class="acc-list">
            <thead>
                <tr>
                    <th>Public Key</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td data-label="Public Key">
                            <?php echo htmlspecialchars(substr($account['public_key'], 0, 4) . '...' . substr($account['public_key'], -4)); ?>
                            <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($account['public_key']); ?>"></i>
                        </td>
                        <td data-label="Role"><?php echo htmlspecialchars($account['role']); ?></td>
                        <td data-label="Status"><?php echo $account['is_active'] ? 'Active' : 'Locked'; ?></td>
                        <td data-label="Created At"><?php echo htmlspecialchars($account['created_at']); ?></td>
                        <td data-label="Last Login"><?php echo htmlspecialchars($account['last_login'] ?: 'Never'); ?></td>
                        <td data-label="Actions">
                            <form method="POST" class="action-form">
                                <input type="hidden" name="public_key" value="<?php echo htmlspecialchars($account['public_key']); ?>">
                                <input type="hidden" name="action" value="<?php echo $account['is_active'] ? 'lock' : 'unlock'; ?>">
                                <button class="cta-button" type="submit" <?php echo $account['public_key'] === $public_key ? 'disabled' : ''; ?>>
                                    <?php echo $account['is_active'] ? 'Lock' : 'Unlock'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once $root_path . 'include/footer.php'; ?>

<!-- Scripts - Internal library -->
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<!-- Global variable -->
<script>
    // Passing JWT_SECRET into JavaScript securely
    const authToken = '<?php echo htmlspecialchars(JWT_SECRET); ?>';
</script>
<!-- Scripts - Source code -->
<script defer src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script defer src="/manage/list-accounts/list-acc.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
