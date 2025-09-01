<?php
// ============================================================================
// File: core/bootstrap.php
// Description: Security check and utility functions for Make Market modules
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Configuration
require_once __DIR__ . '/constants.php'; 		          // Dynamic Domain Name Definition
require_once __DIR__ . '/logs/logging.php'; 		      // Logging utilities
require_once __DIR__ . '/config.php'; 			          // Central configuration
require_once __DIR__ . '/error.php'; 			          // PHP configuration
require_once __DIR__ . '/session.php'; 		              // Initialize session with security options
require_once __DIR__ . '/db.php'; 				          // Database connection management
require_once __DIR__ . '/header-auth.php'; 		          // Security Headers
require_once __DIR__ . '/csrf/csrf.php'; 	              // CSRF Token
require_once __DIR__ . '/../../vendor/autoload.php'; 	  // Solana Library
?>
