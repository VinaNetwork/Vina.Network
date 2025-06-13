<?php
// tools/nft-holders.php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}
$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-holders: bootstrap.php not found at $bootstrap_path", 'nft_holders_log.txt', 'ERROR');
    die('Error: bootstrap.php not found');
}
require_once $bootstrap_path;

session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);
$root_path = '../../';
$page_title = 'Check NFT Holders - Vina Network';
$page_description = 'Check NFT holders for a Solana collection address.';
$page_css = ['../../css/vina.css', '../tools.css'];
include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';

$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-holders: tools-api.php not found at $api_helper_path", 'nft_holders_log.txt', 'ERROR');
    die('Internal Server Error: Missing tools-api.php');
}
log_message("nft-holders: Including tools-api.php from $api_helper_path", 'nft_holders_log.txt');
include $api_helper_path;

log_message("nft-holders: Loaded at " . date('Y-m-d H:i:s'), 'nft_holders_log.txt');
?>
<div class="t-6 nft-holders-content">
    <div class="t-7">
        <h2>Check NFT Holders</h2>
        <p>Enter the <strong>NFT Collection</strong> address to see the number of holders and their wallet addresses. E.g: Find this address on MagicEden under "Details" > "On-chain Collection".</p>
        <form id="nftHoldersForm" method="POST" action="">
            <input type="text" name="mintAddress" id="mintAddressHolders" placeholder="Enter NFT Collection Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <button type="submit">Check Holders</button>
        </form>
        <div class="loader"></div>
    </div>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            $mintAddress = trim($_POST['mintAddress']);
            log_message("nft-holders: Form submitted with mintAddress=$mintAddress, page=" . ($_POST['page'] ?? 'not set'), 'nft_holders_log.txt');
            $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
            $holders_per_page = 50;
            $limit = 1000;
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                throw new Exception("Invalid collection address. Please enter a valid Solana collection address (32-44 characters, base58).");
            }
            if (!isset($_SESSION['last_mintAddress']) || $_SESSION['last_mintAddress'] !== $mintAddress) {
                if (isset($_SESSION['total_items'][$mintAddress])) {
                    unset($_SESSION['total_items'][$mintAddress]);
                    log_message("nft-holders: Cleared session cache for new mintAddress=$mintAddress", 'nft_holders_log.txt');
                }
                $_SESSION['last_mintAddress'] = $mintAddress;
            }
            if (!isset($_SESSION['total_items'][$mintAddress])) {
                $total_items = 0;
                $api_page = 1;
                $has_more = true;
                $items = [];
                while ($has_more) {
                    $total_params = [
                        'groupKey' => 'collection',
                        'groupValue' => $mintAddress,
                        'page' => $api_page,
                        'limit' => $limit
                    ];
                    log_message("nft-holders: Calling API for total items, page=$api_page", 'nft_holders_log.txt');
                    $total_data = callAPI('getAssetsByGroup', $total_params, 'POST');
                    log_message("nft-holders: Total API response (page $api_page): URL=https://mainnet.helius-rpc.com/?api-key=****, Params=" . json_encode($total_params) . ", Response=" . json_encode($total_data), 'nft_holders_log.txt');
                    if (isset($total_data['error'])) {
                        $errorMessage = is_array($total_data['error']) && isset($total_data['error']['message']) ? $total_data['error']['message'] : json_encode($total_data['error']);
                        throw new Exception("API error: " . $errorMessage);
                    }
                    $page_items = $total_data['result']['items'] ?? [];
                    $item_count = count($page_items);
                    $items = array_merge($items, array_map(function($item) {
                        return [
                            'owner' => $item['ownership']['owner'] ?? 'unknown',
                            'amount' => 1
                        ];
                    }, $page_items));
                    $total_items += $item_count;
                    log_message("nft-holders: Page $api_page added $item_count items, total_items = $total_items", 'nft_holders_log.txt');
                    if ($item_count < $limit) {
                        $has_more = false;
                    } else {
                        $api_page++;
                    }
                }
                // Get unique wallets
                $unique_wallets = [];
                foreach ($items as $item) {
                    $owner = $item['owner'];
                    if (!isset($unique_wallets[$owner])) {
                        $unique_wallets[$owner] = $item;
                    } else {
                        $unique_wallets[$owner]['amount'] += 1;
                    }
                }
                $wallets = array_values($unique_wallets);
                $_SESSION['total_items'][$mintAddress] = $total_items;
                $_SESSION['total_wallets'][$mintAddress] = count($wallets);
                $_SESSION['items'][$mintAddress] = $items;
                $_SESSION['wallets'][$mintAddress] = $wallets;
                log_message("nft-holders: Cached total_items = $total_items, total_wallets = " . count($wallets) . " for $mintAddress", 'nft_holders_log.txt');
            } else {
                $total_items = $_SESSION['total_items'][$mintAddress];
                $total_wallets = $_SESSION['total_wallets'][$mintAddress];
                log_message("nft-holders: Retrieved total_items = $total_items, total_wallets = $total_wallets from cache for $mintAddress", 'nft_holders_log.txt');
            }
            log_message("nft-holders: Final total items = $total_items, total wallets = $total_wallets for $mintAddress", 'nft_holders_log.txt');
            if ($total_items === 0) {
                throw new Exception("No items found or invalid collection address.");
            } elseif ($limit > 0 && $total_items % $limit === 0 && $total_items >= $limit) {
                log_message("nft-holders: Suspicious total_items ($total_items) is a multiple of limit for $mintAddress", 'nft_holders_log.txt', 'WARNING');
                echo "<div class='result-error'><p>Warning: Total items ($total_items) is a multiple of API limit ($limit). Actual number may be higher.</p></div>";
            }
            ?>
            <div id="holders-list" data-mint="<?php echo htmlspecialchars($mintAddress); ?>">
                <?php
                $ajax_page = $page;
                log_message("nft-holders: Including nft-holders-info.php with page=$ajax_page", 'nft_holders_log.txt');
                ob_start();
                include 'nft-holders-info.php';
                $holders_output = ob_get_clean();
                echo $holders_output;
                log_message("nft-holders: Holders list output length: " . strlen($holders_output), 'nft_holders_log.txt');
                ?>
            </div>
            <?php
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            log_message("nft-holders: Exception - $error_msg", 'nft_holders_log.txt', 'ERROR');
            echo "<div class='result-error'><p>$error_msg. Please try again.</p></div>";
        }
    }
    ?>
    <div class="t-9">
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
                if (!page || !mint) {
                    console.error('Missing page or mint:', { page, mint });
                    return;
                }
                console.log('Sending AJAX request for page:', page, 'mint:', mint);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/tools/nft-holders/nft-holders-info.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        console.log('AJAX response status:', xhr.status, 'Response:', xhr.responseText.substring(0, 200));
                        if (xhr.status === 200) {
                            holdersList.innerHTML = xhr.responseText;
                        } else {
                            console.error('AJAX error:', xhr.status, xhr.statusText, 'Response:', xhr.responseText);
                            holdersList.innerHTML = '<div class="result-error"><p>Error loading holders. Status: ' + xhr.status + '. Please try again.</p></div>';
                        }
                    }
                };
                var data = 'mintAddress=' + encodeURIComponent(mint) + '&page=' + encodeURIComponent(page);
                console.log('AJAX data:', data);
                xhr.send(data);
            }
        });
    }
});
</script>

<?php
ob_start();
include $root_path . 'include/footer.php';
$footer_output = ob_get_clean();
log_message("nft-holders: Footer output length: " . strlen($footer_output), 'nft_holders_log.txt');
echo $footer_output;
?>
