<?php
// ============================================================================
// File: make-market/index.php
// Description: Make Market page for automated token trading on Solana using Jupiter API
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';
require_once $root_path . '../vendor/autoload.php';

use Attestto\SolanaPhpSdk\Keypair;
use StephenHill\Base58;

// Add Security Headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' $csp_base; connect-src 'self' $csp_base https://quote-api.jup.ag https://api.mainnet-beta.solana.com https://mainnet.helius-rpc.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Access-Control-Allow-Origin: $csp_base");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Error reporting
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("index.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
}

// Database connection
$start_time = microtime(true);
try {
    $pdo = get_db_connection();
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection retrieved (took {$duration}ms)", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    $duration = (microtime(true) - $start_time) * 1000;
    log_message("Database connection failed: {$e->getMessage()} (took {$duration}ms)", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Kết nối cơ sở dữ liệu thất bại']);
    exit;
}

// Check session for authentication
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("Session public_key: $short_public_key", 'make-market.log', 'make-market', 'DEBUG');
}
if (!$public_key) {
    log_message("No public key in session, redirecting to login", 'make-market.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/make-market';
    header('Location: /accounts');
    exit;
}

// Fetch account info for user_id
try {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for session public_key: $short_public_key", 'make-market.log', 'make-market', 'ERROR');
        $_SESSION['redirect_url'] = '/make-market';
        header('Location: /accounts');
        exit;
    }
    $_SESSION['user_id'] = $account['id'];
    log_message("Session updated with user_id: {$account['id']}, public_key: $short_public_key", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi truy xuất thông tin tài khoản']);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        log_message("Form submitted, is AJAX: " . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? 'Yes' : 'No'), 'make-market.log', 'make-market', 'INFO');
        $form_data = $_POST;
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            log_message("Form data: " . json_encode($form_data), 'make-market.log', 'make-market', 'DEBUG');
        }

        // Validate CSRF token
        if (!validate_csrf_token($form_data['csrf_token'] ?? '')) {
            log_message("Invalid CSRF token", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Token CSRF không hợp lệ']);
            exit;
        }

        // Get form data
        $processName = $form_data['processName'] ?? '';
        $privateKey = trim($form_data['privateKey'] ?? '');
        $tokenMint = $form_data['tokenMint'] ?? '';
        $solAmount = floatval($form_data['solAmount'] ?? 0);
        $slippage = floatval($form_data['slippage'] ?? 0.5);
        $delay = intval($form_data['delay'] ?? 0);
        $loopCount = intval($form_data['loopCount'] ?? 1);
        $batchSize = intval($form_data['batchSize'] ?? 5);
        $transactionPublicKey = $form_data['transactionPublicKey'] ?? '';

        // Log form data for debugging
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            log_message("Form data: processName=$processName, tokenMint=$tokenMint, solAmount=$solAmount, slippage=$slippage, delay=$delay, loopCount=$loopCount, batchSize=$batchSize, privateKey_length=" . strlen($privateKey), 'make-market.log', 'make-market', 'DEBUG');
        }

        // Validate inputs
        if (empty($processName) || empty($privateKey) || empty($tokenMint) || empty($transactionPublicKey)) {
            log_message("Missing required fields", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Thiếu các trường bắt buộc']);
            exit;
        }
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{64,128}$/', $privateKey)) {
            log_message("Invalid private key format: length=" . strlen($privateKey), 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Định dạng private key không hợp lệ']);
            exit;
        }
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $transactionPublicKey)) {
            log_message("Invalid transaction public key format: " . substr($transactionPublicKey, 0, 4) . "...", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Định dạng public key giao dịch không hợp lệ']);
            exit;
        }
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/', $tokenMint)) {
            log_message("Invalid token address: $tokenMint", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Địa chỉ token không hợp lệ']);
            exit;
        }
        if ($solAmount <= 0) {
            log_message("Invalid SOL amount: $solAmount", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Số lượng SOL phải lớn hơn 0']);
            exit;
        }
        if ($slippage < 0) {
            log_message("Invalid slippage: $slippage", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Slippage phải không âm']);
            exit;
        }
        if ($loopCount < 1) {
            log_message("Invalid loop count: $loopCount", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Số vòng lặp phải ít nhất là 1']);
            exit;
        }
        if ($batchSize < 1 || $batchSize > 10) {
            log_message("Invalid batch size: $batchSize", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Kích thước batch phải từ 1 đến 10']);
            exit;
        }

        // Validate private key using SolanaPhpSdk
        try {
            $base58 = new Base58();
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                log_message("Decoding private key, length: " . strlen($privateKey), 'make-market.log', 'make-market', 'DEBUG');
            }
            $decodedKey = $base58->decode($privateKey);
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                log_message("Decoded privateKey length: " . strlen($decodedKey), 'make-market.log', 'make-market', 'DEBUG');
            }
            if (strlen($decodedKey) !== 64) {
                log_message("Invalid private key length: " . strlen($decodedKey) . ", expected 64 bytes", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Độ dài private key không hợp lệ']);
                exit;
            }
            $keypair = Keypair::fromSecretKey($decodedKey);
            $derivedPublicKey = $keypair->getPublicKey()->toBase58();
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                log_message("Derived public key: $derivedPublicKey", 'make-market.log', 'make-market', 'DEBUG');
            }
            if ($derivedPublicKey !== $transactionPublicKey) {
                log_message("Private key does not match transaction public key: derived=$derivedPublicKey, provided=" . substr($transactionPublicKey, 0, 4) . "...", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Private key không khớp với public key giao dịch']);
                exit;
            }
            log_message("Private key validated: public_key=$derivedPublicKey", 'make-market.log', 'make-market', 'INFO');
        } catch (Exception $e) {
            log_message("Invalid private key: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Private key không hợp lệ: ' . $e->getMessage()]);
            exit;
        }

        // Kiểm tra số dư ví bằng cách gọi get-balance.php
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => BASE_URL . "make-market/get-balance.php",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'public_key' => $transactionPublicKey,
                    'sol_amount' => $solAmount,
                    'loop_count' => $loopCount,
                    'batch_size' => $batchSize
                ], JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json; charset=utf-8",
                    "X-Requested-With: XMLHttpRequest"
                ],
            ]);

            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                log_message("Failed to call get-balance.php: cURL error: $err", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối khi kiểm tra số dư ví']);
                exit;
            }

            if ($http_code !== 200) {
                log_message("Failed to call get-balance.php: HTTP $http_code", 'make-market.log', 'make-market', 'ERROR');
                $data = json_decode($response, true);
                $errorMessage = isset($data['message']) ? $data['message'] : 'Lỗi không xác định khi kiểm tra số dư ví';
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $errorMessage]);
                exit;
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message("Failed to parse get-balance.php response: " . json_last_error_msg(), 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Lỗi phân tích phản hồi từ kiểm tra số dư ví']);
                exit;
            }

            if ($data['status'] !== 'success') {
                log_message("Balance check failed: {$data['message']}", 'make-market.log', 'make-market', 'ERROR');
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $data['message']]);
                exit;
            }

            log_message("Balance check passed: {$data['message']}, balance={$data['balance']} SOL", 'make-market.log', 'make-market', 'INFO');
        } catch (Exception $e) {
            log_message("Balance check failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Lỗi khi kiểm tra số dư ví: ' . $e->getMessage()]);
            exit;
        }

        // Check JWT_SECRET
        if (!defined('JWT_SECRET') || empty(JWT_SECRET)) {
            log_message("JWT_SECRET is not defined or empty", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Lỗi cấu hình server: Thiếu JWT_SECRET']);
            exit;
        }

        // Encrypt private key
        $encryptedPrivateKey = openssl_encrypt($privateKey, 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
        if ($encryptedPrivateKey === false) {
            log_message("Failed to encrypt private key: OpenSSL error", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Lỗi mã hóa private key']);
            exit;
        }

        // Insert transaction into database with status 'new'
        try {
            $stmt = $pdo->prepare("
                INSERT INTO make_market (
                    user_id, public_key, process_name, private_key, token_mint, 
                    sol_amount, slippage, delay_seconds, loop_count, batch_size, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $transactionPublicKey,
                $processName,
                $encryptedPrivateKey,
                $tokenMint,
                $solAmount,
                $slippage,
                $delay,
                $loopCount,
                $batchSize
            ]);
            $transactionId = $pdo->lastInsertId();
            log_message("Transaction saved to database: ID=$transactionId, processName=$processName, public_key=" . substr($transactionPublicKey, 0, 4) . "...", 'make-market.log', 'make-market', 'INFO');
            log_message("Form saved to database: transactionId=$transactionId", 'make-market.log', 'make-market', 'INFO');
        } catch (PDOException $e) {
            log_message("Database insert failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu giao dịch vào cơ sở dữ liệu']);
            exit;
        }

        // Send redirect
        $redirect_url = "/make-market/process/$transactionId";
        log_message("Sending redirect to $redirect_url", 'make-market.log', 'make-market', 'INFO');
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'transactionId' => $transactionId, 'redirect' => $redirect_url]);
        } else {
            header("Location: $redirect_url");
        }
        exit;
    } catch (Exception $e) {
        log_message("Error saving transaction: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi lưu giao dịch: ' . $e->getMessage()]);
        exit;
    }
}

// SEO meta
$page_title = "Make Market - Giao dịch Token Solana Tự động | Vina Network";
$page_description = "Tự động hóa giao dịch token trên Solana với công cụ Make Market của Vina Network, sử dụng Jupiter API. An toàn, nhanh chóng và tùy chỉnh.";
$page_keywords = "Giao dịch Solana, giao dịch token tự động, Jupiter API, make market, Vina Network, token Solana, giao dịch crypto";
$page_og_title = "Make Market: Tự động hóa giao dịch Token Solana với Vina Network";
$page_og_description = "Sử dụng Make Market của Vina Network để tự động mua và bán token Solana với Jupiter API. Thử ngay!";
$page_og_url = BASE_URL . "make-market/";
$page_canonical = BASE_URL . "make-market/";

// CSS for Make Market
$page_css = ['/make-market/mm.css'];
// Slippage
$defaultSlippage = 0.5;
?>

<!DOCTYPE html>
<html lang="vi">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>

<div class="mm-container">
    <div class="mm-content">
        <h1><i class="fas fa-chart-line"></i> Make Market</h1>
        <div id="account-info">
            <table>
                <tr>
                    <th>Tài khoản:</th>
                    <td>
                        <?php if ($short_public_key !== 'Invalid'): ?>
                            <a href="https://solscan.io/address/<?php echo htmlspecialchars($public_key); ?>" target="_blank">
                                <?php echo htmlspecialchars($short_public_key); ?>
                            </a>
                            <i class="fas fa-copy copy-icon" title="Sao chép địa chỉ đầy đủ" data-full="<?php echo htmlspecialchars($public_key); ?>"></i>
                        <?php else: ?>
                            <span>Địa chỉ không hợp lệ</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Form Make Market -->
        <form id="makeMarketForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="transactionPublicKey" id="transactionPublicKey">
            <label for="processName">Tên quy trình:</label>
            <input type="text" name="processName" id="processName" required>

            <label for="privateKey">🔑 Private Key (Base58):</label>
            <textarea name="privateKey" id="privateKey" required placeholder="Nhập private key..."></textarea>
            
            <label for="tokenMint">🎯 Địa chỉ Token:</label>
            <input type="text" name="tokenMint" id="tokenMint" required placeholder="Ví dụ: So111... hoặc bất kỳ token SPL nào">

            <label for="solAmount">💰 Số lượng SOL để mua:</label>
            <input type="number" step="0.01" name="solAmount" id="solAmount" required placeholder="Ví dụ: 0.1">

            <label for="slippage">📉 Slippage (%):</label>
            <input type="number" name="slippage" id="slippage" step="0.1" value="<?php echo $defaultSlippage; ?>">

            <label for="delay">⏱️ Thời gian chờ giữa Mua và Bán (giây):</label>
            <input type="number" name="delay" id="delay" value="0" min="0">

            <label for="loopCount">🔁 Số vòng lặp:</label>
            <input type="number" name="loopCount" id="loopCount" min="1" value="1">

            <label for="batchSize">📦 Kích thước Batch (1-10):</label>
            <input type="number" name="batchSize" id="batchSize" min="1" max="10" value="5" required>

            <button class="cta-button" type="submit">🚀 Thực hiện Make Market</button>
        </form>

        <div id="mm-result" class="status-box"></div>

        <!-- Link to Transaction History -->
        <div class="history-link">
            <a href="/make-market/history/">Xem lịch sử giao dịch</a>
        </div>
    </div>
</div>

<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts - Internal library -->
<script defer src="/js/libs/solana.web3.iife.js?t=<?php echo time(); ?>" onerror="console.error('Không tải được /js/libs/solana.web3.iife.js')"></script>
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Không tải được /js/libs/axios.min.js')"></script>
<script defer src="/js/libs/bs58.js?t=<?php echo time(); ?>" onerror="console.error('Không tải được /js/libs/bs58.js')"></script>
<script defer src="/js/libs/anchor.umd.js?t=<?php echo time(); ?>" onerror="console.error('Không tải được /js/libs/anchor.umd.js')"></script>
<script defer src="/js/libs/spl-token.iife.js?t=<?php echo time(); ?>" onerror="console.error('Không tải được /js/libs/spl-token.iife.js')"></script>
<!-- Scripts - Source code -->
<script defer src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Không tải được /js/vina.js')"></script>
<script defer src="/js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Không tải được /js/navbar.js')"></script>
<script defer src="/make-market/mm.js?t=<?php echo time(); ?>" onerror="console.error('Không tải được mm.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
