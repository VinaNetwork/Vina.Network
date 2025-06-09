<?php
// nft-holders.php
define('VINANETWORK_ENTRY', true);
$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    echo "Config not found at: " . $configPath . " - Current dir: " . __DIR__ . " - Realpath: " . realpath($configPath);
    exit;
}
require_once $configPath;

session_start();
ini_set('log_errors', true);
ini_set('error_log', '../error_log.txt');
ini_set('display_errors', false);
error_reporting(E_ALL);

include '../api-helper.php';

file_put_contents('../error_log.txt', date('Y-m-d H:i:s') . " - nft-holders.php loaded\n", FILE_APPEND);
error_log('nft-holders.php loaded at ' . date('Y-m-d H:i:s'));
?>

<div class="nft-holders-content">
    <div class="nft-checkbox">
        <h2>Check NFT Holders</h2>
        <p>Enter the <strong>NFT Collection</strong> address to see the number of holders and their wallet addresses. E.g: Find this address on MagicEden under "Details" > "On-chain Collection".</p>
        <form id="nftHoldersForm" method="POST" action="">
            <input type="text" name="mintAddress" id="mintAddressHolders" placeholder="Enter NFT Collection Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <button type="submit">Check Holders</button>
        </form>
        <div class="loader" style="display: none;"></div>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            $mintAddress = trim($_POST['mintAddress']);
            file_put_contents('../error_log.txt', date('Y-m-d H:i:s') . " - Form submitted with mintAddress=$mintAddress\n", FILE_APPEND);
            error_log("nft-holders.php: Form submitted with mintAddress=$mintAddress, page=" . ($_POST['page'] ?? 'not set') . " at " . date('Y-m-d H:i:s'));

            $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
            $holders_per_page = 50;
            $limit = 1000;

            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                throw new Exception("Invalid collection address. Please enter a valid Solana collection address (32-44 characters, base58).");
            }

            if (!isset($_SESSION['last_mintAddress']) || $_SESSION['last_mintAddress'] !== $mintAddress) {
                if (isset($_SESSION['total_holders'][$mintAddress])) {
                    unset($_SESSION['total_holders'][$mintAddress]);
                    error_log("nft-holders.php: Cleared session cache for new mintAddress=$mintAddress at " . date('Y-m-d H:i:s'));
                }
                $_SESSION['last_mintAddress'] = $mintAddress;
            }

            if (!isset($_SESSION['total_holders'][$mintAddress])) {
                $total_holders = 0;
                $api_page = 1;
                $has_more = true;

                while ($has_more) {
                    $total_params = [
                        'groupKey' => 'collection',
                        'groupValue' => $mintAddress,
                        'page' => $api_page,
                        'limit' => $limit
                    ];
                    $total_data = callHeliusAPI('getAssetsByGroup', $total_params, 'POST');
                    file_put_contents('../error_log.txt', date('Y-m-d H:i:s') . " - Total API response (page $api_page): " . json_encode($total_data) . "\n", FILE_APPEND);
                    error_log("nft-holders.php: Total API response (page $api_page) - " . json_encode($total_data) . " at " . date('Y-m-d H:i:s'));

                    if (isset($total_data['error'])) {
                        throw new Exception("Helius API error: " . $total_data['error']);
                    }

                    $items = $total_data['result']['items'] ?? [];
                    $item_count = count($items);
                    $total_holders += $item_count;
                    error_log("nft-holders.php: Page $api_page added $item_count holders, total_holders = $total_holders at " . date('Y-m-d H:i:s'));

                    if ($item_count < $limit) {
                        $has_more = false;
                    } else {
                        $api_page++;
                    }
                }

                $_SESSION['total_holders'][$mintAddress] = $total_holders;
                error_log("nft-holders.php: Cached total_holders = $total_holders for $mintAddress at " . date('Y-m-d H:i:s'));
            } else {
                $total_holders = $_SESSION['total_holders'][$mintAddress];
                error_log("nft-holders.php: Retrieved total_holders = $total_holders from cache for $mintAddress at " . date('Y-m-d H:i:s'));
            }

            error_log("nft-holders.php: Final total holders = $total_holders for $mintAddress at " . date('Y-m-d H:i:s'));

            if ($total_holders === 0) {
                throw new Exception("No holders found or invalid collection address.");
            } elseif ($limit > 0 && $total_holders % $limit === 0 && $total_holders >= $limit) {
                error_log("nft-holders.php: Suspicious total_holders ($total_holders) is multiple of limit for $mintAddress at " . date('Y-m-d H:i:s'));
                echo "<div class='result-error'><p>Warning: Total holders ($total_holders) is a multiple of API limit ($limit). Actual number may be higher.</p></div>";
            }

            // Hiển thị danh sách holders + phân trang
            // Lưu ý: chỉ include khi có POST (đã nhập địa chỉ)
            ?>
            <div id="holders-list" data-mint="<?php echo htmlspecialchars($mintAddress) ?>">
                <?php
                // include file AJAX hóa phần này
                $ajax_page = 1;
                if (isset($_POST['page']) && is_numeric($_POST['page'])) $ajax_page = (int)$_POST['page'];
                include 'nft-holders-list.php';
                ?>
            </div>
            <?php

        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            file_put_contents('../error_log.txt', date('Y-m-d H:i:s') . " - $error_msg\n", FILE_APPEND);
            echo "<div class='result-error'><p>$error_msg. Please try again.</p></div>";
            error_log("nft-holders.php: Exception - $error_msg at " . date('Y-m-d H:i:s'));
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    var holdersList = document.getElementById('holders-list');
    if (holdersList) {
        holdersList.addEventListener('click', function(e) {
            if (e.target.classList.contains('page-button') && e.target.dataset.type !== 'ellipsis') {
                e.preventDefault();
                var page = e.target.closest('form')?.querySelector('input[name="page"]')?.value
                    || e.target.dataset.page;
                var mint = holdersList.dataset.mint;
                if (!page || !mint) return;
                // AJAX tải lại bảng holders
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'nft-holders-list.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        holdersList.innerHTML = xhr.responseText;
                    }
                };
                xhr.send('mintAddress=' + encodeURIComponent(mint) + '&page=' + encodeURIComponent(page));
            }
        });
    }
});
</script>

<?php
// Giữ lại hàm getNFTHolders ở cuối file để file include có thể dùng
function getNFTHolders($mintAddress, $offset = 0, $size = 50) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => ceil(($offset + $size) / $size),
        'limit' => $size
    ];
    
    file_put_contents('../error_log.txt', date('Y-m-d H:i:s') . " - Calling Helius API for holders - mintAddress: $mintAddress, offset: $offset, size: $size, page: {$params['page']}\n", FILE_APPEND);
    error_log("nft-holders.php: Calling Helius API for holders - mintAddress: $mintAddress, offset: $offset, size: $size, page: {$params['page']} at " . date('Y-m-d H:i:s'));
    
    $data = callHeliusAPI('getAssetsByGroup', $params, 'POST');
    
    if (isset($data['error'])) {
        error_log("nft-holders.php: getAssetsByGroup error - " . json_encode($data) . " at " . date('Y-m-d H:i:s'));
        return ['error' => 'This is not an NFT collection address. Please enter a valid NFT Collection address.'];
    }
    
    if (isset($data['result']['items']) && !empty($data['result']['items'])) {
        $holders = array_map(function($item) {
            return [
                'owner' => $item['ownership']['owner'] ?? 'unknown',
                'amount' => 1
            ];
        }, $data['result']['items']);
        
        return ['holders' => $holders];
    }
    
    error_log("nft-holders.php: No holders found for address $mintAddress at " . date('Y-m-d H:i:s'));
    return ['error' => 'This is not an NFT collection address. Please enter a valid NFT Collection address.'];
}
?>
