<!DOCTYPE html>
<html lang="en">
<?php
// Bật hiển thị lỗi để debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$root_path = '../'; // Đường dẫn về thư mục gốc
$page_title = "Vina Network - Check NFT Holders";
$page_description = "Check the number of wallets holding a specific Solana NFT and list their addresses.";
$page_keywords = "Vina Network, Solana NFT, NFT holders, blockchain, VINA"; // Sửa $VINA thành VINA
$page_og_title = "Vina Network - Check NFT Holders";
$page_og_url = "https://vina.network/tools/nft-holders/";
$page.canonical = "https://vina.network/tools/nft-holders/";
$page_css = ['nft-holders.css']; // Đường dẫn đến file CSS trong thư mục tools

// Kiểm tra file header.php
if (!file_exists('../include/header.php')) {
    die('Error: header.php not found.');
}
include '../include/header.php'; // header.php chỉ cung cấp <head>
?>

<body>
<!-- Include Navbar -->
<?php 
if (!file_exists('../include/navbar.php')) {
    die('Error: navbar.php not found.');
}
include '../include/navbar.php'; 
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
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1; // Lấy số trang từ form, mặc định là 1

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

                // Thêm nút phân trang
                echo "<div class='pagination'>";
                if ($page > 1) {
                    echo "<form method='POST' style='display:inline;'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page - 1) . "'><button type='submit'>Previous</button></form>";
                }
                // Chỉ hiển thị nút "Next" nếu số lượng ví trả về bằng giới hạn (1000), nghĩa là có thể còn dữ liệu ở trang tiếp theo
                if (count($holders['holders']) == 1000) {
                    echo "<form method='POST' style='display:inline; margin-left: 10px;'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page + 1) . "'><button type='submit'>Next</button></form>";
                }
                echo "</div>";
            } else {
                echo "<p>No holders found or invalid mint address.</p>";
            }
        }

        function getNFTHolders($mintAddress, $page = 1) {
            // Sử dụng API key bạn cung cấp
            $apiKey = "8eb75cd9-015a-4e24-9de2-5be9ee0f1c63";
            $url = "https://api.helius.xyz/v0/token-accounts?api-key=" . $apiKey;

            // Tạo payload cho API Helius
            $payload = [
                "mint" => $mintAddress,
                "includeOffChain" => false,
                "limit" => 1000, // Giới hạn tối đa 1000 ví mỗi lần gọi
                "page" => $page // Thêm tham số page
            ];

            $ch = curl_init();
            if (!$ch) {
                return ['error' => 'Failed to initialize cURL.'];
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            if ($response === false) {
                $curlError = curl_error($ch);
                curl_close($ch);
                return ['error' => 'cURL error: ' . $curlError];
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['error' => 'Failed to fetch data from API. HTTP Code: ' . $httpCode];
            }

            $data = json_decode($response, true);
            if ($data === null) {
                return ['error' => 'Failed to parse API response as JSON.'];
            }

            if (isset($data['token_accounts'])) {
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
<?php 
if (!file_exists('../include/footer.php')) {
    die('Error: footer.php not found.');
}
include '../include/footer.php'; 
?>

<script src="../js/vina.js"></script>
</body>
</html>
