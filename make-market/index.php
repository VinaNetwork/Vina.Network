<!DOCTYPE html>
<html lang="en">
<?php
// ============================================================================
// File: make-market/index.php
// Description:
// Created by: Vina Network
// ============================================================================

// Head Section (Meta, Styles, Title) is included via header.php
$defaultSlippage = 1.0;
$root_path = '../';
$page_title = "";
$page_description = "";
$page_keywords = "";
$page_og_title = "";
$page_og_description = "";
$page_og_url = "https://www.vina.network/make-market/";
$page_canonical = "https://www.vina.network/make-market/";
$page_css = ['mm.css'];

include '../include/header.php';
?>


<body>
  <!-- Navigation Bar -->
  <?php include '../include/navbar.php'; ?>

  <div class="mm-container">
    <h1>🟢 Make Market</h1>
    <p style="color: red;">⚠️ Cảnh báo: Nhập private key có rủi ro bảo mật. Hãy đảm bảo bạn hiểu rõ trước khi sử dụng!</p>
    <form id="makeMarketForm">
      <label for="processName">Tên tiến trình:</label>
      <input type="text" name="processName" id="processName" required>
      
      <label>🔑 Private Key (Base58):</label>
      <textarea name="privateKey" required placeholder="Nhập private key..."></textarea>

      <label>🎯 Token Address:</label>
      <input type="text" name="tokenMint" required placeholder="VD: So111... hoặc bất kỳ SPL token nào">

      <label>💰 Số lượng SOL muốn mua:</label>
      <input type="number" step="0.01" name="solAmount" required placeholder="VD: 0.1">

      <label>📉 Slippage (%):</label>
      <input type="number" name="slippage" step="0.1" value="<?= $defaultSlippage ?>">

      <label>⏱️ Delay giữa mua và bán (giây):</label>
      <input type="number" name="delay" value="0" min="0">

      <label>🔁 Số vòng lặp:</label>
      <input type="number" name="loopCount" min="1" value="1">

      <button type="submit">🚀 Make Market</button>
    </form>

    <div id="mm-status" class="status-box"></div>
  </div>

  <!-- Footer Section -->
  <?php include '../include/footer.php'; ?>
  <!-- Scripts -->
  <script src="../js/vina.js"></script>
  <script src="../js/navbar.js"></script>
  <script src="mm.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
</body>
</html>
