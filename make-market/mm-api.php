<?php
// API xử lý các vòng giao dịch mua – bán token theo cơ chế lặp lại (auto loop) trên DEX Solana

require_once '../tools-api.php';
require_once '../vendor/autoload.php'; // Load phpseclib

use phpseclib3\Crypt\AES;

// Khóa bí mật (phải khớp với client)
$SECRET_KEY = 'your-secure-secret-key-123'; // Thay bằng khóa an toàn

$encryptedPrivateKey = $_POST['privateKey'] ?? '';
$tokenMint = $_POST['mint'] ?? '';
$solAmount = floatval($_POST['sol']) ?: 0;
$rounds = intval($_POST['rounds']) ?: 1;

header('Content-Type: application/json');

if (!$encryptedPrivateKey || !$tokenMint || !$solAmount || $rounds < 1) {
    echo json_encode(['error' => 'Thiếu tham số']);
    exit;
}

// Giải mã private key
$aes = new AES('cbc'); // Chế độ CBC cho AES
$aes->setKey($SECRET_KEY);
// Giả sử IV (Initialization Vector) được gửi kèm hoặc cố định (cần đồng bộ với client)
$aes->setIV(substr(hash('sha256', $SECRET_KEY), 0, 16)); // Tạo IV từ SECRET_KEY
$wallet = $aes->decrypt(base64_decode($encryptedPrivateKey));

if (!$wallet) {
    echo json_encode(['error' => 'Không thể giải mã private key']);
    exit;
}

$rpc = new HeliusRPC();
$results = [];

for ($i = 1; $i <= $rounds; $i++) {
    // Kiểm tra số dư SOL trước khi mua
    $balanceLamports = $rpc->getBalance($wallet);
    $neededLamports = intval($solAmount * 1e9) + 10000; // thêm phí
    if ($balanceLamports < $neededLamports) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Không đủ SOL để mua (cần ~{$solAmount} SOL + phí)",
            'results' => $results
        ]);
        exit;
    }

    // Gửi lệnh mua
    $route = $rpc->getSwapRoute($wallet, 'So11111111111111111111111111111111111111112', $tokenMint, $solAmount);
    if (!$route) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Không lấy được route mua",
            'results' => $results
        ]);
        exit;
    }

    $tx = $rpc->buildAndSignSwap($wallet, $route);
    $buyTxSig = $rpc->sendRawTransaction($tx);
    if (!$buyTxSig) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Giao dịch mua thất bại",
            'results' => $results
        ]);
        exit;
    }

    sleep(2); // đợi blockchain ghi nhận

    // Kiểm tra số dư token để bán
    $tokenAccounts = $rpc->getTokenAccountsByOwner($wallet, $tokenMint);
    $tokenAmount = 0;
    foreach ($tokenAccounts as $acc) {
        $info = $acc['account']['data']['parsed']['info']['tokenAmount'];
        $tokenAmount += floatval($info['uiAmount']);
    }

    if ($tokenAmount <= 0) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Không đủ token để bán sau khi mua",
            'results' => $results
        ]);
        exit;
    }

    // Gửi lệnh bán
    $routeSell = $rpc->getSwapRoute($wallet, $tokenMint, 'So11111111111111111111111111111111111111112', $tokenAmount);
    if (!$routeSell) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Không lấy được route bán",
            'results' => $results
        ]);
        exit;
    }

    $txSell = $rpc->buildAndSignSwap($wallet, $routeSell);
    $sellTxSig = $rpc->sendRawTransaction($txSell);
    if (!$sellTxSig) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Giao dịch bán thất bại",
            'results' => $results
        ]);
        exit;
    }

    $results[] = [
        'round' => $i,
        'buyTx' => $buyTxSig,
        'sellTx' => $sellTxSig
    ];

    sleep(2); // đợi trước vòng tiếp theo
}

echo json_encode([
    'message' => "✅ Đã hoàn tất $rounds vòng giao dịch",
    'results' => $results
]);
