<?php
/*
 * File: config/config.php
 * Description: Central configuration file for Vina Network.
 * Created by: Vina Network
 */

if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// JWT Secret for authentication
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'your_random_string_of_at_least_32_characters');
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
    define('DB_PASS', 'your_db_password');
}

// Helius API Configuration
if (!defined('HELIUS_API_KEY')) {
    define('HELIUS_API_KEY', 'your_helius_api_key');
}
?>
