<?php
// load-tool.php
header('Content-Type: application/json');

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode(['error' => 'Direct access not allowed']);
    exit;
}

$tool = isset($_GET['tool']) ? $_GET['tool'] : 'nft-holders';
if (!in_array($tool, ['nft-holders', 'nft-valuation', 'nft-transactions'])) {
    $tool = 'nft-holders';
}

$response = ['error' => null, 'html' => ''];
$tool_file = $tool . '.php';

if (file_exists($tool_file)) {
    ob_start();
    include $tool_file;
    $response['html'] = ob_get_clean();
} else {
    $response['error'] = 'Error: Tool not found.';
}

echo json_encode($response);
?>
