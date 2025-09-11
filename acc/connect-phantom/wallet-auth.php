<?php
// ============================================================================
// File: acc/connect-phantom/wallet-auth.php
// Description: API handles Solana wallet signature verification with rate limiting and session regeneration.
// Created by: Vina Network
// ============================================================================

// Web root
$root_path = __DIR__ . '/../../';
require_once $root_path . 'acc/bootstrap.php';
use StephenHill\Base58;

// Set response header and CORS from header-auth.php
header('Content-Type: application/json');

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method: {$_SERVER['REQUEST_METHOD']}, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Use POST.']);
    exit;
}

// Check X-Auth-Token
$headers = getallheaders();
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;

if ($authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'ERROR');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token']);
    exit;
}

// Database connection
$start_time = microtime(true);
try {
    $pdo = get_db_connection();
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection successful (took {$duration}ms)", 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'accounts.log', 'accounts', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Rate limiting configuration
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if ($ip_address === 'Unknown') {
    log_message("Failed to retrieve IP address", 'accounts.log', 'accounts', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Unable to process request: Invalid IP address']);
    exit;
}
$rate_limit = 5;
$rate_limit_window = 60;

// Handle POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['public_key'], $data['signature'], $data['message'])) {
    log_message("Invalid POST data: Missing required fields, IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// Log incoming POST request details
log_message("POST request received: public_key={$data['public_key']}, user_agent={$_SERVER['HTTP_USER_AGENT']}, IP=$ip_address", 'accounts.log', 'accounts', 'DEBUG');

// Rate limiting
try {
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < ?");
    $stmt->execute([date('Y-m-d H:i:s', time() - $rate_limit_window)]);
    log_message("Cleaned up old login attempts for IP: $ip_address", 'accounts.log', 'accounts', 'INFO');

    $stmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM login_attempts WHERE ip_address = ? AND attempt_time >= ?");
    $stmt->execute([$ip_address, date('Y-m-d H:i:s', time() - $rate_limit_window)]);
    $attempt_count = $stmt->fetchColumn();
    log_message("Checked login attempts for IP: $ip_address, count: $attempt_count", 'accounts.log', 'accounts', 'INFO');

    if ($attempt_count >= $rate_limit) {
        log_message("Rate limit exceeded for IP: $ip_address, attempts: $attempt_count", 'accounts.log', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Too many login attempts. Please wait 1 minute and try again.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, ?)");
    $stmt->execute([$ip_address, date('Y-m-d H:i:s')]);
    log_message("Recorded login attempt for IP: $ip_address, attempt count: " . ($attempt_count + 1), 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    log_message("Rate limiting error for IP: $ip_address, SQL Error: {$e->getMessage()}, Code: {$e->getCode()}", 'accounts.log', 'accounts', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Rate limiting error. Please try again later.']);
    exit;
}

$public_key = $data['public_key'];
$short_public_key = strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
$signature = base64_decode($data['signature'], true);
$message = $data['message'];
$current_time = date('Y-m-d H:i:s');

log_message("Received POST: public_key=$short_public_key, message=$message, IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
log_message("Signature (base64): {$data['signature']}", 'accounts.log', 'accounts', 'DEBUG');

if ($signature === false || strlen($signature) !== 64) {
    log_message("Invalid signature: Base64 decode failed or length incorrect (length: " . ($signature === false ? 'decode failed' : strlen($signature)) . "), IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
    exit;
}
log_message("Signature decoded length: " . strlen($signature) . " bytes", 'accounts.log', 'accounts', 'DEBUG');

try {
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

    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        throw new Exception("Sodium library not installed!");
    }
    log_message("Sodium library ready", 'accounts.log', 'accounts', 'INFO');

    if (!class_exists('\StephenHill\Base58')) {
        throw new Exception("stephenhill/base58 library not installed!");
    }
    if (!extension_loaded('bcmath') && !extension_loaded('gmp')) {
        throw new Exception("Please install the BC Math or GMP extension");
    }
    log_message("Base58 library ready", 'accounts.log', 'accounts', 'INFO');

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
        throw new Exception("Public key decoding error: " . $e->getMessage());
    }

    $message_raw = $message;
    log_message("Message hex: " . bin2hex($message_raw), 'accounts.log', 'accounts', 'DEBUG');
    log_message("Signature hex: " . bin2hex($signature), 'accounts.log', 'accounts', 'DEBUG');

    $start_time = microtime(true);
    try {
        if (!is_string($message_raw) || empty($message_raw)) {
            throw new Exception("Invalid message: Empty or not a string");
        }
        if (!is_string($public_key_bytes) || strlen($public_key_bytes) !== 32) {
            throw new Exception("Invalid public key: Length is " . strlen($public_key_bytes) . " bytes, expected 32 bytes");
        }
        if (!is_string($signature) || strlen($signature) !== 64) {
            throw new Exception("Invalid signature: Length is " . strlen($signature) . " bytes, expected 64 bytes");
        }

        $verified = sodium_crypto_sign_verify_detached($signature, $message_raw, $public_key_bytes);
        $duration = (microtime(true) - $start_time) * 1000;

        if (!$verified) {
            $errors = [];
            if (bin2hex($message_raw) !== bin2hex($message)) {
                $errors[] = "Message encoding mismatch";
            }
            if (strlen($public_key_bytes) !== 32) {
                $errors[] = "Invalid public key length";
            }
            if (strlen($signature) !== 64) {
                $errors[] = "Invalid signature length";
            }
            $error_message = "Signature verification failed: " . (empty($errors) ? "Signature does not match, please reconnect wallet" : implode(", ", $errors));
            throw new Exception($error_message);
        }
        log_message("Signature verified successfully (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
    } catch (Exception $e) {
        $duration = (microtime(true) - $start_time) * 1000;
        log_message("Signature verification error: {$e->getMessage()} (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
        throw $e;
    }

    unset($_SESSION['login_nonce']);

    $start_time = microtime(true);
    try {
        $stmt = $pdo->prepare("SELECT id, public_key, role, is_active, created_at, previous_login, last_login FROM accounts WHERE public_key = ?");
        $stmt->execute([$public_key]);
        $account = $stmt->fetch();
        $duration = (microtime(true) - $start_time) * 1000;
        log_message("Account check query: public_key=$short_public_key (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
    } catch (PDOException $e) {
        throw new Exception("Database query error: " . $e->getMessage());
    }

    $old_session_id = session_id();
    session_regenerate_id(true);
    $new_session_id = session_id();
    log_message("Session ID regenerated: old=$old_session_id, new=$new_session_id, public_key=$short_public_key", 'accounts.log', 'accounts', 'INFO');

    $redirect_url = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '/acc/profile';
    unset($_SESSION['redirect_url']);

    if ($account) {
        if (!$account['is_active']) {
            log_message("Login failed: Account disabled, public_key=$short_public_key, IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
            echo json_encode(['status' => 'error', 'message' => 'Account is disabled']);
            exit;
        }
        $start_time = microtime(true);
        $stmt = $pdo->prepare("UPDATE accounts SET previous_login = last_login, last_login = ? WHERE public_key = ?");
        $stmt->execute([$current_time, $public_key]);
        $duration = (microtime(true) - $start_time) * 1000;
        log_message("Login successful: public_key=$short_public_key, role={$account['role']}, id={$account['id']} (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
        $_SESSION['public_key'] = $public_key;
        $_SESSION['role'] = $account['role'];
        echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'redirect' => $redirect_url]);
    } else {
        $start_time = microtime(true);
        $stmt = $pdo->prepare("INSERT INTO accounts (public_key, role, is_active, created_at, last_login) VALUES (?, 'member', TRUE, ?, ?)");
        $stmt->execute([$public_key, $current_time, $current_time]);
        $duration = (microtime(true) - $start_time) * 1000;
        log_message("Registration successful: public_key=$short_public_key, role=member, id=$new_id (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
        $_SESSION['public_key'] = $public_key;
        $_SESSION['role'] = 'member';
        echo json_encode(['status' => 'success', 'message' => 'Registration successful!', 'redirect' => $redirect_url]);
    }
} catch (Exception $e) {
    log_message("Error: {$e->getMessage()}, IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>
