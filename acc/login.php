<?php
session_start();

function base58_decode($base58) {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $indexes = array_flip(str_split($alphabet));
    $size = strlen($base58);
    $intVal = gmp_init(0);

    for ($i = 0; $i < $size; ++$i) {
        $intVal = gmp_add(gmp_mul($intVal, 58), $indexes[$base58[$i]]);
    }

    $hex = gmp_strval($intVal, 16);
    if (strlen($hex) % 2 !== 0) $hex = '0' . $hex;
    $bin = hex2bin($hex);

    $pad = 0;
    for ($i = 0; $i < $size && $base58[$i] === '1'; $i++) $pad++;
    return str_repeat("\x00", $pad) . $bin;
}

function verify_sol_signature($publicKeyBase58, $message, $signatureRaw) {
    $pubKeyRaw = base58_decode($publicKeyBase58);
    if (!$pubKeyRaw || strlen($pubKeyRaw) !== 32 || strlen($signatureRaw) !== 64) {
        return false;
    }
    return sodium_crypto_sign_verify_detached($signatureRaw, $message, $pubKeyRaw);
}

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  isset($_POST['wallet'], $_POST['message'], $_POST['signature'])
) {
    $wallet = trim($_POST['wallet']);
    $message = $_POST['message'];
    $signatureRaw = base64_decode($_POST['signature']);

    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $wallet)) {
        echo 'invalid wallet';
        exit;
    }

    if (!$signatureRaw || strlen($signatureRaw) !== 64) {
        echo 'invalid signature';
        exit;
    }

    if (!verify_sol_signature($wallet, $message, $signatureRaw)) {
        echo 'invalid signature';
        exit;
    }

    // Xác thực OK → tạo/lưu file JSON
    $dataDir = __DIR__ . '/datas';
    if (!is_dir($dataDir)) mkdir($dataDir);

    $userFile = $dataDir . '/' . $wallet . '.json';
    $now = date('Y-m-d H:i:s');

    if (!file_exists($userFile)) {
        $userData = [
            'id' => bin2hex(random_bytes(6)),
            'public_key' => $wallet,
            'created_at' => $now,
            'last_login' => $now
        ];
    } else {
        $userData = json_decode(file_get_contents($userFile), true);
        $userData['last_login'] = $now;
    }

    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $_SESSION['wallet'] = $wallet;
    echo 'ok';
    exit;
}

echo 'fail';
