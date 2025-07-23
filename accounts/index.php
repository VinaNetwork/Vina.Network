<!DOCTYPE html>
<html lang="en">
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

// Kết nối cơ sở dữ liệu
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Kết nối cơ sở dữ liệu thất bại']);
        exit;
    }
    die("Kết nối cơ sở dữ liệu thất bại: " . $e->getMessage());
}

// Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['signature'], $_POST['message'])) {
    header('Content-Type: application/json');
    $public_key = $_POST['public_key'];
    $signature = base64_decode($_POST['signature'], true);
    $message = $_POST['message'];
    $current_time = date('Y-m-d H:i:s');

    log_message("Nhận POST: public_key=$public_key, message=$message");
    log_message("Signature (base64): " . $_POST['signature']);
    if ($signature === false || strlen($signature) !== 64) {
        log_message("Lỗi: Base64 decode thất bại hoặc chữ ký không hợp lệ (độ dài: " . strlen($signature) . ")");
        echo json_encode(['status' => 'error', 'message' => 'Base64 decode thất bại hoặc chữ ký không hợp lệ']);
        exit;
    }
    log_message("Signature decoded length: " . strlen($signature) . " bytes");

    try {
        // Kiểm tra timestamp
        if (!preg_match('/at (\d+)/', $message, $matches)) {
            throw new Exception("Thông điệp không chứa timestamp!");
        }
        $timestamp = $matches[1];
        $current_timestamp = time() * 1000;
        if (abs($current_timestamp - $timestamp) > 300000) {
            throw new Exception("Thông điệp đã hết hạn!");
        }
        log_message("Timestamp hợp lệ: $timestamp");

        // Kiểm tra thư viện sodium
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new Exception("Thư viện sodium không được cài đặt!");
        }
        log_message("Thư viện sodium sẵn sàng");

        // Kiểm tra và nạp thư viện base58
        $autoload_path = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload_path)) {
            throw new Exception("Thư viện Composer (vendor/autoload.php) không tồn tại!");
        }
        require_once $autoload_path;
        if (!class_exists('\Tuupola\Base58')) {
            throw new Exception("Thư viện tuupola/base58 không được cài đặt!");
        }
        $bs58 = new \Tuupola\Base58;
        log_message("Thư viện base58 sẵn sàng");

        // Decode public_key
        try {
            $public_key_bytes = $bs58->decode($public_key);
            if (strlen($public_key_bytes) !== 32) {
                throw new Exception("Public key không hợp lệ!");
            }
            log_message("Public key decoded: $public_key");
        } catch (Exception $e) {
            throw new Exception("Lỗi decode public_key: " . $e->getMessage());
        }

        // Convert message to raw UTF-8 bytes
        $message_raw = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
        log_message("Message hex: " . bin2hex($message_raw));
        log_message("Signature hex: " . bin2hex($signature));

        // Xác minh chữ ký
        $verified = sodium_crypto_sign_verify_detached(
            $signature,
            $message_raw,
            $public_key_bytes
        );
        if (!$verified) {
            throw new Exception("Xác minh chữ ký thất bại!");
        }
        log_message("Chữ ký xác minh thành công");

        // Kiểm tra và lưu vào cơ sở dữ liệu
        try {
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE public_key = ?");
            $stmt->execute([$public_key]);
            $account = $stmt->fetch();
            log_message("Truy vấn kiểm tra tài khoản: public_key=$public_key");
        } catch (PDOException $e) {
            throw new Exception("Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage());
        }

        if ($account) {
            $stmt = $pdo->prepare("UPDATE accounts SET last_login = ? WHERE public_key = ?");
            $stmt->execute([$current_time, $public_key]);
            log_message("Đăng nhập thành công: public_key=$public_key");
            echo json_encode(['status' => 'success', 'message' => 'Đăng nhập thành công!']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO accounts (public_key, created_at, last_login) VALUES (?, ?, ?)");
            $stmt->execute([$public_key, $current_time, $current_time]);
            log_message("Đăng ký thành công: public_key=$public_key");
            echo json_encode(['status' => 'success', 'message' => 'Đăng ký thành công!']);
        }
    } catch (Exception $e) {
        log_message("Lỗi: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}

// Render HTML cho GET
$page_title = "Connect Wallet to Vina Network";
$page_description = "Connect your Solana wallet to register or login to Vina Network";
$page_keywords = "Vina Network, connect wallet, login, register";
$page_og_title = "Connect Wallet to Vina Network";
$page_og_description = "Connect your Solana wallet to register or login to Vina Network";
$page_og_url = "https://www.vina.network/accounts/";
$page_canonical = "https://www.vina.network/accounts/";
$page_css = ['acc.css'];
<?php include '../include/header.php'; ?>
?>

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
    <?php include '../include/footer.php'; ?>

    <script src="https://unpkg.com/@solana/web3.js@latest/lib/index.iife.min.js"></script>
    <script src="../js/vina.js"></script>
    <script src="../js/navbar.js"></script>
    <script src="acc.js"></script>
</body>
</html>
