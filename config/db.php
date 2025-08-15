<?php
// ============================================================================
// File: config/db.php
// Description: Database connection management for Vina Network
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Connect database
function get_db_connection(string $network = null): ?PDO {
    // Use SOLANA_NETWORK if no network is provided
    if ($network === null) {
        if (!defined('SOLANA_NETWORK')) {
            log_message('Missing SOLANA_NETWORK constant', 'database.log', 'logs', 'ERROR');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server configuration error']);
            exit;
        }
        $network = SOLANA_NETWORK;
    }

    static $connections = [];

    $connection_key = $network;
    if (isset($connections[$connection_key]) && $connections[$connection_key] instanceof PDO) {
        // Check if connection is still alive
        try {
            $connections[$connection_key]->query('SELECT 1');
            return $connections[$connection_key];
        } catch (PDOException $e) {
            log_message("Database connection lost for $network: {$e->getMessage()}", 'database.log', 'logs', 'ERROR');
            unset($connections[$connection_key]);
        }
    }

    // Check required database constants
    $required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    foreach ($required_constants as $const) {
        if (!defined($const)) {
            log_message("Missing database configuration constant: $const", 'database.log', 'logs', 'ERROR');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server configuration error']);
            exit;
        }
    }

    try {
        $connections[$connection_key] = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );
        log_message("Database connection established for $network", 'database.log', 'logs', 'INFO');
        return $connections[$connection_key];
    } catch (PDOException $e) {
        log_message("Database connection failed for $network: {$e->getMessage()}", 'database.log', 'logs', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit;
    }
}

// Close database connection (optional, for long-running processes)
function close_db_connection(string $network = null): void {
    // Use SOLANA_NETWORK if no network is provided
    if ($network === null) {
        if (!defined('SOLANA_NETWORK')) {
            log_message('Missing SOLANA_NETWORK constant', 'database.log', 'logs', 'ERROR');
            return;
        }
        $network = SOLANA_NETWORK;
    }

    static $connections = [];
    if (isset($connections[$network])) {
        unset($connections[$network]);
        log_message("Database connection closed for $network", 'database.log', 'logs', 'INFO');
    }
}
?>
