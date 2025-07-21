<?php
// ============================================================================
// File: token-burn/index.php
// Description: Web interface to check how much a Solana token has been burned.
// Created by: Vina Network
// ============================================================================

define('VINANETWORK_ENTRY', true);
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Check Token Burn | Vina Network</title>
  <link rel="stylesheet" href="token-burn.css" />
</head>
<body>
  <div class="container">
    <h1>🔥 Check Token Burn</h1>
    <form id="burnForm">
      <input type="text" id="tokenAddress" placeholder="Enter token mint address..." required />
      <button type="submit">Check</button>
    </form>
    <div id="loading" style="display: none;">Fetching burn data from Helius...</div>
    <div id="result"></div>
  </div>

  <script>
    document.getElementById("burnForm").addEventListener("submit", function(e) {
      e.preventDefault();
      const address = document.getElementById("tokenAddress").value.trim();
      const loading = document.getElementById("loading");
      const result = document.getElementById("result");

      result.innerHTML = "";
      loading.style.display = "block";

      fetch("token-burn-api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ address: address })
      })
      .then(res => res.json())
      .then(data => {
        loading.style.display = "none";
        if (data.error) {
          result.innerHTML = `<div class="error">❌ ${data.error}</div>`;
        } else {
          result.innerHTML = `
            <div class="output">
              <p><strong>Total Burned:</strong> ${data.total_burned}</p>
              <p><strong>Transfers to Burn Wallet:</strong> ${data.to_burn_wallet}</p>
              <p><strong>Explicit Burn:</strong> ${data.explicit_burn}</p>
            </div>
          `;
        }
      })
      .catch(() => {
        loading.style.display = "none";
        result.innerHTML = `<div class="error">❌ Failed to fetch burn data from Helius.</div>`;
      });
    });
  </script>
</body>
</html>
