<?php
// ============================================================================
// File: tools/token-burn/index.php
// Description: Giao diện kiểm tra tổng số token đã bị đốt (qua API nội bộ gọi Helius)
// Created by: Vina Network
// ============================================================================
require_once('../../include/header.php');
?>

<div class="tool-container">
  <h1 class="tool-title">Check Token Burned</h1>
  <p class="tool-desc">Enter a Solana token address to calculate the total number of tokens that have been burned.</p>

  <div class="form-group">
    <input type="text" id="tokenAddress" placeholder="Enter token mint address..." />
    <button onclick="checkTokenBurn()">Check Burn</button>
  </div>

  <div id="result"></div>
  <div id="loading" class="loading hidden">Checking burn data...</div>
</div>

<link rel="stylesheet" href="token-burn.css" />
<script>
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

    try {
      const res = await fetch('/tools/tools-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'getTokenBurned',
          address: address
        })
      });

      const data = await res.json();
      loading.classList.add('hidden');

      if (data.error) {
        resultBox.innerHTML = `<p class="error">Error: ${data.error}</p>`;
      } else {
        resultBox.innerHTML = `
          <div class="burn-result">
            <p><strong>Total Burned:</strong> ${data.totalBurned.toLocaleString()} ${data.symbol ?? ''}</p>
          </div>
        `;
      }
    } catch (e) {
      loading.classList.add('hidden');
      console.error(e);
      resultBox.innerHTML = '<p class="error">Failed to fetch data from server.</p>';
    }
  }
</script>

<?php require_once('../../include/footer.php'); ?>
