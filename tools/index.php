<?php
// ============================================================================
// File: tools/index.php
// Description: Discover Solana NFT tools on Vina Network: Check NFT Info, Check NFT Holders, Check Wallet Creators, Check Wallet Analysis.
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../';
require_once $root_path . 'tools/bootstrap.php';

// Function to extract title and description from a PHP file
function getToolInfo($file_path) {
    if (!file_exists($file_path)) {
        log_message("getToolInfo: File not found at $file_path", 'tools.log', 'tools', 'ERROR');
        return ['title' => 'N/A', 'description' => 'File not found'];
    }
    $content = file_get_contents($file_path);
    $title = 'N/A';
    $description = 'No description available';

    // Extract <h2> from .tools-form
    if (preg_match('/<div class="tools-form">.*?<h2>(.*?)<\/h2>/is', $content, $title_match)) {
        $title = strip_tags($title_match[1]);
    }

    // Extract first <p> from .tools-form
    if (preg_match('/<div class="tools-form">.*?<p>(.*?)<\/p>/is', $content, $desc_match)) {
        $description = strip_tags($desc_match[1]);
    }

    return ['title' => $title, 'description' => $description];
}

// Define tools and their corresponding files
$tools = [
    'nft-info' => [
        'file' => __DIR__ . '/nft-info/nft-info.php',
        'icon' => 'fa-solid fa-circle-info'
    ],
    'nft-holders' => [
        'file' => __DIR__ . '/nft-holders/nft-holders.php',
        'icon' => 'fas fa-user'
    ],
    'nft-transactions' => [
        'file' => __DIR__ . '/nft-transactions/nft-transactions.php',
        'icon' => 'fas fa-clock-rotate-left'
    ],
    'wallet-creators' => [
        'file' => __DIR__ . '/wallet-creators/wallet-creators.php',
        'icon' => 'fa-solid fa-paint-brush'
    ],
    'wallet-analysis' => [
        'file' => __DIR__ . '/wallet-analysis/wallet-analysis.php',
        'icon' => 'fas fa-wallet'
    ]
];

// Get the requested tool from query string
$tool = isset($_GET['tool']) && array_key_exists($_GET['tool'], $tools) ? $_GET['tool'] : null;

// Set SEO meta variables
$page_title = "Vina Network - Solana NFT Tools & Solana Checker";
$page_description = "Discover Solana NFT tools on Vina Network: Check NFT Info, Check NFT Holders & Wallet Analysis. Try now!";
$page_keywords = "Vina Network, Solana NFT, check Solana NFT holders, NFT Info, Wallet Analysis, blockchain, NFT";
$page_og_title = "Vina Network - Solana NFT Tools & Solana Checker";
$page_og_description = "Discover Solana NFT tools on Vina Network: Check NFT Info, Check NFT Holders & Wallet Analysis. Try now!";
$page_og_url = BASE_URL . "tools/";
$page_canonical = BASE_URL . "tools/";
$page_css = ['/tools/tools.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php require_once $root_path . 'include/header.php';?>
<body>
<?php require_once $root_path . 'include/navbar.php';?>
<div class="tools-container">
    <h1>Vina Network Tools</h1>
    <!-- Tool Navigation Menu -->
    <div class="tools-nav" style="<?php echo $tool ? 'display: none;' : ''; ?>">
        <?php foreach ($tools as $tool_key => $tool_data): ?>
            <?php
            $tool_info = getToolInfo($tool_data['file']);
            $is_active = $tool_key === $tool ? 'active' : '';
            ?>
            <div class="tools-nav-card <?php echo $is_active; ?>" data-tool="<?php echo htmlspecialchars($tool_key); ?>">
                <i class="<?php echo htmlspecialchars($tool_data['icon']); ?>"></i>
                <h2><?php echo htmlspecialchars($tool_info['title']); ?></h2>
                <p><?php echo htmlspecialchars($tool_info['description']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <!-- General Notice -->
    <p class="note" style="<?php echo $tool ? 'display: none;' : ''; ?>">Note: Only supports checking on the Solana blockchain.</p>

    <!-- Tool Content Loader -->
    <div class="tools-item" style="<?php echo !$tool ? 'display: none;' : ''; ?>">
        <div class="tools-back">
            <button class="back-button"><i class="fa-solid fa-arrow-left"></i> Back to Tools</button>
        </div>
        <!-- Content will be loaded via AJAX by tools.js -->
    </div>
</div>
<?php require_once $root_path . 'include/footer.php';?>

<!-- Load JavaScript files with timestamp and error fallback -->
<script>console.log('Attempting to load JS files...');</script>
<script src="../js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load js/vina.js')"></script>
<script src="tools.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load tools/tools.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
