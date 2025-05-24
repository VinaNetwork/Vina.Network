<?php
$root_path = '../'; // Đường dẫn về thư mục gốc
$page_title = "Vina Network - Check NFT Holders";
$page_description = "Check the number of wallets holding a specific Solana NFT and list their addresses.";
$page_keywords = "Vina Network, Solana NFT, NFT holders, blockchain, $VINA";
$page_og_title = "Vina Network - Check NFT Holders";
$page_og_url = "https://vina.network/tools/nft-holders/";
$page_canonical = "https://vina.network/tools/nft-holders/";
$page_css = ['tools/nft-holders.css']; // Đường dẫn đến file CSS trong thư mục tools
include '../include/header.php';
?>

<section class="nft-holders-section">
    <div class="nft-holders-content fade-in">
        <h1>Check Solana NFT Holders</h1>
        <p>Enter the mint address of the NFT to see the number of holders and their wallet addresses.</p>
        
        <form id="nftForm" method="POST" action="">
            <input type="text" name="mintAddress" id="mintAddress" placeholder="Enter NFT Mint Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
            <button type="submit">Check Holders</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
            $mintAddress = trim($_POST['mintAddress']);

            // Gọi API để lấy danh sách holders (giả lập, cần tích hợp thực tế)
            $holders = getNFTHolders($mintAddress);

            if ($holders && !empty($holders['holders'])) {
                echo "<h2>Results</h2>";
                echo "<p>Total Holders: " . count($holders['holders']) . "</p>";
                echo "<ul>";
                foreach ($holders['holders'] as $holder) {
                    echo "<li>" . htmlspecialchars($holder) . "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No holders found or invalid mint address.</p>";
            }
        }

        function getNFTHolders($mintAddress) {
            // Đây là hàm giả lập, bạn cần thay thế bằng API thực tế
            // Ví dụ: Sử dụng Helius API hoặc Metaplex JS SDK
            $apiKey = "YOUR_HELIUS_API_KEY"; // Thay bằng API key của bạn
            $url = "https://api.helius.xyz/v1/token-accounts?api-key=" . $apiKey . "&mint=" . urlencode($mintAddress);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);

            if ($data && isset($data['result'])) {
                $holders = array_unique(array_column($data['result'], 'owner'));
                return ['holders' => $holders];
            }
            return false;
        }
        ?>
    </div>
</section>

<?php include '../include/footer.php'; ?>
</body>
</html>
