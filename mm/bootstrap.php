<?php
// ============================================================================
// File: mm/bootstrap.php
// Description: Security check and utility functions for Make Market modules
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

$root_path = __DIR__ . '/../';
// General configuration
require_once $root_path . 'config/constants.php'; 		// Dynamic Domain Name Definition
require_once $root_path . 'config/logging.php'; 		  // Logging utilities
require_once $root_path . 'config/config.php'; 			  // Central configuration
require_once $root_path . 'config/error.php'; 			  // PHP configuration
require_once $root_path . 'config/session.php'; 		  // Initialize session with security options
require_once $root_path . 'config/db.php'; 				    // Database connection management
// Custom configuration
require_once $root_path . 'mm/header-auth.php'; 		  // Security Headers
require_once $root_path . 'mm/network/network.php'; 	// Devnet | Testnet | Mainnet
require_once $root_path . 'mm/csrf/csrf.php'; 			  // CSRF Token
require_once $root_path . '../vendor/autoload.php'; 	// Solana Library
?>
