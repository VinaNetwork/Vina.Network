<?php
// config/config.php
if (!defined('VINANETWORK_STATUS')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Định nghĩa hằng số cấu hình
if (!defined('HELIUS_API_KEY')) {
    define('HELIUS_API_KEY', '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63');
}
if (!defined('ERROR_LOG_PATH')) {
    define('ERROR_LOG_PATH', TOOLS_PATH . 'error_log.txt');
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', ROOT_PATH);
}
