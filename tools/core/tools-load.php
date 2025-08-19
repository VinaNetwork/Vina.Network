<?php
// ============================================================================
// File: tools/core/tools-load.php
// Description: Load separate tools and wallet analysis tabs.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'tools/bootstrap.php';

$tool = isset($_GET['tool']) ? trim($_GET['tool']) : '';
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : '';

log_message("load-tool: Request received - tool=$tool, tab=$tab, method={$_SERVER['REQUEST_METHOD']}", 'tools-load.log', 'tools', 'INFO');

$valid_tools = ['nft-info', 'nft-holders', 'nft-transactions', 'nft-creators', 'wallet-analysis'];
if (!in_array($tool, $valid_tools)) {
    log_message("load-tool: Invalid tool parameter - tool=$tool", 'tools-load.log', 'tools', 'ERROR');
    http_response_code(400);
    echo '<div class="result-error"><p>Error: Invalid tool parameter</p></div>';
    exit;
}

$tool_files = [
    'nft-info' => '../nft-info/nft-info.php',
    'nft-holders' => '../nft-holders/nft-holders.php',
    'nft-transactions' => '../nft-transactions/nft-transactions.php',
    'nft-creators' => '../nft-creators/nft-creators.php',
    'wallet-analysis' => '../wallet-analysis/wallet-analysis.php'
];

if ($tool === 'wallet-analysis' && $tab) {
    $valid_tabs = ['token', 'nft', 'domain'];
    if (!in_array($tab, $valid_tabs)) {
        log_message("load-tool: Invalid tab parameter - tab=$tab", 'tools-load.log', 'tools', 'ERROR');
        http_response_code(400);
        echo '<div class="result-error"><p>Error: Invalid tab parameter</p></div>';
        exit;
    }
    $tool_file = __DIR__ . "/../wallet-analysis/$tab.php";
} else {
    $tool_file = __DIR__ . '/' . ($tool_files[$tool] ?? '');
}

log_message("load-tool: Attempting to include file - $tool_file", 'tools-load.log', 'tools', 'INFO');

if (!file_exists($tool_file)) {
    log_message("load-tool: Tool file not found - $tool_file", 'tools-load.log', 'tools', 'ERROR');
    http_response_code(404);
    echo '<div class="result-error"><p>Error: Tool file not found</p></div>';
    exit;
}

try {
    ob_start();
    include $tool_file;
    $output = ob_get_clean();
    log_message("load-tool: Output length: " . strlen($output) . ", output_preview=" . substr(htmlspecialchars($output), 0, 200), 'tools-load.log', 'tools', 'INFO');
    echo $output;
} catch (Throwable $e) {
    log_message("load-tool: Exception in $tool_file - " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine(), 'tools-load.log', 'tools', 'ERROR');
    http_response_code(500);
    echo '<div class="result-error"><p>Error: Failed to load tool - ' . htmlspecialchars($e->getMessage()) . '</p></div>';
}
?>
