<?php
// ============================================================================
// File: core/config.php
// Description: Central configuration file for Vina Network.
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Database Configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'vina_database');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'vina_user');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'vina_database_password');
}

// Helius API Configuration
if (!defined('HELIUS_API_KEY')) {
    define('HELIUS_API_KEY', 'helius_api_key');
}

// JWT Secret for encryption
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'jwt_secret_key');
}

// Solana Network Configuration
if (getenv('SOLANA_NETWORK') === false) {
    $solana_network = 'devnet'; // devnet | mainnet
    putenv("SOLANA_NETWORK={$solana_network}");
    $_ENV['SOLANA_NETWORK'] = $solana_network;
}

// Transaction fee per transaction (in SOL - mm/balance.php)
define('TRANSACTION_FEE', 0.0001);
?>
