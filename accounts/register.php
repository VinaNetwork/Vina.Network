<!DOCTYPE html>
<html lang="en">
<?php
// ============================================================================
// File: accounts/register.php
// Description: Register page for Vina Network.
// Created by: Vina Network
// ============================================================================

$root_path = '../';
$page_title = "Register page for Vina Network";
$page_description = "Register page for Vina Network";
$page_keywords = "Vina Network, register";
$page_og_title = "Register page for Vina Network";
$page_og_description = "Register page for Vina Network";
$page_og_url = "https://www.vina.network/accounts/register.php/";
$page_canonical = "https://www.vina.network/accounts/register.php/";
$page_css = ['accounts.css'];

include '../include/header.php';
?>
<body>
<!-- Navigation Bar -->
<?php include '../include/navbar.php'; ?>

<div class="container">
    <h1>Register for Vina Network</h1>
    <p id="wallet-address">Connect your wallet to register</p>
    <button id="connect-wallet" class="btn">Connect Wallet</button>
    <button id="register-btn" class="btn">Register</button>
</div>

<!-- Footer Section -->
<?php include '../include/footer.php'; ?>
<!-- Scripts -->
<script src="../js/vina.js"></script>
<script src="../js/navbar.js"></script>
<script src="accounts.js"></script>
</body>
</html>
