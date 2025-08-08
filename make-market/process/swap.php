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
require_once $root_path . 'make-market/process/network.php';
require_once $root_path . '../vendor/autoload.php';
require_once $root_path . 'make-market/process/auth.php';

use Attestto\SolanaPhpSdk\Connection;
use Attestto\SolanaPhpSdk\Keypair;
use Attestto\SolanaPhpSdk\PublicKey;
use Attestto\SolanaPhpSdk\Transaction;
use StephenHill\Base58;

// Initialize security headers and authentication
initialize_auth();
if (!perform_auth_check($pdo, $transaction_id)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication or CSRF validation failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("swap.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'DEBUG');
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['id']) ? intval($input['id']) : 0;
$swap_transactions = $input['swap_transactions'] ?? null;
$sub_transaction_ids = $input['sub_transaction_ids'] ?? null;
$client_network = $input['network'] ?? null;

if ($transaction_id <= 0 || !is_array($swap_transactions) || !is_array($sub_transaction_ids) || count($swap_transactions) !== count($sub_transaction_ids) || !in_array($client_network, ['testnet', 'mainnet'])) {
    log_message("Invalid input: transaction_id=$transaction_id, swap_transactions=" . json_encode($swap_transactions) . ", sub_transaction_ids=" . json_encode($sub_transaction_ids) . ", client_network=$client_network, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction data or network'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check network consistency
if ($client_network !== SOLANA_NETWORK) {
    log_message("Network mismatch: client_network=$client_network, server_network=" . SOLANA_NETWORK . ", user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Network mismatch: client ($client_network) vs server (" . SOLANA_NETWORK . ")"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check RPC endpoint
if (empty(RPC_ENDPOINT)) {
    log_message("RPC_ENDPOINT is empty for network=" . SOLANA_NETWORK . ", user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Missing RPC endpoint'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection error'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT user_id, public_key, token_mint, sol_amount, token_amount, trade_direction, private_key, network FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
    $stmt->execute([$transaction_id, $_SESSION['user_id'], SOLANA_NETWORK]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) {
        log_message("Transaction not found, unauthorized, or network mismatch: ID=$transaction_id, user_id={$_SESSION['user_id']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found, unauthorized, or network mismatch'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Decrypt private key
try {
    if (!defined('JWT_SECRET') || empty(JWT_SECRET)) {
        log_message("JWT_SECRET is not defined or empty, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $private_key = openssl_decrypt($transaction['private_key'], 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
    if ($private_key === false) {
        log_message("Failed to decrypt private key, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to decrypt private key'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    log_message("Private key decrypted successfully, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Private key decryption failed: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to decrypt private key'], JSON_UNESCAPED_UNICODE);
    exit;
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

    if ($sub_transaction_id === 0 || empty($swap_transaction)) {
        log_message("Invalid sub-transaction ID or swap transaction for index=$index, direction=$direction, loop=$loop, batch_index=$batch_index, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
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
        log_message("Failed to decode private key for sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to decode private key: {$e->getMessage()}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
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
        log_message("Failed to create keypair for sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to create keypair: {$e->getMessage()}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
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
        log_message("Public key mismatch for sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: derived=$derivedPublicKey, stored={$transaction['public_key']}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Public key mismatch: derived=$derivedPublicKey, stored={$transaction['public_key']}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
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
        log_message("Failed to decode transaction for sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to decode transaction: {$e->getMessage()}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
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
        log_message("Failed to sign transaction for sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        try {
            $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
            $stmt->execute(['failed', "Failed to sign transaction: {$e->getMessage()}", $sub_transaction_id]);
        } catch (PDOException $e2) {
            log_message("Failed to update sub-transaction status: {$e2->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
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
        try {
            $txid = $connection->sendRawTransaction($transactionObj->serialize());
            log_message("Swap transaction sent for sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index: txid=$txid, network=" . SOLANA_NETWORK . ", user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'INFO');
            try {
                $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ?, txid = ? WHERE id = ?");
                $stmt->execute(['success', null, $txid, $sub_transaction_id]);
                log_message("Sub-transaction status updated: ID=$sub_transaction_id, status=success, txid=$txid, direction=$direction, loop=$loop, batch_index=$batch_index, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'INFO');
            } catch (PDOException $e) {
                log_message("Failed to update sub-transaction status: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
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
            log_message("Failed to send transaction for sub-transaction ID=$sub_transaction_id, direction=$direction, loop=$loop, batch_index=$batch_index, attempt $attempt/$maxRetries: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
            if ($attempt === $maxRetries) {
                try {
                    $stmt = $pdo->prepare("UPDATE make_market_sub SET status = ?, error = ? WHERE id = ?");
                    $stmt->execute(['failed', "Failed to send transaction after $maxRetries attempts: {$e->getMessage()}", $sub_transaction_id]);
                } catch (PDOException $e2) {
                    log_message("Failed to update sub-transaction status: {$e2->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
                }
                $results[] = [
                    'loop' => $loop,
                    'batch_index' => $batch_index,
                    'direction' => $direction,
                    'status' => 'error',
                    'message' => "Error sending transaction after $maxRetries attempts: {$e->getMessage()}"
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
    $overall_status = $success_count === count($swap_transactions) ? 'success' : 'partial';
    $error_message = $success_count < count($swap_transactions) ? "Completed $success_count of " . count($swap_transactions) . " transactions" : null;
    $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
    $stmt->execute([$overall_status, $error_message, $transaction_id]);
    log_message("Main transaction status updated: ID=$transaction_id, status=$overall_status, success_count=$success_count, network=" . SOLANA_NETWORK . ", user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Failed to update main transaction status: {$e->getMessage()}, user_id={$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error updating main transaction status'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Return results
echo json_encode([
    'status' => $success_count === count($swap_transactions) ? 'success' : 'partial',
    'message' => $success_count === count($swap_transactions) ? 'All swap transactions completed successfully' : "Completed $success_count of " . count($swap_transactions) . " transactions",
    'results' => $results
], JSON_UNESCAPED_UNICODE);
?>
