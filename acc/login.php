<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wallet'])) {
    $wallet = trim($_POST['wallet']);

    // Validate địa chỉ ví Solana
    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $wallet)) {
        echo 'fail';
        exit;
    }

    $userDir = __DIR__ . '/users';
    if (!is_dir($userDir)) mkdir($userDir);

    $userFile = $userDir . '/' . $wallet . '.json';
    $now = date('Y-m-d H:i:s');

    if (!file_exists($userFile)) {
        // Tạo tài khoản mới
        $userData = [
            'id' => bin2hex(random_bytes(6)), // id ngắn 12 ký tự
            'public_key' => $wallet,
            'created_at' => $now,
            'last_login' => $now
        ];
    } else {
        // Cập nhật lần đăng nhập
        $userData = json_decode(file_get_contents($userFile), true);
        $userData['last_login'] = $now;
    }

    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $_SESSION['wallet'] = $wallet;
    echo 'ok';
    exit;
}

echo 'fail';
