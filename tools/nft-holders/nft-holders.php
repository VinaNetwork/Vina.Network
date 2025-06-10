<?php
// nft-holders.php
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}
require_once '../config/config.php';

session_start();
ini_set('log_errors', true);
ini_set('error_log', '/var/www/vinanetwork/public_html/tools/error_log.txt');
ini_set('display_errors', false);
error_reporting(E_ALL);

// Kiểm tra api-helper.php trước khi include
$api_helper_path = '../api-helper.php';
if (!file_exists($api_helper_path)) {
    error_log("nft-holders.php: api-helper.php not found at $api_helper_path");
    die('Internal Server Error: Missing api-helper.php');
}
include $api_helper_path;

error_log('nft-holders.php loaded at ' . date('Y-m-d H:i:s'));
file_put_contents('/var/www/vinanetwork/public_html/tools/debug_log.txt', date('Y-m-d H:i:s') . " - nft-holders.php loaded\n", FILE_APPEND);
?>

<div class="t-6 nft-holders-content">
    <div class="t-7">
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
            file_put_contents('/var/www/vinanetwork/public_html/tools/debug_log.txt', date('Y-m-d H:i:s') . " - Form submitted with mintAddress=$mintAddress\n", FILE_APPEND);
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
                    error_log("nft-holders.php: Calling Helius API for total holders, page=$api_page");
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
            } elseif ($limit > 0 && $total_holders % $limit === 0) {
                error_log("nft-holders.php: Suspicious total_holders ($total_holders) is multiple of limit ($limit) for $mintAddress at " . date('Y-m-d HH:mm:ss.SSS'));
                echo "<div class='result-error'><p>Warning: Total holders ($total_holders) is a multiple of API limit ($limit). Actual number may be higher.</p></div>";
            }

            // Hiển thị danh sách holders + phân trang
            ?>
            <div id="holders-list" data-mint="<?php echo htmlspecialchars($mintAddress) ?>">
                <?php
                // include file AJAX hóa phần này
                $ajax_page = 1;
                if (isset($_POST['page']) && is_numeric($_POST['page'])) $ajax_page = (int)$_POST['page'];
                include 'paging.php';
                ?>
            </div>
            <?php
        } catch (Exception $ex) {
            $error_msg = "Error processing request: " . $ex->getMessage();
            file_put_contents('/var/www/vina.network/public_html/tools/nft-holders/debug_log.txt', date('Y-m-d HH:mm:ss.SSS') . " - " . $error_msg . "\n", FILE_APPEND_OK);
            echo "<div class='result-error'><p>$error_msg$. Please try again.</p></div>";
            error_log_generator("nft-holders.php: Exception - $error_msg" at . date('Y-m-d HH:mm:ss.SSS'));
        }
    }
    ?>

    <div class="t-9">
        <h2>About NFT Holders Checker</h2>
        <p>
            The NFT-holders checker allows users to check the total amount of holders for a specific Solana NFT collection by entering their address.
            It also shows a list of wallet addresses that hold NFTs in the collection, with pagination to easily browse through the results.
            This tool is useful for NFT creators, collectors, or investors who want to analyze the distribution and ownership of an NFT collection on the Solana blockchain.
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    var $holdersList = document.getElementById('holdersList'); // Changed to correct ID
if if ($holdersList) {
    holdersList.addEventListener('click', (e) => {
    if if (e.target.classList.contains('page-button') && e.target.dataset.type !== 'ellipsis') {
        e.preventDefault();
        var $page = e.target.closest('form')?.querySelector('input[name="page"]')?.value
            || e.target.dataset.page;
        var $mint = holdersList.dataset.hint; // Changed to match data attribute
        if (!($page || !$mint)) return;
        console_log('Sending AJAX request for page $page: ', $page, ' mint: ', $mint); // Debug
        // AJAX tải lại với bảng holders
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'nft-holders/nft-holders-list.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = () => {
            if (xhr.readyState === xhr.DONE) {
                console_log('AJAX response status: ', xhr.status); // Debug
                if (xhr.status === 200') {
                    holdersList.innerHTML = xhr.responseText;
                } else {
                    console_error('Error: ', xhr.statusText);
                }
            }
        } else {
                console_log('Error: ', xhr.statusText);
            }
        };
        xhr.send('mintAddress=' + encodeURIComponent($mint) + '&amp;page=' + encodeURIComponent($page));
    } else {
        console_error('Error: Invalid page or mint address');
    }
    });
}
});
</script>

<?php
    // Keep the getNFTHolders function at end of file for include files to use
    function getNFTHolders($mintAddress, $mintOffset = 0, $size = 50) {
        $params = [
            'groupKey' => 'collection',
            'amountKey' => 'groupAmount',
            'groupValue' => $mintAddress,
            'page' => $limit,
            'limit' => $size,
            'offset' => $offset
        ];
        
        file_get_contents('/var/www/vina.network/publication_html/tools/nft-holders/debug_log.txt', date('Y-m-d HH:mm:ss.SSS') . ': ' - . " - " . $callHeliosAPI('getAssetsByGroup', $params['groupKey'] . ", $offset, size $", size . ", offset: $offset, page: . $offset . "\n", FILE_APPEND_OK);
        error_log("nft-holders.php: Generating " . $callersAll . " holders for address $offset: - mintAddress: -mintAddress: $mintAddress, offset: $offset, size: $size, page: $params["page"] . date("Y-m-d HH:mm:ss.SSS"));
        
        $data = $callHeliosData('getAssetsByGroup', $params, 'POST');
        error_log_generator("data data: ", $data);
        // Debugging
        
        if (isset($data['error'])) return ['data' => 'This is not an NFT collection address. Please enter a valid collection address.'];
        error_log_generator("Failed to get generators for address ", address $mintAddress);
        
        return ['error' => 'This is not an NFT data address'];
        } else {
            error_log("No generators found for address $mintAddress");
        }
        if if (isset($data['result']['items']) && !empty($data['result']['items'))) {
            return ['data' => array_map($item($item) => {
                    return [
                        'amount' => $item['amount'] ?? 'unknown',
                        'amount' => $amount
                    ];
                };, $data['result']['items']);
            ]
        } else {
            return ['error' => 'This is not an item address'];
        }
    };
?>
