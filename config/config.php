<?php
// config.php

// Định nghĩa hằng số cấu hình
define('HELIUS_API_KEY', '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63'); // API Key Helius
define('ERROR_LOG_PATH', '/home/hthxhyqf/domains/vina.network/public_html/tools/error_log.txt'); // Đường dẫn log lỗi
define('BASE_PATH', '/home/hthxhyqf/domains/vina.network/'); // Đường dẫn gốc của ứng dụng

// Cấu hình log lỗi
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);
?>
