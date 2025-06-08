<!DOCTYPE html>
<html lang="en">
<?php
$root_path = '../';
$page_title = "Notification - Vina Network";
$page_og_url = "https://www.vina.network/notification/";
$page_canonical = "https://www.vina.network/notification/";
$page_css = ['notification.css'];
include '../include/header.php';
?>

<body>
    <?php include '../include/navbar.php'; ?>

    <section class="n-1">
        <div class="n-2">
            <h1><i class="fas fa-tools"></i></h1>
            <h1>Products Under Development</h1>
            <p>We’re sorry, but our products are currently under development. Our team is working hard to bring you the best experience. Stay tuned for updates!</p>
            <a href="https://www.vina.network/" class="cta-button">Back to Home</a>
        </div>
    </section>

    <?php include '../include/footer.php'; ?>

    <script src="../js/vina.js"></script>
    <script src="../js/navbar.js"></script>

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
