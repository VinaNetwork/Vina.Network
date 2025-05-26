<?php
// Chức năng: Kiểm tra NFT Holders
include 'api-helper.php';
?>

<div class="nft-holders-content">
    <div class="nft-checkbox">
    <h2>Check NFT Holders</h2>
    <p>Enter the address of the NFT to see the number of holders and their wallet addresses.</p>

    <form id="nftHoldersForm" method="POST" action="">
        <input type="text" name="mintAddress" id="mintAddressHolders" placeholder="Enter NFT Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
        <button type="submit">Check Holders</button>
    </form>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        $mintAddress = trim($_POST['mintAddress']);
        $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
        $holders_per_page = 10;
        $offset = ($page - 1) * $holders_per_page;

        // Gọi API để lấy danh sách holders
        $holders_data = getNFTHolders($mintAddress, $page);

        if (isset($holders_data['error'])) {
            echo "<div class='result-error'><p>" . htmlspecialchars($holders_data['error']) . "</p></div>";
        } elseif ($holders_data && !empty($holders_data['holders'])) {
            $total_holders = count($holders_data['holders']);
            $paginated_holders = array_slice($holders_data['holders'], $offset, $holders_per_page);

            echo "<div class='result-section'>";
            echo "<h3>Results</h3>";
            echo "<p class='result-info'>Total Holders: $total_holders (Page $page)</p>";
            echo "<ul class='holders-list'>";
            foreach ($paginated_holders as $holder) {
                echo "<li>" . htmlspecialchars($holder) . "</li>";
            }
            echo "</ul>";

            // Phân trang
            echo "<div class='pagination'>";
            $total_pages = ceil($total_holders / $holders_per_page);
            if ($page > 1) {
                echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page - 1) . "'><button type='submit' class='page-btn'>Previous</button></form>";
            }
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i === $page) {
                    echo "<span class='active-page'>$i</span>";
                } else {
                    echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='$i'><button type='submit' class='page-btn'>$i</button></form>";
                }
            }
            if ($page < $total_pages) {
                echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page + 1) . "'><button type='submit' class='page-btn'>Next</button></form>";
            }
            echo "</div>";
            echo "</div>";
        } else {
            echo "<div class='result-error'><p>No holders found or invalid mint address.</p></div>";
        }
    }
    ?>

    <!-- Thêm mô tả chi tiết chức năng của tab -->
    <div class="feature-description">
        <h3>About NFT Holders Checker</h3>
        <p>
            The NFT Holders Checker allows you to view the total number of holders for a specific Solana NFT by entering its mint address. 
            It retrieves a list of wallet addresses that currently hold the NFT, with pagination to browse through the results easily. 
            This tool is useful for NFT creators, collectors, or investors who want to analyze the distribution and ownership of an NFT on the Solana blockchain.
        </p>
    </div>
</div>

<?php
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
