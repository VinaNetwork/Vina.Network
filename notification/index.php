<!-- 
|--------------------------------------------------------------------------
| File: notification/notification.php
| Description: Notification page for products currently under development.
| This page informs users that certain Vina Network products are not yet available.
| It includes the global header, navbar, footer, and uses shared JS/CSS assets.
|--------------------------------------------------------------------------
-->
<!DOCTYPE html>
<html lang="en">

<?php
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
    <section class="n-1">
        <div class="n-2">
            <i class="fas fa-tools"></i>
            <h1>Products Under Development</h1>
            <p>We’re sorry, but our products are currently under development. Our team is working hard to bring you the best experience. Stay tuned for updates!</p>
            <a href="https://www.vina.network/" class="cta-button">Back to Home</a>
        </div>
    </section>

    <!-- Include shared footer -->
    <?php include '../include/footer.php'; ?>

    <!-- Shared JavaScript files -->
    <script src="../js/vina.js"></script>
    <script src="../js/navbar.js"></script>

    <!-- Schema.org structured data for SEO -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebPage",
            "url": "https://www.vina.network/notification/",
            "name": "Notification - Vina Network"
        }
    </script>
</body>
</html>
