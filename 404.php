<?php
// ============================================================================
// File: 404.php
// Description: Custom 404 error page for Vina Network
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = './';
require_once $root_path . 'config/bootstrap.php';

// Add Security Headers
$csp_base = rtrim(BASE_URL, '/');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' $csp_base; connect-src 'self' $csp_base; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header("Access-Control-Allow-Origin: $csp_base");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Log 404 error
$request_uri = $_SERVER['REQUEST_URI'];
log_message("404 Error: Page not found - REQUEST_URI: $request_uri", 'make-market.log', 'make-market', 'ERROR');

// Set HTTP status code
http_response_code(404);

// SEO meta
$page_title = "Page Not Found - Vina Network";
$page_description = "The page you are looking for does not exist.";
$page_keywords = "404, page not found, Vina Network";
$page_og_title = "Page Not Found";
$page_og_description = "The page you are looking for does not exist.";
$page_og_url = BASE_URL . "404.php";
$page_canonical = BASE_URL . "404.php";
$page_css = ['css/404.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="404-container">
    <div class="404-content">
        <i class="fas fa-exclamation-triangle"></i>
        <h1>404</h1>
        <p><strong>Error:</strong> Page Not Found.</p>
        <a href="/" class="cta-button">Back to Home</a>
    </div>
</div>

<?php include $root_path . 'include/community.php'; ?>
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts -->
<script src="js/vina.js"></script>
<script src="js/navbar.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
