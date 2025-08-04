<?php
// ============================================================================
// File: make-market/process/get-public-key.php
// Description: Retrieve public key from private key in database for a transaction
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/config.php';
require_once $root_path . '../vendor/autoload.php';

use Attestto\SolanaPhpSdk\Keypair;
use StephenHill\Base58;

$csp_base = rtrim(BASE_URL, '/');
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: $csp_base");
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Check AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Non-AJAX request rejected", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("get-public-key.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');
}

// Get transaction ID
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($transaction_id <= 0) {
    log_message("Invalid or missing transaction ID: $transaction_id", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction ID']);
    exit;
}

// Database connection
try {
    $pdo = get_db_connection();
    log_message("Database connection retrieved", 'make-market.log', 'make-market', 'INFO');
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT user_id, private_key FROM make_market WHERE id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction || $transaction['user_id'] != $_SESSION['user_id']) {
        log_message("Transaction not found or unauthorized: ID=$transaction_id, session_user_id=" . ($_SESSION['user_id'] ?? 'none'), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized']);
        exit;
    }
    log_message("Transaction fetched: ID=$transaction_id", 'make-market.log', 'make-market', 'INFO');
} catch (PDOException $e) {
    log_message("Database query failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error retrieving transaction: ' . $e->getMessage()]);
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
        log_message("Failed to decrypt private key: openssl_decrypt returned false", 'make-market.log', 'make-market', 'ERROR');
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

// Derive public key
try {
    $base58 = new Base58();
    $decodedKey = $base58->decode($private_key);
    if (strlen($decodedKey) !== 64) {
        log_message("Invalid private key length: " . strlen($decodedKey), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid private key length: ' . strlen($decodedKey)]);
        exit;
    }
    $keypair = Keypair::fromSecretKey($decodedKey);
    $public_key = $keypair->getPublicKey()->toBase58();
    log_message("Public key derived: $public_key", 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'public_key' => $public_key]);
} catch (Exception $e) {
    log_message("Failed to derive public key: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to derive public key: ' . $e->getMessage()]);
    exit;
}
?>
