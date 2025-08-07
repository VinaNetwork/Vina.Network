<?php
if (!defined('VINANETWORK_ENTRY')) {
    exit('Direct access not allowed');
}

function setup_cors_headers($csp_base, $methods = 'GET,POST') {
    header('Content-Type: application/json; charset=utf-8');
    header("Access-Control-Allow-Origin: $csp_base");
    header("Access-Control-Allow-Methods: $methods");
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
}

function check_ajax_request() {
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        log_message("Non-AJAX request rejected", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function check_authenticated_user() {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
        log_message("Unauthorized access attempt: session_user_id=" . ($_SESSION['user_id'] ?? 'none'), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $_SESSION['user_id'];
}

function get_db_connection_safe() {
    try {
        $pdo = get_db_connection();
        log_message("Database connection retrieved", 'make-market.log', 'make-market', 'INFO');
        return $pdo;
    } catch (Exception $e) {
        log_message("Database connection failed: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection error'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function log_request_info($script_name) {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        log_message("$script_name: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}, session_user_id=" . ($_SESSION['user_id'] ?? 'none'), 'make-market.log', 'make-market', 'DEBUG');
    }
}
?>
