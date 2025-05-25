<?php
// nft-holders.php
include 'api-helper.php';

$response = ['error' => null, 'html' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
    $mint_address = trim($_POST['mintAddress']);
    $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
    $holders_per_page = 10;

    if (validateMintAddress($mint_address)) {
        $params = ["mint" => $mint_address, "includeOffChain" => false, "limit" => 1000, "page" => $page];
        $data = callHeliusAPI('token-accounts', $params);

        if (!isset($data['error']) && isset($data['token_accounts'])) {
            $holders = array_unique(array_column($data['token_accounts'], 'owner'));
            $pagination = getPaginatedData($holders, $page, $holders_per_page);
            $holders = $pagination['data'];
            $total_holders = $pagination['total_items'];

            ob_start();
            ?>
            <h2>Check Solana NFT Holders</h2>
            <p>Enter the mint address of the NFT to see the number of holders and their wallet addresses.</p>
            <form id="nftHoldersForm" method="POST" action="">
                <input type="text" name="mintAddress" id="mintAddressHolders" value="<?php echo htmlspecialchars($mint_address); ?>" placeholder="Enter NFT Mint Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
                <button type="submit">Check Holders</button>
            </form>
            <h3>Results</h3>
            <p>Total Holders: <?php echo $total_holders; ?> (Page <?php echo $page; ?>)</p>
            <ul>
                <?php foreach ($holders as $holder): ?>
                    <li><?php echo htmlspecialchars($holder); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($total_holders > $holders_per_page): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="mintAddress" value="<?php echo htmlspecialchars($mint_address); ?>">
                            <input type="hidden" name="page" value="<?php echo $page - 1; ?>">
                            <button type="submit">Previous</button>
                        </form>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= ceil($total_holders / $holders_per_page); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active-page"><?php echo $i; ?></span>
                        <?php else: ?>
                            <form method="POST" style="display:inline; margin-left: 5px;">
                                <input type="hidden" name="mintAddress" value="<?php echo htmlspecialchars($mint_address); ?>">
                                <input type="hidden" name="page" value="<?php echo $i; ?>">
                                <button type="submit"><?php echo $i; ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < ceil($total_holders / $holders_per_page)): ?>
                        <form method="POST" style="display:inline; margin-left: 10px;">
                            <input type="hidden" name="mintAddress" value="<?php echo htmlspecialchars($mint_address); ?>">
                            <input type="hidden" name="page" value="<?php echo $page + 1; ?>">
                            <button type="submit">Next</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif;
            $response['html'] = ob_get_clean();
        } else {
            ob_start();
            ?>
            <h2>Check Solana NFT Holders</h2>
            <p>Enter the mint address of the NFT to see the number of holders and their wallet addresses.</p>
            <form id="nftHoldersForm" method="POST" action="">
                <input type="text" name="mintAddress" id="mintAddressHolders" value="<?php echo htmlspecialchars($mint_address); ?>" placeholder="Enter NFT Mint Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
                <button type="submit">Check Holders</button>
            </form>
            <p><?php echo htmlspecialchars($data['error'] ?? 'No holders found for this NFT.'); ?></p>
            <?php
            $response['html'] = ob_get_clean();
        }
    } else {
        ob_start();
        ?>
        <h2>Check Solana NFT Holders</h2>
        <p>Enter the mint address of the NFT to see the number of holders and their wallet addresses.</p>
        <form id="nftHoldersForm" method="POST" action="">
            <input type="text" name="mintAddress" id="mintAddressHolders" value="<?php echo htmlspecialchars($mint_address); ?>" placeholder="Enter NFT Mint Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
            <button type="submit">Check Holders</button>
        </form>
        <p>Invalid mint address. Please enter a valid Solana mint address.</p>
        <?php
        $response['html'] = ob_get_clean();
    }
} else {
    ob_start();
    ?>
    <h2>Check Solana NFT Holders</h2>
    <p>Enter the mint address of the NFT to see the number of holders and their wallet addresses.</p>
    <form id="nftHoldersForm" method="POST" action="">
        <input type="text" name="mintAddress" id="mintAddressHolders" placeholder="Enter NFT Mint Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
        <button type="submit">Check Holders</button>
    </form>
    <?php
    $response['html'] = ob_get_clean();
}

echo json_encode($response);
?>
