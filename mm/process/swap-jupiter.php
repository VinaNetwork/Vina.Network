<?php
// ============================================================================
// File: mm/process/swap-jupiter.php
// Description: Execute Solana token swap using Jupiter Aggregator API with looping
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

use StephenHill\Base58;
use Attestto\SolanaPhpSdk\Connection;
use Attestto\SolanaPhpSdk\Keypair;
use Attestto\SolanaPhpSdk\PublicKey;
use Attestto\SolanaPhpSdk\Transaction;

// Initialize logging context
$log_context = [
    'endpoint' => 'swap',
    'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

// Log request details
$session_id = session_id() ?: 'none';
$headers = apache_request_headers();
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$cookies = $_SERVER['HTTP_COOKIE'] ?? 'none';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("swap.php: Request received, method=$request_method, uri=$request_uri, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers), 'process.log', 'make-market', 'DEBUG', $log_context);
}

// Check POST method
if ($request_method !== 'POST') {
    log_message("Invalid request method: $request_method, uri=$request_uri, session_id=$session_id", 'process.log', 'make-market', 'ERROR', $log_context);
    send_error_response(405, 'Method not allowed');
}

// Check X-Auth-Token
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;
if ($authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=$request_uri, session_id=$session_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Initialize session
if (!ensure_session()) {
    log_message("Failed to initialize session, method=$request_method, uri=$request_uri, session_id=$session_id, cookies=$cookies", 'process.log', 'make-market', 'ERROR', $log_context);
    send_error_response(500, 'Session initialization failed');
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_error_response(400, 'Invalid JSON input: ' . json_last_error_msg());
}
$transaction_id = isset($input['id']) ? intval($input['id']) : 0;
$swap_transactions = $input['swap_transactions'] ?? null;
$sub_transaction_ids = $input['sub_transaction_ids'] ?? null;
$client_network = $input['network'] ?? null;
$log_context['transaction_id'] = $transaction_id;
$log_context['client_network'] = $client_network;

if ($transaction_id <= 0 || !is_array($swap_transactions) || !is_array($sub_transaction_ids) || count($swap_transactions) !== count($sub_transaction_ids) || !in_array($client_network, ['mainnet', 'devnet'])) {
    $user_id = $_SESSION['user_id'] ?? 'none';
    log_message("Invalid input: transaction_id=$transaction_id, swap_transactions=" . json_encode($swap_transactions) . ", sub_transaction_ids=" . json_encode($sub_transaction_ids) . ", client_network=$client_network, user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    send_error_response(400, 'Invalid transaction data or network');
}

// Check network consistency
if ($client_network !== SOLANA_NETWORK) {
    $user_id = $_SESSION['user_id'] ?? 'none';
    log_message("Network mismatch: client_network=$client_network, server_network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    send_error_response(400, "Network mismatch: client ($client_network) vs server (" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ")");
}

// Check RPC endpoint
if (empty(RPC_ENDPOINT)) {
    $user_id = $_SESSION['user_id'] ?? 'none';
    log_message("RPC_ENDPOINT is empty, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    send_error_response(500, 'Server configuration error: Missing RPC endpoint');
}

// Database connection
try {
    $pdo = get_db_connection();
    $user_id = $_SESSION['user_id'] ?? 'none';
    log_message("Database connection retrieved, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'INFO', $log_context);
} catch (Exception $e) {
    log_message("Database connection failed: " . $e->getMessage() . ", user_id=" . ($_SESSION['user_id'] ?? 'none') . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    send_error_response(500, 'Database connection error');
}

// Fetch transaction details
try {
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT user_id, public_key, token_mint, sol_amount, token_amount, trade_direction, private_key, network FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
    $stmt->execute([$transaction_id, $user_id, SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Transaction not found, unauthorized, or network mismatch: ID=$transaction_id, user_id=$user_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
        send_error_response(403, 'Transaction not found, unauthorized, or network mismatch');
    }
} catch (PDOException $e) {
    log_message("Database query failed: " . $e->getMessage() . ", user_id=" . ($_SESSION['user_id'] ?? 'none') . ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'process.log', 'make-market', 'ERROR', $log_context);
    send_error_response(500, 'Error retrieving transaction');
}

// Decrypt private key
try {
    if (!defined('JWT_SECRET') || empty(JWT_SECRET)) {
        log_message("JWT_SECRET is not defined or empty, user_id=$user_id, network=" . SOLANA_NETWORK, 'process.log', 'make-market', 'ERROR', $log_context);
        send_error_response(500, 'Server configuration error');
    }
    $private_key = openssl_decrypt($transaction['private_key'], 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
    if ($private_key === false) {
        log_message("Failed to decrypt private key, user_id=$user_id, network=" . SOLANA_NETWORK, 'process.log', 'make-market', 'ERROR', $log_context);
        send_error_response(500, 'Failed to decrypt private key');
    }
    log_message("Private key decrypted successfully, user_id=$user_id, network=" . SOLANA_NETWORK, 'process.log', 'make-market', 'INFO', $log_context);
} catch (Exception $e) {
    log_message("Private key decryption failed: " . $e->getMessage() . ", user_id=$user_id, network=" . SOLANA_NETWORK, 'process.log', 'make-market', 'ERROR', $log_context);
    send_error_response(500, 'Failed to decrypt private key: ' . $e->getMessage());
}

// Process each transaction
$results = [];
$connection = new Connection(RPC_ENDPOINT);
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

    if ($sub_transaction_id === 0 || empty($swap_transaction)) {
        log_message("Invalid sub-transaction ID or swap transaction, direction=$direction, loop=$loop, batch_index=$batch_index, user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        $results[] = [
            'loop' => $loop,
            'batch_index' => $batch_index,
            'direction' => $direction,
            'status' => 'error',
            'message' => 'Invalid sub-transaction ID or swap transaction'
        ];
        continue;
    }

    // Decode private key
    try {
        $base58 = new Base58();
        $decoded_private_key = $base58->decode($private_key);
    } catch (Exception $e) {
        log_message("Failed to decode private key for sub-transaction ID=$sub_transaction_id: " . $e->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to decode private key: " . $e->getMessage(), $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: " . $e2->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        }
        $results[] = [
            'loop' => $loop,
            'batch_index' => $batch_index,
            'direction' => $direction,
            'status' => 'error',
            'message' => 'Error processing private key'
        ];
        continue;
    }

    // Create keypair
    try {
        $keypair = Keypair::fromSecretKey($decoded_private_key);
    } catch (Exception $e) {
        log_message("Failed to create keypair for sub-transaction ID=$sub_transaction_id: " . $e->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to create keypair: " . $e->getMessage(), $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: " . $e2->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        }
        $results[] = [
            'loop' => $loop,
            'batch_index' => $batch_index,
            'direction' => $direction,
            'status' => 'error',
            'message' => 'Error creating keypair'
        ];
        continue;
    }

    // Verify public key matches
    $derivedPublicKey = $keypair->getPublicKey()->toBase58();
    if ($derivedPublicKey !== $transaction['public_key']) {
        log_message("Public key mismatch for sub-transaction ID=$sub_transaction_id: derived=$derivedPublicKey, stored={$transaction['public_key']}, user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Public key mismatch: derived=$derivedPublicKey, stored={$transaction['public_key']}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: " . $e2->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        }
        $results[] = [
            'loop' => $loop,
            'batch_index' => $batch_index,
            'direction' => $direction,
            'status' => 'error',
            'message' => 'Wallet address mismatch'
        ];
        continue;
    }

    // Decode and sign transaction
    try {
        $transactionObj = Transaction::from($swap_transaction);
    } catch (Exception $e) {
        log_message("Failed to decode transaction for sub-transaction ID=$sub_transaction_id: " . $e->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to decode transaction: " . $e->getMessage(), $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: " . $e2->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        }
        $results[] = [
            'loop' => $loop,
            'batch_index' => $batch_index,
            'direction' => $direction,
            'status' => 'error',
            'message' => 'Error decoding transaction'
        ];
        continue;
    }

    try {
        $transactionObj->sign($keypair);
    } catch (Exception $e) {
        log_message("Failed to sign transaction for sub-transaction ID=$sub_transaction_id: " . $e->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to sign transaction: " . $e->getMessage(), $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: " . $e2->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
        }
        $results[] = [
            'loop' => $loop,
            'batch_index' => $batch_index,
            'direction' => $direction,
            'status' => 'error',
            'message' => 'Error signing transaction'
        ];
        continue;
    }

    // Send transaction with retries
    $txid = null;
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $log_context['attempt'] = $attempt;
        try {
            $txid = $connection->sendRawTransaction($transactionObj->serialize());
            log_message("Swap transaction sent for sub-transaction ID=$sub_transaction_id: txid=$txid, user_id=$user_id", 'process.log', 'make-market', 'INFO', $log_context);
            try {
                $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ?, txid = ? WHERE id = ?");
                $stmt->execute(['success', null, $txid, $sub_transaction_id]);
                log_message("Sub-transaction status updated: ID=$sub_transaction_id, status=success, txid=$txid, user_id=$user_id", 'process.log', 'make-market', 'INFO', $log_context);
            } catch (PDOException $e) {
                log_message("Failed to update sub-transaction status: " . $e->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
            }
            $results[] = [
                'loop' => $loop,
                'batch_index' => $batch_index,
                'direction' => $direction,
                'status' => 'success',
                'txid' => $txid,
                'explorer_url' => "https://solscan.io/tx/$txid?cluster=" . (SOLANA_NETWORK === 'mainnet' ? 'mainnet-beta' : 'devnet')
            ];
            break;
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            log_message("Failed to send transaction for sub-transaction ID=$sub_transaction_id, attempt $attempt/$maxRetries: $error_message, user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
            if ($attempt === $maxRetries) {
                try {
                    $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
                    $stmt->execute(['failed', "Failed to send transaction after $maxRetries attempts: $error_message", $sub_transaction_id]);
                } catch (PDOException $e2) {
                    log_message("Failed to update sub-transaction status: " . $e2->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
                }
                $results[] = [
                    'loop' => $loop,
                    'batch_index' => $batch_index,
                    'direction' => $direction,
                    'status' => 'error',
                    'message' => "Failed to send transaction after $maxRetries attempts: $error_message"
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
    $error_message = $success_count < count($swap_transactions) ? "Completed $success_count of " . count($swap_transactions) . " transactions" : null;
    $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
    $stmt->execute([$overall_status, $error_message, $transaction_id]);
    log_message("Main transaction status updated: ID=$transaction_id, status=$overall_status, success_count=$success_count, user_id=$user_id", 'process.log', 'make-market', 'INFO', $log_context);
} catch (PDOException $e) {
    log_message("Failed to update main transaction status: " . $e->getMessage() . ", user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    send_error_response(500, 'Error updating main transaction status');
}

// Return results
header('Content-Type: application/json');
echo json_encode([
    'status' => $success_count === count($swap_transactions) ? 'success' : ($success_count > 0 ? 'partial' : 'failed'),
    'message' => $success_count === count($swap_transactions) ? 'All swap transactions completed successfully' : "Completed $success_count of " . count($swap_transactions) . " transactions",
    'results' => $results
], JSON_UNESCAPED_UNICODE);
?>
