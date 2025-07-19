<!DOCTYPE html>
<html lang="en">
<?php
// ============================================================================
// File: accounts/login.php
// Description: Login page for Vina Network.
// Created by: Vina Network
// ============================================================================

$root_path = '../';
$page_title = "Login page for Vina Network";
$page_description = "Login page for Vina Network";
$page_keywords = "Vina Network, login";
$page_og_title = "Login page for Vina Network";
$page_og_description = "Login page for Vina Network";
$page_og_url = "https://www.vina.network/accounts/login.php/";
$page_canonical = "https://www.vina.network/accounts/login.php/";
$page_css = ['accounts.css'];

include '../include/header.php';
?>
<body>
<!-- Navigation Bar -->
<?php include '../include/navbar.php'; ?>

<div class="container">
    <h1>Login to Vina Network</h1>
    <p id="wallet-address">Connect your wallet to login</p>
    <button id="connect-wallet" class="btn">Connect Wallet</button>
    <button id="login-btn" class="btn">Login</button>
</div>

<!-- Footer Section -->
<?php include '../include/footer.php'; ?>
<!-- Scripts -->
<script src="../js/vina.js"></script>
<script src="../js/navbar.js"></script>
<script src="accounts.js"></script>
</body>
</html>
