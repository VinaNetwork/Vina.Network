<?php
/* ============================================================================
 * File: config/config.php (Need to rename config-sample.php to config.php)
 * Description: Central configuration file for Vina Network.
 *              Defines global constants such as API keys, base paths,
 *              error logging settings, security access control, JWT secret,
 *              and database credentials.
 * Created by: Vina Network
 * ============================================================================ */

// JWT Secret for authentication
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'your-secret-key'); // Replace with a random string of at least 32 characters
}

// Database Configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost'); // Replace with your database host (e.g., '127.0.0.1' or a remote server address)
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'vina'); // Replace with your actual database name
}
if (!defined('DB_USER')) {
    define('DB_USER', 'vina_user'); // Replace with your database username
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'your_password'); // Replace with the actual database password
}

// Helius API Configuration
if (!defined('HELIUS_API_KEY')) {
    define('HELIUS_API_KEY', 'your_helius_api_key'); // Replace with your actual Helius API key
}
?>
