<?php
// api-helper.php

// Include file cấu hình
$config_path = dirname(__DIR__) . '/config/config.php';
if (!file_exists($config_path)) {
    error_log("Error: config.php not found at $config_path");
    die('Internal Server Error: Missing config.php');
}
include $config_path;

// Hàm gọi API Helius với caching
function callHeliusAPI($endpoint, $params = [], $method = 'POST') {
    // Tạo key cho cache dựa trên endpoint và params
    $cache_key = md5($endpoint . serialize($params));
    $cache_dir = BASE_PATH . 'cache/';
    $cache_file = $cache_dir . $cache_key . '.cache';
    $cache_time = 300; // 5 phút

    // Kiểm tra cache
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        $cached_data = file_get_contents($cache_file);
        return json_decode($cached_data, true);
    }

    // Nếu không có cache, gọi API
    $url = "https://api.helius.xyz/v0/{$endpoint}?api-key=" . HELIUS_API_KEY;

    $ch = curl_init();
    if (!$ch) {
        error_log("cURL initialization failed.");
        return ['error' => 'Failed to initialize cURL.'];
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if (!empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    if ($response === false) {
        $curlError = curl_error($ch);
        error_log("cURL error: $curlError");
        curl_close($ch);
        return ['error' => 'Failed to fetch data. Please try again later. Error: ' . $curlError];
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
        return ['error' => 'Failed to parse API response. Please try again later.'];
    }

    // Lưu vào cache
    if (!file_exists($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    file_put_contents($cache_file, json_encode($data));

    return $data;
}
?>

#### Cập nhật `nft-holders.php`
- Thêm kiểm tra mint address và thông báo lỗi chi tiết.
- **Nội dung**:
```php
<?php
// Chức năng: Kiểm tra NFT Holders
include 'api-helper.php';

// Hàm kiểm tra mint address hợp lệ
function validateMintAddress($mintAddress) {
    return !empty($mintAddress) && strlen($mintAddress) > 20 && preg_match('/^[A-Za-z0-9]+$/', $mintAddress);
}
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

    if (!validateMintAddress($mintAddress)) {
        echo "<div class='result-error'><p>Invalid mint address. Please enter a valid Solana mint address (alphanumeric characters only).</p></div>";
    } else {
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
            echo "<div class='result-error'><p>No holders found for this NFT.</p></div>";
        }
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
