<?php
// ============================================================================
// File: token-burn/index.php
// Description: Tool to check how many tokens have been burned for a given Solana token.
// Created by: Vina Network
// ============================================================================
define('VINANETWORK_ENTRY', true);
require_once __DIR__ . '/../include/header.php';
?>

<div class="tool-container">
  <h2 class="tool-title">🔍 Check Token Burn (Solana)</h2>

  <form id="burnForm" class="tool-form">
    <label for="mint">Token Mint Address:</label>
    <input type="text" id="mint" name="mint" placeholder="Enter token mint address..." required />
    <button type="submit">Check Burn</button>
  </form>

  <div id="result" class="tool-result" style="display: none;"></div>
  <div id="loading" style="display: none;">⏳ Checking transactions, please wait...</div>
</div>

<script>
document.getElementById('burnForm').addEventListener('submit', function (e) {
  e.preventDefault();

  const mint = document.getElementById('mint').value.trim();
  const resultDiv = document.getElementById('result');
  const loadingDiv = document.getElementById('loading');

  resultDiv.style.display = 'none';
  loadingDiv.style.display = 'block';
  resultDiv.innerHTML = '';

  fetch('token-burn-api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'mint=' + encodeURIComponent(mint)
  })
  .then(res => res.json())
  .then(data => {
    loadingDiv.style.display = 'none';

    if (data.error) {
      resultDiv.innerHTML = `<div class="error">❌ ${data.error}</div>`;
    } else {
      let html = `
        <h3>🔥 Token Burn Result</h3>
        <p><strong>Mint:</strong> ${data.mint}</p>
        <p><strong>Total Burned:</strong> ${data.total_burned}</p>
        <p>• Sent to Burn Wallet (111...): ${data.to_burn_wallet}</p>
        <p>• Explicit Burn: ${data.explicit_burn}</p>
        <p><strong>Burn Transactions:</strong> ${data.burn_transactions.length}</p>
      `;

      if (data.burn_transactions.length > 0) {
        html += '<ul>';
        data.burn_transactions.forEach(sig => {
          html += `<li><a href="https://solscan.io/tx/${sig}" target="_blank" rel="noopener">${sig}</a></li>`;
        });
        html += '</ul>';
      }

      resultDiv.innerHTML = html;
    }

    resultDiv.style.display = 'block';
  })
  .catch(err => {
    loadingDiv.style.display = 'none';
    resultDiv.innerHTML = `<div class="error">❌ Error: ${err.message}</div>`;
    resultDiv.style.display = 'block';
  });
});
</script>

<?php require_once __DIR__ . '/../include/footer.php'; ?>
