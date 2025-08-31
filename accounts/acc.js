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
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
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

    // Handle logout form submission
    const checkForm = () => {
    const logoutForm = document.querySelector('#logout-form');
    if (logoutForm) {
        console.log('Logout form found, attaching submit event');
        logoutForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            console.log('Logout form submitted');

            try {
                // Refresh CSRF token
                let csrfToken = await refreshCsrfToken();
                if (!csrfToken) {
                    showError('Unable to refresh CSRF token. Please refresh the page.');
                    await logToServer('CSRF token refresh failed before logout', 'ERROR');
                    return;
                }

                // Cập nhật token vào input của form
                const csrfInput = logoutForm.querySelector('input[name="csrf_token"]');
                if (csrfInput) {
                    csrfInput.value = csrfToken;
                    console.log('Updated CSRF token in form:', csrfToken.substring(0, 4) + '...');
                } else {
                    console.error('CSRF input not found in form');
                    await logToServer('CSRF input not found in form', 'ERROR');
                    showError('CSRF input not found. Please refresh the page.');
                    return;
                }

                // Gửi yêu cầu logout
                const formData = new FormData(logoutForm);
                console.log('Sending logout request with CSRF token:', csrfToken.substring(0, 4) + '...');
                await logToServer(`Sending logout request with CSRF token: ${csrfToken.substring(0, 4)}...`, 'DEBUG');

                const response = await fetch('/accounts/logout', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const result = await response.json();
                await logToServer(`Logout response: ${JSON.stringify(result)}`, result.status === 'error' ? 'ERROR' : 'INFO');

                if (result.status === 'success') {
                    console.log('Logout successful, redirecting to:', result.redirect || '/accounts/');
                    window.location.href = result.redirect || '/accounts/';
                } else {
                    showError(result.message || 'Logout failed. Please try again.');
                }
            } catch (error) {
                console.error('Error during logout:', error.message);
                await logToServer(`Error during logout: ${error.message}`, 'ERROR');
                showError('Error during logout: ' + error.message);
            }
        });
    } else {
        console.warn('Logout form not found');
    }
};
    checkForm();

    // Check if running in a secure context (HTTPS)
    if (!window.isSecureContext) {
        logToServer('Page not loaded over HTTPS, secure context unavailable', 'ERROR');
        showError('Error: This page must be loaded over HTTPS');
        return;
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
            const response = await fetch('/accounts/log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
        // Prevent default form submission
        const loginForm = document.querySelector('form'); // Adjust selector if needed
        if (loginForm) {
            loginForm.addEventListener('submit', (event) => {
                event.preventDefault(); // Prevent non-AJAX form submission
                console.log('Prevented default form submission');
            });
        }

        connectWalletButton.addEventListener('click', async () => {
            // Disable button to prevent multiple clicks
            connectWalletButton.disabled = true;
            connectWalletButton.textContent = 'Connecting...';

            const walletInfo = document.getElementById('wallet-info');
            const publicKeySpan = document.getElementById('public-key');
            const statusSpan = document.getElementById('status');

            try {
                // Refresh CSRF token before login
                let csrfToken = await refreshCsrfToken();
                if (!csrfToken) {
                    showError('Error: CSRF token missing. Please refresh the page.');
                    logToServer('CSRF token refresh failed before login', 'ERROR');
                    return;
                }
                await logToServer(`Using CSRF token: ${csrfToken.substring(0, 4)}...`, 'DEBUG');

                if (!window.isSecureContext) {
                    logToServer('Wallet connection blocked: Not in secure context', 'ERROR');
                    showError('Error: Wallet connection requires HTTPS');
                    return;
                }

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
                    const nonce = document.getElementById('login-nonce')?.value;
                    if (!nonce) {
                        throw new Error('Login nonce missing');
                    }
                    const message = `Verify login for Vina Network with nonce ${nonce} at ${timestamp}`;
                    const encodedMessage = new TextEncoder().encode(message);
                    await logToServer(`Message to sign: ${message}, hex: ${Array.from(encodedMessage).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

                    // Check for duplicate signature attempts
                    if (sessionStorage.getItem('lastSignature')) {
                        await logToServer('Duplicate signature attempt detected', 'WARNING');
                        statusSpan.textContent = 'Error: A signature request is already in progress. Please wait.';
                        return;
                    }

                    const signature = await window.solana.signMessage(encodedMessage, 'utf8');
                    const signatureBytes = new Uint8Array(signature.signature);
                    if (signatureBytes.length !== 64) {
                        throw new Error(`Invalid signature length: ${signatureBytes.length} bytes, expected 64 bytes`);
                    }
                    sessionStorage.setItem('lastSignature', JSON.stringify(signature));
                    const signatureBase64 = btoa(String.fromCharCode(...signatureBytes));
                    await logToServer(`Signature created, base64: ${signatureBase64}, hex: ${Array.from(signatureBytes).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

                    const formData = new FormData();
                    formData.append('public_key', publicKey);
                    formData.append('signature', signatureBase64);
                    formData.append('message', message);
                    formData.append('csrf_token', csrfToken);

                    statusSpan.textContent = 'Sending data to server...';
                    const responseServer = await fetch('/accounts/wallet-auth', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    if (!responseServer.ok) {
                        throw new Error(`HTTP ${responseServer.status}: ${responseServer.statusText}`);
                    }

                    const result = await responseServer.json();
                    await logToServer(`Server response: ${JSON.stringify(result)}`, result.status === 'error' ? 'ERROR' : 'INFO');

                    if (result.status === 'success' && result.redirect) {
                        statusSpan.textContent = result.message || 'Login successful, redirecting...';
                        window.location.href = result.redirect;
                    } else {
                        showError(result.message || 'Login failed. Please try again.');
                    }
                } else {
                    showError('Please install Phantom wallet!');
                    walletInfo.style.display = 'block';
                    await logToServer('Phantom wallet not installed', 'ERROR');
                }
            } catch (error) {
                await logToServer(`Error connecting or signing: ${error.message}`, 'ERROR');
                console.error('Error connecting or signing:', error);
                showError('Error: ' + error.message);
            } finally {
                // Re-enable button and clear sessionStorage
                connectWalletButton.disabled = false;
                connectWalletButton.textContent = 'Connect Wallet';
                sessionStorage.removeItem('lastSignature');
            }
        });
    }
});
