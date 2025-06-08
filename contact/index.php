<!DOCTYPE html>
<html lang="en">
<?php
$root_path = '../';
$page_title = "Vina Network - Contact Us";
$page_description = "Get in touch with Vina Network. Reach out via X, Telegram, or Email. We're here to assist you!";
$page_keywords = "Vina Network, contact, X, Telegram, Email, support, Web3, cryptocurrency";
$page_og_title = "Vina Network - Contact Us";
$page_og_description = "Contact Vina Network via X, Telegram, or Email. Join our community today!";
$page_og_url = "https://www.vina.network/contact/";
$page_canonical = "https://www.vina.network/contact/";
$page_css = ['contact.css'];
include '../include/header.php';
?>

<body>
    <!-- Include Header -->
    <?php include '../include/navbar.php'; ?>

    <!-- Contact Info Section -->
    <section class="c-1">
        <div class="c-2">
            <h1 class="fade-in" data-delay="0">Contact Us</h1>
            <p class="fade-in" data-delay="200">We'd love to hear from you! Reach out to Vina Network via X, Telegram, or Email.</p>
            <div class="contact-methods">
                <div class="method-card fade-in" data-delay="200">
                    <i class="fab fa-x-twitter"></i>
                    <h2>X (Twitter)</h2>
                    <p>Follow us and send a DM!</p>
                    <a href="https://x.com/Vina_Network" target="_blank" rel="nofollow noopener noreferrer">Follow Now</a>
                </div>
                <div class="method-card fade-in" data-delay="400">
                    <i class="fab fa-telegram-plane"></i>
                    <h2>Telegram</h2>
                    <p>Join our community on Telegram!</p>
                    <a href="https://t.me/Vina_Network" target="_blank" rel="nofollow noopener noreferrer">Join Now</a>
                </div>
                <div class="method-card fade-in" data-delay="600">
                    <i class="fas fa-envelope"></i>
                    <h2>Email</h2>
                    <p>Send us an email for inquiries.</p>
                    <a href="mailto:contact@vina.network">Send Now</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Include Footer -->
    <?php include '../include/footer.php'; ?>

    <script src="../js/vina.js"></script>
    <script src="../js/navbar.js"></script>

    <script type="application/ld+json">
        {
        "@context": "https://schema.org",
        "@type": "ContactPage",
        "url": "https://www.vina.network/contact/",
        "name": "Contact Vina Network"
        }
    </script>
</body>
</html>
