<?php
// Chức năng: Kiểm tra giá trị NFT
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
    $apiKey = "8eb75cd9-015a-4e24-9de2-5be9ee0f1c63";
    $url = "https://api.helius.xyz/v0/tokens?api-key=" . $apiKey;

    $payload = [
        "mintAddresses" => [$mintAddress]
    ];

    $ch = curl_init();
    if (!$ch) {
        error_log("cURL initialization failed.");
        return ['error' => 'Failed to initialize cURL.'];
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    if ($response === false) {
        $curlError = curl_error($ch);
        error_log("cURL error: $curlError");
        curl_close($ch);
        return ['error' => 'cURL error: ' . $curlError];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("API request failed with HTTP code: $httpCode");
        return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
    }

    $data = json_decode($response, true);
    if ($data === null) {
        error_log("Failed to parse API response as JSON. Response: $response");
        return ['error' => 'Failed to parse API response as JSON.'];
    }

    // Giả lập dữ liệu trả về (Helius API có thể không trả về đúng format này, cần điều chỉnh)
    if (isset($data[0])) {
        return [
            'floorPrice' => $data[0]['floorPrice'] ?? 'N/A',
            'lastSale' => $data[0]['lastSale'] ?? 'N/A',
            'volume' => $data[0]['volume'] ?? 'N/A'
        ];
    }

    return ['error' => 'No data found for this mint address.'];
}
