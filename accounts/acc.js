// ============================================================================
// File: accounts/acc.js
// Description: Script for managing the entire Accounts page with HTTPS and XSS checks.
// Created by: Vina Network
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('acc.js loaded');

    // Function to get CSRF token from cookie
    function getCsrfTokenFromCookie() {
        const name = 'csrf_token_cookie=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const cookies = decodedCookie.split(';');
        for (let cookie of cookies) {
            cookie = cookie.trim();
            if (cookie.indexOf(name) === 0) {
                console.log('CSRF token found in cookie');
                return cookie.substring(name.length, cookie.length);
            }
        }
        console.warn('CSRF token not found in cookie');
        return null;
    }

    // Function to refresh CSRF token
    async function refreshCsrfToken() {
        try {
            console.log('Attempting to refresh CSRF token');
            const response = await fetch('/config/csrf.php?action=refresh', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status} - ${response.statusText}`);
            }
            const result = await response.json();
            if (result.status === 'success' && result.csrf_token) {
                console.log('CSRF token refreshed:', result.csrf_token.substring(0, 4) + '...');
                document.getElementById('csrf-token').value = result.csrf_token;
                await logToServer(`CSRF token refreshed: ${result.csrf_token.substring(0, 4)}...`, 'INFO');
                return result.csrf_token;
            } else {
                throw new Error(result.message || 'Failed to refresh CSRF token');
            }
        } catch (error) {
            console.error('Failed to refresh CSRF token:', error.message);
            await logToServer(`Failed to refresh CSRF token: ${error.message}`, 'ERROR');
            return null;
        }
    }

    // Schedule CSRF token refresh before expiration
    if (window.CSRF_TOKEN_TTL) {
        setTimeout(() => {
            refreshCsrfToken();
        }, (window.CSRF_TOKEN_TTL - 60) * 1000); // Refresh 60 seconds before expiration
    }

    // Copy functionality
    const copyIcons = document.querySelectorAll('.copy-icon');
    copyIcons.forEach(icon => {
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked');

            const fullAddress = icon.getAttribute('data-full');
            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            console.log(`Attempting to copy address: ${shortAddress}`);

            navigator.clipboard.writeText(fullAddress).then(() => {
                console.log('Copy successful');
                icon.classList.add('copied');
                const tooltip = document.createElement('span');
                tooltip.className = 'copy-tooltip';
                tooltip.textContent = 'Copied!';
                const parent = icon.parentNode;
                parent.style.position = 'relative';
                parent.appendChild(tooltip);
                setTimeout(() => {
                    icon.classList.remove('copied');
                    tooltip.remove();
                    console.log('Copy feedback removed');
                }, 2000);
            }).catch(err => {
                console.error('Clipboard API failed:', err.message);
                logToServer(`Clipboard API failed: ${err.message}`, 'ERROR');
            });
        });
    });

    // Check if running in a secure context (HTTPS)
    if (!window.isSecureContext) {
        logToServer('Page not loaded over HTTPS, secure context unavailable', 'ERROR');
        const walletInfo = document.getElementById('wallet-info');
        const statusSpan = document.getElementById('status');
        if (walletInfo && statusSpan) {
            walletInfo.style.display = 'block';
            statusSpan.textContent = 'Error: This page must be loaded over HTTPS';
        }
        return; // Stop further execution
    }

    // Function to log to server
    async function logToServer(message, level = 'INFO') {
        try {
            const logData = {
                timestamp: new Date().toISOString(),
                level: level,
                message: message,
                userAgent: navigator.userAgent,
                url: window.location.href
            };
            console.log(`Sending log to server: ${message}`);
            const response = await fetch('/accounts/log.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.getElementById('csrf-token').value || getCsrfTokenFromCookie() || ''
                },
                body: JSON.stringify(logData)
            });
            if (!response.ok) {
                console.error(`Failed to send log to server: HTTP ${response.status} - ${response.statusText}`);
                throw new Error(`HTTP ${response.status} - ${response.statusText}`);
            }
            const result = await response.json();
            console.log(`Log server response: ${JSON.stringify(result)}`);
        } catch (error) {
            console.error(`Failed to send log to server: ${error.message}`);
        }
    }

    // Connect wallet functionality
    const connectWalletButton = document.getElementById('connect-wallet');
    if (connectWalletButton) {
        connectWalletButton.addEventListener('click', async () => {
            const walletInfo = document.getElementById('wallet-info');
            const publicKeySpan = document.getElementById('public-key');
            const statusSpan = document.getElementById('status');
            let csrfToken = document.getElementById('csrf-token').value || getCsrfTokenFromCookie();

            if (!csrfToken) {
                logToServer('CSRF token not found in form or cookie', 'ERROR');
                csrfToken = await refreshCsrfToken();
                if (!csrfToken) {
                    walletInfo.style.display = 'block';
                    statusSpan.textContent = 'Error: CSRF token missing. Please refresh the page.';
                    return;
                }
            }
            await logToServer(`Using CSRF token: ${csrfToken.substring(0, 4)}...`, 'DEBUG');

            // Double-check HTTPS
            if (!window.isSecureContext) {
                logToServer('Wallet connection blocked: Not in secure context', 'ERROR');
                walletInfo.style.display = 'block';
                statusSpan.textContent = 'Error: Wallet connection requires HTTPS';
                return;
            }

            try {
                if (window.solana && window.solana.isPhantom) {
                    statusSpan.textContent = 'Connecting wallet...';
                    await logToServer('Initiating Phantom wallet connection', 'INFO');
                    const response = await window.solana.connect();
                    const publicKey = response.publicKey.toString();
                    const shortPublicKey = publicKey.length >= 8 ? publicKey.substring(0, 4) + '...' + publicKey.substring(publicKey.length - 4) : 'Invalid';
                    publicKeySpan.textContent = publicKey;
                    walletInfo.style.display = 'block';
                    statusSpan.textContent = 'Wallet connected! Signing message...';
                    await logToServer(`Wallet connected, publicKey: ${shortPublicKey}`, 'INFO');

                    const timestamp = Date.now();
                    const nonce = document.getElementById('login-nonce').value;
                    const message = `Verify login for Vina Network with nonce ${nonce} at ${timestamp}`;
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

                    // Check message before sending
                    await logToServer(`Message sent: ${message}, hex: ${Array.from(new TextEncoder().encode(message)).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

                    const formData = new FormData();
                    formData.append('public_key', publicKey);
                    formData.append('signature', signatureBase64);
                    formData.append('message', message);
                    formData.append('csrf_token', csrfToken);

                    statusSpan.textContent = 'Sending data to server...';
                    const responseServer = await fetch('wallet-auth.php', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-Token': csrfToken
                        },
                        body: formData
                    });

                    const result = await responseServer.json();
                    await logToServer(`Server response: ${JSON.stringify(result)}`, result.status === 'error' ? 'ERROR' : 'INFO');
                    if (result.status === 'error' && result.message.includes('Signature verification failed')) {
                        statusSpan.textContent = 'Error: Signature verification failed. Please ensure you are using the correct wallet in Phantom and try again.';
                    } else if (result.status === 'error' && result.message.includes('Invalid CSRF token')) {
                        statusSpan.textContent = 'Error: Invalid CSRF token. Refreshing token...';
                        csrfToken = await refreshCsrfToken();
                        if (csrfToken) {
                            statusSpan.textContent = 'CSRF token refreshed. Please try again.';
                        } else {
                            statusSpan.textContent = 'Error: Failed to refresh CSRF token. Please refresh the page.';
                        }
                    } else if (result.status === 'error' && result.message.includes('Too many login attempts')) {
                        statusSpan.textContent = 'Error: Too many login attempts. Please wait 1 minute and try again.';
                    } else if (result.status === 'success' && result.redirect) {
                        statusSpan.textContent = result.message || 'Success';
                        window.location.href = result.redirect; // Redirect to profile.php
                    } else {
                        statusSpan.textContent = result.message || 'Unknown error';
                    }
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
    }
});
