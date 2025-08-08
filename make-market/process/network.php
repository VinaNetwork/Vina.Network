<?php
// ============================================================================
// File: make-market/process/network.php
// Description: Centralized network configuration for Solana testnet and mainnet
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

$rootPath = realpath(__DIR__ . '/../../../');
if (file_exists($rootPath . '/.env')) {
    require_once $rootPath . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable($rootPath);
    $dotenv->load();
}

// Define SOLANA_NETWORK from environment or default to testnet
define('SOLANA_NETWORK', getenv('SOLANA_NETWORK') ?: 'testnet');

// Validate SOLANA_NETWORK
if (!in_array(SOLANA_NETWORK, ['testnet', 'mainnet'])) {
    error_log("Invalid SOLANA_NETWORK: " . SOLANA_NETWORK);
    die("Server configuration error: Invalid SOLANA_NETWORK");
}

// Define network-specific constants
define('RPC_ENDPOINT_TESTNET', 'https://api.testnet.solana.com');
define('RPC_ENDPOINT_MAINNET', defined('HELIUS_API_KEY') && !empty(HELIUS_API_KEY) 
    ? 'https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY 
    : '');
define('RPC_ENDPOINT', SOLANA_NETWORK === 'testnet' ? RPC_ENDPOINT_TESTNET : RPC_ENDPOINT_MAINNET);
define('EXPLORER_URL_TESTNET', 'https://solana.fm/tx/');
define('EXPLORER_URL_MAINNET', 'https://solscan.io/tx/');
define('EXPLORER_QUERY_TESTNET', '?cluster=testnet');
define('EXPLORER_QUERY_MAINNET', '');
define('EXPLORER_URL', SOLANA_NETWORK === 'testnet' ? EXPLORER_URL_TESTNET : EXPLORER_URL_MAINNET);
define('EXPLORER_QUERY', SOLANA_NETWORK === 'testnet' ? EXPLORER_QUERY_TESTNET : EXPLORER_QUERY_MAINNET);
?>
