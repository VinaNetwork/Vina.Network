<?php
// ============================================================================
// File: accounts/bootstrap.php
// Description: Security check and utility functions for Make Market modules
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Configuration
require_once __DIR__ . '/../core/constants.php'; 		  // Dynamic Domain Name Definition
require_once __DIR__ . '/../core/logging.php'; 	          // Logging utilities
require_once __DIR__ . '/../core/config.php'; 			  // Central configuration
require_once __DIR__ . '/../core/error.php'; 			  // PHP configuration
require_once __DIR__ . '/../core/session.php'; 		      // Initialize session with security options
require_once __DIR__ . '/../core/db.php'; 				  // Database connection management
require_once __DIR__ . '/../core/header-auth.php'; 		  // Security Headers
require_once __DIR__ . '/../core/csrf.php'; 	          // CSRF Token
require_once __DIR__ . '/../../vendor/autoload.php'; 	  // Solana Library
require_once __DIR__ . '/wallet-auth.php';                // Connect wallet
?>
