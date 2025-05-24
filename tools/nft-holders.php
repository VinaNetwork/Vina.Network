<?php
// Chức năng: Kiểm tra NFT Holders
?>

<h2>Check Solana NFT Holders</h2>
<p>Enter the mint address of the NFT to see the number of holders and their wallet addresses.</p>

<form id="nftHoldersForm" method="POST" action="">
    <input type="text" name="mintAddress" id="mintAddressHolders" placeholder="Enter NFT Mint Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
    <button type="submit">Check Holders</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
    $mintAddress = trim($_POST['mintAddress']);
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    // Gọi API để lấy danh sách holders
    $holders = getNFTHolders($mintAddress, $page);

    if (isset($holders['error'])) {
        echo "<p>" . htmlspecialchars($holders['error']) . "</p>";
    } elseif ($holders && !empty($holders['holders'])) {
        echo "<h3>Results</h3>";
        echo "<p>Total Holders: " . count($holders['holders']) . " (Page $page)</p>";
        echo "<ul>";
        foreach ($holders['holders'] as $holder) {
            echo "<li>" . htmlspecialchars($holder) . "</li>";
        }
        echo "</ul>";

        echo "<div class='pagination'>";
        if ($page > 1) {
            echo "<form method='POST' style='display:inline;'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page - 1) . "'><button type='submit'>Previous</button></form>";
        }
        if (count($holders['holders']) == 1000) {
            echo "<form method='POST' style='display:inline; margin-left: 10px;'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page + 1) . "'><button type='submit'>Next</button></form>";
        }
        echo "</div>";
    } else {
        echo "<p>No holders found or invalid mint address.</p>";
    }
}

function getNFTHolders($mintAddress, $page = 1) {
    $apiKey = "8eb75cd9-015a-4e24-9de2-5be9ee0f1c63";
    $url = "https://api.helius.xyz/v0/token-accounts?api-key=" . $apiKey;

    $payload = [
        "mint" => $mintAddress,
        "includeOffChain" => false,
        "limit" => 1000,
        "page" => $page
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

    if (isset($data['token_accounts'])) {
        $holders = array_unique(array_column($data['token_accounts'], 'owner'));
        return ['holders' => $holders];
    }

    return ['error' => 'No data found for this mint address.'];
}
