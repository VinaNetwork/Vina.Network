<?php
// ============================================================================
// File: 404.php
// Description: Custom 404 error page for Vina Network
// Created by: Vina Network
// ============================================================================

ob_start();
http_response_code(404);
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/';
require_once $root_path . 'bootstrap.php';

// Log 404 error
$request_uri = $_SERVER['REQUEST_URI'];
log_message("404 Error: Page not found - REQUEST_URI: $request_uri", 'app.log', 'logs', 'ERROR');

// Set HTTP status code
http_response_code(404);

// SEO meta
$page_title = "Page Not Found - Vina Network";
$page_description = "The page you are looking for does not exist.";
$page_keywords = "404, page not found, Vina Network";
$page_css = ['css/404.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="container-404">
    <div class="content-404">
        <i class="fas fa-exclamation-triangle"></i>
        <h1>404</h1>
        <div class="alert">
            <p><strong>Error:</strong> Page Not Found.</p>
        </div>
        <a href="/" class="cta-button">Back to Home</a>
    </div>
</div>
<?php include $root_path . 'include/community.php'; ?>
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts -->
<script src="/js/vina.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
