<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Function to log messages
function logMessage($message) {
    $logDir = __DIR__ . '/../logs/accounts';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/accounts.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['publicKey']) || !isset($input['message']) || !isset($input['signature'])) {
        throw new Exception('Invalid input data');
    }
    
    $publicKey = $input['publicKey'];
    $message = $input['message'];
    $signature = $input['signature'];
    
    logMessage("Authentication attempt for public key: $publicKey");
    
    // In a real implementation, you would verify the signature here
    // For this example, we'll assume the signature is valid
    
    // Connect to database
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if wallet exists
    $stmt = $db->prepare("SELECT * FROM wallet_accounts WHERE public_key = :publicKey");
    $stmt->bindParam(':publicKey', $publicKey);
    $stmt->execute();
    
    $isNewAccount = false;
    
    if ($stmt->rowCount() === 0) {
        // Create new account
        $insert = $db->prepare("INSERT INTO wallet_accounts (public_key, last_login) VALUES (:publicKey, NOW())");
        $insert->bindParam(':publicKey', $publicKey);
        $insert->execute();
        $isNewAccount = true;
        logMessage("New account created for public key: $publicKey");
    } else {
        // Update last login
        $update = $db->prepare("UPDATE wallet_accounts SET last_login = NOW() WHERE public_key = :publicKey");
        $update->bindParam(':publicKey', $publicKey);
        $update->execute();
        logMessage("Existing account logged in: $publicKey");
    }
    
    // Store public key in session
    session_start();
    $_SESSION['wallet_public_key'] = $publicKey;
    
    echo json_encode([
        'success' => true,
        'isNewAccount' => $isNewAccount
    ]);
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
