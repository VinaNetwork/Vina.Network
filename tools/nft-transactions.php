<?php
// nft-transactions.php
include 'api-helper.php';

$response = ['error' => null, 'html' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mint_address'])) {
    $mint_address = trim($_POST['mint_address']);
    $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
    $transactions_per_page = 10;

    if (validateMintAddress($mint_address)) {
        $data = callHeliusAPI("addresses/{$mint_address}/transactions");

        if (!isset($data['error']) && !empty($data)) {
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

            $pagination = getPaginatedData($filtered_transactions, $page, $transactions_per_page);
            $transactions = $pagination['data'];
            $total_transactions = $pagination['total_items'];

            ob_start();
            ?>
            <h2>NFT Transaction History</h2>
            <form method="POST" class="transaction-form">
                <label for="mint_address">Enter NFT Mint Address:</label>
                <input type="text" id="mint_address" name="mint_address" value="<?php echo htmlspecialchars($mint_address); ?>" placeholder="e.g. 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt" required>
                <button type="submit">Check Transactions</button>
            </form>
            <?php if (!empty($transactions) && !isset($transactions['error'])): ?>
                <p>Total Transactions: <?php echo $total_transactions; ?> (Page <?php echo $page; ?>)</p>
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
                <?php if ($total_transactions > $transactions_per_page): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="mint_address" value="<?php echo htmlspecialchars($mint_address); ?>">
                                <input type="hidden" name="page" value="<?php echo $page - 1; ?>">
                                <button type="submit">Previous</button>
                            </form>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= ceil($total_transactions / $transactions_per_page); $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="active-page"><?php echo $i; ?></span>
                            <?php else: ?>
                                <form method="POST" style="display:inline; margin-left: 5px;">
                                    <input type="hidden" name="mint_address" value="<?php echo htmlspecialchars($mint_address); ?>">
                                    <input type="hidden" name="page" value="<?php echo $i; ?>">
                                    <button type="submit"><?php echo $i; ?></button>
                                </form>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < ceil($total_transactions / $transactions_per_page)): ?>
                            <form method="POST" style="display:inline; margin-left: 10px;">
                                <input type="hidden" name="mint_address" value="<?php echo htmlspecialchars($mint_address); ?>">
                                <input type="hidden" name="page" value="<?php echo $page + 1; ?>">
                                <button type="submit">Next</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php elseif (isset($transactions['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($transactions['error']); ?></p>
            <?php endif;
            $response['html'] .= ob_get_clean();
        } else {
            $response['error'] = $data['error'] ?? 'No transactions found for this NFT.';
        }
    } else {
        $response['error'] = 'Invalid mint address. Please enter a valid Solana mint address.';
    }
} else {
    ob_start();
    ?>
    <h2>NFT Transaction History</h2>
    <form method="POST" class="transaction-form">
        <label for="mint_address">Enter NFT Mint Address:</label>
        <input type="text" id="mint_address" name="mint_address" placeholder="e.g. 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt" required>
        <button type="submit">Check Transactions</button>
    </form>
    <?php
    $response['html'] = ob_get_clean();
}

echo json_encode($response);
?>
