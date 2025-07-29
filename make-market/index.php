<!DOCTYPE html>
<html lang="en">
<?php
// ============================================================================
// File: make-market/index.php
// Description: Make Market page for automated token trading on Solana using Jupiter API
// Created by: Vina Network
// ============================================================================

$defaultSlippage = 0.5; // Đồng bộ với giá trị trong form
$root_path = '../';
$page_title = "Make Market - Automated Solana Token Trading | Vina Network";
$page_description = "Automate token trading on Solana with Vina Network's Make Market tool using Jupiter API. Secure, fast, and customizable.";
$page_keywords = "Solana trading, automated token trading, Jupiter API, make market, Vina Network, Solana tokens, crypto trading";
$page_og_title = "Make Market: Automate Your Solana Token Trades with Vina Network";
$page_og_description = "Use Vina Network's Make Market to automate buying and selling Solana tokens with Jupiter API. Try it now!";
$page_og_url = "https://www.vina.network/make-market/";
$page_canonical = "https://www.vina.network/make-market/";
$page_css = ['mm.css'];

// Kiểm tra và include header
if (file_exists('../include/header.php')) {
    include '../include/header.php';
} else {
    die('Error: header.php not found');
}
?>

<body>
  <!-- Navigation Bar -->
  <?php
  if (file_exists('../include/navbar.php')) {
      include '../include/navbar.php';
  } else {
      die('Error: navbar.php not found');
  }
  ?>

  <div class="mm-container">
    <h1>🟢 Make Market</h1>
    <p style="color: red;">⚠️ Cảnh báo: Nhập private key có rủi ro bảo mật. Hãy đảm bảo bạn hiểu rõ trước khi sử dụng!</p>
    
    <!-- Form Make Market -->
    <form id="makeMarketForm" autocomplete="off">
        <label for="processName">Tên tiến trình:</label>
        <input type="text" name="processName" id="processName" required>
        
        <label>🔑 Private Key (Base58):</label>
        <textarea name="privateKey" required placeholder="Nhập private key..."></textarea>

        <label>🎯 Token Address:</label>
        <input type="text" name="tokenMint" required placeholder="VD: So111... hoặc bất kỳ SPL token nào">

        <label>💰 Số lượng SOL muốn mua:</label>
        <input type="number" step="0.01" name="solAmount" required placeholder="VD: 0.1">

        <label>📉 Slippage (%):</label>
        <input type="number" name="slippage" step="0.1" value="<?php echo $defaultSlippage; ?>">

        <label>⏱️ Delay giữa mua và bán (giây):</label>
        <input type="number" name="delay" value="0" min="0">

        <label>🔁 Số vòng lặp:</label>
        <input type="number" name="loopCount" min="1" value="1">

        <button type="submit">🚀 Make Market</button>
    </form>

    <div id="mm-result" class="status-box"></div>
  </div>

  <!-- Footer Section -->
  <?php
  if (file_exists('../include/footer.php')) {
      include '../include/footer.php';
  } else {
      die('Error: footer.php not found');
  }
  ?>
  <!-- Scripts -->
  <script src="../js/vina.js"></script>
  <script src="../js/navbar.js"></script>
  <script type="module" src="mm.js"></script>
</body>
</html>
