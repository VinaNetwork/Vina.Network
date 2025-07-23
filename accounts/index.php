<?php
// File: accounts/index.php
session_start();
if (isset($_SESSION['public_key'])) {
    header("Location: profile.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect Solana Wallet</title>
    <link rel="stylesheet" href="acc.css">
</head>
<body>
    <div class="container">
        <h1>Connect Your Solana Wallet</h1>
        <button id="connectWallet">Connect Wallet</button>
        <p id="status"></p>
    </div>
    <script src="https://unpkg.com/@solana/web3.js@latest/lib/index.iife.js"></script>
    <script src="acc.js"></script>
</body>
</html>
