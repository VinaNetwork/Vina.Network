<?php
// ============================================================================
// File: config/db.php
// Description: Database connection management for Vina Network
// Created by: Vina Network
// ============================================================================

require_once __DIR__ . '/bootstrap.php';

// Connect database
function get_db_connection() {
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
        log_message("Database connection established", 'database.log', 'make-market', 'INFO');
        return $pdo;
    } catch (PDOException $e) {
        log_message("Database connection failed: {$e->getMessage()}", 'database.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit;
    }
}
?>
