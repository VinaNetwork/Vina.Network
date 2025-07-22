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
$page_og_url = "https://www.vina.network/accounts/";
$page_canonical = "https://www.vina.network/accounts/";
$page_css = ['acc.css'];
include '../include/header.php';
?>
<body>
<!-- Navigation Bar -->
<?php include '../include/navbar.php'; ?>

<div class="acc-container">
    <div class="acc-content">
        <h1>Connect to Vina Network</h1>
        
    </div>
</div>

<!-- Footer Section -->
<?php include '../include/footer.php'; ?>
<!-- Scripts -->
<script src="../js/vina.js"></script>
<script src="../js/navbar.js"></script>
<script src="acc.js"></script>
</body>
</html>
