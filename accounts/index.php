<!DOCTYPE html>
<html lang="en">
<?php
// ============================================================================
// File: accounts/index.php
// Description: Connect wallet page for Vina Network. Handles both registration and login.
// Created by: Vina Network
// ============================================================================

$root_path = '../';
$page_title = "Connect Wallet to Vina Network";
$page_description = "Connect your Solana wallet to register or login to Vina Network";
$page_keywords = "Vina Network, connect wallet, login, register";
$page_og_title = "Connect Wallet to Vina Network";
$page_og_description = "Connect your Solana wallet to register or login to Vina Network";
$page_og_url = "https://www.vina.network/accounts/connect-wallet.php/";
$page_canonical = "https://www.vina.network/accounts/connect-wallet.php/";
$page_css = ['accounts.css'];

include '../include/header.php';
?>
<body>
<!-- Navigation Bar -->
<?php include '../include/navbar.php'; ?>

<div class="container">
    <h1>Connect to Vina Network</h1>
    <p id="wallet-address">Kết nối ví để đăng ký hoặc đăng nhập</p>
    <button id="connect-wallet" class="btn">Connect Wallet</button>
</div>

<!-- Footer Section -->
<?php include '../include/footer.php'; ?>
<!-- Scripts -->
<script src="https://unpkg.com/@solana/web3.js@latest/dist/index.min.js"></script>
<script src="https://unpkg.com/@solana/wallet-adapter-base@latest/dist/index.min.js"></script>
<script src="https://unpkg.com/@solana/wallet-adapter-wallets@latest/dist/index.min.js"></script>
<script src="https://unpkg.com/tweetnacl@latest/dist/nacl-fast.min.js"></script>
<script src="../js/vina.js"></script>
<script src="../js/navbar.js"></script>
<script src="accounts.js"></script>
</body>
</html>
