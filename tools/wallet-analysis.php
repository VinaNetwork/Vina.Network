<?php
// wallet-analysis.php
// Cấu hình log lỗi
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

include 'api-helper.php';
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
            $walletData = getWalletData($walletAddress);

            if (isset($walletData['error'])) {
                echo "<div class='result-error'><p>" . htmlspecialchars($walletData['error']) . "</p></div>";
            } elseif ($walletData) {
                echo "<div class='result-section'>";
                echo "<h3>Results</h3>";
                echo "<p class='result-info'>Wallet Address: " . htmlspecialchars($walletAddress) . "</p>";
                echo "<p class='result-info'>SOL Balance: " . htmlspecialchars($walletData['sol_balance'] ?? 'N/A') . " SOL</p>";
                echo "<p class='result-info'>Token Count: " . htmlspecialchars($walletData['token_count'] ?? 'N/A') . "</p>";
                echo "<p class='result-info'>Recent Activity Count: " . htmlspecialchars($walletData['activity_count'] ?? 'N/A') . "</p>";
                echo "</div>";
            } else {
                echo "<div class='result-error'><p>No data found for this wallet address.</p></div>";
            }
        }
    }
    ?>

    <div class="feature-description">
        <h3>About Wallet Analysis</h3>
        <p>
            The Wallet Analysis tool allows you to analyze a Solana wallet by entering its address. 
            It retrieves key information such as the SOL balance, number of tokens held, and recent activities on the Solana blockchain. 
            This tool is useful for investors, traders, or anyone interested in tracking wallet activities.
        </p>
    </div>
</div>

<?php
function getWalletData($walletAddress) {
    // Gọi API Helius để lấy thông tin ví
    $payload = [
        "addresses" => [$walletAddress]
    ];

    // Lấy số dư SOL
    $balanceData = callHeliusAPI('addresses/' . $walletAddress . '/balances');
    if (isset($balanceData['error'])) {
        return ['error' => $balanceData['error']];
    }

    // Lấy danh sách token
    $tokenData = callHeliusAPI('addresses/' . $walletAddress . '/tokens');
    if (isset($tokenData['error'])) {
        return ['error' => $tokenData['error']];
    }

    // Lấy hoạt động gần đây
    $activityData = callHeliusAPI('addresses/' . $walletAddress . '/transactions', ['limit' => 10]);
    if (isset($activityData['error'])) {
        return ['error' => $activityData['error']];
    }

    // Xử lý dữ liệu trả về
    $solBalance = isset($balanceData['nativeBalance']) ? $balanceData['nativeBalance'] / 1e9 : 'N/A'; // Chuyển từ lamports sang SOL
    $tokenCount = isset($tokenData['tokens']) ? count($tokenData['tokens']) : 'N/A';
    $activityCount = isset($activityData['transactions']) ? count($activityData['transactions']) : 'N/A';

    return [
        'sol_balance' => $solBalance,
        'token_count' => $tokenCount,
        'activity_count' => $activityCount
    ];
}
?>
