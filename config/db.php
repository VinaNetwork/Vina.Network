<?php
// ============================================================================
// File: config/db.php
// Description: Database connection management for Vina Network using Singleton Pattern
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Database connection class
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Validate configuration constants
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
            log_message("Database configuration constants are missing", 'database.log', 'logs', 'ERROR');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database configuration is incomplete']);
            exit;
        }

        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_PERSISTENT => true, // Enable persistent connections
                    PDO::ATTR_TIMEOUT => 5 // Set connection timeout to 5 seconds
                ]
            );
            log_message("Database connection established", 'database.log', 'logs', 'INFO');
        } catch (PDOException $e) {
            log_message("Database connection failed: {$e->getMessage()}", 'database.log', 'logs', 'ERROR');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit;
        }
    }

    // Get singleton instance
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }
}

// Function for backward compatibility
function get_db_connection(): PDO {
    return Database::getInstance();
}
?>
