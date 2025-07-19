<?php
function getDB() {
    $host = "localhost";
    $db = "vina";
    $user = "root";
    $pass = "your_password";
    return new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
}
