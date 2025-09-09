<?php
// File: mm/private-key/list-private-key.php
// Description: Trang hiển thị danh sách private key của tài khoản người dùng
// Created by: Vina Network

ob_start();
$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth | network | csrf | vendor/autoload
require_once $root_path . 'mm/bootstrap.php';

// Log request
log_message("list-private-key.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}", 'private-key-page.log', 'make-market', 'DEBUG');

// Protect POST requests with CSRF
csrf_protect();

// Set CSRF cookie for potential AJAX requests
if (!set_csrf_cookie()) {
    log_message("Failed to set CSRF cookie", 'private-key-page.log', 'make-market', 'ERROR');
} else {
    log_message("CSRF cookie set successfully for Make Market page", 'private-key-page.log', 'make-market', 'INFO');
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
if ($csrf_token === false) {
    log_message("Failed to generate CSRF token", 'private-key-page.log', 'make-market', 'ERROR');
} else {
    log_message("CSRF token generated successfully for Make Market page", 'private-key-page.log', 'make-market', 'INFO');
}

// Kết nối database
try {
    $pdo = get_db_connection();
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối cơ sở dữ liệu']);
    exit;
}

// Kiểm tra session
$public_key = $_SESSION['public_key'] ?? null;
$short_public_key = $public_key ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
if (!$public_key) {
    log_message("Không tìm thấy public key trong session, chuyển hướng đến login", 'private-key-page.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/mm/list-private-key';
    header('Location: /acc/connect');
    exit;
}

// Lấy user_id
try {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("Không tìm thấy tài khoản cho public_key: $short_public_key", 'private-key-page.log', 'make-market', 'ERROR');
        header('Location: /acc/connect');
        exit;
    }
    $user_id = $account['id'];
} catch (PDOException $e) {
    log_message("Lỗi truy vấn tài khoản: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn tài khoản']);
    exit;
}

// Lấy danh sách ví
try {
    $stmt = $pdo->prepare("SELECT id, wallet_name, public_key, private_key, status, created_at FROM private_key WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rút gọn public_key và private_key
    foreach ($wallets as &$wallet) {
        $wallet['short_public_key'] = substr($wallet['public_key'], 0, 4) . '...' . substr($wallet['public_key'], -4);
        // Private key đã mã hóa, chỉ lấy một phần để rút gọn (không giải mã)
        $wallet['short_private_key'] = substr($wallet['private_key'], 0, 4) . '...' . substr($wallet['private_key'], -4);
    }
} catch (PDOException $e) {
    log_message("Lỗi truy vấn danh sách ví: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn danh sách ví']);
    exit;
}

// SEO meta
$page_title = "Danh sách Private Key - Vina Network";
$page_description = "Xem và quản lý danh sách private key của bạn trên Vina Network.";
$page_css = ['/mm/private-key/list-private-key.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="mm-container">
	<div class="mm-content">
		<h1><i class="fas fa-key"></i> Danh sách Private Key</h1>
		
		<!-- Bảng danh sách ví -->
		<table class="wallet-table">
			<thead>
				<tr>
					<th>Tên ví</th>
					<th>Public Key</th>
					<th>Private Key</th>
					<th>Trạng thái</th>
					<th>Ngày tạo</th>
					<th>Hành động</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($wallets)): ?>
					<tr>
						<td colspan="6" style="text-align: center;">Chưa có ví nào được thêm.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($wallets as $wallet): ?>
						<tr>
							<td data-label="Tên ví"><?php echo htmlspecialchars($wallet['wallet_name'] ?: 'Ví #' . $wallet['id']); ?></td>
							<td data-label="Public Key">
								<a href="https://solscan.io/address/<?php echo htmlspecialchars($wallet['public_key']); ?>" target="_blank">
									<?php echo htmlspecialchars($wallet['short_public_key']); ?>
								</a>
								<i class="fas fa-copy copy-icon" title="Sao chép public key" data-full="<?php echo htmlspecialchars($wallet['public_key']); ?>"></i>
							</td>
							<td data-label="Private Key">
								<?php echo htmlspecialchars($wallet['short_private_key']); ?>
								<!-- Không cung cấp sao chép private key để bảo mật -->
							</td>
							<td data-label="Trạng thái"><?php echo htmlspecialchars($wallet['status']); ?></td>
							<td data-label="Ngày tạo"><?php echo htmlspecialchars($wallet['created_at']); ?></td>
							<td data-label="Hành động">
								<button class="deleteWallet cta-button" data-id="<?php echo $wallet['id']; ?>"><i class="fas fa-trash"></i> Xóa</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<div id="mm-result" class="status-box"></div>
	</div>
</div>
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts - Internal library -->
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>"></script>
<!-- Global variable -->
<script>
    // Passing JWT_SECRET into JavaScript securely
    const authToken = '<?php echo htmlspecialchars(JWT_SECRET); ?>';
</script>
<!-- Scripts - Source code -->
<script defer src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
<script defer src="/mm/private-key/list-private-key.js?t=<?php echo time(); ?>"></script>
</body>
</html>
<?php ob_end_flush(); ?>
