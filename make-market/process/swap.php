<?php
// ============================================================================
// File: make-market/process/swap.php
// Description: Execute Solana token swap using Jupiter Aggregator API with looping
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';
require_once $root_path . '../vendor/autoload.php';

use Attestto\SolanaPhpSdk\Connection;
use Attestto\SolanaPhpSdk\Keypair;
use Attestto\SolanaPhpSdk\PublicKey;
use Attestto\SolanaPhpSdk\Transaction;
use StephenHill\Base58;

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: $csp_base");
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("swap.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['id']) ? intval($input['id']) : 0;
$swap_transactions = $input['swap_transactions'] ?? null;

if ($transaction_id <= 0 || !is_array($swap_transactions)) {
    log_message("Invalid or missing transaction ID or swap transactions", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction data'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection error'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT user_id, public_key, token_mint, sol_amount, private_key, loop_count, batch_size FROM make_market WHERE id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction || $transaction['user_id'] != ($_SESSION['user_id'] ?? 0)) {
        log_message("Transaction not found or unauthorized: ID=$transaction_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $loop_count = intval($transaction['loop_count'] ?? 1);
    $batch_size = intval($transaction['batch_size'] ?? 1);
    if ($loop_count <= 0 || $batch_size <= 0) {
        log_message("Invalid loop_count or batch_size: loop_count=$loop_count, batch_size=$batch_size", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid loop count or batch size'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Decrypt private key
try {
    if (!defined('JWT_SECRET') || empty(JWT_SECRET)) {
        log_message("JWT_SECRET is not defined or empty", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $private_key = openssl_decrypt($transaction['private_key'], 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
    if ($private_key === false) {
        log_message("Failed to decrypt private key", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to decrypt private key'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    log_message("Private key decrypted successfully", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Private key decryption failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to decrypt private key'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Create sub-transaction records
try {
    $stmt = $pdo->prepare("INSERT INTO make_market_sub (parent_id, loop_number, batch_index, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $total_transactions = $loop_count * $batch_size;
    for ($loop = 1; $loop <= $loop_count; $loop++) {
        for ($batch_index = 0; $batch_index < $batch_size; $batch_index++) {
            $stmt->execute([$transaction_id, $loop, $batch_index]);
        }
    }
    $sub_transaction_ids = [];
    $stmt = $pdo->query("SELECT id FROM make_market_sub WHERE parent_id = $transaction_id ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sub_transaction_ids[] = $row['id'];
    }
    log_message("Created $total_transactions sub-transactions for transaction ID=$transaction_id, IDs: " . implode(',', $sub_transaction_ids), 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Failed to create sub-transactions: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to create sub-transactions'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Process each transaction
$results = [];
$connection = new Connection('https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY);

foreach ($swap_transactions as $index => $swap_transaction) {
    $loop = floor($index / $batch_size) + 1;
    $batch_index = $index % $batch_size;
    $sub_transaction_id = $sub_transaction_ids[$index];

    // Decode private key
    try {
        $base58 = new Base58();
        $decoded_private_key = $base58->decode($private_key);
    } catch (Exception $e) {
        log_message("Failed to decode private key for loop $loop, batch $batch_index: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to decode private key: {$e->getMessage()}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        }
        $results[] = ['loop' => $loop, 'batch_index' => $batch_index, 'status' => 'error', 'message' => 'Error processing private key'];
        continue;
    }

    // Create keypair
    try {
        $keypair = Keypair::fromSecretKey($decoded_private_key);
    } catch (Exception $e) {
        log_message("Failed to create keypair for loop $loop, batch $batch_index: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to create keypair: {$e->getMessage()}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        }
        $results[] = ['loop' => $loop, 'batch_index' => $batch_index, 'status' => 'error', 'message' => 'Error creating keypair'];
        continue;
    }

    // Verify public key matches
    $derivedPublicKey = $keypair->getPublicKey()->toBase58();
    if ($derivedPublicKey !== $transaction['public_key']) {
        log_message("Public key mismatch for loop $loop, batch $batch_index: derived=$derivedPublicKey, stored={$transaction['public_key']}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Public key mismatch: derived=$derivedPublicKey, stored={$transaction['public_key']}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        }
        $results[] = ['loop' => $loop, 'batch_index' => $batch_index, 'status' => 'error', 'message' => 'Wallet address mismatch'];
        continue;
    }

    // Decode and sign transaction
    try {
        $transactionObj = Transaction::from($swap_transaction);
    } catch (Exception $e) {
        log_message("Failed to decode transaction for loop $loop, batch $batch_index: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to decode transaction: {$e->getMessage()}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        }
        $results[] = ['loop' => $loop, 'batch_index' => $batch_index, 'status' => 'error', 'message' => 'Error decoding transaction'];
        continue;
    }

    try {
        $transactionObj->sign($keypair);
    } catch (Exception $e) {
        log_message("Failed to sign transaction for loop $loop, batch $batch_index: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to sign transaction: {$e->getMessage()}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        }
        $results[] = ['loop' => $loop, 'batch_index' => $batch_index, 'status' => 'error', 'message' => 'Error signing transaction'];
        continue;
    }

    // Send transaction
    try {
        $txid = $connection->sendRawTransaction($transactionObj->serialize());
        log_message("Swap transaction sent for loop $loop, batch $batch_index: txid=$txid", 'make-market.log', 'make-market', 'INFO');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ?, txid = ? WHERE id = ?");
            $stmt->execute(['success', null, $txid, $sub_transaction_id]);
            log_message("Sub-transaction status updated: ID=$sub_transaction_id, status=success, txid=$txid", 'make-market.log', 'make-market', 'INFO');
        } catch (PDOException $e) {
            log_message("Failed to update sub-transaction status: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        }
        $results[] = ['loop' => $loop, 'batch_index' => $batch_index, 'status' => 'success', 'txid' => $txid];
    } catch (Exception $e) {
        log_message("Failed to send transaction for loop $loop, batch $batch_index: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to send transaction: {$e->getMessage()}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        }
        $results[] = ['loop' => $loop, 'batch_index' => $batch_index, 'status' => 'error', 'message' => 'Error sending transaction'];
        continue;
    }
}

// Update main transaction status
try {
    $success_count = count(array_filter($results, fn($r) => $r['status'] === 'success'));
    $overall_status = $success_count === $loop_count * $batch_size ? 'success' : 'partial';
    $error_message = $success_count < $loop_count * $batch_size ? "Completed $success_count of " . ($loop_count * $batch_size) . " transactions" : null;
    $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
    $stmt->execute([$overall_status, $error_message, $transaction_id]);
    log_message("Main transaction status updated: ID=$transaction_id, status=$overall_status, success_count=$success_count", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Failed to update main transaction status: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error updating main transaction status'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Return results
echo json_encode([
    'status' => $success_count === $loop_count * $batch_size ? 'success' : 'partial',
    'message' => $success_count === $loop_count * $batch_size ? 'All swap transactions completed successfully' : "Completed $success_count of " . ($loop_count * $batch_size) . " transactions",
    'results' => $results
], JSON_UNESCAPED_UNICODE);
?>
