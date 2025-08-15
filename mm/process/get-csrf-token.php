<?php
// ============================================================================
// File: mm/process/get-csrf-token.php
// Description: Generate and return a new CSRF token
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/csrf.php';
require_once $root_path . 'mm/network.php';
require_once $root_path . 'mm/header-auth.php';

// Log request details
$session_id = session_id() ?: 'none';
$headers = apache_request_headers();
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$cookies = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    $csrf_token = isset($_SESSION[CSRF_TOKEN_NAME]) ? $_SESSION[CSRF_TOKEN_NAME] : 'none';
    log_message("get-csrf-token.php: Request received, method=$request_method, uri=$request_uri, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers) . ", CSRF_TOKEN: $csrf_token", 'make-market.log', 'make-market', 'DEBUG');
}

// Khởi tạo session
if (!ensure_session()) {
    log_message("Failed to initialize session, method=$request_method, uri=$request_uri, session_id=$session_id, cookies=$cookies", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra phương thức GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    log_message("Invalid request method: $request_method, uri=$request_uri, session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Tạo hoặc lấy CSRF token
try {
    csrf_protect(false); // Không kiểm tra token, chỉ tạo/lấy token
    $csrf_token = $_SESSION[CSRF_TOKEN_NAME];
    log_message("CSRF token retrieved: $csrf_token, session_id=$session_id", 'make-market.log', 'make-market', 'INFO');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'csrfToken' => $csrf_token], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Failed to retrieve CSRF token: " . $e->getMessage() . ", method=$request_method, uri=$request_uri, session_id=$session_id, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve CSRF token'], JSON_UNESCAPED_UNICODE);
}
?>
