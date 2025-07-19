<?php
// Make Market API – Thực hiện mua và bán nhanh một token Solana bằng Jupiter API

header('Content-Type: application/json');

function error($msg) {
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// Lấy dữ liệu từ form
$privateKey = $_POST['privateKey'] ?? '';
$tokenMint = $_POST['tokenMint'] ?? '';
$solAmount = floatval($_POST['solAmount'] ?? 0);
$slippage = floatval($_POST['slippage'] ?? 1.0);
$delay = intval($_POST['delay'] ?? 0);

if (!$privateKey || !$tokenMint || $solAmount <= 0) {
    error("Thiếu dữ liệu đầu vào.");
}

// Convert Private Key (base58 string) → Keypair
require __DIR__ . '/vendor/autoload.php';
use Tightenco\Collect\Support\Collection;
use StephenHill\Base58;
use Solana\SolanaPhpSdk\Util\Keypair;

$base58 = new Base58();

try {
    $decoded = $base58->decode($privateKey);
    $keypair = Keypair::fromSecretKey($decoded);
    $wallet = $keypair->getPublicKey();
} catch (Exception $e) {
    error("Private key không hợp lệ.");
}

// Hàm gọi API Jupiter
function callJupiterAPI($url, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// --- Giai đoạn 1: MUA token
$quoteUrl = "https://quote-api.jup.ag/v6/quote?inputMint=So11111111111111111111111111111111111111112&outputMint=$tokenMint&amount=" . intval($solAmount * 1e9) . "&slippageBps=" . intval($slippage * 100);
$quote = callJupiterAPI($quoteUrl);

if (!isset($quote['routes'][0])) error("Không tìm được route để mua token.");
$route = $quote['routes'][0];

// Gọi API SWAP
$swap = callJupiterAPI("https://quote-api.jup.ag/v6/swap", 'POST', [
    'userPublicKey' => $wallet,
    'wrapUnwrapSOL' => true,
    'feeAccount' => null,
    'route' => $route,
]);

if (empty($swap['swapTransaction'])) error("Không tạo được giao dịch mua.");

$txBuy = base64_decode($swap['swapTransaction']);

// Ký và gửi
use Solana\SolanaPhpSdk\Util\Buffer;
use Solana\SolanaPhpSdk\Util\TxHelper;
use Solana\SolanaPhpSdk\Connection;
use Solana\SolanaPhpSdk\Programs\SystemProgram;

$rpc = new Connection('https://api.mainnet-beta.solana.com');
$signedTx = $keypair->signTransaction($txBuy);
$buyTxSig = $rpc->sendRawTransaction($signedTx);

if (!$buyTxSig) error("Không gửi được giao dịch mua.");

// --- Giai đoạn 2: Delay nếu có
if ($delay > 0) sleep($delay);

// --- Giai đoạn 3: BÁN token lại về SOL
$quoteSell = callJupiterAPI("https://quote-api.jup.ag/v6/quote?inputMint=$tokenMint&outputMint=So11111111111111111111111111111111111111112&amount=" . intval($route['outAmount']) . "&slippageBps=" . intval($slippage * 100));
if (!isset($quoteSell['routes'][0])) error("Không tìm được route để bán token.");
$routeSell = $quoteSell['routes'][0];

$swapSell = callJupiterAPI("https://quote-api.jup.ag/v6/swap", 'POST', [
    'userPublicKey' => $wallet,
    'wrapUnwrapSOL' => true,
    'feeAccount' => null,
    'route' => $routeSell,
]);

if (empty($swapSell['swapTransaction'])) error("Không tạo được giao dịch bán.");
$txSell = base64_decode($swapSell['swapTransaction']);
$signedSell = $keypair->signTransaction($txSell);
$sellTxSig = $rpc->sendRawTransaction($signedSell);

if (!$sellTxSig) error("Không gửi được giao dịch bán.");

echo json_encode([
    'success' => true,
    'buyTx' => $buyTxSig,
    'sellTx' => $sellTxSig
]);
