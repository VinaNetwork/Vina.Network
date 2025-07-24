document.getElementById('connect-wallet').addEventListener('click', async () => {
    const walletInfo = document.getElementById('wallet-info');
    const publicKeySpan = document.getElementById('public-key');
    const statusSpan = document.getElementById('status');

    async function logToServer(message, level = 'INFO') {
        try {
            const logData = {
                timestamp: new Date().toISOString(),
                level: level,
                message: message,
                userAgent: navigator.userAgent,
                url: window.location.href
            };
            const response = await fetch('/accounts/client-log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(logData)
            });
            if (!response.ok) {
                console.error(`Failed to send log to server: HTTP ${response.status}`);
            }
        } catch (error) {
            console.error('Failed to send log to server:', error.message);
        }
    }

    try {
        if (window.solana && window.solana.isPhantom) {
            statusSpan.textContent = 'Connecting wallet...';
            await logToServer('Initiating Phantom wallet connection', 'INFO');
            const response = await window.solana.connect();
            const publicKey = response.publicKey.toString();
            publicKeySpan.textContent = publicKey;
            walletInfo.style.display = 'block';
            statusSpan.textContent = 'Wallet connected! Signing message...';
            await logToServer(`Wallet connected, publicKey: ${publicKey}`, 'INFO');

            const timestamp = Date.now();
            const message = `Verify login for Vina Network at ${timestamp}`;
            const encodedMessage = new TextEncoder().encode(message);
            await logToServer(`Message to sign: ${message}, hex: ${Array.from(encodedMessage).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

            // Sign message as raw bytes
            const signature = await window.solana.signMessage(encodedMessage, 'utf8');
            const signatureBytes = new Uint8Array(signature.signature);
            if (signatureBytes.length !== 64) {
                throw new Error(`Invalid signature length: ${signatureBytes.length} bytes, expected 64 bytes`);
            }
            const signatureBase64 = btoa(String.fromCharCode(...signatureBytes));
            await logToServer(`Signature created, base64: ${signatureBase64}, hex: ${Array.from(signatureBytes).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

            // Kiểm tra message trước khi gửi
            await logToServer(`Message sent: ${message}, hex: ${Array.from(new TextEncoder().encode(message)).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

            const formData = new FormData();
            formData.append('public_key', publicKey);
            formData.append('signature', signatureBase64);
            formData.append('message', message);

            statusSpan.textContent = 'Sending data to server...';
            const responseServer = await fetch('index.php', {
                method: 'POST',
                body: formData
            });

            const result = await responseServer.json();
            await logToServer(`Server response: ${JSON.stringify(result)}`, result.status === 'error' ? 'ERROR' : 'INFO');
            statusSpan.textContent = result.message || 'Unknown error';
        } else {
            statusSpan.textContent = 'Please install Phantom wallet!';
            walletInfo.style.display = 'block';
            await logToServer('Phantom wallet not installed', 'ERROR');
        }
    } catch (error) {
        await logToServer(`Error connecting or signing: ${error.message}`, 'ERROR');
        console.error('Error connecting or signing:', error);
        statusSpan.textContent = 'Error: ' + error.message;
        walletInfo.style.display = 'block';
    }
});
