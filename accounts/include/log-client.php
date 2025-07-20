<?php
// accounts/include/log-client.php
header('Content-Type: application/json');
require_once '../../config/bootstrap.php';

$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message'] ?? '';
if ($message) {
    log_message("Client: $message", 'auth.log', 'INFO');
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid log message']);
}
?>
