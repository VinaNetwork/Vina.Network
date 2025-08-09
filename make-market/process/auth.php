<?php
// ============================================================================
// File: make-market/process/auth.php
// Description: Centralized authentication for Make Market
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'make-market/process/network.php';

// Session start: in config/bootstrap.php

/**
 * Check if request is AJAX
 * @param bool $enforce If true, reject non-AJAX requests
 * @return bool
 */
function check_ajax_request($enforce = true) {
    if ($enforce && (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) {
        log_message("Non-AJAX request rejected, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
        return false;
    }
    return true;
}

/**
 * Check if user is authenticated
 * @return bool
 */
function check_user_auth() {
    $session_id = session_id() ?: 'none';
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['public_key'])) {
        log_message(
            "Unauthorized access attempt: user_id=" . ($_SESSION['user_id'] ?? 'none') . 
            ", public_key=" . ($_SESSION['public_key'] ?? 'none') . 
            ", session_id=$session_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
            'make-market.log',
            'make-market',
            'ERROR'
        );
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Please log in to continue'], JSON_UNESCAPED_UNICODE);
        return false;
    }
    log_message(
        "Auth check passed: user_id={$_SESSION['user_id']}, public_key={$_SESSION['public_key']}, session_id=$session_id, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'make-market.log',
        'make-market',
        'INFO'
    );
    return true;
}

/**
 * Check if transaction belongs to the authenticated user
 * @param PDO $pdo Database connection
 * @param int $transaction_id Transaction ID
 * @return bool
 */
function check_transaction_ownership($pdo, $transaction_id) {
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
        $stmt->execute([$transaction_id, $_SESSION['user_id'], SOLANA_NETWORK]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transaction) {
            log_message(
                "Transaction not found, unauthorized, or network mismatch: ID=$transaction_id, session_user_id=" . ($_SESSION['user_id'] ?? 'none') . 
                ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
                'make-market.log',
                'make-market',
                'ERROR'
            );
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Transaction not found, unauthorized, or network mismatch'], JSON_UNESCAPED_UNICODE);
            return false;
        }
        return true;
    } catch (PDOException $e) {
        log_message(
            "Database query failed: {$e->getMessage()}, session_user_id=" . ($_SESSION['user_id'] ?? 'none') . 
            ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
            'make-market.log',
            'make-market',
            'ERROR'
        );
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking transaction ownership'], JSON_UNESCAPED_UNICODE);
        return false;
    }
}

/**
 * Perform full authentication check (AJAX, CSRF, user, and optional transaction ownership)
 * @param PDO|null $pdo Database connection (required for transaction ownership check)
 * @param int|null $transaction_id Transaction ID (optional)
 * @param bool $enforceAjax If true, require AJAX request
 * @return bool
 */
function perform_auth_check($pdo = null, $transaction_id = null, $enforceAjax = true) {
    if ($enforceAjax && !check_ajax_request(true)) return false;
    if (!check_user_auth()) return false;
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        log_message(
            "Invalid CSRF token, provided=$csrf_token, session=" . ($_SESSION['csrf_token'] ?? 'none') . 
            ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
            'make-market.log',
            'make-market',
            'ERROR'
        );
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
        return false;
    }
    log_message(
        "CSRF token validated: $csrf_token, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'make-market.log',
        'make-market',
        'INFO'
    );
    if ($pdo && $transaction_id !== null) {
        if (!check_transaction_ownership($pdo, $transaction_id)) return false;
    }
    log_message(
        "Authentication successful: session_user_id=" . ($_SESSION['user_id'] ?? 'none') . 
        ($transaction_id ? ", transaction_id=$transaction_id" : "") . 
        ", network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'),
        'make-market.log',
        'make-market',
        'INFO'
    );
    return true;
}
?>
