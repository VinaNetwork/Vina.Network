<!DOCTYPE html>
<html lang="en">
<?php
$root_path = '../';
$page_title = "Vina Network - Tools";
$page_description = "Explore various tools on Vina Network, including NFT Holders Checker, NFT Valuation, and NFT Transaction History.";
$page_keywords = "Vina Network, Solana NFT, NFT holders, NFT valuation, NFT transactions, blockchain, VINA";
$page_og_title = "Vina Network - Tools";
$page_og_url = "https://vina.network/tools/";
$page_canonical = "https://vina.network/tools/"; // Đã sửa từ $page.canonical
$page_css = ['tools.css'];

// Kiểm tra và include header.php
$header_path = $root_path . 'include/header.php';
if (!file_exists($header_path)) {
    error_log("Error: header.php not found at $header_path");
    die('Internal Server Error: Missing header.php');
}
include $header_path;
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
            <a href="?tool=nft-holders" class="tab-link <?php echo isset($_GET['tool']) && $_GET['tool'] === 'nft-holders' ? 'active' : ''; ?>" data-tool="nft-holders">
                <i class="fas fa-wallet"></i> NFT Holders
            </a>
            <a href="?tool=nft-valuation" class="tab-link <?php echo isset($_GET['tool']) && $_GET['tool'] === 'nft-valuation' ? 'active' : ''; ?>" data-tool="nft-valuation">
                <i class="fas fa-chart-line"></i> NFT Valuation
            </a>
            <a href="?tool=nft-transactions" class="tab-link <?php echo isset($_GET['tool']) && $_GET['tool'] === 'nft-transactions' ? 'active' : ''; ?>" data-tool="nft-transactions">
                <i class="fas fa-history"></i> NFT Transactions
            </a>
        </div>

        <!-- Nội dung chức năng (ban đầu trống, sẽ tải qua AJAX) -->
        <div class="tool-content" id="tool-content">
            <!-- Nội dung sẽ được tải qua AJAX -->
        </div>

        <!-- Thông báo -->
        <p class="note">Note: Only supports checking on the Solana blockchain.</p>
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
<script>
// Xử lý chuyển tab bằng AJAX
document.addEventListener('DOMContentLoaded', () => {
    const toolContent = document.getElementById('tool-content');
    const defaultTool = '<?php echo isset($_GET['tool']) ? $_GET['tool'] : 'nft-holders'; ?>';

    function loadTool(tool) {
        console.log('Loading tool:', tool); // Debug log
        fetch(`/tools/load-tool.php?tool=${tool}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('Received data:', data); // Debug log
            if (data.error) {
                toolContent.innerHTML = `<p>${data.error}</p>`;
            } else {
                toolContent.innerHTML = data.html; // Render HTML
            }
        })
        .catch(error => {
            console.error('Error loading tool content:', error);
            toolContent.innerHTML = '<p>Error loading content. Please try again.</p>';
        });
    }

    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.tab-link').forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');

            const tool = this.getAttribute('data-tool');
            history.pushState({}, '', `?tool=${tool}`);
            loadTool(tool);
        });
    });

    // Tải tool mặc định khi trang load
    loadTool(defaultTool);

    // Xử lý form submit bằng AJAX
    document.addEventListener('submit', (e) => {
        if (e.target.matches('#nftHoldersForm, #nftValuationForm, .transaction-form')) {
            e.preventDefault();
            console.log('Form submitted:', e.target); // Debug log

            const formData = new FormData(e.target);
            const tool = document.querySelector('.tab-link.active').getAttribute('data-tool');

            fetch(`/tools/load-tool.php?tool=${tool}`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Form response:', data); // Debug log
                if (data.error) {
                    toolContent.innerHTML = `<p>${data.error}</p>`;
                } else {
                    toolContent.innerHTML = data.html;
                }
            })
            .catch(error => {
                console.error('Error submitting form:', error);
                toolContent.innerHTML = '<p>Error submitting form. Please try again.</p>';
            });
        }
    });
});
</script>
</body>
</html>
