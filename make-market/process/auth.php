<?php
// ============================================================================
// File: make-market/process/auth.php
// Description: Centralized authentication and CSRF validation for Make Market
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
try {
    log_message("Attempting to load bootstrap.php", 'make-market.log', 'make-market', 'DEBUG');
    require_once $root_path . 'config/bootstrap.php';
    log_message("bootstrap.php loaded successfully", 'make-market.log', 'make-market', 'DEBUG');
    require_once $root_path . 'make-market/process/network.php';
    log_message("network.php loaded successfully", 'make-market.log', 'make-market', 'DEBUG');
} catch (Exception $e) {
    log_message("Failed to load required files: {$e->getMessage()}, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Initialize session
 * @return void
 */
function initialize_auth() {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_secure' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict'
            ]);
        }
        log_message("Session initialized, session_id=" . session_id() . ", user_id=" . ($_SESSION['user_id'] ?? 'none') . ", public_key=" . ($_SESSION['public_key'] ?? 'none'), 'make-market.log', 'make-market', 'DEBUG');
    } catch (Exception $e) {
        log_message("Session initialization failed: {$e->getMessage()}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Session initialization error'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Check if request is AJAX
 * @return bool
 */
function check_ajax_request() {
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        log_message("Non-AJAX request rejected, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
        return false;
    }
    return true;
}

/**
 * Validate CSRF token
 * @param string $token CSRF token from request header
 * @return bool
 */
function validate_csrf($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        log_message("Invalid CSRF token, provided=$token, session=" . ($_SESSION['csrf_token'] ?? 'none') . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
        return false;
    }
    log_message("CSRF token validated: $token, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
    return true;
}

/**
 * Check if user is authenticated
 * @return bool
 */
function check_user_auth() {
    initialize_auth(); // Ensure session is started
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['public_key'])) {
        log_message("Unauthorized access attempt: user_id=" . ($_SESSION['user_id'] ?? 'none') . ", public_key=" . ($_SESSION['public_key'] ?? 'none') . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        http_response_code(401); // Use 401 for unauthenticated
        echo json_encode(['status' => 'error', 'message' => 'Please log in to continue'], JSON_UNESCAPED_UNICODE);
        return false;
    }
    log_message("Auth check passed: user_id={$_SESSION['user_id']}, public_key={$_SESSION['public_key']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
    return true;
}

/**
 * Check if transaction belongs to the authenticated user
 * @param PDO $pdo Database connection
 * @param int $transaction_id Transaction ID
 * @return bool
 */
function check_transaction_ownership($pdo, $transaction_id) {
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM make_market WHERE id = ? AND user_id = ? AND network = ?");
        $stmt->execute([$transaction_id, $_SESSION['user_id'], SOLANA_NETWORK]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transaction) {
            log_message("Transaction not found, unauthorized, or network mismatch: ID=$transaction_id, session_user_id=" . ($_SESSION['user_id'] ?? 'none') . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Transaction not found, unauthorized, or network mismatch'], JSON_UNESCAPED_UNICODE);
            return false;
        }
        return true;
    } catch (PDOException $e) {
        log_message("Database query failed: {$e->getMessage()}, network=" . SOLANA_NETWORK . ", session_user_id=" . ($_SESSION['user_id'] ?? 'none'), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error checking transaction ownership'], JSON_UNESCAPED_UNICODE);
        return false;
    }
}

/**
 * Perform full authentication check (AJAX, CSRF, user, and optional transaction ownership)
 * @param PDO|null $pdo Database connection (required for transaction ownership check)
 * @param int|null $transaction_id Transaction ID (optional)
 * @return bool
 */
function perform_auth_check($pdo = null, $transaction_id = null) {
    if (!check_ajax_request()) return false;
    if (!check_user_auth()) return false; // Check user auth before CSRF
    if (!validate_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) return false;
    if ($pdo && $transaction_id !== null) {
        if (!check_transaction_ownership($pdo, $transaction_id)) return false;
    }
    log_message("Authentication successful: session_user_id=" . ($_SESSION['user_id'] ?? 'none') . 
                ($transaction_id ? ", transaction_id=$transaction_id" : "") . 
                ", network=" . SOLANA_NETWORK, 
                'make-market.log', 'make-market', 'INFO');
    return true;
}
?>
