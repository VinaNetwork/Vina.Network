<?php
// ============================================================================
// File: mm/error.php
// Description: Custom error page for Make Market
// Created by: Vina Network
// ============================================================================

ob_start();
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

// SEO meta
$page_title = "Error - Make Market page";
$page_description = "An error occurred while accessing the Make Market.";
$page_keywords = "error, make market, Vina Network";
$page_css = ['/css/error.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include $root_path . 'include/header.php'; ?>
<body>
<?php include $root_path . 'include/navbar.php'; ?>
<div class="container-error">
    <div class="content-error">
        <i class="fas fa-exclamation-triangle"></i>
        <h1>Error</h1>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); // Delete notification after display ?>
        <?php endif; ?>
        <a href="/mm" class="cta-button">Back to Make Market</a>
    </div>
</div>

<?php include $root_path . 'include/community.php'; ?>
<?php include $root_path . 'include/footer.php'; ?>

<!-- Scripts - Source code -->
<script src="/js/vina.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
