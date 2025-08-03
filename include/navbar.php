<?php
// ============================================================================
// File: include/navbar.php
// Description: Fixed top navigation bar shared across the Vina Network website.
// Created by: Vina Network
// ============================================================================

// Main Navigation Bar
<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="logo">
        <a href="/" aria-label="Vina Network Home">
            <img src="/img/logo.png" alt="Vina Network Logo">
            <span class="titleSite">Vina Network</span>
        </a>
    </div>

    <ul class="navbar-content">
        <li class="navbar-item">
            <a href="/" class="navbar-link">
                <i class="fas fa-home"></i> Home
            </a>
        </li>

        <li class="navbar-item dropdown">
            <a href="#" class="navbar-link dropdown-toggle">
                <i class="fas fa-user"></i> Accounts <i class="dropdown-icon fas fa-caret-down"></i>
            </a>
            <ul class="dropdown-menu">
                <li><a href="/accounts" class="dropdown-link"><i class="fas fa-wallet"></i> Connect Wallet</a></li>
                <li><a href="/accounts/profile.php" class="dropdown-link"><i class="fas fa-address-card"></i> Profile</a></li>
            </ul>
        </li>

        <li class="navbar-item dropdown">
            <a href="#" class="navbar-link dropdown-toggle">
                <i class="fas fa-box"></i> Product <i class="dropdown-icon fas fa-caret-down"></i>
            </a>
            <ul class="dropdown-menu">
                <li><a href="/make-market" class="dropdown-link"><i class="fa-solid fa-right-left"></i> Make Market</a></li>
                <li><a href="/notification" class="dropdown-link"><i class="fas fa-wallet"></i> Vina Wallet</a></li>
                <li><a href="/notification" class="dropdown-link"><i class="fas fa-balance-scale"></i> Vina Stablecoin</a></li>
                <li><a href="/notification" class="dropdown-link"><i class="fas fa-exchange-alt"></i> Vina Dex</a></li>
                <li><a href="/notification" class="dropdown-link"><i class="fas fa-link"></i> Vina DeFi</a></li>
                <li><a href="/notification" class="dropdown-link"><i class="fas fa-gamepad"></i> Vina GameFi</a></li>
                <li><a href="/notification" class="dropdown-link"><i class="fas fa-image"></i> NFT Marketplace</a></li>
            </ul>
        </li>

        <li class="navbar-item dropdown">
            <a href="#" class="navbar-link dropdown-toggle">
                <i class="fas fa-screwdriver-wrench"></i> Tools <i class="dropdown-icon fas fa-caret-down"></i>
            </a>
            <ul class="dropdown-menu">
                <li><a href="/tools/?tool=nft-info" class="dropdown-link"><i class="fas fa-image"></i> Check NFT Info</a></li>
                <li><a href="/tools/?tool=nft-holders" class="dropdown-link"><i class="fas fa-wallet"></i> Check NFT Holders</a></li>
                <li><a href="/tools/?tool=nft-transactions" class="dropdown-link"><i class="fas fa-clock-rotate-left"></i> Check NFT Transactions</a></li>
                <li><a href="/tools/?tool=wallet-creators" class="dropdown-link"><i class="fas fa-image"></i> Check Wallet Creators</a></li>
                <li><a href="/tools/?tool=wallet-analysis" class="dropdown-link"><i class="fas fa-chart-line"></i> Wallet Analysis</a></li>
            </ul>
        </li>

        <li class="navbar-item dropdown">
            <a href="#" class="navbar-link dropdown-toggle">
                <i class="fas fa-coins"></i> Meme Coin <i class="dropdown-icon fas fa-caret-down"></i>
            </a>
            <ul class="dropdown-menu">
                <li><a href="/notification" class="dropdown-link"><i class="fa-solid fa-crown"></i> Kimo</a></li>
            </ul>
        </li>

        <li class="navbar-item">
            <a href="/contact" class="navbar-link">
                <i class="fas fa-envelope"></i> Contact
            </a>
        </li>
    </ul>

    <!-- Burger Menu (for mobile responsiveness) -->
    <div class="burger" aria-label="Menu toggle">
        <div class="line1"></div>
        <div class="line2"></div>
        <div class="line3"></div>
    </div>
</nav>
?>
