<?php
// Định nghĩa API Key Helius
$helius_api_key = '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63';

// Xử lý khi form được gửi
$transactions = [];
$mint_address = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mint_address'])) {
    $mint_address = trim($_POST['mint_address']);
    
    // Kiểm tra mint address hợp lệ (đơn giản)
    if (!empty($mint_address) && strlen($mint_address) > 20) {
        // Gọi API Helius để lấy lịch sử giao dịch
        $url = "https://api.helius.xyz/v0/addresses/{$mint_address}/transactions?api-key={$helius_api_key}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            error_log("cURL error in nft-transactions.php: $error_msg");
            $transactions = ['error' => 'Error fetching transaction history. Please check your connection or try again later.'];
        } else {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code === 200) {
                $data = json_decode($response, true);
                if (!empty($data)) {
                    // Lọc các giao dịch liên quan đến NFT (chuyển NFT, mua/bán)
                    foreach ($data as $tx) {
                        $is_nft_related = false;
                        $price = null;
                        $buyer = null;
                        $seller = null;
                        
                        // Kiểm tra nếu giao dịch có liên quan đến NFT
                        if (isset($tx['type']) && in_array($tx['type'], ['NFT_SALE', 'NFT_TRANSFER'])) {
                            $is_nft_related = true;
                            if ($tx['type'] === 'NFT_SALE') {
                                $price = isset($tx['amount']) ? $tx['amount'] / 1e9 : null; // Chuyển từ lamports sang SOL
                                $buyer = $tx['buyer'] ?? null;
                                $seller = $tx['seller'] ?? null;
                            } elseif ($tx['type'] === 'NFT_TRANSFER') {
                                $buyer = $tx['to'] ?? null;
                                $seller = $tx['from'] ?? null;
                            }
                        }

                        if ($is_nft_related) {
                            $transactions[] = [
                                'timestamp' => isset($tx['timestamp']) ? date('Y-m-d H:i:s', $tx['timestamp']) : 'N/A',
                                'type' => $tx['type'] ?? 'Unknown',
                                'price' => $price ? number_format($price, 2) . ' SOL' : 'N/A',
                                'buyer' => $buyer ?? 'N/A',
                                'seller' => $seller ?? 'N/A',
                                'signature' => $tx['signature'] ?? 'N/A'
                            ];
                        }
                    }
                } else {
                    $transactions = ['error' => 'No transactions found for this NFT.'];
                }
            } else {
                $transactions = ['error' => "API error (HTTP $http_code). Please try again later."];
            }
        }
        curl_close($ch);
    } else {
        $transactions = ['error' => 'Invalid mint address. Please enter a valid Solana mint address.'];
    }
}
?>

<div class="nft-transactions">
    <h2>NFT Transaction History</h2>
    <form method="POST" class="transaction-form">
        <label for="mint_address">Enter NFT Mint Address:</label>
        <input type="text" id="mint_address" name="mint_address" value="<?php echo htmlspecialchars($mint_address); ?>" placeholder="e.g. 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt" required>
        <button type="submit">Check Transactions</button>
    </form>

    <?php if (!empty($transactions)): ?>
        <?php if (isset($transactions['error'])): ?>
            <p class="error"><?php echo htmlspecialchars($transactions['error']); ?></p>
        <?php else: ?>
            <table class="transaction-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Price</th>
                        <th>Buyer</th>
                        <th>Seller</th>
                        <th>Signature</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tx['timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($tx['type']); ?></td>
                            <td><?php echo htmlspecialchars($tx['price']); ?></td>
                            <td><?php echo htmlspecialchars($tx['buyer']); ?></td>
                            <td><?php echo htmlspecialchars($tx['seller']); ?></td>
                            <td>
                                <a href="https://explorer.solana.com/tx/<?php echo htmlspecialchars($tx['signature']); ?>" target="_blank">
                                    <?php echo substr(htmlspecialchars($tx['signature']), 0, 10) . '...'; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
