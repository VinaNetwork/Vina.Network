<?php
file_put_contents('debug.txt', print_r($_POST, true));

// Nhận dữ liệu từ client
$wallet = $_POST['wallet'] ?? '';
$message = $_POST['message'] ?? '';
$signatureRaw = $_POST['signature'] ?? '';

// Ghi log nếu cần debug
error_log("LOGIN: wallet=$wallet");

// Kiểm tra đầu vào
if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $wallet)) {
    error_log("Invalid wallet: $wallet");
    echo 'invalid wallet';
    exit;
}

if (!$signatureRaw || strlen($signatureRaw) < 64) {
    error_log("Invalid signature length: " . strlen($signatureRaw));
    echo 'invalid signature';
    exit;
}

// Hàm xác minh chữ ký
function verify_sol_signature($wallet, $message, $signatureBase64) {
    if (!function_exists('sodium_crypto_sign_verify_detached')) return false;

    $pubkey = base58_decode($wallet);
    $signature = base64_decode($signatureBase64);
    $msg = $message;

    if ($pubkey === false || $signature === false) return false;
    return sodium_crypto_sign_verify_detached($signature, $msg, $pubkey);
}

// Hàm decode base58 Solana
function base58_decode($input) {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $decoded = gmp_init(0, 10);
    $base = strlen($alphabet);

    for ($i = 0; $i < strlen($input); $i++) {
        $char = $input[$i];
        $pos = strpos($alphabet, $char);
        if ($pos === false) return false;
        $decoded = gmp_add(gmp_mul($decoded, $base), $pos);
    }

    $bytes = gmp_export($decoded);
    return $bytes !== false ? $bytes : false;
}

// Xác minh
if (!verify_sol_signature($wallet, $message, $signatureRaw)) {
    error_log("Signature verification failed");
    echo 'invalid signature';
    exit;
}

// Lưu tài khoản
$dir = __DIR__ . '/datas';
if (!is_dir($dir)) mkdir($dir, 0777, true);

$id = hash('sha256', $wallet);
$filepath = "$dir/$id.json";
$now = date('c');

if (file_exists($filepath)) {
    // Cập nhật last login
    $data = json_decode(file_get_contents($filepath), true);
    $data['last_login'] = $now;
} else {
    // Tạo mới
    $data = [
        'id' => $id,
        'public_key' => $wallet,
        'created_at' => $now,
        'last_login' => $now
    ];
}

file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));

echo 'ok';
