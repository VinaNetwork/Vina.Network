<?php
// Chức năng: Kiểm tra NFT Holders
include 'api-helper.php';
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
    $holders_per_page = 10;
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
    $payload = [
        "mint" => $mintAddress,
        "includeOffChain" => false,
        "limit" => 1000,
        "page" => $page
    ];

    $data = callHeliusAPI('token-accounts', $payload);
    if (isset($data['error'])) {
        return ['error' => $data['error']];
    }

    if (isset($data['token_accounts'])) {
        $holders = array_unique(array_column($data['token_accounts'], 'owner'));
        return ['holders' => $holders];
    }

    return ['error' => 'No data found for this mint address.'];
}
?>
