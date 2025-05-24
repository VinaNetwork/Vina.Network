<!DOCTYPE html>
<html lang="en">
<?php
$root_path = '../'; // Đường dẫn về thư mục gốc
$page_title = "Vina Network - Check NFT Holders";
$page_description = "Check the number of wallets holding a specific Solana NFT and list their addresses.";
$page_keywords = "Vina Network, Solana NFT, NFT holders, blockchain, $VINA";
$page_og_title = "Vina Network - Check NFT Holders";
$page_og_url = "https://vina.network/tools/nft-holders/";
$page_canonical = "https://vina.network/tools/nft-holders/";
$page_css = ['nft-holders.css']; // Đường dẫn đến file CSS trong thư mục tools
include '../include/header.php'; // header.php chỉ cung cấp <head>
?>

<body>
<!-- Include Navbar -->
<?php include '../include/navbar.php'; ?>

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

            // Gọi API để lấy danh sách holders
            $holders = getNFTHolders($mintAddress);

            if (isset($holders['error'])) {
                echo "<p>" . htmlspecialchars($holders['error']) . "</p>";
            } elseif ($holders && !empty($holders['holders'])) {
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
            // Sử dụng API key mới bạn cung cấp
            $apiKey = "8eb75cd9-015a-4e24-9de2-5be9ee0f1c63";
            $url = "https://api.helius.xyz/v0/token-accounts?api-key=" . $apiKey;

            // Tạo payload cho API Helius
            $payload = [
                "mint" => $mintAddress,
                "includeOffChain" => false,
                "limit" => 1000 // Giới hạn tối đa 1000 ví mỗi lần gọi
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
            }

            $data = json_decode($response, true);

            if ($data && isset($data['token_accounts'])) {
                // Lấy danh sách ví duy nhất từ các token account
                $holders = array_unique(array_column($data['token_accounts'], 'owner'));
                return ['holders' => $holders];
            }

            return ['error' => 'No data found for this mint address.'];
        }
        ?>
    </div>
</section>

<!-- Include Footer -->
<?php include '../include/footer.php'; ?>

<script src="../js/vina.js"></script>
</body>
</html>
