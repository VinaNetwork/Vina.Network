<?php
// Chức năng: Kiểm tra giá trị NFT
include 'api-helper.php';
?>

<h2>Check NFT Valuation</h2>
<p>Enter the mint address of the NFT to see its current floor price and recent sales.</p>

<form id="nftValuationForm" method="POST" action="">
    <input type="text" name="mintAddressValuation" id="mintAddressValuation" placeholder="Enter NFT Mint Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
    <button type="submit">Check Valuation</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddressValuation'])) {
    $mintAddress = trim($_POST['mintAddressValuation']);

    // Gọi API để lấy thông tin giá trị NFT
    $valuation = getNFTValuation($mintAddress);

    if (isset($valuation['error'])) {
        echo "<p>" . htmlspecialchars($valuation['error']) . "</p>";
    } elseif ($valuation) {
        echo "<h3>Results</h3>";
        echo "<p>Floor Price: " . htmlspecialchars($valuation['floorPrice'] ?? 'N/A') . " SOL</p>";
        echo "<p>Last Sale: " . htmlspecialchars($valuation['lastSale'] ?? 'N/A') . " SOL</p>";
        echo "<p>Volume (24h): " . htmlspecialchars($valuation['volume'] ?? 'N/A') . " SOL</p>";
    } else {
        echo "<p>No valuation data found or invalid mint address.</p>";
    }
}

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
