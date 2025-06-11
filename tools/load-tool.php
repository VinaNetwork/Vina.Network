<?php
ob_start();
define('VINANETWORK', true);
require_once 'bootstrap.php';
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    die('Direct access not allowed');
}
$tool = isset($_GET['tool']) ? $_GET['tool'] : 'nft-holders';
log_message("load-tool: tool = $tool", 'tools_log.txt');
if (!in_array($tool, ['nft-holders', 'nft-valuation', 'nft-transactions', 'wallet-analysis'])) {
    $tool = 'nft-holders';
    log_message("load-tool: Invalid tool '$tool', defaulting to nft-holders", 'tools_log.txt', 'ERROR');
}
if ($tool === 'nft-holders') {
    $tool_file = 'nft-holders/nft-holders.php';
} elseif ($tool === 'nft-valuation') {
    $tool_file = 'nft-valuation.php';
} elseif ($tool === 'nft-transactions') {
    $tool_file = 'nft-transactions.php';
} elseif ($tool === 'wallet-analysis') {
    $tool_file = 'wallet-analysis.php';
}
if (isset($tool_file) && file_exists($tool_file)) {
    include $tool_file;
} else {
    echo "<p>Error: Tool not found.</p>";
    log_message("load-tool: Tool file not found: $tool_file", 'tools_log.txt', 'ERROR');
}
ob_end_flush();
?>
