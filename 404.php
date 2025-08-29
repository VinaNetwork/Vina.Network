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
$session_id = session_id() ?: 'none';
$log_context = [
    'endpoint' => '404',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown',
    'session_id' => $session_id,
    'error_message' => isset($_SESSION['error_message']) ? $_SESSION['error_message'] : 'none'
];
log_message("404 Error: Page not found - REQUEST_URI: $request_uri, session_id=$session_id, error_message=" . (isset($_SESSION['error_message']) ? $_SESSION['error_message'] : 'none'), 'app.log', 'logs', 'ERROR', $log_context);

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
$page_css = ['/css/404.css'];
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
        <p><strong>Error:</strong> Page Not Found.</p>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); // Xóa thông báo sau khi hiển thị ?>
        <?php else: ?>
            <p>The page you are looking for does not exist.</p>
        <?php endif; ?>
        <a href="/" class="cta-button">Back to Home</a>
    </div>
</div>

<?php include $root_path . 'include/community.php'; ?>
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts -->
<script src="js/vina.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
