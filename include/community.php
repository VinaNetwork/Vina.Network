<?php
// ============================================================================
// File: include/community.php
// Description: Reusable section for displaying links to Vina Network's social media.
// Created by: Vina Network
// ============================================================================

// Load css
if (!isset($page_css)) {
    $page_css = [];
}
$page_css[] = '/css/community.css';
?>

<!-- Join Our Community -->
<section class="community-content">
	<h2>Join Our Community</h2>
	<p>Be a part of the Vina Network revolution. Connect with us and stay updated!</p>

	<div class="community-link">
		<a href="https://x.com/Vina_Network" target="_blank" class="cta-button">
			<i class="fab fa-x-twitter"></i> Twitter
		</a>
		<a href="https://t.me/Vina_Network" target="_blank" class="cta-button">
			<i class="fab fa-telegram"></i> Telegram
		</a>
		<a href="https://discord.gg/wm2H5epF" target="_blank" class="cta-button">
			<i class="fab fa-discord"></i> Discord
		</a>
	</div>
</section>
