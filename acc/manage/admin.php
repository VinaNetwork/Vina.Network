<?php
// ============================================================================
// File: acc/manage/admin.php
// Description: Admin page to manage user accounts (display, lock/unlock).
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'acc/bootstrap.php';

// Kiểm tra session và quyền admin
$public_key = $_SESSION['public_key'] ?? null;
$role = $_SESSION['role'] ?? null;
$short_public_key = $public_key && strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Attempting to access admin page, public_key: $short_public_key, role: " . ($role ?? 'Not set'), 'accounts.log', 'accounts', 'DEBUG');

if (!$public_key || !$role || $role !== 'admin') {
    log_message("Unauthorized access attempt to admin page, public_key: $short_public_key, role: " . ($role ?? 'Not set') . ", IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'ERROR');
    header('Location: /acc/connect?error=Unauthorized access');
    exit;
}

// Kết nối cơ sở dữ liệu
try {
    $pdo = get_db_connection();
    log_message("Database connection successful for admin page", 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'accounts.log', 'accounts', 'ERROR');
    header('Location: /acc/connect?error=Database connection failed');
    exit;
}

// Xử lý khóa/mở tài khoản
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['action'])) {
    $target_public_key = $_POST['public_key'];
    $action = $_POST['action'];
    $target_short_public_key = strlen($target_public_key) >= 8 ? substr($target_public_key, 0, 4) . '...' . substr($target_public_key, -4) : 'Invalid';
    
    if ($target_public_key === $public_key) {
        log_message("Admin attempted to modify own account: public_key=$target_short_public_key", 'accounts.log', 'accounts', 'ERROR');
        $error = "Cannot modify your own account.";
    } else {
        try {
            $is_active = ($action === 'lock') ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE accounts SET is_active = ? WHERE public_key = ?");
            $stmt->execute([$is_active, $target_public_key]);
            log_message("Account $target_short_public_key " . ($action === 'lock' ? 'locked' : 'unlocked') . " by admin $short_public_key", 'accounts.log', 'accounts', 'INFO');
            $success = "Account $target_short_public_key has been " . ($action === 'lock' ? 'locked' : 'unlocked') . ".";
        } catch (PDOException $e) {
            log_message("Failed to update account $target_short_public_key: {$e->getMessage()}", 'accounts.log', 'accounts', 'ERROR');
            $error = "Failed to update account: {$e->getMessage()}";
        }
    }
}

// Lấy danh sách tài khoản
try {
    $stmt = $pdo->prepare("SELECT public_key, role, is_active, created_at, last_login FROM accounts ORDER BY created_at DESC");
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_message("Failed to fetch accounts: {$e->getMessage()}", 'accounts.log', 'accounts', 'ERROR');
    $error = "Failed to fetch accounts: {$e->getMessage()}";
}

// SEO meta
$page_title = "Admin - Manage Accounts";
$page_description = "Admin panel to manage user accounts on Vina Network";
$page_keywords = "Vina Network, admin, manage accounts";
$page_url = BASE_URL . "acc/manage/admin";
$page_css = ['/acc/acc.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php require_once $root_path . 'include/header.php'; ?>
<body>
<?php require_once $root_path . 'include/navbar.php'; ?>
<div class="acc-container">
    <div class="acc-content">
        <h1>Admin - Manage Accounts</h1>
        <?php if (isset($success)): ?>
            <p style="color: green;"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <table border="1" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
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
                        <td><?php echo htmlspecialchars(substr($account['public_key'], 0, 4) . '...' . substr($account['public_key'], -4)); ?></td>
                        <td><?php echo htmlspecialchars($account['role']); ?></td>
                        <td><?php echo $account['is_active'] ? 'Active' : 'Locked'; ?></td>
                        <td><?php echo htmlspecialchars($account['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($account['last_login'] ?: 'Never'); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="public_key" value="<?php echo htmlspecialchars($account['public_key']); ?>">
                                <input type="hidden" name="action" value="<?php echo $account['is_active'] ? 'lock' : 'unlock'; ?>">
                                <button type="submit" <?php echo $account['public_key'] === $public_key ? 'disabled' : ''; ?>>
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
<script src="/acc/acc.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /acc/acc.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
