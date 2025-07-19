<?php
// ============================================================================
// File: make-market/mm-api.php
// Description: Make Market API with Jupiter Swap V6 integration
// Created by: Vina Network
// ============================================================================

require_once '../vendor/autoload.php';
use phpseclib3\Crypt\AES;
use Dotenv\Dotenv;
use Solana\Web3\Connection;
use Solana\Web3\Keypair;
use Solana\Web3\VersionedTransaction;
use GuzzleHttp\Client;

// Load biến môi trường
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
$SECRET_KEY = $_ENV['SECRET_KEY'];
$RPC_ENDPOINT = $_ENV['RPC_ENDPOINT'] ?? 'https://api.mainnet-beta.solana.com'; // RPC Solana (Helius, QuickNode, v.v.)

$encryptedPrivateKey = $_POST['privateKey'] ?? '';
$iv = $_POST['iv'] ?? '';
$tokenMint = $_POST['mint'] ?? '';
$solAmount = floatval($_POST['sol']) ?: 0;
$rounds = intval($_POST['rounds']) ?: 1;
$slippage = floatval($_POST['slippage']) ?: 1.0; // Slippage từ form (%)
$slippageBps = intval($slippage * 100); // Chuyển % thành basis points (1% = 100 bps)

header('Content-Type: application/json');

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

// Khởi tạo ví và kết nối RPC
$keypair = Keypair::fromSecretKey(base64_decode($walletPrivateKey)); // Giả định private key là Base58 đã mã hóa
$connection = new Connection($RPC_ENDPOINT);
$httpClient = new Client();

// Hàm kiểm tra trạng thái giao dịch với retry
function waitForConfirmation($connection, $txSig, $maxAttempts = 30, $interval = 1, $maxRetries = 3) {
    for ($retry = 1; $retry <= $maxRetries; $retry++) {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $status = $connection->getSignatureStatuses([$txSig]);
            $confirmationStatus = $status['value'][0]['confirmationStatus'] ?? null;
            $err = $status['value'][0]['err'] ?? null;

            if ($err) {
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
            sleep($interval);
        }
    }
    return ['confirmed' => false, 'error' => 'Hết thời gian chờ xác nhận giao dịch'];
}

// Hàm lấy quote từ Jupiter API
function getJupiterQuote($httpClient, $inputMint, $outputMint, $amount, $slippageBps) {
    try {
        $response = $httpClient->get('https://quote-api.jup.ag/v6/quote', [
            'query' => [
                'inputMint' => $inputMint,
                'outputMint' => $outputMint,
                'amount' => $amount,
                'slippageBps' => $slippageBps
            ]
        ]);
        return json_decode($response->getBody(), true);
    } catch (\Exception $e) {
        return ['error' => 'Không lấy được route từ Jupiter: ' . $e->getMessage()];
    }
}

// Hàm tạo và gửi giao dịch swap qua Jupiter API
function executeJupiterSwap($httpClient, $connection, $keypair, $quoteResponse) {
    try {
        $response = $httpClient->post('https://quote-api.jup.ag/v6/swap', [
            'json' => [
                'userPublicKey' => $keypair->publicKey()->toString(),
                'quoteResponse' => $quoteResponse,
                'wrapAndUnwrapSol' => true
            ]
        ]);
        $swapData = json_decode($response->getBody(), true);
        $transaction = VersionedTransaction::deserialize(base64_decode($swapData['swapTransaction']));
        
        // Ký giao dịch
        $transaction->sign([$keypair]);
        
        // Gửi giao dịch
        $txSig = $connection->sendRawTransaction($transaction->serialize());
        return ['txSig' => $txSig, 'error' => null];
    } catch (\Exception $e) {
        return ['txSig' => null, 'error' => 'Lỗi khi tạo/gửi giao dịch: ' . $e->getMessage()];
    }
}

$results = [];

for ($i = 1; $i <= $rounds; $i++) {
    // Kiểm tra số dư SOL
    $balanceLamports = $connection->getBalance($keypair->publicKey());
    $neededLamports = intval($solAmount * 1e9) + 10000; // SOL + phí
    if ($balanceLamports < $neededLamports) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Không đủ SOL để mua (cần ~{$solAmount} SOL + phí)",
            'results' => $results
        ]);
        exit;
    }

    // Gửi lệnh mua (SOL -> Token)
    $quote = getJupiterQuote($httpClient, 'So11111111111111111111111111111111111111112', $tokenMint, intval($solAmount * 1e9), $slippageBps);
    if (isset($quote['error'])) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $quote['error'],
            'results' => $results
        ]);
        exit;
    }

    // Kiểm tra price impact (slippage)
    $priceImpact = floatval($quote['priceImpactPc'] ?? 0);
    if ($priceImpact > $slippage) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Slippage quá cao (price impact: $priceImpact%, tối đa: $slippage%)",
            'results' => $results
        ]);
        exit;
    }

    $swapResult = executeJupiterSwap($httpClient, $connection, $keypair, $quote);
    if ($swapResult['error']) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $swapResult['error'],
            'results' => $results
        ]);
        exit;
    }
    $buyTxSig = $swapResult['txSig'];

    // Chờ xác nhận giao dịch mua
    $buyConfirmation = waitForConfirmation($connection, $buyTxSig);
    if (!$buyConfirmation['confirmed']) {
        if ($buyConfirmation['retry'] ?? false) {
            // Thử lại giao dịch mua
            $swapResult = executeJupiterSwap($httpClient, $connection, $keypair, $quote);
            if ($swapResult['txSig']) {
                $buyTxSig = $swapResult['txSig'];
                $buyConfirmation = waitForConfirmation($connection, $buyTxSig);
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
    $tokenAccounts = $connection->getTokenAccountsByOwner($keypair->publicKey(), ['mint' => $tokenMint]);
    $tokenAmount = 0;
    foreach ($tokenAccounts['value'] as $acc) {
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

    // Gửi lệnh bán (Token -> SOL)
    $quoteSell = getJupiterQuote($httpClient, $tokenMint, 'So11111111111111111111111111111111111111112', intval($tokenAmount * 1e9), $slippageBps);
    if (isset($quoteSell['error'])) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $quoteSell['error'],
            'results' => $results
        ]);
        exit;
    }

    // Kiểm tra price impact (slippage) cho bán
    $priceImpactSell = floatval($quoteSell['priceImpactPc'] ?? 0);
    if ($priceImpactSell > $slippage) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: Slippage quá cao (price impact: $priceImpactSell%, tối đa: $slippage%)",
            'results' => $results
        ]);
        exit;
    }

    $swapSellResult = executeJupiterSwap($httpClient, $connection, $keypair, $quoteSell);
    if ($swapSellResult['error']) {
        echo json_encode([
            'message' => "⛔ Dừng vòng lặp tại vòng $i: " . $swapSellResult['error'],
            'results' => $results
        ]);
        exit;
    }
    $sellTxSig = $swapSellResult['txSig'];

    // Chờ xác nhận giao dịch bán
    $sellConfirmation = waitForConfirmation($connection, $sellTxSig);
    if (!$sellConfirmation['confirmed']) {
        if ($sellConfirmation['retry'] ?? false) {
            // Thử lại giao dịch bán
            $swapSellResult = executeJupiterSwap($httpClient, $connection, $keypair, $quoteSell);
            if ($swapSellResult['txSig']) {
                $sellTxSig = $swapSellResult['txSig'];
                $sellConfirmation = waitForConfirmation($connection, $sellTxSig);
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
    'results' => $results,
    'success' => true
]);
