<?php
// ============================================================================
// tools/index.php - Main Interface Loader for NFT Holder Tools
// Description: Discover Solana NFT tools on Vina Network: Check Holders, Valuation, Transactions & Wallet Analysis.
// Created by: Vina Network
// ============================================================================

// Start output buffering and define constants for context
ob_start();
define('VINANETWORK', true);
define('VINANETWORK_ENTRY', true);
require_once 'bootstrap.php';

// Set error reporting and logging
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set SEO meta variables
$root_path = '../';
$page_title = "Vina Network - Solana NFT Tools & Solana Checker";
$page_description = "Discover Solana NFT tools on Vina Network: Check NFT Info, Check NFT Holders & Wallet Analysis. Try now!";
$page_keywords = "Vina Network, Solana NFT, check Solana NFT holders, NFT Info, Wallet Analysis, blockchain, NFT";
$page_og_title = "Vina Network - Solana NFT Tools & Solana Checker";
$page_og_description = "Discover Solana NFT tools on Vina Network: Check NFT Info, Check NFT Holders & Wallet Analysis. Try now!";
$page_og_image = "https://vina.network/tools/image/tools-og-image.jpg";
$page_og_url = "https://vina.network/tools/";
$page_canonical = "https://vina.network/tools/" . (isset($_GET['tool']) && $_GET['tool'] !== 'nft-info' ? $_GET['tool'] . '/' : '');
$page_css = ['../css/vina.css', 'tools.css'];

// Get the requested tool from query string
$tool = isset($_GET['tool']) ? $_GET['tool'] : 'nft-info';

// Include header
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    log_message("index: header.php not found at $header_path", 'tools_log.txt', 'ERROR');
    die('Internal Server Error: Missing header.php');
}
include $header_path;
?>

<!DOCTYPE html>
<html lang="en">
<body>
<?php 
// Include navigation bar
$navbar_path = $root_path . 'include/navbar.php';
if (!file_exists($navbar_path)) {
    log_message("index: navbar.php not found at $navbar_path", 'tools_log.txt', 'ERROR');
    die('Internal Server Error: Missing navbar.php');
}
include $navbar_path;
?>

<!-- Tools Content -->
<section class="tools">
    <div class="tools-container">
        <h1>Vina Network Tools</h1>
        <!-- Tool Navigation Menu -->
        <div class="tools-nav"> 
            <a href="?tool=nft-info" class="tools-nav-link <?php echo $tool === 'nft-info' ? 'active' : ''; ?>" data-tool="nft-info">
                <i class="fa-solid fa-circle-info"></i> NFT Info
            </a>
            <a href="?tool=nft-holders" class="tools-nav-link <?php echo $tool === 'nft-holders' ? 'active' : ''; ?>" data-tool="nft-holders">
                <i class="fas fa-user"></i> NFT Holders
            </a>
            <a href="?tool=wallet-analysis" class="tools-nav-link <?php echo $tool === 'wallet-analysis' ? 'active' : ''; ?>" data-tool="wallet-analysis">
                <i class="fas fa-wallet"></i>  Wallet Analysis
            </a>
        </div>
        <!-- General Notice -->
        <p class="note">Note: Only supports checking on the Solana blockchain.</p>

        <!-- Tool Content Loader -->
        <div class="tools-content">
            <?php
            log_message("index: tool = $tool", 'tools_log.txt');

            // Validate selected tool, fallback to default
            if (!in_array($tool, ['nft-info', 'nft-holders', 'wallet-analysis'])) {
                $tool = 'nft-info';
                log_message("index: Invalid tool, defaulted to nft-info", 'tools_log.txt', 'ERROR');
            }

            // Define the file path for the selected tool
            if ($tool === 'nft-info') {
                $tool_file = __DIR__ . '/nft-info/nft-info.php';
            } elseif ($tool === 'nft-holders') {
                $tool_file = __DIR__ . '/nft-holders/nft-holders.php';
            } elseif ($tool === 'wallet-analysis') {
                $tool_file = __DIR__ . '/wallet-analysis/wallet-analysis.php';
            }

            // Load the corresponding tool file if exists
            if (isset($tool_file) && file_exists($tool_file)) {
                log_message("index: Including tool file: $tool_file", 'tools_log.txt');
                include $tool_file;
            } else {
                echo "<p>Error: Tool file not found at $tool_file.</p>";
                log_message("index: Tool file not found: $tool_file", 'tools_log.txt', 'ERROR');
            }
            ?>
        </div>
    </div>
</section>

<?php 
// Include footer
$footer_path = __DIR__ . '/../include/footer.php';
log_message("index: Checking footer_path: $footer_path", 'tools_log.txt', 'DEBUG');
if (!file_exists($footer_path)) {
    log_message("index: footer.php not found at $footer_path", 'tools_log.txt', 'ERROR');
    die('Internal Server Error: Missing footer.php');
}
include $footer_path;
?>

<!-- Load JavaScript files with timestamp and error fallback -->
<script>console.log('Attempting to load JS files...');</script>
<script src="../js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load js/vina.js')"></script>
<script src="../js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load js/navbar.js')"></script>
<script src="tools.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load tools/tools.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
