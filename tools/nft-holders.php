<?php
// nft-holders.php
// Chức năng: Kiểm tra NFT Holders với Helius API, dùng session cache
session_start();
ini_set('log_errors', true);
ini_set('error_log', '/var/www/vinanetwork/public_html/tools/error_log.txt');
ini_set('display_errors', false);
error_reporting(E_ALL);

include 'api-helper.php';

file_put_contents('/var/www/vinanetwork/public_html/tools/debug_log.txt', date('Y-m-d H:i:s') . " - nft-holders.php loaded\n", FILE_APPEND);
error_log('nft-holders.php loaded at ' . date('Y-m-d H:i:s'));
?>

<div class="nft-holders-content">
    <div class="nft-checkbox">
        <h2>Check NFT Holders</h2>
        <p>Enter the <strong>NFT Collection</strong> address to see the number of holders and their wallet addresses. E.g: Find this address on MagicEden under "Details" > "On-chain Collection".</p>
        <form id="nftHoldersForm" method="POST" action="">
            <input type="text" name="mintAddress" id="mintAddressHolders" placeholder="Enter NFT Collection Address" required>
            <button type="submit">Check Holders</button>
        </form>
        <div class="loader" style="display: none;"></div> <!-- Đặt ngoài form để dễ quản lý -->
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            $mintAddress = trim($_POST['mintAddress']);
            file_put_contents('/var/www/vinanetwork/public_html/tools/debug_log.txt', date('Y-m-d H:i:s') . " - Form submitted with mintAddress=$mintAddress\n", FILE_APPEND);
            error_log("nft-holders.php: Form submitted with mintAddress=$mintAddress, page=" . ($_POST['page'] ?? 'not set') . " at " . date('Y-m-d H:i:s'));

            $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
            $holders_per_page = 50;
            $offset = ($page - 1) * $holders_per_page;
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
                    file_put_contents('/var/www/vinanetwork/public_html/tools/debug_log.txt', date('Y-m-d H:i:s') . " - Total API response (page $api_page): " . json_encode($total_data) . "\n", FILE_APPEND);
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

            $holders_data = getNFTHolders($mintAddress, $offset, $holders_per_page);
            file_put_contents('/var/www/vinanetwork/public_html/tools/debug_log.txt', date('Y-m-d H:i:s') . " - Holders API response: " . json_encode($holders_data) . "\n", FILE_APPEND);
            error_log("nft-holders.php: Holders API response - " . json_encode($holders_data) . " at " . date('Y-m-d H:i:s'));

            if (isset($holders_data['error'])) {
                throw new Exception("Helius API error: " . $holders_data['error']);
            } elseif ($holders_data && !empty($holders_data['holders'])) {
                $paginated_holders = $holders_data['holders'];

                $current_holders = min($page * $holders_per_page, $total_holders);
                $percentage = $total_holders > 0 ? number_format(($current_holders / $total_holders) * 100, 1) : 0;

                echo "<div class='result-section'>";
                echo "<h2>Results</h2>";
                echo "<p class='result-info'>Checking address: " . htmlspecialchars($mintAddress) . "</p>";
                echo "<p class='result-info'>Owners: $current_holders/$total_holders ($percentage%) (Page $page)</p>";

                echo "<div class='export-section'>";
                echo "<form method='POST' action='export-holders.php' class='export-form'>";
                echo "<input type='hidden' name='mintAddress' value='$mintAddress'>";
                echo "<input type='hidden' name='page' value='$page'>";
                echo "<div class='export-controls'>";
                echo "<select name='export_format' class='export-format'>";
                echo "<option value='csv'>CSV</option>";
                echo "<option value='json'>JSON</option>";
                echo "</select>";
                echo "<button type='submit' name='export_type' value='all' class='export-btn' id='export-all-btn'>Export All Holders</button>";
                echo "<button type='submit' name='export_type' value='current' class='export-btn'>Export Current Page</button>";
                echo "</div>";
                echo "</form>";
                echo "<div class='progress-container' style='display: none;'>";
                echo "<p>Exporting... Please wait.</p>";
                echo "<div class='progress-bar'><div class='progress-bar-fill' style='width: 0%;'></div></div>";
                echo "</div>";
                echo "</div>";

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

                echo "<div class='pagination'>";
                $total_pages = ceil($total_holders / $holders_per_page);

                if ($page > 1) {
                    echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='1'><button type='submit' class='page-button' data-type='number' id='page-first'>1</button></form>";
                } else {
                    echo "<span class='page-button active' data-type='number' id='page-first-active'>1</span>";
                }

                if ($page > 2) {
                    echo "<span class='page-button ellipsis' data-type='ellipsis' id='page-ellipsis-start'>...</span>";
                }

                if ($page > 1) {
                    echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page - 1) . "'><button type='submit' class='page-button nav' data-type='nav' id='page-prev' title='Previous'><i class='fa-solid fa-chevron-left'></i></button></form>";
                }

                if ($page > 1 && $page < $total_pages) {
                    echo "<span class='page-button active' data-type='number' id='page-current'>$page</span>";
                }

                if ($page < $total_pages) {
                    echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='" . ($page + 1) . "'><button type='submit' class='page-button nav' data-type='nav' id='page-next' title='Next'><i class='fa-solid fa-chevron-right'></i></button></form>";
                }

                if ($page < $total_pages - 1) {
                    echo "<span class='page-button ellipsis' data-type='ellipsis' id='page-ellipsis-end'>...</span>";
                }

                if ($page < $total_pages) {
                    echo "<form method='POST' class='page-form'><input type='hidden' name='mintAddress' value='$mintAddress'><input type='hidden' name='page' value='$total_pages'><button type='submit' class='page-button' data-type='number' id='page-last'>$total_pages</button></form>";
                } else {
                    echo "<span class='page-button active' data-type='number' id='page-last-active'>$total_pages</span>";
                }

                echo "</div>";
                echo "</div>";
                error_log("nft-holders.php: Retrieved " . count($paginated_holders) . " holders, page $page for address $mintAddress at " . date('Y-m-d H:i:s'));
            } else {
                throw new Exception("No holders found for this page or invalid collection address.");
            }
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            file_put_contents('/var/www/vinanetwork/public_html/tools/debug_log.txt', date('Y-m-d H:i:s') . " - $error_msg\n", FILE_APPEND);
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

<?php
function getNFTHolders($mintAddress, $offset = 0, $size = 50) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => ceil(($offset + $size) / $size),
        'limit' => $size
    ];
    
    file_put_contents('/var/www/vinanetwork/public_html/tools/debug_log.txt', date('Y-m-d H:i:s') . " - Calling Helius API for holders - mintAddress: $mintAddress, offset: $offset, size: $size, page: {$params['page']}\n", FILE_APPEND);
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
