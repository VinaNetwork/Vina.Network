<?php
// ============================================================================
// File: accounts/wallet-login.php
// Description: API handles Solana wallet signature verification with rate limiting and session regeneration.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . '../vendor/autoload.php';
use StephenHill\Base58;

// Session start: in config/bootstrap.php

// Database connection
$start_time = microtime(true);
try {
    $pdo = get_db_connection(); // Use the function from config/db.php
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection successful (took {$duration}ms)", 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Rate limiting
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if ($ip_address === 'Unknown') {
    log_message("Failed to retrieve IP address", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unable to process request: Invalid IP address']);
    exit;
}
$rate_limit = 5; // Maximum 5 attempts per minute
$rate_limit_window = 60; // 1 minute in seconds

try {
    // Clean up old attempts
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < ?");
    $stmt->execute([date('Y-m-d H:i:s', time() - $rate_limit_window)]);
    log_message("Cleaned up old login attempts for IP: $ip_address", 'accounts.log', 'accounts', 'INFO');

    // Count recent attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM login_attempts WHERE ip_address = ? AND attempt_time >= ?");
    $stmt->execute([$ip_address, date('Y-m-d H:i:s', time() - $rate_limit_window)]);
    $attempt_count = $stmt->fetchColumn();
    log_message("Checked login attempts for IP: $ip_address, count: $attempt_count", 'accounts.log', 'accounts', 'INFO');

    if ($attempt_count >= $rate_limit) {
        log_message("Rate limit exceeded for IP: $ip_address, attempts: $attempt_count", 'accounts.log', 'accounts', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Too many login attempts. Please wait 1 minute and try again.']);
        exit;
    }

    // Log the current attempt
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, ?)");
    $stmt->execute([$ip_address, date('Y-m-d H:i:s')]);
    log_message("Recorded login attempt for IP: $ip_address, attempt count: " . ($attempt_count + 1), 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    log_message("Rate limiting error for IP: $ip_address, SQL Error: {$e->getMessage()}, Code: {$e->getCode()}", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Rate limiting error. Please try again later.']);
    exit;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['signature'], $_POST['message'], $_POST['csrf_token'])) {
    header('Content-Type: application/json');

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'])) {
        log_message("Invalid CSRF token for login attempt from IP: $ip_address", 'accounts.log', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $public_key = $_POST['public_key'];
    $short_public_key = strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
    $signature = base64_decode($_POST['signature'], true);
    $message = $_POST['message'];
    $current_time = date('Y-m-d H:i:s');

    log_message("Received POST: public_key=$short_public_key, message=$message, IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
    log_message("Signature (base64): {$_POST['signature']}", 'accounts.log', 'accounts', 'DEBUG');

    if ($signature === false || strlen($signature) !== 64) {
        log_message("Invalid signature: Base64 decode failed or length incorrect (length: " . ($signature === false ? 'decode failed' : strlen($signature)) . "), IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Base64 decode failed or invalid signature']);
        exit;
    }
    log_message("Signature decoded length: " . strlen($signature) . " bytes", 'accounts.log', 'accounts', 'DEBUG');

    try {
        // Check nonce and timestamp in message
        if (!preg_match('/with nonce (\w+) at (\d+)/', $message, $matches)) {
            throw new Exception("Message does not contain nonce or timestamp!");
        }
        $extracted_nonce = $matches[1];
        $timestamp = $matches[2];

        if (!isset($_SESSION['login_nonce']) || $extracted_nonce !== $_SESSION['login_nonce']) {
            throw new Exception("Invalid nonce!");
        }

        $current_timestamp = time() * 1000;
        if (abs($current_timestamp - $timestamp) > 300000) {
            throw new Exception("Message has expired!");
        }
        log_message("Valid timestamp: $timestamp and nonce: $extracted_nonce, IP=$ip_address", 'accounts.log', 'accounts', 'INFO');

        // Check sodium library
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new Exception("Sodium library is not installed!");
        }
        log_message("Sodium library ready", 'accounts.log', 'accounts', 'INFO');

        // Check stephenhill/base58 and extensions
        if (!class_exists('\StephenHill\Base58')) {
            throw new Exception("stephenhill/base58 library is not installed!");
        }
        if (!extension_loaded('bcmath') && !extension_loaded('gmp')) {
            throw new Exception("Please install the BC Math or GMP extension");
        }
        log_message("Base58 library ready", 'accounts.log', 'accounts', 'INFO');

        // Decode public_key
        $start_time = microtime(true);
        try {
            $bs58 = new Base58();
            $public_key_bytes = $bs58->decode($public_key);
            if (strlen($public_key_bytes) !== 32) {
                throw new Exception("Invalid public key: Length is " . strlen($public_key_bytes) . " bytes, expected 32 bytes");
            }

            // Check if public key is on Ed25519 curve using conversion to X25519
            try {
                sodium_crypto_sign_ed25519_pk_to_curve25519($public_key_bytes);
                // If no exception, key is valid on curve; no need to store the result
            } catch (SodiumException $se) {
                throw new Exception("Invalid public key: Not on Ed25519 curve - " . $se->getMessage());
            }

            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Public key decoded and validated on curve: $short_public_key (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
            log_message("Public key hex: " . bin2hex($public_key_bytes), 'accounts.log', 'accounts', 'DEBUG');
        } catch (Exception $e) {
            throw new Exception("Public key decode error: " . $e->getMessage());
        }

        // Use raw message directly
        $message_raw = $message;
        log_message("Message hex: " . bin2hex($message_raw), 'accounts.log', 'accounts', 'DEBUG');
        log_message("Signature hex: " . bin2hex($signature), 'accounts.log', 'accounts', 'DEBUG');

        // Verify signature
        $start_time = microtime(true);
        try {
            if (!is_string($message_raw) || empty($message_raw)) {
                throw new Exception("Invalid message: Empty or non-string message");
            }
            if (!is_string($public_key_bytes) || strlen($public_key_bytes) !== 32) {
                throw new Exception("Invalid public key: Length is " . strlen($public_key_bytes) . " bytes, expected 32 bytes");
            }
            if (!is_string($signature) || strlen($signature) !== 64) {
                throw new Exception("Invalid signature: Length is " . strlen($signature) . " bytes, expected 64 bytes");
            }

            $verified = sodium_crypto_sign_verify_detached(
                $signature,
                $message_raw,
                $public_key_bytes
            );
            $duration = (microtime(true) - $start_time) * 1000;

            if (!$verified) {
                $errors = [];
                if (bin2hex($message_raw) !== bin2hex($message)) {
                    $errors[] = "Message encoding mismatch";
                }
                if (strlen($public_key_bytes) !== 32) {
                    $errors[] = "Public key length invalid";
                }
                if (strlen($signature) !== 64) {
                    $errors[] = "Signature length invalid";
                }
                $error_message = "Signature verification failed: " . (empty($errors) ? "Signature does not match, please try reconnecting your wallet" : implode(", ", $errors));
                throw new Exception($error_message);
            }
            log_message("Signature verified successfully (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
        } catch (Exception $e) {
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Signature verification error: {$e->getMessage()} (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
            throw $e;
        }

        // Consume nonce after successful verification
        unset($_SESSION['login_nonce']);

        // Check and save to database
        $start_time = microtime(true);
        try {
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE public_key = ?");
            $stmt->execute([$public_key]);
            $account = $stmt->fetch();
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Account check query: public_key=$short_public_key (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
        } catch (PDOException $e) {
            throw new Exception("Database query error: " . $e->getMessage());
        }

        // Regenerate session ID to prevent session fixation
        $old_session_id = session_id();
        session_regenerate_id(true);
        $new_session_id = session_id();
        log_message("Session ID regenerated: old=$old_session_id, new=$new_session_id, public_key=$short_public_key", 'accounts.log', 'accounts', 'INFO');

        // Determine redirect URL
        $redirect_url = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '/accounts/profile.php';
        unset($_SESSION['redirect_url']); // Clear after use

        if ($account) {
            $start_time = microtime(true);
            $stmt = $pdo->prepare("UPDATE accounts SET last_login = ? WHERE public_key = ?");
            $stmt->execute([$current_time, $public_key]);
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Login successful: public_key=$short_public_key (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
            $_SESSION['public_key'] = $public_key;
            echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'redirect' => $redirect_url]);
        } else {
            $start_time = microtime(true);
            $stmt = $pdo->prepare("INSERT INTO accounts (public_key, created_at, last_login) VALUES (?, ?, ?)");
            $stmt->execute([$public_key, $current_time, $current_time]);
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Registration successful: public_key=$short_public_key (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
            $_SESSION['public_key'] = $public_key;
            echo json_encode(['status' => 'success', 'message' => 'Registration successful!', 'redirect' => $redirect_url]);
        }
    } catch (Exception $e) {
        log_message("Error: {$e->getMessage()}, IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}
?>
