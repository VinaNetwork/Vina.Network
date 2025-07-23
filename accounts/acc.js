document.getElementById('connect-wallet').addEventListener('click', async () => {
    const walletInfo = document.getElementById('wallet-info');
    const publicKeySpan = document.getElementById('public-key');
    const statusSpan = document.getElementById('status');

    try {
        if (window.solana && window.solana.isPhantom) {
            statusSpan.textContent = 'Connecting wallet...';
            console.log('Phantom version:', window.solana.version);
            console.log('Is connected:', window.solana.isConnected);
            const response = await window.solana.connect();
            const publicKeyObj = new solanaWeb3.PublicKey(response.publicKey);
            const publicKey = publicKeyObj.toBase58();
            console.log('Raw publicKey object:', response.publicKey);
            console.log('Public Key (base58):', publicKey);
            const base58Regex = /^[1-9A-HJ-NP-Za-km-z]{44}$/;
            if (!base58Regex.test(publicKey)) {
                throw new Error(`Invalid public key format: ${publicKey}`);
            }
            if (publicKey !== 'Frd7k5Thac1Mm76g4ET5jBiHtdABePvNRZFCFYf6GhDM') {
                throw new Error('Public key does not match expected value');
            }
            publicKeySpan.textContent = publicKey;
            walletInfo.style.display = 'block';
            statusSpan.textContent = 'Wallet connected! Signing message...';

            const message = 'VinaNetworkLogin';
            const encodedMessage = new TextEncoder().encode(message);
            console.log('Message:', message, 'Length:', encodedMessage.length, 'Hex:', Array.from(encodedMessage).map(b => b.toString(16).padStart(2, '0')).join(''));

            const signature = await window.solana.signMessage(encodedMessage);
            const signatureBytes = new Uint8Array(signature.signature);
            if (signatureBytes.length !== 64) {
                throw new Error('Invalid signature length from Phantom');
            }
            console.log('Signature length:', signatureBytes.length);
            const signatureHex = Array.from(signatureBytes).map(b => b.toString(16).padStart(2, '0')).join('');
            console.log('Signature (hex):', signatureHex);

            statusSpan.textContent = 'Sending data to server...';
            const responseServer = await fetch('http://localhost:3000/verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ public_key, message, signature_hex: signatureHex })
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
