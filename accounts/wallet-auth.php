<?php
// ============================================================================
// File: accounts/wallet-auth.php
// Description: API handles Solana wallet signature verification with rate limiting and session regeneration.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'accounts/bootstrap.php';
use StephenHill\Base58;

// Database connection
$start_time = microtime(true);
try {
    $pdo = get_db_connection();
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection successful (took {$duration}ms)", 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Rate limiting configuration
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if ($ip_address === 'Unknown') {
    log_message("Failed to retrieve IP address", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unable to process request: Invalid IP address'], JSON_UNESCAPED_UNICODE);
    exit;
}
$rate_limit = 5;
$rate_limit_window = 60;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['signature'], $_POST['message'])) {
    log_message("POST request received: public_key={$_POST['public_key']}, user_agent={$_SERVER['HTTP_USER_AGENT']}, IP=$ip_address", 'accounts.log', 'accounts', 'DEBUG');

    // Protect POST requests with CSRF
    csrf_protect();

    // Rate limiting
    try {
        if (!check_and_record_login_attempt($pdo, $ip_address, $rate_limit, $rate_limit_window)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Too many login attempts. Please wait 1 minute and try again.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Rate limiting error. Please try again later.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json');
    $public_key = $_POST['public_key'];
    $short_public_key = strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
    $signature = base64_decode($_POST['signature'], true);
    $message = $_POST['message'];
    $current_time = date('Y-m-d H:i:s');

    log_message("Received POST: public_key=$short_public_key, message=$message, IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
    log_message("Signature (base64): {$_POST['signature']}", 'accounts.log', 'accounts', 'DEBUG');

    if ($signature === false || strlen($signature) !== 64) {
        log_message("Invalid signature: Base64 decode failed or length incorrect (length: " . ($signature === false ? 'decode failed' : strlen($signature)) . "), IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Base64 decode failed or invalid signature'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    log_message("Signature decoded length: " . strlen($signature) . " bytes", 'accounts.log', 'accounts', 'DEBUG');

    try {
        // Check nonce and timestamp
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

        // Check sodium and base58 libraries
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new Exception("Sodium library is not installed!");
        }
        log_message("Sodium library ready", 'accounts.log', 'accounts', 'INFO');

        if (!class_exists('\StephenHill\Base58') || (!extension_loaded('bcmath') && !extension_loaded('gmp'))) {
            throw new Exception("Base58 library or BC Math/GMP extension is not installed!");
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
            try {
                sodium_crypto_sign_ed25519_pk_to_curve25519($public_key_bytes);
            } catch (SodiumException $se) {
                throw new Exception("Invalid public key: Not on Ed25519 curve - " . $se->getMessage());
            }
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Public key decoded and validated on curve: $short_public_key (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
            log_message("Public key hex: " . bin2hex($public_key_bytes), 'accounts.log', 'accounts', 'DEBUG');
        } catch (Exception $e) {
            throw new Exception("Public key decode error: " . $e->getMessage());
        }

        // Verify signature
        $start_time = microtime(true);
        try {
            if (!is_string($message) || empty($message)) {
                throw new Exception("Invalid message: Empty or non-string message");
            }
            if (!is_string($public_key_bytes) || strlen($public_key_bytes) !== 32) {
                throw new Exception("Invalid public key: Length is " . strlen($public_key_bytes) . " bytes, expected 32 bytes");
            }
            if (!is_string($signature) || strlen($signature) !== 64) {
                throw new Exception("Invalid signature: Length is " . strlen($signature) . " bytes, expected 64 bytes");
            }

            $verified = sodium_crypto_sign_verify_detached($signature, $message, $public_key_bytes);
            $duration = (microtime(true) - $start_time) * 1000;

            if (!$verified) {
                throw new Exception("Signature verification failed: Signature does not match");
            }
            log_message("Signature verified successfully (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
        } catch (Exception $e) {
            log_message("Signature verification error: {$e->getMessage()} (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
            throw $e;
        }

        // Consume nonce
        unset($_SESSION['login_nonce']);

        // Check and update account
        try {
            check_and_update_account($pdo, $public_key, $current_time);
            // Regenerate session ID
            $old_session_id = session_id();
            session_regenerate_id(true);
            $new_session_id = session_id();
            log_message("Session ID regenerated: old=$old_session_id, new=$new_session_id, public_key=$short_public_key", 'accounts.log', 'accounts', 'INFO');
            $_SESSION['public_key'] = $public_key;
            $redirect_url = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '/accounts/profile.php';
            unset($_SESSION['redirect_url']);
            echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'redirect' => $redirect_url], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            throw new Exception("Database query error: " . $e->getMessage());
        }
    } catch (Exception $e) {
        log_message("Error: {$e->getMessage()}, IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    exit;
}
?>
