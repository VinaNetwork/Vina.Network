<?php
// ============================================================================
// File: tools/tools-load.php
// Description: Load separate tools.
// Created by: Vina Network
// ============================================================================

// Ensure essential constants are defined
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Load the bootstrap file for shared configurations
$bootstrap_path = __DIR__ . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    // Fallback to error_log since bootstrap.php (and log_message) is unavailable
    error_log("load-tool: bootstrap.php not found at $bootstrap_path");
    http_response_code(500);
    echo '<div class="result-error"><p>Error: bootstrap.php not found</p></div>';
    exit;
}
require_once $bootstrap_path;

// Get the requested tool from the query string
$tool = isset($_GET['tool']) ? trim($_GET['tool']) : '';
log_message("load-tool: Request received - tool=$tool, method={$_SERVER['REQUEST_METHOD']}", 'tools_load_log.txt', 'INFO');

// Validate the 'tool' parameter against allowed values
$valid_tools = ['nft-holders', 'nft-info', 'wallet-analysis'];
if (!in_array($tool, $valid_tools)) {
    log_message("load-tool: Invalid tool parameter - tool=$tool", 'tools_load_log.txt', 'ERROR');
    http_response_code(400);
    echo '<div class="result-error"><p>Error: Invalid tool parameter</p></div>';
    exit;
}

// Define corresponding file path for each tool
$tool_files = [
    'nft-holders' => 'nft-holders/nft-holders.php',
    'nft-info' => 'nft-info/nft-info.php',
    'wallet-analysis' => 'wallet-analysis/wallet-analysis.php'
];

$tool_file = __DIR__ . '/' . ($tool_files[$tool] ?? '');
log_message("load-tool: Attempting to include file - $tool_file", 'tools_load_log.txt', 'INFO');

// Check if the tool file exists
if (!file_exists($tool_file)) {
    log_message("load-tool: Tool file not found - $tool_file", 'tools_load_log.txt', 'ERROR');
    http_response_code(404);
    echo '<div class="result-error"><p>Error: Tool file not found</p></div>';
    exit;
}

// Attempt to include the tool file and return its output
try {
    ob_start();
    include $tool_file;
    $output = ob_get_clean();
    log_message("load-tool: Output length: " . strlen($output) . ", output_preview=" . substr(htmlspecialchars($output), 0, 200), 'tools_load_log.txt', 'INFO');
    echo $output;
} catch (Exception $e) {
    log_message("load-tool: Exception in $tool_file - " . $e->getMessage() . " at line " . $e->getLine(), 'tools_load_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Error: Failed to load tool - ' . htmlspecialchars($e->getMessage()) . '</p></div>';
}
?>
