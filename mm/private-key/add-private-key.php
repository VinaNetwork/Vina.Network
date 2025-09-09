<?php
// ============================================================================
// File: mm/private-key/add-private-key.php
// Description: Trang thêm và quản lý private key cho người dùng
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

use Attestto\SolanaPhpSdk\Keypair;
use StephenHill\Base58;

// Log request
log_message("add-private-key.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}", 'private-key-page.log', 'make-market', 'DEBUG');

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
if (!$public_key) {
    log_message("Không tìm thấy public key trong session, chuyển hướng đến login", 'private-key-page.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/mm/add-private-key';
    header('Location: /acc/connect');
    exit;
}

// Lấy user_id
try {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("Không tìm thấy tài khoản cho public_key: $public_key", 'private-key-page.log', 'make-market', 'ERROR');
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

// Xử lý form thêm private key
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $privateKeys = $_POST['privateKeys'] ?? [];
    $walletNames = $_POST['walletNames'] ?? [];
    $errors = [];

    // Kiểm tra và mã hóa private keys
    $base58 = new Base58();
    $validWallets = [];
    foreach ($privateKeys as $index => $privateKey) {
        $privateKey = trim($privateKey);
        $walletName = trim($walletNames[$index] ?? "Ví $index");

        if (empty($privateKey)) {
            $errors[] = "Private key thứ $index rỗng";
            continue;
        }

        // Kiểm tra định dạng
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{64,128}$/', $privateKey)) {
            $errors[] = "Private key thứ $index không đúng định dạng";
            continue;
        }

        // Giải mã và suy ra public key
        try {
            $decodedKey = $base58->decode($privateKey);
            if (strlen($decodedKey) !== 64) {
                $errors[] = "Private key thứ $index có độ dài không hợp lệ";
                continue;
            }
            $keypair = Keypair::fromSecretKey($decodedKey);
            $publicKey = $keypair->getPublicKey()->toBase58();

            // Mã hóa private key
            $encryptedPrivateKey = openssl_encrypt($privateKey, 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
            if ($encryptedPrivateKey === false) {
                $errors[] = "Lỗi mã hóa private key thứ $index: " . openssl_error_string();
                continue;
            }

            $validWallets[] = [
                'public_key' => $publicKey,
                'private_key' => $encryptedPrivateKey,
                'wallet_name' => $walletName
            ];
        } catch (Exception $e) {
            $errors[] = "Private key thứ $index không hợp lệ: {$e->getMessage()}";
        }
    }

    // Lưu vào database
    if (empty($errors) && !empty($validWallets)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO private_key (user_id, public_key, private_key, wallet_name)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($validWallets as $wallet) {
                $stmt->execute([$user_id, $wallet['public_key'], $wallet['private_key'], $wallet['wallet_name']]);
            }
            $pdo->commit();
            log_message("Lưu thành công " . count($validWallets) . " private key cho user_id=$user_id", 'private-key-page.log', 'make-market', 'INFO');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Lưu private key thành công']);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            log_message("Lỗi lưu private key: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu private key']);
            exit;
        }
    } else {
        log_message("Lỗi xác thực private key: " . implode(", ", $errors), 'private-key-page.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => implode(", ", $errors)]);
        exit;
    }
}

// Lấy danh sách ví hiện có
$stmt = $pdo->prepare("SELECT id, wallet_name, public_key, created_at, status FROM private_key WHERE user_id = ?");
$stmt->execute([$user_id]);
$existingWallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SEO meta
$page_title = "Quản lý Private Key - Vina Network";
$page_description = "Thêm và quản lý private key cho giao dịch Solana trên Vina Network.";
$page_css = ['/mm/private-key/add-private-key.css'];
?>

<!DOCTYPE html>
<html lang="vi">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="mm-container">
	<div class="mm-content">
		<h1><i class="fas fa-key"></i> Quản lý Private Key</h1>
		
		<!-- Form thêm private key -->
		<form id="addPrivateKeyForm" method="POST">
			<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
			<div id="privateKeysContainer">
				<div class="privateKeyRow">
					<label>Tên ví (tùy chọn):</label>
					<input type="text" name="walletNames[]" placeholder="Nhập tên ví...">
					<label>Private Key:</label>
					<textarea name="privateKeys[]" required placeholder="Nhập private key..."></textarea>
					<button type="button" class="removeKey">Xóa</button>
				</div>
			</div>
			<button class="cta-button" type="button" id="addPrivateKey"><i class="fa-solid fa-plus"></i> Add private key</button>
			<button class="cta-button" type="submit">Lưu</button>
		</form>

		<!-- Danh sách ví hiện có -->
		<h2>Danh sách ví</h2>
		<table>
			<thead>
				<tr>
					<th>Tên ví</th>
					<th>Public Key</th>
					<th>Trạng thái</th>
					<th>Ngày tạo</th>
					<th>Hành động</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($existingWallets as $wallet): ?>
					<tr>
						<td><?php echo htmlspecialchars($wallet['wallet_name'] ?: 'Ví #' . $wallet['id']); ?></td>
						<td><a href="https://solscan.io/address/<?php echo htmlspecialchars($wallet['public_key']); ?>" target="_blank"><?php echo htmlspecialchars(substr($wallet['public_key'], 0, 4) . '...' . substr($wallet['public_key'], -4)); ?></a></td>
						<td><?php echo htmlspecialchars($wallet['status']); ?></td>
						<td><?php echo htmlspecialchars($wallet['created_at']); ?></td>
						<td><button class="cta-button deleteWallet" data-id="<?php echo $wallet['id']; ?>">Xóa</button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div id="mm-result" class="status-box"></div>
	</div>
</div>
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts - Internal library -->
<script defer src="/js/libs/axios.min.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/libs/axios.min.js')"></script>
<!-- Global variable -->
<script>
    // Passing JWT_SECRET into JavaScript securely
    const authToken = '<?php echo htmlspecialchars(JWT_SECRET); ?>';
</script>
<!-- Scripts - Source code -->
<script defer src="/js/vina.js?t=<?php echo time(); ?>"></script>
<script defer src="/mm/private-key/add-private-key.js?t=<?php echo time(); ?>"></script>
</body>
</html>
<?php ob_end_flush(); ?>
