<?php
// ============================================================================
// File: make-market/process/swap.php
// Description: Execute Solana token swap using Jupiter Aggregator API
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

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://vina.network');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Non-AJAX request']);
    exit;
}

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("swap.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['id']) ? intval($input['id']) : 0;
$swap_transaction = $input['swap_transaction'] ?? null;

if ($transaction_id <= 0 || !$swap_transaction) {
    log_message("Invalid or missing transaction ID or swap transaction", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT user_id, public_key, token_mint, sol_amount, private_key FROM make_market WHERE id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction || $transaction['user_id'] != $_SESSION['user_id']) {
        log_message("Transaction not found or unauthorized: ID=$transaction_id", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized']);
        exit;
    }
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction']);
    exit;
}

// Decrypt private key
try {
    if (!defined('JWT_SECRET') || empty(JWT_SECRET)) {
        log_message("JWT_SECRET is not defined or empty", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: JWT_SECRET missing']);
        exit;
    }
    $private_key = openssl_decrypt($transaction['private_key'], 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
    if ($private_key === false) {
        log_message("Failed to decrypt private key", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Failed to decrypt private key']);
        exit;
    }
    log_message("Private key decrypted successfully", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Private key decryption failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Private key decryption failed: ' . $e->getMessage()]);
    exit;
}

// Check balance server-side
try {
    $connection = new Connection('https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY);
    $publicKey = new PublicKey($transaction['public_key']);
    $balance = $connection->getBalance($publicKey);
    $balanceInSol = $balance / 1e9; // Convert lamports to SOL
    $requiredAmount = $transaction['sol_amount'] + 0.005; // Add 0.005 SOL for transaction fees
    if ($balanceInSol < $requiredAmount) {
        throw new Exception("Insufficient balance: $balanceInSol SOL available, $requiredAmount SOL required");
    }
    log_message("Balance check passed: $balanceInSol SOL available", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Balance check failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
    $stmt->execute(['failed', $e->getMessage(), $transaction_id]);
    echo json_encode(['status' => 'error', 'message' => 'Balance check failed: ' . $e->getMessage()]);
    exit;
}

// Sign and send transaction
try {
    $keypair = Keypair::fromSecretKey(base58_decode($private_key));
    $connection = new Connection('https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY);

    // Verify public key matches
    $derivedPublicKey = $keypair->getPublicKey()->toBase58();
    if ($derivedPublicKey !== $transaction['public_key']) {
        log_message("Public key mismatch: derived=$derivedPublicKey, stored={$transaction['public_key']}", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Public key mismatch']);
        exit;
    }

    // Decode and sign transaction
    $transactionObj = Transaction::from($swap_transaction);
    $transactionObj->sign($keypair);

    // Send transaction
    $txid = $connection->sendRawTransaction($transactionObj->serialize());
    log_message("Swap transaction sent: txid=$txid", 'make-market.log', 'make-market', 'INFO');

    // Update transaction status
    $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
    $stmt->execute(['success', null, $transaction_id]);
    echo json_encode(['status' => 'success', 'txid' => $txid]);
} catch (Exception $e) {
    log_message("Swap execution failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
    $stmt->execute(['failed', $e->getMessage(), $transaction_id]);
    echo json_encode(['status' => 'error', 'message' => 'Swap execution failed: ' . $e->getMessage()]);
}
?>
