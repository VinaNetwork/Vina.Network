<?php
// File: accounts/set_session.php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$publicKey = $input['publicKey'] ?? '';

if (!empty($publicKey)) {
    $_SESSION['public_key'] = $publicKey;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid publicKey']);
}
?>
