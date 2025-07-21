<?php session_start(); if (isset($_SESSION['wallet'])) header('Location: dashboard.php'); ?>
<!DOCTYPE html>
<html>
<head>
  <title>Login with Solana</title>
  <script>
    async function connectWallet() {
      if (!window.solana || !window.solana.isPhantom) {
        alert("Please install Phantom wallet!");
        return;
      }

      try {
        const resp = await window.solana.connect();
        const wallet = resp.publicKey.toString();

        const formData = new FormData();
        formData.append('wallet', wallet);

        const res = await fetch('login.php', {
          method: 'POST',
          body: formData
        });

        const result = await res.text();
        if (result === 'ok') {
          window.location.href = 'dashboard.php';
        } else {
          alert("Login failed.");
        }
      } catch (err) {
        console.error(err);
        alert("Wallet connect failed.");
      }
    }
  </script>
</head>
<body>
  <h2>Login with Solana Wallet</h2>
  <button onclick="connectWallet()">Connect Wallet</button>
</body>
</html>
