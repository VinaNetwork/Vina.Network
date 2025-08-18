<?php
// ============================================================================
// File: accounts/bootstrap.php
// Description: Security check and utility functions for Accounts modules
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

$root_path = __DIR__ . '/../';
// General configuration
require_once $root_path . 'config/constants.php'; 		  // Dynamic Domain Name Definition
require_once $root_path . 'config/logging.php'; 		    // Logging utilities
require_once $root_path . 'config/config.php'; 			    // Central configuration
require_once $root_path . 'config/error.php'; 			    // PHP configuration
require_once $root_path . 'config/session.php'; 		    // Initialize session with security options
require_once $root_path . 'config/db.php'; 				      // Database connection management
// Custom configuration
require_once $root_path . 'accounts/header-auth.php'; 	// Security Headers
require_once $root_path . 'accounts/csrf/csrf.php'; 	  // CSRF Token
require_once $root_path . 'accounts/wallet-auth.php'; 	// API handles Solana wallet signature verification
?>
