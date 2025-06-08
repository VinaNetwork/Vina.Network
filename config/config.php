<?php
// config.php
// Yêu cầu bắt buộc muốn include file config.php thì phải có VINANETWORK_ENTRY
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct script access allowed!');
}

// Định nghĩa hằng số cấu hình
if (!defined('HELIUS_API_KEY')) {
    define('HELIUS_API_KEY', '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63'); // API Key Helius
}
if (!defined('SOLSCAN_API_KEY')) {
    define('SOLSCAN_API_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJjcmVhdGVkQXQiOjE3NDgyNDcyMjI3OTgsImVtYWlsIjoibjl1OTNuQGdtYWlsLmNvbSIsImFjdGlvbiI6InRva2VuLWFwaSIsImFwaVZlcnNpb24iOiJ2MiIsImlhdCI6MTc0ODI0NzIyMn0.ukV8lKST8a1G46dA8rc3yu-CtZ90nxDI50o0q4xvgMk'); // API Key Solscan
}
if (!defined('ERROR_LOG_PATH')) {
    define('ERROR_LOG_PATH', '/var/www/vinanetwork/public_html/tools/error_log.txt'); // Đường dẫn log lỗi (đã sửa)
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/var/www/vinanetwork/public_html/'); // Đường dẫn gốc của ứng dụng (đã sửa)
}
?>
