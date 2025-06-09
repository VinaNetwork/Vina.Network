<?php
define('VINANETWORK_ENTRY', true);
require_once '../config/config.php';

// Cấu hình log lỗi
$config_path = '../config/config.php';
if (!file_exists($config_path)) {
    error_log("Error: config.php not found at $config_path");
    die('Internal Server Error: Missing config.php');
}
include $config_path;
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Kiểm tra nếu không phải là yêu cầu AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    die('Direct access not allowed');
}

// Xác định chức năng được chọn
$tool = isset($_GET['tool']) ? $_GET['tool'] : 'nft-holders';
error_log("load-tool.php: tool = $tool"); // Debug
if (!in_array($tool, ['nft-holders', 'nft-valuation', 'nft-transactions', 'wallet-analysis'])) {
    $tool = 'nft-holders';
    error_log("load-tool.php: Invalid tool '$tool', defaulting to nft-holders");
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

// Kiểm tra và include file
if (isset($tool_file) && file_exists($tool_file)) {
    include $tool_file;
} else {
    echo "<p>Error: Tool not found.</p>";
}
?>
