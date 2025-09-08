<?php
// File: mm/private-key/delete-private-key.php
$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ']);
    exit;
}

csrf_protect();
$pdo = get_db_connection();
$walletId = $_POST['walletId'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$walletId || !$user_id) {
    log_message("Thiếu walletId hoặc user_id", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM private_key WHERE id = ? AND user_id = ?");
    $stmt->execute([$walletId, $user_id]);
    if ($stmt->rowCount() > 0) {
        log_message("Xóa ví ID=$walletId cho user_id=$user_id", 'make-market.log', 'make-market', 'INFO');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Xóa ví thành công']);
    } else {
        log_message("Không tìm thấy ví ID=$walletId", 'make-market.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy ví']);
    }
} catch (PDOException $e) {
    log_message("Lỗi xóa ví: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Lỗi xóa ví']);
}
exit;
?>
