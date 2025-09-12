<?php
// ============================================================================
// File: contact/index.php
// Description: "Contact Us" page for Vina Network.
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../';
require_once $root_path . 'bootstrap.php';

// Head Section (Meta, Styles, Title) is included via header.php
$page_title = "Contact Us - Vina Network";
$page_description = "Get in touch with Vina Network. Reach out via X, Telegram, or Email. We're here to assist you!";
$page_keywords = "Vina Network, contact, X, Telegram, Email, support, Web3, cryptocurrency";
$page_css = ['/contact/contact.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
	<!-- Navigation Bar -->
	<?php include $root_path . 'include/navbar.php'; ?>
	
	<!-- Contact Info -->
	<div class="contact">
		<div class="contact-content">
			<h1 class="fade-in" data-delay="0">Contact Us</h1>
			<p class="fade-in" data-delay="200">
				We'd love to hear from you! Reach out to Vina Network via X, Telegram, or Email.
			</p>
		
			<div class="contact-table">
				<!-- X (Twitter) Contact Option -->
				<div class="contact-item fade-in" data-delay="200">
					<i class="fab fa-x-twitter"></i>
					<h2>X (Twitter)</h2>
					<p>Follow us and send a DM!</p>
					<a href="https://x.com/Vina_Network" target="_blank" rel="nofollow noopener noreferrer">Follow Now</a>
				</div>
		
				<!-- Telegram Contact Option -->
				<div class="contact-item fade-in" data-delay="300">
					<i class="fab fa-telegram-plane"></i>
					<h2>Telegram</h2>
					<p>Join our community on Telegram!</p>
					<a href="https://t.me/Vina_Network" target="_blank" rel="nofollow noopener noreferrer">Join Now</a>
				</div>
		
				<!-- Email Contact Option -->
				<div class="contact-item fade-in" data-delay="400">
					<i class="fas fa-envelope"></i>
					<h2>Email</h2>
					<p>Send us an email for inquiries.</p>
					<a href="mailto:contact@vina.network">Send Now</a>
				</div>
			</div>
		</div>
	</div>
	<?php include $root_path . 'include/footer.php'; ?>
	
	<!-- Scripts -->
	<script src="/js/vina.js"></script>
	<script src="/contact/contact.js"></script>
</body>
</html>
