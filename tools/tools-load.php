<?php
// tools/tools-load.php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}
$bootstrap_path = __DIR__ . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    error_log("load-tool: bootstrap.php not found at $bootstrap_path");
    http_response_code(500);
    echo 'Error: bootstrap.php not found';
    exit;
}
require_once $bootstrap_path;

session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

$tool = isset($_GET['tool']) ? trim($_GET['tool']) : '';
log_message("load-tool: Request received - tool=$tool, method={$_SERVER['REQUEST_METHOD']}", 'load_tool_log.txt');

// Validate tool parameter
$valid_tools = ['nft-holders', 'nft-valuation', 'nft-transactions', 'wallet-analysis'];
if (!in_array($tool, $valid_tools)) {
    log_message("load-tool: Invalid tool parameter - tool=$tool", 'load_tool_log.txt', 'ERROR');
    http_response_code(400);
    echo 'Error: Invalid tool parameter';
    exit;
}

// Determine the tool file to include
$tool_files = [
    'nft-holders' => 'nft-holders/nft-holders.php',
    'nft-valuation' => 'nft-valuation/nft-valuation.php',
    'nft-transactions' => 'nft-transactions/nft-transactions.php',
    'wallet-analysis' => 'wallet-analysis/wallet-analysis.php'
];

$tool_file = __DIR__ . '/' . $tool_files[$tool];
log_message("load-tool: Attempting to include file - $tool_file", 'load_tool_log.txt');

if (!file_exists($tool_file)) {
    log_message("load-tool: Tool file not found - $tool_file", 'load_tool_log.txt', 'ERROR');
    http_response_code(404);
    echo 'Error: Tool file not found';
    exit;
}

// Include the tool file
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
