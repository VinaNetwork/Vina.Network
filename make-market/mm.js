// ============================================================================
// File: make-market/mm.js
// Description: JavaScript file for UI interactions on Make Market page
// Created by: Vina Network
// ============================================================================

// Log message function
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
    if (log_type === 'DEBUG' && (!window.ENVIRONMENT || window.ENVIRONMENT !== 'development')) {
        return;
    }
    fetch('/make-market/log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, log_file, module, log_type })
    }).then(response => {
        if (!response.ok) {
            console.error(`Log failed: HTTP ${response.status}`);
        }
    }).catch(err => console.error('Log error:', err));
}

// Show error message
function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p style="color: red;">Error: ${message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear Message</button>`;
    resultDiv.classList.add('active');
    document.querySelector('#makeMarketForm button').disabled = false;
}

// Handle form submission
document.getElementById('makeMarketForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const resultDiv = document.getElementById('mm-result');
    const submitButton = document.querySelector('#makeMarketForm button');
    submitButton.disabled = true;
    resultDiv.innerHTML = '<div class="spinner">Loading...</div>';
    resultDiv.classList.add('active');
    log_message('Form submitted', 'make-market.log', 'make-market', 'INFO');
    console.log('Form submitted');

    const formData = new FormData(e.target);
    const params = {
        processName: formData.get('processName'),
        privateKey: formData.get('privateKey'),
        tokenMint: formData.get('tokenMint'),
        solAmount: parseFloat(formData.get('solAmount')),
        slippage: parseFloat(formData.get('slippage')),
        delay: parseInt(formData.get('delay')),
        loopCount: parseInt(formData.get('loopCount')),
        batchSize: parseInt(formData.get('batchSize')),
        transactionPublicKey: formData.get('transactionPublicKey'),
        csrf_token: formData.get('csrf_token')
    };
    log_message(`Form data: ${JSON.stringify(params)}`, 'make-market.log', 'make-market', 'DEBUG');
    console.log('Form data:', params);

    // Validate private key
    if (!params.privateKey || typeof params.privateKey !== 'string' || params.privateKey.length < 1) {
        log_message('Private key is empty or invalid', 'make-market.log', 'make-market', 'ERROR');
        showError('Private key is empty or invalid. Please check and try again.');
        console.error('Private key is empty or invalid');
        return;
    }
    log_message(`Private key length: ${params.privateKey.length}`, 'make-market.log', 'make-market', 'DEBUG');
    console.log('Private key length:', params.privateKey.length);

    // Derive public key
    let transactionPublicKey;
    try {
        const decodedKey = window.bs58.decode(params.privateKey);
        log_message(`Decoded private key length: ${decodedKey.length}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log('Decoded private key length:', decodedKey.length);
        if (decodedKey.length !== 64) {
            log_message(`Invalid private key length: ${decodedKey.length}, expected 64 bytes`, 'make-market.log', 'make-market', 'ERROR');
            console.error(`Invalid private key length: ${decodedKey.length}, expected 64 bytes`);
            showError('Invalid private key length. Please check and try again.');
            return;
        }
        const keypair = window.solanaWeb3.Keypair.fromSecretKey(decodedKey);
        transactionPublicKey = keypair.publicKey.toBase58();
        if (!/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/.test(transactionPublicKey)) {
            log_message(`Invalid public key format derived from private key`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Invalid public key format derived from private key');
            showError('Invalid public key format. Please check the private key.');
            return;
        }
        formData.set('transactionPublicKey', transactionPublicKey);
        document.getElementById('transactionPublicKey').value = transactionPublicKey;
        log_message(`Derived transaction public key: ${transactionPublicKey}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log('Derived transaction public key:', transactionPublicKey);
    } catch (error) {
        log_message(`Invalid private key: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Invalid private key:', error.message);
        showError(`Invalid private key: ${error.message}. Please check and try again.`);
        return;
    }

    try {
        // Submit form data
        const response = await fetch('/make-market/', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const responseText = await response.text();
        log_message(`Form submission response: HTTP ${response.status}, Response: ${responseText}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log('Form submission response: HTTP', response.status, 'Response:', responseText);
        if (!response.ok) {
            log_message(`Form submission failed: HTTP ${response.status}, Response: ${responseText}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Form submission failed: HTTP', response.status, 'Response:', responseText);
            showError('Form submission failed. Please try again.');
            return;
        }
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (error) {
            log_message(`Error parsing response JSON: ${error.message}, Response: ${responseText}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Error parsing response JSON:', error.message, 'Response:', responseText);
            showError('Invalid server response. Please try again.');
            return;
        }
        if (result.status !== 'success') {
            log_message(`Form submission failed: ${result.message}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Form submission failed:', result.message);
            showError(`${result.message}. Please check and try again.`);
            return;
        }
        log_message(`Form saved to database: transactionId=${result.transactionId}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Form saved to database: transactionId=', result.transactionId);
        // Redirect to process page
        const redirectUrl = result.redirect || `/make-market/process.php?id=${result.transactionId}`;
        log_message(`Redirecting to ${redirectUrl}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Redirecting to', redirectUrl);
        setTimeout(() => {
            window.location.href = redirectUrl;
            console.log('Executing redirect to', redirectUrl);
        }, 100);
    } catch (error) {
        log_message(`Error submitting form: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Error submitting form:', error.message);
        showError(`Error submitting form: ${error.message}. Please try again.`);
    }
});

// Copy functionality for public_key
document.addEventListener('DOMContentLoaded', () => {
    console.log('mm.js loaded');
    log_message('mm.js loaded', 'make-market.log', 'make-market', 'DEBUG');

    const copyIcons = document.querySelectorAll('.copy-icon');
    log_message(`Found ${copyIcons.length} copy icons`, 'make-market.log', 'make-market', 'DEBUG');
    if (copyIcons.length === 0) {
        log_message('No .copy-icon elements found in DOM', 'make-market.log', 'make-market', 'ERROR');
        return;
    }

    copyIcons.forEach(icon => {
        log_message('Attaching click event to copy icon', 'make-market.log', 'make-market', 'DEBUG');
        icon.addEventListener('click', (e) => {
            log_message('Copy icon clicked', 'make-market.log', 'make-market', 'INFO');
            console.log('Copy icon clicked');

            if (!window.isSecureContext) {
                log_message('Copy blocked: Not in secure context', 'make-market.log', 'make-market', 'ERROR');
                console.error('Copy blocked: Not in secure context');
                showError('Unable to copy: This feature requires HTTPS');
                return;
            }

            const fullAddress = icon.getAttribute('data-full');
            if (!fullAddress) {
                log_message('Copy failed: data-full attribute not found or empty', 'make-market.log', 'make-market', 'ERROR');
                console.error('Copy failed: data-full attribute not found or empty');
                showError('Unable to copy: Invalid address');
                return;
            }

            const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
            if (!base58Regex.test(fullAddress)) {
                log_message(`Invalid address format: ${fullAddress}`, 'make-market.log', 'make-market', 'ERROR');
                console.error(`Invalid address format: ${fullAddress}`);
                showError('Unable to copy: Invalid address format');
                return;
            }

            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' + fullAddress.substring(fullAddress.length - 4) : 'Invalid';
            log_message(`Attempting to copy address: ${shortAddress}`, 'make-market.log', 'make-market', 'DEBUG');
            console.log(`Attempting to copy address: ${shortAddress}`);

            if (!navigator.clipboard) {
                log_message('Clipboard API unavailable', 'make-market.log', 'make-market', 'ERROR');
                console.error('Clipboard API unavailable');
                showError('Unable to copy: Browser does not support this feature. Please copy manually.');
                return;
            }

            navigator.clipboard.writeText(fullAddress).then(() => {
                log_message('Copy successful', 'make-market.log', 'make-market', 'INFO');
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
                    log_message('Copy feedback removed', 'make-market.log', 'make-market', 'DEBUG');
                    console.log('Copy feedback removed');
                }, 2000);
            }).catch(err => {
                log_message(`Clipboard API failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
                console.error('Clipboard API failed:', err.message);
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });
});
