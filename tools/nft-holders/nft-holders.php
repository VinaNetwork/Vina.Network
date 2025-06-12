<?php
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
        <div class="loader"></div>
        <div id="holders-list"></div>
    </div>
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
                var page = e.target.closest('form')?.querySelector('input[name="page"]')?.value || e.target.dataset.page;
                var mint = holdersList.dataset.mint;
                if (!page || !mint) {
                    console.error('Missing page or mint:', { page, mint });
                    return;
                }
                console.log('Sending AJAX request for page:', page, 'mint:', mint);
                var loader = document.querySelector('.loader');
                if (loader) loader.style.display = 'block';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/tools/nft-holders/nft-holders-list.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        console.log('Pagination AJAX response status:', xhr.status);
                        if (loader) loader.style.display = 'none';
                        if (xhr.status === 200) {
                            holdersList.innerHTML = xhr.responseText;
                        } else {
                            console.error('Pagination AJAX error:', xhr.status, xhr.statusText);
                            holdersList.innerHTML = '<div class="result-error"><p>Error loading holders. Status: ' + xhr.status + '. Please try again.</p></div>';
                        }
                    }
                };
                var data = 'mintAddress=' + encodeURIComponent(mint) + '&page=' + encodeURIComponent(page);
                console.log('Pagination AJAX data:', data);
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
