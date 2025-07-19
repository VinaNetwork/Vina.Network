<?php
// ============================================================================
// File: make-market/mm-api.php
// Description: Make Market API with Jupiter Swap V6 integration and JWT authentication
// Created by: Vina Network
// ============================================================================

require_once './vendor/autoload.php';
use Dotenv\Dotenv;
use VinaNetwork\JwtAuth;
use VinaNetwork\Swap; // Đổi từ JupiterSwap
use VinaNetwork\TransactionStatus;
use phpseclib3\Crypt\AES;

header('Content-Type: application/json');

// Load biến môi trường
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
$SECRET_KEY = $_ENV['SECRET_KEY'];
$RPC_ENDPOINT = $_ENV['RPC_ENDPOINT'] ?? 'https://api.mainnet-beta.solana.com';
$JWT_SECRET = $_ENV['JWT_SECRET'] ?? '';

// Kiểm tra JWT
$jwtAuth = new JwtAuth($JWT_SECRET);
$authResult = $jwtAuth->validateToken($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!$authResult['valid']) {
    http_response_code(401);
    echo json_encode(['error' => $authResult['error']]);
    exit;
}

// Khởi tạo TransactionStatus và Swap
$transactionStatus = new TransactionStatus();
$encryptedPrivateKey = $_POST['privateKey'] ?? '';
$iv = $_POST['iv'] ?? '';
$tokenMint = $_POST['mint'] ?? '';
$solAmount = floatval($_POST['sol']) ?: 0;
$rounds = intval($_POST['rounds']) ?: 1;
$slippage = floatval($_POST['slippage']) ?: 1.0;
$slippageBps = intval($slippage * 100);
$processId = $_POST['processName'] ?? 'default_process_' . uniqid();

if (!$encryptedPrivateKey || !$iv || !$tokenMint || !$solAmount || $rounds < 1) {
    echo json_encode(['error' => 'Thiếu tham số']);
    exit;
}

// Giải mã private key
$aes = new AES('cbc');
$aes->setKey($SECRET_KEY);
$aes->setIV(base64_decode($iv));
$walletPrivateKey = $aes->decrypt(base64_decode($encryptedPrivateKey));

if (!$walletPrivateKey) {
    echo json_encode(['error' => 'Không thể giải mã private key']);
    exit;
}

// Khởi tạo Swap
$jupiterSwap = new Swap($RPC_ENDPOINT, $walletPrivateKey, $transactionStatus);

$results = [];
$transactionStatus->sendStatus($processId, "Bắt đầu Make Market với $rounds vòng...");

for ($i = 1; $i <= $rounds; $i++) {
    $transactionStatus->sendStatus($processId, "Chuẩn bị vòng $i...");

    // Kiểm tra số dư SOL
    $balanceLamports = $jupiterSwap->getBalance();
    $neededLamports = intval($solAmount * 1e9) + 10000;
    if ($balanceLamports < $neededLamports) {
        $transactionStatus->sendStatus($processId, "Lỗi vòng $i: Không đủ SOL để mua (cần ~{$solAmount} SOL + phí)");
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Không đủ SOL để mua (cần ~{$solAmount} SOL + phí)",
            'results' => $results,
            'success' => false
        ]);
        exit;
    }

    // Gửi lệnh mua (SOL -> Token)
    $quote = $jupiterSwap->getQuote('So11111111111111111111111111111111111111112', $tokenMint, intval($solAmount * 1e9), $slippageBps);
    if (isset($quote['error'])) {
        $transactionStatus->sendStatus($processId, "Lỗi vòng $i: " . $quote['error']);
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $quote['error'],
            'results' => $results,
            'success' => false
        ]);
        exit;
    }

    // Kiểm tra price impact (slippage)
    $priceImpact = floatval($quote['priceImpactPc'] ?? 0);
    if ($priceImpact > $slippage) {
        $transactionStatus->sendStatus($processId, "Lỗi vòng $i: Slippage quá cao (price impact: $priceImpact%, tối đa: $slippage%)");
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Slippage quá cao (price impact: $priceImpact%, tối đa: $slippage%)",
            'results' => $results,
            'success' => false
        ]);
        exit;
    }

    $swapResult = $jupiterSwap->executeSwap($quote, $processId, 'mua', $i);
    if ($swapResult['error']) {
        $transactionStatus->sendStatus($processId, "Lỗi vòng $i: " . $swapResult['error']);
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $swapResult['error'],
            'results' => $results,
            'success' => false
        ]);
        exit;
    }
    $buyTxSig = $swapResult['txSig'];

    // Chờ xác nhận giao dịch mua
    $buyConfirmation = $jupiterSwap->waitForConfirmation($buyTxSig, $processId, 'mua', $i);
    if (!$buyConfirmation['confirmed']) {
        if ($buyConfirmation['retry'] ?? false) {
            $transactionStatus->sendStatus($processId, "Thử lại mua vòng $i...");
            $swapResult = $jupiterSwap->executeSwap($quote, $processId, 'mua', $i);
            if ($swapResult['txSig']) {
                $buyTxSig = $swapResult['txSig'];
                $buyConfirmation = $jupiterSwap->waitForConfirmation($buyTxSig, $processId, 'mua', $i);
            }
        }
        if (!$buyConfirmation['confirmed']) {


            $transactionStatus->sendStatus($processId, "Lỗi vòng $i: " . $buyConfirmation['error']);
            echo json_encode([
                'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $buyConfirmation['error'],
                'results' => $results,
                'success' => false
            ]);
            exit;
        }
    }
    $transactionStatus->sendStatus($processId, "Mua vòng $i hoàn tất!");

    // Kiểm tra số dư token để bán
    $tokenAccounts = $jupiterSwap->getTokenAccounts($tokenMint);
    $tokenAmount = 0;
    foreach ($tokenAccounts['value'] as $acc) {
        $info = $acc['account']['data']['parsed']['info']['tokenAmount'];
        $tokenAmount += floatval($info['uiAmount']);
    }

    if ($tokenAmount <= 0) {
        $transactionStatus->sendStatus($processId, "Lỗi vòng $i: Không đủ token để bán sau khi mua");
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Không đủ token để bán sau khi mua",
            'results' => $results,
            'success' => false
        ]);
        exit;
    }

    // Gửi lệnh bán (Token -> SOL)
    $quoteSell = $jupiterSwap->getQuote($tokenMint, 'So11111111111111111111111111111111111111112', intval($tokenAmount * 1e9), $slippageBps);
    if (isset($quoteSell['error'])) {
        $transactionStatus->sendStatus($processId, "Lỗi vòng $i: " . $quoteSell['error']);
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $quoteSell['error'],
            'results' => $results,
            'success' => false
        ]);
        exit;
    }

    // Kiểm tra price impact (slippage) cho bán
    $priceImpactSell = floatval($quoteSell['priceImpactPc'] ?? 0);
    if ($priceImpactSell > $slippage) {
        $transactionStatus->sendStatus($processId, "Lỗi vòng $i: Slippage quá cao (price impact: $priceImpactSell%, tối đa: $slippage%)");
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Slippage quá cao (price impact: $priceImpactSell%, tối đa: $slippage%)",
            'results' => $results,
            'success' => false
        ]);
        exit;
    }

    $swapSellResult = $jupiterSwap->executeSwap($quoteSell, $processId, 'bán', $i);
    if ($swapSellResult['error']) {
        $transactionStatus->sendStatus($processId, "Lỗi vòng $i: " . $swapSellResult['error']);
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $swapSellResult['error'],
            'results' => $results,
            'success' => false
        ]);
        exit;
    }
    $sellTxSig = $swapSellResult['txSig'];

    // Chờ xác nhận giao dịch bán
    $sellConfirmation = $jupiterSwap->waitForConfirmation($sellTxSig, $processId, 'bán', $i);
    if (!$sellConfirmation['confirmed']) {
        if ($sellConfirmation['retry'] ?? false) {
            $transactionStatus->sendStatus($processId, "Thử lại bán vòng $i...");
            $swapSellResult = $jupiterSwap->executeSwap($quoteSell, $processId, 'bán', $i);
            if ($swapSellResult['txSig']) {
                $sellTxSig = $swapSellResult['txSig'];
                $sellConfirmation = $jupiterSwap->waitForConfirmation($sellTxSig, $processId, 'bán', $i);
            }
        }
        if (!$sellConfirmation['confirmed']) {
            $transactionStatus->sendStatus($processId, "Lỗi vòng $i: " . $sellConfirmation['error']);
            echo json_encode([
                'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $sellConfirmation['error'],
                'results' => $results,
                'success' => false
            ]);
            exit;
        }
    }
    $transactionStatus->sendStatus($processId, "Bán vòng $i hoàn tất!");

    $results[] = [
        'round' => $i,
        'buyTx' => $buyTxSig,
        'sellTx' => $sellTxSig
    ];
}

$transactionStatus->sendStatus($processId, "✅ Đã hoàn tất $rounds vòng giao dịch");
echo json_encode([
    'message' => "✅ Đã hoàn tất $rounds vòng giao dịch",
    'results' => $results,
    'success' => true
]);
