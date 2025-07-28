// ============================================================================
// File: accounts/acc.js
// Description: Script for managing the Accounts page with Solana wallet connection using UnifiedWalletProvider.
// Created by: Vina Network
// ============================================================================

import React, { useState, useMemo } from 'react';
import { createRoot } from 'react-dom/client';
import { Connection } from '@solana/web3.js';
import { UnifiedWalletProvider, UnifiedWalletButton } from '@jup-ag/wallet-adapter';

const Accounts = () => {
    const [status, setStatus] = useState('Not connected');
    const [publicKey, setPublicKey] = useState(null);

    const connection = useMemo(() => new Connection('https://api.mainnet-beta.solana.com'), []);

    const logToServer = async (message, level = 'INFO') => {
        try {
            await fetch('/accounts/client-log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ timestamp: new Date().toISOString(), level, message, userAgent: navigator.userAgent, url: window.location.href })
            });
        } catch (error) {
            console.error('Logging error:', error);
        }
    };

    const handleWalletChange = async (wallet) => {
        console.log('Wallet detected:', wallet);
        if (!window.isSecureContext) {
            logToServer('Wallet connection blocked: Not in secure context', 'ERROR');
            setStatus('Error: Wallet connection requires HTTPS');
            return;
        }
        try {
            if (wallet) {
                const publicKey = wallet.publicKey.toString();
                setPublicKey(publicKey);
                setStatus('Wallet connected! Signing message...');
                await logToServer(`Wallet connected, publicKey: ${publicKey.substring(0, 4)}...${publicKey.substring(publicKey.length - 4)}`, 'INFO');

                const timestamp = Date.now();
                const message = `Verify login for Vina Network at ${timestamp}`;
                const encodedMessage = new TextEncoder().encode(message);
                await logToServer(`Message to sign: ${message}`, 'DEBUG');

                const signature = await wallet.signMessage(encodedMessage);
                const signatureBase64 = btoa(String.fromCharCode(...new Uint8Array(signature)));
                await logToServer(`Signature created, base64: ${signatureBase64}`, 'DEBUG');

                const csrfToken = document.getElementById('csrf-token').value;
                const formData = new FormData();
                formData.append('public_key', publicKey);
                formData.append('signature', signatureBase64);
                formData.append('message', message);
                formData.append('csrf_token', csrfToken);

                setStatus('Sending data to server...');
                const response = await fetch('/accounts/auth.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                await logToServer(`Server response: ${JSON.stringify(result)}`, result.status === 'error' ? 'ERROR' : 'INFO');

                if (result.status === 'success' && result.redirect) {
                    setStatus(result.message || 'Success');
                    window.location.href = result.redirect;
                } else {
                    setStatus(`Error: ${result.message || 'Unknown error'}`);
                }
            } else {
                setPublicKey(null);
                setStatus('Disconnected');
                await logToServer('Wallet disconnected', 'INFO');
            }
        } catch (error) {
            await logToServer(`Error connecting or signing: ${error.message}`, 'ERROR');
            setStatus(`Error: ${error.message}`);
        }
    };

    const handleCopy = (fullAddress) => {
        const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
        if (!base58Regex.test(fullAddress)) {
            logToServer(`Copy blocked: Invalid address format: ${fullAddress.substring(0, 4)}...`, 'ERROR');
            setStatus('Error: Invalid address format');
            return;
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(fullAddress).then(() => {
                logToServer(`Copied public_key: ${fullAddress.substring(0, 4)}...${fullAddress.substring(fullAddress.length - 4)}`, 'INFO');
                setStatus('Copied address!');
            }).catch(err => {
                logToServer(`Clipboard API failed: ${err.message}`, 'ERROR');
                setStatus('Error: Unable to copy address');
            });
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = fullAddress;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                logToServer(`Copied public_key: ${fullAddress.substring(0, 4)}...${fullAddress.substring(fullAddress.length - 4)}`, 'INFO');
                setStatus('Copied address!');
            } catch (err) {
                logToServer(`Fallback copy error: ${err.message}`, 'ERROR');
                setStatus('Error: Unable to copy address');
            } finally {
                document.body.removeChild(textarea);
            }
        }
    };

    return (
        <UnifiedWalletProvider
            wallets={[]}
            config={{
                autoConnect: false,
                env: 'mainnet-beta',
                metadata: {
                    name: 'Vina Network',
                    description: 'Vina Network Wallet Connector',
                    url: 'https://www.vina.network',
                    iconUrls: ['https://www.vina.network/favicon.ico']
                }
            }}
            onWalletChange={handleWalletChange}
        >
            <div className="acc-content">
                <h1>Login/Register with Solana Wallet</h1>
                <UnifiedWalletButton className="cta-button" />
                <p>Status: {status}</p>
                {publicKey && (
                    <p>
                        Wallet address: <span id="public-key">{publicKey.substring(0, 4)}...{publicKey.substring(publicKey.length - 4)}</span>
                        <span className="copy-icon" onClick={() => handleCopy(publicKey)} title="Copy address">📋</span>
                    </p>
                )}
            </div>
        </UnifiedWalletProvider>
    );
};

const root = createRoot(document.getElementById('accounts-root'));
root.render(<Accounts />);
