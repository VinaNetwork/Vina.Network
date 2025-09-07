<?php
// ============================================================================
// File: notification/notification.php
// Description: Notification page for products currently under development.
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../';
require_once $root_path . 'bootstrap.php';

// Set path and SEO metadata for this page
$page_title = "Notification - Vina Network";
$page_css = ['/notification/notification.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<!-- Include shared top navigation bar -->
<?php include $root_path . 'include/navbar.php'; ?>

<!-- Notification section showing under-construction message -->
<section class="notification">
    <div class="notification-content">
        <i class="fas fa-tools"></i>
        <h1>Products Under Development</h1>
        <p>We’re sorry, but our products are currently under development. Our team is working hard to bring you the best experience. Stay tuned for updates!</p>
        <a href="/" class="cta-button">Back to Home</a>
    </div>
</section>

<!-- Include community -->
<?php include $root_path . 'include/community.php'; ?>
<!-- Include footer -->
<?php include $root_path . 'include/footer.php'; ?>

<!-- Shared JavaScript files -->
<script src="../js/vina.js"></script>
</body>
</html>
