<?php
require_once __DIR__ . '/../config/config.php';

session_start();

if (!isset($_SESSION['wallet_public_key'])) {
    header('Location: index.php');
    exit;
}

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $publicKey = $_SESSION['wallet_public_key'];
    
    $stmt = $db->prepare("SELECT * FROM wallet_accounts WHERE public_key = :publicKey");
    $stmt->bindParam(':publicKey', $publicKey);
    $stmt->execute();
    
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        throw new Exception('Account not found');
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Profile</title>
    <link rel="stylesheet" href="acc.css">
</head>
<body>
    <div class="wallet-container">
        <h1>Account Profile</h1>
        
        <div class="profile-info">
            <p><strong>Wallet Address:</strong> <?php echo htmlspecialchars($account['public_key']); ?></p>
            <p><strong>Account Created:</strong> <?php echo date('M j, Y g:i a', strtotime($account['created_at'])); ?></p>
            <p><strong>Last Login:</strong> <?php echo date('M j, Y g:i a', strtotime($account['last_login'])); ?></p>
        </div>
        
        <button onclick="window.location.href='index.php'" class="connect-button">Switch Wallet</button>
    </div>
</body>
</html>
