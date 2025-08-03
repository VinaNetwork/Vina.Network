// ============================================================================
// File: make-market/mm.js
// Description: JavaScript file for UI interactions on Make Market page
// Created by: Vina Network
// ============================================================================

// Log message function
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
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

    // Check libraries
    if (typeof window.solanaWeb3 === 'undefined') {
        log_message('solanaWeb3 is not defined', 'make-market.log', 'make-market', 'ERROR');
        resultDiv.innerHTML = '<p style="color: red;">Error: solanaWeb3 is not defined</p><button class="cta-button" onclick="document.getElementById(\'mm-result\').innerHTML=\'\';document.getElementById(\'mm-result\').classList.remove(\'active\');">Xóa thông báo</button>';
        resultDiv.classList.add('active');
        submitButton.disabled = false;
        console.error('solanaWeb3 is not defined');
        return;
    }
    if (typeof window.bs58 === 'undefined') {
        log_message('bs58 library is not loaded', 'make-market.log', 'make-market', 'ERROR');
        resultDiv.innerHTML = '<p style="color: red;">Error: bs58 library is not loaded</p><button class="cta-button" onclick="document.getElementById(\'mm-result\').innerHTML=\'\';document.getElementById(\'mm-result\').classList.remove(\'active\');">Xóa thông báo</button>';
        resultDiv.classList.add('active');
        submitButton.disabled = false;
        console.error('bs58 library is not loaded');
        return;
    }

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
        log_message('privateKey is empty or invalid', 'make-market.log', 'make-market', 'ERROR');
        resultDiv.innerHTML = '<p style="color: red;">Error: privateKey is empty or invalid</p><button class="cta-button" onclick="document.getElementById(\'mm-result\').innerHTML=\'\';document.getElementById(\'mm-result\').classList.remove(\'active\');">Xóa thông báo</button>';
        resultDiv.classList.add('active');
        submitButton.disabled = false;
        console.error('privateKey is empty or invalid');
        return;
    }
    log_message(`privateKey length: ${params.privateKey.length}`, 'make-market.log', 'make-market', 'DEBUG');
    console.log('privateKey length:', params.privateKey.length);

    // Derive public key
    let transactionPublicKey;
    try {
        const decodedKey = window.bs58.decode(params.privateKey);
        log_message(`Decoded privateKey length: ${decodedKey.length}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log('Decoded privateKey length:', decodedKey.length);
        if (decodedKey.length !== 64) {
            log_message(`Invalid private key length: ${decodedKey.length}, expected 64 bytes`, 'make-market.log', 'make-market', 'ERROR');
            console.error(`Invalid private key length: ${decodedKey.length}, expected 64 bytes`);
            throw new Error(`Invalid private key length: ${decodedKey.length} bytes, expected 64 bytes`);
        }
        const keypair = window.solanaWeb3.Keypair.fromSecretKey(decodedKey);
        transactionPublicKey = keypair.publicKey.toBase58();
        if (!/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/.test(transactionPublicKey)) {
            log_message(`Invalid public key format derived from private key`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Invalid public key format derived from private key');
            throw new Error('Invalid public key format');
        }
        formData.set('transactionPublicKey', transactionPublicKey);
        document.getElementById('transactionPublicKey').value = transactionPublicKey;
        log_message(`Derived transactionPublicKey: ${transactionPublicKey}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log('Derived transactionPublicKey:', transactionPublicKey);
    } catch (error) {
        log_message(`Invalid private key format: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Invalid private key format:', error.message);
        resultDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Xóa thông báo</button>`;
        resultDiv.classList.add('active');
        submitButton.disabled = false;
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
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (error) {
            log_message(`Error parsing response JSON: ${error.message}, Response: ${responseText}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Error parsing response JSON:', error.message, 'Response:', responseText);
            throw new Error('Invalid JSON response');
        }
        if (result.status !== 'success') {
            log_message(`Form submission failed: ${result.message}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Form submission failed:', result.message);
            resultDiv.innerHTML = `<p style="color: red;">Error: ${result.message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Xóa thông báo</button>`;
            resultDiv.classList.add('active');
            submitButton.disabled = false;
            return;
        }
        log_message(`Form saved to database: transactionId=${result.transactionId}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Form saved to database: transactionId=', result.transactionId);
        // Redirect to process page
        const redirectUrl = result.redirect || `/make-market/process/process.php?id=${result.transactionId}`;
        log_message(`Redirecting to ${redirectUrl}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Redirecting to', redirectUrl);
        setTimeout(() => {
            window.location.href = redirectUrl;
            console.log('Executing redirect to', redirectUrl);
        }, 100);
    } catch (error) {
        log_message(`Error submitting form: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Error submitting form:', error.message);
        resultDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Xóa thông báo</button>`;
        resultDiv.classList.add('active');
        submitButton.disabled = false;
    }
});

// Copy functionality for public_key
document.addEventListener('DOMContentLoaded', () => {
    console.log('mm.js loaded');
    log_message('mm.js loaded', 'make-market.log', 'make-market', 'DEBUG');
    log_message(`bs58 available: ${typeof window.bs58 !== 'undefined' ? 'Yes' : 'No'}`, 'make-market.log', 'make-market', 'DEBUG');
    log_message(`solanaWeb3 available: ${typeof window.solanaWeb3 !== 'undefined' ? 'Yes' : 'No'}`, 'make-market.log', 'make-market', 'DEBUG');
    log_message(`splToken available: ${typeof window.splToken !== 'undefined' ? 'Yes' : 'No'}`, 'make-market.log', 'make-market', 'DEBUG');
    log_message(`axios available: ${typeof window.axios !== 'undefined' ? 'Yes' : 'No'}`, 'make-market.log', 'make-market', 'DEBUG');

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
                alert('Unable to copy: This feature requires HTTPS');
                return;
            }

            const fullAddress = icon.getAttribute('data-full');
            if (!fullAddress) {
                log_message('Copy failed: data-full attribute not found or empty', 'make-market.log', 'make-market', 'ERROR');
                console.error('Copy failed: data-full attribute not found or empty');
                resultDiv.innerHTML = '<p style="color: red;">Error: Unable to copy address: Invalid address</p>';
                resultDiv.classList.add('active');
                setTimeout(() => {
                    resultDiv.classList.remove('active');
                    resultDiv.innerHTML = '';
                }, 5000);
                return;
            }

            const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
            if (!base58Regex.test(fullAddress)) {
                log_message(`Invalid address format: ${fullAddress}`, 'make-market.log', 'make-market', 'ERROR');
                console.error(`Invalid address format: ${fullAddress}`);
                resultDiv.innerHTML = '<p style="color: red;">Error: Unable to copy: Invalid address format</p>';
                resultDiv.classList.add('active');
                setTimeout(() => {
                    resultDiv.classList.remove('active');
                    resultDiv.innerHTML = '';
                }, 5000);
                return;
            }

            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' + fullAddress.substring(fullAddress.length - 4) : 'Invalid';
            log_message(`Attempting to copy address: ${shortAddress}`, 'make-market.log', 'make-market', 'DEBUG');
            console.log(`Attempting to copy address: ${shortAddress}`);

            if (navigator.clipboard && window.isSecureContext) {
                log_message('Using Clipboard API', 'make-market.log', 'make-market', 'DEBUG');
                console.log('Using Clipboard API');
                navigator.clipboard.writeText(fullAddress).then(() => {
                    showCopyFeedback(icon);
                }).catch(err => {
                    log_message(`Clipboard API failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
                    console.error('Clipboard API failed:', err.message);
                    fallbackCopy(fullAddress, icon);
                });
            } else {
                log_message('Clipboard API unavailable, using fallback', 'make-market.log', 'make-market', 'DEBUG');
                console.log('Clipboard API unavailable, using fallback');
                fallbackCopy(fullAddress, icon);
            }
        });
    });

    function fallbackCopy(text, icon) {
        const shortText = text.length >= 8 ? text.substring(0, 4) + '...' + text.substring(text.length - 4) : 'Invalid';
        log_message(`Using fallback copy for: ${shortText}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log(`Using fallback copy for: ${shortText}`);
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
            log_message(`Fallback copy result: ${success}`, 'make-market.log', 'make-market', 'DEBUG');
            console.log('Fallback copy result:', success);
            if (success) {
                showCopyFeedback(icon);
            } else {
                log_message('Fallback copy failed', 'make-market.log', 'make-market', 'ERROR');
                console.error('Fallback copy failed');
                resultDiv.innerHTML = '<p style="color: red;">Error: Unable to copy address: Copy error</p>';
                resultDiv.classList.add('active');
                setTimeout(() => {
                    resultDiv.classList.remove('active');
                    resultDiv.innerHTML = '';
                }, 5000);
            }
        } catch (err) {
            log_message(`Fallback copy error: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Fallback copy error:', err.message);
            resultDiv.innerHTML = `<p style="color: red;">Error: Unable to copy address: ${err.message}</p>`;
            resultDiv.classList.add('active');
            setTimeout(() => {
                resultDiv.classList.remove('active');
                resultDiv.innerHTML = '';
            }, 5000);
        } finally {
            document.body.removeChild(textarea);
        }
    }

    function showCopyFeedback(icon) {
        log_message('Showing copy feedback', 'make-market.log', 'make-market', 'DEBUG');
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
            log_message('Copy feedback removed', 'make-market.log', 'make-market', 'DEBUG');
            console.log('Copy feedback removed');
        }, 2000);
        log_message('Copy successful', 'make-market.log', 'make-market', 'INFO');
        console.log('Copy successful');
    }
});
