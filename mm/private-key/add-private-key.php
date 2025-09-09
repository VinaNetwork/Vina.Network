<?php
// ============================================================================
// File: mm/private-key/add-private-key.php
// Description: Add private key page.
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

// Connect database
try {
    $pdo = get_db_connection();
} catch (Exception $e) {
    log_message("Database connection failed: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection error']);
    exit;
}

// Check session
$public_key = $_SESSION['public_key'] ?? null;
if (!$public_key) {
    log_message("No public key found in session, redirecting to login", 'private-key-page.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/mm/add-private-key';
    header('Location: /acc/connect');
    exit;
}

// Get user_id
try {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("Account not found for public_key: $public_key", 'private-key-page.log', 'make-market', 'ERROR');
        header('Location: /acc/connect');
        exit;
    }
    $user_id = $account['id'];
} catch (PDOException $e) {
    log_message("Account query error: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn tài khoản']);
    exit;
}

// Process form add private key
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $privateKeys = $_POST['privateKeys'] ?? [];
    $walletNames = $_POST['walletNames'] ?? [];
    $errors = [];

    // Check and encrypt private keys
    $base58 = new Base58();
    $validWallets = [];
    foreach ($privateKeys as $index => $privateKey) {
        $privateKey = trim($privateKey);
        $walletName = trim($walletNames[$index] ?? "Wallet $index");

        if (empty($privateKey)) {
            $errors[] = "Private key $index is empty";
            continue;
        }

        // Check format
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{64,128}$/', $privateKey)) {
            $errors[] = "Private key $index is not in correct format";
            continue;
        }

        // Decrypt and derive public key
        try {
            $decodedKey = $base58->decode($privateKey);
            if (strlen($decodedKey) !== 64) {
                $errors[] = "Private key $index has invalid length";
                continue;
            }
            $keypair = Keypair::fromSecretKey($decodedKey);
            $publicKey = $keypair->getPublicKey()->toBase58();

            // Private key encryption
            $encryptedPrivateKey = openssl_encrypt($privateKey, 'AES-256-CBC', JWT_SECRET, 0, substr(JWT_SECRET, 0, 16));
            if ($encryptedPrivateKey === false) {
                $errors[] = "Error encrypting private key $index: " . openssl_error_string();
                continue;
            }

            $validWallets[] = [
                'public_key' => $publicKey,
                'private_key' => $encryptedPrivateKey,
                'wallet_name' => $walletName
            ];
        } catch (Exception $e) {
            $errors[] = "The $index private key is invalid.: {$e->getMessage()}";
        }
    }

    // Save to database
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
            log_message("Save successfully " . count($validWallets) . " private key cho user_id=$user_id", 'private-key-page.log', 'make-market', 'INFO');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Lưu private key thành công', 'redirect' => '/mm/list-private-key']);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            log_message("Error saving private key: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu private key']);
            exit;
        }
    } else {
        log_message("Private key authentication error: " . implode(", ", $errors), 'private-key-page.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => implode(", ", $errors)]);
        exit;
    }
}

// SEO meta
$page_title = "Add Private Key - Vina Network";
$page_description = "Add private key for Solana transaction on Vina Network.";
$page_css = ['/mm/private-key/add-private-key.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="mm-container">
	<div class="mm-content">
		<h1><i class="fas fa-key"></i> Add Private Key</h1>
		
		<!-- Form to add private key -->
		<form id="addPrivateKeyForm" method="POST">
			<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
			<div id="privateKeysContainer">
				<div class="privateKeyRow">
					<label><i class="fas fa-wallet"></i> Wallet name (optional):</label>
					<input type="text" name="walletNames[]" placeholder="Enter wallet name...">
					<label><i class="fas fa-key"></i> Private Key:</label>
					<textarea name="privateKeys[]" required placeholder="Enter private key..."></textarea>
					<button type="button" class="removeKey"><i class="fas fa-trash"></i> Delete</button>
				</div>
			</div>
			<button class="cta-button" type="button" id="addPrivateKey"><i class="fa-solid fa-plus"></i> Add private key</button>
			<button class="cta-button" type="submit"><i class="fas fa-cloud"></i> Lưu</button>
		</form>

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
