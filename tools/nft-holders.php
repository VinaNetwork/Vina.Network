<?php
// nft-holders.php
// Chức năng: Kiểm tra NFT Holders với Helius API
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
        <p>Enter the <strong>NFT Collection</strong> address to see the number of holders and their wallet addresses. E.g: Find this address on MagicEden under "Details" > "On-chain Collection".</p>
        <form id="nftHoldersForm" method="POST" action="">
            <input type="text" name="mintAddress" id="mintAddressHolders" placeholder="Enter NFT Collection Address" required>
            <button type="submit">Check Holders</button>
        </form>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            error_log("nft-holders.php: Form submitted with mintAddress={$_POST['mintAddress']}"); // Debug

            $mintAddress = trim($_POST['mintAddress']);
            $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
            $holders_per_page = 50; // Số holders mỗi trang
            $offset = ($page - 1) * $holders_per_page;

            // Kiểm tra định dạng mint address
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                echo "<div class='result-error'><p>Invalid collection address. Please enter a valid Solana collection address (32-44 characters, base58).</p></div>";
                error_log("nft-holders.php: Invalid mint address format - $mintAddress"); // Debug
            } else {
                // Lấy tổng số holders
                $total_params = [
                    'groupKey' => 'collection',
                    'groupValue' => $mintAddress,
                    'page' => 1,
                    'limit' => 50 // Dùng limit=50 để đảm bảo total đúng
                ];
                $total_data = callHeliusAPI('getAssetsByGroup', $total_params, 'POST');
                error_log("nft-holders.php: Total API response - " . json_encode($total_data)); // Debug

                if (isset($total_data['error'])) {
                    echo "<div class='result-error'><p>" . htmlspecialchars($total_data['error']) . "</p></div>";
                    error_log("nft-holders.php: Helius API error for total - {$total_data['error']}"); // Debug
                } else {
                    $total_holders = $total_data['result']['total'] ?? 0;
                    error_log("nft-holders.php: Total holders = $total_holders for $mintAddress"); // Debug

                    if ($total_holders === 0) {
                        echo "<div class='result-error'><p>No holders found or invalid collection address.</p></div>";
                        error_log("nft-holders.php: Zero holders for $mintAddress"); // Debug
                    } else {
                        // Gọi API để lấy holders cho trang hiện tại
                        $holders_data = getNFTHolders($mintAddress, $offset, $holders_per_page);
                        error_log("nft-holders.php: Holders API response - " . json_encode($holders_data)); // Debug

                        if (isset($holders_data['error'])) {
                            echo "<div class='result-error'><p>" . htmlspecialchars($holders_data['error']) . "</p></div>";
                            error_log("nft-holders.php: Helius API error - {$holders_data['error']}"); // Debug
                        } elseif ($holders_data && !empty($holders_data['holders'])) {
                            $paginated_holders = $holders_data['holders'];

                            // Tính số holders hiển thị đến trang hiện tại
                            $current_holders = min($page * $holders_per_page, $total_holders);
                            $percentage = $total_holders > 0 ? number_format(($current_holders / $total_holders) * 100, 1) : 0;

                            echo "<div class='result-section'>";
                            echo "<h2>Results</h2>";
                            echo "<p class='result-info'>Checking address: " . htmlspecialchars($mintAddress) . "</p>";
                            echo "<p class='result-info'>Owners: $current_holders/$total_holders ($percentage%) (Page $page)</p>";
                            echo "<table class='holders-table'>";
                            echo "<thead><tr><th>Address</th><th>Amount</th></tr></thead>";
                            echo "<tbody>";
                            foreach ($paginated_holders as $holder) {
                                $address = htmlspecialchars($holder['owner'] ?? 'N/A');
                                $amount = htmlspecialchars($holder['amount'] ?? 'N/A');
                                echo "<tr><td>$address</td><td>$amount</td></tr>";
                            }
                            echo "</tbody>";
                            echo "</table>";

                            // Phân trang
                            echo "<div class='pagination-btn'>";
                            $total_pages = ceil($total_holders / $holders_per_page);
                            if ($page > 1) {
                                echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page - 1) . "'><button type='submit' class='page-btn'>Previous</button></form>";
                            }
                            for ($i = 1; $i <= $total_pages; $i++) {
                                if ($i === $page) {
                                    echo "<span class='page-btn active'>$i</span>";
                                } else {
                                    echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='$i'><button type='submit' class='page-btn'>$i</button></form>";
                                }
                            }
                            if ($page < $total_pages) {
                                echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page + 1) . "'><button type='submit' class='page-btn'>Next</button></form>";
                            }
                            echo "</div>";
                            echo "</div>";
                            error_log("nft-holders.php: Retrieved " . count($paginated_holders) . " holders, page $page for address $mintAddress"); // Debug
                        } else {
                            echo "<div class='result-error'><p>No holders found for this page or invalid collection address.</p></div>";
                            error_log("nft-holders.php: No holders found for page $page, address $mintAddress"); // Debug
                        }
                    }
                }
            }
        } catch (Exception $e) {
            echo "<div class='result-error'><p>Error processing request. Please try again.</p></div>";
            error_log("nft-holders.php: Exception - {$e->getMessage()}"); // Debug
        }
    }
    ?>

    <div class="feature-description">
        <h2>About NFT Holders Checker</h2>
        <p>
            The NFT Holders Checker allows you to view the total number of holders for a specific Solana NFT collection by entering its On-chain Collection address. 
            It retrieves a list of wallet addresses that currently hold NFTs in the collection, with pagination to browse through the results easily. 
            This tool is useful for NFT creators, collectors, or investors who want to analyze the distribution and ownership of a collection on the Solana blockchain.
        </p>
    </div>
</div>

<?php
function getNFTHolders($mintAddress, $offset = 0, $size = 50) {
    // Gọi Helius API để lấy holders
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => ceil(($offset + $size) / $size), // Helius page bắt đầu từ 1
        'limit' => $size
    ];
    
    error_log("nft-holders.php: Calling Helius API for holders - mintAddress: $mintAddress, offset: $offset, size: $size, page: {$params['page']}"); // Debug
    
    $data = callHeliusAPI('getAssetsByGroup', $params, 'POST');
    
    if (isset($data['error'])) {
        error_log("nft-holders.php: getAssetsByGroup error - {$data['error']}"); // Debug
        return ['error' => 'This is not an NFT collection address. Please enter a valid NFT Collection address.'];
    }
    
    if (isset($data['result']['items']) && !empty($data['result']['items'])) {
        $holders = array_map(function($item) {
            return [
                'owner' => $item['ownership']['owner'] ?? 'unknown',
                'amount' => 1 // Mỗi NFT có amount là 1
            ];
        }, $data['result']['items']);
        
        return ['holders' => $holders];
    }
    
    error_log("nft-holders.php: No holders found for address $mintAddress"); // Debug
    return ['error' => 'This is not an NFT collection address. Please enter a valid NFT Collection address.'];
}
?>
