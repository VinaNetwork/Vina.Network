<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Solana Login</title>
</head>
<body>
  <h2>Connect Wallet</h2>
  <button id="connectBtn">Connect Wallet</button>
  <p id="status"></p>

  <script>
    document.getElementById('connectBtn').onclick = async () => {
      if (!window.solana || !window.solana.isPhantom) {
        alert("Please install Phantom Wallet.");
        return;
      }

      try {
        const resp = await window.solana.connect();
        const wallet = resp.publicKey.toString();
        document.getElementById("status").innerText = "Wallet connected: " + wallet;

        // Message to sign
        const message = `Login to Vina Network\nTime: ${new Date().toISOString()}`;
        const encodedMessage = new TextEncoder().encode(message);

        // Sign
        const signed = await window.solana.signMessage(encodedMessage, 'utf8');
        const signatureBase64 = btoa(String.fromCharCode.apply(null, signed.signature));

        // Send to server
        const formData = new FormData();
        formData.append('wallet', wallet);
        formData.append('message', message);
        formData.append('signature', signatureBase64);

        const response = await fetch('login.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.text();

        if (result === 'ok') {
          alert("Login successful!");
        } else {
          alert("Login failed: " + result);
        }

      } catch (err) {
        console.error(err);
        alert("Error: " + err.message);
      }
    };
  </script>
</body>
</html>
