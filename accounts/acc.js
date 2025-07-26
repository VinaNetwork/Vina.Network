// ============================================================================
// File: accounts/acc.js
// Description: Script for managing the Accounts page with multi-wallet support via CDN, HTTPS, and XSS checks.
// Created by: Vina Network
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('acc.js loaded');

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

    // Check if required libraries are loaded
    if (!window.React || !window.ReactDOM || !window.SolanaWeb3 || !window.Jupiter) {
        logToServer('Required libraries (React, ReactDOM, SolanaWeb3, Jupiter) not loaded', 'ERROR');
        const statusSpan = document.getElementById('status');
        statusSpan.textContent = 'Error: Failed to load required libraries';
        return;
    }

    // Polyfill Buffer for browser
    if (!window.Buffer) {
        window.Buffer = {
            from: (data, encoding) => {
                if (encoding === 'utf8') return new Uint8Array([...data].map(c => c.charCodeAt(0)));
                throw new Error('Unsupported encoding');
            }
        };
    }

    // Hàm ghi log vào server
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
            const response = await fetch('/accounts/client-log.php', {
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

    // Wallet connection logic
    const connection = new window.SolanaWeb3.Connection('https://api.mainnet-beta.solana.com', 'confirmed');
    const wallets = [
        new window.SolanaWalletAdapterWallets.PhantomWalletAdapter(),
        new window.SolanaWalletAdapterWallets.SolflareWalletAdapter(),
        new window.SolanaWalletAdapterWallets.BackpackWalletAdapter(),
        // Add WalletConnect for Jupiter Mobile later if needed
        // new window.SolanaWalletAdapterWallets.WalletConnectWalletAdapter({ network: 'mainnet-beta' })
    ];

    // Render wallet button
    const root = document.getElementById('wallet-connect-root');
    if (!root) {
        logToServer('Root element #wallet-connect-root not found', 'ERROR');
        const statusSpan = document.getElementById('status');
        statusSpan.textContent = 'Error: Page setup failed';
        return;
    }

    const { UnifiedWalletProvider, UnifiedWalletButton } = window.Jupiter;
    window.ReactDOM.render(
        window.React.createElement(
            UnifiedWalletProvider,
            {
                wallets: wallets,
                config: {
                    autoConnect: false,
                    cluster: 'mainnet-beta',
                    connection: connection
                }
            },
            window.React.createElement(UnifiedWalletButton)
        ),
        root
    );

    // Listen for wallet connection
    let connectedWallet = null;
    wallets.forEach(wallet => {
        wallet.on('connect', async (publicKey) => {
            if (!window.isSecureContext) {
                logToServer('Wallet connection blocked: Not in secure context', 'ERROR');
                const walletInfo = document.getElementById('wallet-info');
                const statusSpan = document.getElementById('status');
                walletInfo.style.display = 'block';
                statusSpan.textContent = 'Error: Wallet connection requires HTTPS';
                return;
            }

            try {
                connectedWallet = wallet;
                const publicKeyStr = publicKey.toString();
                const shortPublicKey = publicKeyStr.length >= 8 ? publicKeyStr.substring(0, 4) + '...' + publicKeyStr.substring(publicKeyStr.length - 4) : 'Invalid';
                const walletInfo = document.getElementById('wallet-info');
                const publicKeySpan = document.getElementById('public-key');
                const statusSpan = document.getElementById('status');
                const csrfToken = document.getElementById('csrf-token').value;

                publicKeySpan.textContent = publicKeyStr;
                walletInfo.style.display = 'block';
                statusSpan.textContent = 'Wallet connected! Signing message...';
                await logToServer(`Wallet connected, publicKey: ${shortPublicKey}, wallet: ${wallet.name}`, 'INFO');

                const timestamp = Date.now();
                const message = `Verify login for Vina Network at ${timestamp}`;
                const encodedMessage = new TextEncoder().encode(message);
                await logToServer(`Message to sign: ${message}, hex: ${Array.from(encodedMessage).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

                const signature = await wallet.signMessage(encodedMessage);
                if (signature.length !== 64) {
                    throw new Error(`Invalid signature length: ${signature.length} bytes, expected 64 bytes`);
                }
                const signatureBase64 = btoa(String.fromCharCode(...signature));
                await logToServer(`Signature created, base64: ${signatureBase64}, hex: ${Array.from(signature).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

                const formData = new FormData();
                formData.append('public_key', publicKeyStr);
                formData.append('signature', signatureBase64);
                formData.append('message', message);
                formData.append('csrf_token', csrfToken);

                statusSpan.textContent = 'Sending data to server...';
                const responseServer = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await responseServer.json();
                await logToServer(`Server response: ${JSON.stringify(result)}`, result.status === 'error' ? 'ERROR' : 'INFO');
                if (result.status === 'error' && result.message.includes('Signature verification failed')) {
                    statusSpan.textContent = 'Error: Signature verification failed. Please ensure you are using the correct wallet and try again.';
                } else if (result.status === 'error' && result.message.includes('Invalid CSRF token')) {
                    statusSpan.textContent = 'Error: Invalid CSRF token. Please try again.';
                } else if (result.status === 'error' && result.message.includes('Too many login attempts')) {
                    statusSpan.textContent = 'Error: Too many login attempts. Please wait 1 minute and try again.';
                } else if (result.status === 'success' && result.redirect) {
                    statusSpan.textContent = result.message || 'Success';
                    window.location.href = result.redirect;
                } else {
                    statusSpan.textContent = result.message || 'Unknown error';
                }
            } catch (error) {
                await logToServer(`Error signing or submitting: ${error.message}, wallet: ${wallet.name}`, 'ERROR');
                statusSpan.textContent = 'Error: ' + error.message;
            }
        });

        wallet.on('error', async (error) => {
            await logToServer(`Wallet error: ${error.message}, wallet: ${wallet.name}`, 'ERROR');
            const statusSpan = document.getElementById('status');
            statusSpan.textContent = 'Error: ' + error.message;
        });
    });

    // Copy functionality for public_key
    document.addEventListener('click', function(e) {
        const icon = e.target.closest('.copy-icon');
        if (!icon) return;

        if (!window.isSecureContext) {
            logToServer('Copy blocked: Not in secure context', 'ERROR');
            alert('Unable to copy: This feature requires HTTPS');
            return;
        }

        const fullAddress = icon.getAttribute('data-full');
        if (!fullAddress) {
            console.error('Copy failed: data-full attribute not found or empty');
            logToServer('Copy failed: data-full attribute not found or empty', 'ERROR');
            alert('Unable to copy address: Invalid address');
            return;
        }

        const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
        if (!base58Regex.test(fullAddress)) {
            console.error('Invalid address format:', fullAddress);
            logToServer(`Copy blocked: Invalid address format in data-full: ${fullAddress.substring(0, 8)}...`, 'ERROR');
            alert('Unable to copy: Invalid address format');
            return;
        }

        const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' + fullAddress.substring(fullAddress.length - 4) : 'Invalid';
        console.log('Attempting to copy address:', shortAddress);

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(fullAddress).then(() => {
                showCopyFeedback(icon);
                logToServer(`Copied public_key: ${shortAddress}`, 'INFO');
            }).catch(err => {
                console.error('Clipboard API failed:', err);
                fallbackCopy(fullAddress, icon);
            });
        } else {
            console.warn('Clipboard API unavailable, using fallback');
            fallbackCopy(fullAddress, icon);
        }
    });

    function fallbackCopy(text, icon) {
        const shortText = text.length >= 8 ? text.substring(0, 4) + '...' + text.substring(text.length - 4) : 'Invalid';
        console.log('Using fallback copy for:', shortText);
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.top = '0';
        textarea.style.left = '0';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try {
            const success = document.execCommand('copy');
            console.log('Fallback copy result:', success);
            if (success) {
                showCopyFeedback(icon);
                logToServer(`Copied public_key: ${shortText}`, 'INFO');
            } else {
                console.error('Fallback copy failed');
                logToServer('Fallback copy failed', 'ERROR');
                alert('Unable to copy address: Copy error');
            }
        } catch (err) {
            console.error('Fallback copy error:', err);
            logToServer(`Fallback copy error: ${err.message}`, 'ERROR');
            alert('Unable to copy address: ' + err.message);
        } finally {
            document.body.removeChild(textarea);
        }
    }

    function showCopyFeedback(icon) {
        console.log('Showing copy feedback');
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
        }, 2000);
        console.log('Copy successful');
    }
});
