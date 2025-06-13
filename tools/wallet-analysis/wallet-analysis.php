<?php
// wallet-analysis.php
// Cấu hình log lỗi
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);
include '../api-helper.php';
error_log("wallet-analysis.php loaded"); // Debug
?>

<div class="t-6 wallet-analysis-content">
    <div class="t-7">
        <h2>Check Solana Wallet</h2>
        <p>Enter the wallet address to see its balance, tokens, and recent activities.</p>
        <form id="walletAnalysisForm" class="wallet-form" method="POST" action="">
            <input type="text" name="walletAddress" id="walletAddress" placeholder="Enter Solana Wallet Address (e.g., 7xKXtg2CW87d97...)" required>
            <button type="submit">Analyze Wallet</button>
        </form>
    </div>

    <?php
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['walletAddress'])) {
			$walletAddress = trim($_POST['walletAddress']);
			error_log("wallet-analysis.php: Analyzing walletAddress = $walletAddress"); // Debug

			if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletAddress)) {
				echo "<div class='result-error'><p>Invalid wallet address. Please enter a valid Solana wallet address (32-44 characters, base58).</p></div>";
				error_log("wallet-analysis.php: Invalid wallet address format"); // Debug
			} else {
				$walletData = getWalletData($walletAddress);

				if (isset($walletData['error'])) {
					echo "<div class='result-error'><p>" . htmlspecialchars($walletData['error']) . "</p></div>";
					error_log("wallet-analysis.php: Error - {$walletData['error']}"); // Debug
				} elseif ($walletData) {
					echo "<div class='result-section'>";
					echo "<h3>Results</h3>";
					echo "<p class='result-info'>Wallet Address: " . htmlspecialchars($walletAddress) . "</p>";
					echo "<p class='result-info'>SOL Balance: " . htmlspecialchars($walletData['sol_balance'] ?? 'N/A') . " SOL</p>";
					echo "<p class='result-info'>Token Count: " . htmlspecialchars($walletData['token_count'] ?? 'N/A') . "</p>";
					echo "<p class='result-info'>Recent Activity Count: " . htmlspecialchars($walletData['activity_count'] ?? 'N/A') . "</p>";
					echo "</div>";
					error_log("wallet-analysis.php: Retrieved data - SOL Balance: {$walletData['sol_balance']}, Tokens: {$walletData['token_count']}, Activities: {$walletData['activity_count']}"); // Debug
				} else {
					echo "<div class='result-error'><p>No data found for this wallet address.</p></div>";
					error_log("wallet-analysis.php: No data found for $walletAddress"); // Debug
				}
			}
		}
    ?>

    <div class="t-9">
        <h3>About Wallet Analysis</h3>
        <p>
            The Wallet Analysis tool allows you to analyze a Solana wallet by entering its address. 
            It retrieves key information such as the SOL balance, number of tokens held, and recent activities on the Solana blockchain. 
            This tool is useful for investors, traders, or anyone interested in tracking wallet activities.
        </p>
    </div>
</div>

<?php
	function getWalletData($walletAddress) {
		// Gọi API Helius để lấy thông tin ví
		$payload = [
			"addresses" => [$walletAddress]
		];

		// Lấy số dư SOL
		error_log("wallet-analysis.php: Calling Helius API for balances, walletAddress = $walletAddress"); // Debug
		$balanceData = callHeliusAPI('addresses/' . $walletAddress . '/balances');
		if (isset($balanceData['error'])) {
			error_log("wallet-analysis.php: Balance API error - {$balanceData['error']}"); // Debug
			return ['error' => $balanceData['error']];
		}

		// Lấy danh sách token
		error_log("wallet-analysis.php: Calling Helius API for tokens, walletAddress = $walletAddress"); // Debug
		$tokenData = callHeliusAPI('addresses/' . $walletAddress . '/tokens');
		if (isset($tokenData['error'])) {
			error_log("wallet-analysis.php: Token API error - {$tokenData['error']}"); // Debug
			return ['error' => $tokenData['error']];
		}

		// Lấy hoạt động gần đây
		error_log("wallet-analysis.php: Calling Helius API for transactions, walletAddress = $walletAddress"); // Debug
		$activityData = callHeliusAPI('addresses/' . $walletAddress . '/transactions', ['limit' => 10]);
		if (isset($activityData['error'])) {
			error_log("wallet-analysis.php: Transaction API error - {$activityData['error']}"); // Debug
			return ['error' => $activityData['error']];
		}

		// Xử lý dữ liệu trả về
		$solBalance = isset($balanceData['nativeBalance']) ? $balanceData['nativeBalance'] / 1e9 : 'N/A'; // Chuyển từ lamports sang SOL
		$tokenCount = isset($tokenData['tokens']) ? count($tokenData['tokens']) : 'N/A';
		$activityCount = isset($activityData['transactions']) ? count($activityData['transactions']) : 'N/A';

		error_log("wallet-analysis.php: Processed data - SOL Balance: $solBalance, Tokens: $tokenCount, Activities: $activityCount"); // Debug
		return [
			'sol_balance' => $solBalance,
			'token_count' => $tokenCount,
			'activity_count' => $activityCount
		];
	}
?>
