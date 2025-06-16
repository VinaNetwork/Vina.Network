<?php
/*
 * tools/tools-load.php - Dynamic Loader for NFT Tool Modules
 *
 * This file dynamically loads and serves the appropriate tool module 
 * (e.g., NFT Holders, NFT Valuation, etc.) based on the "tool" parameter 
 * passed via the URL query string.
 *
 * It performs the following:
 * - Verifies necessary constants and configurations
 * - Loads shared settings from bootstrap.php
 * - Validates and maps the 'tool' parameter to its corresponding PHP file
 * - Logs all activities for debugging and security auditing
 * - Handles errors gracefully (404, 400, 500 responses)
 * - Outputs the content of the selected tool module
 */

// Disable error display in the browser for security
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Ensure core constants are defined
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Load global configurations and utility functions
$bootstrap_path = __DIR__ . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    error_log("load-tool: bootstrap.php not found at $bootstrap_path");
    http_response_code(500);
    echo 'Error: bootstrap.php not found';
    exit;
}
require_once $bootstrap_path;

// Initialize session and enable logging
session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

// Get requested tool from query string
$tool = isset($_GET['tool']) ? trim($_GET['tool']) : '';
log_message("load-tool: Request received - tool=$tool, method={$_SERVER['REQUEST_METHOD']}", 'load_tool_log.txt');

// Allow only specific tools for security
$valid_tools = ['nft-holders', 'nft-valuation', 'nft-transactions', 'wallet-analysis'];
if (!in_array($tool, $valid_tools)) {
    log_message("load-tool: Invalid tool parameter - tool=$tool", 'load_tool_log.txt', 'ERROR');
    http_response_code(400);
    echo 'Error: Invalid tool parameter';
    exit;
}

// Map tool name to its corresponding PHP file
$tool_files = [
    'nft-holders' => 'nft-holders/nft-holders.php',
    'nft-valuation' => 'nft-valuation/nft-valuation.php',
    'nft-transactions' => 'nft-transactions/nft-transactions.php',
    'wallet-analysis' => 'wallet-analysis/wallet-analysis.php'
];

$tool_file = __DIR__ . '/' . $tool_files[$tool];
log_message("load-tool: Attempting to include file - $tool_file", 'load_tool_log.txt');

// If the tool file is missing, return 404
if (!file_exists($tool_file)) {
    log_message("load-tool: Tool file not found - $tool_file", 'load_tool_log.txt', 'ERROR');
    http_response_code(404);
    echo 'Error: Tool file not found';
    exit;
}

// Safely include and output the selected tool file
try {
    ob_start();
    include $tool_file;
    $output = ob_get_clean();
    log_message("load-tool: Output length: " . strlen($output), 'load_tool_log.txt');
    echo $output;
} catch (Exception $e) {
    log_message("load-tool: Exception in $tool_file - " . $e->getMessage(), 'load_tool_log.txt', 'ERROR');
    http_response_code(500);
    echo 'Error: Failed to load tool - ' . htmlspecialchars($e->getMessage());
}
