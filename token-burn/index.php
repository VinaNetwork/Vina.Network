<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Token Burn Checker</title>
</head>
<body>
    <h2>Check Burned Tokens</h2>
    <form id="burnForm">
        <input type="text" id="mint" placeholder="Enter Token Mint Address" size="50" required>
        <button type="submit">Check</button>
    </form>
    <div id="result"></div>

    <script>
    document.getElementById('burnForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const address = document.getElementById('mint').value;
        document.getElementById('result').innerText = 'Checking...';

        const res = await fetch('token-burn-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ address })
        });

        const data = await res.json();

        if (data.error) {
            document.getElementById('result').innerText = 'Error: ' + data.error;
        } else {
            document.getElementById('result').innerHTML = `
                <p><strong>Total Burned:</strong> ${data.total_burned}</p>
                <p><strong>Sent to Burn Wallet:</strong> ${data.to_burn_wallet}</p>
                <p><strong>Explicit Burn:</strong> ${data.explicit_burn}</p>
                <p><strong>Burn Transactions:</strong><br>${data.burn_transactions.map(tx => `<code>${tx}</code>`).join('<br>')}</p>
            `;
        }
    });
    </script>
</body>
</html>
