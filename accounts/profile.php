<?php
// File: accounts/profile.php
session_start();
if (!isset($_SESSION['public_key'])) {
    header("Location: index.php");
    exit();
}

require_once '../config/config.php';

// Kết nối database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$public_key = $_SESSION['public_key'];
$result = $conn->query("SELECT * FROM accounts WHERE public_key = '$public_key'");
$account = $result->fetch_assoc();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="acc.css">
</head>
<body>
    <div class="container">
        <h1>Account Profile</h1>
        <p><strong>Public Key:</strong> <?php echo htmlspecialchars($account['public_key']); ?></p>
        <p><strong>Created At:</strong> <?php echo htmlspecialchars($account['created_at']); ?></p>
        <p><strong>Last Login:</strong> <?php echo htmlspecialchars($account['last_login']); ?></p>
        <a href="logout.php">Logout</a>
    </div>
</body>
</html>
