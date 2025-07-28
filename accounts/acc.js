import React, { useMemo, useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { Connection } from '@solana/web3.js';
import { UnifiedWalletProvider, UnifiedWalletButton } from '@jup-ag/wallet-adapter';

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

// Component chính
const App = () => {
    const [publicKey, setPublicKey] = useState(null);
    const [status, setStatus] = useState('Not connected');
    const csrfToken = document.getElementById('csrf-token')?.value;

    const connection = useMemo(
        () => new Connection('https://api.mainnet-beta.solana.com'),
        []
    );

    const wallets = useMemo(() => [], []); // Auto-detects Phantom, Solflare, etc.

    // Kiểm tra HTTPS
    useEffect(() => {
        if (!window.isSecureContext) {
            logToServer('Page not loaded over HTTPS, secure context unavailable', 'ERROR');
            setStatus('Error: This page must be loaded over HTTPS');
        }
    }, []);

    // Hàm xử lý kết nối ví
    const handleWalletChange = async (wallet) => {
        if (!window.isSecureContext) {
            logToServer('Wallet connection blocked: Not in secure context', 'ERROR');
            setStatus('Error: Wallet connection requires HTTPS');
            return;
        }

        if (!wallet) {
            setPublicKey(null);
            setStatus('Disconnected');
            logToServer('Wallet disconnected', 'INFO');
            return;
        }

        try {
            setStatus('Connecting wallet...');
            await logToServer(`Initiating wallet connection: ${wallet.name}`, 'INFO');
            const publicKeyStr = wallet.publicKey?.toBase58();
            if (!publicKeyStr) {
                throw new Error('No public key received');
            }
            const shortPublicKey = publicKeyStr.length >= 8 ? publicKeyStr.substring(0, 4) + '...' + publicKeyStr.substring(publicKeyStr.length - 4) : 'Invalid';
            setPublicKey(publicKeyStr);
            setStatus('Wallet connected! Signing message...');
            await logToServer(`Wallet connected, publicKey: ${shortPublicKey}`, 'INFO');

            const timestamp = Date.now();
            const message = `Verify login for Vina Network at ${timestamp}`;
            const encodedMessage = new TextEncoder().encode(message);
            await logToServer(`Message to sign: ${message}, hex: ${Array.from(encodedMessage).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

            const signature = await wallet.signMessage(encodedMessage);
            const signatureBytes = new Uint8Array(signature);
            if (signatureBytes.length !== 64) {
                throw new Error(`Invalid signature length: ${signatureBytes.length} bytes, expected 64 bytes`);
            }
            const signatureBase64 = btoa(String.fromCharCode(...signatureBytes));
            await logToServer(`Signature created, base64: ${signatureBase64}, hex: ${Array.from(signatureBytes).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

            const formData = new FormData();
            formData.append('public_key', publicKeyStr);
            formData.append('signature', signatureBase64);
            formData.append('message', message);
            formData.append('csrf_token', csrfToken);

            setStatus('Sending data to server...');
            const response = await fetch('auth.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            await logToServer(`Server response: ${JSON.stringify(result)}`, result.status === 'error' ? 'ERROR' : 'INFO');
            if (result.status === 'error' && result.message.includes('Signature verification failed')) {
                setStatus('Error: Signature verification failed. Please ensure you are using the correct wallet and try again.');
            } else if (result.status === 'error' && result.message.includes('Invalid CSRF token')) {
                setStatus('Error: Invalid CSRF token. Please try again.');
            } else if (result.status === 'error' && result.message.includes('Too many login attempts')) {
                setStatus('Error: Too many login attempts. Please wait 1 minute and try again.');
            } else if (result.status === 'success' && result.redirect) {
                setStatus(result.message || 'Success');
                window.location.href = result.redirect;
            } else {
                setStatus(result.message || 'Unknown error');
            }
        } catch (error) {
            await logToServer(`Error connecting or signing: ${error.message}`, 'ERROR');
            console.error('Error connecting or signing:', error);
            setStatus('Error: ' + error.message);
        }
    };

    // Hàm xử lý copy public key
    const handleCopy = async (fullAddress, icon) => {
        if (!window.isSecureContext) {
            logToServer('Copy blocked: Not in secure context', 'ERROR');
            alert('Unable to copy: This feature requires HTTPS');
            return;
        }

        const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
        if (!base58Regex.test(fullAddress)) {
            console.error('Invalid address format:', fullAddress);
            await logToServer(`Copy blocked: Invalid address format: ${fullAddress.substring(0, 8)}...`, 'ERROR');
            alert('Unable to copy: Invalid address format');
            return;
        }

        const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' + fullAddress.substring(fullAddress.length - 4) : 'Invalid';

        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(fullAddress);
                showCopyFeedback(icon);
                await logToServer(`Copied public_key: ${shortAddress}`, 'INFO');
            } catch (err) {
                console.error('Clipboard API failed:', err);
                fallbackCopy(fullAddress, icon);
            }
        } else {
            fallbackCopy(fullAddress, icon);
        }
    };

    const fallbackCopy = (text, icon) => {
        const shortText = text.length >= 8 ? text.substring(0, 4) + '...' + text.substring(text.length - 4) : 'Invalid';
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
    };

    const showCopyFeedback = (icon) => {
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
    };

    return (
        <UnifiedWalletProvider
            wallets={wallets}
            config={{
                autoConnect: false,
                cluster: 'mainnet-beta',
                connection,
                onWalletChange: handleWalletChange
            }}
        >
            <div className="acc-content">
                <h1>Login/Register with Solana Wallet</h1>
                <UnifiedWalletButton />
                <div id="wallet-info" style={{ display: publicKey || status !== 'Not connected' ? 'block' : 'none' }}>
                    <p>Wallet address: <span id="public-key">{publicKey ? `${publicKey.substring(0, 4)}...${publicKey.substring(publicKey.length - 4)}` : ''}</span>
                        {publicKey && (
                            <i className="fas fa-copy copy-icon" title="Copy full address" data-full={publicKey} onClick={(e) => handleCopy(publicKey, e.target)}></i>
                        )}
                    </p>
                    <p>Status: <span id="status">{status}</span></p>
                </div>
            </div>
        </UnifiedWalletProvider>
    );
};

// Render component
const root = createRoot(document.getElementById('accounts-root'));
root.render(<App />);
