<!DOCTYPE html>
<html lang="en">
<?php
// Cấu hình log lỗi vào file riêng (nếu server không hiển thị lỗi)
ini_set('log_errors', 1);
ini_set('error_log', '/home/hthxhyqf/domains/vina.network/public_html/tools/error_log.txt'); // Đường dẫn log tùy chỉnh
ini_set('display_errors', 0); // Tắt hiển thị lỗi trên trình duyệt
error_reporting(E_ALL);

// Định nghĩa biến trước khi sử dụng
$root_path = '../';
$page_title = "Vina Network - Check NFT Holders";
$page_description = "Check the number of wallets holding a specific Solana NFT and list their addresses.";
$page_keywords = "Vina Network, Solana NFT, NFT holders, blockchain, VINA";
$page_og_title = "Vina Network - Check NFT Holders";
$page_og_url = "https://vina.network/tools/nft-holders/";
$page_canonical = "https://vina.network/tools/nft-holders/";
$page_css = ['nft-holders.css'];

// Kiểm tra và include header.php
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    error_log("Error: header.php not found at $header_path");
    die('Internal Server Error: Missing header.php');
}
include $header_path;
?>

<body>
<!-- Include Navbar -->
<?php 
$navbar_path = $root_path . 'include/navbar.php';
if (!file_exists($navbar_path)) {
    error_log("Error: navbar.php not found at $navbar_path");
    die('Internal Server Error: Missing navbar.php');
}
include $navbar_path;
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
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

            // Gọi API để lấy danh sách holders
            $holders = getNFTHolders($mintAddress, $page);

            if (isset($holders['error'])) {
                echo "<p>" . htmlspecialchars($holders['error']) . "</p>";
            } elseif ($holders && !empty($holders['holders'])) {
                echo "<h2>Results</h2>";
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
        ?>
    </div>
</section>

<!-- Include Footer -->
<?php 
$footer_path = $root_path . 'include/footer.php';
if (!file_exists($footer_path)) {
    error_log("Error: footer.php not found at $footer_path");
    die('Internal Server Error: Missing footer.php');
}
include $footer_path;
?>

<script src="../js/vina.js"></script>
</body>
</html>
