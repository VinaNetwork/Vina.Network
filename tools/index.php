<?php
// ============================================================================
// File: tools/index.php
// Description: Tools page for Vina Network
// Created by: Vina Network
// ============================================================================

// Define constants to mark script entry
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Load bootstrap dependencies
$bootstrap_path = __DIR__ . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Set up page variables and include layout headers
$root_path = '../';
$page_title = 'Tools - Vina Network';
$page_description = 'Various tools for analyzing Solana blockchain data, including NFT information, holder analysis, and wallet analysis.';
$page_css = ['../css/vina.css', 'tools.css'];
include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';

// Function to extract title and description from a PHP file
function getToolInfo($file_path) {
    if (!file_exists($file_path)) {
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
        'icon' => 'fas fa-info-circle'
    ],
    'nft-holders' => [
        'file' => __DIR__ . '/nft-holders/nft-holders.php',
        'icon' => 'fas fa-users'
    ],
    'wallet-analysis' => [
        'file' => __DIR__ . '/wallet-analysis/wallet-analysis.php',
        'icon' => 'fas fa-wallet'
    ]
];

// Get current tool from URL
$current_tool = isset($_GET['tool']) && array_key_exists($_GET['tool'], $tools) ? $_GET['tool'] : 'nft-info';
?>

<div class="tools">
    <div class="tools-container">
        <h1>Tools</h1>
        <div class="tools-nav">
            <?php foreach ($tools as $tool_key => $tool_data): ?>
                <?php
                $tool_info = getToolInfo($tool_data['file']);
                $is_active = $tool_key === $current_tool ? 'active' : '';
                ?>
                <div class="tools-nav-card <?php echo $is_active; ?>" data-tool="<?php echo htmlspecialchars($tool_key); ?>">
                    <i class="<?php echo htmlspecialchars($tool_data['icon']); ?>"></i>
                    <h3><?php echo htmlspecialchars($tool_info['title']); ?></h3>
                    <p><?php echo htmlspecialchars($tool_info['description']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="tools-content">
            <?php
            $tool_file = $tools[$current_tool]['file'];
            if (file_exists($tool_file)) {
                include $tool_file;
            } else {
                echo '<div class="result-error"><p>Tool not found</p></div>';
            }
            ?>
        </div>
    </div>
</div>
<?php include $root_path . 'include/footer.php'; ?>
