<!DOCTYPE html>
<html lang="en">
<?php
// ============================================================================
// File: tools/token-burn/index.php
// Description: Check Token Burn
// Created by: Vina Network
// ============================================================================

// Head Section (Meta, Styles, Title) is included via header.php
$root_path = '../';
$page_title = "Check Token Burn - Vina Network";
$page_description = "";
$page_keywords = "Vina Network, Web3, cryptocurrency";
$page_og_title = "Check Token Burn - Vina Network";
$page_og_description = "Check Token Burn";
$page_og_url = "https://www.vina.network/tools/token-burn/";
$page_canonical = "https://www.vina.network/tools/token-burn/";
$page_css = ['token-burn.css'];

include '../../include/header.php';
?>
<body>
<!-- Navigation Bar -->
<?php include '../../include/navbar.php'; ?>

<div class="tool-container">
  <h1 class="tool-title">Check Token Burned</h1>
  <p class="tool-desc">Enter a Solana token address to calculate the total number of tokens that have been burned.</p>

  <div class="form-group">
    <input type="text" id="tokenAddress" placeholder="Enter token address..." />
    <button onclick="checkTokenBurn()">Check Burn</button>
  </div>

  <div id="result"></div>
  <div id="loading" class="loading hidden">Fetching burn data from Helius...</div>
</div>

<script>
  const HELIUS_API_KEY = "8eb75cd9-015a-4e24-9de2-5be9ee0f1c63"; // <-- Thay bằng API key của bạn

  async function checkTokenBurn() {
    const address = document.getElementById('tokenAddress').value.trim();
    const resultBox = document.getElementById('result');
    const loading = document.getElementById('loading');
    resultBox.innerHTML = '';
    if (!address) {
      resultBox.innerHTML = '<p class="error">Please enter a token address.</p>';
      return;
    }

    loading.classList.remove('hidden');

    let page = 1;
    let totalBurned = 0;
    let limit = 1000;
    let before = null;
    try {
      while (true) {
        const url = new URL(`https://mainnet.helius.xyz/v0/addresses/11111111111111111111111111111111/transactions?api-key=${HELIUS_API_KEY}`);
        url.searchParams.set("limit", limit);
        if (before) url.searchParams.set("before", before);

        const res = await fetch(url);
        const txns = await res.json();

        if (!Array.isArray(txns) || txns.length === 0) break;

        for (const tx of txns) {
          if (!tx.tokenTransfers || tx.tokenTransfers.length === 0) continue;

          for (const transfer of tx.tokenTransfers) {
            if (transfer.mint === address) {
              if (transfer.toUserAccount === "11111111111111111111111111111111") {
                totalBurned += parseFloat(transfer.tokenAmount);
              } else if (!transfer.toUserAccount && parseFloat(transfer.tokenAmount) < 0) {
                // Likely a burn without a recipient
                totalBurned += Math.abs(parseFloat(transfer.tokenAmount));
              }
            }
          }
        }

        if (txns.length < limit) break;
        before = txns[txns.length - 1].signature;
        page++;
      }

      loading.classList.add('hidden');
      resultBox.innerHTML = `
        <div class="burn-result">
          <p><strong>Total Burned:</strong> ${totalBurned.toLocaleString()} tokens</p>
        </div>
      `;
    } catch (e) {
      loading.classList.add('hidden');
      console.error(e);
      resultBox.innerHTML = '<p class="error">Failed to fetch burn data from Helius.</p>';
    }
  }
</script>

<?php include __DIR__ . '../../include/footer.php'; ?>

<!-- Scripts -->
<script src="../../js/vina.js"></script>
<script src="../../js/navbar.js"></script>
<script src="token-burn.js"></script>
</body>
</html>
