document.addEventListener('DOMContentLoaded', () => {
    const connectButton = document.getElementById('connectWallet');
    const walletStatus = document.getElementById('walletStatus');
    
    // Check if Phantom wallet is installed
    if (window.solana && window.solana.isPhantom) {
        connectButton.addEventListener('click', connectWallet);
    } else {
        connectButton.textContent = 'Get Phantom Wallet';
        connectButton.addEventListener('click', () => {
            window.open('https://phantom.app/', '_blank');
        });
        walletStatus.textContent = 'Phantom wallet is not installed. Please install it to continue.';
    }

    async function connectWallet() {
        try {
            connectButton.disabled = true;
            walletStatus.textContent = 'Connecting to wallet...';
            
            // Connect to wallet
            const response = await window.solana.connect();
            const publicKey = response.publicKey.toString();
            
            walletStatus.textContent = 'Wallet connected! Verifying...';
            
            // Request user to sign a message for verification
            const message = `VinaNetwork Authentication: ${Date.now()}`;
            const encodedMessage = new TextEncoder().encode(message);
            const { signature } = await window.solana.signMessage(encodedMessage, 'utf8');
            
            // Convert signature to hex
            const signatureHex = Array.from(signature)
                .map(b => b.toString(16).padStart(2, '0'))
                .join('');
            
            // Send to server for verification
            const verificationResponse = await fetch('auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    publicKey,
                    message,
                    signature: signatureHex
                })
            });
            
            const result = await verificationResponse.json();
            
            if (result.success) {
                if (result.isNewAccount) {
                    walletStatus.textContent = 'Account created successfully! Redirecting...';
                } else {
                    walletStatus.textContent = 'Login successful! Redirecting...';
                }
                window.location.href = 'profile.php';
            } else {
                walletStatus.textContent = 'Authentication failed: ' + result.error;
                connectButton.disabled = false;
            }
        } catch (error) {
            console.error('Wallet connection error:', error);
            walletStatus.textContent = 'Error: ' + error.message;
            connectButton.disabled = false;
        }
    }
});
