<!DOCTYPE html>
<html lang="en">
<?php
// ============================================================================
// File: notification/notification.php
// Description: Notification page for products currently under development.
// Created by: Vina Network
// ============================================================================

// Set path and SEO metadata for this page
$root_path = '../';
$page_title = "Notification - Vina Network";
$page_og_url = "https://www.vina.network/notification/";
$page_canonical = "https://www.vina.network/notification/";
$page_css = ['notification.css'];

// Include shared <head> content (meta, title, styles, etc.)
include '../include/header.php';
?>

<body>
<!-- Include shared top navigation bar -->
<?php include '../include/navbar.php'; ?>

<!-- Notification section showing under-construction message -->
<section class="notification">
    <div class="notification-content">
        <i class="fas fa-tools"></i>
        <h1>Products Under Development</h1>
        <p>We’re sorry, but our products are currently under development. Our team is working hard to bring you the best experience. Stay tuned for updates!</p>
        <a href="https://www.vina.network/" class="cta-button">Back to Home</a>
    </div>
</section>

<!-- Include community -->
<?php include __DIR__ . '/include/community.php'; ?>
<!-- Include footer -->
<?php include '../include/footer.php'; ?>

<!-- Shared JavaScript files -->
<script src="../js/vina.js"></script>
<script src="../js/navbar.js"></script>
</body>
</html>
