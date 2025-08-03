<?php
// ============================================================================
// File: make-market/process/check-private-key.php
// Description: Endpoint to validate private key for a transaction
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . '../vendor/autoload.php';

use Attestto\SolanaPhpSdk\Keypair;
use StephenHill\Base58;

header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

if (!isset($_SESSION['user_id'])) {
    log_message('Unauthorized access to check-private-key.php', 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = $input['transaction_id'] ?? null;

if (!$transaction_id || !is_numeric($transaction_id)) {
    log_message("Invalid transaction_id: $transaction_id", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT private_key, public_key, status 
        FROM make_market 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$transaction_id, $_SESSION['user_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        log_message("Transaction ID $transaction_id not found or unauthorized for user_id {$_SESSION['user_id']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }

    // Check if transaction is in a valid state
    if (!in_array($transaction['status'], ['new', 'pending', 'processing'])) {
        log_message("Transaction ID $transaction_id is in invalid state: {$transaction['status']}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Transaction is not in a valid state for key validation']);
        exit;
    }

    // Decrypt private key
    $private_key = openssl_decrypt($transaction['private_key'], 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
    if ($private_key === false) {
        log_message("Failed to decrypt private key for transaction ID $transaction_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to decrypt private key']);
        exit;
    }

    // Validate private key
    try {
        $base58 = new Base58();
        $decoded_key = $base58->decode($private_key);
        if (strlen($decoded_key) !== 64) {
            log_message("Invalid private key length for transaction ID $transaction_id: " . strlen($decoded_key) . " bytes, expected 64 bytes", 'make-market.log', 'make-market', 'ERROR');
            echo json_encode(['status' => 'error', 'message' => 'Invalid private key length: ' . strlen($decoded_key) . ' bytes, expected 64 bytes']);
            exit;
        }
        $keypair = Keypair::fromSecretKey($decoded_key);
        $derived_public_key = $keypair->getPublicKey()->toBase58();
        if ($derived_public_key !== $transaction['public_key']) {
            log_message("Private key does not match public key for transaction ID $transaction_id", 'make-market.log', 'make-market', 'ERROR');
            echo json_encode(['status' => 'error', 'message' => 'Private key does not match public key']);
            exit;
        }
    } catch (Exception $e) {
        log_message("Invalid private key for transaction ID $transaction_id: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Invalid private key: ' . $e->getMessage()]);
        exit;
    }

    log_message("Private key check passed for transaction ID $transaction_id, status: {$transaction['status']}", 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'isPending' => $transaction['status'] === 'pending']);
} catch (Exception $e) {
    log_message("Error checking private key for transaction ID $transaction_id: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
exit;
?>
