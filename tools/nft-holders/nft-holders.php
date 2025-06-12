<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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
ini_set('display_errors', false);
error_reporting(E_ALL);
$root_path = '../../';
$page_title = 'Check NFT Holders - Vina Network';
$page_description = 'Check NFT holders for a Solana collection address.';
$page_css = ['/css/vina.css', '/tools/tools.css'];
include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';

$api_helper_path = dirname(__DIR__) . '/api-helper.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-holders: api-helper.php not found at $api_helper_path", 'nft_holders_log.txt', 'ERROR');
    die('Internal Server Error: Missing api-helper.php');
}
log_message("nft-holders: Including api-helper.php from $api_helper_path", 'nft_holders_log.txt');
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
        <div class="loader" style="display: none;"></div>
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
                if (isset($_SESSION['total_holders'][$mintAddress])) {
                    unset($_SESSION['total_holders'][$mintAddress]);
                    log_message("nft-holders: Cleared session cache for new mintAddress=$mintAddress", 'nft_holders_log.txt');
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
                    log_message("nft-holders: Calling API for total holders, page=$api_page", 'nft_holders_log.txt');
                    $total_data = callAPI('getAssetsByGroup', $total_params, 'POST');
                    log_message("nft-holders: Total API response (page $api_page): URL=https://mainnet.helius-rpc.com/?api-key=****, Params=" . json_encode($total_params) . ", Response=" . json_encode($total_data), 'nft_holders_log.txt');
                    if (isset($total_data['error'])) {
                        throw new Exception("API error: " . $total_data['error']['message']);
                    }
                    $items = $total_data['result']['items'] ?? [];
                    $item_count = count($items);
                    $total_holders += $item_count;
                    log_message("nft-holders: Page $api_page added $item_count holders, total_holders = $total_holders", 'nft_holders_log.txt');
                    if ($item_count < $limit) {
                        $has_more = false;
                    } else {
                        $api_page++;
                    }
                }
                $_SESSION['total_holders'][$mintAddress] = $total_holders;
                log_message("nft-holders: Cached total_holders = $total_holders for $mintAddress", 'nft_holders_log.txt');
            } else {
                $total_holders = $_SESSION['total_holders'][$mintAddress];
                log_message("nft-holders: Retrieved total_holders = $total_holders from cache for $mintAddress", 'nft_holders_log.txt');
            }
            log_message("nft-holders: Final total holders = $total_holders for $mintAddress", 'nft_holders_log.txt');
            if ($total_holders === 0) {
                throw new Exception("No holders found or invalid collection address.");
            } elseif ($limit > 0 && $total_holders % $limit === 0 && $total_holders >= $limit) {
                log_message("nft-holders: Suspicious total_holders ($total_holders) is multiple of limit for $mintAddress", 'nft_holders_log.txt', 'WARNING');
                echo "<div class='result-error'><p>Warning: Total holders ($total_holders) is a multiple of API limit ($limit). Actual number may be higher.</p></div>";
            }
            ?>
            <div id="holders-list" data-mint="<?php echo htmlspecialchars($mintAddress) ?>">
                <?php
                $ajax_page = 1;
                if (isset($_POST['page']) && is_numeric($_POST['page'])) $ajax_page = (int)$_POST['page'];
                $mintAddress = $mintAddress ?? '';
                include __DIR__ . '/nft-holders-list.php';
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
<?php
function getNFTHolders($mintAddress, $offset = 0, $size = 50) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => ceil(($offset + $size) / $size),
        'limit' => $size
    ];
    log_message("nft-holders: Calling API for holders - mintAddress: $mintAddress, offset: $offset, size: $size, page: {$params['page']}", 'nft_holders_log.txt');
    $data = callAPI('getAssetsByGroup', $params, 'POST');
    log_message("nft-holders: API response - URL=https://mainnet.helius-rpc.com/?api-key=****, Params=" . json_encode($params) . ", Response=" . json_encode($data), 'nft_holders_log.txt');
    if (isset($data['error'])) {
        log_message("nft-holders: getAssetsByGroup error - " . json_encode($data), 'nft_holders_log.txt', 'ERROR');
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
    log_message("nft-holders: No holders found for address $mintAddress", 'nft_holders_log.txt', 'ERROR');
    return ['error' => 'This is not an NFT collection address. Please enter a valid NFT Collection address.'];
}
include $root_path . 'include/footer.php';
?>
