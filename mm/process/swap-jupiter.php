<?php
// ============================================================================
// File: mm/process/swap-jupiter.php
// Description: Execute Solana token swap using Jupiter Aggregator API with looping
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

use StephenHill\Base58;
use Attestto\SolanaPhpSdk\Connection;
use Attestto\SolanaPhpSdk\Keypair;
use Attestto\SolanaPhpSdk\PublicKey;
use Attestto\SolanaPhpSdk\Transaction;
use Attestto\SolanaPhpSdk\Programs\SystemProgram;
use Attestto\SolanaPhpSdk\Programs\TokenProgram;

// Initialize logging context
$log_context = [
    'endpoint' => 'swap',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
];

// Log request details
$session_id = session_id() ?: 'none';
$headers = apache_request_headers();
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$cookies = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message(
        "swap.php: Yêu cầu nhận được, method=$request_method, uri=$request_uri, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers) . ", body=" . file_get_contents('php://input'),
        'process.log', 'make-market', 'DEBUG', $log_context
    );
}

// Kiểm tra phương thức POST
if ($request_method !== 'POST') {
    log_message("Phương thức yêu cầu không hợp lệ: $request_method, uri=$request_uri, session_id=$session_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không được phép'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Khởi tạo session
if (!ensure_session()) {
    log_message("Không thể khởi tạo session, method=$request_method, uri=$request_uri, session_id=$session_id, cookies=$cookies", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Khởi tạo session thất bại'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra CSRF token
try {
    csrf_protect();
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Kiểm tra CSRF thất bại: " . $e->getMessage() . ", method=$request_method, uri=$request_uri, session_id=$session_id, user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Kiểm tra CSRF thất bại'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['id']) ? intval($input['id']) : 0;
$swap_transactions = $input['swap_transactions'] ?? null;
$sub_transaction_ids = $input['sub_transaction_ids'] ?? null;
$client_network = $input['network'] ?? null;
$log_context['transaction_id'] = $transaction_id;
$log_context['client_network'] = $client_network;

log_message(
    "Dữ liệu đầu vào nhận được: transaction_id=$transaction_id, swap_transactions_count=" . (is_array($swap_transactions) ? count($swap_transactions) : 0) . ", sub_transaction_ids=" . json_encode($sub_transaction_ids) . ", client_network=$client_network, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
    'process.log', 'make-market', 'INFO', $log_context
);

if ($transaction_id <= 0 || !is_array($swap_transactions) || !is_array($sub_transaction_ids) || count($swap_transactions) !== count($sub_transaction_ids) || !in_array($client_network, ['mainnet', 'devnet'])) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Dữ liệu đầu vào không hợp lệ: transaction_id=$transaction_id, swap_transactions=" . json_encode($swap_transactions) . ", sub_transaction_ids=" . json_encode($sub_transaction_ids) . ", client_network=$client_network, user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu giao dịch hoặc mạng không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check network consistency
if ($client_network !== SOLANA_NETWORK) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Mạng không khớp: client_network=$client_network, server_network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Mạng không khớp: client ($client_network) vs server (" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ")"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check RPC endpoint
if (empty(RPC_ENDPOINT)) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("RPC_ENDPOINT trống, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cấu hình server: Thiếu endpoint RPC'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Kết nối cơ sở dữ liệu thành công, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'INFO', $log_context);
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Kết nối cơ sở dữ liệu thất bại: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối cơ sở dữ liệu'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fetch transaction details
try {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $stmt = $pdo->prepare("SELECT user_id, public_key, token_mint, sol_amount, token_amount, trade_direction, private_key, network FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
    $stmt->execute([$transaction_id, $user_id, SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message("Giao dịch không tìm thấy, không được phép, hoặc mạng không khớp: ID=$transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Giao dịch không tìm thấy, không được phép, hoặc mạng không khớp'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    log_message(
        "Giao dịch đã được lấy: ID=$transaction_id, public_key=" . substr($transaction['public_key'], 0, 4) . "... , token_mint=" . $transaction['token_mint'] . ", trade_direction=" . $transaction['trade_direction'] . ", user_id=$user_id, network=" . $transaction['network'],
        'process.log', 'make-market', 'INFO', $log_context
    );
} catch (PDOException $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Truy vấn cơ sở dữ liệu thất bại: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi lấy thông tin giao dịch'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Decrypt private key
try {
    if (!defined('JWT_SECRET') || empty(JWT_SECRET)) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message("JWT_SECRET không được định nghĩa hoặc trống, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi cấu hình server'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $private_key = openssl_decrypt($transaction['private_key'], 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
    if ($private_key === false) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message("Không thể giải mã khóa riêng, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Không thể giải mã khóa riêng'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    log_message("Khóa riêng đã được giải mã thành công, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'INFO', $log_context);
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Giải mã khóa riêng thất bại: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Không thể giải mã khóa riêng'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Process each transaction
try {
    $results = [];
    $connection = new Connection(RPC_ENDPOINT, ['timeout' => 15]); // Thêm timeout 15 giây
    $maxRetries = 3;

    foreach ($swap_transactions as $index => $swap) {
        $direction = $swap['direction'] ?? 'buy';
        $swap_transaction = $swap['tx'] ?? '';
        $sub_transaction_id = $sub_transaction_ids[$index] ?? 0;
        $loop = $swap['loop'] ?? 1;
        $batch_index = $swap['batch_index'] ?? 0;
        $log_context['sub_transaction_id'] = $sub_transaction_id;
        $log_context['direction'] = $direction;
        $log_context['loop'] = $loop;
        $log_context['batch_index'] = $batch_index;

        log_message(
            "Xử lý sub-transaction: ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index, swap_transaction_length=" . strlen($swap_transaction) . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'),
            'process.log', 'make-market', 'INFO', $log_context
        );

        if ($sub_transaction_id === 0 || empty($swap_transaction)) {
            log_message("ID sub-transaction hoặc swap transaction không hợp lệ, direction=$direction, loop=$loop, batch_index=$batch_index, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
            $results[] = [
                'loop' => $loop,
                'batch_index' => $batch_index,
                'direction' => $direction,
                'status' => 'error',
                'message' => 'ID sub-transaction hoặc swap transaction không hợp lệ'
            ];
            continue;
        }

        // Decode private key
        try {
            $base58 = new Base58();
            $decoded_private_key = $base58->decode($private_key);
            log_message("Khóa riêng đã được giải mã thành công cho sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'INFO', $log_context);
        } catch (Exception $e) {
            log_message("Không thể giải mã khóa riêng cho sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: " . $e->getMessage() . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
            try {
                $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
                $stmt->execute(['failed', "Không thể giải mã khóa riêng: " . $e->getMessage(), $sub_transaction_id]);
            } catch (PDOException $e2) {
                log_message("Không thể cập nhật trạng thái sub-transaction: " . $e2->getMessage() . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
            }
            $results[] = [
                'loop' => $loop,
                'batch_index' => $batch_index,
                'direction' => $direction,
                'status' => 'error',
                'message' => 'Lỗi khi xử lý khóa riêng'
            ];
            continue;
        }

        // Create keypair
        try {
            $keypair = Keypair::fromSecretKey($decoded_private_key);
            log_message("Keypair được tạo thành công cho sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'INFO', $log_context);
        } catch (Exception $e) {
            log_message("Không thể tạo keypair cho sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: " . $e->getMessage() . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
            try {
                $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
                $stmt->execute(['failed', "Không thể tạo keypair: " . $e->getMessage(), $sub_transaction_id]);
            } catch (PDOException $e2) {
                log_message("Không thể cập nhật trạng thái sub-transaction: " . $e2->getMessage() . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
            }
            $results[] = [
                'loop' => $loop,
                'batch_index' => $batch_index,
                'direction' => $direction,
                'status' => 'error',
                'message' => 'Lỗi khi tạo keypair'
            ];
            continue;
        }

        // Decode and sign transaction
        try {
            $transactionObj = Transaction::from($swap_transaction);
            log_message("Giao dịch được giải mã thành công cho sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'INFO', $log_context);
        } catch (Exception $e) {
            log_message("Không thể giải mã giao dịch cho sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: " . $e->getMessage() . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
            try {
                $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
                $stmt->execute(['failed', "Không thể giải mã giao dịch: " . $e->getMessage(), $sub_transaction_id]);
            } catch (PDOException $e2) {
                log_message("Không thể cập nhật trạng thái sub-transaction: " . $e2->getMessage() . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
            }
            $results[] = [
                'loop' => $loop,
                'batch_index' => $batch_index,
                'direction' => $direction,
                'status' => 'error',
                'message' => 'Lỗi khi giải mã giao dịch'
            ];
            continue;
        }

        try {
            $transactionObj->sign($keypair);
            log_message("Giao dịch được ký thành công cho sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'INFO', $log_context);
        } catch (Exception $e) {
            log_message("Không thể ký giao dịch cho sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: " . $e->getMessage() . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
            try {
                $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
                $stmt->execute(['failed', "Không thể ký giao dịch: " . $e->getMessage(), $sub_transaction_id]);
            } catch (PDOException $e2) {
                log_message("Không thể cập nhật trạng thái sub-transaction: " . $e2->getMessage() . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
            }
            $results[] = [
                'loop' => $loop,
                'batch_index' => $batch_index,
                'direction' => $direction,
                'status' => 'error',
                'message' => 'Lỗi khi ký giao dịch'
            ];
            continue;
        }

        // Send transaction with retries
        $txid = null;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $log_context['attempt'] = $attempt;
            try {
                $txid = $connection->sendRawTransaction($transactionObj->serialize());
                log_message("Giao dịch swap đã được gửi cho sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: txid=$txid, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'INFO', $log_context);
                try {
                    $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ?, txid = ? WHERE id = ?");
                    $stmt->execute(['success', null, $txid, $sub_transaction_id]);
                    log_message("Trạng thái sub-transaction đã được cập nhật: ID=$sub_transaction_id, status=success, txid=$txid, direction=$direction, loop=$loop, batch_index=$batch_index, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'INFO', $log_context);
                } catch (PDOException $e) {
                    log_message("Không thể cập nhật trạng thái sub-transaction: " . $e->getMessage() . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
                }
                $results[] = [
                    'loop' => $loop,
                    'batch_index' => $batch_index,
                    'direction' => $direction,
                    'status' => 'success',
                    'txid' => $txid
                ];
                break;
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                $user_friendly_message = $error_message === "Attempt to debit an account but found no record of a prior credit"
                    ? "Số dư ví không đủ để thực hiện giao dịch. Vui lòng nạp thêm SOL."
                    : ($error_message === "TOKEN_NOT_TRADABLE"
                        ? "Token không thể giao dịch. Vui lòng kiểm tra thanh khoản của token hoặc chọn token khác."
                        : "Lỗi khi gửi giao dịch sau $maxRetries lần thử: " . $error_message);
                log_message("Không thể gửi giao dịch cho sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index, lần thử $attempt/$maxRetries: " . $error_message . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
                if ($attempt === $maxRetries) {
                    try {
                        $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
                        $stmt->execute(['failed', $user_friendly_message, $sub_transaction_id]);
                    } catch (PDOException $e2) {
                        log_message("Không thể cập nhật trạng thái sub-transaction: " . $e2->getMessage() . ", user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
                    }
                    $results[] = [
                        'loop' => $loop,
                        'batch_index' => $batch_index,
                        'direction' => $direction,
                        'status' => 'error',
                        'message' => $user_friendly_message
                    ];
                }
                if ($attempt < $maxRetries) {
                    sleep(1 * $attempt); // Wait 1s, 2s, 3s
                }
            }
        }
    }

    // Update main transaction status
    try {
        $success_count = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $overall_status = $success_count === count($swap_transactions) ? 'success' : ($success_count > 0 ? 'partial' : 'failed');
        $error_message = $success_count < count($swap_transactions) ? "Hoàn thành $success_count trong số " . count($swap_transactions) . " giao dịch" : null;
        $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
        $stmt->execute([$overall_status, $error_message, $transaction_id]);
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message(
            "Trạng thái giao dịch chính đã được cập nhật: ID=$transaction_id, status=$overall_status, success_count=$success_count, error_message=" . ($error_message ?? 'none') . ", results=" . json_encode($results) . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", user_id=$user_id",
            'process.log', 'make-market', 'INFO', $log_context
        );
    } catch (PDOException $e) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
        log_message("Không thể cập nhật trạng thái giao dịch chính: " . $e->getMessage() . ", user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi cập nhật trạng thái giao dịch chính'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Return results
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $overall_status,
        'message' => $success_count === count($swap_transactions) ? 'Tất cả giao dịch swap đã hoàn thành thành công' : "Hoàn thành $success_count trong số " . count($swap_transactions) . " giao dịch",
        'results' => $results
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    $error_message = $e->getMessage();
    $user_friendly_message = $error_message === "Attempt to debit an account but found no record of a prior credit"
        ? "Số dư ví không đủ để thực hiện giao dịch. Vui lòng nạp thêm SOL."
        : ($error_message === "TOKEN_NOT_TRADABLE"
            ? "Token không thể giao dịch. Vui lòng kiểm tra thanh khoản của token hoặc chọn token khác."
            : "Lỗi server không mong muốn: " . $error_message);
    log_message(
        "Lỗi không mong muốn trong swap-jupiter.php: " . $error_message . ", transaction_id=$transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'process.log', 'make-market', 'ERROR', $log_context
    );
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $user_friendly_message], JSON_UNESCAPED_UNICODE);
}
?>
