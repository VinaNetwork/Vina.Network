<?php
// ============================================================================
// File: make-market/process/get-csrf.php
// Description: Generate and return CSRF token
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
try {
    log_message("Attempting to load bootstrap.php", 'make-market.log', 'make-market', 'DEBUG');
    require_once $root_path . 'config/bootstrap.php';
    log_message("bootstrap.php loaded successfully", 'make-market.log', 'make-market', 'DEBUG');
    require_once $root_path . 'make-market/process/auth.php';
    log_message("auth.php loaded successfully", 'make-market.log', 'make-market', 'DEBUG');

    log_message("get-csrf.php started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}, session_id: " . session_id(), 'make-market.log', 'make-market', 'DEBUG');

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        log_message("Invalid request method: {$_SERVER['REQUEST_METHOD']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!check_ajax_request()) {
        exit;
    }

    if (!check_user_auth()) {
        exit; // Returns HTTP 401 from check_user_auth()
    }

    // Generate CSRF token
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;

    log_message("CSRF token generated: $csrf_token, session_user_id=" . ($_SESSION['user_id'] ?? 'none') . ", session_id: " . session_id(), 'make-market.log', 'make-market', 'INFO');

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'csrf_token' => $csrf_token
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    log_message("get-csrf.php error: {$e->getMessage()}, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
