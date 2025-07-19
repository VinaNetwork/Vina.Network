<?php
require_once '../vendor/autoload.php';
use phpseclib3\Crypt\AES;
use Dotenv\Dotenv;

// Load biến môi trường
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
$SECRET_KEY = $_ENV['SECRET_KEY'];

$encryptedPrivateKey = $_POST['privateKey'] ?? '';
$iv = $_POST['iv'] ?? '';
$tokenMint = $_POST['mint'] ?? '';
$solAmount = floatval($_POST['sol']) ?: 0;
$rounds = intval($_POST['rounds']) ?: 1;

header('Content-Type: application/json');

if (!$encryptedPrivateKey || !$iv || !$tokenMint || !$solAmount || $rounds < 1) {
    echo json_encode(['error' => 'Thiếu tham số']);
    exit;
}

// Giải mã private key
$aes = new AES('cbc');
$aes->setKey($SECRET_KEY);
$aes->setIV(base64_decode($iv));
$wallet = $aes->decrypt(base64_decode($encryptedPrivateKey));

if (!$wallet) {
    echo json_encode(['error' => 'Không thể giải mã private key']);
    exit;
}

$rpc = new HeliusRPC();
$results = [];

for ($i = 1; $i <= $rounds; $i++) {
    $balanceLamports = $rpc->getBalance($wallet);
    $neededLamports = intval($solAmount * 1e9) + 10000;
    if ($balanceLamports < $neededLamports) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Không đủ SOL để mua (cần ~{$solAmount} SOL + phí)",
            'results' => $results
        ]);
        exit;
    }

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

    sleep(2);

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

    $routeSell = $rpc->getSwapRoute($wallet, $tokenMint, 'So11111111111111111111111111111111111111112', $tokenAmount);
    if (!$route83Sell) {
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

    sleep(2);
}

echo json_encode([
    'message' => "✅ Đã hoàn tất $rounds vòng giao dịch",
    'results' => $results
]);
