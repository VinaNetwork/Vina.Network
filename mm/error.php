<?php
// ============================================================================
// File: mm/error.php
// Description: Custom error page for Make Market
// Created by: Vina Network
// ============================================================================

ob_start();
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'mm/bootstrap.php';

// Log error
$request_uri = $_SERVER['REQUEST_URI'];
$session_id = session_id() ?: 'none';
$log_context = [
    'endpoint' => 'error',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown',
    'session_id' => $session_id,
    'error_message' => isset($_SESSION['error_message']) ? $_SESSION['error_message'] : 'none'
];
log_message("Error: Page not found - REQUEST_URI: $request_uri, session_id=$session_id, error_message=" . (isset($_SESSION['error_message']) ? $_SESSION['error_message'] : 'none'), 'make-market.log', 'make-market', 'ERROR', $log_context);

// Set HTTP status code
http_response_code(404);

// SEO meta
$page_title = "Error - Vina Network";
$page_description = "An error occurred while accessing the Make Market tool.";
$page_keywords = "error, make market, Vina Network";
$page_og_title = "Error: Make Market";
$page_og_description = "An error occurred while accessing the Make Market tool.";
$page_og_url = BASE_URL . "mm/error";
$page_canonical = BASE_URL . "mm/error";
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
        <h1>Error</h1>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); // Xóa thông báo sau khi hiển thị ?>
        <?php endif; ?>
        <a href="/mm" class="cta-button">Back to Make Market</a>
    </div>
</div>

<?php include $root_path . 'include/community.php'; ?>
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts -->
<script src="/js/vina.js?t=<?php echo time(); ?>" onerror="console.error('Failed to load /js/vina.js')"></script>
</body>
</html>
<?php ob_end_flush(); ?>
