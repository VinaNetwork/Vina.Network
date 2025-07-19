<?php
// config/db.php
function getDB() {
    $host = "localhost";
    $db = "vina";
    $user = "root";
    $pass = "your_password"; // Thay bằng mật khẩu thực tế
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
