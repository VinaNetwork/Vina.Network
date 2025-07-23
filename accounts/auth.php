<?php
// ============================================================================
// File: accounts/auth.php
// Description: Handles signature verification and database operations for Vina Network wallet authentication.
// Created by: Vina Network
// ============================================================================

// Additional check to ensure the file is included from index.php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    die("Access denied: This file cannot be accessed directly.");
}

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

// Database connection
log_message("Starting database connection attempt");
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
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['signature'], $_POST['message'])) {
    header('Content-Type: application/json');
    $public_key = $_POST['public_key'];
    $message = $_POST['message'];
    $current_time = date('Y-m-d H:i:s');

    log_message("Received POST request: public_key=$public_key, message=$message");
    log_message("Signature (base64): " . $_POST['signature']);
    $signature = base64_decode($_POST['signature'], true);
    if ($signature === false || strlen($signature) !== 64) {
        log_message("Error: Base64 decode failed or invalid signature (length: " . strlen($signature) . ")");
        echo json_encode(['status' => 'error', 'message' => 'Base64 decode failed or invalid signature']);
        exit;
    }
    log_message("Signature decoded successfully, length: " . strlen($signature) . " bytes");

    try {
        log_message("Starting signature verification process");
        // Check timestamp
        log_message("Checking timestamp in message");
        if (!preg_match('/at (\d+)/', $message, $matches)) {
            throw new Exception("Message does not contain timestamp!");
        }
        $timestamp = $matches[1];
        $current_timestamp = time() * 1000;
        if (abs($current_timestamp - $timestamp) > 300000) {
            throw new Exception("Message has expired!");
        }
        log_message("Timestamp valid: $timestamp");

        // Check sodium library
        log_message("Checking sodium library availability");
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new Exception("Sodium library is not installed!");
        }
        log_message("Sodium library ready");

        // Check and load base58 library
        log_message("Checking base58 library availability");
        $autoload_path = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload_path)) {
            throw new Exception("Composer library (vendor/autoload.php) does not exist!");
        }
        require_once $autoload_path;
        if (!class_exists('\Tuupola\Base58')) {
            throw new Exception("tuupola/base58 library is not installed!");
        }
        $bs58 = new \Tuupola\Base58;
        log_message("Base58 library ready");

        // Decode public_key
        log_message("Decoding public key");
        try {
            $public_key_bytes = $bs58->decode($public_key);
            if (strlen($public_key_bytes) !== 32) {
                throw new Exception("Invalid public key!");
            }
            log_message("Public key decoded successfully: $public_key");
        } catch (Exception $e) {
            throw new Exception("Public key decode error: " . $e->getMessage());
        }

        // Convert message to raw UTF-8 bytes
        log_message("Converting message to UTF-8 bytes");
        $message_raw = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
        log_message("Message hex: " . bin2hex($message_raw));
        log_message("Signature hex: " . bin2hex($signature));

        // Verify signature
        log_message("Verifying signature");
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
        log_message("Querying database for account with public_key=$public_key");
        try {
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE public_key = ?");
            $stmt->execute([$public_key]);
            $account = $stmt->fetch();
            log_message("Account check query completed: public_key=$public_key, found=" . ($account ? 'true' : 'false'));
        } catch (PDOException $e) {
            throw new Exception("Database query error: " . $e->getMessage());
        }

        if ($account) {
            log_message("Updating last login for existing account");
            $stmt = $pdo->prepare("UPDATE accounts SET last_login = ? WHERE public_key = ?");
            $stmt->execute([$current_time, $public_key]);
            log_message("Login successful: public_key=$public_key");
            echo json_encode(['status' => 'success', 'message' => 'Login successful!']);
        } else {
            log_message("Inserting new account into database");
            $stmt = $pdo->prepare("INSERT INTO accounts (public_key, created_at, last_login) VALUES (?, ?, ?)");
            $stmt->execute([$public_key, $current_time, $current_time]);
            log_message("Registration successful: public_key=$public_key");
            echo json_encode(['status' => 'success', 'message' => 'Registration successful!']);
        }
        log_message("Signature verification process completed successfully");
    } catch (Exception $e) {
        log_message("Error during verification: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}
