// ============================================================================
// File: acc/acc.js
// Description: Script for managing the entire Accounts page with HTTPS and XSS checks.
// Created by: Vina Network
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('acc.js loaded');
    log_message('acc.js loaded', 'accounts.log', 'accounts', 'DEBUG');

    // Copy functionality
    const copyIcons = document.querySelectorAll('.copy-icon');
    copyIcons.forEach(icon => {
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked');
            log_message('Copy icon clicked', 'accounts.log', 'accounts', 'INFO');
            const fullAddress = icon.getAttribute('data-full');
            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            console.log(`Attempting to copy address: ${shortAddress}`);
            log_message(`Attempting to copy address: ${shortAddress}`, 'accounts.log', 'accounts', 'DEBUG');
            navigator.clipboard.writeText(fullAddress).then(() => {
                console.log('Copy successful');
                log_message('Copy successful', 'accounts.log', 'accounts', 'INFO');
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
                    log_message('Copy feedback removed', 'accounts.log', 'accounts', 'DEBUG');
                }, 2000);
            }).catch(err => {
                console.error('Clipboard API failed:', err.message);
                log_message(`Clipboard API failed: ${err.message}`, 'accounts.log', 'accounts', 'ERROR');
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });

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

    // Handle logout form submission
    const checkForm = () => {
        // Only run on /acc/profile page
        if (window.location.pathname !== '/acc/profile') {
            console.log('Skipping checkForm on non-profile page');
            log_message('Skipping checkForm on non-profile page', 'accounts.log', 'accounts', 'INFO');
            return;
        }

        let retryCount = 0;
        const maxRetries = 50; // Limit to 5 seconds (50 * 100ms)
        const tryCheckForm = () => {
            const logoutForm = document.querySelector('#logout-form');
            if (logoutForm) {
                console.log('Logout form found, attaching submit event');
                log_message('Logout form found, attaching submit event', 'accounts.log', 'accounts', 'INFO');
                logoutForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    console.log('Logout form submitted');
                    log_message('Logout form submitted', 'accounts.log', 'accounts', 'INFO');

                    try {
                        const formData = new FormData(logoutForm);

                        console.log('Sending logout request');
                        await log_message('Sending logout request', 'accounts.log', 'accounts', 'DEBUG');

                        const response = await fetch('/acc/logout', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-Auth-Token': authToken // Thêm token vào header
                            },
                            body: JSON.stringify(Object.fromEntries(formData)) // Chuyển FormData thành JSON
                        });

                        const result = await response.json();
                        await log_message(`Logout response: ${JSON.stringify(result)}`, 'accounts.log', 'accounts', result.status === 'error' ? 'ERROR' : 'INFO');

                        if (result.status === 'success') {
                            console.log('Logout successful, redirecting to:', result.redirect || '/acc/');
                            log_message(`Logout successful, redirecting to: ${result.redirect || '/acc/'}`, 'accounts.log', 'accounts', 'INFO');
                            window.location.href = result.redirect || '/acc/';
                        } else {
                            showError(result.message || 'Logout failed. Please try again.');
                        }
                    } catch (error) {
                        console.error('Error during logout:', error.message);
                        await log_message(`Error during logout: ${error.message}`, 'accounts.log', 'accounts', 'ERROR');
                        showError('Error during logout: ' + error.message);
                    }
                });
            } else if (retryCount < maxRetries) {
                console.warn('Logout form not found, retrying in 100ms');
                log_message('Logout form not found, retrying in 100ms', 'accounts.log', 'accounts', 'WARNING');
                retryCount++;
                setTimeout(tryCheckForm, 100);
            } else {
                console.error('Max retries reached, logout form not found');
                log_message('Max retries reached, logout form not found', 'accounts.log', 'accounts', 'ERROR');
            }
        };
        tryCheckForm();
    };

    checkForm();

    // Check if running in a secure context (HTTPS)
    if (!window.isSecureContext) {
        log_message('Page not loaded over HTTPS, secure context unavailable', 'accounts.log', 'accounts', 'ERROR');
        showError('Error: This page must be loaded over HTTPS');
        return;
    }

    // Log message function
    function log_message(message, log_file = 'accounts.log', module = 'accounts', log_type = 'INFO') {
        if (log_type === 'DEBUG' && (!window.ENVIRONMENT || window.ENVIRONMENT !== 'development')) {
            return;
        }
        fetch('/acc/get-logs', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Auth-Token': authToken // Thêm token vào header
            },
            credentials: 'include', // Make sure cookies are sent
            body: JSON.stringify({ message, log_file, module, log_type, url: window.location.href, userAgent: navigator.userAgent })
        }).then(response => {
            if (!response.ok) {
                console.error(`Log failed: HTTP ${response.status}, response=${response.statusText}`);
            }
        }).catch(err => console.error(`Log error: ${err.message}`));
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
                log_message('Prevented default form submission', 'accounts.log', 'accounts', 'INFO');
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
                if (!window.isSecureContext) {
                    log_message('Wallet connection blocked: Not in secure context', 'accounts.log', 'accounts', 'ERROR');
                    showError('Error: Wallet connection requires HTTPS');
                    return;
                }

                if (window.solana && window.solana.isPhantom) {
                    statusSpan.textContent = 'Connecting wallet...';
                    await log_message('Initiating Phantom wallet connection', 'accounts.log', 'accounts', 'INFO');
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

                    // Check for duplicate signature attempts
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

                    statusSpan.textContent = 'Sending data to server...';
                    const responseServer = await fetch('/acc/wallet-auth', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Auth-Token': authToken // Thêm token vào header
                        },
                        body: JSON.stringify(Object.fromEntries(formData)) // Chuyển FormData thành JSON
                    });

                    if (!responseServer.ok) {
                        throw new Error(`HTTP ${responseServer.status}: ${responseServer.statusText}`);
                    }

                    const result = await responseServer.json();
                    await log_message(`Server response: ${JSON.stringify(result)}`, 'accounts.log', 'accounts', result.status === 'error' ? 'ERROR' : 'INFO');

                    if (result.status === 'success' && result.redirect) {
                        statusSpan.textContent = result.message || 'Login successful, redirecting...';
                        log_message(`Login successful, redirecting to: ${result.redirect}`, 'accounts.log', 'accounts', 'INFO');
                        window.location.href = result.redirect;
                    } else {
                        showError(result.message || 'Login failed. Please try again.');
                    }
                } else {
                    showError('Please install the Phantom wallet!');
                    walletInfo.style.display = 'block';
                    await log_message('Phantom wallet not installed', 'accounts.log', 'accounts', 'ERROR');
                }
            } catch (error) {
                await log_message(`Error connecting or signing: ${error.message}`, 'accounts.log', 'accounts', 'ERROR');
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
