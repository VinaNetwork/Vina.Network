<?php
// ============================================================================
// File: config/bootstrap.php
// Description: Security check and utility functions for Vina Network modules
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Website root directory
define('ROOT_PATH', dirname(__DIR__) . '/');
// Load configuration
define('CONFIG_PATH', ROOT_PATH . 'config/');
require_once CONFIG_PATH . 'constants.php';
require_once CONFIG_PATH . 'logging.php';
require_once CONFIG_PATH . 'config.php';
require_once CONFIG_PATH . 'error.php';
require_once CONFIG_PATH . 'session.php';
require_once CONFIG_PATH . 'csrf.php';
require_once CONFIG_PATH . 'db.php';
?>
