<?php
// ============================================================================
// File: make-market/mm-api.php
// Description:
// Created by: Vina Network
// ============================================================================

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
$slippage = floatval($_POST['slippage']) ?: 1.0; // Lấy slippage từ form

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

// Hàm kiểm tra trạng thái giao dịch với retry
function waitForConfirmation($rpc, $txSig, $maxAttempts = 30, $interval = 1, $maxRetries = 3) {
    for ($retry = 1; $retry <= $maxRetries; $retry++) {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $status = $rpc->getSignatureStatuses([$txSig]);
            $confirmationStatus = $status['value'][0]['confirmationStatus'] ?? null;
            $err = $status['value'][0]['err'] ?? null;

            if ($err) {
                // Xử lý lỗi cụ thể
                $errorMessage = json_encode($err);
                if (strpos($errorMessage, 'insufficient liquidity') !== false) {
                    return ['confirmed' => false, 'error' => 'Không đủ thanh khoản trong pool'];
                }
                if ($retry < $maxRetries) {
                    return ['confirmed' => false, 'error' => "Thử lại lần $retry/$maxRetries: $errorMessage", 'retry' => true];
                }
                return ['confirmed' => false, 'error' => "Giao dịch thất bại sau $maxRetries lần thử: $errorMessage"];
            }
            if ($confirmationStatus === 'confirmed' || $confirmationStatus === 'finalized') {
                return ['confirmed' => true, 'error' => null];
            }
            sleep($interval); // Chờ trước khi kiểm tra lại
        }
    }
    return ['confirmed' => false, 'error' => 'Hết thời gian chờ xác nhận giao dịch'];
}

for ($i = 1; $i <= $rounds; $i++) {
    // Kiểm tra số dư SOL
    $balanceLamports = $rpc->getBalance($wallet);
    $neededLamports = intval($solAmount * 1e9) + 10000;
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

    // Kiểm tra slippage
    $expectedPrice = $route['expectedPrice'] ?? 0; // Giả định route trả về giá dự kiến
    $maxPrice = $expectedPrice * (1 + $slippage / 100); // Giá tối đa chấp nhận được
    $actualPrice = $route['actualPrice'] ?? $expectedPrice; // Giả định giá thực tế
    if ($actualPrice > $maxPrice) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Slippage quá cao (giá thực tế: $actualPrice, tối đa: $maxPrice)",
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

    // Chờ xác nhận giao dịch mua với retry
    $buyConfirmation = waitForConfirmation($rpc, $buyTxSig);
    if (!$buyConfirmation['confirmed']) {
        if ($buyConfirmation['retry'] ?? false) {
            // Thử lại giao dịch mua
            $tx = $rpc->buildAndSignSwap($wallet, $route);
            $buyTxSig = $rpc->sendRawTransaction($tx);
            if ($buyTxSig) {
                $buyConfirmation = waitForConfirmation($rpc, $buyTxSig);
            }
        }
        if (!$buyConfirmation['confirmed']) {
            echo json_encode([
                'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $buyConfirmation['error'],
                'results' => $results
            ]);
            exit;
        }
    }

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

    // Kiểm tra slippage cho bán
    $expectedSellPrice = $routeSell['expectedPrice'] ?? 0;
    $minSellPrice = $expectedSellPrice * (1 - $slippage / 100); // Giá tối thiểu chấp nhận được
    $actualSellPrice = $routeSell['actualPrice'] ?? $expectedSellPrice;
    if ($actualSellPrice < $minSellPrice) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Slippage quá cao (giá thực tế: $actualSellPrice, tối thiểu: $minSellPrice)",
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

    // Chờ xác nhận giao dịch bán với retry
    $sellConfirmation = waitForConfirmation($rpc, $sellTxSig);
    if (!$sellConfirmation['confirmed']) {
        if ($sellConfirmation['retry'] ?? false) {
            // Thử lại giao dịch bán
            $txSell = $rpc->buildAndSignSwap($wallet, $routeSell);
            $sellTxSig = $rpc->sendRawTransaction($txSell);
            if ($sellTxSig) {
                $sellConfirmation = waitForConfirmation($rpc, $sellTxSig);
            }
        }
        if (!$sellConfirmation['confirmed']) {
            echo json_encode([
                'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $sellConfirmation['error'],
                'results' => $results
            ]);
            exit;
        }
    }

    $results[] = [
        'round' => $i,
        'buyTx' => $buyTxSig,
        'sellTx' => $sellTxSig
    ];
}

echo json_encode([
    'message' => "✅ Đã hoàn tất $rounds vòng giao dịch",
    'results' => $results
]);
