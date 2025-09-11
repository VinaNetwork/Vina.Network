// ============================================================================
// File: acc/connect-jupiter/connect-j.js
// Description: Script for managing Jupiter wallet connection
// Created by: Vina Network
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('connect-j.js loaded');
    log_message('connect-j.js loaded', 'accounts.log', 'accounts', 'DEBUG');

    // Log message function
    async function log_message(message, log_file = 'accounts.log', module = 'accounts', log_type = 'INFO') {
        if (!authToken) {
            console.error('Log failed: authToken is missing');
            return;
        }

        const sanitizedMessage = message.replace(/privateKey=[^\s]+/g, 'privateKey=[HIDDEN]');
        try {
            const response = await axios.post('/acc/write-logs', {
                message: sanitizedMessage,
                log_file,
                module,
                log_type,
                url: window.location.href,
                userAgent: navigator.userAgent
            }, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Auth-Token': authToken
                },
                withCredentials: true
            });

            if (response.status === 200 && response.data.status === 'success') {
                console.log(`Log sent successfully: ${sanitizedMessage}`);
            } else {
                console.error(`Log failed: HTTP ${response.status}, message=${response.data.message || response.statusText}`);
            }
        } catch (err) {
            console.error('Log error:', {
                message: err.message,
                status: err.response?.status,
                data: err.response?.data
            });
        }
    }

    // Function to show error messages
    function showError(message) {
        console.log('Showing error:', message);
        log_message(`Showing error: ${message}`, 'accounts.log', 'accounts', 'ERROR');
        const statusSpan = document.getElementById('status') || document.createElement('span');
        statusSpan.id = 'status';
        statusSpan.textContent = message;
        statusSpan.style.color = 'red';
        const walletInfo = document.getElementById('wallet-info') || document.createElement('div');
        walletInfo.id = 'wallet-info';
        walletInfo.style.display = 'block';
        if (!walletInfo.contains(statusSpan)) {
            walletInfo.appendChild(statusSpan);
        }
    }

    // Check if running in a secure context (HTTPS)
    if (!window.isSecureContext) {
        log_message('Page not loaded over HTTPS, secure context unavailable', 'accounts.log', 'accounts', 'ERROR');
        showError('Error: This page must be loaded over HTTPS');
        return;
    }

    // Connect wallet functionality
    const connectWalletButton = document.getElementById('connect-wallet');
    if (connectWalletButton) {
        const loginForm = document.querySelector('form');
        if (loginForm) {
            loginForm.addEventListener('submit', (event) => {
                event.preventDefault();
                console.log('Prevented default form submission');
                log_message('Prevented default form submission', 'accounts.log', 'accounts', 'INFO');
            });
        }

        connectWalletButton.addEventListener('click', async () => {
            connectWalletButton.disabled = true;
            connectWalletButton.textContent = 'Connecting...';

            const walletInfo = document.getElementById('wallet-info');
            const publicKeySpan = document.getElementById('public-key');
            const statusSpan = document.getElementById('status');

            try {
                if (!window.isSecureContext) {
                    log_message('Wallet connection blocked: Not in secure context', 'accounts.log', 'accounts', 'ERROR');
                    showError('Error: Wallet connection requires HTTPS');
                    return;
                }

                if (window.solana && window.solana.isJupiter) {
                    statusSpan.textContent = 'Connecting wallet...';
                    await log_message('Initiating Jupiter wallet connection', 'accounts.log', 'accounts', 'INFO');
                    const response = await window.solana.connect();
                    const publicKey = response.publicKey.toString();
                    const shortPublicKey = publicKey.length >= 8 ? publicKey.substring(0, 4) + '...' + publicKey.substring(publicKey.length - 4) : 'Invalid';
                    publicKeySpan.textContent = publicKey;
                    walletInfo.style.display = 'block';
                    statusSpan.textContent = 'Wallet connected! Signing message...';
                    await log_message(`Wallet connected, publicKey: ${shortPublicKey}`, 'accounts.log', 'accounts', 'INFO');

                    const timestamp = Date.now();
                    const nonce = document.getElementById('login-nonce')?.value;
                    if (!nonce) {
                        throw new Error('Missing login nonce');
                    }
                    const message = `Verify login for Vina Network with nonce ${nonce} at ${timestamp}`;
                    const encodedMessage = new TextEncoder().encode(message);
                    await log_message(`Message to sign: ${message}, hex: ${Array.from(encodedMessage).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'accounts.log', 'accounts', 'DEBUG');

                    if (sessionStorage.getItem('lastSignature')) {
                        await log_message('Duplicate signature attempt detected', 'accounts.log', 'accounts', 'WARNING');
                        statusSpan.textContent = 'Error: A signature request is being processed. Please wait.';
                        return;
                    }

                    const signature = await window.solana.signMessage(encodedMessage, 'utf8');
                    const signatureBytes = new Uint8Array(signature.signature);
                    if (signatureBytes.length !== 64) {
                        throw new Error(`Invalid signature length: ${signatureBytes.length} bytes, expected 64 bytes`);
                    }
                    sessionStorage.setItem('lastSignature', JSON.stringify(signature));
                    const signatureBase64 = btoa(String.fromCharCode(...signatureBytes));
                    await log_message(`Signature created, base64: ${signatureBase64}, hex: ${Array.from(signatureBytes).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'accounts.log', 'accounts', 'DEBUG');

                    const formData = new FormData();
                    formData.append('public_key', publicKey);
                    formData.append('signature', signatureBase64);
                    formData.append('message', message);

                    const payload = Object.fromEntries(formData);
                    console.log('Wallet-auth payload:', payload);
                    await log_message(`Sending wallet-auth data: ${JSON.stringify(payload)}`, 'accounts.log', 'accounts', 'DEBUG');

                    const responseServer = await fetch('/acc/auth-j.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Auth-Token': authToken
                        },
                        body: JSON.stringify(payload)
                    });

                    if (!responseServer.ok) {
                        throw new Error(`HTTP ${responseServer.status}: ${responseServer.statusText}`);
                    }

                    const result = await responseServer.json();
                    await log_message(`Server response: ${JSON.stringify(result)}`, 'accounts.log', 'accounts', result.status === 'error' ? 'ERROR' : 'INFO');

                    if (result.status === 'success' && result.redirect) {
                        statusSpan.textContent = result.message || 'Login successful, redirecting...';
                        await log_message(`Login successful, redirecting to: ${result.redirect}`, 'accounts.log', 'accounts', 'INFO');
                        if (window.location.pathname !== result.redirect) {
                            window.location.href = result.redirect;
                        } else {
                            console.log('Already on redirect page, skipping navigation');
                            await log_message('Already on redirect page, skipping navigation', 'accounts.log', 'accounts', 'DEBUG');
                        }
                    } else {
                        showError(result.message || 'Login failed. Please try again.');
                    }
                } else {
                    showError('Please install the Jupiter wallet!');
                    walletInfo.style.display = 'block';
                    await log_message('Jupiter wallet not installed', 'accounts.log', 'accounts', 'ERROR');
                }
            } catch (error) {
                await log_message(`Error connecting or signing: ${error.message}`, 'accounts.log', 'accounts', 'ERROR');
                console.error('Error connecting or signing:', error);
                showError('Error: ' + error.message);
            } finally {
                connectWalletButton.disabled = false;
                connectWalletButton.textContent = 'Connect Wallet';
                sessionStorage.removeItem('lastSignature');
            }
        });
    }
});
