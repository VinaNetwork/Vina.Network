<?php
define('VINANETWORK_STATUS', true);
require_once '../bootstrap.php';

session_start();
log_message('nft-tools.php: Script started');

$api_helper_path = TOOLS_PATH . 'api-helper.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-tools.php: api-helper.php not found at $api_helper_path", 'error_log.txt', 'ERROR');
    die('Internal Server Error: Missing api-helper.php');
}
include $api_helper_path;
?>

<div class="t-6 nft-holders-content">
    <div class="t-7">
        <h2>Check NFT Tools</h2>
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
            log_message("nft-tools.php: Form submitted with mintAddress=$mintAddress, page=" . ($_POST['page'] ?? 'not set'));

            $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
            $holders_per_page = 50;
            $limit = 1000;

            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                throw new Exception("Invalid collection address. Please enter a valid Solana collection address (32-44 characters, base58).");
            }

            if (!isset($_SESSION['last_mintAddress']) || $_SESSION['last_mintAddress'] !== $mintAddress) {
                if (isset($_SESSION['total_holders'][$mintAddress])) {
                    unset($_SESSION['total_holders'][$mintAddress]);
                    log_message("nft-tools.php: Cleared session cache for new mintAddress=$mintAddress");
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
                    log_message("nft-tools.php: Calling Helius API for total holders, page=$api_page");
                    $total_data = callHeliusAPI('getAssetsByGroup', $total_params, 'POST');

                    if (isset($total_data['error'])) {
                        throw new Exception("Helius API error: " . $total_data['error']);
                    }

                    $items = $total_data['result']['items'] ?? [];
                    $item_count = count($items);
                    $total_holders += $item_count;
                    log_message("nft-tools.php: Page $api_page added $item_count holders, total_holders = $total_holders");

                    if ($item_count < $limit) {
                        $has_more = false;
                    } else {
                        $api_page++;
                    }
                }

                $_SESSION['total_holders'][$mintAddress] = $total_holders;
                log_message("nft-tools.php: Cached total_holders = $total_holders for $mintAddress");
            } else {
                $total_holders = $_SESSION['total_holders'][$mintAddress];
                log_message("nft-tools.php: Retrieved total_holders = $total_holders from cache for $mintAddress");
            }

            log_message("nft-tools.php: Final total holders = $total_holders for $mintAddress");

            if ($total_holders === 0) {
                throw new Exception("No holders found or invalid collection address.");
            } elseif ($limit > 0 && $total_holders % $limit === 0 && $total_holders >= $limit) {
                log_message("nft-tools.php: Suspicious total_holders ($total_holders) is multiple of limit for $mintAddress", 'error_log.txt', 'WARNING');
                echo "<div class='result-error'><p>Warning: Total holders ($total_holders) is a multiple of API limit ($limit). Actual number may be higher.</p></div>";
            }

            ?>
            <div id="holders-list" data-mint="<?php echo htmlspecialchars($mintAddress) ?>">
                <?php
                $ajax_page = 1;
                if (isset($_POST['page']) && is_numeric($_POST['page'])) $ajax_page = (int)$_POST['page'];
                include 'nft-tools-list.php';
                ?>
            </div>
            <?php

        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            log_message("nft-tools.php: Exception - $error_msg", 'error_log.txt', 'ERROR');
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
                if (!page || !mint) return;
                console.log('Sending AJAX request for page:', page, 'mint:', mint);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'nft-tools-list.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        console.log('AJAX response status:', xhr.status);
                        if (xhr.status === 200) {
                            holdersList.innerHTML = xhr.responseText;
                        } else {
                            console.error('AJAX error:', xhr.statusText);
                        }
                    }
                };
                xhr.send('mintAddress=' + encodeURIComponent(mint) + '&page=' + encodeURIComponent(page));
            }
        });
    }
});
</script>

<?php
function getNFTHolders($mintAddress, $offset = 0, $size = 50) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => ceil(($offset + $size) / $size),
        'limit' => $size
    ];
    
    log_message("nft-tools.php: Calling Helius API for holders - mintAddress: $mintAddress, offset: $offset, size: $size, page: {$params['page']}");
    
    $data = callHeliusAPI('getAssetsByGroup', $params, 'POST');
    
    if (isset($data['error'])) {
        log_message("nft-tools.php: getAssetsByGroup error - " . json_encode($data), 'error_log.txt', 'ERROR');
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
    
    log_message("nft-tools.php: No holders found for address $mintAddress", 'error_log.txt', 'ERROR');
    return ['error' => 'This is not an NFT collection address. Please enter a valid NFT Collection address.'];
}
?>
