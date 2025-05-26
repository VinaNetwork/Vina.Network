<?php
// nft-holders.php
// Chức năng: Kiểm tra NFT Holders
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

include 'api-helper.php';

error_log("nft-holders.php loaded"); // Debug
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

        // Kiểm tra định dạng mint address
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
            echo "<div class='result-error'><p>Invalid mint address. Please enter a valid Solana mint address (32-44 characters, base58).</p></div>";
            error_log("nft-holders.php: Invalid mint address format - $mintAddress"); // Debug
        } else {
            // Gọi Solscan API
            $holders_data = getNFTHolders($mintAddress, $offset, $holders_per_page);

            if (isset($holders_data['error'])) {
                echo "<div class='result-error'><p>" . htmlspecialchars($holders_data['error']) . "</p></div>";
                error_log("nft-holders.php: Solscan API error - {$holders_data['error']}"); // Debug
            } elseif ($holders_data && !empty($holders_data['holders'])) {
                $total_holders = $holders_data['total'];
                $paginated_holders = $holders_data['holders'];

                echo "<div class='result-section'>";
                echo "<h3>Results</h3>";
                echo "<p class='result-info'>Total Holders: $total_holders (Page $page)</p>";
                echo "<ul class='holders-list'>";
                foreach ($paginated_holders as $holder) {
                    $address = htmlspecialchars($holder['owner'] ?? 'N/A');
                    $amount = htmlspecialchars($holder['amount'] ?? 'N/A');
                    echo "<li>Owner: $address - Amount: $amount</li>";
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
                error_log("nft-holders.php: Retrieved $total_holders holders, showing page $page"); // Debug
            } else {
                echo "<div class='result-error'><p>No holders found or invalid mint address.</p></div>";
                error_log("nft-holders.php: No holders found for $mintAddress"); // Debug
            }
        }
    }
    ?>

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
function getNFTHolders($mintAddress, $offset = 0, $size = 10) {
    // Gọi Solscan API
    $params = [
        'tokenAddress' => $mintAddress,
        'offset' => $offset,
        'size' => $size
    ];
    error_log("nft-holders.php: Calling Solscan API for holders - mintAddress: $mintAddress, offset: $offset, size: $size"); // Debug
    
    $data = callSolscanAPI('token/holders', $params);
    
    if (isset($data['error'])) {
        return ['error' => $data['error']];
    }
    
    if (isset($data['data']) && !empty($data['data'])) {
        return [
            'holders' => $data['data'],
            'total' => $data['total'] ?? count($data['data'])
        ];
    }
    
    return ['error' => 'No holders found for this mint address.'];
}
?>
