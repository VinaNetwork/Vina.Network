<?php
// Chức năng: Kiểm tra lịch sử giao dịch NFT
include 'api-helper.php';

// Hàm kiểm tra mint address hợp lệ
function validateMintAddress($mintAddress) {
    return !empty($mintAddress) && strlen($mintAddress) > 20 && preg_match('/^[A-Za-z0-9]+$/', $mintAddress);
}

// Số lượng giao dịch mỗi trang
$transactions_per_page = 10;

// Xác định trang hiện tại
$page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
$offset = ($page - 1) * $transactions_per_page;

// Xử lý khi form được gửi
$transactions = [];
$total_transactions = 0;
$mint_address = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mint_address'])) {
    $mint_address = trim($_POST['mint_address']);
    
    if (!validateMintAddress($mint_address)) {
        $transactions = ['error' => 'Invalid mint address. Please enter a valid Solana mint address (alphanumeric characters only).'];
    } else {
        // Gọi API Helius để lấy lịch sử giao dịch
        $data = callHeliusAPI("addresses/{$mint_address}/transactions", [], 'GET');

        if (isset($data['error'])) {
            $transactions = ['error' => $data['error']];
        } elseif (!empty($data)) {
            // Lọc các giao dịch liên quan đến NFT (chuyển NFT, mua/bán)
            $filtered_transactions = [];
            foreach ($data as $tx) {
                $is_nft_related = false;
                $price = null;
                $buyer = null;
                $seller = null;
                
                if (isset($tx['type']) && in_array($tx['type'], ['NFT_SALE', 'NFT_TRANSFER'])) {
                    $is_nft_related = true;
                    if ($tx['type'] === 'NFT_SALE') {
                        $price = isset($tx['amount']) ? $tx['amount'] / 1e9 : null;
                        $buyer = $tx['buyer'] ?? null;
                        $seller = $tx['seller'] ?? null;
                    } elseif ($tx['type'] === 'NFT_TRANSFER') {
                        $buyer = $tx['to'] ?? null;
                        $seller = $tx['from'] ?? null;
                    }
                }

                if ($is_nft_related) {
                    $filtered_transactions[] = [
                        'timestamp' => isset($tx['timestamp']) ? date('Y-m-d H:i:s', $tx['timestamp']) : 'N/A',
                        'type' => $tx['type'] ?? 'Unknown',
                        'price' => $price ? number_format($price, 2) . ' SOL' : 'N/A',
                        'buyer' => $buyer ?? 'N/A',
                        'seller' => $seller ?? 'N/A',
                        'signature' => $tx['signature'] ?? 'N/A'
                    ];
                }
            }

            // Lưu tổng số giao dịch
            $total_transactions = count($filtered_transactions);
            
            // Phân trang: chỉ lấy dữ liệu cho trang hiện tại
            $transactions = array_slice($filtered_transactions, $offset, $transactions_per_page);

            if (empty($transactions) && $total_transactions > 0) {
                $transactions = ['error' => 'No more transactions to display on this page.'];
            } elseif ($total_transactions == 0) {
                $transactions = ['error' => 'No transactions found for this NFT.'];
            }
        } else {
            $transactions = ['error' => 'No transactions found for this NFT.'];
        }
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
            <div class='result-error'><p class="error"><?php echo htmlspecialchars($transactions['error']); ?></p></div>
        <?php else: ?>
            <div class='result-section'>
                <p class='result-info'>Total Transactions: <?php echo $total_transactions; ?> (Page <?php echo $page; ?>)</p>
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

                <!-- Phân trang -->
                <?php if ($total_transactions > $transactions_per_page): ?>
                    <div class="pagination">
                        <?php
                        $total_pages = ceil($total_transactions / $transactions_per_page);
                        if ($page > 1): ?>
                            <form method="POST" class='page-form'><input type="hidden" name="mint_address" value="<?php echo htmlspecialchars($mint_address); ?>"><input type="hidden" name="page" value="<?php echo $page - 1; ?>"><button type="submit" class='page-btn'>Previous</button></form>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class='active-page'><?php echo $i; ?></span>
                            <?php else: ?>
                                <form method="POST" class='page-form'><input type="hidden" name="mint_address" value="<?php echo htmlspecialchars($mint_address); ?>"><input type="hidden" name="page" value="<?php echo $i; ?>"><button type="submit" class='page-btn'><?php echo $i; ?></button></form>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <form method="POST" class='page-form'><input type="hidden" name="mint_address" value="<?php echo htmlspecialchars($mint_address); ?>"><input type="hidden" name="page" value="<?php echo $page + 1; ?>"><button type="submit" class='page-btn'>Next</button></form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
