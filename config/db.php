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
function get_db_connection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
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
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );
        log_message("Database connection established", 'database.log', 'logs', 'INFO');
        return $pdo;
    } catch (PDOException $e) {
        log_message("Database connection failed: {$e->getMessage()}", 'database.log', 'logs', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit;
    }
}
?>
