<?php
// ============================================================================
// File: index.php
// Description: Homepage of Vina Network.
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/';
// constants | logging | config | error | session | database
require_once $root_path . 'bootstrap.php';
$page_css = ['/css/home.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php require_once $root_path . 'include/header.php';?>
<body>
<?php require_once $root_path . 'include/navbar.php';?>
<div class="home-container">
    <!-- Hero Section -->
    <section class="home-head">
        <div class="home-head-item">
            <h1>Vina Network</h1>
            <p>"Simplifying Crypto. Unlocking Web3"</p>
            <a href="#about" class="cta-button fade-in" data-delay="600">Discover More</a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="home-content home-1">       
	<h2 class="fade-in" data-delay="0">About Vina Network</h2>
	<div class="home-table">
		<div class="home-item fade-in" data-delay="400">
			<img src="/img/vision.png" alt="Vina Network Vision and Mission">
			<h3>Vision & Mission</h3>
			<p>Vina Network envisions becoming a leading crypto-focused ecosystem that serves a global user base. Its mission is to connect, educate, and accelerate the growth of blockchain technology adoption worldwide.</p>
		</div>

		<div class="home-item fade-in" data-delay="550">
			<img src="/img/financial.png" alt="Vina Network Financial Ecosystem">
			<h3>Financial Ecosystem</h3>
			<p>Vina Network aims to build a comprehensive crypto ecosystem, including Web3 wallets, decentralized exchanges (DEX), stablecoins, on-chain analytics tools, NFT platforms, GameFi, and more.</p>
		</div>

		<div class="home-item fade-in" data-delay="700">
			<img src="/img/core-values.png" alt="Vina Network Core Values">
			<h3>Core Values</h3>
			<p>Vina Network is guided by three core principles: transparency, innovation, and community empowerment. These values shape the way we build products, collaborate with partners, and serve users.</p>
		</div>

		<div class="home-item fade-in" data-delay="850">
			<img src="/img/stages.png" alt="Vina Network Development Roadmap">
			<h3>Development Roadmap</h3>
			<p>Vina Network is developing a strategic roadmap that begins with building a strong community and foundational platform. It will gradually launch core products such as a Web3 wallet, DEX, and stablecoin.</p>
		</div>
	</div>       
    </section>

    <!-- Why Choose Us Section -->
    <section class="home-content home-2">
	<h2 class="fade-in" data-delay="0">Why Choose Vina Network?</h2>
	<div class="home-table">              
		<div class="home-item fade-in" data-delay="400">
			<i class="fas fa-shield" aria-hidden="true"></i>
			<h3>Community - Centric Approach</h3>
			<p>Vina Network places its community at the heart of development. From education to governance, users are empowered to shape the future of the ecosystem through transparency, participation, and reward mechanisms.</p>
		</div>

		<div class="home-item fade-in" data-delay="550">
			<i class="fas fa-globe" aria-hidden="true"></i>
			<h3>Comprehensive Web3 Ecosystem</h3>
			<p>By integrating key crypto applications — like wallets, DEX, stablecoins, and on-chain tools — Vina Network offers a seamless and interconnected experience, enabling users to access the full potential of Web3.</p>
		</div>

		<div class="home-item fade-in" data-delay="700">
			<i class="fas fa-rocket" aria-hidden="true"></i>
			<h3>Visionary & Scalable Development</h3>
			<p>The products developed by Vina Network are designed with a long-term vision in mind. Its adaptable infrastructure and cross-chain compatibility ensure sustainability as blockchain evolves.</p>
		</div>

		<div class="home-item fade-in" data-delay="850">
			<i class="fas fa-shield-alt" aria-hidden="true"></i>
			<h3>Security - First Design</h3>
			<p>Vina Network prioritizes user safety with a security-first approach in all its products. From non-custodial wallets to transparent smart contracts, the ecosystem is built to protect users at every level.</p>
		</div>
	</div>
    </section>

    <!-- Our Ecosystem Section -->
    <section class="home-content home-3">
	<h2 class="fade-in" data-delay="0">Our Ecosystem</h2>
	<div class="home-table">
		<div class="home-item fade-in" data-delay="200">
			<i class="fas fa-wallet" aria-hidden="true"></i>
			<h3>Vina Wallet</h3>
			<p>Vina Wallet is a user-friendly, non-custodial Web3 wallet developed by Vina Network. It allows users to securely store, send, and receive cryptocurrencies, interact with decentralized applications, and manage their digital assets.</p>
		</div>

		<div class="home-item fade-in" data-delay="350">
			<i class="fas fa-balance-scale" aria-hidden="true"></i>
			<h3>Vina Stablecoin</h3>
			<p>Vina Stablecoin is a core component of the Vina Network ecosystem, providing a reliable, value-stable medium of exchange for users and DeFi platforms. It is designed to support fast, low-cost transactions.</p>
		</div>

		<div class="home-item fade-in" data-delay="500">
			<i class="fas fa-exchange-alt" aria-hidden="true"></i>
			<h3>Vina DEX</h3>
			<p>Vina DEX is a decentralized exchange developed by Vina Network that enables users to trade cryptocurrencies directly from their wallets without relying on intermediaries. Built for speed, security, and transparency.</p>
		</div>

		<div class="home-item fade-in" data-delay="650">
			<i class="fas fa-link" aria-hidden="true"></i>
			<h3>Vina DeFi</h3>
			<p>Vina DeFi is a decentralized finance platform within the Vina Network ecosystem, offering services such as staking, lending, borrowing, and yield farming. Designed to be accessible and secure for all users.</p>
		</div>

		<div class="home-item fade-in" data-delay="800">
			<i class="fas fa-gamepad" aria-hidden="true"></i>
			<h3>Vina GameFi</h3>
			<p>Vina GameFi is an innovative platform that merges blockchain technology with gaming, enabling players to earn rewards through play-to-earn mechanics. Integrated with NFTs and DeFi.</p>
		</div>

		<div class="home-item fade-in" data-delay="950">
			<i class="fas fa-image" aria-hidden="true"></i>
			<h3>NFT Marketplace</h3>
			<p>Vina NFT Marketplace is a decentralized platform where users can create, buy, sell, and trade NFTs with ease and security. Supporting a wide range of digital assets, from art to music and beyond.</p>
		</div>
	</div>
    </section>
</div>
<?php require_once $root_path . 'include/community.php'; ?>
<?php require_once $root_path . 'include/widget.php'; ?>
<?php require_once $root_path . 'include/footer.php';?>

<!-- Back to Top Button -->
<button id="back-to-top" title="Back to top">
	<i class="fas fa-arrow-up" aria-hidden="true"></i>
</button>

<!-- Scripts -->
<script src="js/vina.js"></script>
<script src="js/home.js"></script>
</body>
</html>
