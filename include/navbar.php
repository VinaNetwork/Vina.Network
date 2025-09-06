<?php
// ============================================================================
// File: include/navbar.php
// Description: Fixed top navigation bar shared across the Vina Network website.
// Created by: Vina Network
// ============================================================================
?>

<!-- Main Navigation Bar -->
<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="logo">
        <a href="/" aria-label="Vina Network Home">
            <img src="/img/logo.png" alt="Vina Network Logo">
            <span class="titleSite">Vina Network</span>
        </a>
    </div>

    <!-- Navigation Links -->
    <ul class="navbar-content">
        <li class="navbar-item">
            <a href="/" class="navbar-link">
                <i class="fas fa-home"></i> Home
            </a>
        </li>

        <!-- Accounts -->
        <li class="navbar-item dropdown">
            <a href="#" class="navbar-link dropdown-toggle">
                <i class="fas fa-user"></i> Accounts <i class="dropdown-icon fas fa-caret-down"></i>
            </a>
            <ul class="dropdown-menu">
                <li><a href="/acc/connect" class="dropdown-link"><i class="fas fa-wallet"></i> Connect</a></li>
                <li><a href="/acc/profile" class="dropdown-link"><i class="fas fa-address-card"></i> Profile</a></li>
            </ul>
        </li>

        <!-- Product Dropdown -->
        <li class="navbar-item dropdown">
            <a href="#" class="navbar-link dropdown-toggle">
                <i class="fas fa-box"></i> Make Market <i class="dropdown-icon fas fa-caret-down"></i>
            </a>
            <ul class="dropdown-menu">
                <li><a href="/mm/create" class="dropdown-link"><i class="fa-solid fa-right-left"></i> Create</a></li>
                <li><a href="/mm/history" class="dropdown-link"><i class="fas fa-wallet"></i> History</a></li>
            </ul>
        </li>

        <!-- Tools Dropdown -->
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

        <!-- Meme Coin Dropdown -->
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
