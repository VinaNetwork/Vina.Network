<?php
// Cấu hình log lỗi
ini_set('log_errors', 1);
ini_set('error_log', '/home/hthxhyqf/domains/vina.network/public_html/tools/error_log.txt');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Kiểm tra nếu không phải là yêu cầu AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    die('Direct access not allowed');
}

// Xác định chức năng được chọn
$tool = isset($_GET['tool']) ? $_GET['tool'] : 'nft-holders';
if (!in_array($tool, ['nft-holders', 'nft-valuation'])) {
    $tool = 'nft-holders'; // Chỉ giữ 2 tab
}

// Include file tương ứng
if ($tool === 'nft-holders') {
    $tool_file = 'nft-holders.php';
} elseif ($tool === 'nft-valuation') {
    $tool_file = 'nft-valuation.php';
}

// Kiểm tra và include file
if (isset($tool_file) && file_exists($tool_file)) {
    include $tool_file;
} else {
    echo "<p>Error: Tool not found.</p>";
}
?>
