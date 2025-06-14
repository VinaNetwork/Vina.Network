<?php
// config.php
// Điều kiện truy cập config.php
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct script access allowed!');
}

// API HELIUS
if (!defined('HELIUS_API_KEY')) {
    define('HELIUS_API_KEY', '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63'); // API Key Helius
}

// Folder Error
if (!defined('ERROR_LOG_PATH')) {
    define('ERROR_LOG_PATH', '/var/www/vinanetwork/public_html/tools/error_log.txt');
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/var/www/vinanetwork/public_html/');
}
?>
