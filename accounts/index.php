<?php
// ============================================================================
// File: accounts/index.php
// Description: Connect wallet page for Vina Network. Handles rendering of the wallet connection interface.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../';

function log_message($message) {
    $log_file = __DIR__ . '/../logs/accounts.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_message("Processing GET request for wallet connection page");
require_once __DIR__ . '/auth.php';
log_message("Included auth.php for POST request handling");

// Render HTML for GET
$page_title = "Connect Wallet to Vina Network";
$page_description = "Connect your Solana wallet to register or login to Vina Network";
$page_keywords = "Vina Network, connect wallet, login, register";
$page_og_title = "Connect Wallet to Vina Network";
$page_og_description = "Connect your Solana wallet to register or login to Vina Network";
$page_og_url = "https://www.vina.network/accounts/";
$page_canonical = "https://www.vina.network/accounts/";
$page_css = ['acc.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include '../include/header.php'; ?>
<body>
    <?php include '../include/navbar.php'; ?>

    <div class="acc-container">
        <div class="acc-content">
            <h1>Login/Register with Phantom Wallet</h1>
            <button id="connect-wallet">Connect Phantom Wallet</button>
            <div id="wallet-info" style="display: none;">
                <p>Wallet address: <span id="public-key"></span></p>
                <p>Status: <span id="status"></span></p>
            </div>
        </div>
    </div>
    
    <?php include '../include/footer.php'; ?>
    <?php log_message("HTML rendering completed for wallet connection page"); ?>

    <script src="https://unpkg.com/@solana/web3.js@latest/lib/index.iife.min.js"></script>
    <script src="../js/vina.js"></script>
    <script src="../js/navbar.js"></script>
    <script src="acc.js"></script>
</body>
</html>
