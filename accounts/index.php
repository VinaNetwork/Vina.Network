<!DOCTYPE html>
<html lang="en">
<?php
// ============================================================================
// File: accounts/index.php
// Description: Connect wallet page for Vina Network. Handles both registration and login.
// Created by: Vina Network
// ============================================================================

// Định nghĩa hằng số để cho phép truy cập trực tiếp
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
$page_title = "Connect Wallet to Vina Network";
$page_description = "Connect your Solana wallet to register or login to Vina Network";
$page_keywords = "Vina Network, connect wallet, login, register";
$page_og_title = "Connect Wallet to Vina Network";
$page_og_description = "Connect your Solana wallet to register or login to Vina Network";
$page_og_url = "https://www.vina.network/accounts/";
$page_canonical = "https://www.vina.network/accounts/";
$page_css = ['acc.css'];

include '../include/header.php';
// Load file cấu hình
require_once __DIR__ . '/../config/config.php';
?>
<body>
<!-- Navigation Bar -->
<?php include '../include/navbar.php'; ?>

<div class="container">
        <h1>Đăng nhập/Đăng ký với ví Phantom</h1>
        <button id="connect-wallet">Kết nối ví Phantom</button>
        <div id="wallet-info" style="display: none;">
            <p>Địa chỉ ví: <span id="public-key"></span></p>
            <p>Trạng thái: <span id="status"></span></p>
        </div>
</div>

<?php
    // Kết nối cơ sở dữ liệu sử dụng các hằng từ config.php
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Kết nối cơ sở dữ liệu thất bại: " . $e->getMessage());
    }

    // Xử lý đăng ký/đăng nhập
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'])) {
        $public_key = $_POST['public_key'];
        $current_time = date('Y-m-d H:i:s');

        // Kiểm tra xem public_key đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE public_key = ?");
        $stmt->execute([$public_key]);
        $account = $stmt->fetch();

        if ($account) {
            // Cập nhật last_login nếu tài khoản đã tồn tại (đăng nhập)
            $stmt = $pdo->prepare("UPDATE accounts SET last_login = ? WHERE public_key = ?");
            $stmt->execute([$current_time, $public_key]);
            echo "<script>document.getElementById('status').textContent = 'Đăng nhập thành công!';</script>";
        } else {
            // Tạo tài khoản mới (đăng ký)
            $stmt = $pdo->prepare("INSERT INTO accounts (public_key, created_at, last_login) VALUES (?, ?, ?)");
            $stmt->execute([$public_key, $current_time, $current_time]);
            echo "<script>document.getElementById('status').textContent = 'Đăng ký thành công!';</script>";
        }
    }
?>

<!-- Footer Section -->
<?php include '../include/footer.php'; ?>
<!-- Scripts -->
<script src="https://unpkg.com/@solana/web3.js@latest/lib/index.iife.min.js"></script>
<script src="../js/vina.js"></script>
<script src="../js/navbar.js"></script>
<script src="acc.js"></script>
</body>
</html>
