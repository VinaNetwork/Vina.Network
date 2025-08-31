<?php
// ============================================================================
// File: core/csrf/clear-csrf.php
// Description: API to clear CSRF token after transaction completion
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth | network | csrf | vendor/autoload
require_once $root_path . 'core/bootstrap.php';

// Method check and AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Invalid request to clear-csrf, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check CSRF token
csrf_protect();

// Delete CSRF token
if (clear_csrf_token()) {
    log_message("CSRF token cleared successfully, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'bootstrap.log', 'logs', 'INFO');
    echo json_encode(['status' => 'success', 'message' => 'CSRF token cleared'], JSON_UNESCAPED_UNICODE);
} else {
    log_message("Failed to clear CSRF token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'bootstrap.log', 'logs', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to clear CSRF token'], JSON_UNESCAPED_UNICODE);
}
exit;
?>
