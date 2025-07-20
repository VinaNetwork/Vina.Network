<?php
// config/db.php
require_once 'bootstrap.php';

function getDB() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        log_message("Database: Connection successful", 'acc_auth.txt', 'accounts', 'INFO');
        return $pdo;
    } catch (PDOException $e) {
        log_message("Database: Connection failed: " . $e->getMessage(), 'acc_auth.txt', 'accounts', 'ERROR');
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
