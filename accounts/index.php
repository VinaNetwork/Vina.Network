<?php
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect Solana Wallet</title>
    <link rel="stylesheet" href="acc.css">
    <script src="https://cdn.jsdelivr.net/npm/@solana/web3.js@1.70.0/lib/index.iife.min.js"></script>
    <script src="acc.js"></script>
</head>
<body>
    <div class="wallet-container">
        <h1>Connect Your Solana Wallet</h1>
        <button id="connectWallet" class="connect-button">Connect Wallet</button>
        <div id="walletStatus" class="status-message"></div>
    </div>
</body>
</html>
