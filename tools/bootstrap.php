<?php
// ============================================================================
// File: tools/bootstrap.php
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
require_once $root_path . 'core/constants.php'; 		  // Dynamic Domain Name Definition
require_once $root_path . 'core/logging.php'; 		      // Logging utilities
require_once $root_path . 'core/error.php'; 			  // PHP configuration
require_once $root_path . 'core/session.php'; 		      // Initialize session with security options

// Custom configuration
require_once $root_path . 'tools/core/tools-load.php'; 	  // Load file
require_once $root_path . 'tools/core/tools-api.php'; 	  // API handles
require_once $root_path . 'tools/core/csrf.php'; 	      // CSRF Token
?>
