<!DOCTYPE html>
<html lang="en">
<?php
// Cấu hình log lỗi
ini_set('log_errors', 1);
ini_set('error_log', '/home/hthxhyqf/domains/vina.network/public_html/tools/error_log.txt');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Định nghĩa biến trước khi sử dụng
$root_path = '../';
$page_title = "Vina Network - Tools";
$page_description = "Explore various tools on Vina Network, including NFT Holders Checker and NFT Valuation.";
$page_keywords = "Vina Network, Solana NFT, NFT holders, NFT valuation, blockchain, VINA";
$page_og_title = "Vina Network - Tools";
$page_og_url = "https://vina.network/tools/";
$page_canonical = "https://vina.network/tools/";
$page_css = ['tools.css'];

// Kiểm tra và include header.php
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    error_log("Error: header.php not found at $header_path");
    die('Internal Server Error: Missing header.php');
}
include $header_path;

// Xác định chức năng được chọn (mặc định là nft-holders)
$tool = isset($_GET['tool']) ? $_GET['tool'] : 'nft-holders';
if (!in_array($tool, ['nft-holders', 'nft-valuation'])) {
    $tool = 'nft-holders'; // Nếu tool không hợp lệ, mặc định là nft-holders
}
?>

<body>
<!-- Include Navbar -->
<?php 
$navbar_path = $root_path . 'include/navbar.php';
if (!file_exists($navbar_path)) {
    error_log("Error: navbar.php not found at $navbar_path");
    die('Internal Server Error: Missing navbar.php');
}
include $navbar_path;
?>

<section class="tools-section">
    <div class="tools-content fade-in">
        <h1>Vina Network Tools</h1>
        <p>Select a tool to explore its features.</p>

        <!-- Tab để chọn chức năng -->
        <div class="tools-tabs">
            <a href="?tool=nft-holders" class="tab-link <?php echo $tool === 'nft-holders' ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i> NFT Holders
            </a>
            <a href="?tool=nft-valuation" class="tab-link <?php echo $tool === 'nft-valuation' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> NFT Valuation
            </a>
        </div>

        <!-- Nội dung chức năng -->
        <div class="tool-content">
            <?php
            // Include file tương ứng với chức năng được chọn
            if ($tool === 'nft-holders') {
                $tool_file = 'nft-holders.php';
            } elseif ($tool === 'nft-valuation') {
                $tool_file = 'nft-valuation.php';
            }

            // Kiểm tra và include file
            if (isset($tool_file) && file_exists($tool_file)) {
                include $tool_file;
            } else {
                echo "<p>Error: Tool not found.</p>";
            }
            ?>
        </div>
    </div>
</section>

<!-- Include Footer -->
<?php 
$footer_path = $root_path . 'include/footer.php';
if (!file_exists($footer_path)) {
    error_log("Error: footer.php not found at $footer_path");
    die('Internal Server Error: Missing footer.php');
}
include $footer_path;
?>

<script src="../js/vina.js"></script>
</body>
</html>
