<?php
// ============================================================================
// File: accounts/auth.php
// Description: API handles Solana wallet signature verification with rate limiting and session regeneration.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    die("Access denied: Direct access to this file is not allowed.");
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use StephenHill\Base58;

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

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
    log_message("Database connection successful (took {$duration}ms)", 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Kết nối cơ sở dữ liệu thất bại']);
    exit;
}

// Rate limiting
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if ($ip_address === 'Unknown') {
    log_message("Failed to retrieve IP address", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Không thể xử lý yêu cầu: Địa chỉ IP không hợp lệ']);
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
        echo json_encode(['status' => 'error', 'message' => 'Quá nhiều lần thử đăng nhập. Vui lòng đợi 1 phút và thử lại.']);
        exit;
    }

    // Log the current attempt
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, ?)");
    $stmt->execute([$ip_address, date('Y-m-d H:i:s')]);
    log_message("Recorded login attempt for IP: $ip_address, attempt count: " . ($attempt_count + 1), 'accounts.log', 'accounts', 'INFO');
} catch (PDOException $e) {
    log_message("Rate limiting error for IP: $ip_address, SQL Error: {$e->getMessage()}, Code: {$e->getCode()}", 'accounts.log', 'accounts', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Lỗi giới hạn tần suất. Vui lòng thử lại sau.']);
    exit;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_key'], $_POST['signature'], $_POST['message'], $_POST['csrf_token'])) {
    header('Content-Type: application/json');

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'])) {
        log_message("Invalid CSRF token for login attempt from IP: $ip_address", 'accounts.log', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Mã CSRF không hợp lệ']);
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
        echo json_encode(['status' => 'error', 'message' => 'Giải mã Base64 thất bại hoặc chữ ký không hợp lệ']);
        exit;
    }
    log_message("Signature decoded length: " . strlen($signature) . " bytes", 'accounts.log', 'accounts', 'DEBUG');

    try {
        // Check timestamp
        if (!preg_match('/at (\d+)/', $message, $matches)) {
            throw new Exception("Tin nhắn không chứa dấu thời gian!");
        }
        $timestamp = $matches[1];
        $current_timestamp = time() * 1000;
        if (abs($current_timestamp - $timestamp) > 300000) {
            throw new Exception("Tin nhắn đã hết hạn!");
        }
        log_message("Valid timestamp: $timestamp, IP=$ip_address", 'accounts.log', 'accounts', 'INFO');

        // Check sodium library
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new Exception("Thư viện Sodium chưa được cài đặt!");
        }
        log_message("Sodium library ready", 'accounts.log', 'accounts', 'INFO');

        // Check stephenhill/base58 and extensions
        if (!class_exists('\StephenHill\Base58')) {
            throw new Exception("Thư viện stephenhill/base58 chưa được cài đặt!");
        }
        if (!extension_loaded('bcmath') && !extension_loaded('gmp')) {
            throw new Exception("Vui lòng cài đặt phần mở rộng BC Math hoặc GMP");
        }
        log_message("Base58 library ready", 'accounts.log', 'accounts', 'INFO');

        // Decode public_key
        $start_time = microtime(true);
        try {
            $bs58 = new Base58();
            $public_key_bytes = $bs58->decode($public_key);
            if (strlen($public_key_bytes) !== 32) {
                throw new Exception("Khóa công khai không hợp lệ: Độ dài là " . strlen($public_key_bytes) . " bytes, cần 32 bytes");
            }
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Public key decoded: $short_public_key (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
            log_message("Public key hex: " . bin2hex($public_key_bytes), 'accounts.log', 'accounts', 'DEBUG');
        } catch (Exception $e) {
            throw new Exception("Lỗi giải mã khóa công khai: " . $e->getMessage());
        }

        // Use raw message directly
        $message_raw = $message;
        log_message("Message hex: " . bin2hex($message_raw), 'accounts.log', 'accounts', 'DEBUG');
        log_message("Signature hex: " . bin2hex($signature), 'accounts.log', 'accounts', 'DEBUG');

        // Verify signature
        $start_time = microtime(true);
        try {
            if (!is_string($message_raw) || empty($message_raw)) {
                throw new Exception("Tin nhắn không hợp lệ: Trống hoặc không phải chuỗi");
            }
            if (!is_string($public_key_bytes) || strlen($public_key_bytes) !== 32) {
                throw new Exception("Khóa công khai không hợp lệ: Độ dài là " . strlen($public_key_bytes) . " bytes, cần 32 bytes");
            }
            if (!is_string($signature) || strlen($signature) !== 64) {
                throw new Exception("Chữ ký không hợp lệ: Độ dài là " . strlen($signature) . " bytes, cần 64 bytes");
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
                    $errors[] = "Mã hóa tin nhắn không khớp";
                }
                if (strlen($public_key_bytes) !== 32) {
                    $errors[] = "Độ dài khóa công khai không hợp lệ";
                }
                if (strlen($signature) !== 64) {
                    $errors[] = "Độ dài chữ ký không hợp lệ";
                }
                $error_message = "Xác thực chữ ký thất bại: " . (empty($errors) ? "Chữ ký không khớp, vui lòng thử kết nối lại ví" : implode(", ", $errors));
                throw new Exception($error_message);
            }
            log_message("Signature verified successfully (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
        } catch (Exception $e) {
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Signature verification error: {$e->getMessage()} (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
            throw $e;
        }

        // Check and save to database
        $start_time = microtime(true);
        try {
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE public_key = ?");
            $stmt->execute([$public_key]);
            $account = $stmt->fetch();
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Account check query: public_key=$short_public_key (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
        } catch (PDOException $e) {
            throw new Exception("Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage());
        }

        // Regenerate session ID to prevent session fixation
        $old_session_id = session_id();
        session_regenerate_id(true);
        $new_session_id = session_id();
        log_message("Session ID regenerated: old=$old_session_id, new=$new_session_id, public_key=$short_public_key", 'accounts.log', 'accounts', 'INFO');

        if ($account) {
            $start_time = microtime(true);
            $stmt = $pdo->prepare("UPDATE accounts SET last_login = ? WHERE public_key = ?");
            $stmt->execute([$current_time, $public_key]);
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Đăng nhập thành công: public_key=$short_public_key (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
            $_SESSION['public_key'] = $public_key;
            echo json_encode(['status' => 'success', 'message' => 'Đăng nhập thành công!', 'redirect' => '/accounts/profile.php']);
        } else {
            $start_time = microtime(true);
            $stmt = $pdo->prepare("INSERT INTO accounts (public_key, created_at, last_login) VALUES (?, ?, ?)");
            $stmt->execute([$public_key, $current_time, $current_time]);
            $duration = (microtime(true) - $start_time) * 1000;
            log_message("Đăng ký thành công: public_key=$short_public_key (took {$duration}ms), IP=$ip_address", 'accounts.log', 'accounts', 'INFO');
            $_SESSION['public_key'] = $public_key;
            echo json_encode(['status' => 'success', 'message' => 'Đăng ký thành công!', 'redirect' => '/accounts/profile.php']);
        }
    } catch (Exception $e) {
        log_message("Lỗi: {$e->getMessage()}, IP=$ip_address", 'accounts.log', 'accounts', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}
?>
