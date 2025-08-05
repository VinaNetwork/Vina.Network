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
require_once $root_path . 'config/config.php';

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
$page_og_url = BASE_URL . "404";
$page_canonical = BASE_URL . "404";
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="process-container">
    <div class="process-content">
        <h1><i class="fas fa-exclamation-triangle"></i> Page Not Found</h1>
        <div id="process-result" class="alert alert-danger">
            <strong>Error:</strong> Trang không tồn tại
        </div>
        <a href="/make-market/history" class="btn btn-primary">View Transaction History</a>
        <a href="/" class="btn btn-secondary">Back to Home</a>
    </div>
</div>
<?php include $root_path . 'include/footer.php'; ?>
</body>
</html>
<?php ob_end_flush(); ?>
