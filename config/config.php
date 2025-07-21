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
    define('JWT_SECRET', 'your-secret-key'); // Replace with a random string of at least 32 characters
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
    define('DB_PASS', 'your_password'); // Replace with the actual database password
}

// Helius API Configuration
if (!defined('HELIUS_API_KEY')) {
    define('HELIUS_API_KEY', '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63');
}
?>
