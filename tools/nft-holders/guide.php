<!DOCTYPE html>
<html lang="en">
<?php
// Định nghĩa biến cơ bản abc
$root_path = '../';
$page_title = "Vina Network - Airdrop Guide with NFT";
$page_description = "Step-by-step guide to running an airdrop event with an NFT collection on Vina Network using Solana blockchain.";
$page_keywords = "Vina Network, Solana NFT, airdrop guide, NFT holders, Magic Eden, blockchain, VINA";
$page_og_title = "Vina Network - Airdrop Guide with NFT";
$page_og_url = "https://vina.network/guide/";
$page_canonical = "https://vina.network/guide/";
$page_css = ['tools.css'];

// Include header.php
include $root_path . 'include/header.php';
?>

<body>
<!-- Include Navbar -->
<?php include $root_path . 'include/navbar.php'; ?>

<section class="tools-section">
    <div class="tools-content">
        <h1>Vina Network Airdrop Guide</h1>

        <!-- Note -->
        <p class="note">Note: This guide focuses on Solana blockchain and uses the NFT Holders tool.</p>

        <!-- Nội dung hướng dẫn -->
        <div class="tool-content">
            <h2>Guide to Running an Airdrop Event with an NFT Collection</h2>
            <p>An NFT-based airdrop is an effective way to attract users and build a community on a blockchain like Solana. Below are the detailed steps:</p>

            <h3>Step 1: Plan and Design the NFT Collection</h3>
            <ul>
                <li>Define the concept: Create a unique NFT collection (e.g., "Vina Solana Guardians") related to Solana and Vina Network.</li>
                <li>Set quantity: Limit minting (e.g., 10,000 NFTs) to enhance rarity value.</li>
                <li>Design images: Use tools like Photoshop or hire an artist to create eye-catching NFT artwork.</li>
                <li>Choose blockchain: Use Solana for its low fees and high speed.</li>
            </ul>

            <h3>Step 2: Develop and Deploy the NFT</h3>
            <ul>
                <li>Create smart contract: Use Metaplex on Solana to develop NFTs, ensuring support for minting and metadata.</li>
                <li>Set up minting platform: Integrate with Magic Eden (a popular Solana marketplace) for users to mint NFTs. Example: Create a collection on <a href="https://magiceden.io/creator" target="_blank">https://magiceden.io/creator</a> and set up a dedicated minting page.</li>
                <li>Set minting price: Set a low minting fee (e.g., 0.01 SOL) or make it free to encourage participation.</li>
            </ul>

            <h3>Step 3: Organize the Minting Event</h3>
            <ul>
                <li>Promote: Post announcements on X, Telegram, and Discord, highlighting the airdrop opportunity.</li>
                <li>Minting instructions: Users connect a Solana wallet (e.g., Phantom) on the Magic Eden minting page, pay the fee, and receive their NFT.</li>
            </ul>

            <h3>Step 4: Set Airdrop Conditions</h3>
            <ul>
                <li>Holding requirement: A wallet only needs to hold an NFT at the time of checking to be eligible.</li>
                <li>Token allocation: Distribute airdrop tokens (e.g., 100 VINA/token) based on the number of NFTs owned (1 NFT = 100 tokens).</li>
            </ul>

            <h3>Step 5: Execute the Airdrop</h3>
            <ul>
                <li>Check wallet list:
                    <ul>
                        <li>Visit the <strong>Vina Network Tools</strong> page at <a href="https://www.vina.network/tools/?tool=nft-holders" target="_blank">https://vina.network/tools/</a> and select the <strong>NFT Holders</strong> tab.</li>
                        <li>Enter the NFT collection address (NFT Collection Address) minted on Magic Eden into the "Check NFT Holders" tool's search field.</li>
                        <li>The tool will automatically display a full list of wallets holding NFTs from the collection, including the number of NFTs each wallet owns.</li>
                        <li>Filter the list to exclude unwanted wallets (e.g., those not meeting other conditions), then save as a CSV file or copy directly.</li>
                    </ul>
                </li>
                <li>Distribute tokens: Use Solana CLI or an automated airdrop service to send tokens to the filtered wallets based on their NFT holdings.</li>
                <li>Announce: Update results on the website and social media, providing instructions for claiming tokens.</li>
            </ul>

            <h3>Step 6: Evaluate and Optimize</h3>
            <ul>
                <li>Gather feedback: Ask the community for their experience.</li>
                <li>Analyze effectiveness: Assess participation numbers and the impact on NFT value.</li>
                <li>Plan next event: Organize another airdrop if needed.</li>
            </ul>

            <h3>Important Notes</h3>
            <ul>
                <li>Ensure user safety by avoiding requests for private keys.</li>
                <li>Check local legal regulations to ensure compliance.</li>
                <li>Use a test wallet to experiment before official deployment.</li>
            </ul>
        </div>
    </div>
</section>

<!-- Include Footer -->
<?php include $root_path . 'include/footer.php'; ?>

<script src="../js/vina.js"></script>
<script src="tools.js"></script>
</body>
</html>
