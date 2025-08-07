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
    let enhancedMessage = message;
    if (message.includes('Insufficient wallet balance') || message.includes('Insufficient SOL balance')) {
        enhancedMessage += ' <a href="https://www.binance.com/en" target="_blank">Top up SOL here</a>';
    } else if (message.includes('Insufficient token balance')) {
        enhancedMessage += ' <a href="https://www.binance.com/en" target="_blank">Top up tokens here</a>';
    }
    resultDiv.innerHTML = `<p>Error: ${enhancedMessage}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear notification</button>`;
    resultDiv.classList.add('active');
    document.querySelector('#makeMarketForm button').disabled = false;
}

// Handle form submission
document.getElementById('makeMarketForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const resultDiv = document.getElementById('mm-result');
    const submitButton = document.querySelector('#makeMarketForm button');
    submitButton.disabled = true;
    resultDiv.innerHTML = '<div class="spinner">Processing...</div>';
    resultDiv.classList.add('active');
    log_message('Form submitted', 'make-market.log', 'make-market', 'INFO');
    console.log('Form submitted');

    const formData = new FormData(e.target);
    const tokenAmountValue = formData.get('tokenAmount');
    log_message(`Raw tokenAmount value: ${tokenAmountValue}`, 'make-market.log', 'make-market', 'DEBUG');
    const params = {
        processName: formData.get('processName'),
        privateKey: formData.get('privateKey'),
        tokenMint: formData.get('tokenMint'),
        tradeDirection: formData.get('tradeDirection'),
        solAmount: parseFloat(formData.get('solAmount')) || 0,
        tokenAmount: tokenAmountValue !== null && tokenAmountValue !== '' ? parseFloat(tokenAmountValue) : 0,
        slippage: parseFloat(formData.get('slippage')) || 0.5,
        delay: parseInt(formData.get('delay')) || 0,
        loopCount: parseInt(formData.get('loopCount')) || 1,
        batchSize: parseInt(formData.get('batchSize')) || 5,
        csrf_token: formData.get('csrf_token')
    };
    log_message(`Form data: ${JSON.stringify(params)}`, 'make-market.log', 'make-market', 'DEBUG');
    console.log('Form data:', params);

    // Basic validation
    if (!params.privateKey || typeof params.privateKey !== 'string' || !/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{64,128}$/.test(params.privateKey)) {
        log_message('Private key is empty or invalid format', 'make-market.log', 'make-market', 'ERROR');
        showError('Private key is empty or invalid format. Please check again.');
        console.error('Private key is empty or invalid format');
        return;
    }
    if (isNaN(params.tokenAmount) || params.tokenAmount < 0) {
        log_message(`Token amount is invalid or negative: ${params.tokenAmount}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Token amount must be non-negative.');
        console.error('Token amount is invalid or negative');
        return;
    }
    if (params.tradeDirection === 'buy' && params.tokenAmount != 0) {
        log_message(`Invalid token amount for buy: ${params.tokenAmount}, must be exactly 0`, 'make-market.log', 'make-market', 'ERROR');
        showError('Token amount must be exactly 0 for buy transactions.');
        console.error('Invalid token amount for buy');
        return;
    }
    if (params.tradeDirection === 'sell' && params.tokenAmount <= 0) {
        log_message(`Invalid token amount for sell: ${params.tokenAmount}, must be greater than 0`, 'make-market.log', 'make-market', 'ERROR');
        showError('Token amount must be greater than 0 for sell transactions.');
        console.error('Invalid token amount for sell');
        return;
    }
    if (params.tradeDirection === 'both' && params.tokenAmount <= 0) {
        log_message(`Invalid token amount for both: ${params.tokenAmount}, must be greater than 0`, 'make-market.log', 'make-market', 'ERROR');
        showError('Token amount must be greater than 0 for both transactions.');
        console.error('Invalid token amount for both');
        return;
    }
    if (!params.tradeDirection || !['buy', 'sell', 'both'].includes(params.tradeDirection)) {
        log_message('Invalid trade direction', 'make-market.log', 'make-market', 'ERROR');
        showError('Please select a valid trade direction (Buy, Sell, or Both).');
        console.error('Invalid trade direction');
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
            log_message(`Error parsing JSON response: ${error.message}, Response: ${responseText}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Error parsing JSON response:', error.message, 'Response:', responseText);
            showError('Invalid response from server. Please try again.');
            return;
        }
        if (result.status !== 'success') {
            log_message(`Form submission failed: ${result.message}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Form submission failed:', result.message);
            showError(result.message); // Display detailed error message from server
            return;
        }
        log_message(`Form saved to database: transactionId=${result.transactionId}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Form saved to database: transactionId=', result.transactionId);
        // Redirect to process page
        const redirectUrl = result.redirect || `/make-market/process/${result.transactionId}`;
        log_message(`Redirecting to ${redirectUrl}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Redirecting to', redirectUrl);
        setTimeout(() => {
            window.location.href = redirectUrl;
            console.log('Redirect executed to', redirectUrl);
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

            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
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
