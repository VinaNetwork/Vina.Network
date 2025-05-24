<!DOCTYPE html>
<html lang="en">
<!-- Header -->
<?php
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
                    <p>Our vision is to build a global blockchain network. Our mission is to educate and accelerate crypto adoption through Web3 innovation.</p>
                </div>
                <div class="about-card fade-in" data-delay="400">
                    <img src="img/stages.png" alt="Stages Icon" class="card-icon">
                    <h3>Development Roadmap</h3>
                    <p>From community building to launching DeFi products and expanding partnerships, we’re shaping the future of blockchain.</p>
                </div>
                <div class="about-card fade-in" data-delay="600">
                    <img src="img/financial.png" alt="Financial Icon" class="card-icon">
                    <h3>Financial Ecosystem</h3>
                    <p>Leverage $VINA for staking, governance, and more, with revenue from DEX fees and stablecoin management.</p>
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
                    <h3>Security First</h3>
                    <p>Advanced encryption and decentralized protocols to protect your assets.</p>
                </div>
                <div class="why-choose-card fade-in" data-delay="400">
                    <i class="fas fa-globe"></i>
                    <h3>Global Reach</h3>
                    <p>Connect with a worldwide community of blockchain enthusiasts and developers.</p>
                </div>
                <div class="why-choose-card fade-in" data-delay="600">
                    <i class="fas fa-rocket"></i>
                    <h3>Innovative Solutions</h3>
                    <p>Cutting-edge DeFi, NFTs, and stablecoins tailored for the Web3 era.</p>
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
                    <h3>$VINA Token</h3>
                    <p>The core of our ecosystem, used for staking, governance, and transactions.</p>
                </div>
                <div class="ecosystem-card fade-in" data-delay="400">
                    <i class="fas fa-exchange-alt"></i>
                    <h3>DeFi Solutions</h3>
                    <p>Decentralized finance tools for lending, borrowing, and yield farming.</p>
                </div>
                <div class="ecosystem-card fade-in" data-delay="600">
                    <i class="fas fa-image"></i>
                    <h3>NFT Marketplace</h3>
                    <p>Create, trade, and collect NFTs on our secure and user-friendly platform.</p>
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
