// ============================================================================
// File: accounts/acc.js
// Description: Script for managing the entire Accounts page with multi-wallet support, HTTPS, and XSS checks.
// Created by: Vina Network
// ============================================================================

import React, { useEffect, useMemo } from 'react';
import { UnifiedWalletProvider, useUnifiedWallet, useUnifiedWalletContext } from '@jup-ag/wallet-adapter';
import { PhantomWalletAdapter, SolflareWalletAdapter, BackpackWalletAdapter } from '@solana/wallet-adapter-wallets';
import { Connection, PublicKey } from '@solana/web3.js';
import { Buffer } from 'buffer';

// Polyfill Buffer for browser
window.Buffer = window.Buffer || Buffer;

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

    // Wallet connection component
    const WalletConnection = () => {
        const { connect, signMessage, connected, publicKey, wallet } = useUnifiedWallet();
        const { setShowModal } = useUnifiedWalletContext();

        useEffect(() => {
            if (connected && publicKey) {
                handleWalletConnected(publicKey.toString());
            }
        }, [connected, publicKey]);

        const handleConnect = async () => {
            // Check HTTPS
            if (!window.isSecureContext) {
                logToServer('Wallet connection blocked: Not in secure context', 'ERROR');
                const walletInfo = document.getElementById('wallet-info');
                const statusSpan = document.getElementById('status');
                walletInfo.style.display = 'block';
                statusSpan.textContent = 'Error: Wallet connection requires HTTPS';
                return;
            }

            try {
                setShowModal(true); // Show wallet selection modal
                await logToServer(`Opening wallet selection modal`, 'INFO');
            } catch (error) {
                await logToServer(`Error opening wallet modal: ${error.message}`, 'ERROR');
                const statusSpan = document.getElementById('status');
                statusSpan.textContent = 'Error: ' + error.message;
            }
        };

        const handleWalletConnected = async (publicKeyStr) => {
            const walletInfo = document.getElementById('wallet-info');
            const publicKeySpan = document.getElementById('public-key');
            const statusSpan = document.getElementById('status');
            const csrfToken = document.getElementById('csrf-token').value;

            try {
                const shortPublicKey = publicKeyStr.length >= 8 ? publicKeyStr.substring(0, 4) + '...' + publicKeyStr.substring(publicKeyStr.length - 4) : 'Invalid';
                publicKeySpan.textContent = publicKeyStr;
                walletInfo.style.display = 'block';
                statusSpan.textContent = 'Wallet connected! Signing message...';
                await logToServer(`Wallet connected, publicKey: ${shortPublicKey}, wallet: ${wallet?.adapter.name}`, 'INFO');

                const timestamp = Date.now();
                const message = `Verify login for Vina Network at ${timestamp}`;
                const encodedMessage = new TextEncoder().encode(message);
                await logToServer(`Message to sign: ${message}, hex: ${Array.from(encodedMessage).map(b => b.toString(16).padStart(2, '0')).join('')}`, 'DEBUG');

                const signature = await signMessage(encodedMessage);
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
                await logToServer(`Error signing or submitting: ${error.message}`, 'ERROR');
                statusSpan.textContent = 'Error: ' + error.message;
            }
        };

        return (
            <button id="connect-wallet" onClick={handleConnect}>
                Connect Wallet
            </button>
        );
    };

    // Render wallet provider
    const connection = new Connection('https://api.mainnet-beta.solana.com', 'confirmed');
    const wallets = useMemo(() => [
        new PhantomWalletAdapter(),
        new SolflareWalletAdapter(),
        new BackpackWalletAdapter(),
        // Add more wallet adapters as needed (e.g., Jupiter Mobile)
    ], []);

    const root = document.createElement('div');
    document.body.appendChild(root);
    ReactDOM.render(
        <UnifiedWalletProvider
            wallets={wallets}
            config={{
                autoConnect: false,
                cluster: 'mainnet-beta',
                connection: connection
            }}
        >
            <WalletConnection />
        </UnifiedWalletProvider>,
        root
    );

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
