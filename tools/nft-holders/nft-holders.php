<?php
/*
 * tools/nft-holders/nft-holders.php - NFT Holders Checker Tool
 *
 * This file provides a web interface to check the number of holders and total NFTs
 * for a given Solana NFT Collection using the Helius API. It includes:
 * - CSRF protection
 * - API pagination and caching
 * - Rate limiting per IP
 * - Compressed cache management
 * - Export functionality (CSV/JSON)
 * - Logging and error handling
 *
 * Used by: tools/tools-load.php (dynamic module loader)
 * Part of: Vina Network Tool Suite
 */

// Disable display of errors in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Define constants to mark script entry
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Load bootstrap dependencies
$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-holders: bootstrap.php not found at $bootstrap_path", 'nft_holders_log.txt', 'ERROR');
    die('Error: bootstrap.php not found');
}
require_once $bootstrap_path;

// Start session and configure error logging
session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

// Rate limiting: 5 requests per minute per IP
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "rate_limit:$ip";
$rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['count'] : 0;
$rate_limit_time = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['time'] : 0;
if (time() - $rate_limit_time > 60) {
    $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
    log_message("nft-holders: Reset rate limit for IP=$ip, count=1", 'nft_holders_log.txt');
} elseif ($rate_limit_count >= 5) {
    log_message("nft-holders: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'nft_holders_log.txt', 'ERROR');
    die("<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>");
} else {
    $_SESSION[$rate_limit_key]['count']++;
    log_message("nft-holders: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'nft_holders_log.txt');
}

// Define cache directory and file
$cache_dir = __DIR__ . '/cache';
$cache_file = $cache_dir . '/nft_holders_cache.json';

// Ensure cache directory exists
if (!is_dir($cache_dir)) {
    if (!mkdir($cache_dir, 0755, true)) {
        log_message("nft-holders: Failed to create cache directory at $cache_dir", 'nft_holders_log.txt', 'ERROR');
        die('Error: Unable to create cache directory');
    }
    log_message("nft-holders: Created cache directory at $cache_dir", 'nft_holders_log.txt');
}

// Ensure cache file exists
if (!file_exists($cache_file)) {
    if (file_put_contents($cache_file, gzcompress(json_encode([]), 6)) === false) {
        log_message("nft-holders: Failed to create compressed cache file at $cache_file", 'nft_holders_log.txt', 'ERROR');
        die('Error: Unable to create cache file');
    }
    chmod($cache_file, 0644);
    log_message("nft-holders: Created compressed cache file at $cache_file", 'nft_holders_log.txt');
}

// Check cache file permissions
if (!is_writable($cache_file)) {
    log_message("nft-holders: Cache file $cache_file is not writable", 'nft_holders_log.txt', 'ERROR');
    die('Error: Cache file is not writable');
}

// Set up page variables and include layout headers
$root_path = '../../';
$page_title = 'Check NFT Holders - Vina Network';
$page_description = 'Check NFT holders for a Solana collection address.';
$page_css = ['../../css/vina.css', '../tools.css'];
include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';

// Include tools API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-holders: tools-api.php not found at $api_helper_path", 'nft_holders_log.txt', 'ERROR');
    die('Internal Server Error: Missing tools-api.php');
}
log_message("nft-holders: Including tools-api.php from $api_helper_path", 'nft_holders_log.txt');
include $api_helper_path;

log_message("nft-holders: Loaded at " . date('Y-m-d H:i:s'), 'nft_holders_log.txt');
?>
