document.getElementById('connect-wallet').addEventListener('click', async () => {
    const walletInfo = document.getElementById('wallet-info');
    const publicKeySpan = document.getElementById('public-key');
    const statusSpan = document.getElementById('status');

    try {
        if (window.solana && window.solana.isPhantom) {
            statusSpan.textContent = 'Connecting wallet...';
            const response = await window.solana.connect();
            const publicKey = response.publicKey.toString();
            publicKeySpan.textContent = publicKey;
            walletInfo.style.display = 'block';
            statusSpan.textContent = 'Wallet connected! Signing message...';
            console.log('Public Key:', publicKey);

            const timestamp = Date.now();
            const message = `Verify login for Vina Network at ${timestamp}`;
            const encodedMessage = new TextEncoder().encode(message);
            console.log('Message:', message);
            console.log('Encoded message length:', encodedMessage.length);
            console.log('Message hex:', Array.from(encodedMessage).map(b => b.toString(16).padStart(2, '0')).join(''));

            // Sign message as raw bytes
            const signature = await window.solana.signMessage(encodedMessage);
            const signatureBytes = new Uint8Array(signature.signature);
            console.log('Signature length:', signatureBytes.length);
            const signatureBase64 = btoa(String.fromCharCode(...signatureBytes));
            console.log('Signature (base64):', signatureBase64);
            console.log('Signature hex:', Array.from(signatureBytes).map(b => b.toString(16).padStart(2, '0')).join(''));

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
            console.log('Server response:', result);
            statusSpan.textContent = result.message || 'Unknown error';
        } else {
            statusSpan.textContent = 'Please install Phantom wallet!';
            walletInfo.style.display = 'block';
        }
    } catch (error) {
        console.error('Error connecting or signing:', error);
        statusSpan.textContent = 'Error: ' + error.message;
        walletInfo.style.display = 'block';
    }
});
