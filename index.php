<!DOCTYPE html>
<html lang="en">
<!-- Header -->
<?php
$page_canonical = 'https://vina.network';
$page_css = ['css/home.css'];
include 'include/header.php';
?>

<!-- body -->
<body>
    <!-- Include Header -->
    <?php include 'include/navbar.php'; ?>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <h1 class="fade-in" data-delay="0">Vina Network</h1>
            <p class="fade-in" data-delay="200">Empowering the Future of Blockchain and Web3</p>
            <a href="#about" class="cta-button fade-in" data-delay="400">Discover More</a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="about-content">
            <h2 class="fade-in" data-delay="0">About Vina Network</h2>
            <div class="about-cards">
                <div class="about-card fade-in" data-delay="200">
                    <img src="img/vision.png" alt="Vision Icon" class="card-icon">
                    <h3>Vision & Mission</h3>
                    <p>Vina Network envisions becoming a leading crypto-focused ecosystem that serves a global user base. Its mission is to connect, educate, and accelerate the growth of blockchain adoption through innovative Web3 products, stablecoins, decentralized finance (DeFi), and community-driven initiatives.</p>
                </div>
                <div class="about-card fade-in" data-delay="600">
                    <img src="img/financial.png" alt="Financial Icon" class="card-icon">
                    <h3>Financial Ecosystem</h3>
                    <p>Vina Network aims to build a comprehensive crypto ecosystem, including Web3 wallets, decentralized exchanges (DEX), stablecoins, on-chain analytics tools, NFT platforms, GameFi, and DeFi services. This ecosystem will connect a global community and drive blockchain adoption through transparent, user-friendly, and accessible Web3 applications.

</p>
                </div>
                <div class="about-card fade-in" data-delay="400">
                    <img src="img/stages.png" alt="Stages Icon" class="card-icon">
                    <h3>Development Roadmap</h3>
                    <p>Vina Network is developing a strategic roadmap that begins with building a strong community and foundational platform. It will gradually launch core products such as a Web3 wallet, DEX, and stablecoin protocol, while expanding into areas like NFT, GameFi, and educational partnerships - laying the groundwork for a robust and scalable Web3 ecosystem.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="why-choose-section">
        <div class="why-choose-content">
            <h2 class="fade-in" data-delay="0">Why Choose Vina Network?</h2>
            <div class="why-choose-cards">
                <div class="why-choose-card fade-in" data-delay="200">
                    <i class="fas fa-shield"></i> <!-- Changed from fa-shield-alt -->
                    <h3>Community - Centric Approach</h3>
                    <p>Vina Network places its community at the heart of development. From education to governance, users are empowered to shape the future of the ecosystem through transparency, participation, and incentives.</p>
                </div>
                <div class="why-choose-card fade-in" data-delay="400">
                    <i class="fas fa-globe"></i>
                    <h3>Comprehensive Web3 Ecosystem</h3>
                    <p>By integrating key crypto applications — like wallets, DEX, stablecoins, and on-chain tools — Vina Network offers a seamless and interconnected experience, enabling users to access the full power of Web3 in one place.</p>
                </div>
                <div class="why-choose-card fade-in" data-delay="600">
                    <i class="fas fa-rocket"></i>
                    <h3>Visionary & Scalable Development</h3>
                    <p>The products developed by Vina Network are designed with a long-term vision in mind. Its adaptable infrastructure and cross-chain compatibility ensure sustainability as blockchain technology continues to evolve.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Ecosystem Section -->
    <section class="ecosystem-section">
        <div class="ecosystem-content">
            <h2 class="fade-in" data-delay="0">Our Ecosystem</h2>
            <div class="ecosystem-cards">
                <div class="ecosystem-card fade-in" data-delay="200">
                    <i class="fas fa-coins"></i>
                    <h3>Vina Wallet</h3>
                    <p>Vina Wallet is a user-friendly, non-custodial Web3 wallet developed by Vina Network. It allows users to securely store, send, and receive cryptocurrencies, interact with decentralized applications (DApps), and manage NFTs - all in one streamlined interface. Designed for both beginners and advanced users, Vina Wallet prioritizes security, simplicity, and seamless integration across multiple blockchain networks.</p>
                </div>
                <div class="ecosystem-card fade-in" data-delay="400">
                    <i class="fas fa-exchange-alt"></i>
                    <h3>Vina Stablecoin</h3>
                    <p>Vina Stablecoin is a core component of the Vina Network ecosystem, providing a reliable, value-stable medium of exchange for users and DeFi platforms. It is designed to support seamless transactions, lending, and cross-border payments. It is backed by digital assets and other highly liquid assets, ensuring stability, transparency, and trust in a decentralized environment.</p>
                </div>
                <div class="ecosystem-card fade-in" data-delay="400">
                    <i class="fas fa-exchange-alt"></i>
                    <h3>Vina DEX</h3>
                    <p>Vina DEX is a decentralized exchange developed by Vina Network that enables users to trade cryptocurrencies directly from their wallets without relying on intermediaries. Built for speed, security, and low fees, Vina DEX offers a seamless trading experience with full control over user assets, supporting multiple blockchain networks and fostering true financial freedom.</p>
                </div>
                <div class="ecosystem-card fade-in" data-delay="400">
                    <i class="fas fa-exchange-alt"></i>
                    <h3></h3>
                    <p></p>
                </div>
                <div class="ecosystem-card fade-in" data-delay="600">
                    <i class="fas fa-image"></i>
                    <h3>NFT Marketplace</h3>
                    <p></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Join Our Community Section -->
    <?php include 'include/community.php'; ?>
    <!-- Include Crypto Widget -->
    <?php include 'include/crypto_widget.php'; ?>
    <!-- Include Footer -->
    <?php include 'include/footer.php'; ?>
    <!-- Back to Top -->
    <button id="back-to-top" title="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script src="js/vina.js"></script>
</body>
</html>
