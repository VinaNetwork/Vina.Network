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
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT user_id, public_key, token_mint, sol_amount, private_key FROM make_market WHERE id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction || $transaction['user_id'] != ($_SESSION['user_id'] ?? 0)) {
        log_message("Transaction not found or unauthorized: ID=$transaction_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized']);
        exit;
    }
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction']);
    exit;
}

// Decrypt private key
try {
    if (!defined('JWT_SECRET') || empty(JWT_SECRET)) {
        log_message("JWT_SECRET is not defined or empty", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: JWT_SECRET missing']);
        exit;
    }
    $private_key = openssl_decrypt($transaction['private_key'], 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
    if ($private_key === false) {
        log_message("Failed to decrypt private key", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to decrypt private key']);
        exit;
    }
    log_message("Private key decrypted successfully", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Private key decryption failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Private key decryption failed: ' . $e->getMessage()]);
    exit;
}

// Check balance server-side using getAssetsByOwner
try {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://mainnet.helius-rpc.com/?api-key=" . HELIUS_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'getAssetsByOwner',
            'params' => [
                'ownerAddress' => $transaction['public_key'],
                'page' => 1,
                'limit' => 50,
                'sortBy' => [
                    'sortBy' => 'created',
                    'sortDirection' => 'asc'
                ],
                'options' => [
                    'showNativeBalance' => true
                ]
            ]
        ]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        log_message("Helius RPC failed: cURL error: $err", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Balance check failed: cURL error: ' . $err]);
        exit;
    }

    if ($http_code !== 200) {
        log_message("Helius RPC failed: HTTP $http_code", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => "Balance check failed: HTTP $http_code"]);
        exit;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Helius RPC failed: Invalid JSON response: " . json_last_error_msg(), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Balance check failed: Invalid JSON response']);
        exit;
    }

    if (isset($data['error'])) {
        log_message("Helius RPC failed: {$data['error']['message']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Balance check failed: ' . $data['error']['message']]);
        exit;
    }

    if (!isset($data['result']['nativeBalance']) || !isset($data['result']['nativeBalance']['lamports'])) {
        log_message("Helius RPC failed: No nativeBalance or lamports in response", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Balance check failed: No native balance found']);
        exit;
    }

    $balanceInSol = floatval($data['result']['nativeBalance']['lamports']) / 1e9; // Convert lamports to SOL
    $requiredAmount = floatval($transaction['sol_amount']) + 0.005; // Add 0.005 SOL for fees
    if ($balanceInSol < $requiredAmount) {
        log_message("Insufficient balance: $balanceInSol SOL available, required=$requiredAmount SOL", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Insufficient balance: $balanceInSol SOL available, $requiredAmount SOL required"]);
        exit;
    }
    log_message("Balance check passed: $balanceInSol SOL available, required=$requiredAmount SOL", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Balance check failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    try {
        $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
        $stmt->execute(['failed', $e->getMessage(), $transaction_id]);
        log_message("Transaction status updated: ID=$transaction_id, status=failed, error={$e->getMessage()}", 'make-market.log', 'make-market', 'INFO');
    } catch (PDOException $e2) {
        log_message("Failed to update transaction status: {$e2->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Balance check failed: ' . $e->getMessage()]);
    exit;
}

// Sign and send transaction
try {
    // Decode private key
    try {
        $decoded_private_key = base58_decode($private_key);
    } catch (Exception $e) {
        log_message("Failed to decode private key: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to decode private key: ' . $e->getMessage()]);
        exit;
    }

    // Create keypair
    try {
        $keypair = Keypair::fromSecretKey($decoded_private_key);
    } catch (Exception $e) {
        log_message("Failed to create keypair: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create keypair: ' . $e->getMessage()]);
        exit;
    }

    $connection = new Connection('https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY);

    // Verify public key matches
    $derivedPublicKey = $keypair->getPublicKey()->toBase58();
    if ($derivedPublicKey !== $transaction['public_key']) {
        log_message("Public key mismatch: derived=$derivedPublicKey, stored={$transaction['public_key']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Public key mismatch']);
        exit;
    }

    // Decode and sign transaction
    try {
        $transactionObj = Transaction::from($swap_transaction);
    } catch (Exception $e) {
        log_message("Failed to decode transaction: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to decode transaction: ' . $e->getMessage()]);
        exit;
    }

    try {
        $transactionObj->sign($keypair);
    } catch (Exception $e) {
        log_message("Failed to sign transaction: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to sign transaction: ' . $e->getMessage()]);
        exit;
    }

    // Send transaction
    try {
        $txid = $connection->sendRawTransaction($transactionObj->serialize());
        log_message("Swap transaction sent: txid=$txid", 'make-market.log', 'make-market', 'INFO');
    } catch (Exception $e) {
        log_message("Failed to send transaction: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to send transaction: ' . $e->getMessage()]);
        exit;
    }

    // Update transaction status
    try {
        $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ?, txid = ? WHERE id = ?");
        $stmt->execute(['success', null, $txid, $transaction_id]);
        log_message("Transaction status updated: ID=$transaction_id, status=success, txid=$txid", 'make-market.log', 'make-market', 'INFO');
        echo json_encode(['status' => 'success', 'txid' => $txid]);
    } catch (PDOException $e) {
        log_message("Failed to update transaction status: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update transaction status: ' . $e->getMessage()]);
        exit;
    }
} catch (Exception $e) {
    log_message("Swap execution failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    try {
        $stmt = $pdo->prepare("UPDATE make_market SET status = ?, error = ? WHERE id = ?");
        $stmt->execute(['failed', $e->getMessage(), $transaction_id]);
        log_message("Transaction status updated: ID=$transaction_id, status=failed, error={$e->getMessage()}", 'make-market.log', 'make-market', 'INFO');
    } catch (PDOException $e2) {
        log_message("Failed to update transaction status: {$e2->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Swap execution failed: ' . $e->getMessage()]);
    exit;
}
?>
