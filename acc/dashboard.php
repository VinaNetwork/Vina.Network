<?php
session_start();
if (!isset($_SESSION['wallet'])) {
    header('Location: index.php');
    exit;
}

$wallet = $_SESSION['wallet'];
$userFile = __DIR__ . "/users/$wallet.json";

$userData = file_exists($userFile) ? json_decode(file_get_contents($userFile), true) : [];
?>
<!DOCTYPE html>
<html>
<head><title>Dashboard</title></head>
<body>
  <h2>Hello: <?php echo htmlspecialchars($wallet); ?></h2>
  <ul>
    <li><strong>ID:</strong> <?= $userData['id'] ?? 'N/A' ?></li>
    <li><strong>Created at:</strong> <?= $userData['created_at'] ?? 'N/A' ?></li>
    <li><strong>Last login:</strong> <?= $userData['last_login'] ?? 'N/A' ?></li>
  </ul>
  <a href="logout.php">Logout</a>
</body>
</html>
