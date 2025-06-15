<?php
/*
 * tools/index.php - Main Interface Loader for NFT Holder Tools
 *
 * This file serves as the main entry point for the NFT Tools section on the Vina Network website.
 * It provides the following functionalities:
 * - Initializes constants, configurations, and SEO metadata
 * - Displays the main HTML layout with tool navigation tabs (Holders, Valuation, Transactions, Wallet Analysis)
 * - Dynamically loads the correct tool module based on URL parameters (via PHP and AJAX)
 * - Provides basic error logging and fallback for missing modules
 * - Includes header, navbar, and footer templates
 * - Injects structured data for SEO (WebApplication schema)
 */

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
$page_title = "Vina Network - Solana NFT Tools & Holders Checker";
$page_description = "Discover Solana NFT tools on Vina Network: Check Holders, Valuation, Transactions & Wallet Analysis. Try now!";
$page_keywords = "Vina Network, Solana NFT, check Solana NFT holders, NFT valuation, blockchain, NFT";
$page_og_title = "Vina Network - Solana NFT Tools & Holders Checker";
$page_og_description = "Discover Solana NFT tools on Vina Network: Check Holders, Valuation, Transactions & Wallet Analysis. Try now!";
$page_og_image = "https://vina.network/tools/image/tools-og-image.jpg";
$page_og_url = "https://vina.network/tools/";
$page_canonical = "https://vina.network/tools/" . (isset($_GET['tool']) && $_GET['tool'] !== 'nft-holders' ? $_GET['tool'] . '/' : '');
$page_css = ['../css/vina.css', 'tools.css'];

// Get the requested tool from query string
$tool = isset($_GET['tool']) ? $_GET['tool'] : 'nft-holders';

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

    <section class="t-1">
        <div class="t-2">
            <!-- Page Title -->
            <h1>Vina Network Tools</h1>

            <!-- Tool Navigation Menu -->
            <div class="t-3">
                <!-- Tool Tab: NFT Holders -->
                <a href="?tool=nft-holders" class="t-link <?php echo $tool === 'nft-holders' ? 'active' : ''; ?>" data-tool="nft-holders">
                    <i class="fas fa-wallet"></i> NFT Holders
                </a>
                <!-- Tool Tab: NFT Valuation -->
                <a href="?tool=nft-valuation" class="t-link <?php echo $tool === 'nft-valuation' ? 'active' : ''; ?>" data-tool="nft-valuation">
                    <i class="fas fa-chart-line"></i> NFT Valuation
                </a>
                <!-- Tool Tab: NFT Transactions -->
                <a href="?tool=nft-transactions" class="t-link <?php echo $tool === 'nft-transactions' ? 'active' : ''; ?>" data-tool="nft-transactions">
                    <i class="fas fa-history"></i> NFT Transactions
                </a>
                <!-- Tool Tab: Wallet Analysis -->
                <a href="?tool=wallet-analysis" class="t-link <?php echo $tool === 'wallet-analysis' ? 'active' : ''; ?>" data-tool="wallet-analysis">
                    <i class="fas fa-user"></i> Wallet Analysis
                </a>
            </div>

            <!-- General Notice -->
            <p class="note">Note: Only supports checking on the Solana blockchain.</p>

            <!-- Tool Content Loader -->
            <div class="t-4">
                <?php
                    log_message("index: tool = $tool", 'tools_log.txt');

                    // Validate selected tool, fallback to default
                    if (!in_array($tool, ['nft-holders', 'nft-valuation', 'nft-transactions', 'wallet-analysis'])) {
                        $tool = 'nft-holders';
                        log_message("index: Invalid tool, defaulted to nft-holders", 'tools_log.txt', 'ERROR');
                    }

                    // Define the file path for the selected tool
                    if ($tool === 'nft-holders') {
                        $tool_file = __DIR__ . '/nft-holders/nft-holders.php';
                    } elseif ($tool === 'nft-valuation') {
                        $tool_file = __DIR__ . '/nft-valuation/nft-valuation.php';
                    } elseif ($tool === 'nft-transactions') {
                        $tool_file = __DIR__ . '/nft-transactions/nft-transactions.php';
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

    <!-- Structured Data for SEO: WebApplication -->
    <script type="application/ld+json"> {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Vina Network Tools",
        "operatingSystem": "All",
        "applicationCategory": "http://schema.org/FinanceApplication",
        "description": "Discover Solana NFT tools on Vina Network: Check Holders, Valuation, Transactions & Wallet Analysis.",
        "url": "https://vina.network/tools/",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        }
    }
    </script>

    <!-- Load JavaScript files with timestamp and error fallback -->
    <script>console.log('Attempting to load JS files...');</script>
    <script src="../js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load js/vina.js')"></script>
    <script src="../js/navbar.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load js/navbar.js')"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.min.js" integrity="sha512-L0Shl7nXXzIlBSUUPpxrokqq4ojqgZFQczTYlGjzONGTDAcLremjwaWv5A+EDLnxhQzY5xUZPWLOLqYRkY0Cbw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="tools.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load tools/tools.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
