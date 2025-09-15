<?php
// ============================================================================
// File: mm/private-key/add-pk.php
// Description: Add private key page with client-side encryption.
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

use Attestto\SolanaPhpSdk\Keypair;
use StephenHill\Base58;

// Log request
log_message("add-pk.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}", 'private-key-page.log', 'make-market', 'DEBUG');

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
$short_public_key = $public_key ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
if (!$public_key) {
    log_message("No public key found in session, redirecting to login", 'private-key-page.log', 'make-market', 'INFO');
    $_SESSION['redirect_url'] = '/mm/add-private-key';
    header('Location: /acc/connect-p');
    exit;
}

// Get user_id
try {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("Account not found for public_key: $short_public_key", 'private-key-page.log', 'make-market', 'ERROR');
        header('Location: /acc/connect-p');
        exit;
    }
    $user_id = $account['id'];
} catch (PDOException $e) {
    log_message("Account query error: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Account query error']);
    exit;
}

// Process form add private key
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $encryptedKeys = json_decode($_POST['encryptedKeys'] ?? '[]', true);
    $errors = [];
    $validWallets = [];
    $base58 = new Base58();

    // Validate encrypted keys
    foreach ($encryptedKeys as $index => $keyData) {
        $walletName = trim($keyData['walletName'] ?? "Wallet $index");
        $iv = $keyData['iv'] ?? null;
        $ciphertext = $keyData['ciphertext'] ?? null;
        $authTag = $keyData['authTag'] ?? null;

        if (!$iv || !$ciphertext || !$authTag) {
            $errors[] = "Invalid encrypted data for wallet $index";
            continue;
        }

        // Derive public key from private key (giả định client đã gửi public key tương ứng)
        try {
            $publicKey = $keyData['publicKey'] ?? null;
            if (!$publicKey) {
                // Nếu client không gửi public key, bạn có thể yêu cầu client gửi hoặc bỏ qua bước này
                $errors[] = "Public key missing for wallet $index";
                continue;
            }

            // Check for duplicate public key
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM private_key WHERE public_key = ? AND user_id = ?");
                $checkStmt->execute([$publicKey, $user_id]);
                if ($checkStmt->fetch()) {
                    $errors[] = "Private key $index already exists for public key " . substr($publicKey, 0, 4) . "...";
                    continue;
                }
            } catch (PDOException $e) {
                $errors[] = "Error checking duplicate for wallet $index: {$e->getMessage()}";
                log_message("Duplicate check error for public_key=$publicKey, user_id=$user_id: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
                continue;
            }

            $validWallets[] = [
                'public_key' => $publicKey,
                'private_key' => json_encode([
                    'iv' => $iv,
                    'ciphertext' => $ciphertext,
                    'authTag' => $authTag
                ]),
                'wallet_name' => $walletName
            ];
        } catch (Exception $e) {
            $errors[] = "Invalid data for wallet $index: {$e->getMessage()}";
            log_message("Invalid data for wallet $index: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
        }
    }

    if (!empty($errors)) {
        log_message("Validation errors: " . implode(", ", $errors), 'private-key-page.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => implode(", ", $errors)]);
        exit;
    }

    // Save to database
    if (!empty($validWallets)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO private_key (user_id, public_key, private_key, wallet_name, created_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($validWallets as $wallet) {
                $stmt->execute([$user_id, $wallet['public_key'], $wallet['private_key'], $wallet['wallet_name'], date('Y-m-d H:i:s')]);
                $new_id = $pdo->lastInsertId();
                log_message("Inserted new private key with ID: $new_id for user_id=$user_id, public_key=" . substr($wallet['public_key'], 0, 4) . "...", 'private-key-page.log', 'make-market', 'INFO');
            }
            $pdo->commit();
            log_message("Saved successfully " . count($validWallets) . " private key(s) for user_id=$user_id", 'private-key-page.log', 'make-market', 'INFO');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Private key saved successfully', 'redirect' => '/mm/list-private-key']);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            log_message("Error saving private key: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error saving private key']);
            exit;
        }
    } else {
        log_message("No valid private keys to save", 'private-key-page.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'No valid private keys provided']);
        exit;
    }
}

// SEO meta
$page_title = "Add Private Key - Vina Network";
$page_description = "Add private key for Solana transaction on Vina Network.";
$page_css = ['/mm/private-key/add-pk.css'];
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
                    <input class="wallet-name" type="text" name="walletNames[]" placeholder="Enter wallet name...">
                    <label><i class="fas fa-key"></i> Private Key:</label>
                    <textarea name="privateKeys[]" required placeholder="Enter private key..."></textarea>
                </div>
            </div>
            <button class="cta-button" type="button" id="addPrivateKey"><i class="fa-solid fa-plus"></i> Add private key</button>
            <button class="cta-button" type="submit"><i class="fas fa-cloud"></i> Save</button>
        </form>

        <div id="mm-result" class="status-box"></div>
    </div>
</div>
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts - Internal library -->
<script defer src="/js/libs/axios.min.js"></script>
<!-- Scripts - Source code -->
<script defer src="/js/vina.js"></script>
<script defer src="/mm/private-key/add-pk.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>