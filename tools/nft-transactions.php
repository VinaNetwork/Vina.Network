<?php
	// nft-transactions.php
	// Chức năng: Kiểm tra lịch sử giao dịch NFT
	include 'api-helper.php';

	error_log("nft-transactions.php loaded"); // Debug
?>

<div class="t-6 nft-transactions-content">
    <div class="t-7">
        <h2>Check NFT Transactions</h2>
        <p>Enter the address of the NFT to see its transaction history.</p>

        <form class="transaction-form" method="POST" action="">
            <input type="text" name="mintAddressTransactions" id="mintAddressTransactions" placeholder="Enter NFT Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
            <button type="submit">Check Transactions</button>
        </form>
    </div>

    <?php
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddressTransactions'])) {
			$mintAddress = trim($_POST['mintAddressTransactions']);
			$page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
			$transactions_per_page = 10;
			$offset = ($page - 1) * $transactions_per_page;

			// Gọi API để lấy lịch sử giao dịch
			error_log("nft-transactions.php: Fetching transactions for mintAddress = $mintAddress, page = $page"); // Debug
			$transactions_data = getNFTTransactions($mintAddress, $page);

			if (isset($transactions_data['error'])) {
				echo "<div class='result-error'><p>" . htmlspecialchars($transactions_data['error']) . "</p></div>";
				error_log("nft-transactions.php: Error - {$transactions_data['error']}"); // Debug
			} elseif ($transactions_data && !empty($transactions_data['transactions'])) {
				$total_transactions = count($transactions_data['transactions']);
				$paginated_transactions = array_slice($transactions_data['transactions'], $offset, $transactions_per_page);

				echo "<div class='result-section'>";
				echo "<h3>Results</h3>";
				echo "<table class='transaction-table'>";
				echo "<tr><th>Transaction ID</th><th>Buyer</th><th>Seller</th><th>Price (SOL)</th><th>Timestamp</th></tr>";
				foreach ($paginated_transactions as $transaction) {
					echo "<tr>";
					echo "<td><a href='https://solscan.io/tx/" . htmlspecialchars($transaction['signature']) . "' target='_blank'>" . htmlspecialchars(substr($transaction['signature'], 0, 8)) . "...</a></td>";
					echo "<td>" . htmlspecialchars($transaction['buyer'] ?? 'N/A') . "</td>";
					echo "<td>" . htmlspecialchars($transaction['seller'] ?? 'N/A') . "</td>";
					echo "<td>" . htmlspecialchars($transaction['price'] ?? 'N/A') . "</td>";
					echo "<td>" . htmlspecialchars($transaction['timestamp'] ?? 'N/A') . "</td>";
					echo "</tr>";
				}
				echo "</table>";

				// Phân trang
				echo "<div class='pagination'>";
				$total_pages = ceil($total_transactions / $transactions_per_page);
				if ($page > 1) {
					echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddressTransactions' value='$mintAddress'><input type='hidden' name='page' value='" . ($page - 1) . "'><button type='submit' class='page-btn'>Previous</button></form>";
				}
				for ($i = 1; $i <= $total_pages; $i++) {
					if ($i === $page) {
						echo "<span class='active-page'>$i</span>";
					} else {
						echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddressTransactions' value='$mintAddress'><input type='hidden' name='page' value='$i'><button type='submit' class='page-btn'>$i</button></form>";
					}
				}
				if ($page < $total_pages) {
					echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddressTransactions' value='$mintAddress'><input type='hidden' name='page' value='" . ($page + 1) . "'><button type='submit' class='page-btn'>Next</button></form>";
				}
				echo "</div>";
				echo "</div>";
			} else {
				echo "<div class='result-error'><p>No transaction history found or invalid mint address.</p></div>";
				error_log("nft-transactions.php: No transactions found for $mintAddress"); // Debug
			}
		}
    ?>

    <!-- Thêm mô tả chi tiết chức năng của tab -->
    <div class="t-8">
        <h3>About NFT Transactions Checker</h3>
        <p>
            The NFT Transactions Checker allows you to view the transaction history of a specific Solana NFT by entering its mint address. 
            It retrieves details such as transaction IDs, buyer and seller addresses, sale prices, and timestamps, with pagination for easy browsing. 
            This tool is valuable for NFT analysts, traders, or collectors who want to track the trading activity of an NFT on the Solana blockchain.
        </p>
    </div>
</div>

<?php
	function getNFTTransactions($mintAddress, $page = 1) {
		$payload = [
			"mint" => $mintAddress,
			"limit" => 1000,
			"page" => $page
		];

		error_log("nft-transactions.php: Calling Helius API for mintAddress = $mintAddress, page = $page"); // Debug
		$data = callHeliusAPI('transactions', $payload);
		if (isset($data['error'])) {
			error_log("nft-transactions.php: Helius API error - {$data['error']}"); // Debug
			return ['error' => $data['error']];
		}

		if (isset($data['transactions'])) {
			error_log("nft-transactions.php: Retrieved " . count($data['transactions']) . " transactions"); // Debug
			return ['transactions' => $data['transactions']];
		}

		error_log("nft-transactions.php: No transaction data found for $mintAddress"); // Debug
		return ['error' => 'No transaction data found for this mint address.'];
	}
?>
