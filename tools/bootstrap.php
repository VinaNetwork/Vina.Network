<?php
// bootstrap.php
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Include config.php
$config_path = dirname(__DIR__) . '/config/config.php';
if (!file_exists($config_path)) {
    error_log("Error: config.php not found at $config_path");
    die('Internal Server Error: Missing config.php');
}
require_once $config_path;

// Cấu hình log lỗi
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Khởi tạo session
session_start();
?>
