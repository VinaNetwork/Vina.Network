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
  <div id="loading" class="loading hidden">Checking burn data...</div>
</div>

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
            <p><strong>Total Burned:</strong> ${data.totalBurned.toLocaleString()} ${data.symbol || ''}</p>
          </div>
        `;
      }
    } catch (e) {
      loading.classList.add('hidden');
      resultBox.innerHTML = '<p class="error">Failed to fetch data.</p>';
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
