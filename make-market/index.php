<?php
// Make Market Tool – Vina Network
// Giao diện cho phép mua và bán token Solana tự động

$defaultSlippage = 1.0;
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Make Market | Vina Network</title>
  <link rel="stylesheet" href="mm.css">
</head>
<body>
  <div class="mm-container">
    <h1>🟢 Make Market</h1>
    <form id="makeMarketForm">
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

      <button type="submit">🚀 Make Market</button>
    </form>

    <div id="mm-status"></div>
  </div>

  <script src="mm.js"></script>
</body>
</html>
