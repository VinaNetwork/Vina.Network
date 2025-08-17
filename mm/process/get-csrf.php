<?php
// ============================================================================
// File: mm/process/get-csrf.php
// Description: Endpoint to retrieve CSRF token for client-side AJAX requests
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php'; // constants | logging | config | error | session | csrf | database
require_once $root_path . 'mm/header-auth.php';

// Log request details
$session_id = session_id() ?: 'none';
$headers = apache_request_headers();
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$cookies = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none';
$log_context = [
    'endpoint' => 'get-csrf',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
];

if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    $csrf_token = isset($_SESSION[CSRF_TOKEN_NAME]) ? $_SESSION[CSRF_TOKEN_NAME] : 'none';
    log_message("get-csrf.php: Request received, method=$request_method, uri=$request_uri, session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers) . ", CSRF_TOKEN: $csrf_token", 'process.log', 'make-market', 'DEBUG', $log_context);
}

// Kiểm tra phương thức GET
if ($request_method !== 'GET') {
    log_message("Invalid request method: $request_method, uri=$request_uri, session_id=$session_id, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Khởi tạo session
if (!ensure_session()) {
    log_message("Failed to initialize session, method=$request_method, uri=$request_uri, session_id=$session_id, cookies=$cookies", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Tạo hoặc lấy CSRF token
$csrf_token = generate_csrf_token();
if ($csrf_token === false) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Failed to generate CSRF token, user_id=$user_id", 'process.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Đặt CSRF token vào cookie (dành cho các yêu cầu AJAX khác nếu cần)
if (!set_csrf_cookie()) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("Failed to set CSRF cookie, user_id=$user_id", 'process.log', 'make-market', 'WARNING', $log_context);
}

// Trả về CSRF token
log_message("CSRF token sent: $csrf_token, session_id=$session_id, user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none'), 'process.log', 'make-market', 'INFO', $log_context);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'csrfToken' => $csrf_token
], JSON_UNESCAPED_UNICODE);
exit;
?>
