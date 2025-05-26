<?php
include 'api-helper.php';

function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('Y-m-d H:i:s');
}

error_log("nft-transactions.php loaded"); // Debug
?>

<div class="nft-transactions-content">
    <div class="nft-checkbox">
        <h2>Check NFT Transactions</h2>
        <p>Enter the address of the NFT to see its transaction history.</p>
        <form class="transaction-form" method="POST" action="">
            <input type="text" name="mintAddressTransactions" id="mintAddressTransactions" placeholder="Enter NFT Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
            <button type="submit">Check Transactions</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddressTransactions'])) {
            $mintAddress = trim($_POST['mintAddressTransactions']);
            $apiKey = API_KEY; // Lấy từ config.php
            $apiUrl = "https://api.helius.xyz/v0/addresses/{$mintAddress}/transactions?api-key={$apiKey}";

            error_log("nft-transactions.php: API request to $apiUrl"); // Debug
            $response = makeApiRequest($apiUrl);

            if (isset($response['error'])) {
                echo "<p class='error'>Error: {$response['error']}</p>";
                error_log("nft-transactions.php: API error - {$response['error']}"); // Debug
            } elseif (!empty($response)) {
                echo "<div class='nft-transaction-results'>";
                echo "<h3>Transaction History</h3>";
                echo "<table>";
                echo "<tr><th>Transaction ID</th><th>Date</th><th>Type</th><th>Amount (SOL)</th></tr>";

                foreach ($response as $transaction) {
                    $txId = $transaction['signature'] ?? 'N/A';
                    $date = isset($transaction['timestamp']) ? formatDate(date('c', $transaction['timestamp'])) : 'N/A';
                    $type = $transaction['type'] ?? 'N/A';
                    $amount = isset($transaction['fee']) ? $transaction['fee'] / 1000000000 : 'N/A';

                    echo "<tr>";
                    echo "<td><a href='https://explorer.solana.com/tx/{$txId}' target='_blank'>" . substr($txId, 0, 8) . "...</a></td>";
                    echo "<td>{$date}</td>";
                    echo "<td>{$type}</td>";
                    echo "<td>{$amount}</td>";
                    echo "</tr>";
                }

                echo "</table>";
                echo "</div>";
            } else {
                echo "<p>No transactions found for this NFT address.</p>";
                error_log("nft-transactions.php: No transactions found for $mintAddress"); // Debug
            }
        }
        ?>
    </div>
</div>
