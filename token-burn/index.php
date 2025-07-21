<?php
// ============================================================================
// File: token-burn/index.php
// Description: Token Burn Checker - Form giao diện nhập địa chỉ Mint để kiểm tra
// Tác giả: Vina Network
// ============================================================================
define('VINANETWORK_ENTRY', true);
require_once(__DIR__ . '/../config/config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Token Burn Checker | Vina Network</title>
    <link rel="stylesheet" href="token-burn.css">
</head>
<body>
<div class="container">
    <h1>Check Token Burn (Solana)</h1>
    <form id="burnForm">
        <label for="mint">Token Mint Address:</label>
        <input type="text" id="mint" name="mint" required placeholder="Enter Solana Mint Address...">
        <button type="submit">Check Burn</button>
    </form>

    <div id="result" class="result"></div>
</div>

<script>
document.getElementById('burnForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const mint = document.getElementById('mint').value.trim();
    const resultDiv = document.getElementById('result');
    resultDiv.innerHTML = 'Checking burn data...';

    try {
        const res = await fetch('token-burn-api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({mint})
        });
        const data = await res.json();

        if (data.success) {
            resultDiv.innerHTML = `
                <p><strong>Total Burned:</strong> ${data.totalBurned} ${data.symbol || ''}</p>
                <p><strong>Burn Transactions:</strong> ${data.txCount}</p>
            `;
        } else {
            resultDiv.innerHTML = `<span class="error">Error: ${data.message}</span>`;
        }
    } catch (err) {
        resultDiv.innerHTML = '<span class="error">Failed to fetch burn data.</span>';
    }
});
</script>
</body>
</html>
