<?php
// ============================================================================
// File: accounts/profile.php
// Description: Profile page for Vina Network. Displays account information (id, public_key, created_at, last_login).
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

session_start();
require_once __DIR__ . '/../config/config.php';

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
    log_message("Database connection successful (took {$duration}ms)", 'INFO');
} catch (PDOException $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Hàm ghi log
function log_message($message, $level = 'INFO') {
    $log_file = __DIR__ . '/../logs/accounts.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $log_message = "[$timestamp] [$level] [IP:$ip] [UA:$userAgent] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Kiểm tra session
$public_key = $_SESSION['public_key'] ?? null;
if (!$public_key) {
    log_message("No public key in session, redirecting to login", 'INFO');
    header('Location: index.php');
    exit;
}

// Lấy thông tin tài khoản từ database
try {
    $stmt = $pdo->prepare("SELECT id, public_key, created_at, last_login FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        log_message("No account found for public_key: $public_key", 'ERROR');
        header('Location: index.php');
        exit;
    }
    log_message("Profile accessed for public_key: $public_key", 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving account information']);
    exit;
}

// Xử lý logout
if (isset($_POST['logout'])) {
    log_message("User logged out: public_key=$public_key", 'INFO');
    session_destroy();
    header('Location: index.php');
    exit;
}

// Render HTML
$page_title = "Vina Network - Profile";
$page_description = "View your Vina Network account information";
$page_keywords = "Vina Network, account, profile";
$page_og_title = "Vina Network - Profile";
$page_og_description = "View your Vina Network account information";
$page_og_url = "https://www.vina.network/accounts/profile.php";
$page_canonical = "https://www.vina.network/accounts/profile.php";
$page_css = ['acc.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include '../include/header.php'; ?>
<body>
    <?php include '../include/navbar.php'; ?>

    <div class="acc-container">
        <div class="acc-content">
            <h1>Your Profile</h1>
            <div id="account-info">
                <table>
                    <tr>
                        <th>ID</th>
                        <td><?php echo htmlspecialchars($account['id']); ?></td>
                    </tr>
                    <tr>
                        <th>Public Key</th>
                        <td><?php echo htmlspecialchars($account['public_key']); ?></td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td><?php echo htmlspecialchars($account['created_at']); ?></td>
                    </tr>
                    <tr>
                        <th>Last Login</th>
                        <td><?php echo htmlspecialchars($account['last_login'] ?: 'Never'); ?></td>
                    </tr>
                </table>
            </div>
            <form method="POST">
                <button type="submit" name="logout">Logout</button>
            </form>
        </div>
    </div>

    <?php include '../include/footer.php'; ?>
    <script src="../js/vina.js"></script>
    <script src="../js/navbar.js"></script>
</body>
</html>
