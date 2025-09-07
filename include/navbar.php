<?php
// ============================================================================
// File: include/navbar.php
// Description: Fixed top navigation bar shared across the Vina Network website.
// Created by: Vina Network
// ============================================================================

// Check login status and admin rights
$public_key = $_SESSION['public_key'] ?? null;
$is_logged_in = !empty($public_key); // User is logged in if public_key exists
$is_admin = false;

if ($is_logged_in) {
    // Check admin rights
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $is_admin = true;
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT role, is_active FROM accounts WHERE public_key = ?");
            $stmt->execute([$public_key]);
            $nav_account = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($nav_account && $nav_account['is_active'] && $nav_account['role'] === 'admin') {
                $_SESSION['role'] = 'admin';
                $is_admin = true;
            }
        } catch (PDOException $e) {
            // Log errors but do not block navbar display
            $short_public_key = strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
            log_message("Database query failed for role check in navbar: {$e->getMessage()}, public_key: $short_public_key", 'accounts.log', 'accounts', 'ERROR');
        }
    }
}
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
                <?php if ($is_admin): ?>
                    <li><a href="/acc/admin" class="dropdown-link"><i class="fas fa-user-shield"></i> Admin</a></li>
                <?php endif; ?>
                <li><a href="/acc/connect" class="dropdown-link"><i class="fas fa-wallet"></i> Connect</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="/acc/profile" class="dropdown-link"><i class="fas fa-address-card"></i> Profile</a></li>
                <?php endif; ?>
            </ul>
        </li>

        <!-- Product Dropdown -->
        <li class="navbar-item dropdown">
            <a href="#" class="navbar-link dropdown-toggle">
                <i class="fas fa-box"></i> Make Market <i class="dropdown-icon fas fa-caret-down"></i>
            </a>
            <ul class="dropdown-menu">
                <li><a href="/mm/create" class="dropdown-link"><i class="fas fa-plus-circle"></i> Create</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="/mm/history" class="dropdown-link"><i class="fas fa-history"></i> History</a></li>
                <?php endif; ?>
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
                <i class="fas fa-coins"></i> Coin <i class="dropdown-icon fas fa-caret-down"></i>
            </a>
            <ul class="dropdown-menu">
                <li><a href="/notification" class="dropdown-link"><img class="logo-coin logo-vina" src="/img/logo.png" alt="Vina Network Coin"> VINA</a></li>
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
