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

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    log_message("Database connection successful");
} catch (PDOException $e) {
    log_message("Database connection failed: " . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit;
    }
    die("Database connection failed: " . $e->getMessage());
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['signature'], $_POST['message'])) {
    header('Content-Type: application/json');
    $public_key = $_POST['public_key'];
    $signature = base64_decode($_POST['signature'], true);
    $message = $_POST['message'];
    $current_time = date('Y-m-d H:i:s');

    log_message("Received POST: public_key=$public_key, message=$message");
    log_message("Signature (base64): " . $_POST['signature']);
    if ($signature === false || strlen($signature) !== 64) {
        log_message("Error: Base64 decode failed or invalid signature (length: " . strlen($signature) . ")");
        echo json_encode(['status' => 'error', 'message' => 'Base64 decode failed or invalid signature']);
        exit;
    }
    log_message("Signature decoded length: " . strlen($signature) . " bytes");

    try {
        // Check timestamp
        if (!preg_match('/at (\d+)/', $message, $matches)) {
            throw new Exception("Message does not contain timestamp!");
        }
        $timestamp = $matches[1];
        $current_timestamp = time() * 1000;
        if (abs($current_timestamp - $timestamp) > 60000) { // 1 minute
            throw new Exception("Message has expired!");
        }
        log_message("Timestamp valid: $timestamp");
        log_message("Server timezone: " . date_default_timezone_get());

        // Check sodium and base58 libraries
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new Exception("Sodium library not installed!");
        }
        $autoload_path = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload_path)) {
            throw new Exception("Composer autoload (vendor/autoload.php) not found!");
        }
        require_once $autoload_path;
        if (!class_exists('\Tuupola\Base58')) {
            throw new Exception("tuupola/base58 library not installed!");
        }
        $bs58 = new \Tuupola\Base58;
        log_message("Libraries loaded: sodium, base58");

        // Decode public_key
        try {
            $public_key_bytes = $bs58->decode($public_key);
            if (strlen($public_key_bytes) !== 32) {
                throw new Exception("Invalid public key!");
            }
            log_message("Public key decoded: $public_key");
        } catch (Exception $e) {
            throw new Exception("Error decoding public_key: " . $e->getMessage());
        }

        // Use raw message (ASCII)
        $message_raw = $message;
        log_message("Message hex: " . bin2hex($message_raw));
        log_message("Signature hex: " . bin2hex($signature));

        // Verify signature
        $verified = sodium_crypto_sign_verify_detached(
            $signature,
            $message_raw,
            $public_key_bytes
        );
        if (!$verified) {
            throw new Exception("Signature verification failed!");
        }
        log_message("Signature verified successfully");

        // Check and save to database
        try {
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE public_key = ?");
            $stmt->execute([$public_key]);
            $account = $stmt->fetch();
            log_message("Account check query: public_key=$public_key");
        } catch (PDOException $e) {
            throw new Exception("Database query error: " . $e->getMessage());
        }

        if ($account) {
            $stmt = $pdo->prepare("UPDATE accounts SET last_login = ? WHERE public_key = ?");
            $stmt->execute([$current_time, $public_key]);
            log_message("Login successful: public_key=$public_key");
            echo json_encode(['status' => 'success', 'message' => 'Login successful!']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO accounts (public_key, created_at, last_login) VALUES (?, ?, ?)");
            $stmt->execute([$public_key, $current_time, $current_time]);
            log_message("Registration successful: public_key=$public_key");
            echo json_encode(['status' => 'success', 'message' => 'Registration successful!']);
        }
    } catch (Exception $e) {
        log_message("Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}

// Render HTML for GET
$page_title = "Connect Wallet to Vina Network";
$page_description = "Connect your Solana wallet to register or login to Vina Network";
$page_keywords = "Vina Network, connect wallet, login, register";
$page_og_title = "Connect Wallet to Vina Network";
$page_og_description = "Connect your Solana wallet to register or login to Vina Network";
$page_og_url = "https://www.vina.network/accounts/";
$page_canonical = "https://www.vina.network/accounts/";
$page_css = ['acc.css'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../include/header.php'; ?>
</head>
<body>
    <?php include '../include/navbar.php'; ?>
    <div class="acc-container">
        <div class="acc-content">
            <h1>Login/Register with Phantom Wallet</h1>
            <button id="connect-wallet">Connect Phantom Wallet</button>
            <div id="wallet-info" style="display: none;">
                <p>Wallet Address: <span id="public-key"></span></p>
                <p>Status: <span id="status"></span></p>
            </div>
        </div>
    </div>
    <?php include '../include/footer.php'; ?>
    <script src="https://unpkg.com/@solana/web3.js@1.95.3/lib/index.iife.min.js"></script>
    <script src="../js/vina.js"></script>
    <script src="../js/navbar.js"></script>
    <script src="acc.js"></script>
</body>
</html>
