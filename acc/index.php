<?php session_start(); if (isset($_SESSION['wallet'])) header('Location: dashboard.php'); ?>
<!DOCTYPE html>
<html>
<head>
  <title>Login with Solana</title>
  <script>
  async function connectWallet() {
    if (!window.solana || !window.solana.isPhantom) {
      alert("Please install Phantom Wallet!");
      return;
    }

    try {
      const resp = await window.solana.connect();
      const wallet = resp.publicKey.toString();

      // Tạo thông điệp xác thực
      const message = `Login to Vina Network at ${new Date().toISOString()}`;
      const encodedMessage = new TextEncoder().encode(message);

      const signed = await window.solana.signMessage(encodedMessage, 'utf8');
      const signature = Array.from(signed.signature)
                             .map(x => x.toString(16).padStart(2, '0'))
                             .join('');

      // Gửi lên server
      const formData = new FormData();
      formData.append('wallet', wallet);
      formData.append('message', message);
      formData.append('signature', signature);

      const res = await fetch('login.php', {
        method: 'POST',
        body: formData
      });

      const result = await res.text();
      if (result === 'ok') {
        window.location.href = 'dashboard.php';
      } else {
        alert("Login failed: " + result);
      }
    } catch (err) {
      console.error(err);
      alert("Wallet connect or sign failed.");
    }
  }
  </script>
</head>
<body>
  <h2>Login with Solana Wallet</h2>
  <button onclick="connectWallet()">Connect Wallet</button>
</body>
</html>
