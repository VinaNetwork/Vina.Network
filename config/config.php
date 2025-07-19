<?php
/* ============================================================================
 * File: config/config.php
 * Description: Central configuration file for Vina Network.
 *              Defines global constants such as API keys, base paths,
 *              error logging settings, security access control, JWT secret,
 *              and database credentials.
 * Created by: Vina Network
 * ============================================================================ */

// Prevent direct access to this config file
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct script access allowed!');
}

// JWT Secret for authentication
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'your-secret-key'); // Thay bằng chuỗi ngẫu nhiên mạnh
}

// Database Configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'vina');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'vina_user');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'your_password'); // Thay bằng mật khẩu thực tế
}

// Helius API Configuration
if (!defined('HELIUS_API_KEY')) {
    define('HELIUS_API_KEY', '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63');
}

// Logging and Path Configuration
if (!defined('ERROR_LOG_PATH')) {
    define('ERROR_LOG_PATH', '/var/www/vinanetwork/public_html/tools/error_log.txt');
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/var/www/vinanetwork/public_html/');
}
?>
