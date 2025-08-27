<?php
// ============================================================================
// File: mm/process/swap-raydium.php
// Description: Process Raydium swap transactions for devnet
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

use Solana\Keypair;
use Solana\Connection;
use Solana\Transaction;

try {
    // Validate CSRF token
    validate_csrf_token();

    // Get POST data
    $post_data = json_decode(file_get_contents('php://input'), true);
    if (!$post_data || !isset($post_data['id'], $post_data['swap_transactions'], $post_data['sub_transaction_ids'], $post_data['network'])) {
        throw new Exception('Invalid request data');
    }

    $transaction_id = $post_data['id'];
    $swap_transactions = $post_data['swap_transactions'];
    $sub_transaction_ids = $post_data['sub_transaction_ids'];
    $network = $post_data['network'];

    if ($network !== 'devnet') {
        throw new Exception('Raydium swaps are only supported on devnet');
    }

    if (count($swap_transactions) !== count($sub_transaction_ids)) {
        throw new Exception('Mismatch between swap transactions and sub-transaction IDs');
    }

    // Initialize Solana connection
    $rpc_endpoint = 'https://api.devnet.solana.com';
    $connection = new Connection($rpc_endpoint);

    // Load server wallet
    $private_key = getenv('SOLANA_PRIVATE_KEY'); // Đảm bảo biến môi trường được thiết lập
    if (!$private_key) {
        throw new Exception('Server wallet private key not configured');
    }
    $keypair = Keypair::fromPrivateKey(base64_decode($private_key));

    $results = [];
    foreach ($swap_transactions as $index => $swap) {
        $sub_tx_id = $sub_transaction_ids[$index];
        try {
            // Deserialize transaction
            $serialized_tx = $swap['tx'];
            $transaction = Transaction::from(base64_decode($serialized_tx));

            // Sign transaction
            $transaction->sign($keypair);

            // Send transaction
            $txid = $connection->sendRawTransaction($transaction->serialize());
            $connection->confirmTransaction($txid, 'confirmed');

            // Log success
            log_message("Swap transaction executed successfully: txid=$txid, sub_transaction_id=$sub_tx_id, network=$network", 'swap-raydium.log', 'make-market', 'INFO');
            $results[] = [
                'sub_transaction_id' => $sub_tx_id,
                'status' => 'success',
                'txid' => $txid,
                'loop' => $swap['loop'],
                'batch_index' => $swap['batch_index'],
                'direction' => $swap['direction']
            ];

            // Update sub-transaction status
            update_sub_transaction_status($sub_tx_id, 'success', $txid);
        } catch (Exception $e) {
            // Log error
            log_message("Swap transaction failed: sub_transaction_id=$sub_tx_id, error=" . $e->getMessage(), 'swap-raydium.log', 'make-market', 'ERROR');
            $results[] = [
                'sub_transaction_id' => $sub_tx_id,
                'status' => 'error',
                'message' => $e->getMessage(),
                'loop' => $swap['loop'],
                'batch_index' => $swap['batch_index'],
                'direction' => $swap['direction']
            ];
            update_sub_transaction_status($sub_tx_id, 'error', null, $e->getMessage());
        }
    }

    // Determine overall status
    $status = count(array_filter($results, fn($r) => $r['status'] === 'success')) === count($results) ? 'success' : 'partial';

    // Return response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'results' => $results,
        'message' => "$status: " . count(array_filter($results, fn($r) => $r['status'] === 'success')) . " of " . count($results) . " transactions completed"
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    log_message("Swap Raydium failed: " . $e->getMessage(), 'swap-raydium.log', 'make-market', 'ERROR');
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
