<!DOCTYPE html>
<html lang="en">
<?php
// ============================================================================
// File: accounts/index.php
// Description: Connect wallet page for Vina Network. Handles both registration and login with signature verification.
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

<div class="acc-container">
    <div class="acc-content">
        <h1>Đăng nhập/Đăng ký với ví Phantom</h1>
        <button id="connect-wallet">Kết nối ví Phantom</button>
        <div id="wallet-info" style="display: none;">
            <p>Địa chỉ ví: <span id="public-key"></span></p>
            <p>Trạng thái: <span id="status"></span></p>
        </div>
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

    // Xử lý đăng ký/đăng nhập với xác minh chữ ký
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['signature'], $_POST['message'])) {
        $public_key = $_POST['public_key'];
        $signature = base64_decode($_POST['signature']); // Giải mã base64
        $message = $_POST['message'];
        $current_time = date('Y-m-d H:i:s');

        // Xác minh chữ ký bằng sodium
        try {
            // Chuyển public_key thành định dạng byte bằng cách decode từ base58
            // Lưu ý: Cần cài đặt thư viện `bs58` nếu muốn decode base58 trên PHP
            // Để đơn giản, chúng ta giả định public_key đã được xử lý đúng
            if (!function_exists('sodium_crypto_sign_verify_detached')) {
                throw new Exception("Thư viện sodium không được cài đặt!");
            }
            // Lưu ý: Cần thư viện để chuyển public_key từ base58 sang byte
            // Tạm thời bỏ qua bước decode public_key và giả định chữ ký đã được xử lý đúng
            // Để xác minh chính xác, cần cài đặt thư viện như `tuupola/base58`
            require_once __DIR__ . '/../vendor/autoload.php'; // Nếu dùng composer
            $bs58 = new \Tuupola\Base58;
            $public_key_bytes = $bs58->decode($public_key);

            if (strlen($public_key_bytes) !== 32) {
                throw new Exception("Public key không hợp lệ!");
            }

            $verified = sodium_crypto_sign_verify_detached(
                $signature,
                $message,
                $public_key_bytes
            );
            if (!$verified) {
                echo "<script>document.getElementById('status').textContent = 'Xác minh chữ ký thất bại!';</script>";
                exit;
            }
        } catch (Exception $e) {
            echo "<script>document.getElementById('status').textContent = 'Lỗi xác minh: " . addslashes($e->getMessage()) . "';</script>";
            exit;
        }

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
