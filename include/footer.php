<?php
/*
|--------------------------------------------------------------------------
| File: include/footer.php
| Description: Global footer used across all pages of Vina Network.
| Includes:
| - Company description
| - Quick navigation links
| - Social media follow links
| - SEO structured data (JSON-LD for Organization)
|--------------------------------------------------------------------------
*/
?>

<!-- Footer Section -->
<footer>
    <!-- Footer Content Area -->
    <div class="footer-1">
        <!-- Company Overview -->
        <div class="footer-2">
            <h4>Vina Network</h4>
            <p>A leading Web3 ecosystem focused on cryptocurrencies, stablecoins, and DeFi.</p>
        </div>

        <!-- Quick Navigation Links -->
        <div class="footer-2">
            <h4>Quick Links</h4>
            <ul class="footer-3">
                <li><a href="https://www.vina.network/"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="https://kimo.vina.network/token/"><i class="fas fa-coins"></i> Kimo</a></li>
                <li><a href="https://www.vina.network/contact/"><i class="fas fa-envelope"></i> Contact</a></li>
            </ul>
        </div>

        <!-- Social Media Links -->
        <div class="footer-2">
            <h4>Follow Us</h4>
            <div class="footer-4">
                <a href="https://x.com/Vina_Network" target="_blank" rel="nofollow noopener noreferrer">
                    <i class="fab fa-x-twitter"></i> Twitter
                </a>
                <a href="https://t.me/VinaNetworks" target="_blank" rel="nofollow noopener noreferrer">
                    <i class="fab fa-telegram-plane"></i> Telegram
                </a>
            </div>
        </div>
    </div>

    <!-- Copyright -->
    <div class="footer-5">
        <p>© 2025 Vina Network. All rights reserved.</p>
    </div>

    <!-- Structured Data (Schema.org JSON-LD for SEO) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Vina Network",
        "url": "https://vina.network",
        "logo": "https://vina.network/img/logo.png",
        "sameAs": [
            "https://x.com/Vina_Network",
            "https://t.me/VinaNetworks"
        ],
        "contactPoint": {
            "@type": "ContactPoint",
            "email": "contact@vina.network",
            "contactType": "Customer Support"
        }
    }
    </script>
</footer>
