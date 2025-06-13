<?php
// nft-valuation.php - Kiểm tra giá trị NFT
include '../api-helper.php';
?>

<div class="t-6 nft-valuation-content">
    <div class="t-7">
        <h2>Check NFT Valuation</h2>
        <p>Enter the address of the NFT to see its current floor price and recent sales.</p>

        <form id="nftValuationForm" method="POST" action="">
            <input type="text" name="mintAddressValuation" id="mintAddressValuation" placeholder="Enter NFT Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
            <button type="submit">Check Valuation</button>
        </form>
    </div>

    <?php
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddressValuation'])) {
			$mintAddress = trim($_POST['mintAddressValuation']);

			// Gọi API để lấy thông tin giá trị NFT
			$valuation = getNFTValuation($mintAddress);

			if (isset($valuation['error'])) {
				echo "<div class='result-error'><p>" . htmlspecialchars($valuation['error']) . "</p></div>";
			} elseif ($valuation) {
				echo "<div class='result-section'>";
				echo "<h3>Results</h3>";
				echo "<p class='result-info'>Floor Price: " . htmlspecialchars($valuation['floorPrice'] ?? 'N/A') . " SOL</p>";
				echo "<p class='result-info'>Last Sale: " . htmlspecialchars($valuation['lastSale'] ?? 'N/A') . " SOL</p>";
				echo "<p class='result-info'>Volume (24h): " . htmlspecialchars($valuation['volume'] ?? 'N/A') . " SOL</p>";
				echo "</div>";
			} else {
				echo "<div class='result-error'><p>No valuation data found or invalid mint address.</p></div>";
			}
		}
    ?>

    <!-- Thêm mô tả chi tiết chức năng của tab -->
    <div class="t-9">
        <h3>About NFT Valuation Checker</h3>
        <p>
            The NFT Valuation Checker allows you to assess the market value of a specific Solana NFT by entering its mint address. 
            It retrieves key financial metrics, including the current floor price, the most recent sale price, and the trading volume over the last 24 hours. 
            This tool is ideal for NFT traders, investors, or collectors looking to evaluate the market performance of an NFT on the Solana blockchain.
        </p>
    </div>
</div>

<?php
	function getNFTValuation($mintAddress) {
		$payload = [
			"mintAddresses" => [$mintAddress]
		];

		$data = callHeliusAPI('tokens', $payload);
		if (isset($data['error'])) {
			return ['error' => $data['error']];
		}

		if (isset($data[0])) {
			return [
				'floorPrice' => $data[0]['floorPrice'] ?? 'N/A',
				'lastSale' => $data[0]['lastSale'] ?? 'N/A',
				'volume' => $data[0]['volume'] ?? 'N/A'
			];
		}

		return ['error' => 'No data found for this mint address.'];
	}
?>
