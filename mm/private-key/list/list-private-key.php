<?php
// File: mm/private-key/list/list-private-key.php
// Description: Page displaying the list of user account private keys
// Created by: Vina Network

ob_start();
$root_path = __DIR__ . '/../../../';
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

// Database connection
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
    $_SESSION['redirect_url'] = '/mm/list-private-key';
    header('Location: /acc/connect');
    exit;
}

// Get user_id
try {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for public_key: $short_public_key", 'private-key-page.log', 'make-market', 'ERROR');
        header('Location: /acc/connect');
        exit;
    }
    $user_id = $account['id'];
} catch (PDOException $e) {
    log_message("Account query error: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Account query error']);
    exit;
}

// Get wallet list
try {
    $stmt = $pdo->prepare("SELECT id, wallet_name, public_key, private_key, status, created_at FROM private_key WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log fetched wallets for debugging
    log_message("Wallets fetched: " . json_encode(array_map(function($w) {
        return [
            'id' => $w['id'],
            'wallet_name' => $w['wallet_name'],
            'public_key' => substr($w['public_key'], 0, 4) . '...',
            'created_at' => $w['created_at']
        ];
    }, $wallets)), 'private-key-page.log', 'make-market', 'DEBUG');

    // Shorten public_key and private_key
    $processed_wallets = [];
    foreach ($wallets as $wallet) {
        $processed_wallets[] = [
            'id' => $wallet['id'],
            'wallet_name' => $wallet['wallet_name'],
            'public_key' => $wallet['public_key'],
            'private_key' => $wallet['private_key'],
            'status' => $wallet['status'],
            'created_at' => $wallet['created_at'],
            'short_public_key' => substr($wallet['public_key'], 0, 4) . '...' . substr($wallet['public_key'], -4),
            'short_private_key' => substr($wallet['private_key'], 0, 4) . '...' . substr($wallet['private_key'], -4)
        ];
    }
    $wallets = $processed_wallets; // Replace original $wallets
} catch (PDOException $e) {
    log_message("Wallet list query error: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Wallet list query error']);
    exit;
}

// SEO meta
$page_title = "Private Key List - Vina Network";
$page_description = "View and manage your private key list on Vina Network.";
$page_css = ['/mm/private-key/list/list-private-key.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="mm-container">
	<div class="mm-content">
		<h1><i class="fas fa-key"></i> Private Key List</h1>

		<!-- Note about encrypted private key -->
		<p class="note">Note: The "Encrypted Private Key" column displays a portion of the encrypted private key for security. Your original private key is securely stored and encrypted in our database.</p>
		
		<!-- Wallet list table -->
		<table class="wallet-table">
			<thead>
				<tr>
					<th>Wallet Name</th>
					<th>Public Key</th>
					<th>Encrypted Private Key</th>
					<th>Status</th>
					<th>Created Date</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($wallets)): ?>
					<tr>
						<td colspan="6" style="text-align: center;">No wallets have been added yet.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($wallets as $wallet): ?>
						<tr>
							<td data-label="Wallet Name"><?php echo htmlspecialchars($wallet['wallet_name'] ?: 'Wallet #' . $wallet['id']); ?></td>
							<td data-label="Public Key">
								<a href="https://solscan.io/address/<?php echo htmlspecialchars($wallet['public_key']); ?>" target="_blank">
									<?php echo htmlspecialchars($wallet['short_public_key']); ?>
								</a>
								<i class="fas fa-copy copy-icon" title="Copy public key" data-full="<?php echo htmlspecialchars($wallet['public_key']); ?>"></i>
							</td>
							<td data-label="Encrypted Private Key">
								<?php echo htmlspecialchars($wallet['short_private_key']); ?>
							</td>
							<td data-label="Status"><?php echo htmlspecialchars($wallet['status']); ?></td>
							<td data-label="Created Date"><?php echo htmlspecialchars($wallet['created_at']); ?></td>
							<td data-label="Action">
								<button class="deleteWallet cta-button" data-id="<?php echo htmlspecialchars($wallet['id']); ?>"><i class="fas fa-trash"></i> Delete</button>
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
<script defer src="/mm/private-key/list/list-private-key.js?t=<?php echo time(); ?>"></script>
</body>
</html>
<?php ob_end_flush(); ?>
