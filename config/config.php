<?php
// config.php

// Định nghĩa hằng số cấu hình
if (!defined('HELIUS_API_KEY')) {
    define('HELIUS_API_KEY', 'Api_key_Helius'); // API Key Helius
}
if (!defined('SOLSCAN_API_KEY')) {
    define('SOLSCAN_API_KEY', 'Api_Solana'); // API Key Solscan
}
if (!defined('ERROR_LOG_PATH')) {
    define('ERROR_LOG_PATH', '/var/www/vinanetwork/public_html/tools/error_log.txt'); // Đường dẫn log lỗi (đã sửa)
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/var/www/vinanetwork/public_html/'); // Đường dẫn gốc của ứng dụng (đã sửa)
}
?>
