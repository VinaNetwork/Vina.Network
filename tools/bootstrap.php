<?php
// ============================================================================
// File: tools/bootstrap.php
// Description: Security check and utility functions for Accounts modules
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Configuration
require_once __DIR__ . '/../core/constants.php'; 		  // Dynamic Domain Name Definition
require_once __DIR__ . '/../core/logs/logging.php'; 	  // Logging utilities
require_once __DIR__ . '/../core/config.php'; 			  // Central configuration
require_once __DIR__ . '/../core/error.php'; 			  // PHP configuration
require_once __DIR__ . '/../core/session.php'; 		      // Initialize session with security options
require_once __DIR__ . '/core/csrf.php'; 	              // CSRF Token
?>
