<?php
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Định nghĩa hằng số đường dẫn
define('ROOT_PATH', dirname(__DIR__) . '/');
define('TOOLS_PATH', ROOT_PATH . 'tools/');
define('NFT_HOLDERS_PATH', TOOLS_PATH . 'nft-holders/');

// Include config
require_once ROOT_PATH . 'config/config.php';

// Hàm ghi log
function log_message($message, $log_file = 'debug_log.txt', $log_type = 'INFO') {
    $log_path = TOOLS_PATH . $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$log_type] $message" . PHP_EOL;

    try {
        if (file_put_contents($log_path, $log_entry, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write log to $log_path: $message");
        }
    } catch (Exception $e) {
        error_log("Log error: " . $e->getMessage());
    }
}
?>
