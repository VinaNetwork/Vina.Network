<?php
// ============================================================================
// File: accounts/index.php
// Description: Connect wallet page for Vina Network. Handles both registration and login with signature verification and timestamp check.
// Created by: Vina Network
// ============================================================================

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
require_once __DIR__ . '/../config/config.php';

function log_message($message) {
    $log_file = __DIR__ . '/../logs/accounts.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}
?>
<!DOCTYPE html>
<html lang="en">
<body>
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
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    log_message("Kết nối cơ sở dữ liệu thành công");
} catch (PDOException $e) {
    log_message("Kết nối cơ sở dữ liệu thất bại: " . $e->getMessage());
    die("Kết nối cơ sở dữ liệu thất bại: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['signature'], $_POST['message'])) {
    $public_key = $_POST['public_key'];
    $signature = base64_decode($_POST['signature'], true);
    $message = $_POST['message'];
    $current_time = date('Y-m-d H:i:s');

    log_message("Nhận POST: public_key=$public_key, message=$message");

    try {
        if ($signature === false || strlen($signature) !== 64) {
            throw new Exception("Base64 decode thất bại hoặc chữ ký không hợp lệ!");
        }

        if (!preg_match('/at (\d+)/', $message, $matches)) {
            throw new Exception("Thông điệp không chứa timestamp!");
        }

        $timestamp = $matches[1];
        $current_timestamp = time() * 1000;
        if (abs($current_timestamp - $timestamp) > 300000) {
            throw new Exception("Thông điệp đã hết hạn!");
        }
        log_message("Timestamp hợp lệ: $timestamp");

        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new Exception("Thư viện sodium không được cài đặt!");
        }

        if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
            throw new Exception("Thư viện Composer (vendor/autoload.php) không tồn tại!");
        }
        require_once __DIR__ . '/../vendor/autoload.php';
        if (!class_exists('\Tuupola\Base58')) {
            throw new Exception("Thư viện tuupola/base58 không được cài đặt!");
        }

        $bs58 = new \Tuupola\Base58;
        $public_key_bytes = $bs58->decode($public_key);
        if (strlen($public_key_bytes) !== 32) {
            throw new Exception("Public key không hợp lệ!");
        }

        log_message("Public key decoded: $public_key");
        log_message("Độ dài chữ ký: " . strlen($signature));

        // Convert message to raw UTF-8 bytes (as Phantom signed)
        $message_raw = mb_convert_encoding($message, 'UTF-8', 'UTF-8');

        // Log raw bytes for debug
        log_message("Message hex: " . bin2hex($message_raw));
        log_message("Signature hex: " . bin2hex($signature));

        $verified = sodium_crypto_sign_verify_detached(
            $signature,
            $message_raw,
            $public_key_bytes
        );

        if (!$verified) {
            throw new Exception("Xác minh chữ ký thất bại!");
        }
        log_message("Chữ ký xác minh thành công");

        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE public_key = ?");
        $stmt->execute([$public_key]);
        $account = $stmt->fetch();

        if ($account) {
            $stmt = $pdo->prepare("UPDATE accounts SET last_login = ? WHERE public_key = ?");
            $stmt->execute([$current_time, $public_key]);
            log_message("Đăng nhập thành công: public_key=$public_key");
            echo "<script>document.getElementById('status').textContent = 'Đăng nhập thành công!';</script>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO accounts (public_key, created_at, last_login) VALUES (?, ?, ?)");
            $stmt->execute([$public_key, $current_time, $current_time]);
            log_message("Đăng ký thành công: public_key=$public_key");
            echo "<script>document.getElementById('status').textContent = 'Đăng ký thành công!';</script>";
        }
    } catch (Exception $e) {
        log_message("Lỗi: " . $e->getMessage());
        echo "<script>document.getElementById('status').textContent = 'Lỗi: " . addslashes($e->getMessage()) . "';</script>";
        exit;
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        log_message("Yêu cầu POST không hợp lệ: Thiếu public_key, signature, hoặc message");
    }
}
?>

<?php include '../include/footer.php'; ?>
<script src="https://unpkg.com/@solana/web3.js@latest/lib/index.iife.min.js"></script>
<script src="../js/vina.js"></script>
<script src="../js/navbar.js"></script>
<script src="acc.js"></script>
</body>
</html>
