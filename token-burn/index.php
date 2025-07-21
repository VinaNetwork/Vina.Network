<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token Burn Checker</title>
    <link rel="stylesheet" href="token-burn.css">
</head>
<body>
    <div class="container">
        <h1>Token Burn Checker</h1>
        <form id="burnForm" method="POST" action="token-burn-api.php">
            <label for="mintAddress">Enter Token Mint Address:</label>
            <input type="text" id="mintAddress" name="mintAddress" placeholder="e.g., DsfCsbbPH77p6yeLS1i4ag9UA5gP9xWSvdCx72FJjLsx" required>
            <button type="submit">Check Burned Tokens</button>
        </form>
        <div id="result"></div>
    </div>

    <script>
        document.getElementById('burnForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const mintAddress = document.getElementById('mintAddress').value;
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = 'Loading...';

            try {
                const response = await fetch('token-burn-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `mintAddress=${encodeURIComponent(mintAddress)}`
                });
                const data = await response.json();
                if (data.error) {
                    resultDiv.innerHTML = `<p class="error">${data.error}</p>`;
                } else {
                    resultDiv.innerHTML = `<p>Total Burned Tokens: ${data.totalBurned}</p>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<p class="error">Error: ${error.message}</p>`;
            }
        });
    </script>
</body>
</html>
