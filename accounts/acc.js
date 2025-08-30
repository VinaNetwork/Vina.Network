// ============================================================================
// File: accounts/acc.js
// Description: Script for managing the entire Accounts page with HTTPS and XSS checks.
// Created by: Vina Network
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('acc.js loaded');

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
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });

    // Function to get CSRF token from cookie
    function getCsrfTokenFromCookie() {
        const name = 'csrf_token_cookie=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const cookies = decodedCookie.split(';');
        for (let cookie of cookies) {
            cookie = cookie.trim();
            if (cookie.indexOf(name) === 0) {
                console.log('CSRF token found in cookie:', cookie.substring(name.length, name.length + 4) + '...');
                return cookie.substring(name.length, cookie.length);
            }
        }
        console.warn('CSRF token not found in cookie');
        return null;
    }

    // Function to refresh CSRF token
    async function refreshCsrfToken() {
        console.log('Attempting to refresh CSRF token');
        try {
            const response = await fetch('/accounts/refresh-csrf', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const result = await response.json();
            console.log('Refresh CSRF response:', result);
            if (result.status === 'success' && result.csrf_token) {
                console.log('CSRF token refreshed:', result.csrf_token.substring(0, 4) + '...');
                const csrfInput = document.querySelector('input[name="csrf_token"]');
                if (csrfInput) {
                    csrfInput.value = result.csrf_token;
                    console.log('Updated CSRF token in form');
                } else {
                    console.error('CSRF input not found in form');
                    logToServer('CSRF input not found in form', 'ERROR');
                }
                return result.csrf_token;
            } else {
                throw new Error('Failed to refresh CSRF token: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error refreshing CSRF token:', error.message);
            logToServer('Error refreshing CSRF token: ' + error.message, 'ERROR');
            return null;
        }
    }

    // Function to show error messages
    function showError(message) {
        console.log('Showing error:', message);
        const statusSpan = document.getElementById('status') || document.createElement('span');
        statusSpan.textContent = message;
        statusSpan.style.color = 'red';
        const walletInfo = document.getElementById('wallet-info') || document.createElement('div');
        walletInfo.style.display = 'block';
        walletInfo.appendChild(statusSpan);
    }

    // Handle logout form submission
    const checkForm = () => {
        const logoutForm = document.querySelector('#logout-form');
        if (logoutForm) {
            console.log('Logout form found, attaching submit event');
            logoutForm.addEventListener('submit', async (event) => {
                console.log('Logout form submitted');
                let csrfToken = document.querySelector('input[name="csrf_token"]').value || getCsrfTokenFromCookie();

                if (!csrfToken) {
                    console.warn('CSRF token not found, attempting to refresh');
                    logToServer('CSRF token not found, attempting to refresh', 'WARNING');
                    event.preventDefault(); // Prevent form submission
                    csrfToken = await refreshCsrfToken();
                    if (!csrfToken) {
                        showError('Unable to refresh CSRF token. Please refresh the page.');
                        return;
                    }
                    // Update CSRF token and allow form submission
                    document.querySelector('input[name="csrf_token"]').value = csrfToken;
                    console.log('CSRF token updated, submitting form');
                    logoutForm.submit(); // Submit form directly
                }
            });
        } else {
            console.warn('Logout form not found, retrying in 100ms');
            setTimeout(checkForm, 100); // Retry after 100ms
        }
    };

    checkForm(); // Initial check for logout form

    // Check if running in a secure context (HTTPS)
    if (!window.isSecureContext) {
        logToServer('Page not loaded over HTTPS, secure context unavailable', 'ERROR');
        const walletInfo = document.getElementById('wallet-info');
        const statusSpan = document.getElementById('status');
        if (walletInfo && statusSpan) {
            walletInfo.style.display = 'block';
            statusSpan.textContent = 'Error: This page must be loaded over HTTPS';
        }
        return;
    }

    // Function to log to server
    async function logToServer(message, level = 'INFO') {
    // Debounce logging to prevent spam
    let lastLogTime = 0;
    const logCooldown = 1000; // 1 second cooldown
    const now = Date.now();
    if (now - lastLogTime < logCooldown) {
        console.log(`Log skipped due to cooldown: ${message}`);
        return;
    }
    lastLogTime = now;

    try {
        const logData = {
            timestamp: new Date().toISOString(),
            level: level,
            message: message,
            userAgent: navigator.userAgent,
            url: window.location.href
        };
        console.log(`Sending log to server: ${message}`);
        const response = await fetch('/accounts/log', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(logData)
        });
        if (!response.ok) {
            const result = await response.json();
            console.error(`Failed to send log to server: HTTP ${response.status} - ${result.message || response.statusText}`);
            throw new Error(`HTTP ${response.status} - ${result.message || response.statusText}`);
        }
        const result = await response.json();
        console.log(`Log server response: ${JSON.stringify(result)}`);
        if (result.status === 'error') {
            console.warn(`Log server error: ${result.message}`);
            if (result.message === 'Unauthorized') {
                showError('Please log in to perform this action.');
            }
        }
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
        const nonce = document.getElementById('login-nonce').value;

        if (!csrfToken) {
            logToServer('CSRF token not found in form or cookie', 'ERROR');
            walletInfo.style.display = 'block';
            statusSpan.textContent = 'Error: CSRF token missing. Please refresh the page.';
            return;
        }
        await logToServer('Initiating wallet authentication', 'DEBUG'); // Generic message

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
                const message = `Verify login for Vina Network with nonce ${nonce} at ${timestamp}`;
                const encodedMessage = new TextEncoder().encode(message);
                await logToServer('Preparing to sign wallet authentication message', 'DEBUG'); // Generic message

                const signature = await window.solana.signMessage(encodedMessage, 'utf8');
                const signatureBytes = new Uint8Array(signature.signature);
                if (signatureBytes.length !== 64) {
                    throw new Error(`Invalid signature length: ${signatureBytes.length} bytes, expected 64 bytes`);
                }
                const signatureBase64 = btoa(String.fromCharCode(...signatureBytes));
                await logToServer('Wallet signature created', 'DEBUG'); // Generic message

                const formData = new FormData();
                formData.append('public_key', publicKey);
                formData.append('signature', signatureBase64);
                formData.append('message', message);
                formData.append('csrf_token', csrfToken);

                statusSpan.textContent = 'Sending data to server...';
                const responseServer = await fetch('/accounts/wallet-auth', {
                    method: 'POST',
                    body: formData
                });

                const result = await responseServer.json();
                await logToServer(`Server response: ${JSON.stringify(result)}`, result.status === 'error' ? 'ERROR' : 'INFO');
                if (result.status === 'error' && result.message.includes('Signature verification failed')) {
                    statusSpan.textContent = 'Error: Signature verification failed. Please ensure you are using the correct wallet in Phantom and try again.';
                } else if (result.status === 'error' && result.message.includes('Invalid CSRF token')) {
                    statusSpan.textContent = 'Error: Invalid CSRF token. Please refresh the page.';
                } else if (result.status === 'error' && result.message.includes('Too many login attempts')) {
                    statusSpan.textContent = 'Error: Too many login attempts. Please wait 1 minute and try again.';
                } else if (result.status === 'success' && result.redirect) {
                    statusSpan.textContent = result.message || 'Success';
                    window.location.href = result.redirect;
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
