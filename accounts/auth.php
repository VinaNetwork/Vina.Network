<?php
// ============================================================================
// File: accounts/auth.php
// Description: API handles Solana wallet signature verification with rate limiting.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    die("Access denied: Direct access to this file is not allowed.");
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use StephenHill\Base58;

session_start();

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
    log_message("Database connection successful (took {$duration}ms)", 'acc_auth.txt', 'accounts', 'INFO');
} catch (PDOException $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'acc_auth.txt', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Rate limiting
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$rate_limit = 5; // Maximum 5 attempts per minute
$rate_limit_window = 60; // 1 minute in seconds

try {
    // Clean up old attempts (older than 1 minute)
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < ?");
    $stmt->execute([date('Y-m-d H:i:s', time() - $rate_limit_window)]);
    log_message("Cleaned up old login attempts for IP: $ip_address", 'acc_auth.txt', 'accounts', 'INFO');

    // Count recent attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM login_attempts WHERE ip_address = ? AND attempt_time >= ?");
    $stmt->execute([$ip_address, date('Y-m-d H:i:s', time() - $rate_limit_window)]);
    $attempt_count = $stmt->fetchColumn();

    if ($attempt_count >= $rate_limit) {
        log_message("Rate limit exceeded for IP: $ip_address, attempts: $attempt_count", 'acc_auth.txt', 'accounts', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Too many login attempts. Please wait 1 minute and try again.']);
        exit;
    }

    // Log the current attempt
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, ?)");
    $stmt->execute([$ip_address, date('Y-m-d H:i:s')]);
    log_message("Recorded login attempt for IP: $ip_address, attempt count: " . ($attempt_count + 1), 'acc_auth.txt', 'accounts', 'INFO');
} catch (PDOException $e) {
    log_message("Rate limiting error: {$e->getMessage()}", 'acc_auth.txt', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Rate limiting error']);
    exit;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['signature'], $_POST['message'], $_POST['csrf_token'])) {
    header('Content-Type: application/json');

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'])) {
        log_message("Invalid CSRF token for login attempt from IP: $ip_address", 'acc_auth.txt', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $public_key = $_POST['public_key'];
    $short_public_key = strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
    $signature = base64_decode($_POST['signature'], true);
    $message = $_POST['message'];
    $current_time = date('Y-m-d H:i:s');

    log_message("Received POST: public_key=$short_public_key, message=$message, IP=$ip_address", 'acc_auth.txt', 'accounts', 'INFO');
    log_message("Signature (base64): {$_POST['signature']}", 'acc_auth.txt', 'accounts', 'DEBUG');

    if ($signature === false || strlen($signature) !== 64) {
        log_message("Invalid signature: Base64 decode failed or length incorrect (length: " . ($signature === false ? 'decode failed' : strlen($signature)) . "), IP=$ip_address", 'acc_auth.txt', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Base64 decode failed or invalid signature']);
        exit;
    }
    log_message("Signature decoded length: " . strlen($signature) . " bytes", 'acc_auth.txt', 'accounts', 'DEBUG');

    try {
        // Check timestamp
        if (!preg_match('/at (\d+)/', $message, $matches)) {
            throw new Exception("Message does not contain timestamp!");
        }
        $timestamp = $matches[1];
        $current_timestamp = time() * 1000;
        if (abs($current_timestamp - $timestamp) > 300000) {
            throw new Exception("Message has expired!");
        }
        log_message("Valid timestamp: $timestamp, IP=$ip_address", 'acc_auth.txt', 'accounts', 'INFO');

        // Check sodium library
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new Exception("Sodium library is not installed!");
        }
        log_message("Sodium library ready", 'acc_auth.txt', 'accounts', 'INFO');

        // Check stephenhill/base58 and extensions
        if (!class_exists('\StephenHill\Base58')) {
            throw new Exception("stephenhill/base58 library is not installed!");
        }
        if (!extension_loaded('bcmath') && !extension_loaded('gmp')) {
            throw new Exception("Please install the BC Math or GMP extension");
        }
        log_message("Base58 library ready", 'acc_auth.txt', 'accounts', 'INFO');

        // Decode public_key
        $start_time = microtime(true);
        try {
            $bs58 = new Base58();
            $public_key_bytes = $bs58->decode($public_key);
            if (strlen($public_key_bytes) !== 32) {
                throw new Exception("Invalid public key: Length is " . strlen($public_key_bytes) . " bytes, expected 32 bytes");
            }
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Public key decoded: $short_public_key (took {$duration}ms), IP=$ip_address", 'acc_auth.txt', 'accounts', 'INFO');
            log_message("Public key hex: " . bin2hex($public_key_bytes), 'acc_auth.txt', 'accounts', 'DEBUG');
        } catch (Exception $e) {
            throw new Exception("Public key decode error: " . $e->getMessage());
        }

        // Use raw message directly
        $message_raw = $message;
        log_message("Message hex: " . bin2hex($message_raw), 'acc_auth.txt', 'accounts', 'DEBUG');
        log_message("Signature hex: " . bin2hex($signature), 'acc_auth.txt', 'accounts', 'DEBUG');

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
            log_message("Signature verified successfully (took {$duration}ms), IP=$ip_address", 'acc_auth.txt', 'accounts', 'INFO');
        } catch (Exception $e) {
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Signature verification error: {$e->getMessage()} (took {$duration}ms), IP=$ip_address", 'acc_auth.txt', 'accounts', 'ERROR');
            throw $e;
        }

        // Check and save to database
        $start_time = microtime(true);
        try {
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE public_key = ?");
            $stmt->execute([$public_key]);
            $account = $stmt->fetch();
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Account check query: public_key=$short_public_key (took {$duration}ms), IP=$ip_address", 'acc_auth.txt', 'accounts', 'INFO');
        } catch (PDOException $e) {
            throw new Exception("Database query error: " . $e->getMessage());
        }

        if ($account) {
            $start_time = microtime(true);
            $stmt = $pdo->prepare("UPDATE accounts SET last_login = ? WHERE public_key = ?");
            $stmt->execute([$current_time, $public_key]);
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Login successful: public_key=$short_public_key (took {$duration}ms), IP=$ip_address", 'acc_auth.txt', 'accounts', 'INFO');
            $_SESSION['public_key'] = $public_key;
            echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'redirect' => 'profile.php']);
        } else {
            $start_time = microtime(true);
            $stmt = $pdo->prepare("INSERT INTO accounts (public_key, created_at, last_login) VALUES (?, ?, ?)");
            $stmt->execute([$public_key, $current_time, $current_time]);
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Registration successful: public_key=$short_public_key (took {$duration}ms), IP=$ip_address", 'acc_auth.txt', 'accounts', 'INFO');
            $_SESSION['public_key'] = $public_key;
            echo json_encode(['status' => 'success', 'message' => 'Registration successful!', 'redirect' => 'profile.php']);
        }
    } catch (Exception $e) {
        log_message("Error: {$e->getMessage()}, IP=$ip_address", 'acc_auth.txt', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}
?>
