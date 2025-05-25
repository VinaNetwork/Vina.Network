<?php
// nft-valuation.php
include 'api-helper.php';

$response = ['error' => null, 'html' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddressValuation'])) {
    $mint_address = trim($_POST['mintAddressValuation']);

    if (validateMintAddress($mint_address)) {
        $params = ["mintAddresses" => [$mint_address]];
        $data = callHeliusAPI('tokens', $params);

        if (!isset($data['error']) && isset($data[0])) {
            $valuation = [
                'floorPrice' => $data[0]['floorPrice'] ?? 'N/A',
                'lastSale' => $data[0]['lastSale'] ?? 'N/A',
                'volume' => $data[0]['volume'] ?? 'N/A'
            ];

            ob_start();
            ?>
            <h2>Check NFT Valuation</h2>
            <p>Enter the mint address of the NFT to see its current floor price and recent sales.</p>
            <form id="nftValuationForm" method="POST" action="">
                <input type="text" name="mintAddressValuation" id="mintAddressValuation" value="<?php echo htmlspecialchars($mint_address); ?>" placeholder="Enter NFT Mint Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
                <button type="submit">Check Valuation</button>
            </form>
            <h3>Results</h3>
            <p>Floor Price: <?php echo htmlspecialchars($valuation['floorPrice']); ?> SOL</p>
            <p>Last Sale: <?php echo htmlspecialchars($valuation['lastSale']); ?> SOL</p>
            <p>Volume (24h): <?php echo htmlspecialchars($valuation['volume']); ?> SOL</p>
            <?php
            $response['html'] .= ob_get_clean();
        } else {
            $response['error'] = $data['error'] ?? 'No valuation data found for this NFT.';
        }
    } else {
        $response['error'] = 'Invalid mint address. Please enter a valid Solana mint address.';
    }
} else {
    ob_start();
    ?>
    <h2>Check NFT Valuation</h2>
    <p>Enter the mint address of the NFT to see its current floor price and recent sales.</p>
    <form id="nftValuationForm" method="POST" action="">
        <input type="text" name="mintAddressValuation" id="mintAddressValuation" placeholder="Enter NFT Mint Address (e.g., 4x7g2KuZvUraiF3txNjrJ8cAEfRh1ZzsSaWr18gtV3Mt)" required>
        <button type="submit">Check Valuation</button>
    </form>
    <?php
    $response['html'] = ob_get_clean();
}

echo json_encode($response);
?>
