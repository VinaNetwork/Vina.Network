<?php
// ============================================================================
// File: acc/bootstrap.php
// Description: Security check and utility functions for Make Market modules
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
// General configuration
require_once $root_path . 'core/constants.php'; 		    // Dynamic Domain Name Definition
require_once $root_path . 'core/logging.php'; 	        // Logging utilities
require_once $root_path . 'core/config.php'; 			      // Central configuration
require_once $root_path . 'core/error.php'; 			      // PHP configuration
require_once $root_path . 'core/session.php'; 		      // Initialize session with security options
require_once $root_path . 'core/db.php'; 				        // Database connection management
require_once $root_path . 'core/header-auth.php'; 		  // Security Headers
require_once $root_path . '../vendor/autoload.php'; 	  // Solana Library
?>
