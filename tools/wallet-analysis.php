<?php
// wallet-analysis.php
// Cấu hình log lỗi
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);
?>

<div class="wallet-analysis-content">
    <div class="nft-checkbox">
        <h2>Check Solana Wallet</h2>
        <p>Enter the wallet address to see its balance, tokens, and recent activities.</p>
        <form id="walletAnalysisForm" method="POST" action="">
            <input type="text" name="walletAddress" id="walletAddress" placeholder="Enter Solana Wallet Address (e.g., 7xKXtg2CW87d97...)" required>
            <button type="submit">Analyze Wallet</button>
        </form>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['walletAddress'])) {
        $walletAddress = trim($_POST['walletAddress']);
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletAddress)) {
            echo "<div class='result-error'><p>Invalid wallet address. Please enter a valid Solana wallet address (32-44 characters, base58).</p></div>";
        } else {
            echo "<div class='result-section'><p class='result-info'>Wallet analysis for $walletAddress is not yet implemented. Please wait for API integration.</p></div>";
        }
    }
    ?>

    <div class="feature-description">
        <h3>About Wallet Analysis</h3>
        <p>
            The Wallet Analysis tool allows you to analyze a Solana wallet by entering its address. 
            It will retrieve key information such as the SOL balance, number of tokens held, and recent activities on the Solana blockchain. 
            This tool is useful for investors, traders, or anyone interested in tracking wallet activities.
        </p>
    </div>
</div>
