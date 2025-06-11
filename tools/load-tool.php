<?php
ob_start();
define('VINANETWORK_STATUS', true);
require_once 'bootstrap.php';

log_message('load-tool.php: Script started');

// Kiểm tra yêu cầu AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    log_message('load-tool.php: Direct access attempted', 'error_log.txt', 'ERROR');
    die('Direct access not allowed');
}

$tool = isset($_GET['tool']) ? $_GET['tool'] : 'nft-tools';
log_message("load-tool.php: tool = $tool");

if (!in_array($tool, ['nft-tools'])) {
    $tool = 'nft-tools';
    log_message("load-tool.php: Invalid tool '$tool', defaulted to nft-tools", 'error_log.txt', 'ERROR');
}

if ($tool === 'nft-tools') {
    $tool_file = NFT_HOLDERS_PATH . 'nft-tools.php';
}

if (isset($tool_file) && file_exists($tool_file)) {
    include $tool_file;
} else {
    echo "<p>Error: Tool not found.</p>";
    log_message("load-tool.php: Tool file not found at $tool_file", 'error_log.txt', 'ERROR');
}
ob_end_flush();
?>
