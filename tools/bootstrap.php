<?php
// tools/bootstrap.php
if (!defined('VINANETWORK_STATUS')) {
    http_response_code(403);
    exit('Forbidden: Direct access not allowed.');
}

// Đường dẫn tới config.php
$config_path = dirname(__DIR__) . '/config/config.php';
if (!file_exists($config_path)) {
    error_log("Bootstrap: config.php not found at $config_path");
    http_response_code(500);
    exit('Internal Server Error: Missing config.php');
}
require_once $config_path;

// Cấu hình error logging
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Hàm ghi log
function log_message($message, $file = 'debug_log.txt', $level = 'INFO') {
    $log_path = dirname(__DIR__) . '/tools/' . $file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    file_put_contents($log_path, $log_entry, FILE_APPEND | LOCK_EX);
    if ($level === 'ERROR') {
        error_log($message);
    }
}

// Định nghĩa hằng số đường dẫn
define('TOOLS_PATH', dirname(__DIR__) . '/tools/');
define('NFT_HOLDERS_PATH', TOOLS_PATH . 'nft-holders/');
define('ROOT_PATH', dirname(__DIR__) . '/');
