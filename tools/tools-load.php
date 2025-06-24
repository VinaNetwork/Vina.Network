<?php
// ============================================================================
// File: tools/tools-load.php
// Description: Load separate tools and wallet analysis tabs.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = __DIR__ . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    error_log("load-tool: bootstrap.php not found at $bootstrap_path");
    http_response_code(500);
    echo '<div class="result-error"><p>Error: bootstrap.php not found</p></div>';
    exit;
}
require_once $bootstrap_path;

$tool = isset($_GET['tool']) ? trim($_GET['tool']) : '';
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : '';

log_message("load-tool: Request received - tool=$tool, tab=$tab, method={$_SERVER['REQUEST_METHOD']}", 'tools_load_log.txt', 'INFO');

$valid_tools = ['nft-holders', 'nft-info', 'wallet-analysis'];
if (!in_array($tool, $valid_tools)) {
    log_message("load-tool: Invalid tool parameter - tool=$tool", 'tools_load_log.txt', 'ERROR');
    http_response_code(400);
    echo '<div class="result-error"><p>Error: Invalid tool parameter</p></div>';
    exit;
}

$tool_files = [
    'nft-holders' => 'nft-holders/nft-holders.php',
    'nft-info' => 'nft-info/nft-info.php',
    'wallet-analysis' => 'wallet-analysis/wallet-analysis.php'
];

if ($tool === 'wallet-analysis' && $tab) {
    $valid_tabs = ['token', 'nft', 'domain'];
    if (!in_array($tab, $valid_tabs)) {
        log_message("load-tool: Invalid tab parameter - tab=$tab", 'tools_load_log.txt', 'ERROR');
        http_response_code(400);
        echo '<div class="result-error"><p>Error: Invalid tab parameter</p></div>';
        exit;
    }
    $tool_file = __DIR__ . "/wallet-analysis/$tab.php";
} else {
    $tool_file = __DIR__ . '/' . ($tool_files[$tool] ?? '');
}

log_message("load-tool: Attempting to include file - $tool_file", 'tools_load_log.txt', 'INFO');

if (!file_exists($tool_file)) {
    log_message("load-tool: Tool file not found - $tool_file", 'tools_load_log.txt', 'ERROR');
    http_response_code(404);
    echo '<div class="result-error"><p>Error: Tool file not found</p></div>';
    exit;
}

try {
    ob_start();
    include $tool_file;
    $output = ob_get_clean();
    log_message("load-tool: Output length: " . strlen($output) . ", output_preview=" . substr(htmlspecialchars($output), 0, 200), 'tools_load_log.txt', 'INFO');
    echo $output;
} catch (Throwable $e) {
    log_message("load-tool: Exception in $tool_file - " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine(), 'tools_load_log.txt', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Error: Failed to load tool - ' . htmlspecialchars($e->getMessage()) . '</p></div>';
}
?>
