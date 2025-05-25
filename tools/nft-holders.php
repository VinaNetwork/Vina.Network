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
    $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
    $holders_per_page = 10; // Số holders hiển thị mỗi trang
    $offset = ($page - 1) * $holders_per_page;

    // Gọi API để lấy danh sách holders
    $holders_data = getNFTHolders($mintAddress, $page);

    if (isset($holders_data['error'])) {
        echo "<p>" . htmlspecialchars($holders_data['error']) . "</p>";
    } elseif ($holders_data && !empty($holders_data['holders'])) {
        $total_holders = count($holders_data['holders']);
        $paginated_holders = array_slice($holders_data['holders'], $offset, $holders_per_page);

        echo "<h3>Results</h3>";
        echo "<p>Total Holders: $total_holders (Page $page)</p>";
        echo "<ul>";
        foreach ($paginated_holders as $holder) {
            echo "<li>" . htmlspecialchars($holder) . "</li>";
        }
        echo "</ul>";

        // Phân trang
        echo "<div class='pagination'>";
        $total_pages = ceil($total_holders / $holders_per_page);
        if ($page > 1) {
            echo "<form method='POST' style='display:inline;'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page - 1) . "'><button type='submit'>Previous</button></form>";
        }
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i === $page) {
                echo "<span class='active-page'>$i</span>";
            } else {
                echo "<form method='POST' style='display:inline; margin-left: 5px;'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='$i'><button type='submit'>$i</button></form>";
            }
        }
        if ($page < $total_pages) {
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
        "limit" => 1000, // Giới hạn API trả về 1000 holders mỗi lần
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
